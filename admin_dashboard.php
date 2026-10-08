<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}
require_once 'config.php';

// Get statistics
$totalPatients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$totalBills = $pdo->query("SELECT COUNT(*) FROM bills")->fetchColumn();
$pendingBills = $pdo->query("SELECT COUNT(*) FROM bills WHERE paid_status = 'pending'")->fetchColumn();
$totalPayments = $pdo->query("SELECT SUM(amount) FROM payments")->fetchColumn();
$totalMeds = $pdo->query("SELECT COUNT(*) FROM medicine_reminders")->fetchColumn();
$totalFollowups = $pdo->query("SELECT COUNT(*) FROM followup_reminders WHERE status = 'scheduled'")->fetchColumn();
$recentPatients = $pdo->query("SELECT * FROM patients ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | OrthoKiosk</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c5f8a;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        .recent-table {
            width: 100%;
            border-collapse: collapse;
        }
        .recent-table th, .recent-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body>
<div class="kiosk-container">
    <div class="header">
        <div class="logo">
            <h1>📊 Admin Dashboard</h1>
            <p>Welcome, <?= htmlspecialchars($_SESSION['admin_username']) ?></p>
        </div>
        <div class="user-info" style="display: flex; gap: 1rem; align-items: center;">
            <a href="dashboard.php" style="color:white; text-decoration:none;">📱 Open Patient Kiosk</a>
            <a href="admin_logout.php" style="color:white; text-decoration:none;">🚪 Logout</a>
        </div>
    </div>
    <div class="nav">
        <ul>
            <li><a href="admin_dashboard.php" class="active">Dashboard</a></li>
            <li><a href="admin_patients.php">Patients</a></li>
            <li><a href="admin_reminders.php">⏰ Alarms & Reminders</a></li>
            <li><a href="admin_sms_settings.php">📲 SMS Gateway Setup</a></li>
            <li><a href="admin_bills.php">Bills & Payments</a></li>
            <li><a href="food_suggestions.php">Food & Nutrition</a></li>
            <li><a href="emergency_guidance.php">Emergency Guide</a></li>
            <li><a href="body_explorer.php">Joint Explorer</a></li>
        </ul>
    </div>
    <div class="main-content">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $totalPatients ?></div>
                <div class="stat-label">Total Patients</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $totalBills ?></div>
                <div class="stat-label">Total Bills</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $pendingBills ?></div>
                <div class="stat-label">Pending Payments</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">₹<?= number_format($totalPayments ?: 0, 2) ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card" style="cursor: pointer;" onclick="window.location='admin_reminders.php'">
                <div class="stat-number" style="color: #10b981;"><?= $totalMeds ?></div>
                <div class="stat-label">⏰ Prescriptions & Alarms</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #0284c7;"><?= $totalFollowups ?></div>
                <div class="stat-label">Scheduled Reviews</div>
            </div>
        </div>
        
        <div class="card">
            <h2 class="card-title">📋 Recently Registered Patients</h2>
            <table class="recent-table">
                <thead>
                    <tr><th>ID</th><th>Name</th><th>Phone</th><th>Registered On</th></tr>
                </thead>
                <tbody>
                    <?php foreach($recentPatients as $p): ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><?= htmlspecialchars($p['phone']) ?></td>
                        <td><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(count($recentPatients) == 0): ?>
                    <tr><td colspan="4">No patients found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>