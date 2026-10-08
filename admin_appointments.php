<?php
// admin_appointments.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    $stmt->execute([$_POST['status'], $_POST['appointment_id']]);
    
    $log = $pdo->prepare("INSERT INTO system_logs (admin_id, action, table_name, record_id, new_value) VALUES (?, 'update_status', 'appointments', ?, ?)");
    $log->execute([$_SESSION['admin_id'], $_POST['appointment_id'], $_POST['status']]);
    
    $success = "Appointment status updated!";
}

// Get all appointments with patient and therapist details
$stmt = $pdo->query("
    SELECT a.*, p.name as patient_name, p.phone, t.name as therapist_name 
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    LEFT JOIN physiotherapists t ON a.therapist_id = t.id
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$appointments = $stmt->fetchAll();

// Get therapists for dropdown
$therapists = $pdo->query("SELECT id, name FROM physiotherapists WHERE status = 'active'")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Appointment Management - OrthoKiosk</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="kiosk-container">
    <div class="header">
        <h1>📅 Appointment Management</h1>
        <div class="user-info"><a href="admin_dashboard.php">← Back to Dashboard</a></div>
    </div>
    <div class="nav">
        <ul>
            <li><a href="admin_dashboard.php">Dashboard</a></li>
            <li><a href="admin_patients.php">Patients</a></li>
            <li><a href="admin_appointments.php" class="active">Appointments</a></li>
            <li><a href="admin_therapists.php">Therapists</a></li>
            <li><a href="admin_bills.php">Billing</a></li>
        </ul>
    </div>
    <div class="main-content">
        <?php if(isset($success)): ?>
            <div style="background:#d4edda; padding:12px; border-radius:12px; margin-bottom:20px;"><?= $success ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h2 class="card-title">All Appointments</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Patient</th>
                            <th>Therapist</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($appointments as $apt): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($apt['appointment_date'])) ?><br><small><?= $apt['appointment_time'] ?></small></td>
                            <td><strong><?= htmlspecialchars($apt['patient_name']) ?></strong><br><small><?= $apt['phone'] ?></small></td>
                            <td><?= $apt['therapist_name'] ?? 'Not assigned' ?></td>
                            <td>
                                <span style="padding:4px 12px; border-radius:20px; font-size:12px; 
                                    <?php
                                    $colors = ['scheduled'=>'#f39c12', 'completed'=>'#27ae60', 'cancelled'=>'#e74c3c', 'no_show'=>'#95a5a6'];
                                    echo "background: {$colors[$apt['status']]}20; color: {$colors[$apt['status']]};";
                                    ?>">
                                    <?= ucfirst($apt['status']) ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                    <select name="status" onchange="this.form.submit()" style="padding:5px;">
                                        <option value="scheduled" <?= $apt['status']=='scheduled'?'selected':'' ?>>Scheduled</option>
                                        <option value="completed" <?= $apt['status']=='completed'?'selected':'' ?>>Completed</option>
                                        <option value="cancelled" <?= $apt['status']=='cancelled'?'selected':'' ?>>Cancelled</option>
                                        <option value="no_show" <?= $apt['status']=='no_show'?'selected':'' ?>>No Show</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>