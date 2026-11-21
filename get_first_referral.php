<?php
/**
 * Generate First Referral Code
 * Creates a referral code for the admin user
 */

require_once 'db_connect.php';

$adminEmail = 'saeedzulfiqar242@gmail.com';

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, email, referral_code FROM users WHERE email = ?");
    $stmt->execute([$adminEmail]);
    $user = $stmt->fetch();
    
    if ($user) {
        // User exists
        if (!empty($user['referral_code'])) {
            // Already has referral code
            echo "✅ Referral code already exists!\n\n";
            echo "Email: " . $user['email'] . "\n";
            echo "Referral Code: " . $user['referral_code'] . "\n\n";
            echo "🔗 Full Referral URL:\n";
            echo "https://cheaper360.store?ref=" . $user['referral_code'] . "\n\n";
            echo "📋 Share this URL with others to give them access!\n";
        } else {
            // Generate new referral code
            $refCode = strtoupper(substr(md5($user['id'] . time()), 0, 8));
            
            $stmt = $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
            $stmt->execute([$refCode, $user['id']]);
            
            echo "✅ New referral code generated!\n\n";
            echo "Email: " . $user['email'] . "\n";
            echo "Referral Code: " . $refCode . "\n\n";
            echo "🔗 Full Referral URL:\n";
            echo "https://cheaper360.store?ref=" . $refCode . "\n\n";
            echo "📋 Share this URL with others to give them access!\n";
        }
    } else {
        // User doesn't exist - create admin user
        echo "⚠️ User not found. Creating admin account...\n\n";
        
        $username = 'admin';
        $password = 'Admin@123'; // Change this!
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $refCode = strtoupper(substr(md5($adminEmail . time()), 0, 8));
        
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, referral_code) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $adminEmail, $passwordHash, $refCode]);
        
        echo "✅ Admin account created!\n\n";
        echo "Email: " . $adminEmail . "\n";
        echo "Username: " . $username . "\n";
        echo "Password: " . $password . " (CHANGE THIS!)\n";
        echo "Referral Code: " . $refCode . "\n\n";
        echo "🔗 Full Referral URL:\n";
        echo "https://cheaper360.store?ref=" . $refCode . "\n\n";
        echo "📋 Share this URL with others to give them access!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
