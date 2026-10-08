<?php
// receipt.php - Official Printable Payment Receipt
require_once 'config.php';

if (!isset($_SESSION['patient_id'])) { 
    header("Location: index.php"); 
    exit; 
}

$patient_id = (int)$_SESSION['patient_id'];
$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM bills WHERE id = ? AND patient_id = ? AND paid_status = 'paid'");
$stmt->execute([$bill_id, $patient_id]);
$bill = $stmt->fetch();

if (!$bill) {
    die("Receipt not found or the bill has not been settled yet. <a href='billing.php'>Return to Billing</a>");
}

// Get latest payment details for this bill
$stmt = $pdo->prepare("SELECT * FROM payments WHERE bill_id = ? ORDER BY payment_date DESC LIMIT 1");
$stmt->execute([$bill_id]);
$payment = $stmt->fetch();

$patient = getPatientById($pdo, $patient_id);
$txn_id = $payment['transaction_id'] ?? ($bill['cf_order_id'] ?? 'TXN' . time());
$payment_method = $payment['payment_method'] ?? 'Cashfree Real-Time PG';
$payment_date = $payment['payment_date'] ?? $bill['bill_date'];

$receipt_no = "RCP-" . str_pad($bill['id'], 6, '0', STR_PAD_LEFT);

// Verification QR code for receipt
$verify_text = "OrthoKiosk Receipt Verification | No: {$receipt_no} | Patient: {$patient['name']} | Amount: Rs. {$bill['amount']} | Txn: {$txn_id}";
$qr_verify_url = "https://api.qrserver.com/v1/create-qr-code/?size=130x130&data=" . urlencode($verify_text);
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(t('official_receipt')) ?> - <?= $receipt_no ?> | OrthoKiosk</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .receipt-card-wrapper {
            max-width: 680px;
            margin: 2rem auto;
            background: #ffffff;
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            position: relative;
        }
        .receipt-clinic-header {
            text-align: center;
            border-bottom: 2px dashed #cbd5e1;
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .receipt-clinic-header h1 {
            color: #1e3a8a;
            margin: 0;
            font-size: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .receipt-clinic-header p {
            margin: 0.3rem 0 0;
            color: #64748b;
            font-size: 0.9rem;
        }
        .receipt-meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            background: #f8fafc;
            border-radius: 16px;
            padding: 1.2rem;
            margin-bottom: 1.5rem;
            font-size: 0.92rem;
        }
        .receipt-meta-item strong {
            display: block;
            color: #64748b;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.2rem;
        }
        .receipt-meta-item span {
            color: #0f172a;
            font-weight: 700;
        }
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
        }
        .receipt-table th {
            text-align: left;
            background: #f1f5f9;
            padding: 0.75rem 1rem;
            color: #475569;
            font-size: 0.85rem;
            text-transform: uppercase;
            border-radius: 8px 8px 0 0;
        }
        .receipt-table td {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.95rem;
            color: #1e293b;
        }
        .receipt-total-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #ecfdf5;
            border-radius: 12px;
            margin-top: 1rem;
        }
        .receipt-total-bar span:first-child {
            font-size: 1.1rem;
            font-weight: 700;
            color: #065f46;
        }
        .receipt-total-bar span:last-child {
            font-size: 1.5rem;
            font-weight: 900;
            color: #059669;
        }

        .receipt-footer-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 2px dashed #cbd5e1;
            font-size: 0.85rem;
            color: #64748b;
        }
        .qr-seal img {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .btn-print-action {
            background: #2563eb;
            color: #ffffff;
            padding: 0.9rem 1.8rem;
            border: none;
            border-radius: 999px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }
        .btn-print-action:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
        }

        @media print {
            body {
                background: #ffffff !important;
            }
            .kiosk-top-bar, .kiosk-nav-bar, .non-printable, .btn-print-action {
                display: none !important;
            }
            .receipt-card-wrapper {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body>
<div class="kiosk-container">
    <div class="non-printable">
        <?php include 'includes/header.php'; ?>
        <?php include 'includes/navbar.php'; ?>
    </div>

    <div class="main-content">
        <div class="receipt-card-wrapper">
            <!-- Clinic Official Header -->
            <div class="receipt-clinic-header">
                <h1>🦴 OrthoKiosk Clinic</h1>
                <p><?= htmlspecialchars(t('clinic_tagline')) ?></p>
                <p><?= htmlspecialchars(t('support_contact')) ?></p>
            </div>

            <!-- Receipt Meta Info Grid -->
            <div class="receipt-meta-grid">
                <div class="receipt-meta-item">
                    <strong><?= htmlspecialchars(t('receipt_no')) ?></strong>
                    <span><?= htmlspecialchars($receipt_no) ?></span>
                </div>
                <div class="receipt-meta-item">
                    <strong><?= htmlspecialchars(t('date')) ?></strong>
                    <span><?= date('d M Y, h:i A', strtotime($payment_date)) ?></span>
                </div>
                <div class="receipt-meta-item">
                    <strong>Patient Name</strong>
                    <span><?= htmlspecialchars($patient['name']) ?> (ID: #<?= $patient['id'] ?>)</span>
                </div>
                <div class="receipt-meta-item">
                    <strong>Contact Phone</strong>
                    <span><?= htmlspecialchars($patient['phone']) ?></span>
                </div>
            </div>

            <!-- Invoiced Services -->
            <table class="receipt-table">
                <thead>
                    <tr>
                        <th>Particulars / Description</th>
                        <th style="text-align: right;">Amount (INR)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($bill['description']) ?></strong><br>
                            <small style="color: #64748b;">Includes orthopaedic assessment intake, doctor referral & digital report</small>
                        </td>
                        <td style="text-align: right; font-weight: 700;">
                            ₹<?= number_format($bill['amount'], 2) ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Total Paid Strip -->
            <div class="receipt-total-bar">
                <span><?= htmlspecialchars(t('amount_paid')) ?></span>
                <span>₹<?= number_format($bill['amount'], 2) ?></span>
            </div>

            <!-- Transaction Audit Info -->
            <div style="margin-top: 1.2rem; background: #f8fafc; border-radius: 12px; padding: 1rem; font-size: 0.85rem; color: #475569;">
                <div><strong><?= htmlspecialchars(t('txn_id')) ?>:</strong> <code style="font-family: monospace; font-size: 0.9rem;"><?= htmlspecialchars($txn_id) ?></code></div>
                <div style="margin-top: 0.3rem;"><strong><?= htmlspecialchars(t('payment_mode')) ?>:</strong> <?= htmlspecialchars($payment_method) ?></div>
                <div style="margin-top: 0.3rem;"><strong>Settlement Status:</strong> <span style="color: #059669; font-weight: 700;">✅ Real-Time Authorized & Confirmed</span></div>
            </div>

            <!-- Footer & Digital Seal -->
            <div class="receipt-footer-flex">
                <div>
                    <p style="margin: 0 0 0.4rem; font-weight: 600; color: #1e293b;">Thank you for choosing OrthoKiosk.</p>
                    <p style="margin: 0; font-size: 0.78rem;"><?= htmlspecialchars(t('computer_generated_note')) ?></p>
                </div>
                <div class="qr-seal">
                    <img src="<?= htmlspecialchars($qr_verify_url) ?>" width="90" height="90" alt="Verification QR Code">
                </div>
            </div>

            <!-- Action Buttons for Kiosk Interaction -->
            <div class="non-printable" style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <button onclick="window.print()" class="btn-print-action">
                    <span>🖨️</span>
                    <span>Print Receipt</span>
                </button>
                <a href="billing.php" class="btn" style="background: #f1f5f9; color: #1e3a5f; border: 1px solid #cbd5e1; padding: 0.9rem 1.6rem; border-radius: 999px; text-decoration: none; font-weight: 700;">
                    ← Back to Billing
                </a>
                <a href="dashboard.php" class="btn" style="background: #10b981; color: #ffffff; padding: 0.9rem 1.6rem; border-radius: 999px; text-decoration: none; font-weight: 700;">
                    🏠 Kiosk Home
                </a>
            </div>
        </div>
    </div>
</div>
</body>
</html>