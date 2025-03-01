<?php
// First, check and close any active session
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Configure session settings
session_set_cookie_params([
    'lifetime' => 30 * 24 * 60 * 60,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Set garbage collection lifetime
ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60);

define('RECAPTCHA_SITE_KEY', '6LfEK8oqAAAAAA4X-xursRqDCIMD4AxPyWjyeIEw');
define('RECAPTCHA_SECRET_KEY', '6LfEK8oqAAAAAKHB_uMx8EaBW4oaYJnAbTf33HLg');

// Database configuration
$config = [
    'database' => [
        'host' => 'localhost',
        'name' => 'TheAceMotiur_fileswith',
        'username' => 'TheAceMotiur_fileswith',
        'password' => 'AmiMotiur27@',
        'charset' => 'utf8mb4',
        'timeout' => 86400 // 24 hours
    ]
];

function getDBConnection() {
    global $config;
    static $db = null;
    
    if ($db === null) {
        try {
            $db = new mysqli(
                $config['database']['host'],
                $config['database']['username'],
                $config['database']['password'],
                $config['database']['name']
            );

            if ($db->connect_error) {
                throw new Exception("Connection failed: " . $db->connect_error);
            }

            // Set charset to utf8mb4
            $db->set_charset($config['database']['charset']);
            
            // Increase timeout settings
            $db->query("SET SESSION wait_timeout=" . $config['database']['timeout']);
            $db->query("SET SESSION interactive_timeout=" . $config['database']['timeout']);

            // Set error reporting
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        } catch(Exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Connection failed. Please check database configuration.");
        }
    }
    
    return $db;
}

return $config;
?>