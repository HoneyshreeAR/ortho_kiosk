<?php
// test_real_sms.php - Diagnostic Tool to Test Real Phone SMS Delivery
require_once __DIR__ . '/config.php';

$isCli = (php_sapi_name() === 'cli');

// Determine recipient phone and test message
$testPhone = '';
$testMsg = "⏰ [OrthoKiosk Test] Hello! This is a real medicine reminder alert from OrthoKiosk sent at " . date('H:i:s') . ". Take your scheduled dose on time!";

if ($isCli) {
    global $argv;
    $testPhone = $argv[1] ?? '9876543210';
    if (!empty($argv[2])) {
        $testMsg = $argv[2];
    }
} else {
    $testPhone = $_GET['phone'] ?? $_POST['phone'] ?? '';
    if (!empty($_GET['message'])) {
        $testMsg = $_GET['message'];
    }
}

// Current provider and API key
$provider = getClinicSetting($pdo, 'sms_provider', REMINDER_SMS_PROVIDER);
$fast2smsKey = getClinicSetting($pdo, 'fast2sms_api_key', FAST2SMS_API_KEY);
$twilioSid = getClinicSetting($pdo, 'twilio_account_sid', TWILIO_ACCOUNT_SID);

$dispatchResult = null;
if (!empty($testPhone)) {
    $dispatchResult = sendPatientSMS($testPhone, $testMsg, $pdo);
}

if ($isCli) {
    echo "========================================================\n";
    echo "           ORTHOKIOSK REAL SMS DIAGNOSTIC TOOL          \n";
    echo "========================================================\n";
    echo "Active Provider : " . $provider . "\n";
    echo "Fast2SMS Key    : " . (!empty($fast2smsKey) ? (substr($fast2smsKey, 0, 6) . '...' . substr($fast2smsKey, -4)) : '[EMPTY - In Simulation Mode]') . "\n";
    echo "Twilio SID      : " . (!empty($twilioSid) ? $twilioSid : '[EMPTY]') . "\n";
    echo "Target Phone    : " . $testPhone . "\n";
    echo "Test Message    : " . $testMsg . "\n";
    echo "--------------------------------------------------------\n";
    if ($dispatchResult) {
        echo "Success Status  : " . ($dispatchResult['success'] ? 'YES' : 'NO') . "\n";
        echo "Delivery State  : " . $dispatchResult['status'] . "\n";
        echo "Gateway Feedback: " . $dispatchResult['feedback'] . "\n";
    }
    echo "========================================================\n";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Real SMS Dispatcher & Tester | OrthoKiosk</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .test-card {
            max-width: 650px;
            margin: 2.5rem auto;
            background: white;
            padding: 2.2rem;
            border-radius: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
        }
        .gateway-badge {
            display: inline-flex;
            padding: 0.35rem 0.9rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 700;
        }
        .badge-real { background: #dcfce7; color: #15803d; }
        .badge-sim { background: #fef3c7; color: #b45309; }
    </style>
</head>
<body style="background: #f8fafc; font-family: system-ui, sans-serif; padding: 1.5rem;">
<div class="test-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
        <h2 style="margin:0; font-size:1.5rem; color:#1e293b;">📲 Real SMS Dispatch Tester</h2>
        <span class="gateway-badge <?= ($provider === 'FAST2SMS' && !empty($fast2smsKey)) ? 'badge-real' : 'badge-sim' ?>">
            <?= ($provider === 'FAST2SMS' && !empty($fast2smsKey)) ? '🟢 Fast2SMS Real Carrier Active' : '🟡 Simulation Mode Active' ?>
        </span>
    </div>

    <?php if ($dispatchResult): ?>
        <div style="padding: 1.2rem; border-radius: 16px; margin-bottom: 1.5rem; <?= $dispatchResult['status'] === 'delivered' ? 'background:#dcfce7; color:#15803d; border:1px solid #86efac;' : ($dispatchResult['status'] === 'sent' ? 'background:#f0f9ff; color:#0369a1; border:1px solid #bae6fd;' : 'background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5;') ?>">
            <h4 style="margin:0 0 0.4rem; font-size:1.1rem;">
                <?= $dispatchResult['status'] === 'delivered' ? '🚀 Real Cellular SMS Delivered!' : ($dispatchResult['status'] === 'sent' ? 'ℹ️ SMS Logged (Simulation Mode)' : '❌ SMS Delivery Failed') ?>
            </h4>
            <p style="margin:0; font-size:0.95rem; line-height:1.5;">
                <?= htmlspecialchars($dispatchResult['feedback']) ?>
            </p>
            <div style="margin-top:0.6rem; font-size:0.85rem; opacity:0.85;">
                Recipient: <strong><?= htmlspecialchars($dispatchResult['phone']) ?></strong> | Provider: <strong><?= htmlspecialchars($dispatchResult['provider']) ?></strong>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" action="test_real_sms.php">
        <div style="margin-bottom: 1.2rem;">
            <label style="display:block; font-weight:700; margin-bottom:0.4rem; color:#1e293b;">Enter Patient Mobile Number (10 digits):</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($testPhone ?: '9876543210') ?>" required style="width:100%; padding:0.8rem 1rem; border-radius:12px; border:1px solid #cbd5e1; font-size:1.05rem; box-sizing:border-box;">
            <small style="color:#64748b; font-size:0.82rem;">Supports all Indian carriers (Jio, Airtel, Vi, BSNL). Enter your real phone number to test.</small>
        </div>

        <div style="margin-bottom: 1.5rem;">
            <label style="display:block; font-weight:700; margin-bottom:0.4rem; color:#1e293b;">Custom Message:</label>
            <textarea name="message" rows="3" style="width:100%; padding:0.8rem 1rem; border-radius:12px; border:1px solid #cbd5e1; font-size:0.95rem; box-sizing:border-box;"><?= htmlspecialchars($testMsg) ?></textarea>
        </div>

        <button type="submit" style="background:#2563eb; color:white; border:none; padding:0.85rem 1.8rem; border-radius:12px; font-weight:700; font-size:1rem; cursor:pointer; width:100%;">
            🚀 Send SMS Now
        </button>
    </form>

    <div style="margin-top:2rem; padding-top:1.5rem; border-top:1px solid #e2e8f0; font-size:0.88rem; color:#475569; line-height:1.6;">
        <strong>How to switch between Simulation and Real Cellular SMS:</strong>
        <ul style="padding-left:1.2rem; margin:0.4rem 0;">
            <li>Go to <a href="admin_sms_settings.php" style="color:#2563eb; font-weight:600;">Admin Panel &gt; SMS Settings</a>.</li>
            <li>Get a free trial API key from <a href="https://www.fast2sms.com" target="_blank" style="color:#2563eb; font-weight:600;">Fast2SMS.com</a> (or use Twilio).</li>
            <li>Paste your key and save. Once saved, real SMS messages will be dispatched automatically!</li>
        </ul>
        <div style="margin-top:1rem;">
            <a href="reminders.php" style="color:#2563eb; text-decoration:none; font-weight:600;">← Back to Reminders</a> &nbsp;|&nbsp;
            <a href="admin_sms_settings.php" style="color:#2563eb; text-decoration:none; font-weight:600;">Admin SMS Settings →</a>
        </div>
    </div>
</div>
</body>
</html>
