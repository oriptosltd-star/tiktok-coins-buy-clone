<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Configuration
$config = require 'config.php';
$smtp_host = $config['smtp']['host'];
$smtp_port = $config['smtp']['port'];
$smtp_user = $config['smtp']['user'];
$smtp_pass = $config['smtp']['pass'];

// Receive JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$name = $input['name'] ?? 'Guest';
$replyTo = $input['email'] ?? '';
$subject = $input['subject'] ?? 'New Support Request';
$message = $input['message'] ?? '';

if (empty($replyTo) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Email and message are required']);
    exit;
}

// Simple SMTP Send Function (SSL/465)
function sendSmtpEmail($host, $port, $username, $password, $from, $fromName, $to, $subject, $body, $replyTo) {
    $c = "\r\n";
    $socket = fsockopen("ssl://{$host}", $port, $errno, $errstr, 10);
    
    if (!$socket) return "Connection failed: $errstr ($errno)";

    function read($s) { 
        $data = "";
        while($str = fgets($s, 515)) {
            $data .= $str;
            if(substr($str, 3, 1) == " ") { break; }
        }
        return $data;
    }

    read($socket); // banner

    fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . $c);
    read($socket);

    fputs($socket, "AUTH LOGIN" . $c);
    read($socket);

    fputs($socket, base64_encode($username) . $c);
    read($socket);

    fputs($socket, base64_encode($password) . $c);
    $authResp = read($socket);

    if (strpos($authResp, '235') === false) {
        return "Authentication failed. Check password.";
    }

    fputs($socket, "MAIL FROM: <$username>" . $c);
    read($socket);

    fputs($socket, "RCPT TO: <$to>" . $c);
    read($socket);

    fputs($socket, "DATA" . $c);
    read($socket);

    $headers = "MIME-Version: 1.0" . $c;
    $headers .= "Content-type: text/html; charset=UTF-8" . $c;
    $headers .= "From: $fromName <$username>" . $c;
    $headers .= "Reply-To: $replyTo" . $c;
    $headers .= "To: $to" . $c;
    $headers .= "Subject: $subject" . $c;

    $emailContent = "$headers$c$body$c.$c";
    
    fputs($socket, $emailContent);
    $sendResp = read($socket);

    fputs($socket, "QUIT" . $c);
    fclose($socket);

    if (strpos($sendResp, '250') !== false) {
        return true;
    } else {
        return "Send failed: $sendResp";
    }
}

// Create Email Body
$emailBody = "
<html>
<head>
  <style>
    body { font-family: 'Inter', sans-serif; background: #f4f4f4; padding: 20px; color: #333; }
    .container { background: #fff; padding: 40px; border-radius: 12px; max-width: 600px; margin: 0 auto; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
    h2 { color: #FE2C55; margin-top: 0; }
    .label { font-weight: bold; color: #555; }
    .val { margin-bottom: 20px; }
    .divider { height: 1px; background: #eee; margin: 20px 0; }
    .footer { font-size: 12px; color: #999; text-align: center; margin-top: 40px; }
  </style>
</head>
<body>
  <div class='container'>
    <h2>New Support Message</h2>
    <div class='label'>From:</div>
    <div class='val'>$name ($replyTo)</div>
    
    <div class='label'>Subject:</div>
    <div class='val'>$subject</div>
    
    <div class='divider'></div>
    
    <div class='label'>Message:</div>
    <div class='val'>" . nl2br(htmlspecialchars($message)) . "</div>
    
    <div class='footer'>Sent via Cheaper360.store SMTP System</div>
  </div>
</body>
</html>
";

// Send
$result = sendSmtpEmail($smtp_host, $smtp_port, $smtp_user, $smtp_pass, $smtp_user, "Cheaper360 Support", $smtp_user, "Support: $subject", $emailBody, $replyTo);

if ($result === true) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $result]);
}
?>
