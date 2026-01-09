<?php

namespace App\Core;

/**
 * Application Class
 * Main application bootstrapper
 */
class App
{
    protected $router;
    
    public function __construct()
    {
        $this->router = new Router();
    }
    
    /**
     * Get the router instance
     * 
     * @return Router
     */
    public function getRouter(): Router
    {
        return $this->router;
    }
    
    /**
     * Run the application
     */
    public function run(): void
    {
        // Get the URL from query string or use root
        $url = $_GET['url'] ?? '/';
        
        // Dispatch the request
        $this->router->dispatch($url);
    }
}
