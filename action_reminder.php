<?php
// action_reminder.php - Backend handler for Medicine & Follow-up Reminders
require_once 'config.php';

if (!isset($_SESSION['patient_id'])) {
    header('Location: index.php');
    exit;
}

$patient_id = $_SESSION['patient_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'mark_taken') {
    $med_id = (int)($_POST['med_id'] ?? $_GET['med_id'] ?? 0);
    if ($med_id > 0) {
        $stmt = $pdo->prepare("UPDATE medicine_reminders SET last_taken_date = CURDATE() WHERE id = ? AND patient_id = ?");
        $stmt->execute([$med_id, $patient_id]);
    }
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Medication logged as taken!']);
        exit;
    }
    
    header('Location: reminders.php?msg=med_taken');
    exit;
}

if ($action === 'add_medicine') {
    $name = trim($_POST['medicine_name'] ?? '');
    $dosage = trim($_POST['dosage'] ?? '');
    $frequency = trim($_POST['frequency'] ?? 'morning');
    $timing = trim($_POST['timing'] ?? 'after_food');
    $duration = (int)($_POST['duration_days'] ?? 7);
    $notes = trim($_POST['notes'] ?? '');

    // Reminder times (morning, afternoon, night or custom)
    $morning_time = trim($_POST['morning_time'] ?? '08:00');
    $afternoon_time = trim($_POST['afternoon_time'] ?? '13:00');
    $night_time = trim($_POST['night_time'] ?? '20:00');

    $timesArray = [];
    if ($frequency === 'morning') {
        $timesArray[] = $morning_time ?: '08:00';
    } elseif ($frequency === 'afternoon') {
        $timesArray[] = $afternoon_time ?: '13:00';
    } elseif ($frequency === 'night') {
        $timesArray[] = $night_time ?: '20:00';
    } elseif ($frequency === 'morning_night') {
        $timesArray[] = $morning_time ?: '08:00';
        $timesArray[] = $night_time ?: '20:00';
    } elseif ($frequency === 'three_times') {
        $timesArray[] = $morning_time ?: '08:00';
        $timesArray[] = $afternoon_time ?: '13:00';
        $timesArray[] = $night_time ?: '20:00';
    } else {
        $timesArray[] = $morning_time ?: '08:00';
    }
    $reminder_times = implode(',', $timesArray);

    $send_sms = isset($_POST['send_sms']) ? 1 : 0;
    $alarm_enabled = isset($_POST['alarm_enabled']) ? 1 : 0;

    if (!empty($name) && !empty($dosage)) {
        $stmt = $pdo->prepare("INSERT INTO medicine_reminders 
            (patient_id, medicine_name, dosage, frequency, timing, reminder_times, send_sms, send_whatsapp, alarm_enabled, duration_days, start_date, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, CURDATE(), ?)");
        $stmt->execute([$patient_id, $name, $dosage, $frequency, $timing, $reminder_times, $send_sms, $alarm_enabled, $duration, $notes]);
        $newMedId = $pdo->lastInsertId();

        // Send instant confirmation SMS notification to patient's phone
        $patient = getPatientById($pdo, $patient_id);
        if ($patient && $send_sms) {
            $confMsg = "✅ OrthoKiosk: Medicine schedule confirmed for {$name} ({$dosage}). Scheduled times: {$reminder_times}. Direct SMS alerts are active on your phone!";
            sendPatientSMS($patient['phone'], $confMsg, $pdo, $patient_id, $newMedId);
        }
    }

    header('Location: reminders.php?msg=med_added');
    exit;
}

if ($action === 'delete_medicine') {
    $med_id = (int)($_POST['med_id'] ?? $_GET['med_id'] ?? 0);
    if ($med_id > 0) {
        $stmt = $pdo->prepare("DELETE FROM medicine_reminders WHERE id = ? AND patient_id = ?");
        $stmt->execute([$med_id, $patient_id]);
    }
    header('Location: reminders.php?msg=med_deleted');
    exit;
}

if ($action === 'schedule_followup') {
    $doctor = trim($_POST['doctor_name'] ?? 'Dr. Ramesh Rao');
    $dept = trim($_POST['department'] ?? 'Orthopaedics');
    $date = trim($_POST['appointment_date'] ?? date('Y-m-d', strtotime('+7 days')));
    $time = trim($_POST['appointment_time'] ?? '10:00:00');
    $purpose = trim($_POST['purpose'] ?? 'Follow-up Consultation');

    if (!empty($doctor) && !empty($date)) {
        $stmt = $pdo->prepare("INSERT INTO followup_reminders (patient_id, doctor_name, department, appointment_date, appointment_time, purpose, status) VALUES (?, ?, ?, ?, ?, ?, 'scheduled')");
        $stmt->execute([$patient_id, $doctor, $dept, $date, $time, $purpose]);
    }

    header('Location: reminders.php?msg=followup_scheduled');
    exit;
}

header('Location: reminders.php');
exit;
?>
