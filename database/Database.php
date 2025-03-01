<?php

namespace App;

use mysqli;
use Exception;

class Database {
    private static $instance = null;
    private $connection;
    private $config;

    private function __construct() {
        // Load config file
        $this->config = require __DIR__ . '/../config.php';
        
        try {
            $this->connect();
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            die("Database connection failed. Please check your configuration.");
        }
    }

    /**
     * Connect to the database
     */
    private function connect() {
        $dbConfig = $this->config['database'];
        
        $this->connection = new mysqli(
            $dbConfig['host'],
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['name']
        );

        if ($this->connection->connect_error) {
            throw new Exception("Connection failed: " . $this->connection->connect_error);
        }

        // Configure connection
        $this->connection->set_charset($dbConfig['charset']);
        
        // Set timeout settings to prevent "gone away" errors during long operations
        $this->connection->query("SET SESSION wait_timeout=" . $dbConfig['timeout']);
        $this->connection->query("SET SESSION interactive_timeout=" . $dbConfig['timeout']);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        // Check if connection is still alive
        if (!$this->connection || !$this->connection->ping()) {
            try {
                $this->reconnect();
            } catch (Exception $e) {
                error_log("Database reconnection error: " . $e->getMessage());
                die("Lost database connection and failed to reconnect.");
            }
        }
        return $this->connection;
    }
    
    /**
     * Reconnect to the database if connection is lost
     */
    public function reconnect() {
        if ($this->connection) {
            $this->connection->close();
        }
        
        $this->connect();
    }
    
    /**
     * Test database connection
     * 
     * @return bool
     */
    public function testConnection() {
        try {
            return $this->connection && $this->connection->ping();
        } catch (Exception $e) {
            return false;
        }
    }
}