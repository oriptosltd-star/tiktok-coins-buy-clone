<?php
header('Content-Type: application/json');
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$action = isset($input['action']) ? $input['action'] : '';

try {
    if ($action === 'change_password') {
        $oldPass = $input['old_password'];
        $newPass = $input['new_password'];

        if (!$oldPass || !$newPass) {
            throw new Exception("Missing fields.");
        }

        if (strlen($newPass) < 6) {
            throw new Exception("New password must be at least 6 characters.");
        }

        // Verify Old
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!password_verify($oldPass, $user['password_hash'])) {
            throw new Exception("Incorrect current password.");
        }

        // Update New
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $update->execute([$newHash, $userId]);

        echo json_encode(['success' => true]);

    } elseif ($action === 'generate_2fa') {
        // Simple TOTP Secret Generation (Base32 - stripped down)
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[rand(0, 31)];
        }

        // Return secret and QR URL (using simple chart api or just secret for now)
        // We will stick to Manual Entry + Secret for simplicity without external dependecies
        // Or strictly use a JS library on frontend to generate QR from this secret
        
        echo json_encode(['success' => true, 'secret' => $secret]);

    } elseif ($action === 'enable_2fa') {
        $secret = $input['secret'] ?? '';
        $code = $input['code'] ?? '';

        if (!$secret || !$code) {
            throw new Exception("Missing secret or verification code.");
        }

        require_once 'TOTP.php';
        if (!TOTP::verifyCode($secret, $code)) {
            throw new Exception("Invalid verification code. Please try again.");
        }
        
        $update = $pdo->prepare("UPDATE users SET two_factor_secret = ?, is_2fa_enabled = 1 WHERE id = ?");
        $update->execute([$secret, $userId]);
        
        echo json_encode(['success' => true]);

    } elseif ($action === 'disable_2fa') {
        $update = $pdo->prepare("UPDATE users SET two_factor_secret = NULL, is_2fa_enabled = 0 WHERE id = ?");
        $update->execute([$userId]);
        echo json_encode(['success' => true]);
        
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
