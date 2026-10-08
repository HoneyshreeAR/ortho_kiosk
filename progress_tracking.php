<?php
// progress_tracking.php - Pain Trends & Mobility Improvements, Follow-up Monitoring
require_once 'config.php';

if (!isset($_SESSION['patient_id'])) {
    header("Location: index.php");
    exit;
}

$patient_id = $_SESSION['patient_id'];
$patient = getPatientById($pdo, $patient_id);
$assessments = getPainAssessments($pdo, $patient_id);
$dates = []; $intensities = []; $mobilities = [];
foreach($assessments as $a) {
    $dates[] = date('d M', strtotime($a['assessment_date']));
    $intensities[] = (int)$a['pain_intensity'];
    $mobilities[] = (int)$a['mobility_score'];
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars(t('progress_title')) ?> | OrthoKiosk</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="kiosk-container">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <div class="main-content">
        <div class="page-title-banner">
            <div>
                <h2>📈 <?= htmlspecialchars(t('progress_title')) ?></h2>
                <p>Objective recovery analytics tracking pain reduction, functional mobility, and adherence.</p>
            </div>
            <a href="pain_report.php" class="btn btn-primary" style="display: inline-block; width: auto; padding: 0.8rem 1.6rem;">
                ➕ <?= htmlspecialchars(t('nav_report_pain')) ?>
            </a>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <div class="card" style="margin-bottom: 0;">
                <h3 class="card-title" style="border-left-color: #dc2626;">🔥 <?= htmlspecialchars(t('pain_trend')) ?></h3>
                <div style="position: relative; height: 260px;">
                    <canvas id="painChart"></canvas>
                </div>
            </div>

            <div class="card" style="margin-bottom: 0;">
                <h3 class="card-title" style="border-left-color: #2563eb;">🚶 <?= htmlspecialchars(t('mobility_trend')) ?></h3>
                <div style="position: relative; height: 260px;">
                    <canvas id="mobilityChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card">
            <h3 class="card-title" style="border-left-color: #10b981;">📋 Clinical Rehabilitation Summary & Adherence</h3>
            <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; align-items: center; justify-content: space-between;">
                <div>
                    <p style="color: #334155; margin-bottom: 0.5rem; font-size: 1rem;">
                        ✅ <strong>Exercise Adherence Rate:</strong> 85% across prescribed home physiotherapy sessions.
                    </p>
                    <p style="color: #64748b; margin: 0; font-size: 0.95rem;">
                        📅 <strong>Clinical Recommendation:</strong> Maintain prescribed range of motion exercises daily. Scheduled for periodic progress check in 2 weeks.
                    </p>
                </div>
                <div style="display: flex; gap: 0.8rem;">
                    <a href="reminders.php" class="btn btn-secondary" style="display: inline-block; width: auto; padding: 0.7rem 1.4rem;">
                        💊 <?= htmlspecialchars(t('nav_reminders')) ?>
                    </a>
                    <a href="food_suggestions.php" class="btn btn-secondary" style="display: inline-block; width: auto; padding: 0.7rem 1.4rem;">
                        🥗 <?= htmlspecialchars(t('nav_food')) ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    const dateLabels = <?= json_encode(!empty($dates) ? $dates : ['Today']) ?>;
    const painVals = <?= json_encode(!empty($intensities) ? $intensities : [0]) ?>;
    const mobVals = <?= json_encode(!empty($mobilities) ? $mobilities : [10]) ?>;

    new Chart(document.getElementById('painChart'), { 
        type: 'line', 
        data: { 
            labels: dateLabels, 
            datasets: [{ 
                label: 'Pain Intensity (0-10)', 
                data: painVals, 
                borderColor: '#dc2626', 
                backgroundColor: 'rgba(220, 38, 38, 0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 6,
                pointHoverRadius: 8
            }] 
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { min: 0, max: 10 } }
        }
    });

    new Chart(document.getElementById('mobilityChart'), { 
        type: 'line', 
        data: { 
            labels: dateLabels, 
            datasets: [{ 
                label: 'Mobility Score (0-10)', 
                data: mobVals, 
                borderColor: '#2563eb', 
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 6,
                pointHoverRadius: 8
            }] 
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { min: 0, max: 10 } }
        }
    });
</script>
</body>
</html>