<?php
require 'config.php';

$ch = curl_init(CASHFREE_BASE_URL . "/orders");
$payload = [
    "order_id" => "TEST_ORD_" . time(),
    "order_amount" => 1.00,
    "order_currency" => "INR",
    "customer_details" => [
        "customer_id" => "test_cust_1",
        "customer_name" => "Test Patient",
        "customer_email" => "test@orthokiosk.com",
        "customer_phone" => "9876543210"
    ]
];

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
    CURLOPT_TIMEOUT => 12,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "cURL Error: $err\n";
echo "Response: $response\n";
