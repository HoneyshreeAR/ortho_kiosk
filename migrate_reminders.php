<?php
// migrate_reminders.php - Automatically update database schema for Medicine Reminders & Alarms
require_once __DIR__ . '/config.php';

echo "<h2>OrthoKiosk - Database Migration for Reminders & Alarms</h2><pre>\n";

try {
    // 1. Add columns to medicine_reminders if they don't exist
    $columnsToAdd = [
        'reminder_times' => "VARCHAR(255) DEFAULT '08:00,13:00,20:00' COMMENT 'Comma-separated times or JSON of dosage times'",
        'send_sms' => "TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = send SMS to phone, 0 = no'",
        'send_whatsapp' => "TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = send WhatsApp to phone, 0 = no'",
        'alarm_enabled' => "TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = ring phone/device alarm, 0 = no'",
    ];

    foreach ($columnsToAdd as $col => $definition) {
        $check = $pdo->query("SHOW COLUMNS FROM medicine_reminders LIKE '$col'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE medicine_reminders ADD COLUMN $col $definition");
            echo "✔ Added column '$col' to 'medicine_reminders'.\n";
        } else {
            echo "ℹ Column '$col' already exists in 'medicine_reminders'.\n";
        }
    }

    // 2. Create medicine_notifications_log table if not exists
    $sqlLog = "CREATE TABLE IF NOT EXISTS medicine_notifications_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        medicine_id INT NOT NULL,
        alert_type ENUM('sms', 'whatsapp', 'alarm', 'push') NOT NULL DEFAULT 'sms',
        phone VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('sent', 'delivered', 'failed', 'triggered') NOT NULL DEFAULT 'sent',
        scheduled_time TIME NULL,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_patient_med (patient_id, medicine_id),
        INDEX idx_sent_at (sent_at),
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (medicine_id) REFERENCES medicine_reminders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sqlLog);
    echo "✔ Checked / created 'medicine_notifications_log' table.\n";

    echo "\n✔ Migration completed successfully!</pre>";
} catch (Exception $e) {
    echo "❌ Migration failed: " . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>
