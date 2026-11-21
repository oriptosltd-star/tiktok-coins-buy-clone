<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

$code = $_GET['code'] ?? '';

if (empty($code)) {
    echo json_encode(['valid' => false]);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
$stmt->execute([$code]);
$user = $stmt->fetch();

echo json_encode(['valid' => (bool)$user]);
?>
