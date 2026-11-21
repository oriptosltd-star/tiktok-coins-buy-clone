-- Loyalty Program Database Schema
-- Run this to create all necessary tables

-- Points History Table
CREATE TABLE IF NOT EXISTS loyalty_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    points INT NOT NULL,
    action_type VARCHAR(50) NOT NULL COMMENT 'registration, purchase, referral, login, etc.',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_points (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User Tier Table
CREATE TABLE IF NOT EXISTS user_tiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    tier_level ENUM('bronze', 'silver', 'gold', 'platinum') DEFAULT 'bronze',
    total_points INT DEFAULT 0,
    lifetime_points INT DEFAULT 0 COMMENT 'Never decreases, used for tier calculation',
    tier_benefits JSON,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_tier_level (tier_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Points Redemption Table
CREATE TABLE IF NOT EXISTS points_redemptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    points_spent INT NOT NULL,
    reward_type VARCHAR(50) COMMENT 'discount, free_coins, etc.',
    reward_value DECIMAL(10,2),
    order_id INT NULL COMMENT 'Associated order if applicable',
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_redemptions (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Referral Bonuses Table
CREATE TABLE IF NOT EXISTS referral_bonuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT NOT NULL COMMENT 'User who referred',
    referred_id INT NOT NULL COMMENT 'User who was referred',
    bonus_points INT NOT NULL,
    bonus_type VARCHAR(50) COMMENT 'signup, first_purchase, etc.',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_referrer (referrer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rate Limiting Table
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    action_type VARCHAR(50) NOT NULL COMMENT 'login, register, api, etc.',
    attempt_count INT DEFAULT 1,
    first_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ip_action (ip_address, action_type),
    INDEX idx_last_attempt (last_attempt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Fraud Detection Logs
CREATE TABLE IF NOT EXISTS fraud_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    ip_address VARCHAR(45) NOT NULL,
    email VARCHAR(255),
    risk_score INT NOT NULL,
    risk_level ENUM('LOW', 'MEDIUM', 'HIGH') NOT NULL,
    risk_reasons JSON,
    action_taken ENUM('APPROVE', 'REVIEW', 'BLOCK') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_risk_level (risk_level, created_at),
    INDEX idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Initialize tiers for existing users
INSERT INTO user_tiers (user_id, tier_level, total_points, lifetime_points)
SELECT id, 'bronze', 0, 0 
FROM users 
WHERE id NOT IN (SELECT user_id FROM user_tiers)
ON DUPLICATE KEY UPDATE user_id = user_id;
