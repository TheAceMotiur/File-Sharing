<?php

namespace App\Middleware;

/**
 * Middleware Interface
 */
interface MiddlewareInterface
{
    /**
     * Handle the request
     * 
     * @return bool Return true to continue, false to stop
     */
    public function handle(): bool;
}
