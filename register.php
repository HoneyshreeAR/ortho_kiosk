<?php
// register.php - Patient Registration with Multilingual & Auto-Reminders Onboarding
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $age = (int)($_POST['age'] ?? 0);
    $gender = trim($_POST['gender'] ?? 'male');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Check if phone already exists
    $check = $pdo->prepare("SELECT id FROM patients WHERE phone = ?");
    $check->execute([$phone]);
    $existing = $check->fetch();

    if ($existing) {
        $_SESSION['patient_id'] = $existing['id'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO patients (name, age, gender, phone, email) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $age, $gender, $phone, $email]);
        $patient_id = $pdo->lastInsertId();
        $_SESSION['patient_id'] = $patient_id;

        // Auto-seed welcome starter reminders for demo experience
        $medStmt = $pdo->prepare("INSERT INTO medicine_reminders (patient_id, medicine_name, dosage, frequency, timing, duration_days, start_date, notes) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?)");
        $medStmt->execute([$patient_id, 'Calcium Carbonate + Vit D3', '500 mg (1 tab)', 'morning', 'after_food', 30, 'Bone mineral density support after breakfast']);
        $medStmt->execute([$patient_id, 'Joint Ease Glucosamine', '750 mg (1 cap)', 'night', 'after_food', 60, 'Promotes cartilage hydration']);

        $flStmt = $pdo->prepare("INSERT INTO followup_reminders (patient_id, doctor_name, department, appointment_date, appointment_time, purpose, status) VALUES (?, ?, ?, CURDATE() + INTERVAL 7 DAY, '10:30:00', 'Initial Orthopaedic Mobility & Gait Consultation', 'scheduled')");
        $flStmt->execute([$patient_id, 'Dr. Ramesh Rao', 'Orthopaedics & Joint Care']);
    }

    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars(t('register_title')) ?> | OrthoKiosk</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="kiosk-container">
    <?php include 'includes/header.php'; ?>
    <div class="main-content">
        <div class="card" style="max-width: 600px; margin: 2rem auto; padding: 2.5rem; border-radius: 28px;">
            <div style="text-align: center; margin-bottom: 1.8rem;">
                <span style="font-size: 3rem;">📋</span>
                <h2 style="color: #1a3e5c; margin-top: 0.5rem;"><?= htmlspecialchars(t('register_title')) ?></h2>
                <p style="color: #64748b; font-size: 0.95rem;"><?= htmlspecialchars(t('tagline')) ?></p>
            </div>

            <form method="POST">
                <div class="form-group" style="margin-bottom: 1.2rem;">
                    <label style="display: block; font-weight: 600; margin-bottom: 0.4rem; color: #1e3a5f;"><?= htmlspecialchars(t('full_name')) ?></label>
                    <input type="text" name="name" required style="width: 100%; padding: 0.8rem 1rem; border: 1px solid #cbd5e1; border-radius: 40px; font-size: 1rem;">
                </div>

                <div style="display: flex; gap: 1rem; margin-bottom: 1.2rem;">
                    <div style="flex: 1;">
                        <label style="display: block; font-weight: 600; margin-bottom: 0.4rem; color: #1e3a5f;"><?= htmlspecialchars(t('age')) ?></label>
                        <input type="number" name="age" min="1" max="120" required style="width: 100%; padding: 0.8rem 1rem; border: 1px solid #cbd5e1; border-radius: 40px; font-size: 1rem;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; font-weight: 600; margin-bottom: 0.4rem; color: #1e3a5f;"><?= htmlspecialchars(t('gender')) ?></label>
                        <select name="gender" style="width: 100%; padding: 0.8rem 1rem; border: 1px solid #cbd5e1; border-radius: 40px; font-size: 1rem; background: white;">
                            <option value="male"><?= htmlspecialchars(t('male')) ?></option>
                            <option value="female"><?= htmlspecialchars(t('female')) ?></option>
                            <option value="other"><?= htmlspecialchars(t('other')) ?></option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.2rem;">
                    <label style="display: block; font-weight: 600; margin-bottom: 0.4rem; color: #1e3a5f;">📞 <?= htmlspecialchars(t('phone')) ?></label>
                    <input type="tel" name="phone" placeholder="<?= htmlspecialchars(t('phone_placeholder')) ?>" required style="width: 100%; padding: 0.8rem 1rem; border: 1px solid #cbd5e1; border-radius: 40px; font-size: 1rem;">
                </div>

                <div class="form-group" style="margin-bottom: 1.8rem;">
                    <label style="display: block; font-weight: 600; margin-bottom: 0.4rem; color: #1e3a5f;">✉️ <?= htmlspecialchars(t('email_optional')) ?></label>
                    <input type="email" name="email" style="width: 100%; padding: 0.8rem 1rem; border: 1px solid #cbd5e1; border-radius: 40px; font-size: 1rem;">
                </div>

                <button type="submit" class="btn btn-primary" style="padding: 1rem; font-size: 1.1rem; width: 100%;">
                    <?= htmlspecialchars(t('register_btn')) ?> →
                </button>
            </form>

            <div style="text-align: center; margin-top: 1.5rem;">
                <a href="index.php" style="color: #64748b; text-decoration: none; font-size: 0.9rem; font-weight: 600;">
                    ← <?= htmlspecialchars(t('login_continue')) ?>
                </a>
            </div>
        </div>
    </div>
</div>
</body>
</html>