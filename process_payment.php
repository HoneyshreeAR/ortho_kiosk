<?php
// process_payment.php - Dummy payment processing, then show bill details
session_start();
require_once 'config.php';

if (!isset($_SESSION['patient_id'])) {
    header("Location: index.php");
    exit;
}

$patient_id = $_SESSION['patient_id'];
$bill_id = $_POST['bill_id'] ?? 0;
$amount = $_POST['amount'] ?? 0;
$description = $_POST['description'] ?? '';

// Verify bill belongs to patient and is pending
$stmt = $pdo->prepare("SELECT * FROM bills WHERE id = ? AND patient_id = ? AND paid_status = 'pending'");
$stmt->execute([$bill_id, $patient_id]);
$bill = $stmt->fetch();

if (!$bill) {
    $_SESSION['payment_error'] = "Invalid bill or already paid.";
    header("Location: billing.php");
    exit;
}

// Dummy payment - always successful
$transaction_id = 'TXN' . time() . rand(1000, 9999);

// Update bill status
$update = $pdo->prepare("UPDATE bills SET paid_status = 'paid' WHERE id = ?");
$update->execute([$bill_id]);

// Record payment in payments table
$insert = $pdo->prepare("INSERT INTO payments (patient_id, bill_id, amount, transaction_id, payment_date) VALUES (?, ?, ?, ?, NOW())");
$insert->execute([$patient_id, $bill_id, $amount, $transaction_id]);

// Store payment details in session to show on success page
$_SESSION['last_payment'] = [
    'bill_id' => $bill_id,
    'amount' => $amount,
    'description' => $description,
    'transaction_id' => $transaction_id,
    'payment_date' => date('Y-m-d H:i:s')
];

// Redirect to payment success page (shows bill details)
header("Location: payment_success.php?bill_id=$bill_id");
exit;
?>