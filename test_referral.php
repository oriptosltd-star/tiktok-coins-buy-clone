<?php
/**
 * Test Referral Code System
 * This will show you what's happening
 */

require_once 'db_connect.php';

echo "<h2>🔍 Referral System Debug</h2>";

// Test 1: Check if ADMIN2024 exists
echo "<h3>Test 1: Check Database</h3>";
$stmt = $pdo->prepare("SELECT id, email, username, referral_code FROM users WHERE referral_code = ?");
$stmt->execute(['ADMIN2024']);
$user = $stmt->fetch();

if ($user) {
    echo "✅ <strong>ADMIN2024</strong> exists in database!<br>";
    echo "User ID: " . $user['id'] . "<br>";
    echo "Email: " . $user['email'] . "<br>";
    echo "Username: " . $user['username'] . "<br><br>";
} else {
    echo "❌ <strong>ADMIN2024</strong> NOT found in database!<br>";
    echo "You need to run: <a href='add_first_referral.php'>add_first_referral.php</a><br><br>";
}

// Test 2: Check all users with referral codes
echo "<h3>Test 2: All Users with Referral Codes</h3>";
$stmt = $pdo->query("SELECT id, email, username, referral_code FROM users WHERE referral_code IS NOT NULL AND referral_code != ''");
$users = $stmt->fetchAll();

if (count($users) > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Email</th><th>Username</th><th>Referral Code</th><th>URL</th></tr>";
    foreach ($users as $u) {
        echo "<tr>";
        echo "<td>" . $u['id'] . "</td>";
        echo "<td>" . $u['email'] . "</td>";
        echo "<td>" . $u['username'] . "</td>";
        echo "<td><strong>" . $u['referral_code'] . "</strong></td>";
        echo "<td><a href='https://cheaper360.store?ref=" . $u['referral_code'] . "' target='_blank'>Test Link</a></td>";
        echo "</tr>";
    }
    echo "</table><br>";
} else {
    echo "❌ No users with referral codes found!<br><br>";
}

// Test 3: Test check_referral.php API
echo "<h3>Test 3: API Test</h3>";
echo "Testing: <code>check_referral.php?code=ADMIN2024</code><br>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://cheaper360.store/check_referral.php?code=ADMIN2024");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

echo "API Response: <code>" . htmlspecialchars($response) . "</code><br>";

$data = json_decode($response, true);
if ($data && isset($data['valid'])) {
    if ($data['valid'] === true) {
        echo "✅ API says code is <strong>VALID</strong>!<br>";
    } else {
        echo "❌ API says code is <strong>INVALID</strong>!<br>";
    }
} else {
    echo "❌ API returned invalid response!<br>";
}

echo "<br><hr><br>";
echo "<h3>📋 Quick Actions</h3>";
echo "<a href='add_first_referral.php' style='padding: 10px 20px; background: #FE2C55; color: white; text-decoration: none; border-radius: 5px;'>Add ADMIN2024 to Database</a><br><br>";
echo "<a href='https://cheaper360.store?ref=ADMIN2024' style='padding: 10px 20px; background: #25F4EE; color: black; text-decoration: none; border-radius: 5px;'>Test Referral URL</a>";
?>
