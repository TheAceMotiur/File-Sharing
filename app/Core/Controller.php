<?php

namespace App\Core;

/**
 * Base Controller Class
 * All controllers should extend this class
 */
class Controller
{
    /**
     * Load a view file
     * 
     * @param string $view View name (path relative to Views folder)
     * @param array $data Data to pass to the view
     * @return void
     */
    protected function view(string $view, array $data = []): void
    {
        // Extract data array to variables
        extract($data);
        
        // Build the view file path
        $viewFile = __DIR__ . '/../Views/' . $view . '.php';
        
        if (file_exists($viewFile)) {
            require_once $viewFile;
        } else {
            die("View '{$view}' not found.");
        }
    }
    
    /**
     * Load a model
     * 
     * @param string $model Model name
     * @return object
     */
    protected function model(string $model): object
    {
        $modelClass = "App\\Models\\" . $model;
        
        if (class_exists($modelClass)) {
            return new $modelClass();
        }
        
        die("Model '{$model}' not found.");
    }
    
    /**
     * Redirect to another URL
     * 
     * @param string $url URL to redirect to
     * @return void
     */
    protected function redirect(string $url): void
    {
        header("Location: " . $url);
        exit();
    }
    
    /**
     * Get JSON input from request body
     * 
     * @return array
     */
    protected function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }
    
    /**
     * Return JSON response
     * 
     * @param mixed $data Data to return
     * @param int $statusCode HTTP status code
     * @return void
     */
    protected function json($data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
    
    /**
     * Check if request is POST
     * 
     * @return bool
     */
    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
    
    /**
     * Check if request is GET
     * 
     * @return bool
     */
    protected function isGet(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }
    
    /**
     * Get POST data with optional default value
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function post(string $key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }
    
    /**
     * Get GET data with optional default value
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function get(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }
}
