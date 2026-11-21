<?php
require_once 'db_connect.php';
header('Content-Type: text/plain');

try {
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'saved_wallets'");
    if ($stmt->rowCount() > 0) {
        echo "Table 'saved_wallets' EXISTS.\n";
        
        // Describe table
        $stmt = $pdo->query("DESCRIBE saved_wallets");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        print_r($columns);

        // Try select
        $stmt = $pdo->query("SELECT * FROM saved_wallets LIMIT 1");
        echo "Select Check: Success (Rows: " . $stmt->rowCount() . ")\n";

    } else {
        echo "Table 'saved_wallets' DOES NOT EXIST.\n";
    }
} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage();
}
?>
