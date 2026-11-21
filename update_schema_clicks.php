<?php
require_once 'db_connect.php';

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS clicks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        referral_code VARCHAR(10),
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (referral_code)
    )";

    $pdo->exec($sql);
    echo "Table 'clicks' created successfully (or already exists).";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>
