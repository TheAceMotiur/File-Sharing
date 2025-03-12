<?php

// Set session cookie to last for 30 days
ini_set('session.cookie_lifetime', 30 * 24 * 60 * 60); // 30 days in seconds
ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);

define('RECAPTCHA_SITE_KEY', '6Le9P8oqAAAAAE8mla042L3U6G9ti1iZKsGUnork');
define('RECAPTCHA_SECRET_KEY', '6Le9P8oqAAAAAEZkNLFldwAI2P-3diQv8UK2FVnQ');

function getDBConnection() {
    static $db = null;
      
    if ($db === null) {
        $host = 'localhost';
        $dbname = 'TheAceMotiur_freenetly';
        $username = 'TheAceMotiur_freenetly';
        $password = 'AmiMotiur27@';

        try {
            $db = new mysqli($host, $username, $password, $dbname);

            if ($db->connect_error) {
                throw new Exception("Connection failed: " . $db->connect_error);
            }

            // Set charset to utf8mb4
            $db->set_charset('utf8mb4');

            // Set error reporting
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        } catch(Exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Connection failed. Please check database configuration.");
        }
    }
    
    return $db;
}

function getSiteName() {
    $db = getDBConnection();
    $result = $db->query("SELECT setting_value FROM site_settings WHERE setting_key = 'site_name'");
    if ($row = $result->fetch_assoc()) {
        return htmlspecialchars($row['setting_value']);
    }
    return 'FreeNetly'; // Fallback name
}
?>