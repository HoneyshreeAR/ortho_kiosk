<?php
// config.php - Database configuration and helper functions
date_default_timezone_set('Asia/Kolkata'); // Ensure server matches local Indian Standard Time

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$dbname = 'ortho_kiosk';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Load multilingual system
require_once __DIR__ . '/includes/lang.php';

// ===================== CASHFREE PAYMENT CONFIG =====================
// Paste your App ID and Secret Key from Cashfree dashboard below, or in config.local.php
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}
if (!defined('CASHFREE_APP_ID')) {
    define('CASHFREE_APP_ID', 'TEST_YOUR_CASHFREE_APP_ID');
}
if (!defined('CASHFREE_SECRET_KEY')) {
    define('CASHFREE_SECRET_KEY', 'TEST_YOUR_CASHFREE_SECRET_KEY');
}

// Keep this as 'SANDBOX' while testing. Change to 'PRODUCTION' only when you're
// ready to accept real money, and swap in your production App ID/Secret Key too.
define('CASHFREE_ENV', 'SANDBOX');

define('CASHFREE_API_VERSION', '2023-08-01');

if (CASHFREE_ENV === 'PRODUCTION') {
    define('CASHFREE_BASE_URL', 'https://api.cashfree.com/pg');
} else {
    define('CASHFREE_BASE_URL', 'https://sandbox.cashfree.com/pg');
}
define('CASHFREE_JS_SDK', 'https://sdk.cashfree.com/js/v3/cashfree.js');
define('CASHFREE_MODE', CASHFREE_ENV === 'PRODUCTION' ? 'production' : 'sandbox');
// =================== END CASHFREE PAYMENT CONFIG ===================

// ===================== SMS NOTIFICATION CONFIG =====================
// Default provider: 'FAST2SMS', 'TWILIO', or 'SIMULATION'
// (These can also be updated directly in the Admin Panel without editing code)
define('REMINDER_SMS_PROVIDER', 'SIMULATION');
define('FAST2SMS_API_KEY', ''); // Enter your Fast2SMS API Key from https://fast2sms.com
define('TWILIO_ACCOUNT_SID', '');
define('TWILIO_AUTH_TOKEN', '');
define('TWILIO_PHONE_NUMBER', '');
// =================== END NOTIFICATION CONFIG ===================

// Setting Helper Functions
function getClinicSetting($pdo, $key, $default = '') {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM clinic_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false && $val !== null && $val !== '') ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function setClinicSetting($pdo, $key, $value) {
    try {
        $stmt = $pdo->prepare("INSERT INTO clinic_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        return $stmt->execute([$key, $value, $value]);
    } catch (Exception $e) {
        return false;
    }
}

// Ensure reminder database columns, settings & log table exist
function ensureReminderSchema($pdo) {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        // 1. Settings Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS clinic_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // 2. Medicine Reminders Columns
        $checkCol = $pdo->query("SHOW COLUMNS FROM medicine_reminders LIKE 'reminder_times'")->fetch();
        if (!$checkCol) {
            $pdo->exec("ALTER TABLE medicine_reminders 
                ADD COLUMN reminder_times VARCHAR(255) DEFAULT '08:00,13:00,20:00',
                ADD COLUMN send_sms TINYINT(1) NOT NULL DEFAULT 1,
                ADD COLUMN alarm_enabled TINYINT(1) NOT NULL DEFAULT 1");
        }

        // 3. Medicine Notifications Log Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS medicine_notifications_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            medicine_id INT NOT NULL,
            alert_type ENUM('sms', 'alarm', 'push') NOT NULL DEFAULT 'sms',
            phone VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('sent', 'delivered', 'failed', 'triggered') NOT NULL DEFAULT 'sent',
            scheduled_time TIME NULL,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_patient_med (patient_id, medicine_id),
            INDEX idx_sent_at (sent_at),
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            FOREIGN KEY (medicine_id) REFERENCES medicine_reminders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        // Silently skip if table doesn't exist yet during initial setup
    }
}
ensureReminderSchema($pdo);

// Function to log medicine notifications
function logMedicineAlert($pdo, $patient_id, $med_id, $alert_type, $phone, $message, $status = 'sent', $scheduled_time = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO medicine_notifications_log 
            (patient_id, medicine_id, alert_type, phone, message, status, scheduled_time) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$patient_id, $med_id, $alert_type, $phone, $message, $status, $scheduled_time]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Failed to log alert: " . $e->getMessage());
        return false;
    }
}

// Function to send SMS to patient's phone (Direct Cellular SMS)
function sendPatientSMS($phone, $message, $pdo = null, $patient_id = null, $med_id = null, $scheduled_time = null) {
    // Sanitize phone number to 10 digits for Indian carriers
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    $tenDigit = (strlen($cleanPhone) >= 10) ? substr($cleanPhone, -10) : $cleanPhone;

    // Check database settings first, then fallback to constants
    $provider = $pdo ? getClinicSetting($pdo, 'sms_provider', REMINDER_SMS_PROVIDER) : REMINDER_SMS_PROVIDER;
    $fast2smsKey = $pdo ? getClinicSetting($pdo, 'fast2sms_api_key', FAST2SMS_API_KEY) : FAST2SMS_API_KEY;
    $twilioSid = $pdo ? getClinicSetting($pdo, 'twilio_account_sid', TWILIO_ACCOUNT_SID) : TWILIO_ACCOUNT_SID;
    $twilioToken = $pdo ? getClinicSetting($pdo, 'twilio_auth_token', TWILIO_AUTH_TOKEN) : TWILIO_AUTH_TOKEN;
    $twilioPhone = $pdo ? getClinicSetting($pdo, 'twilio_phone_number', TWILIO_PHONE_NUMBER) : TWILIO_PHONE_NUMBER;

    $status = 'sent';
    $gatewayFeedback = '';

    if ($provider === 'FAST2SMS' && !empty($fast2smsKey)) {
        // Fast2SMS Quick Transactional SMS Route ('q' route delivers immediately 24/7 in India)
        $fields = [
            "route" => "q",
            "message" => $message,
            "language" => "english",
            "flash" => 0,
            "numbers" => $tenDigit
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://www.fast2sms.com/dev/bulkV2",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($fields),
            CURLOPT_HTTPHEADER => [
                "authorization: " . trim($fast2smsKey),
                "accept: */*",
                "cache-control: no-cache",
                "content-type: application/json"
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            $status = 'failed';
            $gatewayFeedback = 'Network cURL error: ' . $err;
        } else {
            $respJson = json_decode($response, true);
            if ($respJson && isset($respJson['return']) && $respJson['return'] === true) {
                $status = 'delivered';
                $gatewayFeedback = 'Real SMS successfully dispatched to ' . $tenDigit . ' via Fast2SMS telecom gateway.';
            } else {
                $status = 'failed';
                $gatewayFeedback = isset($respJson['message']) ? (is_array($respJson['message']) ? implode(', ', $respJson['message']) : $respJson['message']) : 'Fast2SMS returned error';
            }
        }
    } elseif ($provider === 'TWILIO' && !empty($twilioSid) && !empty($twilioToken)) {
        $url = "https://api.twilio.com/2010-04-01/Accounts/" . $twilioSid . "/Messages.json";
        $data = [
            'From' => $twilioPhone,
            'To' => '+91' . $tenDigit,
            'Body' => $message,
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $twilioSid . ":" . $twilioToken);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $status = 'failed';
            $gatewayFeedback = 'Twilio error: ' . $err;
        } else {
            $status = 'delivered';
            $gatewayFeedback = 'Real SMS dispatched via Twilio.';
        }
    } else {
        // Simulation mode: logs message cleanly for zero-cost testing
        $status = 'sent';
        $gatewayFeedback = 'Simulation Mode (Logged to database). To send real cellular SMS to phone, enter Fast2SMS API Key in Admin Settings.';
    }

    if ($pdo && $patient_id && $med_id) {
        logMedicineAlert($pdo, $patient_id, $med_id, 'sms', $phone, $message, $status, $scheduled_time);
    }

    return [
        'success' => ($status === 'sent' || $status === 'delivered'),
        'provider' => $provider,
        'phone' => $phone,
        'status' => $status,
        'feedback' => $gatewayFeedback
    ];
}

// Function to get recent medicine alerts for a patient
function getRecentMedicineAlerts($pdo, $patient_id, $limit = 10) {
    try {
        $stmt = $pdo->prepare("SELECT l.*, m.medicine_name, m.dosage 
            FROM medicine_notifications_log l
            LEFT JOIN medicine_reminders m ON l.medicine_id = m.id
            WHERE l.patient_id = ?
            ORDER BY l.sent_at DESC
            LIMIT ?");
        $stmt->bindValue(1, $patient_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Function to get patient by ID
function getPatientById($pdo, $patient_id) {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get all pain assessments for a patient
function getPainAssessments($pdo, $patient_id) {
    $stmt = $pdo->prepare("SELECT * FROM pain_assessments WHERE patient_id = ? ORDER BY assessment_date DESC");
    $stmt->execute([$patient_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Pain classification logic
function classifyPain($pain_quality, $duration_weeks, $activity_aggravation, $pain_type_other = null) {
    if ($pain_quality == 'burning' || $pain_quality == 'shooting') {
        return 'neuropathic';
    } elseif ($duration_weeks > 12) {
        return 'chronic';
    } elseif ($activity_aggravation == 'yes') {
        return 'mechanical';
    } else {
        return 'musculoskeletal';
    }
}

// Get specialist based on classification (with fallback to default array)
function getSpecialist($pdo, $classification) {
    // Try to find a specialist matching the classification (case-insensitive)
    $stmt = $pdo->prepare("SELECT * FROM physiotherapists WHERE LOWER(specialization) LIKE LOWER(?) LIMIT 1");
    $stmt->execute(["%$classification%"]);
    $specialist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$specialist) {
        // Fallback: any physiotherapist from the table
        $stmt = $pdo->prepare("SELECT * FROM physiotherapists LIMIT 1");
        $stmt->execute();
        $specialist = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // If still no specialist (empty table), return a default array to prevent errors
    if (!$specialist) {
        return [
            'name' => 'General Orthopaedic Specialist',
            'specialization' => 'orthopaedics',
            'room_number' => 'Main Clinic - Room 101',
            'availability' => 'Monday to Friday, 9 AM - 5 PM',
            'navigation_instructions' => 'Please proceed to the main reception desk for assistance.'
        ];
    }
    
    return $specialist;
}

// Get exercise recommendations based on pain classification and body part
function getExercises($classification, $body_part) {
    $exercises = [
        'musculoskeletal' => [
            'Stretching exercises for affected area',
            'Strengthening with resistance bands',
            'Range of motion movements'
        ],
        'neuropathic' => [
            'Nerve gliding exercises',
            'Gentle desensitization techniques',
            'Posture correction drills'
        ],
        'mechanical' => [
            'Core stabilization exercises',
            'Joint mobilization activities',
            'Ergonomic adjustments'
        ],
        'chronic' => [
            'Graded motor imagery',
            'Low-impact aerobic conditioning',
            'Pain neuroscience education'
        ]
    ];
    
    // Return the appropriate array, fallback to musculoskeletal if classification not found
    return $exercises[$classification] ?? $exercises['musculoskeletal'];
}

// Function to get active medicine reminders for a patient
function getMedicineReminders($pdo, $patient_id) {
    $stmt = $pdo->prepare("SELECT * FROM medicine_reminders WHERE patient_id = ? ORDER BY id ASC");
    $stmt->execute([$patient_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get upcoming follow-up reminders for a patient
function getFollowupReminders($pdo, $patient_id) {
    $stmt = $pdo->prepare("SELECT * FROM followup_reminders WHERE patient_id = ? ORDER BY appointment_date ASC, appointment_time ASC");
    $stmt->execute([$patient_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get latest assessment for a patient
function getLatestAssessment($pdo, $patient_id) {
    $stmt = $pdo->prepare("SELECT * FROM pain_assessments WHERE patient_id = ? ORDER BY assessment_date DESC LIMIT 1");
    $stmt->execute([$patient_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>