<?php
require_once __DIR__ . '/database/Database.php';

use App\Database;

/**
 * Safely execute a database query during an upload process
 * Handles reconnection if the MySQL server has gone away
 * 
 * @param string $query SQL query to execute
 * @param array $params Parameters for prepared statement
 * @param string $types Types of parameters (i: integer, s: string, d: double, b: blob)
 * @return mixed Query result or false on failure
 */
function executeUploadQuery($query, $params = [], $types = '') {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            // Check if it's a "server has gone away" error
            if ($conn->errno == 2006 || $conn->errno == 2013) {
                logUploadActivity('MySQL server gone away, attempting reconnection', 'warning');
                
                // Try to reconnect
                $db->reconnect();
                $conn = $db->getConnection();
                
                // Retry preparing the statement
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception('Failed to prepare statement after reconnection: ' . $conn->error);
                }
            } else {
                throw new Exception('Failed to prepare statement: ' . $conn->error);
            }
        }
        
        // Bind parameters if any
        if (!empty($params) && !empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        
        // Execute the query
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute statement: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        logUploadActivity('Database error: ' . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Save upload status to database with reconnection support
 * 
 * @param string $fileId Unique file ID
 * @param int $userId User ID
 * @param string $status Upload status
 * @param string $fileName Original file name
 * @param int $fileSize File size in bytes
 * @return bool Success status
 */
function saveUploadStatus($fileId, $userId, $status, $fileName, $fileSize = 0) {
    $query = "INSERT INTO uploads (file_id, user_id, status, file_name, file_size, created_at) 
              VALUES (?, ?, ?, ?, ?, NOW())
              ON DUPLICATE KEY UPDATE 
              status = VALUES(status), 
              updated_at = NOW()";
    
    $result = executeUploadQuery($query, [$fileId, $userId, $status, $fileName, $fileSize], 'sissi');
    return $result !== false;
}

/**
 * Update upload progress in database with reconnection support
 * 
 * @param string $fileId Unique file ID
 * @param int $progress Progress percentage (0-100)
 * @return bool Success status
 */
function updateUploadProgress($fileId, $progress) {
    $query = "UPDATE uploads SET progress = ?, updated_at = NOW() WHERE file_id = ?";
    $result = executeUploadQuery($query, [$progress, $fileId], 'is');
    return $result !== false;
}

/**
 * Gets the database error status and logs it
 * 
 * @return array Error information
 */
function getDatabaseErrorInfo() {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $error = [
        'code' => $conn->errno,
        'message' => $conn->error,
        'sqlState' => $conn->sqlstate
    ];
    
    logUploadActivity("Database error: {$error['code']} - {$error['message']}", 'error');
    
    return $error;
}
