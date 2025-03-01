<?php
require_once __DIR__ . '/database/Database.php';
header('Content-Type: application/json');

try {
    // Get database instance
    $db = App\Database::getInstance();
    $conn = $db->getConnection();
    
    // Check if connection is active
    if (!$conn->ping()) {
        // Try to reconnect
        $db->reconnect();
        $conn = $db->getConnection();
        
        if (!$conn->ping()) {
            throw new Exception("Database connection failed even after reconnection attempt");
        }
    }
    
    // Get timeout values
    $timeouts = [
        'wait_timeout' => null,
        'interactive_timeout' => null,
        'net_read_timeout' => null,
        'net_write_timeout' => null,
    ];
    
    foreach ($timeouts as $var => $value) {
        $result = $conn->query("SHOW VARIABLES LIKE '$var'");
        if ($result && $row = $result->fetch_assoc()) {
            $timeouts[$var] = $row['Value'];
        }
    }
    
    // Return success result
    echo json_encode([
        'success' => true,
        'message' => 'Database connection is working',
        'timeouts' => $timeouts
    ]);
} catch (Exception $e) {
    // Return error result
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
