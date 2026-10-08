<?php
// api_reminders.php - REST API for Medicine Alarms, SMS/WhatsApp Testing & Preferences
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$patient_id = $_SESSION['patient_id'] ?? (int)($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);

if (!$patient_id && !in_array($action, ['public_ping'])) {
    echo json_encode(['success' => false, 'error' => 'Authentication required or invalid patient ID']);
    exit;
}

$patient = getPatientById($pdo, $patient_id);
if (!$patient && !in_array($action, ['public_ping'])) {
    echo json_encode(['success' => false, 'error' => 'Patient not found']);
    exit;
}

switch ($action) {
    case 'due_alarms':
        // Retrieve medicines due for alarm right now
        $today = date('Y-m-d');
        $currentTime = date('H:i');
        
        $stmt = $pdo->prepare("SELECT * FROM medicine_reminders 
            WHERE patient_id = ? 
              AND start_date <= CURDATE() 
              AND DATE_ADD(start_date, INTERVAL duration_days DAY) >= CURDATE()
              AND (last_taken_date IS NULL OR last_taken_date != CURDATE())");
        $stmt->execute([$patient_id]);
        $meds = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dueNow = [];
        foreach ($meds as $med) {
            if (empty($med['alarm_enabled'])) continue;

            $timesStr = $med['reminder_times'] ?? '';
            if (empty($timesStr)) {
                if ($med['frequency'] === 'morning') $timesStr = '08:00';
                elseif ($med['frequency'] === 'afternoon') $timesStr = '13:00';
                elseif ($med['frequency'] === 'night') $timesStr = '20:00';
                elseif ($med['frequency'] === 'morning_night') $timesStr = '08:00,20:00';
                else $timesStr = '08:00,13:00,20:00';
            }

            $times = array_map('trim', explode(',', $timesStr));
            foreach ($times as $t) {
                if (empty($t)) continue;
                $tFormat = date('H:i', strtotime($t));
                // If scheduled time is within ±15 minutes of now
                $diff = abs(strtotime($currentTime) - strtotime($tFormat)) / 60;
                if ($diff <= 15) {
                    $dueNow[] = [
                        'id' => $med['id'],
                        'name' => $med['medicine_name'],
                        'dosage' => $med['dosage'],
                        'timing' => $med['timing'],
                        'scheduled_time' => $tFormat,
                        'notes' => $med['notes']
                    ];
                    break; // one alarm per medicine per check
                }
            }
        }

        echo json_encode(['success' => true, 'count' => count($dueNow), 'alarms' => $dueNow]);
        break;

    case 'test_sms':
        // Dispatch test SMS to patient
        $med_id = (int)($_POST['med_id'] ?? 0);
        $med_name = trim($_POST['med_name'] ?? 'Prescribed Medicine');
        $dosage = trim($_POST['dosage'] ?? '1 Dose');

        $message = "⏰ [TEST ALERT] Hello {$patient['name']}! This is a test medicine alarm from OrthoKiosk for your scheduled {$med_name} ({$dosage}). Please take your medicine on time.";
        $result = sendPatientSMS($patient['phone'], $message, $pdo, $patient_id, $med_id > 0 ? $med_id : null, date('H:i:s'));

        $userMsg = "";
        if ($result['provider'] === 'FAST2SMS' && $result['status'] === 'delivered') {
            $userMsg = "✅ Real SMS sent to {$patient['phone']} via Fast2SMS! Check your mobile SMS inbox.";
        } elseif ($result['provider'] === 'FAST2SMS' && $result['status'] === 'failed') {
            $userMsg = "❌ Fast2SMS Error: " . ($result['feedback'] ?? 'Failed to deliver SMS');
        } elseif ($result['provider'] === 'TWILIO' && $result['status'] === 'delivered') {
            $userMsg = "✅ Real SMS sent to {$patient['phone']} via Twilio! Check your mobile SMS inbox.";
        } else {
            $userMsg = "ℹ️ [SIMULATOR MODE]: SMS recorded for {$patient['phone']}. To receive real SMS on your phone, enter your free Fast2SMS API Key in Admin Settings.";
        }

        echo json_encode([
            'success' => $result['success'],
            'message' => $userMsg,
            'provider' => $result['provider'],
            'details' => $result
        ]);
        break;

    case 'log_alarm':
        // Log in-app audio alarm sounding
        $med_id = (int)($_POST['med_id'] ?? 0);
        $med_name = trim($_POST['med_name'] ?? 'Medicine');
        $sched_time = trim($_POST['time'] ?? date('H:i:s'));

        $logId = logMedicineAlert(
            $pdo, 
            $patient_id, 
            $med_id, 
            'alarm', 
            $patient['phone'], 
            "Device Alarm sounded for {$med_name} at {$sched_time}", 
            'triggered', 
            $sched_time
        );

        echo json_encode(['success' => (bool)$logId, 'log_id' => $logId]);
        break;

    case 'update_times':
        // Update dosage times and channel preferences for a medicine
        $med_id = (int)($_POST['med_id'] ?? 0);
        $times = trim($_POST['reminder_times'] ?? '08:00,13:00,20:00');
        $send_sms = isset($_POST['send_sms']) ? (int)$_POST['send_sms'] : 1;
        $alarm = isset($_POST['alarm_enabled']) ? (int)$_POST['alarm_enabled'] : 1;

        if ($med_id > 0) {
            $stmt = $pdo->prepare("UPDATE medicine_reminders 
                SET reminder_times = ?, send_sms = ?, alarm_enabled = ? 
                WHERE id = ? AND patient_id = ?");
            $stmt->execute([$times, $send_sms, $alarm, $med_id, $patient_id]);
            echo json_encode(['success' => true, 'message' => 'Reminder preferences saved successfully!']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid medicine ID']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        break;
}
?>
