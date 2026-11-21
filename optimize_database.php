<?php
require_once 'db_connect.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<h2>Optimization & Scaling Script</h2>";

    // 1. Add order_id to Referrals if not exists
    try {
        $pdo->exec("ALTER TABLE referrals ADD COLUMN order_id VARCHAR(100) NULL");
        echo "Added 'order_id' column to referrals.<br>";
    } catch (PDOException $e) {
        echo "Column 'order_id' likely exists.<br>";
    }

    // 2. Add Unique Index on order_id (Prevent Double Payment)
    try {
        $pdo->exec("CREATE UNIQUE INDEX idx_order_id ON referrals (order_id)");
        echo "Added UNIQUE INDEX on 'order_id' (Protection against double-spending).<br>";
    } catch (PDOException $e) {
        echo "Index 'idx_order_id' likely exists.<br>";
    }

    // 3. Add Index on referrer_id (Speed up Dashboard)
    try {
        $pdo->exec("CREATE INDEX idx_referrer_id ON referrals (referrer_id)");
        echo "Added INDEX on 'referrer_id' (Speeds up Dashboard).<br>";
    } catch (PDOException $e) {
        echo "Index 'idx_referrer_id' likely exists.<br>";
    }

    // 4. Add Indexes to Users (Speed up Login/Lookup)
    try {
        $pdo->exec("CREATE INDEX idx_referral_code ON users (referral_code)");
        echo "Added INDEX on 'referral_code' (Speeds up Payments).<br>";
    } catch (PDOException $e) {
        echo "Index 'idx_referral_code' likely exists.<br>";
    }

    try {
        $pdo->exec("CREATE INDEX idx_email ON users (email)");
        echo "Added INDEX on 'email' (Speeds up Login).<br>";
    } catch (PDOException $e) {
        echo "Index 'idx_email' likely exists.<br>";
    }

    echo "<h3>Optimization Complete. Database is ready for scale.</h3>";

} catch (PDOException $e) {
    echo "Critical Error: " . $e->getMessage();
}
?>
