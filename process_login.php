<?php
// process_login.php
require_once 'config.php';
$phone = $_POST['phone'];
$stmt = $pdo->prepare("SELECT id FROM patients WHERE phone = ?");
$stmt->execute([$phone]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
if ($patient) {
    $_SESSION['patient_id'] = $patient['id'];
    header("Location: dashboard.php");
} else {
    $_SESSION['error'] = "No patient found with this phone number. Please register first.";
    header("Location: index.php");
}
exit;
?>