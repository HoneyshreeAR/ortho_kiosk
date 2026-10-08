<?php
// cron_reminders.php - Scheduled & Automated Medicine Reminder Dispatcher
// Can be executed via:
// 1. Command-Line / Task Scheduler: php cron_reminders.php
// 2. Web browser / Cron cURL: http://localhost/ortho_kiosk/cron_reminders.php
// 3. Admin on-demand: http://localhost/ortho_kiosk/cron_reminders.php?force=1

require_once __DIR__ . '/config.php';

$isCli = (php_sapi_name() === 'cli');
$force = (isset($_GET['force']) && $_GET['force'] == '1') || (isset($argv) && (in_array('--force', $argv) || in_array('--force=1', $argv) || in_array('force', $argv)));
$targetPatient = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : null;

$currentTime = date('H:i');
$currentDate = date('Y-m-d');

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Reminder Dispatcher | OrthoKiosk</title>";
    echo "<style>body{font-family:sans-serif;background:#f1f5f9;padding:2rem;} .box{background:#fff;border-radius:16px;padding:1.5rem;max-width:800px;margin:auto;box-shadow:0 4px 15px rgba(0,0,0,0.06);} .badge{padding:4px 8px;border-radius:6px;font-size:12px;font-weight:bold;} .sent{background:#dcfce7;color:#15803d;} .skipped{background:#fef3c7;color:#b45309;}</style></head><body>";
    echo "<div class='box'><h2>⏰ OrthoKiosk Medicine Reminder Dispatcher</h2>";
    echo "<p><strong>Server Time:</strong> " . date('Y-m-d H:i:s') . " | <strong>Mode:</strong> " . ($force ? "Force All Active" : "Scheduled Time Check") . "</p><hr>";
}

// 1. Fetch active medicines with patient details
$sql = "SELECT m.*, p.name AS patient_name, p.phone AS patient_phone, p.id AS patient_id 
        FROM medicine_reminders m
        INNER JOIN patients p ON m.patient_id = p.id
        WHERE m.start_date <= CURDATE() 
          AND DATE_ADD(m.start_date, INTERVAL m.duration_days DAY) >= CURDATE()";

if ($targetPatient) {
    $sql .= " AND p.id = " . (int)$targetPatient;
}

$stmt = $pdo->query($sql);
$activeMeds = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dispatched = [];
$skipped = [];

foreach ($activeMeds as $med) {
    // Parse reminder times (format: "08:00,13:00,20:00" or default based on frequency)
    $timesStr = $med['reminder_times'] ?? '';
    if (empty($timesStr)) {
        if ($med['frequency'] === 'morning') $timesStr = '08:00';
        elseif ($med['frequency'] === 'afternoon') $timesStr = '13:00';
        elseif ($med['frequency'] === 'night') $timesStr = '20:00';
        elseif ($med['frequency'] === 'morning_night') $timesStr = '08:00,20:00';
        else $timesStr = '08:00,13:00,20:00';
    }

    $times = array_map('trim', explode(',', $timesStr));

    foreach ($times as $doseTime) {
        if (empty($doseTime)) continue;

        // Ensure HH:MM format
        $doseTimeFormatted = date('H:i', strtotime($doseTime));

        // Check if due: either force=1 or current time matches within ±20 minutes window
        $isDue = false;
        if ($force) {
            $isDue = true;
        } else {
            $diffMins = abs(strtotime($currentTime) - strtotime($doseTimeFormatted)) / 60;
            if ($diffMins <= 20) {
                $isDue = true;
            }
        }

        if (!$isDue) {
            continue;
        }

        // Check if already dispatched today for this medicine and dose time
        $checkStmt = $pdo->prepare("SELECT id FROM medicine_notifications_log 
            WHERE patient_id = ? AND medicine_id = ? AND DATE(sent_at) = CURDATE() AND scheduled_time = ?");
        $checkStmt->execute([$med['patient_id'], $med['id'], $doseTimeFormatted . ':00']);
        $alreadyLogged = $checkStmt->fetch();

        if ($alreadyLogged && !$force) {
            $skipped[] = [
                'patient' => $med['patient_name'],
                'medicine' => $med['medicine_name'],
                'time' => $doseTimeFormatted,
                'reason' => 'Already notified today'
            ];
            continue;
        }

        // Timing label (Before / After food)
        $mealTiming = ($med['timing'] === 'before_food') ? 'Before Food (ಖಾಲಿ ಹೊಟ್ಟೆಯಲ್ಲಿ / खाने से पहले)' : 'After Food (ಊಟದ ನಂತರ / खाने के बाद)';

        // Personalized message text
        $msg = "⏰ Hello {$med['patient_name']}! Medicine Reminder from OrthoKiosk: Time to take your scheduled dose of {$med['medicine_name']} ({$med['dosage']}), {$mealTiming}. Take care and stay healthy!";

        // Dispatch Direct Phone SMS
        $smsResult = null;
        if (!empty($med['send_sms'])) {
            $smsResult = sendPatientSMS($med['patient_phone'], $msg, $pdo, $med['patient_id'], $med['id'], $doseTimeFormatted . ':00');
        }

        $dispatched[] = [
            'patient' => $med['patient_name'],
            'phone' => $med['patient_phone'],
            'medicine' => $med['medicine_name'],
            'dosage' => $med['dosage'],
            'time' => $doseTimeFormatted,
            'sms' => $smsResult ? $smsResult['status'] : 'disabled'
        ];
    }
}

if ($isCli) {
    echo "OrthoKiosk Reminder Cron finished at " . date('Y-m-d H:i:s') . "\n";
    echo "Dispatched: " . count($dispatched) . " | Skipped: " . count($skipped) . "\n";
    foreach ($dispatched as $d) {
        echo " - Sent SMS to {$d['patient']} ({$d['phone']}) for {$d['medicine']} at {$d['time']}\n";
    }
} else {
    echo "<h3>Dispatched Reminders (" . count($dispatched) . ")</h3>";
    if (empty($dispatched)) {
        echo "<p style='color:#64748b;'>No reminders were scheduled for this exact time window.</p>";
    } else {
        echo "<ul>";
        foreach ($dispatched as $d) {
            echo "<li><strong>{$d['patient']}</strong> ({$d['phone']}) - <em>{$d['medicine']} ({$d['dosage']})</em> at {$d['time']} ";
            echo "<span class='badge sent'>Direct SMS: {$d['sms']}</span>";
            echo "</li>";
        }
        echo "</ul>";
    }

    echo "<h3>Skipped / Already Sent Today (" . count($skipped) . ")</h3>";
    if (!empty($skipped)) {
        echo "<ul>";
        foreach ($skipped as $s) {
            echo "<li>{$s['patient']} - {$s['medicine']} ({$s['time']}): <span class='badge skipped'>{$s['reason']}</span></li>";
        }
        echo "</ul>";
    }

    echo "<br><a href='admin_dashboard.php' style='display:inline-block;padding:8px 16px;background:#1e3a5f;color:white;text-decoration:none;border-radius:8px;'>Back to Admin</a>";
    echo " <a href='cron_reminders.php?force=1' style='display:inline-block;padding:8px 16px;background:#0d9488;color:white;text-decoration:none;border-radius:8px;'>Force Dispatch All Active Now</a>";
    echo "</div></body></html>";
}
?>
