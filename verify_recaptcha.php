<?php
function verifyRecaptcha($token) {
    $secretKey = 'YOUR_SECRET_KEY_HERE'; // Replace with your actual secret key
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    
    $data = [
        'secret' => $secretKey,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        // If verification fails, log error but don't block user
        error_log("reCAPTCHA verification failed - network error");
        return true; // Fail open for better UX
    }
    
    $result = json_decode($response);
    
    if (!$result || !isset($result->success)) {
        error_log("reCAPTCHA verification failed - invalid response");
        return true; // Fail open
    }
    
    // reCAPTCHA v3 returns a score from 0.0 to 1.0
    // 0.0 = very likely a bot, 1.0 = very likely a human
    // Threshold: 0.5 is recommended
    $threshold = 0.5;
    
    if ($result->success && isset($result->score)) {
        return $result->score >= $threshold;
    }
    
    return $result->success;
}

function getRecaptchaScore($token) {
    $secretKey = 'YOUR_SECRET_KEY_HERE';
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    
    $data = [
        'secret' => $secretKey,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return null;
    }
    
    $result = json_decode($response);
    
    return $result->score ?? null;
}
?>
