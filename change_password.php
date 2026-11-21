<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$oldPass = $input['old_password'] ?? '';
$newPass = $input['new_password'] ?? '';

if (empty($oldPass) || empty($newPass)) {
    echo json_encode(['error' => 'All fields are required']);
    exit;
}

if (strlen($newPass) < 6) {
    echo json_encode(['error' => 'New password must be at least 6 characters']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($oldPass, $user['password_hash'])) {
        echo json_encode(['error' => 'Incorrect old password']);
        exit;
    }

    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
    
    $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $update->execute([$newHash, $_SESSION['user_id']]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
