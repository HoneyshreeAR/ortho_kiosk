```php
<?php
/**
 * Ortho Kiosk - Application Configuration
 * PHP + MySQL deployment configuration
 */

date_default_timezone_set('Asia/Kolkata');

/* ===================== SESSION ===================== */

if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        session_start();
    } else {
        error_log(
            'Cannot start session: output was sent before config.php.'
        );
    }
}

/* ===================== DATABASE CONFIGURATION ===================== */

// Configure these in Render -> Environment.
// Local development defaults are retained.

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'ortho_kiosk';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$port = getenv('DB_PORT') ?: '3306';

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $dbname
    );

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log(
        'Ortho Kiosk database connection failed: '
        . $e->getMessage()
    );

    if (!headers_sent()) {
        http_response_code(500);
    }

    exit(
        'Database connection failed. '
        . 'Please check the server configuration.'
    );
}

/* ===================== LANGUAGE SYSTEM ===================== */

require_once __DIR__ . '/includes/lang.php';

/* ===================== LOCAL CONFIGURATION ===================== */

// Optional local-only overrides.
// Do not commit secret credentials to GitHub.

$localConfig = __DIR__ . '/config.local.php';

if (is_file($localConfig)) {
    require_once $localConfig;
}

/* ===================== CASHFREE CONFIGURATION ===================== */

if (!defined('CASHFREE_APP_ID')) {
    define(
        'CASHFREE_APP_ID',
        getenv('CASHFREE_APP_ID') ?: ''
    );
}

if (!defined('CASHFREE_SECRET_KEY')) {
    define(
        'CASHFREE_SECRET_KEY',
        getenv('CASHFREE_SECRET_KEY') ?: ''
    );
}

if (!defined('CASHFREE_ENV')) {
    define(
        'CASHFREE_ENV',
        strtoupper(getenv('CASHFREE_ENV') ?: 'SANDBOX')
    );
}

define('CASHFREE_API_VERSION', '2023-08-01');

if (CASHFREE_ENV === 'PRODUCTION') {
    define(
        'CASHFREE_BASE_URL',
        'https://api.cashfree.com/pg'
    );
} else {
    define(
        'CASHFREE_BASE_URL',
        'https://sandbox.cashfree.com/pg'
    );
}

define(
    'CASHFREE_JS_SDK',
    'https://sdk.cashfree.com/js/v3/cashfree.js'
);

define(
    'CASHFREE_MODE',
    CASHFREE_ENV === 'PRODUCTION'
        ? 'production'
        : 'sandbox'
);

/* ===================== SMS CONFIGURATION ===================== */

if (!defined('REMINDER_SMS_PROVIDER')) {
    define(
        'REMINDER_SMS_PROVIDER',
        strtoupper(
            getenv('REMINDER_SMS_PROVIDER') ?: 'SIMULATION'
        )
    );
}

if (!defined('FAST2SMS_API_KEY')) {
    define(
        'FAST2SMS_API_KEY',
        getenv('FAST2SMS_API_KEY') ?: ''
    );
}

if (!defined('TWILIO_ACCOUNT_SID')) {
    define(
        'TWILIO_ACCOUNT_SID',
        getenv('TWILIO_ACCOUNT_SID') ?: ''
    );
}

if (!defined('TWILIO_AUTH_TOKEN')) {
    define(
        'TWILIO_AUTH_TOKEN',
        getenv('TWILIO_AUTH_TOKEN') ?: ''
    );
}

if (!defined('TWILIO_PHONE_NUMBER')) {
    define(
        'TWILIO_PHONE_NUMBER',
        getenv('TWILIO_PHONE_NUMBER') ?: ''
    );
}

/* ===================== CLINIC SETTINGS ===================== */

function getClinicSetting($pdo, $key, $default = '')
{
    try {
        $stmt = $pdo->prepare(
            'SELECT setting_value
             FROM clinic_settings
             WHERE setting_key = ?
             LIMIT 1'
        );

        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return (
            $value !== false &&
            $value !== null &&
            $value !== ''
        ) ? $value : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function setClinicSetting($pdo, $key, $value)
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO clinic_settings
                (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value)'
        );

        return $stmt->execute([$key, $value]);
    } catch (Throwable $e) {
        error_log(
            'Clinic setting update failed: ' . $e->getMessage()
        );

        return false;
    }
}

/* ===================== REMINDER DATABASE SCHEMA ===================== */

function ensureReminderSchema($pdo)
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS clinic_settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value TEXT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // Check the reminder table before altering it.
        $tableCheck = $pdo->query(
            "SHOW TABLES LIKE 'medicine_reminders'"
        )->fetchColumn();

        if ($tableCheck) {
            $columns = [
                'reminder_times' => "
                    ALTER TABLE medicine_reminders
                    ADD COLUMN reminder_times
                    VARCHAR(255) DEFAULT '08:00,13:00,20:00'
                ",
                'send_sms' => "
                    ALTER TABLE medicine_reminders
                    ADD COLUMN send_sms
                    TINYINT(1) NOT NULL DEFAULT 1
                ",
                'alarm_enabled' => "
                    ALTER TABLE medicine_reminders
                    ADD COLUMN alarm_enabled
                    TINYINT(1) NOT NULL DEFAULT 1
                ",
            ];

            foreach ($columns as $column => $sql) {
                $stmt = $pdo->prepare(
                    "SHOW COLUMNS FROM medicine_reminders LIKE ?"
                );

                $stmt->execute([$column]);

                if (!$stmt->fetch()) {
                    $pdo->exec($sql);
                }
            }
        }

        // Create the notification log only when the required
        // parent tables are available.
        $patientsExists = $pdo->query(
            "SHOW TABLES LIKE 'patients'"
        )->fetchColumn();

        $remindersExists = $pdo->query(
            "SHOW TABLES LIKE 'medicine_reminders'"
        )->fetchColumn();

        if ($patientsExists && $remindersExists) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS
                medicine_notifications_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    patient_id INT NOT NULL,
                    medicine_id INT NOT NULL,
                    alert_type ENUM('sms','alarm','push')
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
                    INDEX idx_patient_med
                        (patient_id, medicine_id),
                    INDEX idx_sent_at (sent_at),
                    FOREIGN KEY (patient_id)
                        REFERENCES patients(id) ON DELETE CASCADE,
                    FOREIGN KEY (medicine_id)
                        REFERENCES medicine_reminders(id)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        }
    } catch (Throwable $e) {
        error_log(
            'Reminder schema setup failed: ' . $e->getMessage()
        );
    }
}

ensureReminderSchema($pdo);

/* ===================== LOG MEDICINE NOTIFICATIONS ===================== */

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
            'INSERT INTO medicine_notifications_log
                (
                    patient_id,
                    medicine_id,
                    alert_type,
                    phone,
                    message,
                    status,
                    scheduled_time
                )
             VALUES (?, ?, ?, ?, ?, ?, ?)'
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
    } catch (Throwable $e) {
        error_log(
            'Medicine notification logging failed: '
            . $e->getMessage()
        );

        return false;
    }
}

/* ===================== SEND PATIENT SMS ===================== */

function sendPatientSMS(
    $phone,
    $message,
    $pdo = null,
    $patient_id = null,
    $med_id = null,
    $scheduled_time = null
) {
    $cleanPhone = preg_replace('/\D+/', '', (string) $phone);

    $tenDigit = strlen($cleanPhone) >= 10
        ? substr($cleanPhone, -10)
        : $cleanPhone;

    $provider = $pdo
        ? getClinicSetting(
            $pdo,
            'sms_provider',
            REMINDER_SMS_PROVIDER
        )
        : REMINDER_SMS_PROVIDER;

    $provider = strtoupper($provider);

    $fast2smsKey = $pdo
        ? getClinicSetting(
            $pdo,
            'fast2sms_api_key',
            FAST2SMS_API_KEY
        )
        : FAST2SMS_API_KEY;

    $twilioSid = $pdo
        ? getClinicSetting(
            $pdo,
            'twilio_account_sid',
            TWILIO_ACCOUNT_SID
        )
        : TWILIO_ACCOUNT_SID;

    $twilioToken = $pdo
        ? getClinicSetting(
            $pdo,
            'twilio_auth_token',
            TWILIO_AUTH_TOKEN
        )
        : TWILIO_AUTH_TOKEN;

    $twilioPhone = $pdo
        ? getClinicSetting(
            $pdo,
            'twilio_phone_number',
            TWILIO_PHONE_NUMBER
        )
        : TWILIO_PHONE_NUMBER;

    $status = 'sent';
    $feedback = '';

    if ($provider === 'FAST2SMS' && $fast2smsKey !== '') {
        $fields = [
            'route' => 'q',
            'message' => $message,
            'language' => 'english',
            'flash' => 0,
            'numbers' => $tenDigit,
        ];

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://www.fast2sms.com/dev/bulkV2',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($fields),
            CURLOPT_HTTPHEADER => [
                'authorization: ' . trim($fast2smsKey),
                'accept: application/json',
                'content-type: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($error) {
            $status = 'failed';
            $feedback = 'Fast2SMS connection failed.';
            error_log('Fast2SMS cURL error: ' . $error);
        } else {
            $json = json_decode($response, true);

            if (
                $httpCode >= 200 &&
                $httpCode < 300 &&
                isset($json['return']) &&
                $json['return'] === true
            ) {
                $status = 'sent';
                $feedback = 'Fast2SMS accepted the request.';
            } else {
                $status = 'failed';
                $feedback = 'Fast2SMS rejected the request.';
                error_log('Fast2SMS returned HTTP ' . $httpCode);
            }
        }
    } elseif (
        $provider === 'TWILIO' &&
        $twilioSid !== '' &&
        $twilioToken !== '' &&
        $twilioPhone !== ''
    ) {
        $url = 'https://api.twilio.com/2010-04-01/Accounts/'
            . rawurlencode($twilioSid)
            . '/Messages.json';

        $toPhone = preg_match('/^\+/', (string) $phone)
            ? $phone
            : '+91' . $tenDigit;

        $data = [
            'From' => $twilioPhone,
            'To' => $toPhone,
            'Body' => $message,
        ];

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $twilioSid . ':' . $twilioToken,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($error) {
            $status = 'failed';
            $feedback = 'Twilio connection failed.';
            error_log('Twilio cURL error: ' . $error);
        } else {
            $json = json_decode($response, true);

            if (
                $httpCode >= 200 &&
                $httpCode < 300 &&
                !empty($json['sid'])
            ) {
                $status = 'sent';
                $feedback = 'Twilio accepted the request.';
            } else {
                $status = 'failed';
                $feedback = 'Twilio rejected the request.';
                error_log('Twilio returned HTTP ' . $httpCode);
            }
        }
    } else {
        // Simulation does not send a real SMS.
        $status = 'sent';
        $feedback =
            'Simulation mode: no real SMS was sent.';
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
        'success' => in_array(
            $status,
            ['sent', 'delivered'],
            true
        ),
        'provider' => $provider,
        'phone' => $phone,
        'status' => $status,
        'feedback' => $feedback,
    ];
}

/* ===================== RECENT MEDICINE ALERTS ===================== */

function getRecentMedicineAlerts($pdo, $patient_id, $limit = 10)
{
    try {
        $limit = max(1, min(100, (int) $limit));

        $stmt = $pdo->prepare(
            'SELECT l.*, m.medicine_name, m.dosage
             FROM medicine_notifications_log l
             LEFT JOIN medicine_reminders m
                ON l.medicine_id = m.id
             WHERE l.patient_id = ?
             ORDER BY l.sent_at DESC
             LIMIT ?'
        );

        $stmt->bindValue(
            1,
            (int) $patient_id,
            PDO::PARAM_INT
        );

        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/* ===================== PATIENT HELPERS ===================== */

function getPatientById($pdo, $patient_id)
{
    $stmt = $pdo->prepare(
        'SELECT * FROM patients WHERE id = ?'
    );

    $stmt->execute([$patient_id]);

    return $stmt->fetch();
}

function getPainAssessments($pdo, $patient_id)
{
    $stmt = $pdo->prepare(
        'SELECT * FROM pain_assessments
         WHERE patient_id = ?
         ORDER BY assessment_date DESC'
    );

    $stmt->execute([$patient_id]);

    return $stmt->fetchAll();
}

/* ===================== PAIN CLASSIFICATION ===================== */

function classifyPain(
    $pain_quality,
    $duration_weeks,
    $activity_aggravation,
    $pain_type_other = null
) {
    if (
        $pain_quality === 'burning' ||
        $pain_quality === 'shooting'
    ) {
        return 'neuropathic';
    }

    if ((int) $duration_weeks > 12) {
        return 'chronic';
    }

    if ($activity_aggravation === 'yes') {
        return 'mechanical';
    }

    return 'musculoskeletal';
}

/* ===================== SPECIALIST RECOMMENDATION ===================== */

function getSpecialist($pdo, $classification)
{
    $stmt = $pdo->prepare(
        'SELECT * FROM physiotherapists
         WHERE LOWER(specialization) LIKE LOWER(?)
         LIMIT 1'
    );

    $stmt->execute(['%' . $classification . '%']);
    $specialist = $stmt->fetch();

    if (!$specialist) {
        $stmt = $pdo->query(
            'SELECT * FROM physiotherapists LIMIT 1'
        );

        $specialist = $stmt->fetch();
    }

    if (!$specialist) {
        return [
            'name' => 'General Orthopaedic Specialist',
            'specialization' => 'orthopaedics',
            'room_number' => 'Main Clinic - Room 101',
            'availability' => 'Monday to Friday, 9 AM - 5 PM',
            'navigation_instructions' =>
                'Please proceed to the main reception desk.',
        ];
    }

    return $specialist;
}

/* ===================== EXERCISE RECOMMENDATIONS ===================== */

function getExercises($classification, $body_part)
{
    $exercises = [
        'musculoskeletal' => [
            'Stretching exercises for the affected area',
            'Strengthening with resistance bands',
            'Range-of-motion movements',
        ],
        'neuropathic' => [
            'Nerve gliding exercises',
            'Gentle desensitization techniques',
            'Posture correction drills',
        ],
        'mechanical' => [
            'Core stabilization exercises',
            'Joint mobility activities',
            'Ergonomic adjustments',
        ],
        'chronic' => [
            'Graded motor imagery',
            'Low-impact aerobic conditioning',
            'Pain neuroscience education',
        ],
    ];

    return $exercises[$classification]
        ?? $exercises['musculoskeletal'];
}

/* ===================== MEDICINE REMINDERS ===================== */

function getMedicineReminders($pdo, $patient_id)
{
    $stmt = $pdo->prepare(
        'SELECT * FROM medicine_reminders
         WHERE patient_id = ?
         ORDER BY id ASC'
    );

    $stmt->execute([$patient_id]);

    return $stmt->fetchAll();
}

/* ===================== FOLLOW-UP REMINDERS ===================== */

function getFollowupReminders($pdo, $patient_id)
{
    $stmt = $pdo->prepare(
        'SELECT * FROM followup_reminders
         WHERE patient_id = ?
         ORDER BY appointment_date ASC, appointment_time ASC'
    );

    $stmt->execute([$patient_id]);

    return $stmt->fetchAll();
}

/* ===================== LATEST ASSESSMENT ===================== */

function getLatestAssessment($pdo, $patient_id)
{
    $stmt = $pdo->prepare(
        'SELECT * FROM pain_assessments
         WHERE patient_id = ?
         ORDER BY assessment_date DESC
         LIMIT 1'
    );

    $stmt->execute([$patient_id]);

    return $stmt->fetch();
}
```
