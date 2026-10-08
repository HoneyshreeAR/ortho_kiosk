<?php
// index.php - Landing & Patient Identification with Slideshow & Info Modals
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>OrthoKiosk | Self-Service Orthopaedic System</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1a3e5c">
    <link rel="stylesheet" href="style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(145deg, #eef2f8 0%, #dce5f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.5rem;
        }

        .kiosk-container {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
        }

        /* Header */
        .header {
            background: white;
            border-radius: 40px;
            padding: 1rem 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        }

        .logo h1 {
            font-size: 1.8rem;
            color: #1a3e5c;
        }

        .logo p {
            font-size: 0.8rem;
            color: #5b7c9e;
        }

        .nav-links {
            display: flex;
            gap: 1.2rem;
        }

        .nav-link {
            background: none;
            border: none;
            font-weight: 600;
            color: #2c5a7a;
            cursor: pointer;
            padding: 0.5rem 1rem;
            border-radius: 40px;
            transition: 0.2s;
            font-size: 0.9rem;
        }

        .nav-link:hover {
            background: #e9f0f5;
        }

        .admin-btn {
            background: #1a3e5c;
            color: white;
            text-decoration: none;
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-weight: 600;
        }

        .admin-btn:hover {
            background: #0f2b40;
        }

        /* Main card with slideshow and login */
        .main-card {
            background: white;
            border-radius: 40px;
            overflow: hidden;
            box-shadow: 0 20px 35px -12px rgba(0,0,0,0.15);
            display: flex;
            flex-wrap: wrap;
        }

        .slideshow-area {
            flex: 1.2;
            min-width: 280px;
            background: #f0f4fa;
            position: relative;
            overflow: hidden;
        }

        .slideshow-container {
            position: relative;
            width: 100%;
            height: 100%;
            min-height: 380px;
        }

        .slide {
            position: absolute;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0;
            transition: opacity 1s ease-in-out;
        }

        .slide.active {
            opacity: 1;
        }

        .login-area {
            flex: 1;
            padding: 2.5rem;
            background: white;
        }

        .login-area h2 {
            font-size: 1.8rem;
            color: #1a3e5c;
            margin-bottom: 0.5rem;
        }

        .login-area p {
            color: #5b7c9e;
            margin-bottom: 1.8rem;
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.4rem;
            color: #1f4a6e;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid #cbd5e1;
            border-radius: 60px;
            font-size: 1rem;
        }

        .btn {
            display: inline-block;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 60px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: 0.2s;
            width: 100%;
        }

        .btn-primary {
            background: #1a3e5c;
            color: white;
        }

        .btn-primary:hover {
            background: #0f2b40;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #1a3e5c;
        }

        .btn-secondary:hover {
            background: #cbd5e1;
        }

        hr {
            margin: 1.5rem 0;
            border: none;
            border-top: 1px solid #e2e8f0;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            max-width: 500px;
            width: 90%;
            border-radius: 32px;
            padding: 2rem;
            position: relative;
        }

        .modal-content h3 {
            margin-bottom: 1rem;
            color: #1a3e5c;
        }

        .close-modal {
            position: absolute;
            top: 1rem;
            right: 1.5rem;
            font-size: 1.5rem;
            cursor: pointer;
        }

        @media (max-width: 780px) {
            .main-card {
                flex-direction: column;
            }
            .slideshow-area {
                min-height: 250px;
            }
            .login-area {
                padding: 1.8rem;
            }
            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
        }
    </style>
</head>
<body>
<div class="kiosk-container">
    <div class="header">
        <div class="logo">
            <h1>🦴 <?= htmlspecialchars(t('app_name')) ?></h1>
            <p><?= htmlspecialchars(t('tagline')) ?></p>
        </div>
        <div class="nav-links" style="align-items: center;">
            <!-- Install Mobile App PWA Button -->
            <button id="pwaInstallBtn" type="button" class="pwa-install-btn" style="display:none; margin-right: 0.5rem;" onclick="triggerPwaInstall()">
                📲 <?= htmlspecialchars(t('install_app')) ?>
            </button>

            <!-- Emergency SOS Button -->
            <a href="emergency_guidance.php" class="emergency-sos-btn" style="margin-right: 0.5rem;">
                <?= htmlspecialchars(t('emergency_sos')) ?>
            </a>

            <!-- Multilingual Language Selector -->
            <div class="lang-selector" style="background: #f1f5f9; padding: 0.3rem 0.6rem; border-radius: 30px; margin-right: 0.5rem;">
                <span class="lang-globe">🌐</span>
                <a href="?lang=en" class="lang-btn <?= getCurrentLang() === 'en' ? 'active' : '' ?>" style="color: #1a3e5c;">EN</a>
                <a href="?lang=kn" class="lang-btn <?= getCurrentLang() === 'kn' ? 'active' : '' ?>" style="color: #1a3e5c;">ಕನ್ನಡ</a>
                <a href="?lang=hi" class="lang-btn <?= getCurrentLang() === 'hi' ? 'active' : '' ?>" style="color: #1a3e5c;">हिन्दी</a>
            </div>

            <a href="body_explorer.php" class="nav-link">🦴 <?= htmlspecialchars(t('nav_body_explorer')) ?></a>
            <button class="nav-link" data-info="about">📖 <?= htmlspecialchars(t('about')) ?></button>
            <button class="nav-link" data-info="contact">📞 <?= htmlspecialchars(t('contact')) ?></button>
            <a href="admin_login.php" class="admin-btn">🔐 <?= htmlspecialchars(t('admin')) ?></a>
        </div>
    </div>

    <div class="main-card">
        <!-- Slideshow Area -->
        <div class="slideshow-area">
            <div class="slideshow-container" id="slideshowContainer">
                <img class="slide active" src="https://images.unsplash.com/photo-1579684385127-1ef15d508118?w=600&h=400&fit=crop" alt="Orthopaedic care">
                <img class="slide" src="https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?w=600&h=400&fit=crop" alt="Physical therapy">
                <img class="slide" src="https://images.unsplash.com/photo-1516549655169-df83a0774514?w=600&h=400&fit=crop" alt="X-ray consultation">
                <img class="slide" src="https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=600&h=400&fit=crop" alt="Medical team">               
            </div>
        </div>

        <!-- Login / Registration Area -->
        <div class="login-area">
            <h2><?= htmlspecialchars(t('welcome')) ?></h2>
            <p><?= htmlspecialchars(t('tagline')) ?></p>

            <?php if(isset($_SESSION['error'])): ?>
                <div style="background: #fee2e2; padding: 12px; border-radius: 12px; margin-bottom: 1rem; color: #b91c1c;"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <form action="process_login.php" method="POST">
                <div class="form-group">
                    <label>📞 <?= htmlspecialchars(t('phone')) ?></label>
                    <input type="tel" name="phone" placeholder="<?= htmlspecialchars(t('phone_placeholder')) ?>" required>
                </div>
                <button type="submit" class="btn btn-primary"><?= htmlspecialchars(t('login_continue')) ?></button>
            </form>
            <hr>
            <div style="text-align: center; display: flex; flex-direction: column; gap: 0.8rem;">
                <a href="register.php" class="btn btn-secondary"><?= htmlspecialchars(t('new_registration')) ?></a>
                <a href="food_suggestions.php" style="color: #0d9488; font-weight: 600; font-size: 0.9rem; text-decoration: none;">
                    🥗 <?= htmlspecialchars(t('food_title')) ?> →
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal for About / History / Contact -->
<div id="infoModal" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h3 id="modalTitle"></h3>
        <p id="modalBody"></p>
    </div>
</div>

<script>
    // Slideshow logic
    const slides = document.querySelectorAll('.slide');
    let currentIndex = 0;
    const totalSlides = slides.length;

    function showSlide(index) {
        slides.forEach((slide, i) => {
            if (i === index) {
                slide.classList.add('active');
            } else {
                slide.classList.remove('active');
            }
        });
    }

    function nextSlide() {
        currentIndex = (currentIndex + 1) % totalSlides;
        showSlide(currentIndex);
    }

    if (totalSlides > 0) {
        setInterval(nextSlide, 2000); // change every 2 seconds
    }

    // Modal handling for About, History, Contact
    const modal = document.getElementById('infoModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    const closeModalSpan = document.querySelector('.close-modal');

    const infoContent = {
        about: {
            title: "📖 About OrthoKiosk",
            body: "OrthoKiosk is a modern, self-service orthopaedic patient management system designed to streamline pain reporting, treatment tracking, and medical document management. Our mission is to enhance the patient experience in orthopaedic clinics with intuitive digital tools."
        },
        history: {
            title: "📜 Our History",
            body: "Founded in 2022 by orthopaedic surgeons and health tech experts, OrthoKiosk started as a research project to reduce waiting times and improve data accuracy. Since its launch, it has been adopted by over 15 clinics across India, serving thousands of patients."
        },
        contact: {
            title: "📞 Contact Us",
            body: "Email: support@orthokiosk.com<br>Phone: +91 98765 43210<br>Address: Ortho Health Tower, 2nd Floor, Medical District, Mumbai - 400001<br><br>Clinic hours: Mon-Sat 9am - 6pm"
        }
    };

    function openModal(type) {
        const content = infoContent[type];
        if (content) {
            modalTitle.innerHTML = content.title;
            modalBody.innerHTML = content.body;
            modal.style.display = 'flex';
        }
    }

    document.querySelectorAll('.nav-link').forEach(btn => {
        btn.addEventListener('click', () => {
            const type = btn.getAttribute('data-info');
            if (type) openModal(type);
        });
    });

    closeModalSpan.addEventListener('click', () => {
        modal.style.display = 'none';
    });
    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });

    // PWA Service Worker & Install Handler
    let deferredPrompt = null;
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js')
                .then(reg => console.log('✔ Service Worker registered for OrthoKiosk:', reg.scope))
                .catch(err => console.warn('Service Worker registration failed:', err));
        });
    }
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
                deferredPrompt = null;
                const btn = document.getElementById('pwaInstallBtn');
                if (btn) btn.style.display = 'none';
            });
        } else {
            alert("To install this app on your phone:\n1. Tap your browser menu (⋮ or Share icon)\n2. Select 'Add to Home screen' or 'Install App'.");
        }
    }
</script>
</body>
</html>