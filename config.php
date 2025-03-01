<?php

// Set session cookie to last for 30 days
ini_set('session.cookie_lifetime', 30 * 24 * 60 * 60); // 30 days in seconds
ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);

define('RECAPTCHA_SITE_KEY', '6LfEK8oqAAAAAA4X-xursRqDCIMD4AxPyWjyeIEw');
define('RECAPTCHA_SECRET_KEY', '6LfEK8oqAAAAAKHB_uMx8EaBW4oaYJnAbTf33HLg');
define('DB_MAX_RETRIES', 3);
define('DB_RETRY_DELAY', 1); // Delay in seconds between retries

function getDBConnection() {
    static $db = null;
    
    if ($db !== null) {
        // Check if connection is still alive
        if ($db->ping()) {
            return $db;
        }
        // Connection lost, close it
        $db->close();
        $db = null;
    }
    
    $host = 'localhost';
    $dbname = 'TheAceMotiur_fileswith';
    $username = 'TheAceMotiur_fileswith';
    $password = 'AmiMotiur27@';
    
    $retries = 0;
    while ($retries < DB_MAX_RETRIES) {
        try {
            $db = new mysqli($host, $username, $password, $dbname);
            
            if ($db->connect_error) {
                throw new Exception("Connection failed: " . $db->connect_error);
            }
            
            // Configure connection
            $db->set_charset('utf8mb4');
            
            // Set timeouts
            $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
            $db->options(MYSQLI_OPT_READ_TIMEOUT, 30);
            $db->options(MYSQLI_OPT_WRITE_TIMEOUT, 30);
            
            // Enable error reporting
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            
            // Test connection with a simple query
            $db->query("SELECT 1");
            
            return $db;
            
        } catch (Exception $e) {
            $retries++;
            if ($retries >= DB_MAX_RETRIES) {
                error_log("Database connection failed after {$retries} attempts: " . $e->getMessage());
                throw new Exception("Database connection failed. Please try again later.");
            }
            // Wait before retrying
            sleep(DB_RETRY_DELAY);
        }
    }
}

// Add a helper function to safely execute queries with retries
function executeQuery($db, $callback) {
    $retries = 0;
    while ($retries < DB_MAX_RETRIES) {
        try {
            return $callback($db);
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 2006) { // MySQL server has gone away
                $retries++;
                if ($retries >= DB_MAX_RETRIES) {
                    throw $e;
                }
                // Get fresh connection
                $db = getDBConnection();
                continue;
            }
            throw $e;
        }
    }
}
?>