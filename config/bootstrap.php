// Autoload dependencies
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load .env variables
if (file_exists(dirname(__DIR__) . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->load();
}

// Load configuration
$config = require_once __DIR__ . '/app.php';
/**
 * Bootstrap file
 * Initializes the application
 */

// Load configuration
$config = require_once __DIR__ . '/app.php';

// Set error reporting
error_reporting($config['debug']['error_reporting']);
ini_set('display_errors', $config['debug']['display_errors']);

// Set timezone
date_default_timezone_set($config['timezone']);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', $config['session']['gc_maxlifetime']);
    ini_set('session.cookie_lifetime', $config['session']['cookie_lifetime']);
    session_start();
}

// Define constants for backwards compatibility
define('DB_HOST', $config['database']['host']);
define('DB_NAME', $config['database']['name']);
define('DB_USER', $config['database']['user']);
define('DB_PASS', $config['database']['password']);
define('DB_CHARSET', $config['database']['charset']);

define('SITE_NAME', $config['site']['name']);
define('SITE_URL', $config['site']['url']);
define('BASE_PATH', $config['site']['base_path']);

define('UPLOAD_DIR', $config['upload']['dir']);
define('MAX_FILE_SIZE', $config['upload']['max_size']);
define('ALLOWED_EXTENSIONS', $config['upload']['allowed_extensions']);

define('ENCRYPTION_KEY', $config['security']['encryption_key']);
define('PASSWORD_HASH_ALGO', $config['security']['password_algo']);

define('SESSION_LIFETIME', $config['session']['lifetime']);

define('SMTP_HOST', $config['email']['smtp_host']);
define('SMTP_PORT', $config['email']['smtp_port']);
define('SMTP_USERNAME', $config['email']['smtp_username']);
define('SMTP_PASSWORD', $config['email']['smtp_password']);
define('SMTP_FROM_EMAIL', $config['email']['from_email']);
define('SMTP_FROM_NAME', $config['email']['from_name']);

define('ITEMS_PER_PAGE', $config['pagination']['items_per_page']);
define('FILE_RETENTION_DAYS', $config['retention']['days']);
define('ADMIN_EMAIL', $config['admin']['email']);
define('API_RATE_LIMIT', $config['api']['rate_limit']);

define('RECAPTCHA_SITE_KEY', $config['recaptcha']['site_key']);
define('RECAPTCHA_SECRET_KEY', $config['recaptcha']['secret_key']);
define('RECAPTCHA_ENABLED', $config['recaptcha']['enabled']);

// Autoload dependencies
require_once BASE_PATH . '/vendor/autoload.php';

// Load database class
require_once BASE_PATH . '/database/Database.php';

// Helper functions
require_once __DIR__ . '/helpers.php';

return $config;
