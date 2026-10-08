<?php
// api_payment_status.php - Real-Time Payment Status Polling API for Cashfree & Kiosk
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

$order_id = $_GET['order_id'] ?? $_POST['order_id'] ?? '';

if (empty($order_id)) {
    echo json_encode(['success' => false, 'error' => 'Order ID required', 'paid' => false]);
    exit;
}

// 1. First check if bill is already marked paid locally in MySQL
$stmt = $pdo->prepare("SELECT b.*, p.transaction_id, p.payment_method 
    FROM bills b 
    LEFT JOIN payments p ON b.id = p.bill_id 
    WHERE b.cf_order_id = ? LIMIT 1");
$stmt->execute([$order_id]);
$bill = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bill) {
    // If order_id pattern is ORD_{bill_id}_{timestamp}
    if (preg_match('/^ORD_(\d+)_/', $order_id, $matches)) {
        $bill_id_guess = (int)$matches[1];
        $stmt2 = $pdo->prepare("SELECT b.*, p.transaction_id, p.payment_method 
            FROM bills b 
            LEFT JOIN payments p ON b.id = p.bill_id 
            WHERE b.id = ? LIMIT 1");
        $stmt2->execute([$bill_id_guess]);
        $bill = $stmt2->fetch(PDO::FETCH_ASSOC);
    }
}

if ($bill && $bill['paid_status'] === 'paid') {
    echo json_encode([
        'success' => true,
        'status' => 'PAID',
        'paid' => true,
        'bill_id' => $bill['id'],
        'amount' => $bill['amount'],
        'transaction_id' => $bill['transaction_id'] ?? $order_id,
        'payment_method' => $bill['payment_method'] ?? 'UPI QR (Cashfree)'
    ]);
    exit;
}

// 2. Query Cashfree API to get live order status
function check_cashfree_order_live($order_id) {
    $ch = curl_init(CASHFREE_BASE_URL . "/orders/" . rawurlencode($order_id));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "x-api-version: " . CASHFREE_API_VERSION,
            "x-client-id: " . CASHFREE_APP_ID,
            "x-client-secret: " . CASHFREE_SECRET_KEY,
        ],
        CURLOPT_TIMEOUT => 8,
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

$cf_order = check_cashfree_order_live($order_id);

if (!$cf_order) {
    echo json_encode([
        'success' => false,
        'status' => 'CHECKING',
        'paid' => false,
        'message' => 'Waiting for gateway response...'
    ]);
    exit;
}

$status = $cf_order['order_status'] ?? 'ACTIVE';

// 3. If Cashfree reports PAID, settle bill in MySQL immediately
if ($status === 'PAID') {
    $txn_id = $cf_order['cf_order_id'] ?? $order_id;
    $patient_id = $bill ? $bill['patient_id'] : ($_SESSION['patient_id'] ?? 0);
    $bill_id = $bill ? $bill['id'] : 0;
    $amount = $bill ? $bill['amount'] : ($cf_order['order_amount'] ?? 0);

    if ($bill && $bill['paid_status'] !== 'paid') {
        $upd = $pdo->prepare("UPDATE bills SET paid_status = 'paid', cf_order_id = ? WHERE id = ?");
        $upd->execute([$order_id, $bill['id']]);

        $ins = $pdo->prepare("INSERT INTO payments (patient_id, bill_id, amount, transaction_id, payment_method, payment_date) VALUES (?, ?, ?, ?, 'Cashfree UPI QR', NOW())");
        $ins->execute([$patient_id, $bill['id'], $amount, $txn_id]);
    }

    if (isset($_SESSION)) {
        $_SESSION['last_payment'] = [
            'bill_id' => $bill_id,
            'amount' => $amount,
            'description' => $bill['description'] ?? 'Orthopaedic Consultation',
            'transaction_id' => $txn_id,
            'payment_method' => 'Cashfree UPI QR',
            'payment_date' => date('Y-m-d H:i:s'),
        ];
    }

    echo json_encode([
        'success' => true,
        'status' => 'PAID',
        'paid' => true,
        'bill_id' => $bill_id,
        'amount' => $amount,
        'transaction_id' => $txn_id,
        'redirect_url' => "payment_success.php?bill_id={$bill_id}&order_id=" . urlencode($order_id)
    ]);
    exit;
}

// Order is still active, waiting for patient to scan and approve in UPI app
echo json_encode([
    'success' => true,
    'status' => $status,
    'paid' => false
]);
