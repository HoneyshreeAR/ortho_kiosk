-- Database setup for Ortho Kiosk System
CREATE DATABASE IF NOT EXISTS ortho_kiosk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ortho_kiosk;

-- 1. Admin table
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Patients table
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    age INT NOT NULL,
    gender VARCHAR(20) NOT NULL,
    phone VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(150) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Pain Assessments table
CREATE TABLE IF NOT EXISTS pain_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    body_part VARCHAR(255) NOT NULL,
    pain_intensity INT NOT NULL DEFAULT 0,
    activities VARCHAR(255) NULL,
    pain_quality VARCHAR(50) NULL,
    duration_weeks INT DEFAULT 1,
    activity_aggravation VARCHAR(10) DEFAULT 'no',
    pain_description TEXT NULL,
    mobility_score INT DEFAULT 5,
    classification VARCHAR(100) DEFAULT 'musculoskeletal',
    assessment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Physiotherapists / Specialists table
CREATE TABLE IF NOT EXISTS physiotherapists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    specialization VARCHAR(150) NOT NULL,
    room_number VARCHAR(50) NOT NULL,
    availability VARCHAR(150) NOT NULL,
    navigation_instructions TEXT NULL,
    status VARCHAR(20) DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Bills table
CREATE TABLE IF NOT EXISTS bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    paid_status VARCHAR(20) DEFAULT 'pending',
    bill_date DATE NOT NULL,
    cf_order_id VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    bill_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    transaction_id VARCHAR(100) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'Cashfree PG',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Documents table
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    file_type VARCHAR(100) NULL,
    file_size INT DEFAULT 0,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Appointments table
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    therapist_id INT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status VARCHAR(30) DEFAULT 'scheduled',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. System Logs table
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id INT NOT NULL,
    new_value VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Medicine Reminders table
CREATE TABLE IF NOT EXISTS medicine_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    medicine_name VARCHAR(150) NOT NULL,
    dosage VARCHAR(100) NOT NULL,
    frequency VARCHAR(50) NOT NULL,
    timing VARCHAR(50) NOT NULL DEFAULT 'after_food',
    reminder_times VARCHAR(255) DEFAULT '08:00,13:00,20:00',
    send_sms TINYINT(1) NOT NULL DEFAULT 1,
    send_whatsapp TINYINT(1) NOT NULL DEFAULT 1,
    alarm_enabled TINYINT(1) NOT NULL DEFAULT 1,
    duration_days INT DEFAULT 7,
    start_date DATE NOT NULL,
    notes TEXT NULL,
    last_taken_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Follow-up Reminders table
CREATE TABLE IF NOT EXISTS followup_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_name VARCHAR(150) NOT NULL,
    department VARCHAR(100) DEFAULT 'Orthopaedics',
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL DEFAULT '10:00:00',
    purpose VARCHAR(255) NOT NULL,
    status VARCHAR(30) DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Medicine Notifications & Alarms Log
CREATE TABLE IF NOT EXISTS medicine_notifications_log (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SEED DATA
INSERT INTO admin (username, password) 
VALUES ('admin', 'admin123')
ON DUPLICATE KEY UPDATE username=username;

INSERT INTO physiotherapists (name, specialization, room_number, availability, navigation_instructions, status) VALUES
('Dr. Ramesh Rao, MS (Ortho)', 'musculoskeletal', 'Room 101 - Ground Floor', 'Mon - Sat (9:00 AM - 1:00 PM)', 'Turn right from the entrance lobby, first room on your left.', 'active'),
('Dr. Ananya Sharma, MPT', 'mechanical', 'Room 104 - Physiotherapy Wing', 'Mon - Fri (10:00 AM - 4:00 PM)', 'Proceed down the main corridor to Wing B, adjacent to the rehab gym.', 'active'),
('Dr. Vikram Patil, MD (Neuro-Ortho)', 'neuropathic', 'Room 202 - 1st Floor', 'Tue, Thu, Sat (11:00 AM - 5:00 PM)', 'Take the central elevator to 1st Floor, turn left towards Neurology & Spine.', 'active'),
('Dr. Sunita Mehta, PhD', 'chronic', 'Room 205 - Pain Clinic', 'Mon - Sat (9:00 AM - 3:00 PM)', '1st Floor, Pain Management Center, Room 205.', 'active')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO patients (id, name, age, gender, phone, email) VALUES
(1, 'Rahul Sharma', 42, 'male', '9876543210', 'rahul.sharma@example.com')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO pain_assessments (id, patient_id, body_part, pain_intensity, activities, pain_quality, duration_weeks, activity_aggravation, pain_description, mobility_score, classification, assessment_date) VALUES
(1, 1, 'Knee', 6, 'Walking, Climbing stairs', 'dull', 4, 'yes', 'Persistent dull aching in right knee joint after climbing stairs, mild swelling in the evening.', 6, 'mechanical', NOW() - INTERVAL 5 DAY),
(2, 1, 'Knee', 4, 'Climbing stairs', 'dull', 5, 'yes', 'Improving after stretching and cold packs. Mild stiffness in the morning.', 8, 'mechanical', NOW() - INTERVAL 1 DAY)
ON DUPLICATE KEY UPDATE body_part=VALUES(body_part);

INSERT INTO bills (id, patient_id, description, amount, paid_status, bill_date) VALUES
(1, 1, 'Orthopaedic Pain Assessment & Specialist Consultation', 500.00, 'paid', CURDATE() - INTERVAL 5 DAY),
(2, 1, 'Follow-up Physical Therapy Session & Joint Mobility Scan', 350.00, 'pending', CURDATE())
ON DUPLICATE KEY UPDATE description=VALUES(description);

INSERT INTO payments (id, patient_id, bill_id, amount, transaction_id, payment_date) VALUES
(1, 1, 1, 500.00, 'TXN_ORTHO_984124', NOW() - INTERVAL 5 DAY)
ON DUPLICATE KEY UPDATE transaction_id=VALUES(transaction_id);

INSERT INTO medicine_reminders (patient_id, medicine_name, dosage, frequency, timing, duration_days, start_date, notes, last_taken_date) VALUES
(1, 'Calcium Carbonate + Vitamin D3', '500 mg + 250 IU (1 tab)', 'morning', 'after_food', 30, CURDATE() - INTERVAL 5 DAY, 'Take after breakfast for bone mineral density support', CURDATE()),
(1, 'Paracetamol (Analgesic)', '650 mg (1 tab)', 'morning_night', 'after_food', 7, CURDATE() - INTERVAL 5 DAY, 'Take for joint discomfort relief as needed', CURDATE()),
(1, 'Glucosamine Sulfate + Chondroitin', '750 mg (1 capsule)', 'night', 'after_food', 60, CURDATE() - INTERVAL 5 DAY, 'Promotes cartilage repair and synovial fluid lubrication', NULL);

INSERT INTO followup_reminders (patient_id, doctor_name, department, appointment_date, appointment_time, purpose, status) VALUES
(1, 'Dr. Ramesh Rao', 'Orthopaedics & Joint Care', CURDATE() + INTERVAL 3 DAY, '10:30:00', 'Post-treatment Knee Mobility & Joint Stability Reassessment', 'scheduled'),
(1, 'Dr. Ananya Sharma', 'Physiotherapy & Rehabilitation', CURDATE() + INTERVAL 10 DAY, '11:00:00', 'Quadriceps Strengthening & Gait Correction Check', 'scheduled');
