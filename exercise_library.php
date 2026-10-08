<?php
// exercise_library.php - Exercise Video Library with Access Restriction (No Payment)
require_once 'config.php';
if (!isset($_SESSION['patient_id'])) { 
    header("Location: index.php"); 
    exit; 
}

$patient = getPatientById($pdo, $_SESSION['patient_id']);
$search = $_GET['search'] ?? '';
$selected_body_part = $_GET['body_part'] ?? '';

// Get the patient's latest pain assessment to know their affected areas
$stmt = $pdo->prepare("SELECT body_part, classification, assessment_date FROM pain_assessments WHERE patient_id = ? ORDER BY assessment_date DESC LIMIT 1");
$stmt->execute([$_SESSION['patient_id']]);
$latest_assessment = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all affected areas from the patient's latest assessment
$patient_affected_areas = [];
if ($latest_assessment) {
    // Split comma-separated body parts (e.g., "Head, Neck, Shoulder")
    $patient_affected_areas = array_map('trim', explode(',', $latest_assessment['body_part']));
}
$patient_classification = $latest_assessment['classification'] ?? 'musculoskeletal';

// Check if user is trying to search for a restricted body part
$is_restricted = false;
$requested_body_part = $selected_body_part;

if (!empty($selected_body_part) && !empty($patient_affected_areas)) {
    // Check if requested body part is in patient's affected areas
    $found = false;
    foreach ($patient_affected_areas as $area) {
        if (strcasecmp(trim($selected_body_part), trim($area)) == 0) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $is_restricted = true;
    }
}

// Complete Exercise Database with videos for all body parts
$exercise_database = [
    // Head & Neck Exercises (Accessible for Head/Neck patients)
    ['id' => 1, 'title' => 'Neck Stretches for Tension Relief', 'body_part' => 'Head', 'classification' => 'musculoskeletal', 'duration' => '5 mins', 'difficulty' => 'Beginner', 'video_url' => 'https://www.youtube.com/embed/6JZ_TWqO6to', 'thumbnail' => 'https://img.youtube.com/vi/6JZ_TWqO6to/mqdefault.jpg', 'description' => 'Gentle neck stretches to relieve tension and improve mobility'],
    ['id' => 2, 'title' => 'Neck Pain Relief Exercises', 'body_part' => 'Neck', 'classification' => 'mechanical', 'duration' => '8 mins', 'difficulty' => 'Beginner', 'video_url' => 'https://www.youtube.com/embed/1LUFgGp2iFk', 'thumbnail' => 'https://img.youtube.com/vi/1LUFgGp2iFk/mqdefault.jpg', 'description' => 'Simple exercises to reduce neck pain and stiffness'],
    ['id' => 20, 'title' => 'Headache Relief Exercises', 'body_part' => 'Head', 'classification' => 'musculoskeletal', 'duration' => '6 mins', 'difficulty' => 'Beginner', 'video_url' => 'https://www.youtube.com/embed/6JZ_TWqO6to', 'thumbnail' => 'https://img.youtube.com/vi/6JZ_TWqO6to/mqdefault.jpg', 'description' => 'Gentle exercises to relieve tension headaches'],
    
    // Shoulder Exercises (Not accessible for Head patients)
    ['id' => 3, 'title' => 'Shoulder Pain Relief & Strengthening', 'body_part' => 'Shoulder', 'classification' => 'musculoskeletal', 'duration' => '10 mins', 'difficulty' => 'Intermediate', 'video_url' => 'https://www.youtube.com/embed/gtGErThj41Y', 'thumbnail' => 'https://img.youtube.com/vi/gtGErThj41Y/mqdefault.jpg', 'description' => 'Complete shoulder rehabilitation exercises'],
    
    // Elbow Exercises
    ['id' => 5, 'title' => 'Tennis Elbow Rehabilitation', 'body_part' => 'Elbow', 'classification' => 'mechanical', 'duration' => '7 mins', 'difficulty' => 'Beginner', 'video_url' => 'https://www.youtube.com/embed/7QgELpXRoDk', 'thumbnail' => 'https://img.youtube.com/vi/7QgELpXRoDk/mqdefault.jpg', 'description' => 'Strengthening exercises for tennis elbow'],
    ['id' => 6, 'title' => 'Elbow Flexibility Exercises', 'body_part' => 'Elbow', 'classification' => 'musculoskeletal', 'duration' => '6 mins', 'difficulty' => 'Beginner', 'video_url' => 'https://www.youtube.com/embed/Oh25WNLuSS8', 'thumbnail' => 'https://img.youtube.com/vi/Oh25WNLuSS8/mqdefault.jpg', 'description' => 'Improve elbow range of motion'],
    
    // Wrist Exercises
    ['id' => 7, 'title' => 'Wrist Pain Relief Exercises', 'body_part' => 'Wrist', 'classification' => 'mechanical', 'duration' => '5 mins', 'difficulty' => 'Beginner', 'video_url' => 'https://www.youtube.com/embed/K6P8ueDSS9I', 'thumbnail' => 'https://img.youtube.com/vi/K6P8ueDSS9I/mqdefault.jpg', 'description' => 'Gentle wrist stretches and strengthening'],
    ['id' => 8, 'title' => 'Carpal Tunnel Exercises', 'body_part' => 'Wrist', 'classification' => 'neuropathic', 'duration' => '8 mins', 'difficulty' => 'Beginner', 'video_url' => 'https://www.youtube.com/embed/DLEuGUNoeWg', 'thumbnail' => 'https://img.youtube.com/vi/DLEuGUNoeWg/mqdefault.jpg', 'description' => 'Exercises for carpal tunnel relief'],
    
    // Back Exercises
    ['id' => 9, 'title' => 'Lower Back Pain Relief', 'body_part' => 'Back', 'classification' => 'mechanical', 'duration' => '10 mins', 'difficulty' => 'Beginner', 'video_url' => 'https://www.youtube.com/embed/4BOTvaRaDjI', 'thumbnail' => 'https://img.youtube.com/vi/4BOTvaRaDjI/mqdefault.jpg', 'description' => 'Gentle stretches for lower back pain'],
    ['id' => 10, 'title' => 'Upper Back & Posture Exercises', 'body_part' => 'Back', 'classification' => 'musculoskeletal', 'duration' => '8 mins', 'difficulty' => 'Beginner', 'video_url' => 'https://www.youtube.com/embed/3HYuiOoxmsg', 'thumbnail' => 'https://img.youtube.com/vi/3HYuiOoxmsg/mqdefault.jpg', 'description' => 'Improve posture and reduce upper back pain'],
    ['id' => 11, 'title' => 'Core Strengthening for Back', 'body_part' => 'Back', 'classification' => 'chronic', 'duration' => '15 mins', 'difficulty' => 'Intermediate', 'video_url' => 'https://www.youtube.com/embed/2QRbq7hS9pE', 'thumbnail' => 'https://img.youtube.com/vi/2QRbq7hS9pE/mqdefault.jpg', 'description' => 'Build core strength to support your back'],
    
    // Hip Exercises
    ['id' => 12, 'title' => 'Hip Mobility Exercises', 'body_part' => 'Hip', 'classification' => 'musculoskeletal', 'duration' => '8 mins', 'difficulty' => 'Beginner', 'video_url' => 'https://www.youtube.com/embed/QZpV4U_sBBI', 'thumbnail' => 'https://img.youtube.com/vi/QZpV4U_sBBI/mqdefault.jpg', 'description' => 'Improve hip flexibility and reduce pain'],
    ['id' => 13, 'title' => 'Hip Strengthening Routine', 'body_part' => 'Hip', 'classification' => 'mechanical', 'duration' => '10 mins', 'difficulty' => 'Intermediate', 'video_url' => 'https://www.youtube.com/embed/3uD6z0mpA6k', 'thumbnail' => 'https://img.youtube.com/vi/3uD6z0mpA6k/mqdefault.jpg', 'description' => 'Strengthen hip muscles for better stability'],
    
    // Knee Exercises
    ['id' => 14, 'title' => 'Knee Pain Relief Exercises', 'body_part' => 'Knee', 'classification' => 'mechanical', 'duration' => '8 mins', 'difficulty' => 'Beginner', 'video_url' => 'https://www.youtube.com/embed/7eE6jJc4SJk', 'thumbnail' => 'https://img.youtube.com/vi/7eE6jJc4SJk/mqdefault.jpg', 'description' => 'Gentle exercises to reduce knee pain'],
    ['id' => 15, 'title' => 'Knee Strengthening for Arthritis', 'body_part' => 'Knee', 'classification' => 'chronic', 'duration' => '12 mins', 'difficulty' => 'Beginner', 'video_url' => 'https://www.youtube.com/embed/e2_dqrwaB_M', 'thumbnail' => 'https://img.youtube.com/vi/e2_dqrwaB_M/mqdefault.jpg', 'description' => 'Low-impact exercises for knee arthritis'],
    
    // Ankle Exercises
    ['id' => 16, 'title' => 'Ankle Rehabilitation Exercises', 'body_part' => 'Ankle', 'classification' => 'musculoskeletal', 'duration' => '6 mins', 'difficulty' => 'Beginner', 'video_url' => 'https://www.youtube.com/embed/hg7vCvx6p1M', 'thumbnail' => 'https://img.youtube.com/vi/hg7vCvx6p1M/mqdefault.jpg', 'description' => 'Restore ankle mobility and strength'],
    ['id' => 17, 'title' => 'Ankle Stability & Balance', 'body_part' => 'Ankle', 'classification' => 'mechanical', 'duration' => '7 mins', 'difficulty' => 'Intermediate', 'video_url' => 'https://www.youtube.com/embed/haS1n7LUW7I', 'thumbnail' => 'https://img.youtube.com/vi/haS1n7LUW7I/mqdefault.jpg', 'description' => 'Improve ankle stability and prevent injury'],
    
    // Foot Exercises
    ['id' => 18, 'title' => 'Foot Pain Relief Stretches', 'body_part' => 'Foot', 'classification' => 'musculoskeletal', 'duration' => '5 mins', 'difficulty' => 'Beginner', 'video_url' => 'https://www.youtube.com/embed/5L3rVgVQ5aM', 'thumbnail' => 'https://img.youtube.com/vi/5L3rVgVQ5aM/mqdefault.jpg', 'description' => 'Stretches for plantar fasciitis and foot pain'],
    ['id' => 19, 'title' => 'Foot & Toe Strengthening', 'body_part' => 'Foot', 'classification' => 'mechanical', 'duration' => '6 mins', 'difficulty' => 'Beginner', 'video_url' => 'https://www.youtube.com/embed/sUZTwDPHF9M', 'thumbnail' => 'https://img.youtube.com/vi/sUZTwDPHF9M/mqdefault.jpg', 'description' => 'Strengthen foot muscles and arches'],
];

// Filter exercises based on patient's affected areas only
$filtered_exercises = [];
foreach ($exercise_database as $exercise) {
    // Check if exercise body part matches patient's affected areas
    $exercise_matches_patient = false;
    foreach ($patient_affected_areas as $area) {
        if (strcasecmp(trim($exercise['body_part']), trim($area)) == 0) {
            $exercise_matches_patient = true;
            break;
        }
    }
    
    // Only include exercises that match patient's affected areas
    if ($exercise_matches_patient) {
        // Apply search filter if any
        if (!empty($search)) {
            if (stripos($exercise['title'], $search) !== false || 
                stripos($exercise['body_part'], $search) !== false ||
                stripos($exercise['description'], $search) !== false) {
                $filtered_exercises[] = $exercise;
            }
        } else {
            $filtered_exercises[] = $exercise;
        }
    }
}

// Get unique body parts that patient has access to
$accessible_body_parts = array_unique($patient_affected_areas);
sort($accessible_body_parts);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Exercise Library | OrthoKiosk</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .exercise-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        .exercise-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .exercise-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .exercise-thumbnail {
            width: 100%;
            height: 180px;
            object-fit: cover;
            cursor: pointer;
        }
        .exercise-info {
            padding: 15px;
        }
        .exercise-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c5f8a;
            margin-bottom: 8px;
        }
        .exercise-meta {
            display: flex;
            gap: 15px;
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        .exercise-meta span {
            background: #f0f0f0;
            padding: 3px 8px;
            border-radius: 20px;
        }
        .exercise-description {
            font-size: 0.85rem;
            color: #555;
            margin-bottom: 12px;
            line-height: 1.4;
        }
        .watch-btn {
            display: inline-block;
            background: #2c5f8a;
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: background 0.2s;
            border: none;
            cursor: pointer;
        }
        .watch-btn:hover {
            background: #1a3e5c;
        }
        .unavailable-message {
            text-align: center;
            padding: 60px 40px;
            background: #fff3e0;
            border-radius: 20px;
            margin: 20px 0;
            border: 2px solid #ff9800;
        }
        .unavailable-message h3 {
            color: #e65100;
            margin-bottom: 15px;
            font-size: 1.5rem;
        }
        .unavailable-message .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .search-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .search-input {
            flex: 1;
            padding: 12px 20px;
            border: 2px solid #e0e6ed;
            border-radius: 40px;
            font-size: 1rem;
        }
        .filter-select {
            padding: 12px 20px;
            border: 2px solid #e0e6ed;
            border-radius: 40px;
            font-size: 1rem;
            background: white;
            cursor: pointer;
        }
        .video-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .video-modal.active {
            display: flex;
        }
        .modal-content {
            position: relative;
            width: 90%;
            max-width: 800px;
            background: black;
            border-radius: 16px;
            overflow: hidden;
        }
        .modal-content iframe {
            width: 100%;
            height: 450px;
            border: none;
        }
        .close-modal {
            position: absolute;
            top: -40px;
            right: 0;
            color: white;
            font-size: 30px;
            cursor: pointer;
            background: none;
            border: none;
        }
        .results-count {
            margin: 15px 0;
            color: #666;
            font-size: 0.9rem;
        }
        .affected-areas-badge {
            background: #e8f4ff;
            padding: 10px 15px;
            border-radius: 30px;
            display: inline-block;
            margin: 10px 0;
        }
        .affected-areas-badge span {
            background: #2c5f8a;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            margin: 0 5px;
            display: inline-block;
            font-size: 0.85rem;
        }
        .no-results {
            text-align: center;
            padding: 60px;
            background: #f9f9f9;
            border-radius: 20px;
        }
        @media (max-width: 768px) {
            .exercise-grid {
                grid-template-columns: 1fr;
            }
            .modal-content iframe {
                height: 250px;
            }
        }
    </style>
</head>
<body>
<div class="kiosk-container">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="main-content">
        <!-- Show patient's accessible areas -->
        <div class="card">
            <h2 class="card-title">🎯 Your Accessible Exercises</h2>
            <p>Based on your latest pain assessment (<?= date('d M Y', strtotime($latest_assessment['assessment_date'] ?? 'now')) ?>), you have access to exercises for:</p>
            <div class="affected-areas-badge">
                <?php foreach($patient_affected_areas as $area): ?>
                <span>📍 <?= htmlspecialchars(trim($area)) ?></span>
                <?php endforeach; ?>
            </div>
            <p style="margin-top: 10px; font-size: 0.85rem; color: #666;">
                💡 <strong>Note:</strong> You can only access exercise videos related to your reported pain areas. 
                Exercises for other body parts are not available.
            </p>
        </div>
        
        <!-- Unavailable Message for Restricted Body Parts -->
        <?php if($is_restricted && !empty($selected_body_part)): ?>
        <div class="unavailable-message">
            <div class="icon">🔒</div>
            <h3>Exercise Not Available</h3>
            <p>You are trying to access exercises for <strong><?= htmlspecialchars($selected_body_part) ?></strong>.</p>
            <p>Based on your pain assessment, you only have access to exercises for:</p>
            <div class="affected-areas-badge">
                <?php foreach($patient_affected_areas as $area): ?>
                <span>📍 <?= htmlspecialchars(trim($area)) ?></span>
                <?php endforeach; ?>
            </div>
            <p style="margin-top: 20px; color: #e65100;">
                ⚠️ Exercises for <strong><?= htmlspecialchars($selected_body_part) ?></strong> are not included in your current treatment plan.
            </p>
            <a href="exercise_library.php" class="btn btn-primary" style="margin-top: 20px;">
                ← Back to My Accessible Exercises
            </a>
        </div>
        <?php elseif(empty($filtered_exercises) && !$is_restricted): ?>
        <!-- No exercises found for search -->
        <div class="no-results">
            <h3>📭 No Exercises Found</h3>
            <p>No exercises match your search "<strong><?= htmlspecialchars($search) ?></strong>" for your accessible areas.</p>
            <p>Your accessible areas: <strong><?= implode(', ', $patient_affected_areas) ?></strong></p>
            <p>Try searching for: <?= implode(', ', $accessible_body_parts) ?></p>
            <a href="exercise_library.php" class="btn btn-primary" style="margin-top: 19px;">Clear Search</a>
        </div>
        <?php else: ?>
        
        <!-- Search and Filter Section (only for accessible body parts) -->
        <div class="card">
            <h2 class="card-title">🔍 Search Your Exercises</h2>
            <form method="GET" class="search-bar">
                <input type="text" name="search" class="search-input" placeholder="Search exercises (e.g., neck, head, stretch)..." value="<?= htmlspecialchars($search) ?>">
                <select name="body_part" class="filter-select">
                    <option value="">All Accessible Areas</option>
                    <?php foreach($accessible_body_parts as $part): ?>
                    <option value="<?= htmlspecialchars($part) ?>" <?= $selected_body_part == $part ? 'selected' : '' ?>><?= htmlspecialchars($part) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">🔍 Search</button>
                <?php if($search || $selected_body_part): ?>
                <a href="exercise_library.php" class="btn btn-secondary">Clear Filters</a>
                <?php endif; ?>
            </form>
            
            <div class="results-count">
                Found <?= count($filtered_exercises) ?> exercise(s) for your accessible areas
            </div>
        </div>
        
        <!-- Exercise Grid -->
        <div class="exercise-grid">
            <?php foreach($filtered_exercises as $exercise): ?>
            <div class="exercise-card">
                <img src="<?= $exercise['thumbnail'] ?>" alt="<?= htmlspecialchars($exercise['title']) ?>" class="exercise-thumbnail" onclick="openVideo('<?= $exercise['video_url'] ?>', '<?= htmlspecialchars($exercise['title']) ?>')">
                <div class="exercise-info">
                    <div class="exercise-title"><?= htmlspecialchars($exercise['title']) ?></div>
                    <div class="exercise-meta">
                        <span>📍 <?= $exercise['body_part'] ?></span>
                        <span>⏱️ <?= $exercise['duration'] ?></span>
                        <span>📊 <?= $exercise['difficulty'] ?></span>
                    </div>
                    <div class="exercise-description"><?= htmlspecialchars($exercise['description']) ?></div>
                    <button class="watch-btn" onclick="openVideo('<?= $exercise['video_url'] ?>', '<?= htmlspecialchars($exercise['title']) ?>')">▶ Watch Video</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Tips Section -->
        <div class="card" style="background: #f9f9f9;">
            <h2 class="card-title">💡 Exercise Tips</h2>
            <ul style="margin-left: 20px;">
                <li>Always warm up for 5-10 minutes before exercises</li>
                <li>Listen to your body - stop if you feel sharp pain</li>
                <li>Breathe normally throughout the exercises</li>
                <li>Consistency is key - aim for daily practice</li>
                <li>Consult your physiotherapist before starting new exercises</li>
            </ul>
        </div>
    </div>
</div>

<!-- Video Modal -->
<div id="videoModal" class="video-modal">
    <div class="modal-content">
        <button class="close-modal" onclick="closeVideo()">✕</button>
        <iframe id="videoFrame" src="" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        <div style="padding: 10px; background: white; text-align: center;">
            <strong id="videoTitle"></strong>
        </div>
    </div>
</div>

<script>
    function openVideo(url, title) {
        const modal = document.getElementById('videoModal');
        const frame = document.getElementById('videoFrame');
        const videoTitle = document.getElementById('videoTitle');
        
        frame.src = url;
        videoTitle.textContent = title;
        modal.classList.add('active');
    }
    
    function closeVideo() {
        const modal = document.getElementById('videoModal');
        const frame = document.getElementById('videoFrame');
        
        frame.src = '';
        modal.classList.remove('active');
    }
    
    // Close modal on escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeVideo();
        }
    });
    
    // Close modal on background click
    document.getElementById('videoModal').addEventListener('click', function(event) {
        if (event.target === this) {
            closeVideo();
        }
    });
</script>
</body>
</html>