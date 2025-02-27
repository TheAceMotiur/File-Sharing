<?php
require_once __DIR__ . '/config.php';

// Start by getting the database connection
$db = getDBConnection();

// Include the Migration class
require_once __DIR__ . '/database/Migration.php';

try {
    echo "Starting migrations...\n";
    $migration = new App\Migration($db);
    $migration->migrate();
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
