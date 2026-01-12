<?php

return [
    // Database Configuration
    'database' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'name' => getenv('DB_NAME') ?: 'onenetly',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: 'AmiMotiur27@',
        'charset' => 'utf8mb4',
    ], 
    
    // Site Configuration
    'site' => [
        'name' => 'OneNetly',
        'url' => getenv('SITE_URL') ?: 'http://onenetly.co',
        'base_path' => dirname(__DIR__),
    ],
    
    // File Upload Configuration
    'upload' => [
        'dir' => dirname(__DIR__) . '/uploads/',
        'max_size' => 2 * 1024 * 1024 * 1024, // 2 GB in bytes
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'zip', 'rar', 'mp4', 'mp3', 'mkv', 'avi', 'mov'],
    ],
    
    // Dropbox Configuration
    'dropbox' => [
        'max_account_size' => 2 * 1024 * 1024 * 1024, // 2 GB per account
        'auto_sync' => false,
        'delete_local_after_sync' => true,
    ],

    // SecurityConfiguration
    'security' => [
        'encryption_key' => 'your-secret-key-change-this-in-production',
        'password_algo' => PASSWORD_DEFAULT,
    ],
    
    // Session Configuration
    'session' => [
        'lifetime' => 3600 * 24 * 7, // 7 days
        'gc_maxlifetime' => 3600 * 24 * 7,
        'cookie_lifetime' => 3600 * 24 * 7,
    ],
    
    // Email Configuration
    'email' => [
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_username' => '',
        'smtp_password' => '',
        'from_email' => 'noreply@onenetly.com',
        'from_name' => 'OneNetly',
    ],
    
    // Pagination
    'pagination' => [
        'items_per_page' => 20,
    ],
    
    // File retention period (in days)
    'retention' => [
        'days' => 30,
    ],
    
    // Admin Configuration
    'admin' => [
        'email' => 'admin@onenetly.com',
    ],
    
    // API Configuration
    'api' => [
        'rate_limit' => 100, // requests per hour
    ],
    
    // Google reCAPTCHA v2 Configuration
    'recaptcha' => [
        'site_key' => '6LcvCfEqAAAAAKplp_UyXJRQOl8WohlSvnej7Mox',
        'secret_key' => '6LcvCfEqAAAAALc3f4SOs3wbcQgsxV6cZTzJo1Ma',
        'enabled' => false,
    ],
    
    // Error Reporting
    'debug' => [
        'error_reporting' => E_ALL,
        'display_errors' => 1,
    ],
    
    // Timezone
    'timezone' => 'UTC',
];
