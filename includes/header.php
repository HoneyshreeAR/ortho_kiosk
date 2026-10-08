<?php
// includes/header.php - Global Header with Language Switcher and SOS
$current_page = basename($_SERVER['PHP_SELF']);
$active_lang = getCurrentLang();

$build_lang_url = function($target_lang) {
    $params = $_GET;
    $params['lang'] = $target_lang;
    return '?' . http_build_query($params);
};
?>
<div class="kiosk-top-bar">
    <div class="kiosk-brand">
        <a href="<?= isset($patient) ? 'dashboard.php' : 'index.php' ?>" class="brand-link">
            <span class="brand-icon">🦴</span>
            <div>
                <div class="brand-title"><?= htmlspecialchars(t('app_name')) ?></div>
                <div class="brand-sub"><?= htmlspecialchars(t('tagline')) ?></div>
            </div>
        </a>
    </div>

    <div class="kiosk-header-actions">
        <!-- Install Mobile App PWA Button -->
        <button id="pwaInstallBtn" type="button" class="pwa-install-btn" style="display:none;" onclick="triggerPwaInstall()">
            📲 <?= htmlspecialchars(t('install_app')) ?>
        </button>

        <!-- Emergency SOS Button -->
        <a href="emergency_guidance.php" class="emergency-sos-btn">
            <?= htmlspecialchars(t('emergency_sos')) ?>
        </a>

        <!-- Multilingual Language Selector -->
        <div class="lang-selector">
            <span class="lang-globe">🌐</span>
            <a href="<?= htmlspecialchars($build_lang_url('en')) ?>" class="lang-btn <?= $active_lang === 'en' ? 'active' : '' ?>" title="English">EN</a>
            <a href="<?= htmlspecialchars($build_lang_url('kn')) ?>" class="lang-btn <?= $active_lang === 'kn' ? 'active' : '' ?>" title="ಕನ್ನಡ">ಕನ್ನಡ</a>
            <a href="<?= htmlspecialchars($build_lang_url('hi')) ?>" class="lang-btn <?= $active_lang === 'hi' ? 'active' : '' ?>" title="हिन्दी">हिन्दी</a>
        </div>

        <?php if (isset($patient) && !empty($patient)): ?>
        <div class="patient-pill">
            <span class="patient-avatar">👤</span>
            <div>
                <strong><?= htmlspecialchars($patient['name']) ?></strong>
                <small>#<?= htmlspecialchars($patient['id']) ?> | <?= htmlspecialchars($patient['phone']) ?></small>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Progressive Web App (PWA) Registration & Install Engine -->
<script>
    let deferredPrompt = null;

    // Register Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js')
                .then(reg => console.log('✔ Service Worker registered for OrthoKiosk:', reg.scope))
                .catch(err => console.warn('Service Worker registration failed:', err));
        });
    }

    // Capture install prompt
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        const btn = document.getElementById('pwaInstallBtn');
        if (btn) btn.style.display = 'inline-flex';
    });

    function triggerPwaInstall() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('User installed OrthoKiosk mobile app');
                }
                deferredPrompt = null;
                const btn = document.getElementById('pwaInstallBtn');
                if (btn) btn.style.display = 'none';
            });
        } else {
            alert("To install this app on your phone:\n1. Tap your browser menu (⋮ or Share icon)\n2. Select 'Add to Home screen' or 'Install App'.");
        }
    }

    window.addEventListener('appinstalled', () => {
        console.log('OrthoKiosk Mobile App installed successfully.');
        const btn = document.getElementById('pwaInstallBtn');
        if (btn) btn.style.display = 'none';
    });
</script>

