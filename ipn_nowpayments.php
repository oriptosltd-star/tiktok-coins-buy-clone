<?php
require_once 'webhook_handler.php'; // Reuse logic

// CONFIGURATION
$config = require 'config.php';
$ipnSecret = $config['nowpayments']['ipn_secret'];
// Helper function to recursively sort array by keys (Recommended by NowPayments)
function tksort(&$array) {
  ksort($array);
  foreach(array_keys($array) as $k) {
    if(gettype($array[$k])=="array") {
      tksort($array[$k]);
    }
  }
}

$error_msg = "Unknown error";
$auth_ok = false;
$request_data = null;

if (isset($_SERVER['HTTP_X_NOWPAYMENTS_SIG']) && !empty($_SERVER['HTTP_X_NOWPAYMENTS_SIG'])) {
    $recived_hmac = $_SERVER['HTTP_X_NOWPAYMENTS_SIG'];
    $request_json = file_get_contents('php://input');
    $request_data = json_decode($request_json, true);
    
    // Sort logic
    tksort($request_data);
    $sorted_request_json = json_encode($request_data, JSON_UNESCAPED_SLASHES);

    if ($request_json !== false && !empty($request_json)) {
        $hmac = hash_hmac("sha512", $sorted_request_json, $ipnSecret);
        if ($hmac == $recived_hmac) {
            $auth_ok = true;
        } else {
            $error_msg = 'HMAC signature does not match';
        }
    } else {
        $error_msg = 'Error reading POST data';
    }
} else {
    $error_msg = 'No HMAC signature sent';
}

if ($auth_ok) {
    // Payment status
    $payment_status = $request_data['payment_status'];
    $order_id = $request_data['order_id'] ?? '';
    
    // We only credit on 'finished'
    if ($payment_status == 'finished') {
        
        // Extract Referral Code from Order ID (ORD-12345-REF-CODE)
        $parts = explode('-REF-', $order_id);
        if (count($parts) > 1) {
            $refCode = $parts[1];
            
            // Amount Handling: 'pay_amount' is what user sent. 'price_amount' is what we asked for (USD)
            // Ideally commission is based on the USD value we asked for.
            $usd_amount = $request_data['price_amount'] ?? 0;

            processCommission($order_id, $usd_amount, $refCode, $request_data['payment_id'] ?? 'Crypto');
            echo "Commission Processed";
        } else {
            echo "No referral code found in order_id";
        }
    } else {
        echo "Status: " . $payment_status;
    }
} else {
    http_response_code(400);
    echo $error_msg;
}
?>
