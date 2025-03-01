<?php
require_once __DIR__ . '/database/Database.php';

// For direct testing without the class
function testDirectConnection() {
    $config = require __DIR__ . '/config.php';
    $db = null;
    
    try {
        $db = new mysqli(
            $config['database']['host'],
            $config['database']['username'], 
            $config['database']['password'],
            $config['database']['name']
        );
        
        if ($db->connect_error) {
            echo "Direct connection failed: " . $db->connect_error;
            return false;
        }
        
        echo "Direct connection successful!<br>";
        $db->close();
        return true;
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
        return false;
    }
}

// Test using the Database class
function testDatabaseClass() {
    try {
        $db = App\Database::getInstance();
        if ($db->testConnection()) {
            echo "Database class connection successful!<br>";
            return true;
        } else {
            echo "Database class connection failed!<br>";
            return false;
        }
    } catch (Exception $e) {
        echo "Error with Database class: " . $e->getMessage() . "<br>";
        return false;
    }
}

// Show PHP and MySQL information
echo "<h1>Database Connection Test</h1>";
echo "<h2>PHP Info</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Loaded Extensions:<br>";
echo "<pre>";
print_r(get_loaded_extensions());
echo "</pre>";

echo "<h2>MySQL Connection Tests</h2>";
echo "Testing direct connection...<br>";
testDirectConnection();

echo "<br>Testing Database class connection...<br>";
testDatabaseClass();
?>
