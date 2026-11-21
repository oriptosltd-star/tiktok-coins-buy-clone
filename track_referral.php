<?php
// Save Referral Code to Cookie
if (isset($_GET['ref'])) {
    $code = htmlspecialchars($_GET['ref']); // Sanitize
    // Set Cookie for 30 Days
    setcookie('ref_code', $code, time() + (86400 * 30), "/"); 
    
    // Log Click to Database
    try {
        require_once 'db_connect.php';
        $ip = $_SERVER['REMOTE_ADDR'];
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt = $pdo->prepare("INSERT INTO clicks (referral_code, ip_address, user_agent) VALUES (?, ?, ?)");
        $stmt->execute([$code, $ip, $ua]);
    } catch (Exception $e) {
        // Silent fail for tracking to not disrupt user flow
    }
}
?>
