<?php
// includes/navbar.php - Responsive Kiosk Touch Navigation Bar
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF']);
}
?>
<nav class="kiosk-nav-bar">
    <ul class="nav-menu">
        <li><a href="dashboard.php" class="<?= $current_page === 'dashboard.php' ? 'active' : '' ?>"><?= htmlspecialchars(t('nav_home')) ?></a></li>
        <li><a href="body_explorer.php" class="<?= $current_page === 'body_explorer.php' ? 'active' : '' ?>"><?= htmlspecialchars(t('nav_body_explorer')) ?></a></li>
        <li><a href="pain_report.php" class="<?= in_array($current_page, ['pain_report.php', 'submit_pain_assessment.php', 'assessment_report.php']) ? 'active' : '' ?>"><?= htmlspecialchars(t('nav_report_pain')) ?></a></li>
        <li><a href="food_suggestions.php" class="<?= $current_page === 'food_suggestions.php' ? 'active' : '' ?>"><?= htmlspecialchars(t('nav_food')) ?></a></li>
        <li><a href="reminders.php" class="<?= $current_page === 'reminders.php' ? 'active' : '' ?>"><?= htmlspecialchars(t('nav_reminders')) ?></a></li>
        <li><a href="emergency_guidance.php" class="<?= $current_page === 'emergency_guidance.php' ? 'active' : '' ?>"><?= htmlspecialchars(t('nav_emergency')) ?></a></li>
        <li><a href="exercise_library.php" class="<?= $current_page === 'exercise_library.php' ? 'active' : '' ?>"><?= htmlspecialchars(t('nav_exercises')) ?></a></li>
        <li><a href="progress_tracking.php" class="<?= $current_page === 'progress_tracking.php' ? 'active' : '' ?>"><?= htmlspecialchars(t('nav_progress')) ?></a></li>
        <li><a href="billing.php" class="<?= in_array($current_page, ['billing.php', 'receipt.php', 'payment_section.php']) ? 'active' : '' ?>"><?= htmlspecialchars(t('nav_billing')) ?></a></li>
        <li><a href="documents.php" class="<?= $current_page === 'documents.php' ? 'active' : '' ?>"><?= htmlspecialchars(t('nav_documents')) ?></a></li>
        <li class="nav-exit"><a href="logout.php"><?= htmlspecialchars(t('nav_logout')) ?></a></li>
    </ul>
</nav>

<!-- Mobile Bottom Navigation Bar (Visible on mobile/PWA) -->
<div class="mobile-bottom-bar">
    <a href="dashboard.php" class="mob-tab <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
        <span class="mob-tab-icon">🏠</span>
        <span class="mob-tab-label">Home</span>
    </a>
    <a href="reminders.php" class="mob-tab <?= $current_page === 'reminders.php' ? 'active' : '' ?>">
        <span class="mob-tab-icon">💊</span>
        <span class="mob-tab-label">Alarms</span>
    </a>
    <a href="pain_report.php" class="mob-tab <?= in_array($current_page, ['pain_report.php', 'submit_pain_assessment.php']) ? 'active' : '' ?>">
        <span class="mob-tab-icon">📝</span>
        <span class="mob-tab-label">Pain</span>
    </a>
    <a href="emergency_guidance.php" class="mob-tab <?= $current_page === 'emergency_guidance.php' ? 'active' : '' ?>">
        <span class="mob-tab-icon">🚨</span>
        <span class="mob-tab-label">SOS</span>
    </a>
    <a href="logout.php" class="mob-tab">
        <span class="mob-tab-icon">🚪</span>
        <span class="mob-tab-label">Exit</span>
    </a>
</div>

<?php 
// Global Automated Medicine Alarm Engine
include_once __DIR__ . '/alarm_monitor.php'; 
?>


