<?php

namespace App\Middleware;

/**
 * Auth Middleware
 * Check if user is authenticated
 */
class AuthMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            // Check for remember token
            if (isset($_COOKIE['remember_token'])) {
                $db = \App\Database::getInstance()->getConnection();
                
                $stmt = $db->prepare("
                    SELECT id, name 
                    FROM users 
                    WHERE remember_token = ? 
                    AND remember_token_expires > NOW()
                ");
                $stmt->bind_param("s", $_COOKIE['remember_token']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($user = $result->fetch_assoc()) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    return true;
                }
            }
            
            // Redirect to login
            header('Location: /login');
            exit();
        }
        
        return true;
    }
}
