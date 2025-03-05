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
                // Use API key as both access and secret key
                return self::validateApiKey($accessKey);
            }
            return false;
        }

        // Parse Authorization header
        if (preg_match('/AWS(?:4-HMAC-SHA256|)\s([^:]+):/', $auth, $matches)) {
            $accessKey = $matches[1];
            return self::validateApiKey($accessKey);
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
