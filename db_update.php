<?php
require_once 'db_connect.php';

try {
    // Add reset_token column
    $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) NULL");
    echo "Added reset_token column.<br>";
} catch (PDOException $e) {
    echo "reset_token column likely exists or error: " . $e->getMessage() . "<br>";
}

try {
    // Add reset_expires column
    $pdo->exec("ALTER TABLE users ADD COLUMN reset_expires DATETIME NULL");
    echo "Added reset_expires column.<br>";
} catch (PDOException $e) {
    echo "reset_expires column likely exists or error: " . $e->getMessage() . "<br>";
}

echo "Database update complete.";
?>
