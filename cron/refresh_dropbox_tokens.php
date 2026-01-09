<?php
require_once __DIR__ . '/../config/bootstrap.php';

try {
    $db = getDBConnection();
    
    // Get all Dropbox accounts
    $accounts = $db->query("SELECT * FROM dropbox_accounts")->fetch_all(MYSQLI_ASSOC);
    
    foreach ($accounts as $account) {
        // Call Dropbox API to refresh token
        $response = file_get_contents('https://api.dropboxapi.com/oauth2/token', false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query([
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $account['refresh_token'],
                    'client_id' => $account['app_key'],
                    'client_secret' => $account['app_secret']
                ])
            ]
        ]));

        $tokens = json_decode($response, true);

        if (isset($tokens['access_token'])) {
            // Update tokens in database
            $stmt = $db->prepare("UPDATE dropbox_accounts SET access_token = ? WHERE id = ?");
            $stmt->bind_param("si", $tokens['access_token'], $account['id']);
            $stmt->execute();
        }
    }

    echo "Tokens refreshed successfully\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}