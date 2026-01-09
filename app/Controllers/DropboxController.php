<?php

namespace App\Controllers;

use App\Core\Controller;

/**
 * Dropbox Controller
 * Handles Dropbox OAuth callbacks
 */
class DropboxController extends Controller
{
    /**
     * Handle Dropbox OAuth callback
     */
    public function callback()
    {
        // Verify admin status
        if (!isset($_SESSION['user_id'])) {
            return $this->redirect('/login');
        }

        $db = getDBConnection();
        
        // Verify admin status
        $stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if (!$user || !$user['is_admin']) {
            return $this->redirect('/dashboard');
        }

        try {
            // Handle OAuth callback
            if (isset($_GET['code']) && isset($_GET['state'])) {
                // Decode state parameter
                $state = json_decode(base64_decode($_GET['state']), true);
                $code = $_GET['code'];
                
                if (!$state || !isset($state['app_key']) || !isset($state['app_secret'])) {
                    throw new \Exception('Invalid state parameter');
                }

                $accountId = $state['account_id'] ?? null;
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
                            'redirect_uri' => 'https://' . $_SERVER['HTTP_HOST'] . '/dropbox/callback'
                        ])
                    ]
                ]));

                $tokens = json_decode($response, true);

                if (isset($tokens['access_token']) && isset($tokens['refresh_token'])) {
                    if ($accountId) {
                        // Update existing account
                        $stmt = $db->prepare("UPDATE dropbox_accounts 
                                             SET access_token = ?, refresh_token = ?, updated_at = NOW() 
                                             WHERE id = ?");
                        $stmt->bind_param("ssi", 
                            $tokens['access_token'],
                            $tokens['refresh_token'],
                            $accountId
                        );
                        $stmt->execute();
                    } else {
                        // Create new account (fallback)
                        $stmt = $db->prepare("INSERT INTO dropbox_accounts (app_key, app_secret, access_token, refresh_token) 
                                             VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("ssss", 
                            $app_key,
                            $app_secret,
                            $tokens['access_token'],
                            $tokens['refresh_token']
                        );
                        $stmt->execute();
                    }
                    
                    return $this->redirect('/admin/dropbox?success=added');
                }
            }

            return $this->redirect('/admin/dropbox?error=1');

        } catch (\Exception $e) {
            return $this->redirect('/admin/dropbox?error=' . urlencode($e->getMessage()));
        }
    }
}
