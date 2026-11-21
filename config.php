<?php
// Central Configuration File

// Prevent direct access
if (realpath(__FILE__) == realpath($_SERVER['SCRIPT_FILENAME'])) {
    header('HTTP/1.0 403 Forbidden');
    die('Forbidden');
}

return [
    // Database Configuration
    'db' => [
        'host' => 'localhost',
        'name' => 'u916646072_tiktok',
        'user' => 'u916646072_admin',
        'pass' => '@Idea241@'
    ],

    // SMTP Configuration
    'smtp' => [
        'host' => 'smtp.hostinger.com',
        'port' => 465,
        'user' => 'support@cheaper360.store',
        'pass' => '@Idea241@',
        'from_name' => 'Cheaper360 Support'
    ],

    // API Keys
    'nowpayments' => [
        'api_key' => 'Y2G2ZKE-RJD4JKK-Q75C5ZV-ZZNXJWR',
        'ipn_secret' => 'pseOum5Fli4a3BjXftcj3CaOhbm3l1b1'
    ],

    'rapidapi' => [
        'key' => 'abbe2d2165msh4aa4efb006af576p1cdb1ejsnc9b4e2a8f327',
        'host' => 'tiktok-api23.p.rapidapi.com'
    ],

    // Stripe
    'stripe' => [
        'public_key' => 'pk_live_51TuycHE0rHzJS7UuqDrvgd2kzMYoFaXQ2mT28ozOPodOJk0sQovt1k4bugaRxwPur4x4JZSQGWhS5hazCuqk5ujo00aHjkLDkt',
        'secret_key' => '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'
    ]
];
?>
