<?php
class S3Auth {
    public static function authenticate() {
        $headers = getallheaders();
        
        // Get Authorization header
        $auth = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        // If no Authorization header, check for query parameters
        if (!$auth) {
            $accessKey = $_GET['AWSAccessKeyId'] ?? null;
            if ($accessKey) {
                return self::validateApiKey($accessKey);
            }
        } else {
            // Parse AWS v4 signature
            if (preg_match('/AWS4-HMAC-SHA256\s+Credential=([^\/]+)\//', $auth, $matches)) {
                return self::validateApiKey($matches[1]);
            }
            // Parse AWS v2 signature
            if (preg_match('/AWS\s+([^:]+):/', $auth, $matches)) {
                return self::validateApiKey($matches[1]);
            }
        }

        return false;
    }

    private static function validateApiKey($key) {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM api_keys WHERE api_key = ?");
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            // Update last used timestamp
            $stmt = $db->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $row['id']);
            $stmt->execute();
            return true;
        }
        
        return false;
    }
}
