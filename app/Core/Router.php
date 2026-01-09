<?php

namespace App\Core;

/**
 * Router Class
 * Handles routing of requests to controllers
 */
class Router
{
    protected $routes = [];
    protected $params = [];
    
    /**
     * Add a GET route
     * 
     * @param string $route
     * @param string $controller Controller and method (Controller@method)
     * @param array $middleware
     */
    public function get(string $route, string $controller, array $middleware = []): void
    {
        $this->addRoute($route, $controller, 'GET', $middleware);
    }
    
    /**
     * Add a POST route
     * 
     * @param string $route
     * @param string $controller Controller and method (Controller@method)
     * @param array $middleware
     */
    public function post(string $route, string $controller, array $middleware = []): void
    {
        $this->addRoute($route, $controller, 'POST', $middleware);
    }
    
    /**
     * Add a route that handles both GET and POST
     * 
     * @param string $route
     * @param string $controller Controller and method (Controller@method)
     * @param array $middleware
     */
    public function any(string $route, string $controller, array $middleware = []): void
    {
        $this->addRoute($route, $controller, 'GET|POST', $middleware);
    }
    
    /**
     * Add a route to the routes array
     * 
     * @param string $route
     * @param string $controller
     * @param string $method
     * @param array $middleware
     */
    protected function addRoute(string $route, string $controller, string $method, array $middleware): void
    {
        $this->routes[] = [
            'route' => $route,
            'controller' => $controller,
            'method' => $method,
            'middleware' => $middleware
        ];
    }
    
    /**
     * Dispatch the request
     * 
     * @param string $url
     * @return void
     */
    public function dispatch(string $url): void
    {
        $url = $this->formatUrl($url);
        $method = $_SERVER['REQUEST_METHOD'];
        
        foreach ($this->routes as $route) {
            // Check if method matches
            if (!preg_match('/^' . $route['method'] . '$/i', $method)) {
                continue;
            }
            
            // Convert route pattern to regex
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([a-zA-Z0-9_-]+)', $route['route']);
            $pattern = '#^' . $pattern . '$#';
            
            if (preg_match($pattern, $url, $matches)) {
                array_shift($matches); // Remove the full match
                
                // Run middleware
                if (!empty($route['middleware'])) {
                    foreach ($route['middleware'] as $middlewareClass) {
                        $middleware = new $middlewareClass();
                        if (!$middleware->handle()) {
                            return; // Middleware stopped the request
                        }
                    }
                }
                
                // Parse controller and method
                list($controller, $action) = explode('@', $route['controller']);
                
                $controllerClass = "App\\Controllers\\" . $controller;
                
                if (!class_exists($controllerClass)) {
                    die("Controller '{$controller}' not found.");
                }
                
                $controllerInstance = new $controllerClass();
                
                if (!method_exists($controllerInstance, $action)) {
                    die("Method '{$action}' not found in controller '{$controller}'.");
                }
                
                // Call the controller method with parameters
                call_user_func_array([$controllerInstance, $action], $matches);
                return;
            }
        }
        
        // No route matched
        $this->notFound();
    }
    
    /**
     * Format the URL
     * 
     * @param string $url
     * @return string
     */
    protected function formatUrl(string $url): string
    {
        $url = trim($url, '/');
        $url = filter_var($url, FILTER_SANITIZE_URL);
        return $url ?: '/';
    }
    
    /**
     * Handle 404 Not Found
     */
    protected function notFound(): void
    {
        http_response_code(404);
        echo "404 - Page Not Found";
        exit();
    }
}
