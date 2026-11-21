<?php
header('Content-Type: application/json');

$config = require 'config.php';
$stripeSecret = $config['stripe']['secret_key'];

require_once 'db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$productId = $input['product_id'] ?? '';
$referralCode = trim($input['referral_code'] ?? ''); 
$customCoins = intval($input['custom_coins'] ?? 0);

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
    if ($customCoins < 100) {
        http_response_code(400);
        echo json_encode(['error' => 'Minimum 100 coins required']);
        exit;
    }
    $amount = $customCoins * 0.004;
    $productName = "$customCoins Coins (Custom)";
} elseif (isset($products[$productId])) {
    $product = $products[$productId];
    $amount = $product['price'];
    $productName = "{$product['coins']} Coins Bundle";
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid Product ID']);
    exit;
}

if (!empty($referralCode)) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
    $stmt->execute([$referralCode]);
    if ($stmt->fetch()) {
        $discount = $amount * 0.05;
        $amount = $amount - $discount;
        $productName .= ' (5% Off)';
    }
}

$orderId = 'ORD-' . time() . '-' . rand(1000,9999);
if (!empty($referralCode)) {
    $orderId = $orderId . '-REF-' . $referralCode;
}

$url = 'https://api.stripe.com/v1/payment_intents';

$data = http_build_query([
    'amount' => intval(round($amount * 100)),
    'currency' => 'usd',
    'automatic_payment_methods' => ['enabled' => 'true'],
    'metadata' => [
        'order_id' => $orderId,
        'product_name' => $productName
    ]
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, preg_replace('/%5B[0-9]+%5D/simU', '%5B%5D', $data)); // fix http_build_query array formatting for stripe
curl_setopt($ch, CURLOPT_USERPWD, $stripeSecret . ':');

$result = curl_exec($ch);
if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(['error' => 'Curl Error: ' . curl_error($ch)]);
} else {
    $response = json_decode($result, true);
    if (isset($response['client_secret'])) {
        echo json_encode(['client_secret' => $response['client_secret']]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Stripe Error: ' . ($response['error']['message'] ?? 'Unknown error')]);
    }
}
curl_close($ch);
?>
