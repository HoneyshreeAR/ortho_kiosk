<?php
// admin_bills.php - Admin Real-Time Billing & Payments Manager
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}
require_once 'config.php';

$filter_status = $_GET['status'] ?? 'all';

$sql = "
    SELECT b.*, p.name as patient_name, p.phone as patient_phone,
           pm.transaction_id, pm.payment_method, pm.payment_date
    FROM bills b 
    JOIN patients p ON b.patient_id = p.id 
    LEFT JOIN payments pm ON b.id = pm.bill_id
";

if ($filter_status === 'paid') {
    $sql .= " WHERE b.paid_status = 'paid'";
} elseif ($filter_status === 'pending') {
    $sql .= " WHERE b.paid_status = 'pending'";
}

$sql .= " ORDER BY b.bill_date DESC, b.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$bills = $stmt->fetchAll();

// Statistics
$total_revenue = $pdo->query("SELECT SUM(amount) FROM payments")->fetchColumn() ?: 0;
$pending_total = $pdo->query("SELECT SUM(amount) FROM bills WHERE paid_status = 'pending'")->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Bills & Real-Time Payments | Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-stat-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.2rem;
            margin-bottom: 2rem;
        }
        .stat-box {
            background: #ffffff;
            border-radius: 18px;
            padding: 1.4rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        .stat-box-num {
            font-size: 1.8rem;
            font-weight: 800;
        }
        .stat-box-label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 600;
            margin-top: 0.2rem;
        }
        .filter-bar {
            display: flex;
            gap: 0.8rem;
            margin-bottom: 1.5rem;
            align-items: center;
        }
        .filter-btn {
            padding: 0.6rem 1.2rem;
            border-radius: 999px;
            font-size: 0.88rem;
            font-weight: 700;
            text-decoration: none;
            color: #475569;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
        }
        .filter-btn.active {
            background: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
        }
        .bill-card {
            background: white;
            border-radius: 18px;
            padding: 1.4rem;
            margin-bottom: 1.2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .status-paid {
            background: #dcfce7;
            color: #15803d;
            padding: 0.3rem 0.8rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 700;
        }
        .status-pending {
            background: #fee2e2;
            color: #b91c1c;
            padding: 0.3rem 0.8rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 700;
        }
    </style>
</head>
<body>
<div class="kiosk-container">
    <div class="header">
        <div class="logo">
            <h1>💰 Real-Time Billing & Payments Manager</h1>
            <p>Monitor patient billing, gateway settlements, and revenue tracking</p>
        </div>
        <div class="user-info">
            <a href="admin_dashboard.php" style="color:white; text-decoration:none; font-weight:700;">← Admin Dashboard</a>
        </div>
    </div>

    <div class="nav">
        <ul>
            <li><a href="admin_dashboard.php">Dashboard</a></li>
            <li><a href="admin_patients.php">Patients</a></li>
            <li><a href="admin_bills.php" class="active">Bills & Payments</a></li>
            <li><a href="food_suggestions.php">Food & Nutrition</a></li>
            <li><a href="body_explorer.php">Joint Explorer</a></li>
            <li><a href="admin_logout.php">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <!-- Stats -->
        <div class="admin-stat-row">
            <div class="stat-box">
                <div class="stat-box-num" style="color: #16a34a;">₹<?= number_format($total_revenue, 2) ?></div>
                <div class="stat-box-label">Total Settled Revenue</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-num" style="color: #dc2626;">₹<?= number_format($pending_total, 2) ?></div>
                <div class="stat-box-label">Total Outstanding / Pending</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-num" style="color: #2563eb;"><?= count($bills) ?></div>
                <div class="stat-box-label">Invoices in Current View</div>
            </div>
        </div>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 1rem;">
                <h2 class="card-title" style="margin: 0;">Invoices & Transactions</h2>
                
                <div class="filter-bar" style="margin: 0;">
                    <a href="admin_bills.php?status=all" class="filter-btn <?= $filter_status === 'all' ? 'active' : '' ?>">All</a>
                    <a href="admin_bills.php?status=paid" class="filter-btn <?= $filter_status === 'paid' ? 'active' : '' ?>">Paid Only</a>
                    <a href="admin_bills.php?status=pending" class="filter-btn <?= $filter_status === 'pending' ? 'active' : '' ?>">Pending Only</a>
                </div>
            </div>

            <?php if (count($bills) > 0): ?>
                <?php foreach ($bills as $bill): ?>
                <div class="bill-card">
                    <div style="flex: 2; min-width: 250px;">
                        <div style="font-size: 1.1rem; font-weight: 700; color: #1e293b;">
                            <?= htmlspecialchars($bill['patient_name']) ?> 
                            <small style="color: #64748b; font-weight: 500;">(ID: #<?= $bill['patient_id'] ?> | 📞 <?= htmlspecialchars($bill['patient_phone']) ?>)</small>
                        </div>
                        <div style="color: #334155; margin: 0.3rem 0; font-weight: 600;">
                            <?= htmlspecialchars($bill['description']) ?>
                        </div>
                        <div style="font-size: 0.85rem; color: #64748b;">
                            📅 <strong>Date:</strong> <?= date('d M Y', strtotime($bill['bill_date'])) ?> | 
                            🧾 <strong>Invoice:</strong> RCP-<?= str_pad($bill['id'], 6, '0', STR_PAD_LEFT) ?>
                            <?php if (!empty($bill['cf_order_id'])): ?>
                                | 🏷️ <strong>Gateway Order:</strong> <code><?= htmlspecialchars($bill['cf_order_id']) ?></code>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($bill['transaction_id'])): ?>
                        <div style="font-size: 0.85rem; color: #0284c7; margin-top: 0.3rem;">
                            ⚡ <strong>Txn ID:</strong> <code><?= htmlspecialchars($bill['transaction_id']) ?></code>
                            (<?= htmlspecialchars($bill['payment_method'] ?? 'PG') ?>)
                        </div>
                        <?php endif; ?>
                    </div>

                    <div style="text-align: right; min-width: 130px;">
                        <div style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-bottom: 0.3rem;">
                            ₹<?= number_format($bill['amount'], 2) ?>
                        </div>
                        <div>
                            <span class="<?= $bill['paid_status'] === 'paid' ? 'status-paid' : 'status-pending' ?>">
                                <?= ucfirst($bill['paid_status']) ?>
                            </span>
                        </div>
                        <?php if ($bill['paid_status'] === 'paid'): ?>
                        <div style="margin-top: 0.5rem;">
                            <a href="receipt.php?bill_id=<?= $bill['id'] ?>" target="_blank" style="font-size: 0.85rem; color: #2563eb; font-weight: 700; text-decoration: none;">
                                📄 View Receipt
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #64748b; padding: 2rem 0; text-align: center;">No invoices found matching criteria.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>