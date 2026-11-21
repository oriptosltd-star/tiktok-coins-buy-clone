<?php
/**
 * Add First Referral Code to Database
 * This creates the initial admin referral code
 */

require_once 'db_connect.php';

$adminEmail = 'saeedzulfiqar242@gmail.com';
$refCode = 'ADMIN2024';

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, email, referral_code FROM users WHERE email = ?");
    $stmt->execute([$adminEmail]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Update existing user
        $stmt = $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
        $stmt->execute([$refCode, $user['id']]);
        
        echo "✅ Referral code updated!<br><br>";
        echo "Email: " . $user['email'] . "<br>";
        echo "Referral Code: <strong>" . $refCode . "</strong><br><br>";
        echo "🔗 <strong>Full Referral URL:</strong><br>";
        echo '<a href="https://cheaper360.store?ref=' . $refCode . '" target="_blank">';
        echo "https://cheaper360.store?ref=" . $refCode;
        echo "</a><br><br>";
        echo "✅ <strong>Test it now!</strong> Click the link above to access full site!";
    } else {
        // Create new admin user
        $username = 'admin';
        $password = 'Admin@2024!';
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, referral_code) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $adminEmail, $passwordHash, $refCode]);
        
        echo "✅ Admin account created!<br><br>";
        echo "Email: " . $adminEmail . "<br>";
        echo "Username: " . $username . "<br>";
        echo "Password: <strong>" . $password . "</strong> (Save this!)<br>";
        echo "Referral Code: <strong>" . $refCode . "</strong><br><br>";
        echo "🔗 <strong>Full Referral URL:</strong><br>";
        echo '<a href="https://cheaper360.store?ref=' . $refCode . '" target="_blank">';
        echo "https://cheaper360.store?ref=" . $refCode;
        echo "</a><br><br>";
        echo "✅ <strong>Test it now!</strong> Click the link above to access full site!";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
