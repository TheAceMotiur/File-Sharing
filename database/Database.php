<?php

namespace App;

use mysqli;

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        // Load config if not already loaded
        if (!defined('DB_HOST')) {
            $config = require __DIR__ . '/../config/app.php';
            define('DB_HOST', $config['database']['host']);
            define('DB_NAME', $config['database']['name']);
            define('DB_USER', $config['database']['user']);
            define('DB_PASS', $config['database']['password']);
            define('DB_CHARSET', $config['database']['charset']);
        }
        
        $this->connection = new mysqli(
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME
        );

        if ($this->connection->connect_error) {
            die("Connection failed: " . $this->connection->connect_error);
        }

        $this->connection->set_charset(DB_CHARSET);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }
}