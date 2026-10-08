<?php
// submit_pain_assessment.php
require_once 'config.php';
if (!isset($_SESSION['patient_id'])) { 
    header("Location: index.php"); 
    exit; 
}
$patient_id = $_SESSION['patient_id'];

$body_parts = isset($_POST['body_parts']) ? implode(", ", $_POST['body_parts']) : 'Not specified';
$pain_intensity = $_POST['pain_intensity'];
$activities = isset($_POST['activities']) ? implode(", ", $_POST['activities']) : '';
$pain_quality = $_POST['pain_quality'];
$duration_weeks = (int)$_POST['duration_weeks'];
$activity_aggravation = $_POST['activity_aggravation'];
$pain_description = $_POST['pain_description'];
$mobility_score = $_POST['mobility_score'];

$classification = classifyPain($pain_quality, $duration_weeks, $activity_aggravation);

$stmt = $pdo->prepare("INSERT INTO pain_assessments (patient_id, body_part, pain_intensity, activities, pain_quality, duration_weeks, activity_aggravation, pain_description, mobility_score, classification) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$patient_id, $body_parts, $pain_intensity, $activities, $pain_quality, $duration_weeks, $activity_aggravation, $pain_description, $mobility_score, $classification]);

$assessment_id = $pdo->lastInsertId();
$_SESSION['latest_assessment_id'] = $assessment_id;   // <-- NEW LINE

// Create a pending bill if not exists
$check = $pdo->prepare("SELECT id FROM bills WHERE patient_id = ? AND paid_status = 'pending' LIMIT 1");
$check->execute([$patient_id]);
if (!$check->fetch()) {
    $amount = 500.00;
    $desc = "Orthopaedic Consultation & Pain Assessment";
    $insert = $pdo->prepare("INSERT INTO bills (patient_id, description, amount, paid_status, bill_date) VALUES (?, ?, ?, 'pending', CURDATE())");
    $insert->execute([$patient_id, $desc, $amount]);
}

// Go to payment page
header("Location: payment_section.php");
exit;
?>