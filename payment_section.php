<?php
// payment_section.php - Multi-Mode Real-Time Payment Terminal
require_once 'config.php';

if (!isset($_SESSION['patient_id'])) {
    header("Location: index.php");
    exit;
}

$patient_id = (int)$_SESSION['patient_id'];
$patient = getPatientById($pdo, $patient_id);

// Check if a specific bill is requested
$req_bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : (isset($_POST['bill_id']) ? (int)$_POST['bill_id'] : 0);

if ($req_bill_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM bills WHERE id = ? AND patient_id = ? AND paid_status = 'pending'");
    $stmt->execute([$req_bill_id, $patient_id]);
    $pending_bill = $stmt->fetch();
} else {
    // Get latest pending bill
    $stmt = $pdo->prepare("SELECT * FROM bills WHERE patient_id = ? AND paid_status = 'pending' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$patient_id]);
    $pending_bill = $stmt->fetch();
}

// Auto-create a pending consultation bill if none exists
if (!$pending_bill) {
    $description = "Orthopaedic Consultation & Assessment";
    $amount = 500.00;
    $stmt = $pdo->prepare("INSERT INTO bills (patient_id, description, amount, paid_status, bill_date) VALUES (?, ?, ?, 'pending', CURDATE())");
    $stmt->execute([$patient_id, $description, $amount]);
    $new_bill_id = $pdo->lastInsertId();

    $stmt = $pdo->prepare("SELECT * FROM bills WHERE id = ?");
    $stmt->execute([$new_bill_id]);
    $pending_bill = $stmt->fetch();
}

/**
 * Call the Cashfree Orders API to create an order and get a payment_session_id.
 */
function cashfree_create_order($pdo, $patient, $pending_bill) {
    $order_id = 'ORD_' . $pending_bill['id'] . '_' . time();

    // Accurately construct return URL with correct protocol and directory structure
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
    $script_dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $return_url = $protocol . $_SERVER['HTTP_HOST'] . $script_dir . "/payment_verify.php?order_id={order_id}";

    $payload = [
        "order_id" => $order_id,
        "order_amount" => (float)$pending_bill['amount'],
        "order_currency" => "INR",
        "customer_details" => [
            "customer_id" => "patient_" . $patient['id'],
            "customer_name" => $patient['name'],
            "customer_email" => !empty($patient['email']) ? $patient['email'] : 'patient' . $patient['id'] . '@orthokiosk.com',
            "customer_phone" => !empty($patient['phone']) ? preg_replace('/[^0-9]/', '', $patient['phone']) : '9876543210',
        ],
        "order_meta" => [
            "return_url" => $return_url,
        ],
        "order_note" => $pending_bill['description'],
    ];

    $ch = curl_init(CASHFREE_BASE_URL . "/orders");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "x-api-version: " . CASHFREE_API_VERSION,
            "x-client-id: " . CASHFREE_APP_ID,
            "x-client-secret: " . CASHFREE_SECRET_KEY,
        ],
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        return ['error' => "Gateway Connection: $curl_err"];
    }

    $data = json_decode($response, true);

    if ($http_code !== 200 || empty($data['payment_session_id'])) {
        $msg = $data['message'] ?? ('Unexpected response from Cashfree (HTTP ' . $http_code . ')');
        return ['error' => $msg];
    }

    // Store mapping in bills table
    $upd = $pdo->prepare("UPDATE bills SET cf_order_id = ? WHERE id = ?");
    $upd->execute([$order_id, $pending_bill['id']]);

    return [
        'order_id' => $order_id,
        'payment_session_id' => $data['payment_session_id'],
    ];
}

/**
 * Call Cashfree Order Pay API to generate a dynamic UPI QR Code base64 image
 */
function cashfree_generate_upi_qr($session_id) {
    if (empty($session_id)) return null;

    $payPayload = [
        "payment_session_id" => $session_id,
        "payment_method" => [
            "upi" => [
                "channel" => "qrcode"
            ]
        ]
    ];

    $ch = curl_init(CASHFREE_BASE_URL . "/orders/sessions");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payPayload),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "x-api-version: " . CASHFREE_API_VERSION,
            "x-client-id: " . CASHFREE_APP_ID,
            "x-client-secret: " . CASHFREE_SECRET_KEY,
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && !empty($response)) {
        $json = json_decode($response, true);
        if (!empty($json['data']['payload']['qrcode'])) {
            return $json['data']['payload']['qrcode'];
        }
    }
    return null;
}

$cf_error = null;
$cf_session = null;
$cf_qr_code = null;

if ($pending_bill) {
    $result = cashfree_create_order($pdo, $patient, $pending_bill);
    if (isset($result['error'])) {
        $cf_error = $result['error'];
    } else {
        $cf_session = $result;
        $cf_qr_code = cashfree_generate_upi_qr($cf_session['payment_session_id']);
    }
}

// Fallback UPI string for kiosk display if gateway QR is temporarily unavailable
$upi_pa = "cashfree@testbank";
$upi_pn = "Ortho Kiosk Clinic";
$upi_am = number_format($pending_bill['amount'], 2, '.', '');
$upi_tn = "Bill-" . $pending_bill['id'];
$upi_uri = "upi://pay?pa={$upi_pa}&pn=" . urlencode($upi_pn) . "&am={$upi_am}&cu=INR&tn=" . urlencode($upi_tn);
$display_qr_url = !empty($cf_qr_code) ? $cf_qr_code : ("https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=" . urlencode($upi_uri));
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars(t('payment_title')) ?> | OrthoKiosk</title>
    <link rel="stylesheet" href="style.css">
    <script src="<?= htmlspecialchars(CASHFREE_JS_SDK) ?>"></script>
    <style>
        .payment-wrapper {
            max-width: 820px;
            margin: 1.5rem auto 3rem;
        }
        .payment-card {
            background: #ffffff;
            border-radius: 28px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .payment-header-strip {
            background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%);
            color: #ffffff;
            padding: 2rem 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .payment-header-strip h1 {
            margin: 0;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .payment-header-strip p {
            margin: 0.3rem 0 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }
        .payment-body {
            padding: 2rem 2.5rem;
        }
        .invoice-summary-box {
            background: #f8fafc;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            padding: 1.6rem;
            margin-bottom: 2rem;
        }
        .invoice-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
        }
        .invoice-row {
            display: flex;
            justify-content: space-between;
            padding: 0.6rem 0;
            border-bottom: 1px solid #edf2f7;
            font-size: 0.95rem;
        }
        .invoice-row:last-child {
            border-bottom: none;
        }
        .invoice-label {
            color: #64748b;
        }
        .invoice-val {
            font-weight: 600;
            color: #0f172a;
        }
        .invoice-total-row {
            margin-top: 0.8rem;
            padding-top: 0.8rem;
            border-top: 2px dashed #cbd5e1;
            font-size: 1.25rem;
            font-weight: 800;
            color: #16a34a;
        }

        /* Payment Method Tabs */
        .method-tabs {
            display: flex;
            gap: 0.8rem;
            margin-bottom: 1.8rem;
            flex-wrap: wrap;
        }
        .method-tab-btn {
            flex: 1;
            min-width: 200px;
            padding: 1rem 1.2rem;
            border-radius: 16px;
            border: 2px solid #e2e8f0;
            background: #ffffff;
            font-size: 0.95rem;
            font-weight: 700;
            color: #334155;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
        }
        .method-tab-btn:hover {
            border-color: #94a3b8;
            background: #f8fafc;
        }
        .method-tab-btn.active {
            border-color: #2563eb;
            background: #eff6ff;
            color: #1d4ed8;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.12);
        }

        .tab-panel {
            display: none;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 2rem;
            animation: fadeIn 0.3s ease;
        }
        .tab-panel.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .pay-primary-btn {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: #ffffff;
            width: 100%;
            padding: 1.1rem 2rem;
            border: none;
            border-radius: 40px;
            font-size: 1.2rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 8px 20px rgba(22, 163, 74, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }
        .pay-primary-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(22, 163, 74, 0.4);
        }
        .pay-primary-btn:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            box-shadow: none;
        }

        .qr-display-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 1.2rem;
        }
        .qr-frame {
            padding: 15px;
            background: #ffffff;
            border: 3px dashed #2563eb;
            border-radius: 24px;
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.1);
            display: inline-block;
        }
        .qr-frame img {
            display: block;
            border-radius: 12px;
            max-width: 220px;
            height: auto;
        }
        .qr-apps-row {
            display: flex;
            gap: 1rem;
            justify-content: center;
            font-size: 0.88rem;
            color: #475569;
            font-weight: 600;
        }

        .pos-hardware-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 1.2rem;
            padding: 1.5rem;
        }
        .pos-terminal-graphic {
            font-size: 4.5rem;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 1.8rem;
            text-align: center;
        }
        .cf-alert-error {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #b91c1c;
            padding: 1rem 1.4rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
<div class="kiosk-container">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <div class="main-content">
        <div class="payment-wrapper">
            <div class="payment-card">
                <div class="payment-header-strip">
                    <div>
                        <h1>💳 <?= htmlspecialchars(t('payment_title')) ?></h1>
                        <p><?= htmlspecialchars(t('payment_subtitle')) ?></p>
                    </div>
                    <div style="background: rgba(255,255,255,0.18); padding: 0.6rem 1.2rem; border-radius: 16px; font-weight: 700;">
                        <span>👤 <?= htmlspecialchars($patient['name']) ?></span>
                        <span style="opacity: 0.85; margin-left: 0.4rem;">#<?= $patient['id'] ?></span>
                    </div>
                </div>

                <div class="payment-body">
                    <!-- Bill Breakdown -->
                    <div class="invoice-summary-box">
                        <div class="invoice-title">
                            <span>📋 <?= htmlspecialchars(t('invoice_summary')) ?></span>
                            <span style="font-size: 0.9rem; color: #dc2626; font-weight: 700;">● Pending Payment</span>
                        </div>

                        <div class="invoice-row">
                            <span class="invoice-label"><?= htmlspecialchars(t('service_label')) ?></span>
                            <span class="invoice-val"><?= htmlspecialchars($pending_bill['description']) ?></span>
                        </div>
                        <div class="invoice-row">
                            <span class="invoice-label"><?= htmlspecialchars(t('bill_date_label')) ?></span>
                            <span class="invoice-val"><?= date('d M Y', strtotime($pending_bill['bill_date'])) ?></span>
                        </div>
                        <div class="invoice-row">
                            <span class="invoice-label"><?= htmlspecialchars(t('order_ref')) ?></span>
                            <span class="invoice-val" style="font-family:monospace;"><?= htmlspecialchars($pending_bill['cf_order_id'] ?? 'ORD_' . $pending_bill['id']) ?></span>
                        </div>
                        <div class="invoice-row invoice-total-row">
                            <span><?= htmlspecialchars(t('total_amount_label')) ?></span>
                            <span>₹<?= number_format($pending_bill['amount'], 2) ?></span>
                        </div>
                    </div>

                    <!-- Payment Mode Selection Tabs -->
                    <h3 style="margin-bottom: 1rem; color: #1e293b; font-size: 1.15rem;">
                        👉 <?= htmlspecialchars(t('payment_options')) ?>
                    </h3>
                    <div class="method-tabs">
                        <button type="button" class="method-tab-btn active" onclick="switchTab('cashfree')">
                            <?= htmlspecialchars(t('pay_via_cashfree')) ?>
                        </button>
                        <button type="button" class="method-tab-btn" onclick="switchTab('qr')">
                            <?= htmlspecialchars(t('pay_via_qr')) ?>
                        </button>
                        <button type="button" class="method-tab-btn" onclick="switchTab('pos')">
                            <?= htmlspecialchars(t('pay_via_pos')) ?>
                        </button>
                    </div>

                    <!-- TAB 1: CASHFREE ONLINE GATEWAY (SDK v3) -->
                    <div id="tab-cashfree" class="tab-panel active">
                        <?php if ($cf_error): ?>
                            <div class="cf-alert-error">
                                <strong>⚠️ Gateway Notice:</strong> <?= htmlspecialchars($cf_error) ?><br>
                                <em>You can switch to the "Dynamic UPI QR" or "POS Terminal" tabs below for immediate payment.</em>
                            </div>
                        <?php endif; ?>

                        <p style="color: #475569; margin-bottom: 1.5rem; line-height: 1.6;">
                            🔒 Direct checkout with <strong>Cashfree Payment Gateway</strong>. Supports Google Pay, PhonePe, Paytm UPI, Debit/Credit Cards (Visa, RuPay, MasterCard), Net Banking, and Healthcare Wallets.
                        </p>

                        <button id="cfPayBtn" class="pay-primary-btn" <?= $cf_error ? 'disabled' : '' ?>>
                            <span>🚀</span>
                            <span><?= htmlspecialchars(t('pay_now_btn')) ?> — ₹<?= number_format($pending_bill['amount'], 2) ?></span>
                        </button>
                    </div>

                    <!-- TAB 2: DYNAMIC KIOSK UPI QR CODE (CASHFREE POWERED) -->
                    <div id="tab-qr" class="tab-panel">
                        <div class="qr-display-container">
                            <div style="display:inline-flex; align-items:center; gap:0.4rem; background:#f0fdf4; color:#166534; padding:0.35rem 0.85rem; border-radius:999px; font-size:0.85rem; font-weight:700; margin-bottom:0.8rem; border:1px solid #bbf7d0;">
                                <span>🔒</span> <span>Cashfree Dynamic UPI QR Code</span>
                            </div>
                            <p style="color: #334155; margin-bottom: 0.5rem; font-weight: 600;">
                                <?= htmlspecialchars(t('scan_qr_desc')) ?>
                            </p>
                            <div class="qr-frame" style="position:relative; background:#ffffff; padding:12px; border-radius:18px; box-shadow:0 8px 24px rgba(0,0,0,0.06); display:inline-block;">
                                <img id="kioskQrImage" src="<?= htmlspecialchars($display_qr_url) ?>" alt="Cashfree Dynamic UPI QR Code" style="width:230px; height:230px; display:block;">
                            </div>
                            <div class="qr-apps-row" style="margin-top:0.7rem;">
                                <span>📱 Google Pay</span> • <span>🟣 PhonePe</span> • <span>🔵 Paytm</span> • <span>🇮🇳 BHIM UPI</span>
                            </div>

                            <!-- Live Auto-Detection Status Indicator -->
                            <div id="qrLiveStatus" style="margin: 0.9rem 0; padding: 0.65rem 1.1rem; border-radius: 12px; background: #e0f2fe; color: #0369a1; font-size: 0.88rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.6rem; transition: all 0.3s ease;">
                                <span class="pulse-dot" style="display:inline-block; width:10px; height:10px; background:#0284c7; border-radius:50%;"></span>
                                <span id="qrStatusText">Listening for QR payment on your phone... (Auto-settles)</span>
                            </div>

                            <p style="font-size: 0.85rem; color: #64748b; margin-top: 0.2rem;">
                                Order Reference: <code><?= htmlspecialchars($cf_session['order_id'] ?? ('ORD_' . $pending_bill['id'])) ?></code> | Total: <strong>₹<?= number_format($pending_bill['amount'], 2) ?></strong>
                            </p>

                            <!-- Instant Simulator Button for Touchscreen Evaluation -->
                            <form method="POST" action="payment_verify.php" style="width: 100%; margin-top: 1rem;">
                                <input type="hidden" name="action" value="simulate_payment">
                                <input type="hidden" name="bill_id" value="<?= $pending_bill['id'] ?>">
                                <input type="hidden" name="method" value="Cashfree Dynamic UPI QR">
                                <button type="submit" class="pay-primary-btn" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);">
                                    <span>📲</span>
                                    <span><?= htmlspecialchars(t('simulate_qr_pay')) ?></span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- TAB 3: KIOSK POS TERMINAL (CHIP / CONTACTLESS NFC) -->
                    <div id="tab-pos" class="tab-panel">
                        <div class="pos-hardware-box">
                            <div class="pos-terminal-graphic">📟</div>
                            <h4 style="margin: 0; font-size: 1.25rem; color: #1e293b;">Integrated Kiosk Smart POS</h4>
                            <p style="color: #475569; max-width: 480px; margin: 0 auto; line-height: 1.5;">
                                <?= htmlspecialchars(t('pos_desc')) ?>
                            </p>
                            <div style="background: #f1f5f9; padding: 0.8rem 1.5rem; border-radius: 12px; font-weight: 600; color: #334155;">
                                Supported: RuPay • Visa • MasterCard • Contactless NFC Tap
                            </div>

                            <form method="POST" action="payment_verify.php" style="width: 100%; margin-top: 1.2rem;">
                                <input type="hidden" name="action" value="simulate_payment">
                                <input type="hidden" name="bill_id" value="<?= $pending_bill['id'] ?>">
                                <input type="hidden" name="method" value="Card POS Terminal">
                                <button type="submit" class="pay-primary-btn" style="background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);">
                                    <span>💳</span>
                                    <span><?= htmlspecialchars(t('tap_pay_btn')) ?></span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="security-badge">
                        <span>🔒</span>
                        <span><?= htmlspecialchars(t('pay_secure_note')) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Tab Switching
function switchTab(tabName) {
    document.querySelectorAll('.method-tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));

    if (tabName === 'cashfree') {
        document.querySelector('.method-tab-btn:nth-child(1)').classList.add('active');
        document.getElementById('tab-cashfree').classList.add('active');
    } else if (tabName === 'qr') {
        document.querySelector('.method-tab-btn:nth-child(2)').classList.add('active');
        document.getElementById('tab-qr').classList.add('active');
    } else if (tabName === 'pos') {
        document.querySelector('.method-tab-btn:nth-child(3)').classList.add('active');
        document.getElementById('tab-pos').classList.add('active');
    }
}

// Cashfree JS SDK Checkout
<?php if (!$cf_error && !empty($cf_session['payment_session_id'])): ?>
const cashfree = Cashfree({
    mode: "<?= htmlspecialchars(CASHFREE_MODE) ?>"
});

const payBtn = document.getElementById("cfPayBtn");
if (payBtn) {
    payBtn.addEventListener("click", function () {
        this.disabled = true;
        this.innerHTML = "<span>⏳</span> <span>Connecting to Secure Gateway...</span>";

        cashfree.checkout({
            paymentSessionId: "<?= htmlspecialchars($cf_session['payment_session_id']) ?>",
            redirectTarget: "_self"
        }).then(function (result) {
            if (result.error) {
                alert("Payment error: " + result.error.message);
                payBtn.disabled = false;
                payBtn.innerHTML = "<span>🚀</span> <span><?= htmlspecialchars(t('pay_now_btn')) ?></span>";
            }
        });
    });
}
<?php endif; ?>

// Real-Time Background Auto-Polling for Cashfree Dynamic QR Payment
const currentOrderId = "<?= !empty($cf_session['order_id']) ? htmlspecialchars($cf_session['order_id']) : '' ?>";
let pollIntervalTimer = null;

function pollCashfreeStatus() {
    if (!currentOrderId) return;

    fetch(`api_payment_status.php?order_id=${encodeURIComponent(currentOrderId)}`)
        .then(res => res.json())
        .then(data => {
            if (data.paid || data.status === 'PAID') {
                clearInterval(pollIntervalTimer);
                const statusBox = document.getElementById('qrLiveStatus');
                const statusText = document.getElementById('qrStatusText');
                if (statusBox && statusText) {
                    statusBox.style.background = '#dcfce7';
                    statusBox.style.color = '#15803d';
                    statusBox.style.border = '1px solid #86efac';
                    statusText.innerHTML = "🎉 <strong>Payment Confirmed via Cashfree!</strong> Generating receipt...";
                }
                setTimeout(() => {
                    const dest = data.redirect_url ? data.redirect_url : `payment_verify.php?order_id=${encodeURIComponent(currentOrderId)}`;
                    window.location.href = dest;
                }, 900);
            }
        })
        .catch(err => {
            console.debug("Payment poll note:", err);
        });
}

if (currentOrderId) {
    // Poll every 2.5 seconds
    pollIntervalTimer = setInterval(pollCashfreeStatus, 2500);
}
</script>
</body>
</html>