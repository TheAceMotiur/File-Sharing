<?php

// Set session cookie to last for 30 days
ini_set('session.cookie_lifetime', 30 * 24 * 60 * 60); // 30 days in seconds
ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);

// Set upload limits for 2GB files
ini_set('upload_max_filesize', '2048M');
ini_set('post_max_size', '2048M');
ini_set('memory_limit', '2048M');
ini_set('max_execution_time', '3600');
ini_set('max_input_time', '3600');

define('RECAPTCHA_SITE_KEY', '6LfEK8oqAAAAAA4X-xursRqDCIMD4AxPyWjyeIEw');
define('RECAPTCHA_SECRET_KEY', '6LfEK8oqAAAAAKHB_uMx8EaBW4oaYJnAbTf33HLg');

function getDBConnection() {
    static $db = null;
    
    if ($db === null) {
        $host = 'localhost';
        $dbname = 'TheAceMotiur_fileswith';
        $username = 'TheAceMotiur_fileswith';
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
?>