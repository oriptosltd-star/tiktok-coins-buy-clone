<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$amount = floatval($input['amount'] ?? 0);
$method = trim($input['method'] ?? '');
$details = trim($input['details'] ?? '');

// Validation
if ($amount < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Minimum withdrawal amount is $1.00']);
    exit;
}

if (empty($method) || empty($details)) {
    http_response_code(400);
    echo json_encode(['error' => 'Payment method and details are required.']);
    exit;
}

    try {
    $pdo->beginTransaction();

    // 1. Check Balance AND Payout Details (Locking row for update)
    $stmt = $pdo->prepare("SELECT wallet_balance, payout_address FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
         throw new Exception("User not found");
    }

    if (empty($user['payout_address'])) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['error' => 'No payout account connected. Please update Payment Settings first.']);
        exit;
    }

    if ($user['wallet_balance'] < $amount) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['error' => 'Insufficient balance.']);
        exit;
    }

    // 2. Deduct Balance
    $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
    $stmt->execute([$amount, $userId]);

    // 3. Create Withdrawal Record
    // Use stored details
    $method = 'USDT (TRC20)';
    $details = $user['payout_address'];
    
    $stmt = $pdo->prepare("INSERT INTO withdrawals (user_id, amount, payment_method, payment_details, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->execute([$userId, $amount, $method, $details]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Withdrawal request submitted successfully.']);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'details' => $e->getMessage()]);
}
?>
