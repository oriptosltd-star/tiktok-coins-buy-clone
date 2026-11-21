<?php
session_start();
require_once 'db_connect.php';

// Only load advanced features if tables exist
$advancedFeaturesEnabled = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'rate_limits'");
    if ($stmt->rowCount() > 0) {
        require_once 'rate_limiter.php';
        require_once 'fraud_detector.php';
        require_once 'verify_recaptcha.php';
        $rateLimiter = new RateLimiter($pdo);
        $fraudDetector = new FraudDetector($pdo);
        $advancedFeaturesEnabled = true;
    }
} catch (Exception $e) {
    // Advanced features not available yet
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

if ($action === 'register') {
    // Check rate limit (if enabled)
    if ($advancedFeaturesEnabled) {
        $rateCheck = $rateLimiter->checkLimit('register');
        if (!$rateCheck['allowed']) {
            redirectWithError('register.html', $rateCheck['message']);
        }
        
        // Verify reCAPTCHA (if enabled)
        $recaptchaToken = $_POST['recaptcha_token'] ?? '';
        if (!empty($recaptchaToken) && !verifyRecaptcha($recaptchaToken)) {
            redirectWithError('register.html', 'reCAPTCHA verification failed. Please try again.');
        }
    }
    
    // Sanitize and Validate Input
    $name = strip_tags(trim($_POST['name']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
         redirectWithError('register.html', 'Invalid email format');
    }

    $password = $_POST['password'];
    $ip = $_SERVER['REMOTE_ADDR'];

    // Basic Validation
    if (empty($name) || empty($email) || empty($password)) {
        redirectWithError('register.html', 'All fields are required');
    }

    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        redirectWithError('register.html', 'Email already registered');
    }

    // Generate Referral Code (Unique 8 chars)
    $refCode = strtoupper(substr(md5(uniqid()), 0, 8));

    // Handle Referral Logic (Tier 2) - Check Cookie or Session
    // Handle Referral Logic: Check POST (Form) -> Cookie -> Session
    $referredBy = null;
    $refCodeInput = $_POST['referral_code'] ?? $_COOKIE['ref_code'] ?? $_SESSION['ref_code'] ?? '';
    
    // Clean and Validate
    $refCodeInput = trim(strtoupper($refCodeInput));
    
    if (!empty($refCodeInput)) {
        // Find the user who owns this code
        $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmt->execute([$refCodeInput]);
        $referrer = $stmt->fetch();
        if ($referrer) {
            $referredBy = $referrer['id'];
        }
    }

    // Hash Password
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert User
    try {
        // Check if registration_ip column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'registration_ip'");
        $hasIpColumn = $stmt->rowCount() > 0;
        
        if ($hasIpColumn) {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, referral_code, registration_ip, referred_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hash, $refCode, $ip, $referredBy]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, referral_code, referred_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hash, $refCode, $referredBy]);
        }
        
        $userId = $pdo->lastInsertId();
        
        // Initialize loyalty features if tables exist
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'user_tiers'");
            if ($stmt->rowCount() > 0) {
                // Initialize user tier
                $stmt = $pdo->prepare("INSERT INTO user_tiers (user_id) VALUES (?)");
                $stmt->execute([$userId]);
                
                // Award registration bonus points
                $stmt = $pdo->prepare("INSERT INTO loyalty_points (user_id, points, action_type, description) VALUES (?, 100, 'registration', 'Welcome bonus')");
                $stmt->execute([$userId]);
                $stmt = $pdo->prepare("UPDATE user_tiers SET total_points = 100, lifetime_points = 100 WHERE user_id = ?");
                $stmt->execute([$userId]);
            }
        } catch (Exception $e) {
            // Loyalty features not available, continue without them
        }
        
        // Auto Login
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $name;
        $_SESSION['ref_code'] = $refCode;
        
        // Reset rate limit on successful registration (if enabled)
        if ($advancedFeaturesEnabled) {
            $rateLimiter->resetLimit('register');
        }
        
        header("Location: dashboard.html");
        exit;
        
    } catch (Exception $e) {
        redirectWithError('register.html', 'Registration failed');
    }
}

if ($action === 'login') {
    // Check rate limit (if enabled)
    if ($advancedFeaturesEnabled) {
        $rateCheck = $rateLimiter->checkLimit('login');
        if (!$rateCheck['allowed']) {
            redirectWithError('login.html', $rateCheck['message']);
        }
        
        // Verify reCAPTCHA (if enabled)
        $recaptchaToken = $_POST['recaptcha_token'] ?? '';
        if (!empty($recaptchaToken) && !verifyRecaptcha($recaptchaToken)) {
            redirectWithError('login.html', 'reCAPTCHA verification failed. Please try again.');
        }
    }
    
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $ip = $_SERVER['REMOTE_ADDR'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if ($user['is_2fa_enabled'] && !empty($user['two_factor_secret'])) {
            // Set pending session and redirect to 2FA challenge
            $_SESSION['pending_user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'referral_code' => $user['referral_code']
            ];
            header("Location: 2fa_login.html");
            exit;
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['username'];
        $_SESSION['ref_code'] = $user['referral_code'];
        
        // Update last login IP (if column exists)
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_login_ip'");
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("UPDATE users SET last_login_ip = ?, last_login = NOW() WHERE id = ?");
                $stmt->execute([$ip, $user['id']]);
            }
        } catch (Exception $e) {
            // Column doesn't exist yet
        }
        
        // Award daily login points (if tables exist)
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'loyalty_points'");
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM loyalty_points WHERE user_id = ? AND action_type = 'daily_login' AND DATE(created_at) = CURDATE()");
                $stmt->execute([$user['id']]);
                if ($stmt->fetchColumn() == 0) {
                    $stmt = $pdo->prepare("INSERT INTO loyalty_points (user_id, points, action_type, description) VALUES (?, 5, 'daily_login', 'Daily login bonus')");
                    $stmt->execute([$user['id']]);
                    $stmt = $pdo->prepare("UPDATE user_tiers SET total_points = total_points + 5, lifetime_points = lifetime_points + 5 WHERE user_id = ?");
                    $stmt->execute([$user['id']]);
                }
            }
        } catch (Exception $e) {
            // Loyalty features not available
        }
        
        // Reset rate limit on successful login (if enabled)
        if ($advancedFeaturesEnabled) {
            $rateLimiter->resetLimit('login');
        }
        
        header("Location: dashboard.html");
        exit;
    } else {
        redirectWithError('login.html', 'Invalid email or password');
    }
}

if ($_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.html");
    exit;
}

function redirectWithError($page, $msg) {
    // For simplicity in this demo, sending back as query param
    header("Location: $page?error=" . urlencode($msg));
    exit;
}
?>
