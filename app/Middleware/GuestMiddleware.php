<?php

namespace App\Middleware;

/**
 * Guest Middleware
 * Redirect authenticated users away from guest-only pages
 */
class GuestMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        if (isset($_SESSION['user_id'])) {
            header('Location: /dashboard');
            exit();
        }
        
        return true;
    }
}
