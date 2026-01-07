<?php
// Prevent any output before JSON
ob_start();

require_once __DIR__ . '/../config.php';

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear any buffered output
ob_end_clean();
ob_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    $db = getDBConnection();
    $fileId = $_POST['file_id'] ?? '';
    $newName = trim($_POST['new_name'] ?? '');
    
    if (empty($fileId) || empty($newName)) {
        throw new Exception('File ID and new name are required');
    }
    
    // Get the original file record
    $stmt = $db->prepare("SELECT * FROM file_uploads WHERE file_id = ? AND uploaded_by = ?");
    $stmt->bind_param("si", $fileId, $_SESSION['user_id']);
    $stmt->execute();
    $file = $stmt->get_result()->fetch_assoc();
    
    if (!$file) {
        throw new Exception('File not found');
    }
    
    // Get original extension and ensure it's preserved
    $originalExt = pathinfo($file['file_name'], PATHINFO_EXTENSION);
    
    // Remove any extension from new name if present
    $newNameWithoutExt = pathinfo($newName, PATHINFO_FILENAME);
    
    // Combine new name with original extension
    $finalName = $newNameWithoutExt . '.' . $originalExt;
    
    // Set up paths
    $oldPath = '/' . $fileId . '/' . $file['file_name'];
    $newPath = '/' . $fileId . '/' . $finalName;
    
    // Get Dropbox credentials
    $dropbox = $db->query("SELECT * FROM dropbox_accounts LIMIT 1")->fetch_assoc();
    
    if ($dropbox) {
        // Use curl to rename file in Dropbox
        $ch = curl_init('https://api.dropboxapi.com/2/files/move_v2');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $dropbox['access_token'],
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "from_path" => $oldPath,
            "to_path" => $newPath
        ]));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Failed to rename file in Dropbox');
        }
    }
    
    // Update database
    $stmt = $db->prepare("UPDATE file_uploads SET file_name = ?, dropbox_path = ? WHERE file_id = ? AND uploaded_by = ?");
    $stmt->bind_param("sssi", $finalName, $newPath, $fileId, $_SESSION['user_id']);
    $stmt->execute();
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'name' => $finalName,
        'message' => 'File renamed successfully'
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
