<?php
// billing.php - View Bills, Real-Time Payment Access & Printable Receipts
require_once 'config.php';

if (!isset($_SESSION['patient_id'])) { 
    header("Location: index.php"); 
    exit; 
}

$patient_id = (int)$_SESSION['patient_id'];
$patient = getPatientById($pdo, $patient_id);

// Get flash messages
$payment_success = $_SESSION['payment_success'] ?? null;
$payment_error = $_SESSION['payment_error'] ?? null;
unset($_SESSION['payment_success'], $_SESSION['payment_error']);

// Get all bills for this patient
$stmt = $pdo->prepare("SELECT * FROM bills WHERE patient_id = ? ORDER BY bill_date DESC, id DESC");
$stmt->execute([$patient_id]);
$bills = $stmt->fetchAll();

// Calculate totals
$total_pending = 0;
$total_paid = 0;
$pending_count = 0;

foreach ($bills as $bill) {
    if ($bill['paid_status'] === 'pending') {
        $total_pending += $bill['amount'];
        $pending_count++;
    } else {
        $total_paid += $bill['amount'];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars(t('nav_billing')) ?> | OrthoKiosk</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .billing-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.2rem;
            margin-bottom: 2rem;
        }
        .summary-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }
        .summary-icon {
            font-size: 2.2rem;
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .summary-num {
            font-size: 1.6rem;
            font-weight: 800;
            color: #1e293b;
            line-height: 1.2;
        }
        .summary-label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 600;
        }

        .bill-card-item {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.5rem 1.8rem;
            margin-bottom: 1.2rem;
            box-shadow: 0 4px 14px rgba(0,0,0,0.03);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.2rem;
            transition: transform 0.2s ease;
        }
        .bill-card-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        }
        .bill-main-info {
            flex: 2;
            min-width: 260px;
        }
        .bill-desc {
            font-size: 1.15rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.4rem;
        }
        .bill-meta {
            font-size: 0.88rem;
            color: #64748b;
            display: flex;
            gap: 1.2rem;
            flex-wrap: wrap;
        }
        .bill-pricing {
            text-align: right;
            min-width: 140px;
        }
        .bill-amount {
            font-size: 1.45rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.4rem;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.85rem;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-paid {
            background: #dcfce7;
            color: #15803d;
        }
        .status-pending {
            background: #fee2e2;
            color: #b91c1c;
        }

        .btn-pay-now {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: #ffffff;
            padding: 0.75rem 1.6rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
        }
        .btn-pay-now:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(22, 163, 74, 0.4);
        }
        .btn-receipt-view {
            background: #f1f5f9;
            color: #1e3a5f;
            border: 1px solid #cbd5e1;
            padding: 0.65rem 1.3rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s ease;
        }
        .btn-receipt-view:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
    </style>
</head>
<body>
<div class="kiosk-container">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <div class="main-content">
        <div class="page-title-banner">
            <div>
                <h2>💰 <?= htmlspecialchars(t('billing_title')) ?></h2>
                <p>Transparent self-service billing, real-time digital payments, and instant receipts.</p>
            </div>
            <?php if ($pending_count > 0): ?>
            <a href="payment_section.php" class="btn-pay-now">
                💳 <?= htmlspecialchars(t('pay_now')) ?> (₹<?= number_format($total_pending, 2) ?>)
            </a>
            <?php endif; ?>
        </div>

        <?php if ($payment_success): ?>
        <div class="alert-success" style="background:#dcfce7; color:#15803d; padding:1rem 1.4rem; border-radius:16px; margin-bottom:1.5rem; font-weight:600;">
            ✅ <?= htmlspecialchars($payment_success) ?>
        </div>
        <?php endif; ?>
        <?php if ($payment_error): ?>
        <div class="alert-error" style="background:#fee2e2; color:#b91c1c; padding:1rem 1.4rem; border-radius:16px; margin-bottom:1.5rem; font-weight:600;">
            ❌ <?= htmlspecialchars($payment_error) ?>
        </div>
        <?php endif; ?>

        <!-- Billing Summary Metrics -->
        <div class="billing-summary-grid">
            <div class="summary-card">
                <div class="summary-icon" style="background: #fee2e2; color: #dc2626;">⏳</div>
                <div>
                    <div class="summary-num" style="color: #dc2626;">₹<?= number_format($total_pending, 2) ?></div>
                    <div class="summary-label"><?= htmlspecialchars(t('total_pending')) ?> (<?= $pending_count ?> bills)</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon" style="background: #dcfce7; color: #16a34a;">✅</div>
                <div>
                    <div class="summary-num" style="color: #16a34a;">₹<?= number_format($total_paid, 2) ?></div>
                    <div class="summary-label">Total Settled</div>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon" style="background: #e0f2fe; color: #0284c7;">📑</div>
                <div>
                    <div class="summary-num"><?= count($bills) ?></div>
                    <div class="summary-label"><?= htmlspecialchars(t('total_bills')) ?></div>
                </div>
            </div>
        </div>

        <!-- Invoices List -->
        <div class="card">
            <h3 class="card-title"><?= htmlspecialchars(t('your_bills')) ?></h3>

            <?php if (count($bills) > 0): ?>
                <?php foreach ($bills as $bill): ?>
                <div class="bill-card-item">
                    <div class="bill-main-info">
                        <div class="bill-desc"><?= htmlspecialchars($bill['description']) ?></div>
                        <div class="bill-meta">
                            <span>📅 <strong><?= htmlspecialchars(t('date')) ?>:</strong> <?= date('d M Y', strtotime($bill['bill_date'])) ?></span>
                            <span>🧾 <strong>Invoice:</strong> RCP-<?= str_pad($bill['id'], 6, '0', STR_PAD_LEFT) ?></span>
                            <?php if (!empty($bill['cf_order_id'])): ?>
                            <span>🏷️ <strong>Ref:</strong> <code style="font-size: 0.8rem;"><?= htmlspecialchars($bill['cf_order_id']) ?></code></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="bill-pricing">
                        <div class="bill-amount">₹<?= number_format($bill['amount'], 2) ?></div>
                        <div>
                            <?php if ($bill['paid_status'] === 'paid'): ?>
                                <span class="status-badge status-paid">● Paid</span>
                            <?php else: ?>
                                <span class="status-badge status-pending">● Pending</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <?php if ($bill['paid_status'] === 'pending'): ?>
                            <a href="payment_section.php?bill_id=<?= $bill['id'] ?>" class="btn-pay-now">
                                💳 <?= htmlspecialchars(t('pay_now')) ?>
                            </a>
                        <?php else: ?>
                            <a href="receipt.php?bill_id=<?= $bill['id'] ?>" class="btn-receipt-view">
                                📄 <?= htmlspecialchars(t('view_receipt')) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #64748b; padding: 1.5rem 0;">No billing records found for your account.</p>
            <?php endif; ?>
        </div>

        <!-- Payment Transparency Info Box -->
        <div class="card" style="margin-top: 1.5rem;">
            <h3 class="card-title">🔒 Real-Time Payment Guarantee</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.2rem; margin-top: 1rem;">
                <div style="background: #f8fafc; padding: 1.2rem; border-radius: 16px;">
                    <strong>💳 Multi-Channel Gateway</strong>
                    <p style="font-size: 0.88rem; color: #64748b; margin: 0.4rem 0 0;">Pay via dynamic UPI QR, cards, net banking, or contactless kiosk POS terminal.</p>
                </div>
                <div style="background: #f8fafc; padding: 1.2rem; border-radius: 16px;">
                    <strong>⚡ Instant Settlement</strong>
                    <p style="font-size: 0.88rem; color: #64748b; margin: 0.4rem 0 0;">All transactions are verified instantly via Cashfree API and recorded directly to your clinic file.</p>
                </div>
                <div style="background: #f8fafc; padding: 1.2rem; border-radius: 16px;">
                    <strong>📄 Printable Tax Receipt</strong>
                    <p style="font-size: 0.88rem; color: #64748b; margin: 0.4rem 0 0;">Download or print your official computer-generated receipt anytime from this terminal.</p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>