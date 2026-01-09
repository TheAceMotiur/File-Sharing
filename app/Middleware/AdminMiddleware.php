<?php

namespace App\Middleware;

/**
 * Admin Middleware
 * Check if user is an admin
 */
class AdminMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit();
        }
        
        if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
            header('Location: /dashboard');
            exit();
        }
        
        return true;
    }
}
