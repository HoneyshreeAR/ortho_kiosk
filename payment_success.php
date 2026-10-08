<?php
// payment_success.php - Real-Time Payment Success & Confirmation Screen
require_once 'config.php';

if (!isset($_SESSION['patient_id'])) {
    header("Location: index.php");
    exit;
}

$patient_id = (int)$_SESSION['patient_id'];
$patient = getPatientById($pdo, $patient_id);

$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : (int)($_SESSION['last_payment']['bill_id'] ?? 0);

if ($bill_id <= 0) {
    // If no specific bill_id, find latest paid bill
    $stmt = $pdo->prepare("SELECT * FROM bills WHERE patient_id = ? AND paid_status = 'paid' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$patient_id]);
    $bill = $stmt->fetch();
} else {
    $stmt = $pdo->prepare("SELECT * FROM bills WHERE id = ? AND patient_id = ?");
    $stmt->execute([$bill_id, $patient_id]);
    $bill = $stmt->fetch();
}

if (!$bill) {
    header("Location: billing.php");
    exit;
}

// Fetch latest payment record for this bill
$stmt = $pdo->prepare("SELECT * FROM payments WHERE bill_id = ? ORDER BY payment_date DESC LIMIT 1");
$stmt->execute([$bill['id']]);
$payment = $stmt->fetch();

$transaction_id = $payment['transaction_id'] ?? ($_SESSION['last_payment']['transaction_id'] ?? ($bill['cf_order_id'] ?? 'TXN' . time()));
$payment_method = $payment['payment_method'] ?? ($_SESSION['last_payment']['payment_method'] ?? 'Cashfree Payment Gateway');
$payment_date = $payment['payment_date'] ?? ($_SESSION['last_payment']['payment_date'] ?? date('Y-m-d H:i:s'));

// Check for linked assessment
$assessment_id = isset($_GET['assessment_id']) ? (int)$_GET['assessment_id'] : (int)($_SESSION['last_payment']['assessment_id'] ?? 0);
if ($assessment_id <= 0) {
    $latest_ass = getLatestAssessment($pdo, $patient_id);
    if ($latest_ass) {
        $assessment_id = (int)$latest_ass['id'];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars(t('payment_success_title')) ?> | OrthoKiosk</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .success-wrapper {
            max-width: 760px;
            margin: 2rem auto;
            text-align: center;
        }
        .success-card {
            background: #ffffff;
            border-radius: 28px;
            padding: 3rem 2.5rem;
            box-shadow: 0 16px 40px rgba(16, 185, 129, 0.12), 0 4px 16px rgba(0,0,0,0.05);
            border: 1px solid #d1fae5;
            position: relative;
            overflow: hidden;
        }
        .success-badge {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.35);
            animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes popIn {
            0% { transform: scale(0.3); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .success-title {
            font-size: 2.2rem;
            font-weight: 800;
            color: #065f46;
            margin-bottom: 0.5rem;
        }
        .success-subtitle {
            font-size: 1.1rem;
            color: #64748b;
            margin-bottom: 2rem;
        }
        .receipt-summary-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.8rem;
            margin-bottom: 2rem;
            text-align: left;
        }
        .receipt-summary-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px dashed #cbd5e1;
            padding-bottom: 0.8rem;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.65rem 0;
            font-size: 0.98rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .summary-item:last-child {
            border-bottom: none;
        }
        .summary-label {
            color: #64748b;
            font-weight: 500;
        }
        .summary-val {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
        }
        .amount-highlight {
            font-size: 1.5rem;
            color: #059669;
            font-weight: 900;
        }
        .kiosk-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .action-btn-tile {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 1.1rem 1.4rem;
            border-radius: 18px;
            font-size: 1rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .action-btn-primary {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
        }
        .action-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
        }
        .action-btn-secondary {
            background: #ffffff;
            color: #1e3a5f;
            border: 2px solid #cbd5e1;
        }
        .action-btn-secondary:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            transform: translateY(-2px);
        }
        .action-btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
        }
        .action-btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }
        .security-stamp {
            margin-top: 2rem;
            font-size: 0.85rem;
            color: #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
<div class="kiosk-container">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <div class="main-content">
        <div class="success-wrapper">
            <div class="success-card">
                <div class="success-badge">✓</div>
                <h1 class="success-title"><?= htmlspecialchars(t('payment_success_title')) ?></h1>
                <p class="success-subtitle"><?= htmlspecialchars(t('payment_success_sub')) ?></p>

                <!-- Transaction Summary Breakdown -->
                <div class="receipt-summary-box">
                    <div class="receipt-summary-title">
                        <span>🧾 <?= htmlspecialchars(t('invoice_summary')) ?></span>
                        <span style="color:#059669; font-size:0.9rem;">● <?= htmlspecialchars(t('txn_successful')) ?></span>
                    </div>

                    <div class="summary-item">
                        <span class="summary-label"><?= htmlspecialchars(t('service_label')) ?></span>
                        <span class="summary-val"><?= htmlspecialchars($bill['description']) ?></span>
                    </div>

                    <div class="summary-item">
                        <span class="summary-label"><?= htmlspecialchars(t('receipt_no')) ?></span>
                        <span class="summary-val">RCP-<?= str_pad($bill['id'], 6, '0', STR_PAD_LEFT) ?></span>
                    </div>

                    <div class="summary-item">
                        <span class="summary-label"><?= htmlspecialchars(t('txn_id')) ?></span>
                        <span class="summary-val" style="font-family:monospace;"><?= htmlspecialchars($transaction_id) ?></span>
                    </div>

                    <div class="summary-item">
                        <span class="summary-label"><?= htmlspecialchars(t('payment_mode')) ?></span>
                        <span class="summary-val"><?= htmlspecialchars($payment_method) ?></span>
                    </div>

                    <div class="summary-item">
                        <span class="summary-label"><?= htmlspecialchars(t('date')) ?></span>
                        <span class="summary-val"><?= date('d M Y, h:i A', strtotime($payment_date)) ?></span>
                    </div>

                    <div class="summary-item" style="margin-top:0.8rem; padding-top:0.8rem; border-top: 2px dashed #cbd5e1;">
                        <span class="summary-label" style="font-size:1.1rem; color:#1e293b; font-weight:700;"><?= htmlspecialchars(t('amount_paid')) ?></span>
                        <span class="summary-val amount-highlight">₹<?= number_format($bill['amount'], 2) ?></span>
                    </div>
                </div>

                <!-- Next Steps Action Grid -->
                <div class="kiosk-actions-grid">
                    <?php if ($assessment_id > 0): ?>
                    <a href="assessment_report.php?id=<?= $assessment_id ?>" class="action-btn-tile action-btn-primary">
                        <span>🩺</span>
                        <span><?= htmlspecialchars(t('view_report')) ?></span>
                    </a>
                    <?php endif; ?>

                    <a href="receipt.php?bill_id=<?= $bill['id'] ?>" class="action-btn-tile action-btn-success">
                        <span>📄</span>
                        <span><?= htmlspecialchars(t('view_receipt_btn')) ?></span>
                    </a>

                    <a href="food_suggestions.php" class="action-btn-tile action-btn-secondary">
                        <span>🥗</span>
                        <span><?= htmlspecialchars(t('view_diet_plan')) ?></span>
                    </a>

                    <a href="reminders.php" class="action-btn-tile action-btn-secondary">
                        <span>💊</span>
                        <span><?= htmlspecialchars(t('nav_reminders')) ?></span>
                    </a>

                    <a href="dashboard.php" class="action-btn-tile action-btn-secondary" style="grid-column: 1 / -1;">
                        <span>🏠</span>
                        <span><?= htmlspecialchars(t('return_dashboard')) ?></span>
                    </a>
                </div>

                <div class="security-stamp">
                    <span>🔒</span>
                    <span><?= htmlspecialchars(t('computer_generated_note')) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>