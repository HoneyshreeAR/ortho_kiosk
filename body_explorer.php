<?php
// body_explorer.php - Interactive Visual Body & Joint Information Explorer
require_once 'config.php';

$patient = null;
if (isset($_SESSION['patient_id'])) {
    $patient = getPatientById($pdo, $_SESSION['patient_id']);
}

// Initial active joint (default: Knee)
$active_joint = $_GET['joint'] ?? 'knee';
$valid_joints = ['head', 'neck', 'shoulder', 'elbow', 'wrist', 'back', 'hip', 'knee', 'ankle', 'foot'];
if (!in_array($active_joint, $valid_joints)) {
    $active_joint = 'knee';
}

// Comprehensive Joint Knowledge Base with localization
$joint_database = [
    'head' => [
        'name' => t('joint_head'),
        'kannada' => 'ತಲೆ ಮತ್ತು ದವಡೆ (TMJ)',
        'hindi' => 'सिर और जबड़ा (TMJ)',
        'icon' => '🧠',
        'bones' => 'Cranium, Mandible, Temporomandibular Joint (TMJ), Cervical-Cranial Junction',
        'ligaments' => 'Temporomandibular ligament, Sphenomandibular ligament, Articular disc',
        'conditions' => [
            'TMJ Dysfunction & Jaw Clicking',
            'Cervicogenic Headache (neck-origin headaches)',
            'Tension Headaches from postural neck strain',
            'Whiplash injury to upper cervical joints'
        ],
        'rom' => [
            ['motion' => 'Jaw Opening (Depression)', 'degrees' => '40 - 50 mm', 'pct' => 85],
            ['motion' => 'Lateral Deviation', 'degrees' => '8 - 12 mm', 'pct' => 70],
            ['motion' => 'Protrusion', 'degrees' => '> 7 mm', 'pct' => 75]
        ],
        'care_tips' => 'Avoid clenching teeth, chew evenly on both sides, maintain forward head posture correction during desk work, apply warm compresses to masseter muscles.',
        'diet_condition' => 'spondylosis',
        'exercise_part' => 'Head'
    ],

    'neck' => [
        'name' => t('joint_neck'),
        'kannada' => 'ಕುತ್ತಿಗೆ (ಸರ್ವಿಕಲ್ ಸ್ಪೈನ್)',
        'hindi' => 'गर्दन (ग्रीवा रीढ़ / सर्वाइकल)',
        'icon' => '🧣',
        'bones' => '7 Cervical Vertebrae (C1 Atlas to C7), Facet joints, Intervertebral Discs',
        'ligaments' => 'Anterior Longitudinal Ligament, Ligamentum Flavum, Nuchal Ligament',
        'conditions' => [
            'Cervical Spondylosis (Disc Degeneration)',
            'Cervical Radiculopathy (Pinched Nerve radiating down arm)',
            'Text-Neck Syndrome (Forward Head Posture strain)',
            'Acute Neck Muscle Spasm (Wry Neck / Torticollis)'
        ],
        'rom' => [
            ['motion' => 'Flexion (Chin to Chest)', 'degrees' => '45° - 50°', 'pct' => 80],
            ['motion' => 'Extension (Looking up)', 'degrees' => '55° - 60°', 'pct' => 85],
            ['motion' => 'Lateral Rotation (Left/Right)', 'degrees' => '70° - 80°', 'pct' => 90],
            ['motion' => 'Lateral Flexion (Ear to Shoulder)', 'degrees' => '40° - 45°', 'pct' => 75]
        ],
        'care_tips' => 'Keep computer monitor at eye level, avoid cradling phone between ear and shoulder, use an ergonomic contour pillow, take neck stretch micro-breaks every 45 minutes.',
        'diet_condition' => 'spondylosis',
        'exercise_part' => 'Neck'
    ],

    'shoulder' => [
        'name' => t('joint_shoulder'),
        'kannada' => 'ಭುಜ (ಗ್ಲೆನೋಹ್ಯೂಮರಲ್)',
        'hindi' => 'कंधा (ग्लेनोह्यूमरल जोड़)',
        'icon' => '🦾',
        'bones' => 'Scapula (Glenoid cavity), Humerus head, Clavicle (Collar bone)',
        'ligaments' => 'Glenohumeral ligaments, Coracohumeral ligament, Labrum',
        'conditions' => [
            'Rotator Cuff Tendonitis & Partial Tears (Supraspinatus)',
            'Adhesive Capsulitis (Frozen Shoulder)',
            'Subacromial Shoulder Impingement',
            'Glenohumeral Dislocation or Labral Tear (Bankart lesion)'
        ],
        'rom' => [
            ['motion' => 'Forward Flexion (Arm raised up)', 'degrees' => '170° - 180°', 'pct' => 95],
            ['motion' => 'Abduction (Arm to side)', 'degrees' => '170° - 180°', 'pct' => 95],
            ['motion' => 'External Rotation', 'degrees' => '80° - 90°', 'pct' => 85],
            ['motion' => 'Internal Rotation', 'degrees' => '60° - 70°', 'pct' => 70]
        ],
        'care_tips' => 'Strengthen rotator cuff stabilizers with light resistance bands, avoid heavy overhead lifting during acute pain, do pendulum swings to maintain mobility.',
        'diet_condition' => 'ligament',
        'exercise_part' => 'Shoulder'
    ],

    'elbow' => [
        'name' => t('joint_elbow'),
        'kannada' => 'ಮೊಣಕೈ ಮತ್ತು ಮುಂದೋಳು',
        'hindi' => 'कोहनी और अग्रभाग',
        'icon' => '💪',
        'bones' => 'Humerus (Trochlea & Capitulum), Radius head, Ulna (Olecranon process)',
        'ligaments' => 'Ulnar Collateral Ligament (UCL), Radial Collateral Ligament, Annular ligament',
        'conditions' => [
            'Lateral Epicondylitis (Tennis Elbow)',
            'Medial Epicondylitis (Golfer’s Elbow)',
            'Olecranon Bursitis (Student’s Elbow swelling)',
            'Cubital Tunnel Syndrome (Ulnar nerve compression)'
        ],
        'rom' => [
            ['motion' => 'Flexion (Bending elbow)', 'degrees' => '140° - 150°', 'pct' => 90],
            ['motion' => 'Extension (Straightening)', 'degrees' => '0° (Full straight)', 'pct' => 100],
            ['motion' => 'Forearm Pronation (Palm down)', 'degrees' => '80° - 85°', 'pct' => 85],
            ['motion' => 'Forearm Supination (Palm up)', 'degrees' => '85° - 90°', 'pct' => 90]
        ],
        'care_tips' => 'Use forearm counterforce brace during repetitive typing or racket sports, avoid leaning elbows on hard desk surfaces, stretch wrist extensors regularly.',
        'diet_condition' => 'ligament',
        'exercise_part' => 'Elbow'
    ],

    'wrist' => [
        'name' => t('joint_wrist'),
        'kannada' => 'ಮಣಿಕಟ್ಟು ಮತ್ತು ಕೈ',
        'hindi' => 'कलाई और हाथ',
        'icon' => '✋',
        'bones' => '8 Carpal bones (Scaphoid, Lunate, Triquetrum, Pisiform, Trapezium, Trapezoid, Capitate, Hamate), Radius, Metacarpals',
        'ligaments' => 'Transverse Carpal Ligament (Flexor Retinaculum), Scapholunate ligament',
        'conditions' => [
            'Carpal Tunnel Syndrome (Median nerve tingling)',
            'De Quervain’s Tenosynovitis (Thumb side wrist pain)',
            'Scaphoid Fracture (Fall on outstretched hand - FOOSH)',
            'Wrist Ganglion Cysts'
        ],
        'rom' => [
            ['motion' => 'Palmar Flexion (Wrist down)', 'degrees' => '70° - 80°', 'pct' => 80],
            ['motion' => 'Dorsiflexion (Wrist up)', 'degrees' => '65° - 75°', 'pct' => 75],
            ['motion' => 'Radial Deviation', 'degrees' => '15° - 20°', 'pct' => 50],
            ['motion' => 'Ulnar Deviation', 'degrees' => '30° - 35°', 'pct' => 65]
        ],
        'care_tips' => 'Use ergonomic neutral wrist rests while typing, wear night cock-up splints for median nerve decompression, perform gentle tendon gliding movements.',
        'diet_condition' => 'ligament',
        'exercise_part' => 'Wrist'
    ],

    'back' => [
        'name' => t('joint_back'),
        'kannada' => 'ಬೆನ್ನುಮೂಳೆ ಮತ್ತು ಕೆಳಬೆನ್ನು (ಲುಂಬಾರ್)',
        'hindi' => 'रीढ़ और निचली पीठ (लम्बर)',
        'icon' => '🧘',
        'bones' => '12 Thoracic & 5 Lumbar Vertebrae (L1 to L5), Sacrum, Intervertebral Discs',
        'ligaments' => 'Posterior Longitudinal Ligament, Iliolumbar Ligament, Supraspinous Ligament',
        'conditions' => [
            'Lumbar Disc Herniation / Bulge (Slip Disc)',
            'Sciatica (L4-S1 nerve root compression shooting down leg)',
            'Lumbar Spondylolisthesis (Vertebra slipping forward)',
            'Mechanical Low Back Muscle Strain & Facet Syndrome'
        ],
        'rom' => [
            ['motion' => 'Lumbar Forward Flexion', 'degrees' => '40° - 60° (Hands reach shins)', 'pct' => 75],
            ['motion' => 'Lumbar Extension (Arching back)', 'degrees' => '20° - 35°', 'pct' => 60],
            ['motion' => 'Lateral Side Bending', 'degrees' => '15° - 20° each side', 'pct' => 50]
        ],
        'care_tips' => 'Always bend at the knees when lifting heavy objects (never bend straight from the waist), maintain strong transversus abdominis core muscles, avoid prolonged slouched sitting.',
        'diet_condition' => 'spondylosis',
        'exercise_part' => 'Back'
    ],

    'hip' => [
        'name' => t('joint_hip'),
        'kannada' => 'ಸೊಂಟ ಮತ್ತು ಪೆಲ್ವಿಸ್',
        'hindi' => 'कूल्हा और पेल्विस',
        'icon' => '🚶',
        'bones' => 'Acetabulum (Pelvis socket), Femoral head (Ball), Femur neck',
        'ligaments' => 'Iliofemoral ligament (Y-ligament of Bigelow), Pubofemoral ligament, Ischiofemoral ligament',
        'conditions' => [
            'Hip Osteoarthritis (Loss of joint space & groin pain)',
            'Femoroacetabular Impingement (FAI)',
            'Greater Trochanteric Pain Syndrome (Hip Bursitis)',
            'Avascular Necrosis (AVN) of the Femoral Head'
        ],
        'rom' => [
            ['motion' => 'Hip Flexion (Knee to chest)', 'degrees' => '115° - 125°', 'pct' => 85],
            ['motion' => 'Hip Extension (Leg back)', 'degrees' => '10° - 15°', 'pct' => 50],
            ['motion' => 'Abduction (Leg out to side)', 'degrees' => '40° - 45°', 'pct' => 70],
            ['motion' => 'Internal / External Rotation', 'degrees' => '40° - 45° each', 'pct' => 70]
        ],
        'care_tips' => 'Perform non-weight-bearing exercises like swimming or stationary cycling, avoid crossing legs for prolonged hours, maintain healthy BMI to reduce joint load.',
        'diet_condition' => 'osteoarthritis',
        'exercise_part' => 'Hip'
    ],

    'knee' => [
        'name' => t('joint_knee'),
        'kannada' => 'ಮೊಣಕಾಲು ಕೀಲು (ಪಟೆಲ್ಲಾ/ಮೆನಿಸ್ಕಸ್)',
        'hindi' => 'घुटना (पटेला / मेनिस्कस)',
        'icon' => '🦵',
        'bones' => 'Femur (Thigh bone), Tibia (Shin bone), Patella (Kneecap), Fibula',
        'ligaments' => 'Anterior Cruciate Ligament (ACL), PCL, Medial Collateral (MCL), LCL, Medial & Lateral Meniscus',
        'conditions' => [
            'Knee Osteoarthritis (Degeneration of articular cartilage)',
            'ACL / Meniscus Tear (Twisting sports injuries)',
            'Patellofemoral Pain Syndrome (Runner’s Knee)',
            'Baker’s Cyst (Fluid swelling behind the knee)'
        ],
        'rom' => [
            ['motion' => 'Flexion (Bending knee backward)', 'degrees' => '135° - 145°', 'pct' => 90],
            ['motion' => 'Extension (Full straight leg)', 'degrees' => '0° (Fully straight)', 'pct' => 100]
        ],
        'care_tips' => 'Strengthen vastus medialis (quadriceps) and hamstring muscles, wear cushioned supportive footwear, avoid deep squatting on hard floors if experiencing acute pain.',
        'diet_condition' => 'osteoarthritis',
        'exercise_part' => 'Knee'
    ],

    'ankle' => [
        'name' => t('joint_ankle'),
        'kannada' => 'ಹಿಮ್ಮಡಿ ಮತ್ತು ಪಾದದ ಕೀಲು',
        'hindi' => 'टखना और एड़ी (Achilles)',
        'icon' => '🦶',
        'bones' => 'Talus, Tibia (Medial Malleolus), Fibula (Lateral Malleolus), Calcaneus (Heel bone)',
        'ligaments' => 'Anterior Talofibular Ligament (ATFL - most commonly sprained), Calcaneofibular, Deltoid Ligament',
        'conditions' => [
            'Lateral Inversion Ankle Sprain (Torn ATFL)',
            'Achilles Tendinopathy & Rupture',
            'Ankle Osteoarthritis (Post-traumatic)',
            'Tarsal Tunnel Syndrome'
        ],
        'rom' => [
            ['motion' => 'Dorsiflexion (Toes pointing up)', 'degrees' => '15° - 20°', 'pct' => 60],
            ['motion' => 'Plantarflexion (Toes pointing down)', 'degrees' => '45° - 50°', 'pct' => 85],
            ['motion' => 'Inversion (Sole inward)', 'degrees' => '30° - 35°', 'pct' => 70],
            ['motion' => 'Eversion (Sole outward)', 'degrees' => '15° - 20°', 'pct' => 50]
        ],
        'care_tips' => 'Train single-leg balance to restore proprioception after sprains, warm up calf muscles before running, use ankle compression support during rehabilitation.',
        'diet_condition' => 'ligament',
        'exercise_part' => 'Ankle'
    ],

    'foot' => [
        'name' => t('joint_foot'),
        'kannada' => 'ಪಾದ ಮತ್ತು ಪ್ಲಾಂಟರ್ ಫ್ಯಾಸಿಯಾ',
        'hindi' => 'पैर और तलवा (Plantar Fascia)',
        'icon' => '👣',
        'bones' => 'Tarsals (Navicular, Cuboid, Cuneiforms), 5 Metatarsals, 14 Phalanges (Toes)',
        'ligaments' => 'Plantar Fascia Aponeurosis, Spring Ligament (Plantar calcaneonavicular)',
        'conditions' => [
            'Plantar Fasciitis (Sharp morning heel pain)',
            'Calcaneal Heel Spur',
            'Metatarsalgia & Morton’s Neuroma (Ball of foot pain)',
            'Flat Feet (Pes Planus) and Hallux Valgus (Bunions)'
        ],
        'rom' => [
            ['motion' => 'Great Toe Extension', 'degrees' => '60° - 70°', 'pct' => 75],
            ['motion' => 'Midfoot Inversion/Eversion', 'degrees' => '10° - 15°', 'pct' => 45]
        ],
        'care_tips' => 'Roll a frozen water bottle under your foot arch for 10 minutes, avoid walking barefoot on hard tile floors, choose shoes with firm medial arch support.',
        'diet_condition' => 'fracture',
        'exercise_part' => 'Foot'
    ]
];

$active_data = $joint_database[$active_joint];
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars(t('body_explorer_title')) ?> | OrthoKiosk</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .explorer-layout {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 1.8rem;
            margin-top: 1rem;
        }

        /* Interactive Skeleton / Body Card */
        .body-interactive-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .body-svg-container {
            position: relative;
            width: 100%;
            max-width: 320px;
            height: 520px;
            background: linear-gradient(180deg, #f8fafc 0%, #edf2f7 100%);
            border-radius: 20px;
            border: 2px dashed #cbd5e1;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        /* Joint Hotspot Target Nodes */
        .joint-node {
            position: absolute;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #2563eb;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.4);
            border: 3px solid white;
            transition: all 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-decoration: none;
            z-index: 10;
        }
        .joint-node:hover {
            transform: scale(1.3);
            background: #ffb347;
            box-shadow: 0 6px 16px rgba(255, 179, 71, 0.6);
        }
        .joint-node.active {
            transform: scale(1.35);
            background: #dc2626;
            box-shadow: 0 0 0 5px rgba(220, 38, 38, 0.25);
            animation: pulse-node 1.8s infinite;
        }

        @keyframes pulse-node {
            0% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.5); }
            70% { box-shadow: 0 0 0 12px rgba(220, 38, 38, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
        }

        /* Joint Node Coordinate Placements */
        .node-head { top: 40px; left: 142px; }
        .node-neck { top: 88px; left: 142px; }
        .node-shoulder-left { top: 120px; left: 90px; }
        .node-shoulder-right { top: 120px; left: 194px; }
        .node-elbow { top: 185px; left: 68px; }
        .node-wrist { top: 255px; left: 52px; }
        .node-back { top: 180px; left: 142px; }
        .node-hip { top: 250px; left: 142px; }
        .node-knee-left { top: 345px; left: 115px; }
        .node-knee-right { top: 345px; left: 170px; }
        .node-ankle { top: 440px; left: 115px; }
        .node-foot { top: 480px; left: 105px; }

        /* Joint Quick-Tap Strip */
        .joint-quick-strip {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
            justify-content: center;
        }
        .quick-joint-btn {
            background: #f1f5f9;
            color: #334155;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
        }
        .quick-joint-btn:hover, .quick-joint-btn.active {
            background: #1a3e5c;
            color: white;
        }

        /* Detail Inspector Panel */
        .inspector-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            border-top: 6px solid #2563eb;
        }
        .joint-hero-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 1.2rem;
            margin-bottom: 1.5rem;
        }

        .section-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 1.2rem;
            margin-bottom: 1.5rem;
        }
        .section-box h4 {
            color: #1e3a5f;
            font-size: 1.1rem;
            margin-bottom: 0.6rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* ROM Gauge Bars */
        .rom-item {
            margin-bottom: 0.8rem;
        }
        .rom-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.88rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.3rem;
        }
        .rom-bar-bg {
            height: 10px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }
        .rom-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #10b981);
            border-radius: 10px;
            transition: width 0.6s ease;
        }

        /* 1-Click Action Launchpad */
        .action-launchpad {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.8rem;
        }
        .launch-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            padding: 0.85rem 1rem;
            border-radius: 16px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.92rem;
            text-align: center;
            transition: all 0.2s;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
        }
        .launch-btn.pain {
            background: #dc2626;
            color: white;
        }
        .launch-btn.pain:hover { background: #b91c1c; }
        .launch-btn.ex {
            background: #0284c7;
            color: white;
        }
        .launch-btn.ex:hover { background: #0369a1; }
        .launch-btn.diet {
            background: #10b981;
            color: white;
        }
        .launch-btn.diet:hover { background: #059669; }
        .launch-btn.sos {
            background: #f59e0b;
            color: white;
        }
        .launch-btn.sos:hover { background: #d97706; }

        @media (max-width: 900px) {
            .explorer-layout {
                grid-template-columns: 1fr;
            }
            .body-svg-container {
                max-width: 100%;
            }
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
                <h2>🦴 <?= htmlspecialchars(t('body_explorer_title')) ?></h2>
                <p><?= htmlspecialchars(t('body_explorer_subtitle')) ?></p>
            </div>
            <div style="background: #e0f2fe; color: #0369a1; font-weight: 700; padding: 0.5rem 1.2rem; border-radius: 30px; font-size: 0.9rem;">
                <?= htmlspecialchars(t('tap_joint_instruction')) ?>
            </div>
        </div>

        <div class="explorer-layout">
            <!-- Left Side: Interactive Skeleton Visual -->
            <div class="body-interactive-card">
                <div class="body-svg-container">
                    <!-- Clean Anatomical Skeleton Silhouette SVG -->
                    <svg viewBox="0 0 300 520" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="opacity: 0.85;">
                        <!-- Head -->
                        <circle cx="150" cy="50" r="28" fill="#cbd5e1" stroke="#94a3b8" stroke-width="2"/>
                        <!-- Neck -->
                        <line x1="150" y1="78" x2="150" y2="105" stroke="#94a3b8" stroke-width="6" stroke-linecap="round"/>
                        <!-- Clavicle / Shoulders -->
                        <line x1="100" y1="120" x2="200" y2="120" stroke="#94a3b8" stroke-width="5" stroke-linecap="round"/>
                        <!-- Spine / Torso -->
                        <line x1="150" y1="105" x2="150" y2="250" stroke="#94a3b8" stroke-width="8" stroke-linecap="round"/>
                        <!-- Ribs -->
                        <ellipse cx="150" cy="150" rx="42" ry="18" fill="none" stroke="#cbd5e1" stroke-width="3"/>
                        <ellipse cx="150" cy="180" rx="40" ry="18" fill="none" stroke="#cbd5e1" stroke-width="3"/>
                        <ellipse cx="150" cy="210" rx="36" ry="16" fill="none" stroke="#cbd5e1" stroke-width="3"/>
                        <!-- Arms -->
                        <line x1="100" y1="120" x2="78" y2="185" stroke="#94a3b8" stroke-width="4" stroke-linecap="round"/>
                        <line x1="78" y1="185" x2="62" y2="255" stroke="#94a3b8" stroke-width="4" stroke-linecap="round"/>
                        <line x1="200" y1="120" x2="222" y2="185" stroke="#94a3b8" stroke-width="4" stroke-linecap="round"/>
                        <line x1="222" y1="185" x2="238" y2="255" stroke="#94a3b8" stroke-width="4" stroke-linecap="round"/>
                        <!-- Pelvis -->
                        <path d="M 120 250 Q 150 270 180 250 L 175 285 Q 150 300 125 285 Z" fill="#cbd5e1" stroke="#94a3b8" stroke-width="2"/>
                        <!-- Legs -->
                        <line x1="130" y1="285" x2="125" y2="360" stroke="#94a3b8" stroke-width="6" stroke-linecap="round"/>
                        <line x1="125" y1="360" x2="125" y2="455" stroke="#94a3b8" stroke-width="5" stroke-linecap="round"/>
                        <line x1="170" y1="285" x2="175" y2="360" stroke="#94a3b8" stroke-width="6" stroke-linecap="round"/>
                        <line x1="175" y1="360" x2="175" y2="455" stroke="#94a3b8" stroke-width="5" stroke-linecap="round"/>
                        <!-- Feet -->
                        <ellipse cx="118" cy="495" rx="14" ry="7" fill="#cbd5e1"/>
                        <ellipse cx="182" cy="495" rx="14" ry="7" fill="#cbd5e1"/>
                    </svg>

                    <!-- Interactive Clickable Hotspots -->
                    <a href="?joint=head" class="joint-node node-head <?= $active_joint === 'head' ? 'active' : '' ?>" title="Head">1</a>
                    <a href="?joint=neck" class="joint-node node-neck <?= $active_joint === 'neck' ? 'active' : '' ?>" title="Neck">2</a>
                    <a href="?joint=shoulder" class="joint-node node-shoulder-left <?= $active_joint === 'shoulder' ? 'active' : '' ?>" title="Shoulder">3</a>
                    <a href="?joint=elbow" class="joint-node node-elbow <?= $active_joint === 'elbow' ? 'active' : '' ?>" title="Elbow">4</a>
                    <a href="?joint=wrist" class="joint-node node-wrist <?= $active_joint === 'wrist' ? 'active' : '' ?>" title="Wrist">5</a>
                    <a href="?joint=back" class="joint-node node-back <?= $active_joint === 'back' ? 'active' : '' ?>" title="Spine">6</a>
                    <a href="?joint=hip" class="joint-node node-hip <?= $active_joint === 'hip' ? 'active' : '' ?>" title="Hip">7</a>
                    <a href="?joint=knee" class="joint-node node-knee-left <?= $active_joint === 'knee' ? 'active' : '' ?>" title="Knee">8</a>
                    <a href="?joint=ankle" class="joint-node node-ankle <?= $active_joint === 'ankle' ? 'active' : '' ?>" title="Ankle">9</a>
                    <a href="?joint=foot" class="joint-node node-foot <?= $active_joint === 'foot' ? 'active' : '' ?>" title="Foot">10</a>
                </div>

                <!-- Quick-Tap Button Strip -->
                <div class="joint-quick-strip">
                    <?php foreach ($valid_joints as $j): ?>
                        <a href="?joint=<?= $j ?>" class="quick-joint-btn <?= $active_joint === $j ? 'active' : '' ?>">
                            <?= $joint_database[$j]['icon'] ?> <?= htmlspecialchars($joint_database[$j]['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right Side: Detailed Clinical Inspector -->
            <div class="inspector-panel">
                <div class="joint-hero-title">
                    <div>
                        <h3 style="font-size: 1.8rem; color: #1a3e5c; margin-bottom: 0.2rem;">
                            <?= $active_data['icon'] ?> <?= htmlspecialchars($active_data['name']) ?>
                        </h3>
                        <?php if (getCurrentLang() === 'kn'): ?>
                            <div style="font-size: 1.1rem; color: #0d9488; font-weight: 700;">ಕನ್ನಡ: <?= htmlspecialchars($active_data['kannada']) ?></div>
                        <?php elseif (getCurrentLang() === 'hi'): ?>
                            <div style="font-size: 1.1rem; color: #0d9488; font-weight: 700;">हिन्दी: <?= htmlspecialchars($active_data['hindi']) ?></div>
                        <?php endif; ?>
                    </div>
                    <span style="background: #dbeafe; color: #1e40af; font-weight: 800; padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.85rem;">
                        CLINICAL PROFILE
                    </span>
                </div>

                <!-- 1. Anatomical Structure -->
                <div class="section-box">
                    <h4>🦴 <?= htmlspecialchars(t('anatomy_details')) ?></h4>
                    <p style="color: #334155; margin-bottom: 0.5rem; font-size: 0.95rem;">
                        <strong>Bones & Articulations:</strong> <?= htmlspecialchars($active_data['bones']) ?>
                    </p>
                    <p style="color: #334155; margin: 0; font-size: 0.95rem;">
                        <strong>Ligaments & Cartilage:</strong> <?= htmlspecialchars($active_data['ligaments']) ?>
                    </p>
                </div>

                <!-- 2. Common Conditions -->
                <div class="section-box">
                    <h4>🩺 <?= htmlspecialchars(t('common_conditions')) ?></h4>
                    <ul style="padding-left: 1.2rem; color: #334155; font-size: 0.95rem; line-height: 1.6;">
                        <?php foreach ($active_data['conditions'] as $cond): ?>
                            <li><?= htmlspecialchars($cond) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- 3. Normal Range of Motion -->
                <div class="section-box">
                    <h4>📐 <?= htmlspecialchars(t('range_of_motion')) ?></h4>
                    <?php foreach ($active_data['rom'] as $romItem): ?>
                        <div class="rom-item">
                            <div class="rom-labels">
                                <span><?= htmlspecialchars($romItem['motion']) ?></span>
                                <strong><?= htmlspecialchars($romItem['degrees']) ?></strong>
                            </div>
                            <div class="rom-bar-bg">
                                <div class="rom-bar-fill" style="width: <?= $romItem['pct'] ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- 4. Prevention & Care Tips -->
                <div class="section-box" style="background: #f0fdf4; border-color: #bbf7d0;">
                    <h4 style="color: #15803d;">💡 <?= htmlspecialchars(t('prevention_care')) ?></h4>
                    <p style="color: #166534; font-size: 0.95rem; line-height: 1.5; margin: 0;">
                        <?= htmlspecialchars($active_data['care_tips']) ?>
                    </p>
                </div>

                <!-- 5. One-Click Clinical Launchpad -->
                <div class="action-launchpad">
                    <a href="pain_report.php" class="launch-btn pain">
                        <?= htmlspecialchars(t('action_report_pain')) ?>
                    </a>
                    <a href="exercise_library.php?body_part=<?= urlencode($active_data['exercise_part']) ?>" class="launch-btn ex">
                        <?= htmlspecialchars(t('action_view_exercises')) ?>
                    </a>
                    <a href="food_suggestions.php?condition=<?= urlencode($active_data['diet_condition']) ?>" class="launch-btn diet">
                        <?= htmlspecialchars(t('action_view_diet')) ?>
                    </a>
                    <a href="emergency_guidance.php" class="launch-btn sos">
                        <?= htmlspecialchars(t('action_first_aid')) ?>
                    </a>
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <a href="dashboard.php" class="btn btn-secondary" style="display: inline-block; width: auto; padding: 0.8rem 2rem;">
                🏠 <?= htmlspecialchars(t('back_to_dashboard')) ?>
            </a>
        </div>
    </div>
</div>
</body>
</html>
