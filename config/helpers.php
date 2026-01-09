<?php

/**
 * Helper Functions
 */

/**
 * Get database connection
 */
function getDB() {
    return \App\Database::getInstance();
}

/**
 * Alias for compatibility
 */
function getDBConnection() {
    return getDB()->getConnection();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

/**
 * Redirect to URL
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Sanitize input
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get site name from database or config
 */
function getSiteName() {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT value FROM site_settings WHERE setting_key = 'site_name' LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return $row['value'];
        }
    } catch (Exception $e) {
        // If database query fails, return the constant
    }
    return SITE_NAME;
}

/**
 * Get configuration value
 */
function config($key, $default = null) {
    global $config;
    
    $keys = explode('.', $key);
    $value = $config;
    
    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return $default;
        }
        $value = $value[$k];
    }
    
    return $value;
}

/**
 * Update premium status
 */
function updatePremiumStatus($userId, $db) {
    $stmt = $db->prepare("SELECT premium_until FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result && $result['premium_until']) {
        $isPremium = strtotime($result['premium_until']) > time();
        $_SESSION['premium'] = $isPremium ? 1 : 0;
        $_SESSION['user_premium'] = $_SESSION['premium'];
    }
}

/**
 * Asset helper - get URL for public assets
 */
function asset($path) {
    return '/' . ltrim($path, '/');
}

/**
 * URL helper - generate URL
 */
function url($path = '') {
    return SITE_URL . '/' . ltrim($path, '/');
}

/**
 * View helper - render a view
 */
function view($view, $data = []) {
    extract($data);
    $viewFile = BASE_PATH . '/app/Views/' . $view . '.php';
    
    if (file_exists($viewFile)) {
        require $viewFile;
    } else {
        die("View '{$view}' not found.");
    }
}
