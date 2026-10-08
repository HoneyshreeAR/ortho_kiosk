<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) { header("Location: admin_login.php"); exit; }
require_once 'config.php';
$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();
if(!$patient) die("Patient not found");
?>
<!DOCTYPE html>
<html>
<head><title>Patient Details</title><link rel="stylesheet" href="style.css"></head>
<body>
<div class="kiosk-container">
    <div class="header"><h1>Patient Details</h1><a href="admin_patients.php">← Back</a></div>
    <div class="main-content">
        <div class="card">
            <p><strong>Name:</strong> <?= htmlspecialchars($patient['name']) ?></p>
            <p><strong>Phone:</strong> <?= $patient['phone'] ?></p>
            <p><strong>Email:</strong> <?= $patient['email'] ?></p>
            <p><strong>Age:</strong> <?= $patient['age'] ?></p>
            <p><strong>Gender:</strong> <?= $patient['gender'] ?></p>
            <p><strong>Registered:</strong> <?= $patient['created_at'] ?></p>
        </div>
    </div>
</div>
</body>
</html>