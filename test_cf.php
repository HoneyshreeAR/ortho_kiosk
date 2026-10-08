<?php
require_once 'config.php';
$payload = [
    "order_id" => "TEST_" . time(),
    "order_amount" => 500.0,
    "order_currency" => "INR",
    "customer_details" => [
        "customer_id" => "cust_1",
        "customer_name" => "Rahul Sharma",
        "customer_email" => "rahul@example.com",
        "customer_phone" => "9876543210"
    ]
];
$ch = curl_init(CASHFREE_BASE_URL . "/orders");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "x-api-version: " . CASHFREE_API_VERSION,
        "x-client-id: " . CASHFREE_APP_ID,
        "x-client-secret: " . CASHFREE_SECRET_KEY,
    ],
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false
]);
$res = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
echo "HTTP Code: " . $http . "\n";
echo "Response: " . $res . "\n";
if ($err) echo "Curl Error: " . $err . "\n";
?>
