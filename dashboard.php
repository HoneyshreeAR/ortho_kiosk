<?php
// dashboard.php - Main Patient Self-Service Dashboard
require_once 'config.php';

if (!isset($_SESSION['patient_id'])) {
    header("Location: index.php");
    exit;
}

$patient = getPatientById($pdo, $_SESSION['patient_id']);
$assessments = getPainAssessments($pdo, $patient['id']);
$medicines = getMedicineReminders($pdo, $patient['id']);
$followups = getFollowupReminders($pdo, $patient['id']);

$today = date('Y-m-d');
$pending_meds = 0;
foreach ($medicines as $m) {
    if ($m['last_taken_date'] !== $today) {
        $pending_meds++;
    }
}

// Check next upcoming follow-up
$next_followup = null;
foreach ($followups as $f) {
    if ($f['appointment_date'] >= $today && $f['status'] === 'scheduled') {
        $next_followup = $f;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars(t('welcome')) ?> | OrthoKiosk</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .banner-alerts {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
            margin-bottom: 2rem;
        }
        .banner-pill {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.4rem;
            border-radius: 18px;
            font-size: 0.95rem;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            text-decoration: none;
            transition: transform 0.2s;
        }
        .banner-pill:hover {
            transform: translateY(-2px);
        }
        .banner-pill.meds {
            background: #fef3c7;
            border: 1px solid #fde68a;
            color: #92400e;
        }
        .banner-pill.followup {
            background: #e0f2fe;
            border: 1px solid #bae6fd;
            color: #0369a1;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.2rem;
            margin-top: 1rem;
        }
        .kiosk-tile {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            padding: 1.6rem 1.4rem;
            text-decoration: none;
            color: #1e3a5f;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            transition: all 0.25s cubic-bezier(0.165, 0.84, 0.44, 1);
            box-shadow: 0 4px 14px rgba(0,0,0,0.03);
            position: relative;
            overflow: hidden;
        }
        .kiosk-tile:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 25px rgba(26, 62, 92, 0.12);
            border-color: #cbd5e1;
        }
        .tile-icon {
            font-size: 2.2rem;
            margin-bottom: 0.8rem;
            background: #f8fafc;
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .tile-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
            color: #1a3e5c;
        }
        .tile-sub {
            font-size: 0.88rem;
            color: #64748b;
            line-height: 1.4;
        }
        .tile-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #ef4444;
            color: white;
            font-size: 0.75rem;
            font-weight: 800;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
        }
    </style>
</head>
<body>
<div class="kiosk-container">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <div class="main-content">
        <div class="page-title-banner">
            <div>
                <h2><?= htmlspecialchars(t('welcome_patient')) ?> <?= htmlspecialchars($patient['name']) ?></h2>
                <p>Standalone Orthopaedic Patient Self-Service Terminal</p>
            </div>
            <a href="pain_report.php" class="btn btn-primary" style="display: inline-block; width: auto; padding: 0.8rem 1.6rem;">
                ➕ <?= htmlspecialchars(t('nav_report_pain')) ?>
            </a>
        </div>

        <!-- Interactive Notification Alerts -->
        <div class="banner-alerts">
            <?php if ($pending_meds > 0): ?>
            <a href="reminders.php" class="banner-pill meds">
                <span>💊 <strong>Medication Reminder:</strong> You have <?= $pending_meds ?> medicine(s) scheduled for today.</span>
                <span><?= htmlspecialchars(t('mark_taken')) ?> →</span>
            </a>
            <?php endif; ?>

            <?php if ($next_followup): 
                $daysLeft = round((strtotime($next_followup['appointment_date']) - strtotime($today)) / 86400);
            ?>
            <a href="reminders.php" class="banner-pill followup">
                <span>🗓️ <strong>Upcoming Follow-Up:</strong> <?= htmlspecialchars($next_followup['doctor_name']) ?> (<?= htmlspecialchars($next_followup['purpose']) ?>) on <?= date('d M Y', strtotime($next_followup['appointment_date'])) ?></span>
                <span><?= ($daysLeft == 0) ? 'Due Today!' : "In {$daysLeft} days →" ?></span>
            </a>
            <?php endif; ?>
        </div>

        <!-- Comprehensive Kiosk Action Tiles -->
        <div class="card">
            <h3 class="card-title"><?= htmlspecialchars(t('quick_actions')) ?></h3>
            <div class="dashboard-grid">
                <!-- 1. Joint Explorer -->
                <a href="body_explorer.php" class="kiosk-tile" style="border-top: 4px solid #2563eb;">
                    <div class="tile-icon">🦴</div>
                    <div class="tile-title"><?= htmlspecialchars(t('nav_body_explorer')) ?></div>
                    <div class="tile-sub">Interactive body skeleton, joint range of motion, and anatomy care.</div>
                </a>

                <!-- 2. Report Pain -->
                <a href="pain_report.php" class="kiosk-tile" style="border-top: 4px solid #dc2626;">
                    <div class="tile-icon">📝</div>
                    <div class="tile-title"><?= htmlspecialchars(t('nav_report_pain')) ?></div>
                    <div class="tile-sub">Symptom intake, pain scale, and specialist doctor assignment.</div>
                </a>

                <!-- 3. Food & Nutrition -->
                <a href="food_suggestions.php" class="kiosk-tile" style="border-top: 4px solid #10b981;">
                    <div class="tile-icon">🥗</div>
                    <div class="tile-title"><?= htmlspecialchars(t('nav_food')) ?></div>
                    <div class="tile-sub">Condition-tailored orthopaedic diets, superfoods, and recovery plans.</div>
                </a>

                <!-- 4. Meds & Follow-ups -->
                <a href="reminders.php" class="kiosk-tile" style="border-top: 4px solid #f59e0b;">
                    <?php if ($pending_meds > 0): ?>
                        <span class="tile-badge"><?= $pending_meds ?> PENDING</span>
                    <?php endif; ?>
                    <div class="tile-icon">💊</div>
                    <div class="tile-title"><?= htmlspecialchars(t('nav_reminders')) ?></div>
                    <div class="tile-sub">Prescription timetable, adherence tracker, and review bookings.</div>
                </a>

                <!-- 5. Emergency First-Aid -->
                <a href="emergency_guidance.php" class="kiosk-tile" style="border-top: 4px solid #b91c1c;">
                    <div class="tile-icon">🚨</div>
                    <div class="tile-title"><?= htmlspecialchars(t('nav_emergency')) ?></div>
                    <div class="tile-sub">Immediate R.I.C.E. guide, fracture splinting, and 24/7 SOS hotlines.</div>
                </a>

                <!-- 6. Exercise Videos -->
                <a href="exercise_library.php" class="kiosk-tile" style="border-top: 4px solid #06b6d4;">
                    <div class="tile-icon">🏋️</div>
                    <div class="tile-title"><?= htmlspecialchars(t('nav_exercises')) ?></div>
                    <div class="tile-sub">Physiotherapy videos customized to your reported injury areas.</div>
                </a>

                <!-- 7. Progress Tracking -->
                <a href="progress_tracking.php" class="kiosk-tile" style="border-top: 4px solid #8b5cf6;">
                    <div class="tile-icon">📈</div>
                    <div class="tile-title"><?= htmlspecialchars(t('nav_progress')) ?></div>
                    <div class="tile-sub">Visual charts showing pain reduction and mobility gains over time.</div>
                </a>

                <!-- 8. Billing & Receipts -->
                <a href="billing.php" class="kiosk-tile" style="border-top: 4px solid #14b8a6;">
                    <div class="tile-icon">💰</div>
                    <div class="tile-title"><?= htmlspecialchars(t('nav_billing')) ?></div>
                    <div class="tile-sub">Review consultation invoices, pay digitally, and print receipts.</div>
                </a>

                <!-- 9. Documents -->
                <a href="documents.php" class="kiosk-tile" style="border-top: 4px solid #64748b;">
                    <div class="tile-icon">📄</div>
                    <div class="tile-title"><?= htmlspecialchars(t('nav_documents')) ?></div>
                    <div class="tile-sub">Upload and store digital X-rays, MRI scans, and lab reports.</div>
                </a>
            </div>
        </div>

        <!-- Recent Pain Assessments -->
        <div class="card">
            <h3 class="card-title"><?= htmlspecialchars(t('recent_assessments')) ?></h3>
            <?php if (count($assessments) > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th><?= htmlspecialchars(t('date')) ?></th>
                            <th><?= htmlspecialchars(t('body_part')) ?></th>
                            <th><?= htmlspecialchars(t('pain_intensity')) ?></th>
                            <th><?= htmlspecialchars(t('classification')) ?></th>
                            <th><?= htmlspecialchars(t('details')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach(array_slice($assessments, 0, 5) as $ass): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($ass['assessment_date'])) ?></td>
                        <td><strong><?= htmlspecialchars($ass['body_part']) ?></strong></td>
                        <td>
                            <span style="background: <?= $ass['pain_intensity'] >= 7 ? '#fee2e2; color:#b91c1c;' : ($ass['pain_intensity'] >= 4 ? '#fef3c7; color:#b45309;' : '#dcfce7; color:#15803d;') ?> padding: 0.2rem 0.6rem; border-radius: 12px; font-weight: 700;">
                                <?= htmlspecialchars($ass['pain_intensity']) ?>/10
                            </span>
                        </td>
                        <td><span class="diet-badge" style="background: #e0f2fe; color: #0369a1;"><?= htmlspecialchars(ucfirst($ass['classification'])) ?></span></td>
                        <td><a href="assessment_report.php?id=<?= $ass['id'] ?>" class="btn btn-secondary" style="padding: 0.4rem 0.9rem; font-size: 0.85rem;"><?= htmlspecialchars(t('view_report')) ?></a></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p><?= htmlspecialchars(t('no_reports')) ?> <a href="pain_report.php"><?= htmlspecialchars(t('report_first')) ?></a></p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>