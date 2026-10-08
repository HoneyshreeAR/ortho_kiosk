<?php
// payment_failed.php
require_once 'config.php';

if (!isset($_SESSION['patient_id'])) {
    header("Location: index.php");
    exit;
}

$order_id = $_GET['order_id'] ?? '';
$status = $_GET['status'] ?? 'UNKNOWN';

$friendly_message = [
    'ACTIVE' => "It looks like the payment wasn't completed.",
    'EXPIRED' => "The payment session expired before it was completed.",
    'TERMINATED' => "The payment was cancelled.",
    'TERMINATION_REQUESTED' => "The payment was cancelled.",
][$status] ?? "The payment could not be completed.";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Failed | OrthoKiosk</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .fail-container { max-width: 600px; margin: 60px auto; background: white; border-radius: 24px; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); text-align: center; }
        .fail-icon { font-size: 3rem; }
        .fail-container h1 { color: #b71c1c; margin-top: 10px; }
        .fail-container p { color: #555; margin: 15px 0; }
        .retry-btn { background: #28a745; color: white; padding: 14px 30px; border: none; border-radius: 40px; font-size: 1.1rem; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 15px; }
        .retry-btn:hover { background: #218838; }
        .order-ref { font-size: 0.8rem; color: #999; margin-top: 20px; }
    </style>
</head>
<body>
<div class="kiosk-container">
    <div class="main-content">
        <div class="fail-container">
            <div class="fail-icon">⚠️</div>
            <h1>Payment Not Completed</h1>
            <p><?= htmlspecialchars($friendly_message) ?></p>
            <p>No amount has been charged. You can try again below.</p>
            <a href="payment_section.php" class="retry-btn">🔄 Try Payment Again</a>
            <?php if ($order_id): ?>
                <div class="order-ref">Reference: <?= htmlspecialchars($order_id) ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>