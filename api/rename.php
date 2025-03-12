<?php
require_once __DIR__ . '/../config.php';
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check for required parameters
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'rename') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

if (!isset($_POST['file_id']) || !isset($_POST['new_name']) || empty($_POST['new_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$fileId = $_POST['file_id'];
$newName = trim($_POST['new_name']);

try {
    $db = getDBConnection();
    
    // Get the file and check ownership
    $fileStmt = $db->prepare("SELECT * FROM file_uploads WHERE file_id = ? AND uploaded_by = ?");
    $fileStmt->bind_param("si", $fileId, $_SESSION['user_id']);
    $fileStmt->execute();
    $fileResult = $fileStmt->get_result();
    
    if ($fileResult->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to rename this file']);
        exit;
    }
    
    $file = $fileResult->fetch_assoc();
    
    // Perform the rename operation
    $updateStmt = $db->prepare("UPDATE file_uploads SET file_name = ? WHERE file_id = ?");
    $updateStmt->bind_param("ss", $newName, $fileId);
    
    if ($updateStmt->execute()) {
        echo json_encode([
            'success' => true,
            'name' => $newName
        ]);
    } else {
        throw new Exception('Failed to update file name in database');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
