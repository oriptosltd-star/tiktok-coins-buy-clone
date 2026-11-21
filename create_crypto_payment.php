<?php
header('Content-Type: application/json');

// CONFIGURATION
$config = require 'config.php';
$apiKey = $config['nowpayments']['api_key'];
$ipnSecret = $config['nowpayments']['ipn_secret'];

// Include DB Connection
require_once 'db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$productId = $input['product_id'] ?? '';
$referralCode = trim($input['referral_code'] ?? ''); 
$customCoins = intval($input['custom_coins'] ?? 0);

// Server-Side Pricing Definition
$products = [
    'pri_01kfvrmpyx86ckcz4bp6k2nfdd' => ['coins' => 10000, 'price' => 40.00],
    'pri_01kfvrjv0y5tg33ceys8gg0z1n' => ['coins' => 20000, 'price' => 80.00],
    'pri_01kfvrpmzq1t01az6m6pwbkjpf' => ['coins' => 30000, 'price' => 120.00],
    'pri_01kfvrr1jac41awqnc77cg0c7y' => ['coins' => 40000, 'price' => 160.00],
    'pri_01kfvrscrr6pvg3g8df8zhr101' => ['coins' => 50000, 'price' => 200.00],
    'pri_01kfvrtqvzbnm4g6f0ybbbrrfs' => ['coins' => 100000, 'price' => 400.00],
    'pri_01kfvrvwcjyr28f5p8yme5qedc' => ['coins' => 200000, 'price' => 800.00]
];

$amount = 0;
$productName = '';

if ($productId === 'custom_amount') {
    // Custom Logic: $0.004 per coin
    if ($customCoins < 100) {
        http_response_code(400);
        echo json_encode(['error' => 'Minimum 100 coins required']);
        exit;
    }
    $amount = $customCoins * 0.004;
    $productName = "$customCoins Coins (Custom)";
} elseif (isset($products[$productId])) {
    // Fixed Bundle Logic
    $product = $products[$productId];
    $amount = $product['price'];
    $productName = "{$product['coins']} Coins Bundle";
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid Product ID']);
    exit;
}

// 5% Discount Logic
if (!empty($referralCode)) {
    // Validate Referral Code against DB
    $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
    $stmt->execute([$referralCode]);
    if ($stmt->fetch()) {
        $discount = $amount * 0.05;
        $amount = $amount - $discount;
        $productName .= ' (5% Off Applied)';
    }
}

// URL for Invoice API (Hosted Checkout)
$url = 'https://api.nowpayments.io/v1/invoice';

$orderId = 'ORD-' . time() . '-' . rand(1000,9999);

// Add Referral Code to Order ID if present
if (!empty($referralCode)) {
    $orderId = $orderId . '-REF-' . $referralCode;
}

$payload = [
    'price_amount' => $amount,
    'price_currency' => 'usd',
    // 'pay_currency' => 'btc', // Optional: Leave empty to let user choose on hosted page
    'ipn_callback_url' => 'https://cheaper360.store/ipn_nowpayments.php',
    'order_id' => $orderId,
    'order_description' => $productName,
    'success_url' => 'https://cheaper360.store/app.html?payment=success',
    'cancel_url' => 'https://cheaper360.store/app.html'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'x-api-key: ' . $apiKey,
    'Content-Type: application/json'
]);

$result = curl_exec($ch);
if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(['error' => 'Curl Error: ' . curl_error($ch)]);
} else {
    $response = json_decode($result, true);
    
    // Check for invoice_url in response
    if (isset($response['invoice_url'])) {
        echo json_encode(['invoice_url' => $response['invoice_url']]);
    } else {
        // Log error if needed or return full response for debug
        // For security, maybe just 'error' but during dev we return result
        echo $result;
    }
}
curl_close($ch);
?>
