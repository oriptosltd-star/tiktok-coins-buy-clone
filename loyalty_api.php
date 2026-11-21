<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Award Points
if ($action === 'award_points') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    $points = intval($_POST['points']);
    $actionType = $_POST['action_type'];
    $description = $_POST['description'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        // Add points to history
        $stmt = $pdo->prepare("INSERT INTO loyalty_points (user_id, points, action_type, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $points, $actionType, $description]);
        
        // Update total points and lifetime points
        $stmt = $pdo->prepare("UPDATE user_tiers SET total_points = total_points + ?, lifetime_points = lifetime_points + ? WHERE user_id = ?");
        $stmt->execute([$points, $points, $userId]);
        
        // Check tier upgrade
        updateUserTier($pdo, $userId);
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'points_awarded' => $points]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Failed to award points']);
    }
    exit;
}

// Get User Points and Tier
if ($action === 'get_points') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("SELECT total_points, lifetime_points, tier_level FROM user_tiers WHERE user_id = ?");
    $stmt->execute([$userId]);
    $data = $stmt->fetch();
    
    if (!$data) {
        // Create tier record if doesn't exist
        $pdo->prepare("INSERT INTO user_tiers (user_id) VALUES (?)")->execute([$userId]);
        $data = ['total_points' => 0, 'lifetime_points' => 0, 'tier_level' => 'bronze'];
    }
    
    // Calculate points to next tier
    $tierThresholds = ['bronze' => 0, 'silver' => 1000, 'gold' => 5000, 'platinum' => 10000];
    $currentTier = $data['tier_level'];
    $currentPoints = $data['lifetime_points'];
    
    $nextTier = null;
    $pointsToNext = 0;
    
    foreach ($tierThresholds as $tier => $threshold) {
        if ($threshold > $currentPoints) {
            $nextTier = $tier;
            $pointsToNext = $threshold - $currentPoints;
            break;
        }
    }
    
    // Get tier benefits
    $benefits = getTierBenefits($currentTier);
    
    echo json_encode([
        'success' => true,
        'total_points' => $data['total_points'],
        'lifetime_points' => $data['lifetime_points'],
        'tier' => $currentTier,
        'next_tier' => $nextTier,
        'points_to_next' => $pointsToNext,
        'benefits' => $benefits
    ]);
    exit;
}

// Redeem Points
if ($action === 'redeem_points') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    $pointsToSpend = intval($_POST['points']);
    $rewardType = $_POST['reward_type'] ?? 'discount';
    
    try {
        $pdo->beginTransaction();
        
        // Check if user has enough points
        $stmt = $pdo->prepare("SELECT total_points FROM user_tiers WHERE user_id = ?");
        $stmt->execute([$userId]);
        $currentPoints = $stmt->fetchColumn();
        
        if ($currentPoints < $pointsToSpend) {
            echo json_encode(['success' => false, 'error' => 'Insufficient points']);
            exit;
        }
        
        // Calculate reward value (100 points = $1 discount)
        $rewardValue = $pointsToSpend / 100;
        
        // Deduct points (only from total_points, not lifetime_points)
        $stmt = $pdo->prepare("UPDATE user_tiers SET total_points = total_points - ? WHERE user_id = ?");
        $stmt->execute([$pointsToSpend, $userId]);
        
        // Record redemption
        $stmt = $pdo->prepare("INSERT INTO points_redemptions (user_id, points_spent, reward_type, reward_value, status) VALUES (?, ?, ?, ?, 'completed')");
        $stmt->execute([$userId, $pointsToSpend, $rewardType, $rewardValue]);
        
        // Add negative entry to points history
        $stmt = $pdo->prepare("INSERT INTO loyalty_points (user_id, points, action_type, description) VALUES (?, ?, 'redemption', ?)");
        $stmt->execute([$userId, -$pointsToSpend, "Redeemed for $$rewardValue discount"]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'discount' => $rewardValue,
            'points_spent' => $pointsToSpend,
            'remaining_points' => $currentPoints - $pointsToSpend
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Redemption failed']);
    }
    exit;
}

// Get Points History
if ($action === 'get_history') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    $limit = intval($_GET['limit'] ?? 20);
    
    $stmt = $pdo->prepare("SELECT points, action_type, description, created_at FROM loyalty_points WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    $history = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'history' => $history]);
    exit;
}

// Award Referral Bonus
if ($action === 'award_referral_bonus') {
    $referrerId = intval($_POST['referrer_id']);
    $referredId = intval($_POST['referred_id']);
    $bonusType = $_POST['bonus_type'] ?? 'signup';
    
    $bonusPoints = $bonusType === 'signup' ? 200 : 500; // 200 for signup, 500 for first purchase
    
    try {
        $pdo->beginTransaction();
        
        // Record referral bonus
        $stmt = $pdo->prepare("INSERT INTO referral_bonuses (referrer_id, referred_id, bonus_points, bonus_type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$referrerId, $referredId, $bonusPoints, $bonusType]);
        
        // Award points to referrer
        $stmt = $pdo->prepare("INSERT INTO loyalty_points (user_id, points, action_type, description) VALUES (?, ?, 'referral', ?)");
        $stmt->execute([$referrerId, $bonusPoints, "Referral bonus: $bonusType"]);
        
        $stmt = $pdo->prepare("UPDATE user_tiers SET total_points = total_points + ?, lifetime_points = lifetime_points + ? WHERE user_id = ?");
        $stmt->execute([$bonusPoints, $bonusPoints, $referrerId]);
        
        updateUserTier($pdo, $referrerId);
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'bonus_awarded' => $bonusPoints]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Failed to award referral bonus']);
    }
    exit;
}

// Helper Functions

function updateUserTier($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT lifetime_points FROM user_tiers WHERE user_id = ?");
    $stmt->execute([$userId]);
    $points = $stmt->fetchColumn();
    
    $newTier = 'bronze';
    if ($points >= 10000) $newTier = 'platinum';
    elseif ($points >= 5000) $newTier = 'gold';
    elseif ($points >= 1000) $newTier = 'silver';
    
    $benefits = getTierBenefits($newTier);
    
    $stmt = $pdo->prepare("UPDATE user_tiers SET tier_level = ?, tier_benefits = ? WHERE user_id = ?");
    $stmt->execute([$newTier, json_encode($benefits), $userId]);
}

function getTierBenefits($tier) {
    $benefits = [
        'bronze' => [
            'support' => 'Standard',
            'points_multiplier' => 1.0,
            'discount' => 0,
            'perks' => []
        ],
        'silver' => [
            'support' => 'Priority',
            'points_multiplier' => 1.25,
            'discount' => 5,
            'perks' => ['Priority email support']
        ],
        'gold' => [
            'support' => 'VIP',
            'points_multiplier' => 1.5,
            'discount' => 10,
            'perks' => ['VIP support', 'Early access to deals', 'Exclusive promotions']
        ],
        'platinum' => [
            'support' => 'Dedicated Manager',
            'points_multiplier' => 2.0,
            'discount' => 15,
            'perks' => ['Dedicated account manager', '2x points on all purchases', 'Exclusive platinum deals', 'Priority processing']
        ]
    ];
    
    return $benefits[$tier] ?? $benefits['bronze'];
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
?>
