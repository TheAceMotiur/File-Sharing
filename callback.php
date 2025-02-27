<?php
require_once __DIR__ . '/config.php';
session_start();

// Verify admin status
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

try {
    $db = getDBConnection();
    
    // Verify admin status
    $stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user['is_admin']) {
        header('Location: dashboard.php');
        exit;
    }

    // Handle OAuth callback
    if (isset($_GET['code']) && isset($_GET['state'])) {
        // Decode state parameter
        $state = json_decode(base64_decode($_GET['state']), true);
        $code = $_GET['code'];
        
        if (!$state || !isset($state['app_key']) || !isset($state['app_secret'])) {
            throw new Exception('Invalid state parameter');
        }

        $app_key = $state['app_key'];
        $app_secret = $state['app_secret'];
        
        // Exchange code for tokens via Dropbox API
        $response = file_get_contents('https://api.dropboxapi.com/oauth2/token', false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query([
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'client_id' => $app_key,
                    'client_secret' => $app_secret,
                    'redirect_uri' => 'https://' . $_SERVER['HTTP_HOST'] . '/callback.php'
                ])
            ]
        ]));

        $tokens = json_decode($response, true);

        if (isset($tokens['access_token']) && isset($tokens['refresh_token'])) {
            // Create new account
            $stmt = $db->prepare("INSERT INTO dropbox_accounts (app_key, app_secret, access_token, refresh_token) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", 
                $app_key,
                $app_secret,
                $tokens['access_token'],
                $tokens['refresh_token']
            );
            $stmt->execute();
            
            header('Location: /admin/dropbox.php?success=1');
            exit;
        }
    }

    header('Location: /admin/dropbox.php?error=1');
    exit;

} catch (Exception $e) {
    header('Location: /admin/dropbox.php?error=' . urlencode($e->getMessage()));
    exit;
}
