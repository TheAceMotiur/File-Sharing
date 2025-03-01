<?php

// Set session cookie to last for 30 days
ini_set('session.cookie_lifetime', 30 * 24 * 60 * 60); // 30 days in seconds
ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);

define('RECAPTCHA_SITE_KEY', '6LfEK8oqAAAAAA4X-xursRqDCIMD4AxPyWjyeIEw');
define('RECAPTCHA_SECRET_KEY', '6LfEK8oqAAAAAKHB_uMx8EaBW4oaYJnAbTf33HLg');

function getDBConnection() {
    static $db = null;
    
    if ($db === null || !$db->ping()) {
        $host = 'localhost';
        $dbname = 'TheAceMotiur_fileswith';
        $username = 'TheAceMotiur_fileswith';
        $password = 'AmiMotiur27@';

        try {
            // Close existing connection if it exists
            if ($db !== null) {
                $db->close();
            }

            // Create new connection with persistent settings
            $db = new mysqli($host, $username, $password, $dbname);

            if ($db->connect_error) {
                throw new Exception("Connection failed: " . $db->connect_error);
            }

            // Configure connection
            $db->set_charset('utf8mb4');
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

            // Set connection timeout and wait_timeout
            $db->query("SET SESSION wait_timeout=28800"); // 8 hours
            $db->query("SET SESSION interactive_timeout=28800");
            
            // Enable reconnect
            $db->options(MYSQLI_OPT_RECONNECT, true);

        } catch(Exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Connection failed. Please check database configuration.");
        }
    }
    
    return $db;
}
?>