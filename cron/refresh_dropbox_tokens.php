<?php
require_once __DIR__ . '/../config/bootstrap.php';

try {
    $db = getDBConnection();
    
    // Get all Dropbox accounts
    $result = $db->query("SELECT * FROM dropbox_accounts");
    
    if (!$result) {
        throw new Exception("Failed to fetch dropbox accounts: " . $db->error);
    }
    
    $accounts = $result->fetch_all(MYSQLI_ASSOC);
    
    if (empty($accounts)) {
        echo "No Dropbox accounts found to refresh.\n";
        exit(0);
    }
    
    echo "Found " . count($accounts) . " account(s) to refresh.\n";
    
    foreach ($accounts as $account) {
        echo "\nRefreshing account ID: {$account['id']}\n";
        
        // Validate required fields
        if (empty($account['refresh_token']) || empty($account['app_key']) || empty($account['app_secret'])) {
            echo "  [ERROR] Missing required credentials for account {$account['id']}\n";
            continue;
        }
        
        // Create context for API call with error handling
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query([
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $account['refresh_token'],
                    'client_id' => $account['app_key'],
                    'client_secret' => $account['app_secret']
                ]),
                'ignore_errors' => true,
                'timeout' => 30
            ]
        ]);
        
        // Call Dropbox API to refresh token
        $response = @file_get_contents('https://api.dropboxapi.com/oauth2/token', false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            echo "  [ERROR] Failed to connect to Dropbox API: " . ($error['message'] ?? 'Unknown error') . "\n";
            continue;
        }
        
        $tokens = json_decode($response, true);
        
        // Check for API errors
        if (isset($tokens['error'])) {
            echo "  [ERROR] Dropbox API error: {$tokens['error']}\n";
            if (isset($tokens['error_description'])) {
                echo "  Description: {$tokens['error_description']}\n";
            }
            continue;
        }
        
        if (!isset($tokens['access_token'])) {
            echo "  [ERROR] No access_token in response: " . json_encode($tokens) . "\n";
            continue;
        }
        
        // Update tokens in database
        $stmt = $db->prepare("UPDATE dropbox_accounts SET access_token = ?, updated_at = NOW() WHERE id = ?");
        if (!$stmt) {
            echo "  [ERROR] Failed to prepare statement: " . $db->error . "\n";
            continue;
        }
        
        $stmt->bind_param("si", $tokens['access_token'], $account['id']);
        
        if ($stmt->execute()) {
            echo "  [SUCCESS] Token refreshed successfully\n";
        } else {
            echo "  [ERROR] Failed to update token in database: " . $stmt->error . "\n";
        }
        
        $stmt->close();
    }
    
    echo "\n=== Token refresh process completed ===\n";

} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}