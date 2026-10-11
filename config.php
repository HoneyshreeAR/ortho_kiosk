
<?php
// config.php - Database configuration and helper functions

date_default_timezone_set('Asia/Kolkata');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===================== DATABASE CONFIGURATION =====================
// Render environment variables are used in deployment.
// Local development defaults are retained.

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'ortho_kiosk';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$port = getenv('DB_PORT') ?: '3306';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Check the server configuration.');
}

// ===================== LANGUAGE SYSTEM =====================
require_once __DIR__ . '/includes/lang.php';

// ===================== CASHFREE PAYMENT CONFIG =====================
// Configure credentials through Render environment variables.
// Keep SANDBOX enabled until payment testing is complete.

if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

if (!defined('CASHFREE_APP_ID')) {
    define('CASHFREE_APP_ID', getenv('CASHFREE_APP_ID') ?: '');
}

if (!defined('CASHFREE_SECRET_KEY')) {
    define('CASHFREE_SECRET_KEY', getenv('CASHFREE_SECRET_KEY') ?: '');
}

if (!defined('CASHFREE_ENV')) {
    define('CASHFREE_ENV', getenv('CASHFREE_ENV') ?: 'SANDBOX');
}

define('CASHFREE_API_VERSION', '2023-08-01');

if (CASHFREE_ENV === 'PRODUCTION') {
    define('CASHFREE_BASE_URL', 'https://api.cashfree.com/pg');
} else {
    define('CASHFREE_BASE_URL', 'https://sandbox.cashfree.com/pg');
}

define('CASHFREE_JS_SDK', 'https://sdk.cashfree.com/js/v3/cashfree.js');
define(
    'CASHFREE_MODE',
    CASHFREE_ENV === 'PRODUCTION' ? 'production' : 'sandbox'
);

// =================== END CASHFREE PAYMENT CONFIG ===================

// ===================== SMS NOTIFICATION CONFIG =====================
// Simulation mode is safe for initial deployment.
// Real SMS requires valid provider credentials.

if (!defined('REMINDER_SMS_PROVIDER')) {
    define(
        'REMINDER_SMS_PROVIDER',
        getenv('REMINDER_SMS_PROVIDER') ?: 'SIMULATION'
    );
}

if (!defined('FAST2SMS_API_KEY')) {
    define('FAST2SMS_API_KEY', getenv('FAST2SMS_API_KEY') ?: '');
}

if (!defined('TWILIO_ACCOUNT_SID')) {
    define('TWILIO_ACCOUNT_SID', getenv('TWILIO_ACCOUNT_SID') ?: '');
}

if (!defined('TWILIO_AUTH_TOKEN')) {
    define('TWILIO_AUTH_TOKEN', getenv('TWILIO_AUTH_TOKEN') ?: '');
}

if (!defined('TWILIO_PHONE_NUMBER')) {
    define('TWILIO_PHONE_NUMBER', getenv('TWILIO_PHONE_NUMBER') ?: '');
}

// =================== END NOTIFICATION CONFIG ===================


// ===================== CLINIC SETTINGS HELPERS =====================

function getClinicSetting($pdo, $key, $default = '') {
    try {
        $stmt = $pdo->prepare(
            "SELECT setting_value
             FROM clinic_settings
             WHERE setting_key = ?"
        );
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();

        return ($val !== false && $val !== null && $val !== '')
            ? $val
            : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function setClinicSetting($pdo, $key, $value) {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO clinic_settings
                (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = ?"
        );

        return $stmt->execute([$key, $value, $value]);
    } catch (Exception $e) {
        error_log('Failed to update clinic setting: ' . $e->getMessage());
        return false;
    }
}


// ===================== REMINDER DATABASE SCHEMA =====================

function ensureReminderSchema($pdo) {
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    try {
        // 1. Clinic settings table
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS clinic_settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value TEXT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // 2. Add reminder columns when they do not exist
        $checkCol = $pdo->query(
            "SHOW COLUMNS FROM medicine_reminders
             LIKE 'reminder_times'"
        )->fetch();

        if (!$checkCol) {
            $pdo->exec(
                "ALTER TABLE medicine_reminders
                    ADD COLUMN reminder_times
                        VARCHAR(255) DEFAULT '08:00,13:00,20:00',
                    ADD COLUMN send_sms
                        TINYINT(1) NOT NULL DEFAULT 1,
                    ADD COLUMN alarm_enabled
                        TINYINT(1) NOT NULL DEFAULT 1"
            );
        }

        // 3. Medicine notification log
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS medicine_notifications_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                patient_id INT NOT NULL,
                medicine_id INT NOT NULL,
                alert_type ENUM('sms', 'alarm', 'push')
                    NOT NULL DEFAULT 'sms',
                phone VARCHAR(20) NOT NULL,
                message TEXT NOT NULL,
                status ENUM(
                    'sent',
                    'delivered',
                    'failed',
                    'triggered'
                ) NOT NULL DEFAULT 'sent',
                scheduled_time TIME NULL,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_patient_med (patient_id, medicine_id),
                INDEX idx_sent_at (sent_at),
                FOREIGN KEY (patient_id)
                    REFERENCES patients(id) ON DELETE CASCADE,
                FOREIGN KEY (medicine_id)
                    REFERENCES medicine_reminders(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } catch (Exception $e) {
        // Tables may not exist until the initial database import.
        error_log('Reminder schema setup: ' . $e->getMessage());
    }
}

ensureReminderSchema($pdo);


// ===================== LOG MEDICINE NOTIFICATIONS =====================

function logMedicineAlert(
    $pdo,
    $patient_id,
    $med_id,
    $alert_type,
    $phone,
    $message,
    $status = 'sent',
    $scheduled_time = null
) {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO medicine_notifications_log
                (
                    patient_id,
                    medicine_id,
                    alert_type,
                    phone,
                    message,
                    status,
                    scheduled_time
                )
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $patient_id,
            $med_id,
            $alert_type,
            $phone,
            $message,
            $status,
            $scheduled_time
        ]);

        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log('Failed to log alert: ' . $e->getMessage());
        return false;
    }
}


// ===================== SEND PATIENT SMS =====================

function sendPatientSMS(
    $phone,
    $message,
    $pdo = null,
    $patient_id = null,
    $med_id = null,
    $scheduled_time = null
) {
    // Normalize the phone number for Indian recipients.
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    $tenDigit = strlen($cleanPhone) >= 10
        ? substr($cleanPhone, -10)
        : $cleanPhone;

    // Read provider settings from the database first.
    $provider = $pdo
        ? getClinicSetting($pdo, 'sms_provider', REMINDER_SMS_PROVIDER)
        : REMINDER_SMS_PROVIDER;

    $fast2smsKey = $pdo
        ? getClinicSetting($pdo, 'fast2sms_api_key', FAST2SMS_API_KEY)
        : FAST2SMS_API_KEY;

    $twilioSid = $pdo
        ? getClinicSetting($pdo, 'twilio_account_sid', TWILIO_ACCOUNT_SID)
        : TWILIO_ACCOUNT_SID;

    $twilioToken = $pdo
        ? getClinicSetting($pdo, 'twilio_auth_token', TWILIO_AUTH_TOKEN)
        : TWILIO_AUTH_TOKEN;

    $twilioPhone = $pdo
        ? getClinicSetting($pdo, 'twilio_phone_number', TWILIO_PHONE_NUMBER)
        : TWILIO_PHONE_NUMBER;

    $status = 'sent';
    $gatewayFeedback = '';

    // Fast2SMS
    if ($provider === 'FAST2SMS' && !empty($fast2smsKey)) {
        $fields = [
            'route' => 'q',
            'message' => $message,
            'language' => 'english',
            'flash' => 0,
            'numbers' => $tenDigit
        ];

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://www.fast2sms.com/dev/bulkV2',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($fields),
            CURLOPT_HTTPHEADER => [
                'authorization: ' . trim($fast2smsKey),
                'accept: */*',
                'cache-control: no-cache',
                'content-type: application/json'
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($err) {
            $status = 'failed';
            $gatewayFeedback = 'Network cURL error: ' . $err;
        } else {
            $respJson = json_decode($response, true);

            if (
                $httpCode >= 200 &&
                $httpCode < 300 &&
                isset($respJson['return']) &&
                $respJson['return'] === true
            ) {
                $status = 'delivered';
                $gatewayFeedback = 'SMS accepted by Fast2SMS.';
            } else {
                $status = 'failed';

                $gatewayFeedback = isset($respJson['message'])
                    ? (
                        is_array($respJson['message'])
                            ? implode(', ', $respJson['message'])
                            : $respJson['message']
                    )
                    : 'Fast2SMS returned an error.';
            }
        }
    }

    // Twilio
    elseif (
        $provider === 'TWILIO' &&
        !empty($twilioSid) &&
        !empty($twilioToken) &&
        !empty($twilioPhone)
    ) {
        $url = 'https://api.twilio.com/2010-04-01/Accounts/'
            . rawurlencode($twilioSid)
            . '/Messages.json';

        $data = [
            'From' => $twilioPhone,
            'To' => '+91' . $tenDigit,
            'Body' => $message
        ];

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $twilioSid . ':' . $twilioToken,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            $status = 'failed';
            $gatewayFeedback = 'Twilio connection error: ' . $err;
        } else {
            $respJson = json_decode($res, true);

            if (
                $httpCode >= 200 &&
                $httpCode < 300 &&
                !empty($respJson['sid'])
            ) {
                // The provider accepted the message; delivery is not guaranteed yet.
                $status = 'sent';
                $gatewayFeedback = 'Twilio accepted the SMS request.';
            } else {
                $status = 'failed';
                $gatewayFeedback = 'Twilio rejected the SMS request.';
            }
        }
    }

    // Simulation mode
    else {
        $status = 'sent';
        $gatewayFeedback =
            'Simulation mode: no real SMS was sent. '
            . 'Configure a valid SMS provider to send messages.';
    }

    if ($pdo && $patient_id && $med_id) {
        logMedicineAlert(
            $pdo,
            $patient_id,
            $med_id,
            'sms',
            $phone,
            $message,
            $status,
            $scheduled_time
        );
    }

    return [
        'success' => ($status === 'sent' || $status === 'delivered'),
        'provider' => $provider,
        'phone' => $phone,
        'status' => $status,
        'feedback' => $gatewayFeedback
    ];
}


// ===================== RECENT MEDICINE ALERTS =====================

function getRecentMedicineAlerts($pdo, $patient_id, $limit = 10) {
    try {
        $stmt = $pdo->prepare(
            "SELECT l.*, m.medicine_name, m.dosage
             FROM medicine_notifications_log l
             LEFT JOIN medicine_reminders m
                ON l.medicine_id = m.id
             WHERE l.patient_id = ?
             ORDER BY l.sent_at DESC
             LIMIT ?"
        );

        $stmt->bindValue(1, $patient_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}


// ===================== PATIENT HELPERS =====================

function getPatientById($pdo, $patient_id) {
    $stmt = $pdo->prepare(
        'SELECT * FROM patients WHERE id = ?'
    );

    $stmt->execute([$patient_id]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getPainAssessments($pdo, $patient_id) {
    $stmt = $pdo->prepare(
        'SELECT * FROM pain_assessments
         WHERE patient_id = ?
         ORDER BY assessment_date DESC'
    );

    $stmt->execute([$patient_id]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ===================== PAIN CLASSIFICATION =====================

function classifyPain(
    $pain_quality,
    $duration_weeks,
    $activity_aggravation,
    $pain_type_other = null
) {
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


// ===================== SPECIALIST RECOMMENDATION =====================

function getSpecialist($pdo, $classification) {
    $stmt = $pdo->prepare(
        'SELECT * FROM physiotherapists
         WHERE LOWER(specialization) LIKE LOWER(?)
         LIMIT 1'
    );

    $stmt->execute(['%' . $classification . '%']);
    $specialist = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$specialist) {
        $stmt = $pdo->query(
            'SELECT * FROM physiotherapists LIMIT 1'
        );

        $specialist = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$specialist) {
        return [
            'name' => 'General Orthopaedic Specialist',
            'specialization' => 'orthopaedics',
            'room_number' => 'Main Clinic - Room 101',
            'availability' => 'Monday to Friday, 9 AM - 5 PM',
            'navigation_instructions' =>
                'Please proceed to the main reception desk for assistance.'
        ];
    }

    return $specialist;
}


// ===================== EXERCISE RECOMMENDATIONS =====================

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

    return $exercises[$classification]
        ?? $exercises['musculoskeletal'];
}


// ===================== MEDICINE REMINDERS =====================

function getMedicineReminders($pdo, $patient_id) {
    $stmt = $pdo->prepare(
        'SELECT * FROM medicine_reminders
         WHERE patient_id = ?
         ORDER BY id ASC'
    );

    $stmt->execute([$patient_id]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ===================== FOLLOW-UP REMINDERS =====================

function getFollowupReminders($pdo, $patient_id) {
    $stmt = $pdo->prepare(
        'SELECT * FROM followup_reminders
         WHERE patient_id = ?
         ORDER BY appointment_date ASC, appointment_time ASC'
    );

    $stmt->execute([$patient_id]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ===================== LATEST ASSESSMENT =====================

function getLatestAssessment($pdo, $patient_id) {
    $stmt = $pdo->prepare(
        'SELECT * FROM pain_assessments
         WHERE patient_id = ?
         ORDER BY assessment_date DESC
         LIMIT 1'
    );

    $stmt->execute([$patient_id]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ===================== END CONFIG.PHP =====================
?>
