<?php
require_once 'db_connect.php';

class RateLimiter {
    private $pdo;
    private $limits = [
        'login' => ['attempts' => 5, 'window' => 900], // 5 attempts per 15 min
        'register' => ['attempts' => 3, 'window' => 3600], // 3 per hour
        'api' => ['attempts' => 100, 'window' => 60], // 100 per minute
        'contact' => ['attempts' => 5, 'window' => 3600] // 5 per hour
    ];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function checkLimit($actionType) {
        $ip = $this->getClientIP();
        $limit = $this->limits[$actionType] ?? ['attempts' => 10, 'window' => 60];
        
        // Clean old records
        $cutoff = date('Y-m-d H:i:s', time() - $limit['window']);
        $this->pdo->prepare("DELETE FROM rate_limits WHERE action_type = ? AND last_attempt < ?")
                  ->execute([$actionType, $cutoff]);
        
        // Check current attempts
        $stmt = $this->pdo->prepare("SELECT attempt_count, first_attempt FROM rate_limits WHERE ip_address = ? AND action_type = ?");
        $stmt->execute([$ip, $actionType]);
        $record = $stmt->fetch();
        
        if ($record && $record['attempt_count'] >= $limit['attempts']) {
            $timeRemaining = $limit['window'] - (time() - strtotime($record['first_attempt']));
            return [
                'allowed' => false,
                'retry_after' => max(0, $timeRemaining),
                'message' => "Too many attempts. Please try again in " . ceil($timeRemaining / 60) . " minutes."
            ];
        }
        
        // Update or insert
        if ($record) {
            $this->pdo->prepare("UPDATE rate_limits SET attempt_count = attempt_count + 1, last_attempt = NOW() WHERE ip_address = ? AND action_type = ?")
                      ->execute([$ip, $actionType]);
            $remaining = $limit['attempts'] - $record['attempt_count'] - 1;
        } else {
            $this->pdo->prepare("INSERT INTO rate_limits (ip_address, action_type, attempt_count) VALUES (?, ?, 1)")
                      ->execute([$ip, $actionType]);
            $remaining = $limit['attempts'] - 1;
        }
        
        return [
            'allowed' => true,
            'remaining' => $remaining,
            'limit' => $limit['attempts']
        ];
    }
    
    public function resetLimit($actionType, $ip = null) {
        $ip = $ip ?? $this->getClientIP();
        $this->pdo->prepare("DELETE FROM rate_limits WHERE ip_address = ? AND action_type = ?")
                  ->execute([$ip, $actionType]);
    }
    
    private function getClientIP() {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP']; // Cloudflare
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
?>
