<?php
require_once 'db_connect.php';

class FraudDetector {
    private $pdo;
    private $disposableEmailDomains = [
        'tempmail.com', '10minutemail.com', 'guerrillamail.com', 'mailinator.com',
        'throwaway.email', 'temp-mail.org', 'getnada.com', 'maildrop.cc'
    ];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function analyzePurchase($userId, $amount, $email, $ip) {
        $riskScore = 0;
        $reasons = [];
        
        // Check 1: Multiple accounts from same IP
        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT id) as count FROM users WHERE last_login_ip = ? OR registration_ip = ?");
        $stmt->execute([$ip, $ip]);
        $ipCount = $stmt->fetchColumn();
        
        if ($ipCount > 5) {
            $riskScore += 40;
            $reasons[] = "Multiple accounts from same IP ($ipCount accounts)";
        } elseif ($ipCount > 3) {
            $riskScore += 20;
            $reasons[] = "Several accounts from same IP";
        }
        
        // Check 2: Disposable email
        $emailDomain = strtolower(substr(strrchr($email, '@'), 1));
        if (in_array($emailDomain, $this->disposableEmailDomains)) {
            $riskScore += 50;
            $reasons[] = 'Disposable email detected';
        }
        
        // Check 3: Rapid purchases
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute([$userId]);
        $recentOrders = $stmt->fetchColumn();
        
        if ($recentOrders > 5) {
            $riskScore += 50;
            $reasons[] = "Unusual purchase frequency ($recentOrders in 1 hour)";
        } elseif ($recentOrders > 3) {
            $riskScore += 25;
            $reasons[] = "High purchase frequency";
        }
        
        // Check 4: Large first purchase
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
        $stmt->execute([$userId]);
        $totalOrders = $stmt->fetchColumn();
        
        if ($totalOrders == 0 && $amount > 100) {
            $riskScore += 30;
            $reasons[] = "Large first purchase ($" . number_format($amount, 2) . ")";
        } elseif ($totalOrders == 0 && $amount > 200) {
            $riskScore += 50;
            $reasons[] = "Very large first purchase";
        }
        
        // Check 5: New account (< 1 hour old)
        $stmt = $this->pdo->prepare("SELECT created_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $accountAge = strtotime($stmt->fetchColumn());
        $ageInHours = (time() - $accountAge) / 3600;
        
        if ($ageInHours < 1 && $amount > 50) {
            $riskScore += 30;
            $reasons[] = "New account with immediate large purchase";
        }
        
        // Check 6: VPN/Proxy detection (basic)
        if ($this->isVPN($ip)) {
            $riskScore += 20;
            $reasons[] = "VPN or proxy detected";
        }
        
        // Check 7: Previous fraud from this IP
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM fraud_logs WHERE ip_address = ? AND risk_level = 'HIGH' AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute([$ip]);
        if ($stmt->fetchColumn() > 0) {
            $riskScore += 60;
            $reasons[] = "Previous fraud from this IP";
        }
        
        $result = [
            'risk_score' => min($riskScore, 100),
            'risk_level' => $this->getRiskLevel($riskScore),
            'reasons' => $reasons,
            'action' => $this->getRecommendedAction($riskScore)
        ];
        
        // Log the fraud check
        $this->logFraudCheck($userId, $ip, $email, $result);
        
        return $result;
    }
    
    private function getRiskLevel($score) {
        if ($score >= 70) return 'HIGH';
        if ($score >= 40) return 'MEDIUM';
        return 'LOW';
    }
    
    private function getRecommendedAction($score) {
        if ($score >= 70) return 'BLOCK';
        if ($score >= 40) return 'REVIEW';
        return 'APPROVE';
    }
    
    private function isVPN($ip) {
        // Basic check for common VPN IP ranges
        // In production, use a service like IPQualityScore or IPHub
        $vpnRanges = [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16'
        ];
        
        foreach ($vpnRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function ipInRange($ip, $range) {
        list($subnet, $mask) = explode('/', $range);
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask_long = -1 << (32 - $mask);
        return ($ip_long & $mask_long) == ($subnet_long & $mask_long);
    }
    
    private function logFraudCheck($userId, $ip, $email, $result) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO fraud_logs (user_id, ip_address, email, risk_score, risk_level, risk_reasons, action_taken) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $userId,
                $ip,
                $email,
                $result['risk_score'],
                $result['risk_level'],
                json_encode($result['reasons']),
                $result['action']
            ]);
        } catch (Exception $e) {
            // Silent fail - don't block transaction if logging fails
            error_log("Fraud log failed: " . $e->getMessage());
        }
    }
}
?>
