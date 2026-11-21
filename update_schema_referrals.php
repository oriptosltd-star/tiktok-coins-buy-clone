<?php
require_once 'db_connect.php';

try {
    // Add referred_by column if not exists
    $pdo->exec("
        ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS referred_by INT DEFAULT NULL,
        ADD INDEX (referred_by);
    ");
    echo "Column 'referred_by' added successfully.<br>";

    // Update referrals table if needed to track type (optional, but good for reporting)
    // We can use the 'status' or add a new column 'type'
    $pdo->exec("
        ALTER TABLE referrals 
        ADD COLUMN IF NOT EXISTS type ENUM('direct', 'tier_2') DEFAULT 'direct';
    ");
    echo "Column 'type' added to referrals.<br>";

    echo "Schema Updated Successfully.";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
