<?php
/**
 * Simple Router - Works on both Apache and Nginx
 * Front controller pattern for clean URLs
 */

require_once __DIR__ . '/config.php';

// Get the requested URI and clean it
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request_uri = trim($request_uri, '/');

// Define routes - URL pattern => PHP file
$routes = [
    // Public routes
    '' => 'index.php',
    'index' => 'index.php',
    'login' => 'login.php',
    'register' => 'register.php',
    'logout' => 'logout.php',
    'forgot-password' => 'forgot-password.php',
    'reset-password' => 'reset-password.php',
    'verify' => 'verify.php',
    'terms' => 'terms.php',
    'privacy' => 'privacy.php',
    'dmca' => 'dmca.php',
    'docs' => 'docs.php',
    'report' => 'report.php',
    
    // User routes
    'dashboard' => 'dashboard.php',
    'upload' => 'upload.php',
    'profile' => 'profile.php',
    'keys' => 'keys.php',
    
    // Admin routes
    'admin' => 'admin/dashboard.php',
    'admin/dashboard' => 'admin/dashboard.php',
    'admin/users' => 'admin/users.php',
    'admin/files' => 'admin/files.php',
    'admin/settings' => 'admin/settings.php',
    'admin/email-settings' => 'admin/email-settings.php',
    'admin/dropbox' => 'admin/dropbox.php',
    'admin/reports' => 'admin/reports.php',
    'admin/cron' => 'admin/cron.php',
];

// Handle download routes with pattern matching
if (preg_match('#^download/([a-zA-Z0-9]+)(/download|/preview)?$#', $request_uri, $matches)) {
    $_GET['id'] = $matches[1];
    if (isset($matches[2])) {
        if ($matches[2] === '/download') {
            $_GET['download'] = 1;
        } elseif ($matches[2] === '/preview') {
            $_GET['preview'] = 1;
        }
    }
    require __DIR__ . '/download.php';
    exit;
}

// Handle API routes
if (strpos($request_uri, 'api/') === 0) {
    $api_file = __DIR__ . '/' . $request_uri . '.php';
    if (file_exists($api_file)) {
        require $api_file;
        exit;
    }
}

// Check if route exists in our routes array
if (array_key_exists($request_uri, $routes)) {
    $file = __DIR__ . '/' . $routes[$request_uri];
    if (file_exists($file)) {
        require $file;
        exit;
    }
}

// Try to find a matching PHP file directly
$direct_file = __DIR__ . '/' . $request_uri . '.php';
if (file_exists($direct_file)) {
    require $direct_file;
    exit;
}

// Handle static files (let the server handle them naturally)
$static_file = __DIR__ . '/' . $request_uri;
if (file_exists($static_file) && !is_dir($static_file)) {
    return false; // Let the server handle static files
}

// 404 - Not Found
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php include __DIR__ . '/header.php'; ?>
    
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="text-center">
            <h1 class="text-6xl font-bold text-gray-900 mb-4">404</h1>
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Page Not Found</h2>
            <p class="text-gray-600 mb-8">The page you're looking for doesn't exist.</p>
            <a href="/" class="inline-block px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                Go Back Home
            </a>
        </div>
    </div>
    
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
