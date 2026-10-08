<?php
// emergency_guidance.php - Emergency Protocols, First-Aid Guidance & Hotline SOS
require_once 'config.php';

$patient = null;
if (isset($_SESSION['patient_id'])) {
    $patient = getPatientById($pdo, $_SESSION['patient_id']);
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars(t('emergency_title')) ?> | OrthoKiosk</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .sos-hero-card {
            background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%);
            color: #ffffff;
            border-radius: 28px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 15px 30px rgba(185, 28, 28, 0.25);
        }
        .sos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.2rem;
            margin-top: 1.5rem;
        }
        .sos-btn-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            padding: 1.2rem;
            color: #ffffff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.2s ease;
        }
        .sos-btn-card:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-3px);
        }
        .sos-icon {
            font-size: 2.2rem;
            background: #ffffff;
            color: #b91c1c;
            width: 54px;
            height: 54px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }
        .sos-label {
            font-size: 1.1rem;
            font-weight: 800;
        }
        .sos-num {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .protocol-section {
            margin-bottom: 2.5rem;
        }
        .protocol-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.2rem;
            margin-top: 1rem;
        }
        .protocol-step {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.4rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            position: relative;
            border-top: 5px solid #2563eb;
        }
        .step-letter {
            font-size: 2rem;
            font-weight: 900;
            color: #2563eb;
            margin-bottom: 0.5rem;
        }
        .step-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #1e3a5f;
            margin-bottom: 0.5rem;
        }
        .step-desc {
            font-size: 0.92rem;
            color: #475569;
            line-height: 1.5;
        }

        /* Ice Timer Widget */
        .ice-timer-widget {
            background: #f0f9ff;
            border: 2px solid #bae6fd;
            border-radius: 22px;
            padding: 1.5rem;
            margin-top: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.2rem;
        }
        .timer-display {
            font-size: 2.5rem;
            font-weight: 800;
            color: #0284c7;
            font-family: monospace;
        }
        .timer-controls {
            display: flex;
            gap: 0.8rem;
        }
        .timer-btn {
            background: #0284c7;
            color: white;
            border: none;
            padding: 0.7rem 1.4rem;
            border-radius: 30px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }
        .timer-btn:hover {
            background: #0369a1;
        }
        .timer-btn.reset {
            background: #e2e8f0;
            color: #334155;
        }

        /* Red Flags Box */
        .red-flag-card {
            background: #fff5f5;
            border: 2px solid #fecaca;
            border-radius: 24px;
            padding: 1.8rem;
            margin-bottom: 2rem;
        }
        .red-flag-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
            margin-top: 1.2rem;
        }
        .red-flag-item {
            background: #ffffff;
            border: 1px solid #fee2e2;
            border-radius: 16px;
            padding: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.8rem;
            font-weight: 600;
            color: #991b1b;
            cursor: pointer;
        }
        .red-flag-item input {
            margin-top: 4px;
            transform: scale(1.3);
            accent-color: #dc2626;
        }

        #triageAlert {
            display: none;
            background: #dc2626;
            color: white;
            padding: 1.2rem;
            border-radius: 16px;
            margin-top: 1.2rem;
            text-align: center;
            font-weight: 800;
            font-size: 1.1rem;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
<div class="kiosk-container">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <div class="main-content">
        <!-- SOS Hero Banner -->
        <div class="sos-hero-card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h2 style="font-size: 1.8rem; margin-bottom: 0.3rem;">🚨 <?= htmlspecialchars(t('emergency_title')) ?></h2>
                    <p style="opacity: 0.9; font-size: 1rem;"><?= htmlspecialchars(t('emergency_subtitle')) ?></p>
                </div>
                <div style="background: rgba(255,255,255,0.2); padding: 0.5rem 1rem; border-radius: 20px; font-weight: 700; font-size: 0.85rem;">
                    24/7 TRAUMA & ORTHOPAEDIC SOS
                </div>
            </div>

            <div class="sos-grid">
                <a href="tel:108" class="sos-btn-card">
                    <span class="sos-icon">🚑</span>
                    <div>
                        <div class="sos-label">108</div>
                        <div class="sos-num"><?= htmlspecialchars(t('ambulance_call')) ?></div>
                    </div>
                </a>
                <a href="tel:112" class="sos-btn-card">
                    <span class="sos-icon">🚨</span>
                    <div>
                        <div class="sos-label">112</div>
                        <div class="sos-num"><?= htmlspecialchars(t('national_emergency')) ?></div>
                    </div>
                </a>
                <a href="tel:+919876543210" class="sos-btn-card">
                    <span class="sos-icon">📞</span>
                    <div>
                        <div class="sos-label">+91 98765 43210</div>
                        <div class="sos-num"><?= htmlspecialchars(t('clinic_hotline')) ?></div>
                    </div>
                </a>
            </div>

            <div style="margin-top: 1.2rem; font-size: 0.9rem; opacity: 0.95; background: rgba(0,0,0,0.2); padding: 0.8rem 1.2rem; border-radius: 14px;">
                📍 <strong><?= htmlspecialchars(t('clinic_address_label')) ?></strong>
            </div>
        </div>

        <!-- Section 1: Red Flag Symptom Triage -->
        <div class="red-flag-card">
            <h3 style="color: #991b1b; font-size: 1.3rem; margin-bottom: 0.3rem;">
                <?= htmlspecialchars(t('red_flags_title')) ?>
            </h3>
            <p style="color: #7f1d1d; font-size: 0.95rem;">
                Check any severe symptoms present right now for instant triage determination:
            </p>

            <div class="red-flag-list">
                <label class="red-flag-item">
                    <input type="checkbox" class="triage-check" onchange="checkTriage()">
                    <span><?= htmlspecialchars(t('red_flag_1')) ?></span>
                </label>
                <label class="red-flag-item">
                    <input type="checkbox" class="triage-check" onchange="checkTriage()">
                    <span><?= htmlspecialchars(t('red_flag_2')) ?></span>
                </label>
                <label class="red-flag-item">
                    <input type="checkbox" class="triage-check" onchange="checkTriage()">
                    <span><?= htmlspecialchars(t('red_flag_3')) ?></span>
                </label>
                <label class="red-flag-item">
                    <input type="checkbox" class="triage-check" onchange="checkTriage()">
                    <span><?= htmlspecialchars(t('red_flag_4')) ?></span>
                </label>
            </div>

            <div id="triageAlert">
                ⚠️ RED FLAG DETECTED! Do not walk or move the affected limb. Call 108 or proceed to the nearest Emergency Triage immediately!
            </div>
        </div>

        <!-- Section 2: R.I.C.E. Protocol -->
        <div class="card protocol-section">
            <h3 class="card-title" style="border-left-color: #0284c7;">
                <?= htmlspecialchars(t('rice_protocol')) ?>
            </h3>
            <p style="color: #64748b; margin-bottom: 1.2rem;">
                Follow this standard clinical protocol during the first 48 to 72 hours after an acute joint sprain or strain.
            </p>

            <div class="protocol-grid">
                <div class="protocol-step" style="border-top-color: #3b82f6;">
                    <div class="step-letter">R</div>
                    <div class="step-title">Rest (ವಿಶ್ರಾಂತಿ / विश्राम)</div>
                    <div class="step-desc"><?= htmlspecialchars(t('rice_r')) ?></div>
                </div>
                <div class="protocol-step" style="border-top-color: #0284c7;">
                    <div class="step-letter">I</div>
                    <div class="step-title">Ice (ಐಸ್ / बर्फ)</div>
                    <div class="step-desc"><?= htmlspecialchars(t('rice_i')) ?></div>
                </div>
                <div class="protocol-step" style="border-top-color: #0d9488;">
                    <div class="step-letter">C</div>
                    <div class="step-title">Compress (ಒತ್ತಡ / दबाव)</div>
                    <div class="step-desc"><?= htmlspecialchars(t('rice_c')) ?></div>
                </div>
                <div class="protocol-step" style="border-top-color: #6366f1;">
                    <div class="step-letter">E</div>
                    <div class="step-title">Elevate (ಎತ್ತರಿಸಿ / ऊंचाई)</div>
                    <div class="step-desc"><?= htmlspecialchars(t('rice_e')) ?></div>
                </div>
            </div>

            <!-- Built-in 15-Minute Ice Timer -->
            <div class="ice-timer-widget">
                <div>
                    <h4 style="color: #0369a1; font-size: 1.1rem; margin-bottom: 0.3rem;">⏱️ 15-Minute Clinical Ice Pack Timer</h4>
                    <p style="color: #475569; font-size: 0.88rem; margin: 0;">Apply cold pack wrapped in a thin towel. Do not exceed 20 minutes to prevent frostbite.</p>
                </div>
                <div style="display: flex; align-items: center; gap: 1.5rem;">
                    <div class="timer-display" id="iceTimerDisplay">15:00</div>
                    <div class="timer-controls">
                        <button class="timer-btn" id="startTimerBtn" onclick="toggleIceTimer()">Start</button>
                        <button class="timer-btn reset" onclick="resetIceTimer()">Reset</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Suspected Fracture Rules -->
        <div class="card protocol-section">
            <h3 class="card-title" style="border-left-color: #d97706;">
                <?= htmlspecialchars(t('fracture_care')) ?>
            </h3>
            <div class="protocol-grid">
                <div class="protocol-step" style="border-top-color: #d97706;">
                    <div class="step-letter" style="color: #d97706;">1</div>
                    <div class="step-title">Do Not Straighten Bone</div>
                    <div class="step-desc"><?= htmlspecialchars(t('fracture_rule_1')) ?></div>
                </div>
                <div class="protocol-step" style="border-top-color: #d97706;">
                    <div class="step-letter" style="color: #d97706;">2</div>
                    <div class="step-title">Emergency Immobilisation</div>
                    <div class="step-desc"><?= htmlspecialchars(t('fracture_rule_2')) ?></div>
                </div>
                <div class="protocol-step" style="border-top-color: #d97706;">
                    <div class="step-letter" style="color: #d97706;">3</div>
                    <div class="step-title">Open Fracture Bleeding Care</div>
                    <div class="step-desc"><?= htmlspecialchars(t('fracture_rule_3')) ?></div>
                </div>
            </div>
        </div>

        <!-- Section 4: Joint Dislocation Warning -->
        <div class="card protocol-section">
            <h3 class="card-title" style="border-left-color: #dc2626;">
                <?= htmlspecialchars(t('dislocation_care')) ?>
            </h3>
            <div class="protocol-grid">
                <div class="protocol-step" style="border-top-color: #dc2626;">
                    <div class="step-letter" style="color: #dc2626;">⚠️</div>
                    <div class="step-title">Never Force or "Pop" Back</div>
                    <div class="step-desc"><?= htmlspecialchars(t('dislocation_rule_1')) ?></div>
                </div>
                <div class="protocol-step" style="border-top-color: #dc2626;">
                    <div class="step-letter" style="color: #dc2626;">🛡️</div>
                    <div class="step-title">Support in Place with Sling</div>
                    <div class="step-desc"><?= htmlspecialchars(t('dislocation_rule_2')) ?></div>
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <a href="dashboard.php" class="btn btn-secondary" style="display: inline-block; width: auto; padding: 0.8rem 2rem;">
                🏠 <?= htmlspecialchars(t('back_to_dashboard')) ?>
            </a>
            <a href="pain_report.php" class="btn btn-primary" style="display: inline-block; width: auto; padding: 0.8rem 2rem; margin-left: 1rem;">
                📝 <?= htmlspecialchars(t('nav_report_pain')) ?>
            </a>
        </div>
    </div>
</div>

<script>
    // Red Flag Triage Checker
    function checkTriage() {
        const checkboxes = document.querySelectorAll('.triage-check');
        let anyChecked = false;
        checkboxes.forEach(cb => {
            if (cb.checked) anyChecked = true;
        });
        document.getElementById('triageAlert').style.display = anyChecked ? 'block' : 'none';
    }

    // Ice Timer Implementation
    let timeLeft = 15 * 60;
    let timerId = null;

    function formatTime(seconds) {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return `${m < 10 ? '0' : ''}${m}:${s < 10 ? '0' : ''}${s}`;
    }

    function toggleIceTimer() {
        const btn = document.getElementById('startTimerBtn');
        if (timerId === null) {
            btn.textContent = 'Pause';
            btn.style.background = '#f59e0b';
            timerId = setInterval(() => {
                if (timeLeft > 0) {
                    timeLeft--;
                    document.getElementById('iceTimerDisplay').textContent = formatTime(timeLeft);
                } else {
                    clearInterval(timerId);
                    timerId = null;
                    btn.textContent = 'Completed';
                    alert('⏰ 15-minute ice application finished! Remove cold pack and re-assess.');
                }
            }, 1000);
        } else {
            clearInterval(timerId);
            timerId = null;
            btn.textContent = 'Resume';
            btn.style.background = '#0284c7';
        }
    }

    function resetIceTimer() {
        if (timerId !== null) {
            clearInterval(timerId);
            timerId = null;
        }
        timeLeft = 15 * 60;
        document.getElementById('iceTimerDisplay').textContent = '15:00';
        const btn = document.getElementById('startTimerBtn');
        btn.textContent = 'Start';
        btn.style.background = '#0284c7';
    }
</script>
</body>
</html>
