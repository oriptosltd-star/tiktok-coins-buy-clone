<?php
include 'db_connect.php';

echo "<h1>Updating Schema for Profile & Security</h1>";

// 1. Add 'avatar' column
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL");
    echo "<p style='color:green'>[SUCCESS] Added 'avatar' column.</p>";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column") !== false) {
        echo "<p style='color:orange'>[INFO] 'avatar' column already exists.</p>";
    } else {
        echo "<p style='color:red'>[FAIL] Error adding 'avatar': " . $e->getMessage() . "</p>";
    }
}

// 2. Add 'two_factor_secret' column
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_secret VARCHAR(255) DEFAULT NULL");
    echo "<p style='color:green'>[SUCCESS] Added 'two_factor_secret' column.</p>";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column") !== false) {
        echo "<p style='color:orange'>[INFO] 'two_factor_secret' column already exists.</p>";
    } else {
        echo "<p style='color:red'>[FAIL] Error adding 'two_factor_secret': " . $e->getMessage() . "</p>";
    }
}

// 3. Add 'is_2fa_enabled' column
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN is_2fa_enabled TINYINT(1) DEFAULT 0");
    echo "<p style='color:green'>[SUCCESS] Added 'is_2fa_enabled' column.</p>";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column") !== false) {
        echo "<p style='color:orange'>[INFO] 'is_2fa_enabled' column already exists.</p>";
    } else {
        echo "<p style='color:red'>[FAIL] Error adding 'is_2fa_enabled': " . $e->getMessage() . "</p>";
    }
}

echo "<h3>Database Update Complete.</h3>";
?>
