<?php
// admin_sms_settings.php - Configure Fast2SMS / Real Cellular SMS Gateway
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}
require_once 'config.php';

$msg = '';
$msgType = '';
$testResult = null;

// Handle Save Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $provider = trim($_POST['sms_provider'] ?? 'FAST2SMS');
    $fast2smsKey = trim($_POST['fast2sms_api_key'] ?? '');
    $twilioSid = trim($_POST['twilio_account_sid'] ?? '');
    $twilioToken = trim($_POST['twilio_auth_token'] ?? '');
    $twilioPhone = trim($_POST['twilio_phone_number'] ?? '');

    // Auto-activate Fast2SMS if a key is provided
    if (!empty($fast2smsKey) && ($provider === 'SIMULATION' || isset($_POST['activate_fast2sms']))) {
        $provider = 'FAST2SMS';
    }

    setClinicSetting($pdo, 'sms_provider', $provider);
    setClinicSetting($pdo, 'fast2sms_api_key', $fast2smsKey);
    setClinicSetting($pdo, 'twilio_account_sid', $twilioSid);
    setClinicSetting($pdo, 'twilio_auth_token', $twilioToken);
    setClinicSetting($pdo, 'twilio_phone_number', $twilioPhone);

    if ($provider === 'FAST2SMS' && !empty($fast2smsKey)) {
        $msg = "✔ Fast2SMS API Key saved & activated successfully! Real cellular SMS will now be delivered to patients' phones.";
    } elseif ($provider === 'SIMULATION') {
        $msg = "✔ Gateway set to Simulation Mode (Offline testing).";
    } else {
        $msg = "✔ SMS Gateway settings saved successfully!";
    }
    $msgType = 'success';
}

// Handle Live Test SMS Dispatch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_real_sms') {
    $testPhone = trim($_POST['test_phone'] ?? '');
    $customMsg = trim($_POST['test_message'] ?? 'OrthoKiosk Test: Your medicine reminder service is active!');

    if (!empty($testPhone)) {
        $testResult = sendPatientSMS($testPhone, $customMsg, $pdo);
        if ($testResult['success'] && $testResult['status'] === 'delivered') {
            $msg = "🚀 Real SMS sent to {$testPhone}! Check your phone's SMS app.";
            $msgType = 'success';
        } elseif ($testResult['provider'] === 'SIMULATION') {
            $msg = "ℹ️ Sent in SIMULATION mode. To send real SMS to mobile, switch provider to Fast2SMS and enter your API Key.";
            $msgType = 'info';
        } else {
            $msg = "❌ SMS Failed: " . ($testResult['feedback'] ?? 'Gateway returned an error.');
            $msgType = 'error';
        }
    } else {
        $msg = "Please enter a valid 10-digit mobile number.";
        $msgType = 'error';
    }
}

// Load current settings
$currentProvider = getClinicSetting($pdo, 'sms_provider', REMINDER_SMS_PROVIDER);
$currentFastKey = getClinicSetting($pdo, 'fast2sms_api_key', FAST2SMS_API_KEY);
$currentTwilioSid = getClinicSetting($pdo, 'twilio_account_sid', TWILIO_ACCOUNT_SID);
$currentTwilioToken = getClinicSetting($pdo, 'twilio_auth_token', TWILIO_AUTH_TOKEN);
$currentTwilioPhone = getClinicSetting($pdo, 'twilio_phone_number', TWILIO_PHONE_NUMBER);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Gateway Settings | OrthoKiosk Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .settings-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .guide-box {
            background: #eff6ff;
            border: 2px solid #bfdbfe;
            border-radius: 18px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            color: #1e3a8a;
        }
        .guide-box h3 {
            margin-bottom: 0.5rem;
            color: #1e40af;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .step-list {
            padding-left: 1.5rem;
            margin: 0.8rem 0;
            line-height: 1.6;
        }
        .form-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            margin-bottom: 2rem;
        }
        .form-group {
            margin-bottom: 1.3rem;
        }
        .form-group label {
            display: block;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.4rem;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.8rem 1rem;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .btn-save {
            background: #1a3e5c;
            color: white;
            border: none;
            padding: 0.9rem 1.8rem;
            border-radius: 30px;
            font-weight: 700;
            cursor: pointer;
            font-size: 1rem;
            transition: 0.2s;
        }
        .btn-save:hover {
            background: #0f2b40;
        }
        .btn-live-test {
            background: #10b981;
            color: white;
            border: none;
            padding: 0.9rem 1.8rem;
            border-radius: 30px;
            font-weight: 700;
            cursor: pointer;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-live-test:hover {
            background: #059669;
        }
        .alert-msg {
            padding: 1rem 1.4rem;
            border-radius: 14px;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        .alert-msg.success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-msg.info { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .alert-msg.error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .badge-status {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.85rem;
        }
        .badge-status.active { background: #dcfce7; color: #15803d; }
        .badge-status.sim { background: #fef3c7; color: #b45309; }
    </style>
</head>
<body>
<div class="kiosk-container">
    <div class="header">
        <div class="logo">
            <h1>📲 Real Phone SMS Gateway Setup</h1>
            <p>Connect Fast2SMS to deliver real cellular SMS to patients' phone numbers</p>
        </div>
        <div class="user-info" style="display: flex; gap: 1rem; align-items: center;">
            <a href="admin_reminders.php" style="color:white; text-decoration:none;">⏰ Alarms & Reminders</a>
            <a href="admin_dashboard.php" style="color:white; text-decoration:none;">📊 Dashboard</a>
            <a href="admin_logout.php" style="color:white; text-decoration:none;">🚪 Logout</a>
        </div>
    </div>

    <div class="settings-container">
        <?php if (!empty($msg)): ?>
            <div class="alert-msg <?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <!-- How It Works Guide -->
        <div class="guide-box">
            <h3>💡 3 Quick Steps to Send Real Cellular SMS to Any Phone:</h3>
            <p style="margin-bottom: 0.8rem;">
                Current Status: 
                <strong class="badge-status <?= $currentProvider === 'FAST2SMS' ? 'active' : 'sim' ?>">
                    <?= $currentProvider === 'FAST2SMS' ? '🟢 Fast2SMS Active (Real Cellular SMS Delivery)' : ($currentProvider === 'TWILIO' ? '🟢 Twilio Active' : '🟡 Simulation Mode (Offline Testing Only)') ?>
                </strong>
            </p>
            <ol class="step-list">
                <li>
                    <strong>Step 1:</strong> Visit <a href="https://www.fast2sms.com" target="_blank" style="color: #2563eb; font-weight: 700; text-decoration: underline;">Fast2SMS.com</a> and sign up free with your mobile number. (You get free trial SMS wallet credits immediately!).
                </li>
                <li>
                    <strong>Step 2:</strong> In Fast2SMS left sidebar, click <strong>Dev API</strong> (or open <a href="https://www.fast2sms.com/dashboard/dev-api" target="_blank" style="color: #2563eb; font-weight: 700; text-decoration: underline;">fast2sms.com/dashboard/dev-api</a>) and copy your <strong>API Authorization Key</strong>.
                </li>
                <li>
                    <strong>Step 3:</strong> Paste the key into the input box below and click <strong>"Save & Activate Real SMS (Fast2SMS)"</strong>. That's all! Every medicine reminder will be delivered directly to the patient's phone SMS inbox.
                </li>
            </ol>
            <div style="margin-top: 1rem;">
                <a href="https://www.fast2sms.com/dashboard/dev-api" target="_blank" style="display:inline-flex; align-items:center; gap:0.5rem; background:#2563eb; color:white; padding:0.6rem 1.2rem; border-radius:10px; font-weight:700; text-decoration:none;">
                    🔗 Open Fast2SMS Dev API Page & Copy Key
                </a>
            </div>
        </div>

        <!-- Settings Form -->
        <div class="form-card">
            <h2 style="color:#1a3e5c; margin-bottom:1.2rem;">⚙️ SMS Gateway Configuration</h2>
            <form action="admin_sms_settings.php" method="POST">
                <input type="hidden" name="action" value="save_settings">
                
                <div class="form-group" style="background: #f0fdf4; border: 2px solid #86efac; border-radius: 16px; padding: 1.2rem; margin-bottom: 1.5rem;">
                    <label style="color:#15803d; font-size:1.05rem; display:flex; align-items:center; gap:0.4rem;">
                        🟢 <strong>Fast2SMS API Authorization Key (For India Real SMS)</strong>
                    </label>
                    <p style="color:#475569; font-size:0.88rem; margin:0.3rem 0 0.8rem 0;">
                        Paste your API Key from <a href="https://www.fast2sms.com/dashboard/dev-api" target="_blank" style="color:#2563eb; font-weight:600;">Fast2SMS Dev API</a> here:
                    </p>
                    <input type="text" name="fast2sms_api_key" id="fast2smsInput" value="<?= htmlspecialchars($currentFastKey) ?>" placeholder="Paste your Authorization Key here (e.g. abcdef1234567890...)" style="font-family: monospace; font-size:1.05rem; padding:0.85rem; border:2px solid #22c55e; border-radius:10px; width:100%; box-sizing:border-box;" oninput="onApiKeyInput(this.value)">
                    <small style="color:#166534; font-weight:600; margin-top:0.5rem; display:block;">
                        ✔ Fast2SMS 'q' (Quick) route delivers 24/7 to all Indian operators (Jio, Airtel, Vi, BSNL) directly to their native SMS app.
                    </small>
                </div>

                <div class="form-group">
                    <label>Active SMS Gateway Provider</label>
                    <select name="sms_provider" id="smsProviderSelect" onchange="toggleProviderFields(this.value)">
                        <option value="FAST2SMS" <?= $currentProvider === 'FAST2SMS' ? 'selected' : '' ?>>
                            🟢 Fast2SMS (Delivers Real SMS to Indian Phones)
                        </option>
                        <option value="SIMULATION" <?= $currentProvider === 'SIMULATION' ? 'selected' : '' ?>>
                            🟡 Simulation Mode (Safe testing - logs to database, no real cellular SMS sent)
                        </option>
                        <option value="TWILIO" <?= $currentProvider === 'TWILIO' ? 'selected' : '' ?>>
                            🌐 Twilio (International / Global SMS)
                        </option>
                    </select>
                </div>

                <!-- Twilio Configuration -->
                <div id="twilioFields" style="<?= $currentProvider === 'TWILIO' ? '' : 'display:none;' ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Twilio Account SID</label>
                            <input type="text" name="twilio_account_sid" value="<?= htmlspecialchars($currentTwilioSid) ?>">
                        </div>
                        <div class="form-group">
                            <label>Twilio Auth Token</label>
                            <input type="password" name="twilio_auth_token" value="<?= htmlspecialchars($currentTwilioToken) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Twilio Phone Number</label>
                        <input type="text" name="twilio_phone_number" value="<?= htmlspecialchars($currentTwilioPhone) ?>" placeholder="e.g. +1234567890">
                    </div>
                </div>

                <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-top:1.5rem;">
                    <button type="submit" name="activate_fast2sms" value="1" class="btn-save" style="background:#10b981; font-size:1.05rem; display:inline-flex; align-items:center; gap:0.5rem;">
                        💾 Save & Activate Real SMS (Fast2SMS)
                    </button>
                    <button type="submit" class="btn-save" style="background:#64748b;">
                        💾 Save Current Provider
                    </button>
                </div>
            </form>
        </div>

        <!-- Live Real SMS Tester -->
        <div class="form-card" style="border-top: 6px solid #10b981;">
            <h2 style="color:#10b981; margin-bottom:0.5rem;">🧪 Live Real SMS Tester</h2>
            <p style="color:#64748b; font-size:0.95rem; margin-bottom:1.5rem;">
                Test sending a real SMS right now to your own personal phone number to confirm delivery to your phone's SMS app.
            </p>

            <form action="admin_sms_settings.php" method="POST">
                <input type="hidden" name="action" value="test_real_sms">

                <div class="form-group">
                    <label>Recipient Mobile Number (10 Digits)</label>
                    <input type="text" name="test_phone" placeholder="e.g. 9876543210" required maxlength="12" style="font-size:1.1rem; font-weight:700;">
                </div>

                <div class="form-group">
                    <label>Test Message Text</label>
                    <textarea name="test_message" rows="3" style="resize:none;">⏰ OrthoKiosk Medicine Alert: Hello! It is time to take your prescribed dose of Calcium 500mg, After Food. Stay healthy!</textarea>
                </div>

                <button type="submit" class="btn-live-test">
                    🚀 Send Live Test SMS to My Phone Now
                </button>
            </form>

            <?php if ($testResult): ?>
                <div style="margin-top: 1.5rem; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 12px; padding: 1rem;">
                    <strong>Gateway Output Details:</strong>
                    <pre style="margin-top: 0.5rem; font-size: 0.85rem; color: #334155; white-space: pre-wrap;"><?= htmlspecialchars(print_r($testResult, true)) ?></pre>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function onApiKeyInput(val) {
    if (val && val.trim().length > 5) {
        document.getElementById('smsProviderSelect').value = 'FAST2SMS';
    }
}
function toggleProviderFields(provider) {
    document.getElementById('twilioFields').style.display = (provider === 'TWILIO') ? 'block' : 'none';
}
</script>
</body>
</html>
