<?php
// pain_report.php - Intake with Body Map, Sliders, Activities and Explorer link
require_once 'config.php';

if (!isset($_SESSION['patient_id'])) {
    header("Location: index.php");
    exit;
}

$patient = getPatientById($pdo, $_SESSION['patient_id']);
$preselected_part = strtolower($_GET['part'] ?? '');
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars(t('pain_assessment_title')) ?> | OrthoKiosk</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .body-part-card {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            padding: 0.8rem 1.2rem;
            border-radius: 16px;
            font-weight: 700;
            color: #1e3a5f;
            cursor: pointer;
            transition: all 0.2s;
        }
        .body-part-card:hover {
            border-color: #2563eb;
            background: #eff6ff;
        }
        .body-part-card input {
            transform: scale(1.3);
            accent-color: #2563eb;
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
                <h2>📝 <?= htmlspecialchars(t('pain_assessment_title')) ?></h2>
                <p>Detailed orthopaedic symptom intake, mobility assessment, and clinical classification.</p>
            </div>
            <a href="body_explorer.php" class="btn btn-secondary" style="display: inline-block; width: auto; padding: 0.7rem 1.4rem;">
                🦴 <?= htmlspecialchars(t('nav_body_explorer')) ?> →
            </a>
        </div>

        <div class="card">
            <form action="submit_pain_assessment.php" method="POST">
                <!-- Visual Body Map with checkboxes -->
                <div class="form-group" style="margin-bottom: 1.8rem;">
                    <label style="font-size: 1.1rem; font-weight: 700; color: #1a3e5c; margin-bottom: 0.8rem; display: block;">
                        📍 <?= htmlspecialchars(t('affected_area')) ?>
                    </label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 0.8rem;">
                        <?php 
                        $bodyParts = [
                            'Head' => '🧠 ' . t('joint_head'),
                            'Neck' => '🧣 ' . t('joint_neck'),
                            'Shoulder' => '🦾 ' . t('joint_shoulder'),
                            'Elbow' => '💪 ' . t('joint_elbow'),
                            'Wrist' => '✋ ' . t('joint_wrist'),
                            'Back' => '🧘 ' . t('joint_back'),
                            'Hip' => '🚶 ' . t('joint_hip'),
                            'Knee' => '🦵 ' . t('joint_knee'),
                            'Ankle' => '🦶 ' . t('joint_ankle'),
                            'Foot' => '👣 ' . t('joint_foot')
                        ];
                        foreach($bodyParts as $partKey => $partLabel): 
                            $isChecked = ($preselected_part === strtolower($partKey));
                        ?>
                        <label class="body-part-card">
                            <input type="checkbox" name="body_parts[]" value="<?= $partKey ?>" <?= $isChecked ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($partLabel) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Pain Intensity Slider -->
                <div class="form-group" style="margin-bottom: 1.8rem; background: #f8fafc; padding: 1.2rem; border-radius: 18px; border: 1px solid #e2e8f0;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem;">
                        <label style="font-weight: 700; color: #1a3e5c;">🔥 <?= htmlspecialchars(t('pain_intensity_label')) ?></label>
                        <span style="background: #dc2626; color: white; padding: 0.3rem 0.9rem; border-radius: 20px; font-weight: 800; font-size: 1.1rem;" id="intensityVal">3</span>
                    </div>
                    <input type="range" id="intensity" name="pain_intensity" min="0" max="10" step="1" value="3" style="width: 100%; height: 12px; accent-color: #dc2626;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: #64748b; margin-top: 0.4rem;">
                        <span>0 - No Pain</span>
                        <span>5 - Moderate Pain</span>
                        <span>10 - Worst Pain Imaginable</span>
                    </div>
                </div>
                
                <!-- Activity-based inputs -->
                <div class="form-group" style="margin-bottom: 1.8rem;">
                    <label style="font-weight: 700; color: #1a3e5c; margin-bottom: 0.6rem; display: block;">🏃 <?= htmlspecialchars(t('worsening_activities')) ?></label>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <label class="body-part-card"><input type="checkbox" name="activities[]" value="Walking"> <?= htmlspecialchars(t('walking')) ?></label>
                        <label class="body-part-card"><input type="checkbox" name="activities[]" value="Running"> <?= htmlspecialchars(t('running')) ?></label>
                        <label class="body-part-card"><input type="checkbox" name="activities[]" value="Climbing stairs"> <?= htmlspecialchars(t('climbing_stairs')) ?></label>
                        <label class="body-part-card"><input type="checkbox" name="activities[]" value="Sitting"> <?= htmlspecialchars(t('sitting')) ?></label>
                        <label class="body-part-card"><input type="checkbox" name="activities[]" value="Lifting"> <?= htmlspecialchars(t('lifting_objects')) ?></label>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.2rem; margin-bottom: 1.5rem;">
                    <div class="form-group">
                        <label style="font-weight: 700; color: #1a3e5c; margin-bottom: 0.4rem; display: block;">💢 <?= htmlspecialchars(t('pain_quality')) ?></label>
                        <select name="pain_quality" style="width:100%; padding:0.8rem; border-radius:14px; border:1px solid #cbd5e1; font-size:1rem;">
                            <option value="dull"><?= htmlspecialchars(t('dull_ache')) ?></option>
                            <option value="sharp"><?= htmlspecialchars(t('sharp')) ?></option>
                            <option value="burning"><?= htmlspecialchars(t('burning')) ?></option>
                            <option value="shooting"><?= htmlspecialchars(t('shooting')) ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label style="font-weight: 700; color: #1a3e5c; margin-bottom: 0.4rem; display: block;">⏱️ <?= htmlspecialchars(t('duration_weeks')) ?></label>
                        <input type="number" name="duration_weeks" step="1" value="2" min="1" max="150" required style="width:100%; padding:0.8rem; border-radius:14px; border:1px solid #cbd5e1; font-size:1rem;">
                    </div>
                    
                    <div class="form-group">
                        <label style="font-weight: 700; color: #1a3e5c; margin-bottom: 0.4rem; display: block;">⚠️ <?= htmlspecialchars(t('activity_worse')) ?></label>
                        <select name="activity_aggravation" style="width:100%; padding:0.8rem; border-radius:14px; border:1px solid #cbd5e1; font-size:1rem;">
                            <option value="yes"><?= htmlspecialchars(t('yes')) ?></option>
                            <option value="no"><?= htmlspecialchars(t('no')) ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="font-weight: 700; color: #1a3e5c; margin-bottom: 0.4rem; display: block;">📝 <?= htmlspecialchars(t('additional_desc')) ?></label>
                    <textarea name="pain_description" rows="3" placeholder="e.g., joint swelling, morning stiffness, tingling in toes..." style="width:100%; padding:0.8rem; border-radius:14px; border:1px solid #cbd5e1; font-size:1rem;"></textarea>
                </div>
                
                <!-- Mobility Score Slider -->
                <div class="form-group" style="margin-bottom: 2rem; background: #f8fafc; padding: 1.2rem; border-radius: 18px; border: 1px solid #e2e8f0;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem;">
                        <label style="font-weight: 700; color: #1a3e5c;">🚶 <?= htmlspecialchars(t('mobility_score')) ?></label>
                        <span style="background: #2563eb; color: white; padding: 0.3rem 0.9rem; border-radius: 20px; font-weight: 800; font-size: 1.1rem;" id="mobilityVal">6</span>
                    </div>
                    <input type="range" name="mobility_score" min="0" max="10" step="1" value="6" style="width: 100%; height: 12px; accent-color: #2563eb;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: #64748b; margin-top: 0.4rem;">
                        <span>0 - Unable to Move</span>
                        <span>5 - Moderate Mobility</span>
                        <span>10 - Full Functional Mobility</span>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" style="padding: 1rem; font-size: 1.1rem;">
                    <?= htmlspecialchars(t('submit_assessment')) ?>
                </button>
            </form>
        </div>
    </div>
</div>
<script>
    const slider = document.getElementById('intensity');
    const valSpan = document.getElementById('intensityVal');
    slider.oninput = () => valSpan.innerText = slider.value;
    const mobSlider = document.querySelector('input[name="mobility_score"]');
    const mobSpan = document.getElementById('mobilityVal');
    if(mobSlider) mobSlider.oninput = () => mobSpan.innerText = mobSlider.value;
</script>
</body>
</html>