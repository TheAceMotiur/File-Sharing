<?php

namespace App;

use mysqli;

class Database {
    private static $instance = null;
    private $connection;
    private $config;

    private function __construct() {
        $this->config = require __DIR__ . '/../config.php';
        $dbConfig = $this->config['database'];
        
        $this->connection = new mysqli(
            $dbConfig['host'],
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['name']
        );

        if ($this->connection->connect_error) {
            die("Connection failed: " . $this->connection->connect_error);
        }

        $this->connection->set_charset($dbConfig['charset']);
        
        // Set session wait_timeout to prevent "gone away" errors during long uploads
        $this->connection->query("SET SESSION wait_timeout=86400"); // 24 hours
        $this->connection->query("SET SESSION interactive_timeout=86400"); // 24 hours
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        // Check if connection is still alive
        if (!$this->connection->ping()) {
            $this->reconnect();
        }
        return $this->connection;
    }
    
    /**
     * Reconnect to the database if connection is lost
     */
    public function reconnect() {
        $this->connection->close();
        
        $dbConfig = $this->config['database'];
        $this->connection = new mysqli(
            $dbConfig['host'],
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['name']
        );

        if ($this->connection->connect_error) {
            die("Reconnection failed: " . $this->connection->connect_error);
        }

        $this->connection->set_charset($dbConfig['charset']);
        $this->connection->query("SET SESSION wait_timeout=86400");
        $this->connection->query("SET SESSION interactive_timeout=86400");
    }
}