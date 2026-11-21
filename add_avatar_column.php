<?php
// Add avatar column to users table
include 'db_connect.php';

try {
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'avatar'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        // Add avatar column
        $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT '👤'");
        echo "✅ Avatar column added successfully!<br>";
        echo "Default avatar set to: 👤<br>";
    } else {
        echo "ℹ️ Avatar column already exists!<br>";
    }
    
    // Show current users
    $stmt = $pdo->query("SELECT id, username, email, avatar FROM users LIMIT 5");
    echo "<br><strong>Sample Users:</strong><br>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Avatar</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['username']}</td>";
        echo "<td>{$row['email']}</td>";
        echo "<td style='font-size:24px'>{$row['avatar']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><br>✅ <strong>Migration Complete!</strong>";
    echo "<br><a href='dashboard.html'>Go to Dashboard</a>";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
