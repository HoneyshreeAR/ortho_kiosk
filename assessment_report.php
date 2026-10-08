<?php
require_once 'config.php';
if (!isset($_SESSION['patient_id'])) {
    header('Location: index.php');
    exit;
}

$patient = getPatientById($pdo, $_SESSION['patient_id']);
$assessment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$assessment_id) {
    header('Location: dashboard.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM pain_assessments WHERE id = ? AND patient_id = ?');
$stmt->execute([$assessment_id, $patient['id']]);
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assessment) {
    header('Location: dashboard.php');
    exit;
}

$specialist = getSpecialist($pdo, $assessment['classification']);
$exercises = getExercises($assessment['classification'], $assessment['body_part']);

function formatYesNo($value) {
    return $value === 'yes' ? 'Yes' : ($value === 'no' ? 'No' : ucfirst($value));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Report | OrthoKiosk</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background: linear-gradient(145deg, #eaf4ff 0%, #f7f9fc 100%); font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 0; }
        .report-wrapper { max-width: 1100px; margin: 2rem auto; padding: 1rem; }
        .report-card { background: #fff; border-radius: 28px; box-shadow: 0 20px 45px rgba(14, 30, 60, 0.08); overflow: hidden; }
        .report-header { padding: 2rem 2.5rem; background: linear-gradient(135deg, #1e3a8a, #2563eb); color: #fff; }
        .report-header h1 { margin: 0 0 0.5rem; font-size: 2.4rem; }
        .report-header p { margin: 0; opacity: 0.88; }
        .report-body { display: grid; gap: 2rem; padding: 2rem 2.5rem; }
        .section { background: #f8fbff; border-radius: 24px; padding: 1.8rem; }
        .section h2 { margin-top: 0; font-size: 1.45rem; color: #102a43; }
        .detail-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .detail-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 1rem 1.2rem; }
        .detail-card strong { display: block; margin-bottom: 0.7rem; color: #334155; font-size: 0.95rem; }
        .detail-card span { color: #0f172a; font-weight: 600; }
        .text-content { line-height: 1.8; color: #334155; }
        .tag { display: inline-flex; align-items: center; gap: 0.5rem; background: #dbeafe; color: #1d4ed8; padding: 0.45rem 0.85rem; border-radius: 999px; font-weight: 700; font-size: 0.9rem; }
        .btn-group { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1.5rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 0.95rem 1.45rem; border-radius: 999px; font-weight: 700; text-decoration: none; transition: transform 0.15s ease, box-shadow 0.15s ease; }
        .btn-primary { background: #1d4ed8; color: #fff; }
        .btn-secondary { background: #0f172a; color: #fff; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 12px 24px rgba(15, 23, 42, 0.14); }
        .exercise-list { list-style: inside disc; padding-left: 1.1rem; color: #334155; }
        .exercise-list li { margin-bottom: 0.7rem; }
        .metric-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 0; border-bottom: 1px solid #e2e8f0; }
        .metric-row:last-child { border-bottom: none; }
        .metric-label { color: #64748b; font-size: 0.95rem; }
        .metric-value { color: #0f172a; font-weight: 700; }
        @media (max-width: 720px) { .report-body { padding: 1.5rem; } }
    </style>
</head>
<body>
<div class="report-wrapper">
    <div class="report-card">
        <div class="report-header">
            <h1>Assessment Report</h1>
            <p>Symptom summary and clinical recommendations for <?= htmlspecialchars($patient['name']) ?></p>
        </div>
        <div class="report-body">
            <div class="section">
                <h2>Report Summary</h2>
                <div class="detail-grid">
                    <div class="detail-card"><strong>Assessment ID</strong><span>#<?= $assessment['id'] ?></span></div>
                    <div class="detail-card"><strong>Reported On</strong><span><?= date('d M Y', strtotime($assessment['assessment_date'])) ?></span></div>
                    <div class="detail-card"><strong>Body Part(s)</strong><span><?= htmlspecialchars($assessment['body_part']) ?></span></div>
                    <div class="detail-card"><strong>Pain Intensity</strong><span><?= htmlspecialchars($assessment['pain_intensity']) ?>/10</span></div>
                </div>
            </div>
            <div class="section">
                <h2>Symptom Details</h2>
                <div class="metric-row"><span class="metric-label">Pain Quality</span><span class="metric-value"><?= htmlspecialchars(ucfirst($assessment['pain_quality'])) ?></span></div>
                <div class="metric-row"><span class="metric-label">Duration</span><span class="metric-value"><?= htmlspecialchars($assessment['duration_weeks']) ?> week<?= ($assessment['duration_weeks'] == 1 ? '' : 's') ?></span></div>
                <div class="metric-row"><span class="metric-label">Aggravated by activity</span><span class="metric-value"><?= formatYesNo($assessment['activity_aggravation']) ?></span></div>
                <div class="metric-row"><span class="metric-label">Mobility score</span><span class="metric-value"><?= htmlspecialchars($assessment['mobility_score']) ?>/10</span></div>
                <div class="metric-row"><span class="metric-label">Classification</span><span class="metric-value"><span class="tag"><?= htmlspecialchars(ucfirst($assessment['classification'])) ?></span></span></div>
                <div class="text-content" style="margin-top:1rem;"><strong>Patient description:</strong><br><?= nl2br(htmlspecialchars($assessment['pain_description'])) ?></div>
            </div>
            <div class="section">
                <h2>Recommended Specialist</h2>
                <div class="detail-grid">
                    <div class="detail-card"><strong>Name</strong><span><?= htmlspecialchars($specialist['name']) ?></span></div>
                    <div class="detail-card"><strong>Specialty</strong><span><?= htmlspecialchars($specialist['specialization']) ?></span></div>
                    <div class="detail-card"><strong>Room</strong><span><?= htmlspecialchars($specialist['room_number']) ?></span></div>
                    <div class="detail-card"><strong>Availability</strong><span><?= htmlspecialchars($specialist['availability']) ?></span></div>
                </div>
                <p class="text-content" style="margin-top:1rem;"><strong>Navigation:</strong> <?= htmlspecialchars($specialist['navigation_instructions']) ?></p>
            </div>
            <div class="section">
                <h2>Recommended Exercises</h2>
                <ul class="exercise-list">
                    <?php foreach ($exercises as $exercise): ?>
                        <li><?= htmlspecialchars($exercise) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="section" style="text-align:center;">
                <div class="btn-group" style="justify-content: center;">
                    <a class="btn btn-primary" href="dashboard.php">🏠 <?= htmlspecialchars(t('back_to_dashboard')) ?></a>
                    <a class="btn btn-secondary" href="food_suggestions.php">🥗 <?= htmlspecialchars(t('nav_food')) ?></a>
                    <a class="btn btn-secondary" href="reminders.php">💊 <?= htmlspecialchars(t('nav_reminders')) ?></a>
                    <a class="btn btn-secondary" href="exercise_library.php">🏋️ <?= htmlspecialchars(t('nav_exercises')) ?></a>
                    <a class="btn btn-secondary" href="pain_report.php">📝 <?= htmlspecialchars(t('report_another_pain')) ?></a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
