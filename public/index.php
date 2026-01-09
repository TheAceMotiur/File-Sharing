<?php

/**
 * Front Controller
 * Entry point for all requests
 */

// Load bootstrap
require_once __DIR__ . '/../config/bootstrap.php';

// Create application instance
$app = new App\Core\App();

// Get router
$router = $app->getRouter();

// Load routes
require_once __DIR__ . '/../routes/web.php';

// Run the application
$app->run();
