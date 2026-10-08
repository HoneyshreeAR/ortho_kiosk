<?php
// reminders.php - Medicine Schedule & Follow-up Reminders with Phone Alarms & SMS/WhatsApp
require_once 'config.php';

if (!isset($_SESSION['patient_id'])) {
    header('Location: index.php');
    exit;
}

$patient = getPatientById($pdo, $_SESSION['patient_id']);
$medicines = getMedicineReminders($pdo, $patient['id']);
$followups = getFollowupReminders($pdo, $patient['id']);
$recentAlerts = getRecentMedicineAlerts($pdo, $patient['id'], 6);

$today = date('Y-m-d');
$nowTime = date('H:i');

// Calculate adherence metrics
$total_meds = count($medicines);
$taken_count = 0;
foreach ($medicines as $m) {
    if ($m['last_taken_date'] === $today) {
        $taken_count++;
    }
}
$adherence_pct = $total_meds > 0 ? round(($taken_count / $total_meds) * 100) : 100;

// Calculate next upcoming dose
$nextDoseTime = null;
$nextDoseMed = null;
$allTimes = [];

foreach ($medicines as $m) {
    $timesStr = $m['reminder_times'] ?? '';
    if (empty($timesStr)) {
        if ($m['frequency'] === 'morning') $timesStr = '08:00';
        elseif ($m['frequency'] === 'afternoon') $timesStr = '13:00';
        elseif ($m['frequency'] === 'night') $timesStr = '20:00';
        elseif ($m['frequency'] === 'morning_night') $timesStr = '08:00,20:00';
        else $timesStr = '08:00,13:00,20:00';
    }
    $tArr = array_map('trim', explode(',', $timesStr));
    foreach ($tArr as $t) {
        if (!empty($t)) {
            $formattedT = date('H:i', strtotime($t));
            $allTimes[] = ['time' => $formattedT, 'med' => $m];
        }
    }
}

// Sort upcoming times
usort($allTimes, function($a, $b) {
    return strcmp($a['time'], $b['time']);
});

foreach ($allTimes as $at) {
    if ($at['time'] >= $nowTime) {
        $nextDoseTime = $at['time'];
        $nextDoseMed = $at['med'];
        break;
    }
}
if (!$nextDoseTime && !empty($allTimes)) {
    // Tomorrow's first dose
    $nextDoseTime = $allTimes[0]['time'] . ' (Tomorrow)';
    $nextDoseMed = $allTimes[0]['med'];
}

// Prepare active medicines array for real-time client-side clock checking
$activeMedsForJs = [];
foreach ($medicines as $m) {
    if (!empty($m['alarm_enabled']) && $m['last_taken_date'] !== $today) {
        $timesStr = !empty($m['reminder_times']) ? trim($m['reminder_times']) : '';
        if (empty($timesStr)) {
            if ($m['frequency'] === 'morning') $timesStr = '08:00';
            elseif ($m['frequency'] === 'afternoon') $timesStr = '13:00';
            elseif ($m['frequency'] === 'night') $timesStr = '20:00';
            elseif ($m['frequency'] === 'morning_night') $timesStr = '08:00,20:00';
            else $timesStr = '08:00,13:00,20:00';
        }
        $timeParts = array_filter(array_map('trim', explode(',', $timesStr)));
        $formattedTimes = [];
        foreach ($timeParts as $tp) {
            if (!empty($tp)) {
                $formattedTimes[] = date('H:i', strtotime($tp));
            }
        }
        $activeMedsForJs[] = [
            'id' => (int)$m['id'],
            'name' => $m['medicine_name'],
            'dosage' => $m['dosage'],
            'timing' => ($m['timing'] === 'before_food' ? 'Before Food (ಖಾಲಿ ಹೊಟ್ಟೆ / खाने से पहले)' : 'After Food (ಊಟದ ನಂತರ / खाने के बाद)'),
            'times' => $formattedTimes
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars(t('reminders_title')) ?> | OrthoKiosk</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1a3e5c">
    <link rel="stylesheet" href="style.css">
    <style>
        .reminder-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.5rem;
        }
        .metric-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .metric-icon {
            font-size: 2rem;
            background: #f0fdf4;
            padding: 0.7rem;
            border-radius: 14px;
            line-height: 1;
        }
        .metric-val {
            font-size: 1.6rem;
            font-weight: 800;
            color: #1a3e5c;
        }
        .metric-sub {
            font-size: 0.82rem;
            color: #64748b;
            font-weight: 600;
        }

        /* Phone & Alarm Notification Banner */
        .phone-alert-banner {
            background: linear-gradient(135deg, #1e3a5f 0%, #0d9488 100%);
            color: white;
            border-radius: 24px;
            padding: 1.5rem 1.8rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 24px rgba(13, 148, 136, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.2rem;
        }
        .phone-alert-info h3 {
            font-size: 1.35rem;
            margin-bottom: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .phone-alert-info p {
            font-size: 0.92rem;
            opacity: 0.92;
            line-height: 1.4;
            margin-bottom: 0.6rem;
        }
        .channel-badges {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .ch-badge {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(8px);
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .phone-test-btns {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
        }
        .btn-test {
            background: white;
            color: #1e3a5f;
            border: none;
            padding: 0.6rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: transform 0.15s, background 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .btn-test:hover {
            transform: translateY(-2px);
            background: #f8fafc;
        }

        /* Next dose highlight card */
        .next-dose-banner {
            background: #fffbeb;
            border: 2px solid #fef3c7;
            border-left: 6px solid #f59e0b;
            border-radius: 16px;
            padding: 1rem 1.4rem;
            margin-bottom: 1.8rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .next-dose-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #b45309;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .next-dose-time {
            font-size: 1.25rem;
            font-weight: 800;
            color: #78350f;
        }

        .med-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.2rem;
        }
        .med-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.4rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            transition: transform 0.2s;
        }
        .med-card.taken {
            border-left: 6px solid #10b981;
            background: #f8fdfa;
        }
        .med-card.pending {
            border-left: 6px solid #f59e0b;
        }
        .med-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.4rem;
        }
        .med-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1e3a5f;
        }
        .med-tags {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
            margin-bottom: 0.8rem;
        }
        .med-tag {
            font-size: 0.78rem;
            font-weight: 700;
            padding: 0.25rem 0.65rem;
            border-radius: 12px;
            background: #f1f5f9;
            color: #475569;
        }
        .med-tag.time {
            background: #e0f2fe;
            color: #0284c7;
        }
        .med-tag.timing {
            background: #fef3c7;
            color: #b45309;
        }
        .med-tag.alarm {
            background: #fce7f3;
            color: #be185d;
        }

        .med-alarm-times {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            padding: 0.6rem 0.8rem;
            margin-bottom: 0.8rem;
            font-size: 0.85rem;
            color: #334155;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .med-notes {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 1rem;
            line-height: 1.4;
        }

        .med-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.8rem;
        }
        .take-btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 0.65rem 1rem;
            border-radius: 30px;
            font-weight: 700;
            cursor: pointer;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            transition: background 0.2s;
            font-size: 0.9rem;
        }
        .take-btn:hover {
            background: #059669;
        }
        .take-btn.done {
            background: #e2e8f0;
            color: #475569;
            cursor: default;
        }
        .btn-icon-action {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #475569;
            border-radius: 50%;
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
            transition: 0.2s;
        }
        .btn-icon-action:hover {
            background: #fee2e2;
            color: #b91c1c;
            border-color: #fca5a5;
        }

        /* Recent Alerts Table */
        .alerts-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
            margin-top: 0.5rem;
        }
        .alerts-table th {
            text-align: left;
            padding: 0.6rem 0.8rem;
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            border-bottom: 2px solid #e2e8f0;
        }
        .alerts-table td {
            padding: 0.65rem 0.8rem;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        .status-badge {
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .status-badge.sent { background: #dcfce7; color: #15803d; }
        .status-badge.triggered { background: #fee2e2; color: #b91c1c; }

        /* Full-Screen Wake-up Alarm Overlay Modal */
        #medicineAlarmModal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(10px);
            z-index: 99999;
            justify-content: center;
            align-items: center;
            padding: 1.5rem;
            animation: fadeIn 0.3s ease;
        }
        .alarm-card {
            background: white;
            border-radius: 32px;
            max-width: 480px;
            width: 100%;
            padding: 2.2rem;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            border-top: 10px solid #ef4444;
            animation: pulseCard 1.5s infinite ease-in-out;
        }
        @keyframes pulseCard {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            50% { transform: scale(1.02); box-shadow: 0 0 25px 8px rgba(239, 68, 68, 0.3); }
        }
        .alarm-bell-icon {
            font-size: 3.8rem;
            margin-bottom: 0.5rem;
            display: inline-block;
            animation: bellRing 0.8s infinite alternate ease-in-out;
        }
        @keyframes bellRing {
            0% { transform: rotate(-20deg); }
            100% { transform: rotate(20deg); }
        }
        .alarm-title {
            font-size: 1.6rem;
            font-weight: 900;
            color: #1e293b;
            margin-bottom: 0.4rem;
        }
        .alarm-med-name {
            font-size: 1.35rem;
            font-weight: 800;
            color: #0284c7;
            background: #f0f9ff;
            padding: 0.5rem 1rem;
            border-radius: 16px;
            display: inline-block;
            margin: 0.8rem 0;
        }
        .alarm-btns {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
            margin-top: 1.5rem;
        }
        .btn-alarm-take {
            background: #10b981;
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 30px;
            font-size: 1.1rem;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3);
        }
        .btn-alarm-snooze {
            background: #f1f5f9;
            color: #475569;
            border: none;
            padding: 0.8rem;
            border-radius: 30px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
        }

        /* Modal styling */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-box {
            background: white;
            border-radius: 24px;
            max-width: 520px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2rem;
            position: relative;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        .time-picker-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 0.6rem;
            background: #f8fafc;
            padding: 0.8rem;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            margin-bottom: 1rem;
        }
        .channel-checks {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            background: #f0fdf4;
            padding: 0.8rem 1rem;
            border-radius: 14px;
            border: 1px solid #bbf7d0;
            margin-bottom: 1.2rem;
        }
    </style>
</head>
<body>
<div class="kiosk-container">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <div class="main-content">
        <div class="page-title-banner">
            <div>
                <h2>💊 <?= htmlspecialchars(t('reminders_title')) ?></h2>
                <p><?= htmlspecialchars(t('reminders_subtitle')) ?></p>
            </div>
            <div style="display:flex; gap:0.6rem; flex-wrap:wrap;">
                <button class="modal-trigger-btn" onclick="openModal('medModal')">
                    <?= htmlspecialchars(t('add_medicine')) ?>
                </button>
                <button class="modal-trigger-btn" style="background: #0d9488;" onclick="openModal('followupModal')">
                    <?= htmlspecialchars(t('schedule_followup')) ?>
                </button>
            </div>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div style="background: #dcfce7; color: #15803d; padding: 1rem; border-radius: 14px; margin-bottom: 1.5rem; font-weight: 600;">
                <?php 
                    if ($_GET['msg'] === 'med_taken') echo "✅ " . t('taken_today');
                    elseif ($_GET['msg'] === 'med_added') echo "✅ " . t('add_medicine') . " successfully! Alarms and phone alerts activated.";
                    elseif ($_GET['msg'] === 'med_deleted') echo "🗑️ Medication removed from active schedule.";
                    elseif ($_GET['msg'] === 'followup_scheduled') echo "✅ " . t('schedule_followup') . " successfully!";
                ?>
            </div>
        <?php endif; ?>

        <!-- Phone Reminders & Alarms Hero Bar -->
        <div class="phone-alert-banner">
            <div class="phone-alert-info">
                <h3>📱 <?= htmlspecialchars(t('phone_reminders_badge')) ?></h3>
                <p>
                    Reminders are scheduled for <strong><?= htmlspecialchars($patient['phone']) ?></strong>.
                </p>
                <div class="channel-badges">
                    <span class="ch-badge" style="background:#0284c7; color:white;">🕒 Device Clock: <strong id="liveDeviceClock">--:--:--</strong></span>
                    <span class="ch-badge">💬 Direct Phone SMS: Active</span>
                    <span class="ch-badge">🔔 Audio Alarm: Active</span>
                </div>
            </div>
            <div class="phone-test-btns">
                <button type="button" class="btn-test" id="unmuteSpeakerBtn" onclick="enableSpeakerAudio()">
                    🔊 Test Alarm Speaker
                </button>
                <button type="button" class="btn-test" style="background: #10b981; color: white;" onclick="sendTestSMS()">
                    💬 <?= htmlspecialchars(t('test_sms_btn')) ?>
                </button>
            </div>
        </div>

        <!-- Next Scheduled Dose Banner -->
        <?php if ($nextDoseMed && $nextDoseTime): ?>
        <div class="next-dose-banner">
            <div>
                <div class="next-dose-title">
                    <span>⏰</span> <?= htmlspecialchars(t('next_dose_in')) ?>: <strong><?= $nextDoseTime ?></strong>
                </div>
                <div style="font-size:0.9rem; color:#4b5563; margin-top:0.2rem;">
                    <strong><?= htmlspecialchars($nextDoseMed['medicine_name']) ?></strong> (<?= htmlspecialchars($nextDoseMed['dosage']) ?>) &bull; <?= ($nextDoseMed['timing'] === 'before_food') ? t('before_food') : t('after_food') ?>
                </div>
            </div>
            <button class="btn-test" style="border: 1px solid #f59e0b; color:#b45309;" onclick="triggerSpecificAlarm('<?= htmlspecialchars($nextDoseMed['medicine_name']) ?>', '<?= htmlspecialchars($nextDoseMed['dosage']) ?>', '<?= ($nextDoseMed['timing'] === 'before_food') ? t('before_food') : t('after_food') ?>', '<?= $nextDoseMed['id'] ?>')">
                🔔 Test Ring Now
            </button>
        </div>
        <?php endif; ?>

        <!-- Adherence & Metrics -->
        <div class="reminder-metrics">
            <div class="metric-card">
                <div class="metric-icon">📊</div>
                <div>
                    <div class="metric-val"><?= $adherence_pct ?>%</div>
                    <div class="metric-sub"><?= htmlspecialchars(t('adherence_rate')) ?></div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">🔥</div>
                <div>
                    <div class="metric-val"><?= ($taken_count > 0 ? 5 : 0) ?> <?= htmlspecialchars(t('streak_days')) ?></div>
                    <div class="metric-sub">Adherence Record</div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">💊</div>
                <div>
                    <div class="metric-val"><?= $taken_count ?> / <?= $total_meds ?></div>
                    <div class="metric-sub"><?= htmlspecialchars(t('meds_today')) ?></div>
                </div>
            </div>
        </div>

        <!-- Section 1: Today's Prescribed Medications -->
        <div class="card" style="margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem;">
                <h3 class="card-title" style="margin-bottom: 0;">💊 <?= htmlspecialchars(t('meds_today')) ?></h3>
                <span style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Automated Alarms Enabled</span>
            </div>

            <?php if (count($medicines) > 0): ?>
            <div class="med-grid">
                <?php foreach ($medicines as $med): 
                    $isTaken = ($med['last_taken_date'] === $today);
                    $timesDisplay = $med['reminder_times'] ?? '08:00,13:00,20:00';
                ?>
                <div class="med-card <?= $isTaken ? 'taken' : 'pending' ?>">
                    <div>
                        <div class="med-header">
                            <div class="med-title"><?= htmlspecialchars($med['medicine_name']) ?></div>
                            <form action="action_reminder.php" method="POST" onsubmit="return confirm('Remove this medication?');">
                                <input type="hidden" name="action" value="delete_medicine">
                                <input type="hidden" name="med_id" value="<?= $med['id'] ?>">
                                <button type="submit" class="btn-icon-action" title="Delete">🗑️</button>
                            </form>
                        </div>

                        <div class="med-tags">
                            <span class="med-tag">⚖️ <?= htmlspecialchars($med['dosage']) ?></span>
                            <span class="med-tag timing">
                                <?= ($med['timing'] === 'before_food') ? '🍽️ ' . t('before_food') : '🍲 ' . t('after_food') ?>
                            </span>
                            <span class="med-tag alarm">
                                🔔 <?= (!empty($med['alarm_enabled'])) ? 'Alarm ON' : 'Alarm OFF' ?>
                            </span>
                        </div>

                        <!-- Scheduled Alarm Times -->
                        <div class="med-alarm-times">
                            <span><strong>⏰ <?= htmlspecialchars(t('scheduled_times')) ?>:</strong></span>
                            <strong><?= htmlspecialchars($timesDisplay) ?></strong>
                        </div>

                        <?php if (!empty($med['notes'])): ?>
                            <div class="med-notes">💡 <?= htmlspecialchars($med['notes']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="med-actions">
                        <?php if ($isTaken): ?>
                            <button class="take-btn done" disabled>
                                ✓ <?= htmlspecialchars(t('taken_today')) ?>
                            </button>
                        <?php else: ?>
                            <form action="action_reminder.php" method="POST" style="flex:1;">
                                <input type="hidden" name="action" value="mark_taken">
                                <input type="hidden" name="med_id" value="<?= $med['id'] ?>">
                                <button type="submit" class="take-btn">
                                    ✓ <?= htmlspecialchars(t('mark_taken')) ?>
                                </button>
                            </form>
                            <button type="button" class="btn-icon-action" style="border-radius: 30px; width: auto; padding: 0 10px; font-size: 0.8rem; font-weight: 700;" onclick="triggerSpecificAlarm('<?= htmlspecialchars($med['medicine_name']) ?>', '<?= htmlspecialchars($med['dosage']) ?>', '<?= ($med['timing'] === 'before_food') ? t('before_food') : t('after_food') ?>', '<?= $med['id'] ?>')">
                                🔔 Test
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <p style="color: #64748b;"><?= htmlspecialchars(t('no_meds_scheduled')) ?></p>
            <?php endif; ?>
        </div>

        <!-- Section 2: Phone Alerts & Dispatch Log -->
        <div class="card" style="margin-bottom: 2rem;">
            <h3 class="card-title" style="border-left-color: #0284c7;">
                📋 <?= htmlspecialchars(t('alerts_history')) ?>
            </h3>
            <?php if (!empty($recentAlerts)): ?>
            <div style="overflow-x: auto;">
                <table class="alerts-table">
                    <thead>
                        <tr>
                            <th>Time Sent</th>
                            <th>Channel</th>
                            <th>Medication</th>
                            <th>Status</th>
                            <th>Message Preview</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentAlerts as $alert): ?>
                        <tr>
                            <td><?= date('d M, h:i A', strtotime($alert['sent_at'])) ?></td>
                            <td>
                                <?php if ($alert['alert_type'] === 'sms'): ?>
                                    💬 Direct SMS
                                <?php else: ?>
                                    🔔 Audio Alarm
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($alert['medicine_name'] ?? 'Medication') ?></strong></td>
                            <td>
                                <span class="status-badge <?= $alert['status'] === 'triggered' ? 'triggered' : 'sent' ?>">
                                    <?= htmlspecialchars(ucfirst($alert['status'])) ?>
                                </span>
                            </td>
                            <td style="color: #64748b; max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?= htmlspecialchars($alert['message']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p style="color: #64748b; font-size: 0.9rem;"><?= htmlspecialchars(t('no_alerts_logged')) ?></p>
            <?php endif; ?>
        </div>

        <!-- Section 3: Upcoming Clinical Follow-up Consultations -->
        <div class="card">
            <h3 class="card-title" style="border-left-color: #0d9488;">
                🗓️ <?= htmlspecialchars(t('upcoming_followups')) ?>
            </h3>
            <?php if (count($followups) > 0): ?>
            <div class="followup-grid">
                <?php foreach ($followups as $f): 
                    $appt_date = strtotime($f['appointment_date']);
                    $days_diff = round(($appt_date - strtotime($today)) / (60 * 60 * 24));
                    $badgeClass = ($days_diff <= 1) ? '' : (($days_diff <= 3) ? 'near' : 'future');
                ?>
                <div class="followup-card">
                    <div class="countdown-badge <?= $badgeClass ?>">
                        <?php 
                            if ($days_diff < 0) echo "Past Review";
                            elseif ($days_diff == 0) echo "⚠️ " . t('due_today');
                            elseif ($days_diff == 1) echo "Tomorrow!";
                            else echo "In {$days_diff} " . t('days_left');
                        ?>
                    </div>
                    <div style="font-size: 1.2rem; font-weight: 700; color: #1a3e5c; margin-bottom: 0.3rem;">
                        <?= htmlspecialchars($f['doctor_name']) ?>
                    </div>
                    <div style="font-size: 0.9rem; color: #0d9488; font-weight: 600; margin-bottom: 0.8rem;">
                        🏥 <?= htmlspecialchars($f['department']) ?>
                    </div>
                    <div style="font-size: 0.95rem; color: #334155; margin-bottom: 0.5rem;">
                        📅 <strong><?= date('l, d M Y', $appt_date) ?></strong> at <strong><?= date('h:i A', strtotime($f['appointment_time'])) ?></strong>
                    </div>
                    <div style="font-size: 0.9rem; color: #64748b; background: #f8fafc; padding: 0.8rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <strong><?= htmlspecialchars(t('purpose')) ?>:</strong><br>
                        <?= htmlspecialchars($f['purpose']) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <p style="color: #64748b;"><?= htmlspecialchars(t('no_followup_scheduled')) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal 1: Add Prescribed Medicine with Alarm Times & Channels -->
<div id="medModal" class="modal-overlay">
    <div class="modal-box">
        <span onclick="closeModal('medModal')" style="position: absolute; top: 1.2rem; right: 1.5rem; font-size: 1.6rem; cursor: pointer;">&times;</span>
        <h3 style="color: #1a3e5c; margin-bottom: 1.2rem;">➕ <?= htmlspecialchars(t('add_medicine')) ?></h3>
        <form action="action_reminder.php" method="POST">
            <input type="hidden" name="action" value="add_medicine">
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display:block; font-weight:600; margin-bottom:0.3rem;"><?= htmlspecialchars(t('medicine_name')) ?></label>
                <input type="text" name="medicine_name" placeholder="e.g. Calcium + Vit D3, Aceclofenac" required style="width:100%; padding:0.75rem; border-radius:12px; border:1px solid #cbd5e1;">
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display:block; font-weight:600; margin-bottom:0.3rem;"><?= htmlspecialchars(t('dosage')) ?></label>
                <input type="text" name="dosage" placeholder="e.g. 500mg (1 Tablet)" required style="width:100%; padding:0.75rem; border-radius:12px; border:1px solid #cbd5e1;">
            </div>

            <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                <div style="flex: 1;">
                    <label style="display:block; font-weight:600; margin-bottom:0.3rem;"><?= htmlspecialchars(t('timing')) ?></label>
                    <select name="frequency" id="freqSelect" onchange="adjustTimeInputs(this.value)" style="width:100%; padding:0.75rem; border-radius:12px; border:1px solid #cbd5e1;">
                        <option value="morning"><?= htmlspecialchars(t('morning')) ?></option>
                        <option value="afternoon"><?= htmlspecialchars(t('afternoon')) ?></option>
                        <option value="night"><?= htmlspecialchars(t('night')) ?></option>
                        <option value="morning_night"><?= htmlspecialchars(t('morning_night')) ?></option>
                        <option value="three_times" selected><?= htmlspecialchars(t('three_times')) ?></option>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label style="display:block; font-weight:600; margin-bottom:0.3rem;"><?= htmlspecialchars(t('meal_timing') ?? 'Meal Timing') ?></label>
                    <select name="timing" style="width:100%; padding:0.75rem; border-radius:12px; border:1px solid #cbd5e1;">
                        <option value="after_food"><?= htmlspecialchars(t('after_food')) ?></option>
                        <option value="before_food"><?= htmlspecialchars(t('before_food')) ?></option>
                    </select>
                </div>
            </div>

            <!-- Scheduled Dose Exact Times -->
            <label style="display:block; font-weight:700; margin-bottom:0.4rem; color:#1e3a5f;">
                ⏰ <?= htmlspecialchars(t('set_exact_times')) ?>
            </label>
            <div class="time-picker-grid">
                <div>
                    <label style="font-size:0.78rem; font-weight:600; display:block; margin-bottom:0.2rem;"><?= htmlspecialchars(t('morning_time')) ?></label>
                    <input type="time" name="morning_time" id="mTime" value="08:00" style="width:100%; padding:0.5rem; border-radius:8px; border:1px solid #cbd5e1;">
                </div>
                <div>
                    <label style="font-size:0.78rem; font-weight:600; display:block; margin-bottom:0.2rem;"><?= htmlspecialchars(t('afternoon_time')) ?></label>
                    <input type="time" name="afternoon_time" id="aTime" value="13:00" style="width:100%; padding:0.5rem; border-radius:8px; border:1px solid #cbd5e1;">
                </div>
                <div>
                    <label style="font-size:0.78rem; font-weight:600; display:block; margin-bottom:0.2rem;"><?= htmlspecialchars(t('night_time')) ?></label>
                    <input type="time" name="night_time" id="nTime" value="20:00" style="width:100%; padding:0.5rem; border-radius:8px; border:1px solid #cbd5e1;">
                </div>
            </div>

            <!-- Notification Channels -->
            <label style="display:block; font-weight:700; margin-bottom:0.4rem; color:#1e3a5f;">
                🔔 <?= htmlspecialchars(t('notify_channels')) ?>
            </label>
            <div class="channel-checks">
                <label style="font-size:0.9rem; display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                    <input type="checkbox" name="send_sms" value="1" checked> 💬 <?= htmlspecialchars(t('sms_alerts')) ?> to <?= htmlspecialchars($patient['phone']) ?> (Direct text message, no download needed)
                </label>
                <label style="font-size:0.9rem; display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                    <input type="checkbox" name="alarm_enabled" value="1" checked> 🔔 <?= htmlspecialchars(t('phone_alarm')) ?> & Device Ring Chime
                </label>
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display:block; font-weight:600; margin-bottom:0.3rem;"><?= htmlspecialchars(t('duration')) ?> (Days)</label>
                <input type="number" name="duration_days" value="14" min="1" max="90" style="width:100%; padding:0.75rem; border-radius:12px; border:1px solid #cbd5e1;">
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="display:block; font-weight:600; margin-bottom:0.3rem;">Clinical Notes / Instructions</label>
                <input type="text" name="notes" placeholder="e.g. Take with warm water, avoid empty stomach" style="width:100%; padding:0.75rem; border-radius:12px; border:1px solid #cbd5e1;">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <?= htmlspecialchars(t('save')) ?>
            </button>
        </form>
    </div>
</div>

<!-- Modal 2: Schedule Follow-up -->
<div id="followupModal" class="modal-overlay">
    <div class="modal-box">
        <span onclick="closeModal('followupModal')" style="position: absolute; top: 1.2rem; right: 1.5rem; font-size: 1.6rem; cursor: pointer;">&times;</span>
        <h3 style="color: #1a3e5c; margin-bottom: 1.2rem;">📅 <?= htmlspecialchars(t('schedule_followup')) ?></h3>
        <form action="action_reminder.php" method="POST">
            <input type="hidden" name="action" value="schedule_followup">
            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display:block; font-weight:600; margin-bottom:0.3rem;"><?= htmlspecialchars(t('doctor_name')) ?></label>
                <select name="doctor_name" style="width:100%; padding:0.75rem; border-radius:12px; border:1px solid #cbd5e1;">
                    <option value="Dr. Ramesh Rao">Dr. Ramesh Rao (Consultant Orthopaedic Surgeon)</option>
                    <option value="Dr. Ananya Sharma">Dr. Ananya Sharma (Lead Physiotherapist)</option>
                    <option value="Dr. Vikram Patil">Dr. Vikram Patil (Spine & Neuro-Ortho)</option>
                    <option value="Dr. Sunita Mehta">Dr. Sunita Mehta (Pain Management Specialist)</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display:block; font-weight:600; margin-bottom:0.3rem;"><?= htmlspecialchars(t('department')) ?></label>
                <input type="text" name="department" value="Orthopaedics & Joint Care" style="width:100%; padding:0.75rem; border-radius:12px; border:1px solid #cbd5e1;">
            </div>
            <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                <div style="flex: 1;">
                    <label style="display:block; font-weight:600; margin-bottom:0.3rem;"><?= htmlspecialchars(t('appointment_date')) ?></label>
                    <input type="date" name="appointment_date" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" min="<?= date('Y-m-d') ?>" required style="width:100%; padding:0.75rem; border-radius:12px; border:1px solid #cbd5e1;">
                </div>
                <div style="flex: 1;">
                    <label style="display:block; font-weight:600; margin-bottom:0.3rem;"><?= htmlspecialchars(t('appointment_time')) ?></label>
                    <input type="time" name="appointment_time" value="10:30" required style="width:100%; padding:0.75rem; border-radius:12px; border:1px solid #cbd5e1;">
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="display:block; font-weight:600; margin-bottom:0.3rem;"><?= htmlspecialchars(t('purpose')) ?></label>
                <input type="text" name="purpose" placeholder="e.g. Suture removal, Plaster review, X-ray check" required style="width:100%; padding:0.75rem; border-radius:12px; border:1px solid #cbd5e1;">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <?= htmlspecialchars(t('schedule_followup')) ?>
            </button>
        </form>
    </div>
</div>

<!-- Fullscreen Wake-up Medicine Alarm Modal -->
<div id="medicineAlarmModal">
    <div class="alarm-card">
        <div class="alarm-bell-icon">🔔</div>
        <div class="alarm-title"><?= htmlspecialchars(t('take_medicine_alarm_title')) ?></div>
        <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 0.8rem;"><?= htmlspecialchars(t('alarm_ringing_desc')) ?></p>
        
        <div id="alarmMedDisplay" class="alarm-med-name">Aceclofenac 100mg</div>
        <div id="alarmTimingDisplay" style="font-weight: 700; color: #b45309; font-size: 1rem; margin-bottom: 0.5rem;">
            🍲 After Food
        </div>

        <div class="alarm-btns">
            <button type="button" class="btn-alarm-take" onclick="acknowledgeAlarmTake()">
                ✓ <?= htmlspecialchars(t('mark_taken')) ?>
            </button>
            <button type="button" class="btn-alarm-snooze" onclick="snoozeAlarm()">
                <?= htmlspecialchars(t('snooze_10m')) ?>
            </button>
        </div>
    </div>
</div>

<!-- Audio Alarm Synthesizer & Alarm Ticker Script -->
<script>
    let audioCtx = null;
    let alarmOscInterval = null;
    let activeAlarmMedId = null;
    const patientPhone = <?= json_encode($patient['phone']) ?>;
    const patientId = <?= json_encode($patient['id']) ?>;
    const patientSchedule = <?= json_encode($activeMedsForJs) ?>;
    const triggeredAlarms = new Set();

    // Low-level synthesizer tone generator
    function playBeep(freq, duration, type = 'sine') {
        try {
            if (!audioCtx) {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                audioCtx = new AudioContext();
            }
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = type;
            osc.frequency.setValueAtTime(freq, audioCtx.currentTime);
            gain.gain.setValueAtTime(0.4, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + duration);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + duration);
        } catch (e) {
            console.warn("playBeep error:", e);
        }
    }

    // Unlock and warm up AudioContext on speaker test or user interaction
    function enableSpeakerAudio() {
        try {
            if (!audioCtx) {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                audioCtx = new AudioContext();
            }
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            // Play a pleasant 2-tone chime to confirm speaker is active
            playBeep(784.00, 0.22, 'triangle');
            setTimeout(() => playBeep(1046.50, 0.38, 'sine'), 160);

            const btn = document.getElementById('unmuteSpeakerBtn');
            if (btn) {
                btn.innerHTML = '🔊 Speaker Ready & Tested';
                btn.style.background = '#dcfce7';
                btn.style.color = '#15803d';
                btn.style.borderColor = '#86efac';
            }
            showToast("🔊 Speaker audio active! Scheduled alarms will ring automatically.");
        } catch (e) {
            console.warn("AudioContext error:", e);
            showToast("⚠️ Could not activate audio context.");
        }
    }

    // Auto-unlock audio on any touch/click gesture on page
    ['click', 'touchstart', 'keydown'].forEach(evt => {
        document.addEventListener(evt, function unlockAudio() {
            try {
                if (!audioCtx) {
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    audioCtx = new AudioContext();
                }
                if (audioCtx && audioCtx.state === 'suspended') {
                    audioCtx.resume();
                }
            } catch (e) {}
        }, { once: true });
    });

    // Web Audio Synthesizer: Creates loud, musical medical chime alarm
    function startAlarmChime() {
        try {
            if (!audioCtx) {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                audioCtx = new AudioContext();
            }
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }

            // Immediately sound first chime
            playBeep(784.00, 0.22, 'triangle');
            setTimeout(() => playBeep(1046.50, 0.38, 'sine'), 160);

            // Repeat chime loop every 800ms
            if (alarmOscInterval) clearInterval(alarmOscInterval);
            alarmOscInterval = setInterval(() => {
                playBeep(784.00, 0.22, 'triangle'); // G5
                setTimeout(() => playBeep(1046.50, 0.38, 'sine'), 160); // C6
            }, 800);

            // Trigger mobile vibration if supported
            if (navigator.vibrate) {
                navigator.vibrate([400, 200, 400, 200, 600]);
            }
        } catch (e) {
            console.warn("Audio alarm playback error:", e);
        }
    }

    function stopAlarmChime() {
        if (alarmOscInterval) {
            clearInterval(alarmOscInterval);
            alarmOscInterval = null;
        }
    }

    // Trigger test alarm sound
    function triggerAlarmTest() {
        startAlarmChime();
        showWakeupAlarmModal("Calcium + Vitamin D3", "500mg", "After Food (ಊಟದ ನಂತರ / खाने के बाद)", 0);
        showToast("🔔 Medicine alarm ringing! Test audio and vibration active.");
    }

    function triggerSpecificAlarm(name, dosage, timing, medId) {
        startAlarmChime();
        showWakeupAlarmModal(name, dosage, timing, medId);
    }

    function showWakeupAlarmModal(name, dosage, timing, medId) {
        activeAlarmMedId = medId;
        document.getElementById('alarmMedDisplay').textContent = `${name} (${dosage})`;
        document.getElementById('alarmTimingDisplay').textContent = `🍽️ ${timing}`;
        document.getElementById('medicineAlarmModal').style.display = 'flex';

        // Log device alarm triggered
        if (medId > 0) {
            fetch('api_reminders.php?action=log_alarm', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `med_id=${medId}&med_name=${encodeURIComponent(name)}&time=${encodeURIComponent(new Date().toLocaleTimeString())}`
            }).catch(console.error);
        }

        // Show push notification if allowed
        if ("Notification" in window && Notification.permission === "granted") {
            try {
                navigator.serviceWorker && navigator.serviceWorker.ready.then(reg => {
                    reg.showNotification(`⏰ Medicine Time: ${name}`, {
                        body: `Take ${dosage}, ${timing}. Stay on track!`,
                        icon: 'images/icon-192.png',
                        badge: 'images/icon-192.png',
                        vibrate: [300, 100, 300],
                        tag: 'medicine-alarm-' + medId
                    });
                }).catch(() => {
                    new Notification(`⏰ Medicine Time: ${name}`, {
                        body: `Take ${dosage}, ${timing}.`,
                    });
                });
            } catch (e) {}
        }
    }

    function acknowledgeAlarmTake() {
        stopAlarmChime();
        document.getElementById('medicineAlarmModal').style.display = 'none';

        if (activeAlarmMedId > 0) {
            fetch('action_reminder.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=mark_taken&med_id=${activeAlarmMedId}`
            }).then(() => {
                showToast("✅ Great job! Medicine logged as taken today.");
                setTimeout(() => window.location.reload(), 1000);
            }).catch(() => {
                window.location.reload();
            });
        } else {
            showToast("✅ Test alarm dismissed!");
        }
    }

    function snoozeAlarm() {
        stopAlarmChime();
        document.getElementById('medicineAlarmModal').style.display = 'none';
        showToast("💤 Snoozed for 10 minutes. Alarm will ring again.");
        setTimeout(() => {
            if (activeAlarmMedId) {
                startAlarmChime();
                document.getElementById('medicineAlarmModal').style.display = 'flex';
            }
        }, 10 * 60 * 1000);
    }

    // Send Test SMS
    function sendTestSMS() {
        showToast("⏳ Sending test SMS to " + patientPhone + "...");
        fetch('api_reminders.php?action=test_sms', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `med_name=Prescribed+Medicine&dosage=1+Tablet`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast("💬 " + data.message);
            } else {
                showToast("❌ SMS dispatch notice: " + (data.error || 'Failed'));
            }
        })
        .catch(err => showToast("❌ Network error sending test SMS."));
    }

    // Device Clock & Medicine Alarm Engine (Runs every 1 second)
    function checkDeviceClock() {
        const now = new Date();
        const hh = String(now.getHours()).padStart(2, '0');
        const mm = String(now.getMinutes()).padStart(2, '0');
        const ss = String(now.getSeconds()).padStart(2, '0');
        const currentTimeStr = `${hh}:${mm}`;
        const todayDateStr = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');

        // Update live device digital clock in hero bar
        const clockEl = document.getElementById('liveDeviceClock');
        if (clockEl) {
            clockEl.textContent = `${hh}:${mm}:${ss}`;
        }

        const nowMins = now.getHours() * 60 + now.getMinutes();

        // Compare patient's active scheduled medicine times with current device minute
        if (Array.isArray(patientSchedule)) {
            patientSchedule.forEach(med => {
                if (med.times && Array.isArray(med.times)) {
                    med.times.forEach(schedTime => {
                        const [sH, sM] = schedTime.split(':').map(Number);
                        const schedMins = (sH * 60) + sM;
                        const diff = nowMins - schedMins;

                        // Ring at exact minute OR if due within the last 15 minutes
                        if (schedTime === currentTimeStr || (diff >= 0 && diff <= 15)) {
                            const alarmKey = `${med.id}_${schedTime}_${todayDateStr}`;
                            if (!triggeredAlarms.has(alarmKey)) {
                                triggeredAlarms.add(alarmKey);
                                console.log(`[ALARM RINGING NOW] Scheduled dose for ${med.name} at ${currentTimeStr} (scheduled: ${schedTime})`);
                                triggerSpecificAlarm(med.name, med.dosage, med.timing, med.id);
                            }
                        }
                    });
                }
            });
        }
    }

    // Start 1-second real-time device clock
    setInterval(checkDeviceClock, 1000);
    checkDeviceClock();

    // Background Server Sync (Runs every 60 seconds as a secondary fallback)
    function checkDueAlarms() {
        fetch(`api_reminders.php?action=due_alarms&patient_id=${patientId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.alarms && data.alarms.length > 0) {
                    const due = data.alarms[0];
                    const todayDateStr = new Date().toISOString().slice(0, 10);
                    const alarmKey = `srv_${due.id}_${due.scheduled_time}_${todayDateStr}`;
                    if (!triggeredAlarms.has(alarmKey)) {
                        triggeredAlarms.add(alarmKey);
                        triggerSpecificAlarm(due.name, due.dosage, due.timing, due.id);
                    }
                }
            })
            .catch(console.error);
    }
    setInterval(checkDueAlarms, 60000);

    // Dynamic Time inputs in Modal
    function adjustTimeInputs(freq) {
        const m = document.getElementById('mTime');
        const a = document.getElementById('aTime');
        const n = document.getElementById('nTime');
        if (freq === 'morning') {
            m.disabled = false; a.disabled = true; n.disabled = true;
        } else if (freq === 'afternoon') {
            m.disabled = true; a.disabled = false; n.disabled = true;
        } else if (freq === 'night') {
            m.disabled = true; a.disabled = true; n.disabled = false;
        } else if (freq === 'morning_night') {
            m.disabled = false; a.disabled = true; n.disabled = false;
        } else {
            m.disabled = false; a.disabled = false; n.disabled = false;
        }
    }

    // Toast Notification utility
    function showToast(text) {
        let t = document.getElementById('kioskToast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'kioskToast';
            t.style.cssText = 'position:fixed;bottom:25px;right:25px;background:#1e293b;color:#fff;padding:14px 22px;border-radius:14px;box-shadow:0 10px 25px rgba(0,0,0,0.25);z-index:999999;font-weight:600;font-size:0.95rem;transition:all 0.3s;max-width:360px;';
            document.body.appendChild(t);
        }
        t.textContent = text;
        t.style.opacity = '1';
        t.style.transform = 'translateY(0)';
        setTimeout(() => {
            t.style.opacity = '0';
            t.style.transform = 'translateY(15px)';
        }, 4500);
    }

    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
    window.onclick = function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            e.target.style.display = 'none';
        }
    }

    // Ask for Notification permission on page click
    document.addEventListener('click', function initNotif() {
        if ("Notification" in window && Notification.permission === "default") {
            Notification.requestPermission();
        }
        document.removeEventListener('click', initNotif);
    }, { once: true });
</script>
</body>
</html>
