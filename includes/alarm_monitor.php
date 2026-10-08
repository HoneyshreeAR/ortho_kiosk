<?php
// includes/alarm_monitor.php - Global Real-Time Automated Medicine Alarm Engine
// Automatically active on all patient pages via includes/navbar.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$monitor_patient_id = $_SESSION['patient_id'] ?? 0;
if (!$monitor_patient_id || !isset($pdo)) {
    return;
}

// Fetch active medicines with alarms enabled for today
$monitor_today = date('Y-m-d');
$stmt_alarm = $pdo->prepare("SELECT * FROM medicine_reminders 
    WHERE patient_id = ? 
      AND alarm_enabled = 1
      AND start_date <= CURDATE() 
      AND DATE_ADD(start_date, INTERVAL duration_days DAY) >= CURDATE()
      AND (last_taken_date IS NULL OR last_taken_date != CURDATE())");
$stmt_alarm->execute([$monitor_patient_id]);
$monitor_meds = $stmt_alarm->fetchAll(PDO::FETCH_ASSOC);

$global_schedule = [];
foreach ($monitor_meds as $m) {
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
    $global_schedule[] = [
        'id' => (int)$m['id'],
        'name' => $m['medicine_name'],
        'dosage' => $m['dosage'],
        'timing' => ($m['timing'] === 'before_food' ? 'Before Food (ಖಾಲಿ ಹೊಟ್ಟೆ / खाने से पहले)' : 'After Food (ಊಟದ ನಂತರ / खाने के बाद)'),
        'times' => $formattedTimes
    ];
}

// Check if current page is reminders.php (to avoid duplicating modal if already present)
$is_reminders_page = (basename($_SERVER['PHP_SELF']) === 'reminders.php');
?>

<?php if (!$is_reminders_page): ?>
<!-- Global Medicine Alarm Overlay Modal -->
<div id="globalAlarmModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.85); backdrop-filter:blur(8px); z-index:9999999; align-items:center; justify-content:center; padding:1.5rem; box-sizing:border-box;">
    <div style="background:#ffffff; border-radius:28px; padding:2rem 2.2rem; max-width:440px; width:100%; text-align:center; box-shadow:0 25px 60px rgba(0,0,0,0.3); border:3px solid #ef4444; animation: pulseAlert 1s infinite alternate; font-family:inherit;">
        <div style="font-size:3.5rem; animation: shakeBell 0.6s infinite alternate; display:inline-block; margin-bottom:0.5rem;">🔔</div>
        <h2 style="margin:0 0 0.4rem; color:#dc2626; font-size:1.6rem; font-weight:800;">⏰ Medicine Time!</h2>
        <p style="color:#64748b; font-size:0.95rem; margin-bottom:1.2rem;">Scheduled medicine alarm is ringing now</p>
        <div style="background:#fef2f2; border:2px dashed #f87171; border-radius:18px; padding:1.2rem; margin-bottom:1.5rem;">
            <div id="globalAlarmMedName" style="font-size:1.35rem; font-weight:800; color:#1e293b; margin-bottom:0.3rem;">Medicine Name</div>
            <div id="globalAlarmTiming" style="font-size:0.95rem; color:#b91c1c; font-weight:600;">🍽️ After Food</div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.8rem;">
            <button type="button" onclick="acknowledgeGlobalAlarmTake()" style="background:#16a34a; color:#fff; border:none; padding:0.9rem; border-radius:14px; font-weight:800; font-size:1rem; cursor:pointer; box-shadow:0 4px 12px rgba(22,163,74,0.3);">
                ✓ Taken
            </button>
            <button type="button" onclick="snoozeGlobalAlarm()" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; padding:0.9rem; border-radius:14px; font-weight:700; font-size:0.95rem; cursor:pointer;">
                💤 Snooze 10m
            </button>
        </div>
    </div>
</div>

<style>
@keyframes shakeBell { 0% { transform: rotate(-15deg); } 100% { transform: rotate(15deg); } }
@keyframes pulseAlert { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); } 100% { box-shadow: 0 0 0 16px rgba(239, 68, 68, 0); } }
</style>
<?php endif; ?>

<script>
(function() {
    const globalSchedule = <?= json_encode($global_schedule) ?>;
    const isRemindersPage = <?= json_encode($is_reminders_page) ?>;
    const globalTriggered = new Set();
    let globalAudioCtx = null;
    let globalChimeTimer = null;
    let globalActiveMedId = 0;

    // Web Audio Synthesizer Tone
    function playGlobalBeep(freq, duration, type = 'triangle') {
        try {
            if (!globalAudioCtx) {
                const AC = window.AudioContext || window.webkitAudioContext;
                globalAudioCtx = new AC();
            }
            if (globalAudioCtx.state === 'suspended') {
                globalAudioCtx.resume();
            }
            const osc = globalAudioCtx.createOscillator();
            const gain = globalAudioCtx.createGain();
            osc.type = type;
            osc.frequency.setValueAtTime(freq, globalAudioCtx.currentTime);
            gain.gain.setValueAtTime(0.45, globalAudioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, globalAudioCtx.currentTime + duration);
            osc.connect(gain);
            gain.connect(globalAudioCtx.destination);
            osc.start();
            osc.stop(globalAudioCtx.currentTime + duration);
        } catch (e) {}
    }

    // Unmute & unlock Web Audio on first gesture
    ['click', 'touchstart', 'keydown'].forEach(evt => {
        document.addEventListener(evt, function unlockGlobalAudio() {
            try {
                if (!globalAudioCtx) {
                    const AC = window.AudioContext || window.webkitAudioContext;
                    globalAudioCtx = new AC();
                }
                if (globalAudioCtx && globalAudioCtx.state === 'suspended') {
                    globalAudioCtx.resume();
                }
            } catch (e) {}
        }, { once: true });
    });

    function startGlobalAlarmChime() {
        try {
            if (!globalAudioCtx) {
                const AC = window.AudioContext || window.webkitAudioContext;
                globalAudioCtx = new AC();
            }
            if (globalAudioCtx.state === 'suspended') globalAudioCtx.resume();

            playGlobalBeep(784.00, 0.22, 'triangle'); // G5
            setTimeout(() => playGlobalBeep(1046.50, 0.38, 'sine'), 160); // C6

            if (globalChimeTimer) clearInterval(globalChimeTimer);
            globalChimeTimer = setInterval(() => {
                playGlobalBeep(784.00, 0.22, 'triangle');
                setTimeout(() => playGlobalBeep(1046.50, 0.38, 'sine'), 160);
            }, 800);

            if (navigator.vibrate) {
                navigator.vibrate([400, 200, 400, 200, 600]);
            }
        } catch (e) {}
    }

    function stopGlobalAlarmChime() {
        if (globalChimeTimer) {
            clearInterval(globalChimeTimer);
            globalChimeTimer = null;
        }
    }

    window.triggerGlobalAlarm = function(name, dosage, timing, medId) {
        if (isRemindersPage && typeof window.triggerSpecificAlarm === 'function') {
            window.triggerSpecificAlarm(name, dosage, timing, medId);
            return;
        }

        globalActiveMedId = medId;
        startGlobalAlarmChime();

        const nameEl = document.getElementById('globalAlarmMedName');
        const timingEl = document.getElementById('globalAlarmTiming');
        const modal = document.getElementById('globalAlarmModal');

        if (nameEl) nameEl.textContent = `${name} (${dosage})`;
        if (timingEl) timingEl.textContent = `🍽️ ${timing}`;
        if (modal) modal.style.display = 'flex';

        // Log alarm sounding
        if (medId > 0) {
            fetch('api_reminders.php?action=log_alarm', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `med_id=${medId}&med_name=${encodeURIComponent(name)}&time=${encodeURIComponent(new Date().toLocaleTimeString())}`
            }).catch(console.error);
        }

        // Trigger browser push notification if permitted
        if ("Notification" in window && Notification.permission === "granted") {
            try {
                new Notification(`⏰ Medicine Time: ${name}`, {
                    body: `Take ${dosage}, ${timing}. Stay on schedule!`,
                    icon: 'images/icon-192.png'
                });
            } catch (e) {}
        }
    };

    window.acknowledgeGlobalAlarmTake = function() {
        stopGlobalAlarmChime();
        const modal = document.getElementById('globalAlarmModal');
        if (modal) modal.style.display = 'none';

        if (globalActiveMedId > 0) {
            fetch('action_reminder.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=mark_taken&med_id=${globalActiveMedId}`
            }).then(() => {
                alert("✅ Great job! Dose logged as taken.");
                window.location.reload();
            }).catch(() => window.location.reload());
        }
    };

    window.snoozeGlobalAlarm = function() {
        stopGlobalAlarmChime();
        const modal = document.getElementById('globalAlarmModal');
        if (modal) modal.style.display = 'none';
        setTimeout(() => {
            if (globalActiveMedId) {
                startGlobalAlarmChime();
                if (modal) modal.style.display = 'flex';
            }
        }, 10 * 60 * 1000);
    };

    // 1-Second Real-Time Clock Checker
    function checkGlobalAlarmClock() {
        const now = new Date();
        const hh = String(now.getHours()).padStart(2, '0');
        const mm = String(now.getMinutes()).padStart(2, '0');
        const currentTime = `${hh}:${mm}`;
        const todayDate = now.toISOString().slice(0, 10);
        const nowMins = now.getHours() * 60 + now.getMinutes();

        if (Array.isArray(globalSchedule)) {
            globalSchedule.forEach(med => {
                if (med.times && Array.isArray(med.times)) {
                    med.times.forEach(schedTime => {
                        const [sH, sM] = schedTime.split(':').map(Number);
                        const schedMins = (sH * 60) + sM;
                        const diff = nowMins - schedMins;

                        // Ring at exact minute OR if due within past 15 mins
                        if (schedTime === currentTime || (diff >= 0 && diff <= 15)) {
                            const key = `alarm_${med.id}_${schedTime}_${todayDate}`;
                            if (!globalTriggered.has(key)) {
                                globalTriggered.add(key);
                                triggerGlobalAlarm(med.name, med.dosage, med.timing, med.id);
                            }
                        }
                    });
                }
            });
        }
    }

    if (!isRemindersPage) {
        setInterval(checkGlobalAlarmClock, 1000);
        checkGlobalAlarmClock();
    }
})();
</script>
