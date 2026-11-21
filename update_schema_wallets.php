<?php
require_once 'db_connect.php';
try {
    $sql = file_get_contents('wallets_schema.sql');
    $pdo->exec($sql);
    echo "Wallets schema updated successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
