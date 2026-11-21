<?php
require_once 'db_connect.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (empty($email)) {
    echo json_encode(['error' => 'Email required']);
    exit;
}

try {
    // 1. Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // 2. Generate Token
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // 3. Store Token
        $update = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
        $update->execute([$token, $expiry, $user['id']]);

        // 4. Send Professional HTML Email
        $resetLink = "https://cheaper360.store/reset_password.html?token=" . $token;
        $subject = "Password Reset Request - Cheaper360";
        
        // Professional HTML Email Template
        $message = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f7f7f7;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f7f7f7; padding: 40px 20px;">
        <tr>
            <td align="center">
                <!-- Main Email Container -->
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); max-width: 600px;">
                    
                    <!-- Header -->
                    <tr>
                        <td style="padding: 40px 40px 30px 40px; text-align: center; border-bottom: 1px solid #f0f0f0;">
                            <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #FE2C55; letter-spacing: -0.5px;">
                                Cheaper360
                            </h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 40px 30px 40px;">
                            <h2 style="margin: 0 0 20px 0; font-size: 24px; font-weight: 700; color: #161823; line-height: 1.3;">
                                Reset Your Password
                            </h2>
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #666666;">
                                We received a request to reset your password. Click the button below to create a new password:
                            </p>
                            
                            <!-- Reset Button -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="' . $resetLink . '" style="display: inline-block; padding: 16px 48px; background-color: #FE2C55; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 700; border-radius: 8px; box-shadow: 0 4px 12px rgba(254, 44, 85, 0.3);">
                                            Reset Password
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 20px 0; font-size: 14px; line-height: 1.6; color: #999999;">
                                Or copy and paste this link into your browser:
                            </p>
                            <p style="margin: 0 0 20px 0; font-size: 13px; line-height: 1.6; color: #FE2C55; word-break: break-all;">
                                ' . $resetLink . '
                            </p>
                            
                            <!-- Security Notice -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 30px 0; background-color: #FFF9F9; border-left: 4px solid #FE2C55; border-radius: 4px;">
                                <tr>
                                    <td style="padding: 16px 20px;">
                                        <p style="margin: 0 0 8px 0; font-size: 14px; font-weight: 700; color: #161823; display: flex; align-items: center; gap: 8px;">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="flex-shrink: 0;">
                                                <rect x="5" y="11" width="14" height="10" rx="2" stroke="#FE2C55" stroke-width="2" fill="none"/>
                                                <path d="M8 11V7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7V11" stroke="#FE2C55" stroke-width="2" stroke-linecap="round"/>
                                                <circle cx="12" cy="16" r="1.5" fill="#FE2C55"/>
                                            </svg>
                                            <span>Security Notice</span>
                                        </p>
                                        <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #666666;">
                                            This link will expire in <strong>1 hour</strong>. If you didn\'t request this password reset, please ignore this email or contact our support team.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #f9f9f9; border-top: 1px solid #f0f0f0; border-radius: 0 0 12px 12px;">
                            <p style="margin: 0 0 10px 0; font-size: 13px; line-height: 1.6; color: #999999; text-align: center;">
                                Need help? Contact us at <a href="mailto:support@cheaper360.store" style="color: #FE2C55; text-decoration: none;">support@cheaper360.store</a>
                            </p>
                            <p style="margin: 0; font-size: 12px; line-height: 1.6; color: #cccccc; text-align: center;">
                                © 2026 Cheaper360. All rights reserved.
                            </p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Cheaper360 <noreply@cheaper360.store>\r\n";

        if(mail($email, $subject, $message, $headers)) {
            echo json_encode(['success' => true]);
        } else {
            // Fallback for demo/localhost: Return token in response if mail fails
            echo json_encode(['success' => true, 'debug_link' => $resetLink]);
        }
    } else {
        // Fake success for security (don't reveal registered emails)
        echo json_encode(['success' => true]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>
