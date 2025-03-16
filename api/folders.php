<?php
require_once __DIR__ . '/../config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $db = getDBConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_folder':
                $folderName = trim($_POST['folder_name']);
                
                // Validate folder name
                if (empty($folderName)) {
                    throw new Exception('Folder name is required');
                }
                
                // Create folder in database
                $stmt = $db->prepare("INSERT INTO folders (name, created_by) VALUES (?, ?)");
                $stmt->bind_param("si", $folderName, $_SESSION['user_id']);
                $stmt->execute();
                
                if ($stmt->affected_rows > 0) {
                    echo json_encode([
                        'success' => true,
                        'folder_id' => $stmt->insert_id,
                        'message' => 'Folder created successfully'
                    ]);
                } else {
                    throw new Exception('Failed to create folder');
                }
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } else {
        throw new Exception('Invalid request');
    }
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
