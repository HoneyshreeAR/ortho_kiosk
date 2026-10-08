<?php
// admin_reminders.php - Clinic Administrator Reminders & Alarms Monitoring Hub
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}
require_once 'config.php';

// Fetch all active prescriptions with patient details
$sqlMeds = "SELECT m.*, p.name AS patient_name, p.phone AS patient_phone, p.id AS patient_id
            FROM medicine_reminders m
            INNER JOIN patients p ON m.patient_id = p.id
            ORDER BY m.created_at DESC";
$allMeds = $pdo->query($sqlMeds)->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent dispatch logs
$sqlLogs = "SELECT l.*, p.name AS patient_name, m.medicine_name, m.dosage
            FROM medicine_notifications_log l
            LEFT JOIN patients p ON l.patient_id = p.id
            LEFT JOIN medicine_reminders m ON l.medicine_id = m.id
            ORDER BY l.sent_at DESC
            LIMIT 50";
$logs = $pdo->query($sqlLogs)->fetchAll(PDO::FETCH_ASSOC);

$totalAlertsToday = $pdo->query("SELECT COUNT(*) FROM medicine_notifications_log WHERE DATE(sent_at) = CURDATE()")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Alarms & Reminders Hub | Admin OrthoKiosk</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 2rem;
            gap: 1rem;
        }
        .hub-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.2rem;
            margin-bottom: 2rem;
        }
        .hub-card {
            background: white;
            border-radius: 18px;
            padding: 1.3rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            border-left: 5px solid #1a3e5c;
        }
        .hub-num {
            font-size: 1.8rem;
            font-weight: 800;
            color: #1a3e5c;
        }
        .hub-sub {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 600;
        }
        .table-responsive {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            margin-bottom: 2rem;
            overflow-x: auto;
        }
        .custom-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .custom-table th {
            text-align: left;
            padding: 0.8rem 1rem;
            background: #f8fafc;
            color: #334155;
            font-weight: 700;
            border-bottom: 2px solid #e2e8f0;
        }
        .custom-table td {
            padding: 0.8rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
        }
        .badge-pill {
            padding: 0.25rem 0.65rem;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .badge-pill.active { background: #dcfce7; color: #15803d; }
        .badge-pill.disabled { background: #fee2e2; color: #b91c1c; }
        .btn-broadcast {
            background: #0d9488;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 30px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 12px rgba(13, 148, 136, 0.3);
            transition: 0.2s;
        }
        .btn-broadcast:hover {
            background: #0f766e;
        }
    </style>
</head>
<body>
<div class="kiosk-container">
    <div class="header">
        <div class="logo">
            <h1>⏰ Medicine Alarms & Reminders Hub</h1>
            <p>Monitor patient phone alerts, Direct SMS notifications, and alarm schedules</p>
        </div>
        <div class="user-info" style="display: flex; gap: 1rem; align-items: center;">
            <a href="admin_dashboard.php" style="color:white; text-decoration:none;">📊 Dashboard</a>
            <a href="admin_logout.php" style="color:white; text-decoration:none;">🚪 Logout</a>
        </div>
    </div>

    <div class="nav">
        <ul>
            <li><a href="admin_dashboard.php">Dashboard</a></li>
            <li><a href="admin_patients.php">Patients</a></li>
            <li><a href="admin_reminders.php" class="active">Alarms & Reminders</a></li>
            <li><a href="admin_sms_settings.php">📲 SMS Gateway Setup</a></li>
            <li><a href="admin_bills.php">Bills & Payments</a></li>
            <li><a href="food_suggestions.php">Food & Nutrition</a></li>
            <li><a href="emergency_guidance.php">Emergency Guide</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="admin-header-row">
            <div>
                <h2>Patient Medicine Schedules & Alerts</h2>
                <p style="color:#64748b;">Automated triggers deliver alerts to each patient's mobile number.</p>
            </div>
            <div style="display:flex; gap:0.8rem; flex-wrap:wrap;">
                <a href="admin_sms_settings.php" class="btn-broadcast" style="background:#1a3e5c;">
                    📲 SMS Gateway Setup (Fast2SMS)
                </a>
                <a href="cron_reminders.php?force=1" target="_blank" class="btn-broadcast">
                    🚀 Broadcast Due Reminders Now
                </a>
            </div>
        </div>

        <div class="hub-stats">
            <div class="hub-card" style="border-left-color: #0284c7;">
                <div class="hub-num"><?= count($allMeds) ?></div>
                <div class="hub-sub">Active Medication Schedules</div>
            </div>
            <div class="hub-card" style="border-left-color: #10b981;">
                <div class="hub-num"><?= $totalAlertsToday ?></div>
                <div class="hub-sub">Alerts Dispatched Today</div>
            </div>
            <div class="hub-card" style="border-left-color: #f59e0b;">
                <div class="hub-num"><?= count($logs) ?></div>
                <div class="hub-sub">Total Logged Notifications</div>
            </div>
        </div>

        <!-- Section 1: Active Schedules -->
        <div class="table-responsive">
            <h3 style="margin-bottom: 1rem; color: #1a3e5c;">📋 Patient Medication Schedules</h3>
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Phone Number</th>
                        <th>Medication & Dose</th>
                        <th>Timing</th>
                        <th>Alarm Times</th>
                        <th>Channels</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($allMeds)): ?>
                        <?php foreach ($allMeds as $m): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($m['patient_name']) ?></strong> (#<?= $m['patient_id'] ?>)</td>
                            <td><?= htmlspecialchars($m['patient_phone']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($m['medicine_name']) ?></strong><br>
                                <small style="color:#64748b;"><?= htmlspecialchars($m['dosage']) ?></small>
                            </td>
                            <td><?= htmlspecialchars(ucfirst($m['frequency'])) ?> (<?= $m['timing'] === 'before_food' ? 'Before Food' : 'After Food' ?>)</td>
                            <td><strong>⏰ <?= htmlspecialchars($m['reminder_times'] ?? '08:00,13:00,20:00') ?></strong></td>
                            <td>
                                <span class="badge-pill <?= !empty($m['send_sms']) ? 'active' : 'disabled' ?>">SMS</span>
                                <span class="badge-pill <?= !empty($m['alarm_enabled']) ? 'active' : 'disabled' ?>">Alarm</span>
                            </td>
                            <td>
                                <a href="cron_reminders.php?force=1&patient_id=<?= $m['patient_id'] ?>" target="_blank" style="color:#0284c7; font-weight:700; text-decoration:none; font-size:0.85rem;">
                                    🔔 Send Alert Now
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center; color:#64748b;">No active medicine schedules found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Section 2: Real-time Dispatch Logs -->
        <div class="table-responsive">
            <h3 style="margin-bottom: 1rem; color: #1a3e5c;">📜 Recent Notification Delivery Logs</h3>
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Patient</th>
                        <th>Phone</th>
                        <th>Channel</th>
                        <th>Medication</th>
                        <th>Status</th>
                        <th>Message Content</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $l): ?>
                        <tr>
                            <td><?= date('d M Y, h:i A', strtotime($l['sent_at'])) ?></td>
                            <td><strong><?= htmlspecialchars($l['patient_name'] ?? 'Patient') ?></strong></td>
                            <td><?= htmlspecialchars($l['phone']) ?></td>
                            <td>
                                <strong><?= strtoupper($l['alert_type']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($l['medicine_name'] ?? 'General') ?></td>
                            <td>
                                <span class="badge-pill active"><?= htmlspecialchars(ucfirst($l['status'])) ?></span>
                            </td>
                            <td style="color:#64748b; max-width:280px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                <?= htmlspecialchars($l['message']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center; color:#64748b;">No notification logs recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
