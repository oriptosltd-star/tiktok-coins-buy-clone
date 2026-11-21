<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$username = isset($_GET['username']) ? trim($_GET['username']) : '';
$username = str_replace('@', '', $username);

if (empty($username)) {
    http_response_code(400);
    echo json_encode(['error' => 'Username required']);
    exit;
}

    // ---------------------------------------------------------
    // STRATEGY 1: RapidAPI (Lundehund / tiktok-api23)
    // ---------------------------------------------------------
    $config = require 'config.php';
    $rapidApiKey = $config['rapidapi']['key'];
    $rapidApiHost = $config['rapidapi']['host'];
    
    // Endpoint: /api/search/account
    $rapidUrl = "https://tiktok-api23.p.rapidapi.com/api/search/account?keyword=" . urlencode($username);
    
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $rapidUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "X-RapidAPI-Host: " . $rapidApiHost,
            "X-RapidAPI-Key: " . $rapidApiKey
        ],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    // Check for Connection Errors
    if ($err) {
         echo json_encode(['success' => false, 'error' => 'cURL Error: ' . $err]);
         exit;
    }

    if (!$err) {
        $data = json_decode($response, true);
        
        // Check for specific API errors
        // "message" often appears if endpoint not found or subscription issue
        if (isset($data['message']) && (stripos($data['message'], 'subscribed') !== false || stripos($data['message'], 'endpoint') !== false)) {
             $errorMsg = $data['message'];
             // error_log("RapidAPI Error: $errorMsg"); 
             // Allow fallthrough to scraping logic
        } else {
             // Parse list of users for tiktok-api23
             // Structure: { user_list: [ { user_info: { nickname:..., avatar_medium:... } } ] }
             $userList = [];
             $candidates = $data['user_list'] ?? [];
     
             if (!empty($candidates)) {
                  foreach ($candidates as $item) {
                      $u = $item['user_info'] ?? [];
                      
                      $validUsername = $u['unique_id'] ?? $u['nickname'] ?? null;
                      $validName = $u['nickname'] ?? $u['unique_id'] ?? null;
                      
                      // Extract Avatar URL (Handles both string and array formats)
                      $avatarObj = $u['avatar_thumb'] ?? $u['avatar_medium'] ?? $u['avatar_168x168'] ?? null;
                      $validAvatar = null;
                      
                      if (is_string($avatarObj)) {
                          $validAvatar = $avatarObj;
                      } elseif (is_array($avatarObj) && !empty($avatarObj['url_list'][0])) {
                          $validAvatar = $avatarObj['url_list'][0];
                      }
                      
                      if ($validUsername && $validAvatar) {
                          $userList[] = [
                              'username' => '@' . str_replace('@', '', $validUsername),
                              'name' => $validName,
                              'avatar' => $validAvatar
                          ];
                          
                          // Limit to 3 results as per user request
                          if (count($userList) >= 3) {
                              break;
                          }
                      }
                  }
             }
             
             if (count($userList) > 0) {
                 echo json_encode([
                     'success' => true,
                     'users' => $userList,
                     'source' => 'tiktok-api23'
                 ]);
                 exit;
             }
        }
    }

// ---------------------------------------------------------
// STRATEGY 2: Fallback to Public Scraping (If API fails/quota exceeded)
// ---------------------------------------------------------
// error_log("RapidAPI failed for user $username. Trying scraper...");
$fallbackUrl = "https://www.tiktok.com/@" . $username;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $fallbackUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
// Improve user agent to rotate if possible, but keep simple for fixed file
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$html = curl_exec($ch);
curl_close($ch);

if ($html) {
    preg_match('/<meta property="og:image" content="([^"]+)"/i', $html, $avatarMatches);
    $avatarUrl = $avatarMatches[1] ?? null;

    preg_match('/<title>([^<]+)<\/title>/i', $html, $titleMatches);
    $titleText = $titleMatches[1] ?? '';

    $displayName = $username;
    if ($titleText) {
        $clean = explode('|', $titleText)[0];
        $clean = trim($clean);
        $parts = explode('(@', $clean);
        if (count($parts) > 1) {
            $displayName = trim($parts[0]);
        } else {
            $displayName = $clean;
        }
    }

    if ($avatarUrl) {
        echo json_encode([
            'success' => true,
            'username' => '@' . $username,
            'name' => $displayName,
            'avatar' => $avatarUrl,
             'source' => 'scrape_fallback'
        ]);
        exit;
    }
}

// ---------------------------------------------------------
// FAILURE
// ---------------------------------------------------------
// Return 200 with success: false (as per user preference for soft errors) or 404
// User requested: echo json_encode(["success" => false]);
echo json_encode(['success' => false, 'error' => 'User not found']);
?>
