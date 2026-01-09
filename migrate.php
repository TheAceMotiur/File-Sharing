<?php
/**
 * Database Migration Runner
 * Run this to execute all pending migrations
 */

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/database/Migration.php';

try {
    $db = getDBConnection();
    $migration = new App\Migration($db);
    
    echo "Running migrations...\n";
    $migration->migrate();
    echo "All migrations completed successfully!\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
