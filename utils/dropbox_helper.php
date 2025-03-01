<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Spatie\Dropbox\Client as DropboxClient;

/**
 * Helper class for Dropbox operations with better error handling
 */
class DropboxHelper {
    /**
     * Get an available Dropbox account with sufficient storage
     * 
     * @param int $requiredSpace Space needed in bytes
     * @return array|null Account details or null if no account with space is available
     */
    public static function getAvailableAccount($requiredSpace = 0) {
        $db = getDBConnection();
        
        // First try to get accounts with confirmed available space
        $query = "
            SELECT da.*, 
                   COALESCE(SUM(fu.size), 0) as used_storage
            FROM dropbox_accounts da
            LEFT JOIN file_uploads fu ON fu.dropbox_account_id = da.id 
                AND fu.upload_status = 'completed'
            GROUP BY da.id
            HAVING (used_storage + ?) <= 2147483648 OR used_storage IS NULL
            ORDER BY used_storage ASC
            LIMIT 1
        ";
        
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $requiredSpace);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        
        // If no account with sufficient space was found, try to verify space directly with Dropbox API
        if (!$account) {
            $accounts = $db->query("SELECT * FROM dropbox_accounts")->fetch_all(MYSQLI_ASSOC);
            
            foreach ($accounts as $acc) {
                try {
                    $spaceInfo = self::getAccountSpaceInfo($acc);
                    $availableSpace = $spaceInfo['allocation']['allocated'] - $spaceInfo['used'];
                    
                    if ($availableSpace >= $requiredSpace) {
                        return $acc;
                    }
                } catch (Exception $e) {
                    error_log("Error checking Dropbox space for account ID {$acc['id']}: " . $e->getMessage());
                    continue;
                }
            }
            
            return null;
        }
        
        return $account;
    }
    
    /**
     * Get space info for a Dropbox account
     * 
     * @param array $account Dropbox account details
     * @return array Space usage information
     */
    public static function getAccountSpaceInfo($account) {
        try {
            // Check if token needs to be refreshed
            if (self::isTokenExpired($account['access_token'])) {
                $account = self::refreshToken($account);
            }
            
            $client = new DropboxClient($account['access_token']);
            $response = $client->rpcEndpointRequest('users/get_space_usage');
            return json_decode($response, true);
        } catch (Exception $e) {
            error_log("Failed to get account space info: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Check if a Dropbox access token is expired
     * 
     * @param string $token Dropbox access token
     * @return bool True if expired, false otherwise
     */
    public static function isTokenExpired($token) {
        try {
            // Simple check by trying to make a request
            $ch = curl_init('https://api.dropboxapi.com/2/users/get_current_account');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json' 
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "{}");
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 401; // 401 = Unauthorized, token is likely expired
        } catch (Exception $e) {
            error_log("Error checking token expiration: " . $e->getMessage());
            return true; // Assume expired on error
        }
    }
    
    /**
     * Refresh a Dropbox access token
     * 
     * @param array $account Dropbox account details
     * @return array Updated account details
     */
    public static function refreshToken($account) {
        try {
            $db = getDBConnection();
            
            $ch = curl_init('https://api.dropboxapi.com/oauth2/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'grant_type' => 'refresh_token',
                'refresh_token' => $account['refresh_token'],
                'client_id' => $account['app_key'],
                'client_secret' => $account['app_secret']
            ]));
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $data = json_decode($response, true);
            
            if (!isset($data['access_token'])) {
                throw new Exception('Failed to refresh token: ' . ($data['error_description'] ?? 'Unknown error'));
            }
            
            // Update database with new tokens
            $stmt = $db->prepare("UPDATE dropbox_accounts SET access_token = ? WHERE id = ?");
            $stmt->bind_param("si", $data['access_token'], $account['id']);
            $stmt->execute();
            
            // Update the account array
            $account['access_token'] = $data['access_token'];
            
            return $account;
        } catch (Exception $e) {
            error_log("Token refresh error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Upload a file to Dropbox with better error handling
     * 
     * @param string $filePath Path to the local file
     * @param string $dropboxPath Destination path in Dropbox
     * @param string $accessToken Dropbox access token
     * @return bool Success status
     */
    public static function uploadFile($filePath, $dropboxPath, $accessToken) {
        try {
            $client = new DropboxClient($accessToken);
            
            // For larger files, use a file handle to avoid memory issues
            $handle = fopen($filePath, 'rb');
            if (!$handle) {
                throw new Exception("Could not open file for upload: $filePath");
            }
            
            try {
                $client->upload($dropboxPath, $handle, 'add');
                fclose($handle);
                return true;
            } catch (Exception $e) {
                fclose($handle);
                
                // Check for specific Dropbox errors
                $message = $e->getMessage();
                if (strpos($message, 'path/insufficient_space') !== false) {
                    throw new Exception('Insufficient space in Dropbox account');
                }
                
                throw $e;
            }
        } catch (Exception $e) {
            error_log("Dropbox upload error: " . $e->getMessage());
            throw $e;
        }
    }
}
