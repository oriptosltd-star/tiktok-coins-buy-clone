<?php
require_once 'db_connect.php';

// This script should be called by the Payment Gateway Webhook
// OR internally by the success page if we trust the redirect (less secure, but easier for MVP)

// For this implementation, we will assume this is called internally after verification
// verify_payment.php would call this function

function processCommission($orderId, $amount, $referralCode, $buyerId) {
    global $pdo;

    if (empty($referralCode)) return;

    // 1. Find Direct Referrer (Seller - Tier 1)
    $stmt = $pdo->prepare("SELECT id, referred_by FROM users WHERE referral_code = ?");
    $stmt->execute([$referralCode]);
    $seller = $stmt->fetch();

    if (!$seller) return; // Invalid code

    // Tier 1 Commission (25%)
    $commissionTier1 = $amount * 0.25;
    
    // Tier 2 Commission (10%)
    $commissionTier2 = $amount * 0.10;

    // 2. Identify Upline (Tier 2)
    $uplineId = $seller['referred_by']; 

    // 3. Process Payments (Atomic Transaction)
    try {
        $pdo->beginTransaction();

        // Check if order already processed
        $check = $pdo->prepare("SELECT id FROM referrals WHERE order_id = ?");
        $check->execute([$orderId]);
        if ($check->fetch()) {
            $pdo->commit();
            return true; // Already processed
        }

        // --- TIER 1 PAYMENT (Seller) ---
        $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $stmt->execute([$commissionTier1, $seller['id']]);

        // Log Tier 1
        $stmt = $pdo->prepare("INSERT INTO referrals (referrer_id, buyer_identifier, amount_total, commission_amount, status, order_id, type) VALUES (?, ?, ?, ?, 'completed', ?, 'direct')");
        $stmt->execute([$seller['id'], $buyerId, $amount, $commissionTier1, $orderId]);

        // --- TIER 2 PAYMENT (Upline) ---
        if ($uplineId) {
             $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
             $stmt->execute([$commissionTier2, $uplineId]);

             // Log Tier 2
             $stmt = $pdo->prepare("INSERT INTO referrals (referrer_id, buyer_identifier, amount_total, commission_amount, status, order_id, type) VALUES (?, ?, ?, ?, 'completed', ?, 'tier_2')");
             $stmt->execute([$uplineId, $buyerId, $amount, $commissionTier2, $orderId]);
        }

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        $pdo->rollBack();
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
             return true; 
        }
        return false;
    }
}
?>
