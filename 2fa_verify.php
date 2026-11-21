<?php
header('Content-Type: application/json');
session_start();
require_once 'db_connect.php';
require_once 'TOTP.php';

$input = json_decode(file_get_contents('php://input'), true);
$code = $input['code'] ?? '';

if (!isset($_SESSION['pending_user'])) {
    echo json_encode(['success' => false, 'error' => 'Session expired. Please login again.']);
    exit;
}

$userData = $_SESSION['pending_user'];
$userId = $userData['id'];

try {
    // Fetch latest secret from DB
    $stmt = $pdo->prepare("SELECT two_factor_secret, is_2fa_enabled FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !$user['is_2fa_enabled']) {
         echo json_encode(['success' => false, 'error' => '2FA not enabled for this account.']);
         exit;
    }

    if (TOTP::verifyCode($user['two_factor_secret'], $code)) {
        // Success: Promote to full session
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $userData['username'];
        $_SESSION['ref_code'] = $userData['referral_code'];
        unset($_SESSION['pending_user']);
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid verification code.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error.']);
}
?>
