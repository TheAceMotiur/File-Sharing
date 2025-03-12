<?php
require_once __DIR__ . '/config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be logged in to upload files']);
    exit;
}

// Check if the request has files
if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
    http_response_code(400);
    echo json_encode(['error' => 'No files were uploaded']);
    exit;
}

// Get folder ID if provided
$folderId = isset($_POST['folder_id']) && !empty($_POST['folder_id']) ? $_POST['folder_id'] : null;

try {
    $db = getDBConnection();
    
    // If folder ID provided, check if it belongs to the user
    if ($folderId) {
        $folderStmt = $db->prepare("SELECT id FROM folders WHERE id = ? AND user_id = ?");
        $folderStmt->bind_param("si", $folderId, $_SESSION['user_id']);
        $folderStmt->execute();
        
        if ($folderStmt->get_result()->num_rows === 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid folder']);
            exit;
        }
    }

    // Get user's current storage usage
    $storageStmt = $db->prepare("SELECT COALESCE(SUM(size), 0) as used_storage FROM file_uploads WHERE uploaded_by = ?");
    $storageStmt->bind_param("i", $_SESSION['user_id']);
    $storageStmt->execute();
    $result = $storageStmt->get_result();
    $storage = $result->fetch_assoc();
    $usedStorage = $storage['used_storage'];
    
    // Get user premium status to determine storage limit (2GB for free, 20GB for premium)
    $isPremium = $_SESSION['premium'] ?? false;
    $storageLimit = $isPremium ? 20 * 1024 * 1024 * 1024 : 2 * 1024 * 1024 * 1024; // 2GB or 20GB
    
    // Process each file
    $uploadedFiles = [];
    $errors = [];

    // Loop through each file
    for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
        $fileName = $_FILES['files']['name'][$i];
        $fileSize = $_FILES['files']['size'][$i];
        $fileTmp = $_FILES['files']['tmp_name'][$i];
        $fileError = $_FILES['files']['error'][$i];
        
        // Check for file errors
        if ($fileError !== UPLOAD_ERR_OK) {
            $errors[] = "Error uploading $fileName. Error code: $fileError";
            continue;
        }
        
        // Check if adding this file would exceed the user's storage limit
        if ($usedStorage + $fileSize > $storageLimit) {
            $errors[] = "Uploading $fileName would exceed your storage limit";
            continue;
        }
        
        // Generate unique file ID
        $fileId = bin2hex(random_bytes(16));
        
        // Get DB connection and select a Dropbox account for upload
        $dropbox = $db->query("SELECT * FROM dropbox_accounts WHERE is_active = 1 ORDER BY total_used_storage ASC LIMIT 1")->fetch_assoc();
        
        if (!$dropbox) {
            $errors[] = "No available storage provider";
            continue;
        }
        
        // Insert file record with 'pending' status and folder_id
        $stmt = $db->prepare("
            INSERT INTO file_uploads 
            (file_id, file_name, size, uploaded_by, upload_status, dropbox_account_id, folder_id) 
            VALUES (?, ?, ?, ?, 'pending', ?, ?)
        ");
        $stmt->bind_param("ssiiss", $fileId, $fileName, $fileSize, $_SESSION['user_id'], $dropbox['id'], $folderId);
        $stmt->execute();
        
        // Prepare and start the upload to Dropbox
        $ch = curl_init('https://content.dropboxapi.com/2/files/upload');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $dropbox['access_token'],
            'Dropbox-API-Arg: {"path":"/' . $fileId . '", "mode":"add", "autorename":true, "mute":false}',
            'Content-Type: application/octet-stream',
        ));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($fileTmp));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            // Update the file record to 'completed'
            $completeStmt = $db->prepare("UPDATE file_uploads SET upload_status = 'completed' WHERE file_id = ?");
            $completeStmt->bind_param("s", $fileId);
            $completeStmt->execute();
            
            // Update dropbox account storage usage
            $updateStorageStmt = $db->prepare("UPDATE dropbox_accounts SET total_used_storage = total_used_storage + ? WHERE id = ?");
            $updateStorageStmt->bind_param("ii", $fileSize, $dropbox['id']);
            $updateStorageStmt->execute();
            
            $uploadedFiles[] = [
                'id' => $fileId,
                'name' => $fileName,
                'size' => $fileSize,
                'folder_id' => $folderId,
                'type' => 'file',
                'created_at' => date('Y-m-d H:i:s'),
                'is_image' => preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $fileName) ? 1 : 0
            ];
            
            // Increment used storage
            $usedStorage += $fileSize;
        } else {
            // Update the file record to 'failed'
            $failStmt = $db->prepare("UPDATE file_uploads SET upload_status = 'failed' WHERE file_id = ?");
            $failStmt->bind_param("s", $fileId);
            $failStmt->execute();
            
            $errors[] = "Failed to upload $fileName to Dropbox";
        }
    }
    
    // Return the results
    echo json_encode([
        'success' => count($uploadedFiles) > 0,
        'files' => $uploadedFiles,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
