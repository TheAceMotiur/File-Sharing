<?php
require_once __DIR__ . '/config.php';
session_start();

try {
    // Load database configuration and get connection
    $db = getDBConnection();
    
    // Clear remember token if it exists
    if (isset($_COOKIE['remember_token']) && isset($_SESSION['user_id'])) {
        // Clear the remember token from database
        $stmt = $db->prepare("UPDATE users SET remember_token = NULL, remember_token_expires = NULL WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        
        // Delete the cookie
        setcookie('remember_token', '', time() - 3600, '/');
    }

    // Destroy session
    session_unset();
    session_destroy();

} catch (Exception $e) {
    // Log error but continue with logout
    error_log("Logout error: " . $e->getMessage());
}

// Always redirect to login
header('Location: login');
exit;
?>