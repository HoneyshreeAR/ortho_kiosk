<?php
// payment_verify.php - Real-Time Payment Verification & Settlement Handler
require_once 'config.php';

// Handle Kiosk Instant Simulator Payment (UPI QR or Card POS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'simulate_payment') {
    if (!isset($_SESSION['patient_id'])) {
        header("Location: index.php");
        exit;
    }
    
    $patient_id = (int)$_SESSION['patient_id'];
    $bill_id = (int)($_POST['bill_id'] ?? 0);
    $method = trim($_POST['method'] ?? 'UPI QR');
    
    $stmt = $pdo->prepare("SELECT * FROM bills WHERE id = ? AND patient_id = ? AND paid_status = 'pending'");
    $stmt->execute([$bill_id, $patient_id]);
    $bill = $stmt->fetch();
    
    if (!$bill) {
        $_SESSION['payment_error'] = "Bill not found or already settled.";
        header("Location: billing.php");
        exit;
    }
    
    $prefix = ($method === 'Card POS') ? 'POS' : 'UPI';
    $txn_id = 'TXN_' . $prefix . '_' . date('YmdHis') . '_' . rand(100, 999);
    $cf_order_id = 'ORD_SIM_' . $bill['id'] . '_' . time();
    
    // Update bill
    $update = $pdo->prepare("UPDATE bills SET paid_status = 'paid', cf_order_id = ? WHERE id = ?");
    $update->execute([$cf_order_id, $bill['id']]);
    
    // Record payment
    $insert = $pdo->prepare("INSERT INTO payments (patient_id, bill_id, amount, transaction_id, payment_method, payment_date) VALUES (?, ?, ?, ?, ?, NOW())");
    $insert->execute([$patient_id, $bill['id'], $bill['amount'], $txn_id, $method]);
    
    $_SESSION['last_payment'] = [
        'bill_id' => $bill['id'],
        'amount' => $bill['amount'],
        'description' => $bill['description'],
        'transaction_id' => $txn_id,
        'payment_method' => $method,
        'payment_date' => date('Y-m-d H:i:s'),
    ];
    
    $assessment_query = "";
    if (isset($_SESSION['latest_assessment_id'])) {
        $ass_id = (int)$_SESSION['latest_assessment_id'];
        unset($_SESSION['latest_assessment_id']);
        $_SESSION['last_payment']['assessment_id'] = $ass_id;
        $assessment_query = "&assessment_id=" . $ass_id;
    }
    
    header("Location: payment_success.php?bill_id=" . $bill['id'] . "&order_id=" . urlencode($cf_order_id) . $assessment_query);
    exit;
}

// Cashfree Gateway Callback Verification via GET
$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    header("Location: billing.php");
    exit;
}

// Find bill by cf_order_id
$stmt = $pdo->prepare("SELECT * FROM bills WHERE cf_order_id = ? LIMIT 1");
$stmt->execute([$order_id]);
$bill = $stmt->fetch();

if (!$bill) {
    // If order_id pattern has bill_id like ORD_5_timestamp
    if (preg_match('/^ORD_(\d+)_/', $order_id, $matches)) {
        $bill_id_guess = (int)$matches[1];
        $stmt2 = $pdo->prepare("SELECT * FROM bills WHERE id = ? LIMIT 1");
        $stmt2->execute([$bill_id_guess]);
        $bill = $stmt2->fetch();
    }
}

if (!$bill) {
    die("Error: Order reference " . htmlspecialchars($order_id) . " could not be found.");
}

// Restore session if lost during external gateway redirect
if (!isset($_SESSION['patient_id'])) {
    $_SESSION['patient_id'] = $bill['patient_id'];
}
$patient_id = $_SESSION['patient_id'];

// Query Cashfree API to verify payment
function cashfree_verify_order($order_id) {
    $ch = curl_init(CASHFREE_BASE_URL . "/orders/" . rawurlencode($order_id));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "x-api-version: " . CASHFREE_API_VERSION,
            "x-client-id: " . CASHFREE_APP_ID,
            "x-client-secret: " . CASHFREE_SECRET_KEY,
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || empty($response)) {
        return null;
    }
    return json_decode($response, true);
}

$order = cashfree_verify_order($order_id);

if (!$order) {
    // Could not verify with Cashfree API, check if order is already paid locally
    if ($bill['paid_status'] === 'paid') {
        header("Location: payment_success.php?bill_id=" . $bill['id'] . "&order_id=" . urlencode($order_id));
        exit;
    }
    die("Could not communicate with payment gateway. Please contact the clinic reception with Order ID: " . htmlspecialchars($order_id));
}

// Check if bill is already settled locally
if ($bill['paid_status'] === 'paid') {
    header("Location: payment_success.php?bill_id=" . $bill['id'] . "&order_id=" . urlencode($order_id));
    exit;
}

// Check Cashfree order status (PAID, ACTIVE, EXPIRED, TERMINATED)
if ($order['order_status'] === 'PAID') {
    $txn_id = $order['cf_order_id'] ?? $order_id;
    
    if ($bill['paid_status'] !== 'paid') {
        // Mark bill as paid
        $update = $pdo->prepare("UPDATE bills SET paid_status = 'paid' WHERE id = ?");
        $update->execute([$bill['id']]);

        // Insert into payments table
        $insert = $pdo->prepare("INSERT INTO payments (patient_id, bill_id, amount, transaction_id, payment_method, payment_date) VALUES (?, ?, ?, ?, 'Cashfree PG', NOW())");
        $insert->execute([$patient_id, $bill['id'], $bill['amount'], $txn_id]);
    }

    $_SESSION['last_payment'] = [
        'bill_id' => $bill['id'],
        'amount' => $bill['amount'],
        'description' => $bill['description'],
        'transaction_id' => $txn_id,
        'payment_method' => 'Cashfree PG (Online UPI/Cards)',
        'payment_date' => date('Y-m-d H:i:s'),
    ];

    $assessment_query = "";
    if (isset($_SESSION['latest_assessment_id'])) {
        $assessment_id = (int)$_SESSION['latest_assessment_id'];
        unset($_SESSION['latest_assessment_id']);
        $_SESSION['last_payment']['assessment_id'] = $assessment_id;
        $assessment_query = "&assessment_id=" . $assessment_id;
    }

    header("Location: payment_success.php?bill_id=" . $bill['id'] . "&order_id=" . urlencode($order_id) . $assessment_query);
    exit;
}

// Payment was not completed or failed
header("Location: payment_failed.php?order_id=" . urlencode($order_id) . "&status=" . urlencode($order['order_status'] ?? 'FAILED'));
exit;
?>
