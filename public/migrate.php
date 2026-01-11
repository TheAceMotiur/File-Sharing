<?php
/**
 * Web-accessible Migration Runner
 * Access: https://onenetly.com/migrate.php
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../database/Migration.php';

// Set content type to plain text for better readability
header('Content-Type: text/plain');

try {
    $db = getDBConnection();
    $migration = new App\Migration($db);
    
    echo "===========================================\n";
    echo "  DATABASE MIGRATION RUNNER\n";
    echo "===========================================\n\n";
    echo "Starting migrations...\n\n";
    
    $migration->migrate();
    
    echo "\n===========================================\n";
    echo "✓ All migrations completed successfully!\n";
    echo "===========================================\n";
    
} catch (Exception $e) {
    echo "\n===========================================\n";
    echo "✗ Migration failed!\n";
    echo "===========================================\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    http_response_code(500);
}
