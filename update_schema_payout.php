<?php
require_once 'db_connect.php';

try {
    $sql = file_get_contents('payout_schema.sql');
    $pdo->exec($sql);
    echo "Payout schema updated successfully!";
} catch (PDOException $e) {
    echo "Error updating schema: " . $e->getMessage();
}
?>
