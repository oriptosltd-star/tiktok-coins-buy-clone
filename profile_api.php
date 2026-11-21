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
$action = $_REQUEST['action'] ?? '';

try {
    if ($action === 'upload_avatar') {
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("No file uploaded or upload error.");
        }

        $file = $_FILES['avatar'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception("Invalid file type. Only JPG, PNG, GIF, WEBP allowed.");
        }

        if ($file['size'] > 2 * 1024 * 1024) { // 2MB Limit
            throw new Exception("File too large. Max 2MB.");
        }

        // Create upload dir if not exists
        $uploadDir = 'uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'u' . $userId . '_' . time() . '.' . $ext;
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // Update DB
            $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $stmt->execute([$targetPath, $userId]);
            
            // Update Session
            $_SESSION['avatar'] = $targetPath;

            echo json_encode(['success' => true, 'avatar_url' => $targetPath]);
        } else {
            throw new Exception("Failed to save file.");
        }

    } elseif ($action === 'update_avatar') {
        // Update emoji avatar
        if (!isset($_POST['avatar'])) {
            throw new Exception("No avatar provided.");
        }
        
        $avatar = $_POST['avatar'];
        
        // Update DB
        $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        $stmt->execute([$avatar, $userId]);
        
        // Update Session
        $_SESSION['avatar'] = $avatar;
        
        echo json_encode(['success' => true, 'message' => 'Avatar updated successfully']);

    } elseif ($action === 'get_wallets') {
        $stmt = $pdo->prepare("SELECT * FROM saved_wallets WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        $wallets = $stmt->fetchAll();
        
        // Also get active payout address
        $stmt = $pdo->prepare("SELECT payout_address FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $active = $stmt->fetchColumn();

        echo json_encode(['success' => true, 'wallets' => $wallets, 'active_address' => $active]);

    } elseif ($action === 'add_wallet') {
        $address = trim($_POST['address'] ?? '');
        $label = trim($_POST['label'] ?? 'My Wallet');
        
        if (strlen($address) < 20) throw new Exception("Invalid Wallet Address");

        // Check duplicate
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM saved_wallets WHERE user_id = ? AND address = ?");
        $stmt->execute([$userId, $address]);
        if ($stmt->fetchColumn() > 0) throw new Exception("Wallet already added");

        $stmt = $pdo->prepare("INSERT INTO saved_wallets (user_id, address, label) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $address, $label]);

        // If it's the first wallet, make it active automatically
        $stmt = $pdo->prepare("UPDATE users SET payout_address = ?, payout_method = 'usdt', payout_network = 'trc20' WHERE id = ? AND (payout_address IS NULL OR payout_address = '')");
        $stmt->execute([$address, $userId]);
        
        echo json_encode(['success' => true, 'message' => 'Wallet added successfully']);

    } elseif ($action === 'set_active_wallet') {
        $address = trim($_POST['address'] ?? '');
        if (!$address) throw new Exception("No address provided");

        $stmt = $pdo->prepare("UPDATE users SET payout_address = ?, payout_method = 'usdt' WHERE id = ?");
        $stmt->execute([$address, $userId]);
        
        echo json_encode(['success' => true, 'message' => 'Active wallet updated']);

    } elseif ($action === 'delete_wallet') {
        $id = $_POST['wallet_id'];
        $stmt = $pdo->prepare("DELETE FROM saved_wallets WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        echo json_encode(['success' => true, 'message' => 'Wallet removed']);

    } elseif ($action === 'update_payout') {
        // Legacy support - acts as add_wallet + set_active
        $address = trim($_POST['address'] ?? '');
        if (strlen($address) < 20) throw new Exception("Invalid Wallet Address");
        
        // Update Active
        $stmt = $pdo->prepare("UPDATE users SET payout_address = ?, payout_method = 'usdt', payout_network = 'trc20' WHERE id = ?");
        $stmt->execute([$address, $userId]);

        // Add to saved list if not exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM saved_wallets WHERE user_id = ? AND address = ?");
        $stmt->execute([$userId, $address]);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO saved_wallets (user_id, address, label) VALUES (?, ?, 'Primary Wallet')");
            $stmt->execute([$userId, $address]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Payout details saved']);


    } elseif ($action === 'update_info') {
        // Reserved for username/email updates if needed
        echo json_encode(['error' => 'Not implemented yet']);
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
