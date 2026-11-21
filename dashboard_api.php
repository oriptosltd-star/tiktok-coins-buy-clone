<?php
session_start();
require_once 'db_connect.php';

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$refCode = $_SESSION['ref_code'];

try {
    // Fetch Wallet Balance
    $stmt = $pdo->prepare("SELECT wallet_balance, username, email, avatar, payout_address, payout_method, payout_network FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    // Fetch Referrals Stats
    // Assuming 'referrals' table structure from plan
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_referrals,
            SUM(commission_amount) as total_earnings
        FROM referrals 
        WHERE referrer_id = ? AND status = 'completed'
    ");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch();

    // Fetch Realtime Clicks
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clicks WHERE referral_code = ?");
    $stmt->execute([$refCode]);
    $clickCount = $stmt->fetchColumn();

    // Fetch Recent Transactions
    $stmt = $pdo->prepare("
        SELECT created_at as date, buyer_identifier as user, amount_total as amount, commission_amount as comm, status
        FROM referrals 
        WHERE referrer_id = ?
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $transactions = $stmt->fetchAll();

    echo json_encode([
        'user' => [
            'username' => $user['username'],
            'email' => $user['email'],
            'avatar' => $user['avatar'],
            'code' => $refCode,
            'payout' => [
                'address' => $user['payout_address'],
                'method' => $user['payout_method'],
                'network' => $user['payout_network']
            ]
        ],
        'balance' => floatval($user['wallet_balance']),
        'stats' => [
            'clicks' => intval($clickCount), 
            'referrals' => intval($stats['total_referrals']),
            'total_earnings' => floatval($stats['total_earnings'])
        ],
        'transactions' => $transactions
    ]);

} catch (Exception $e) {
    http_response_code(500);
    $errorMsg = "API Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
    file_put_contents('server_error.log', date('[Y-m-d H:i:s] ') . $errorMsg . "\n", FILE_APPEND);
    echo json_encode(['error' => $errorMsg]);
}
?>
