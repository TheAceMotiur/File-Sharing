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

$db = getDBConnection();
$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            // List folders and files in the current directory
            $parentId = $_GET['parent_id'] ?? null;
            
            // Get folders
            $folderStmt = $db->prepare("
                SELECT id, name, created_at, updated_at, 'folder' as type
                FROM folders 
                WHERE user_id = ? AND (parent_id " . ($parentId ? "= ?" : "IS NULL") . ")
                ORDER BY name ASC
            ");
            
            if ($parentId) {
                $folderStmt->bind_param("is", $_SESSION['user_id'], $parentId);
            } else {
                $folderStmt->bind_param("i", $_SESSION['user_id']);
            }
            
            $folderStmt->execute();
            $folders = $folderStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Get files
            $fileStmt = $db->prepare("
                SELECT file_id as id, file_name as name, size, created_at, 'file' as type, 
                CASE 
                    WHEN LOWER(file_name) LIKE '%.jpg' OR 
                         LOWER(file_name) LIKE '%.jpeg' OR 
                         LOWER(file_name) LIKE '%.png' OR 
                         LOWER(file_name) LIKE '%.gif' OR 
                         LOWER(file_name) LIKE '%.webp' 
                    THEN 1 
                    ELSE 0 
                END as is_image
                FROM file_uploads 
                WHERE uploaded_by = ? AND upload_status = 'completed' 
                AND (folder_id " . ($parentId ? "= ?" : "IS NULL") . ")
                ORDER BY created_at DESC
            ");
            
            if ($parentId) {
                $fileStmt->bind_param("is", $_SESSION['user_id'], $parentId);
            } else {
                $fileStmt->bind_param("i", $_SESSION['user_id']);
            }
            
            $fileStmt->execute();
            $files = $fileStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Get breadcrumbs
            $breadcrumbs = [];
            $currentId = $parentId;
            
            while ($currentId) {
                $breadcrumbStmt = $db->prepare("SELECT id, name, parent_id FROM folders WHERE id = ? AND user_id = ?");
                $breadcrumbStmt->bind_param("si", $currentId, $_SESSION['user_id']);
                $breadcrumbStmt->execute();
                $folder = $breadcrumbStmt->get_result()->fetch_assoc();
                
                if ($folder) {
                    array_unshift($breadcrumbs, ['id' => $folder['id'], 'name' => $folder['name']]);
                    $currentId = $folder['parent_id'];
                } else {
                    break;
                }
            }
            
            // Add root to breadcrumbs if we have any breadcrumbs
            if (!empty($breadcrumbs)) {
                array_unshift($breadcrumbs, ['id' => null, 'name' => 'Home']);
            }
            
            echo json_encode([
                'folders' => $folders,
                'files' => $files,
                'breadcrumbs' => $breadcrumbs,
                'current_folder' => $parentId
            ]);
            break;
            
        case 'create':
            // Create a new folder
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            $name = trim($_POST['name']);
            $parentId = $_POST['parent_id'] ?? null;
            
            if (empty($name)) {
                http_response_code(400);
                echo json_encode(['error' => 'Folder name cannot be empty']);
                exit;
            }
            
            // Validate parent folder belongs to user if provided
            if ($parentId) {
                $parentCheck = $db->prepare("SELECT id FROM folders WHERE id = ? AND user_id = ?");
                $parentCheck->bind_param("si", $parentId, $_SESSION['user_id']);
                $parentCheck->execute();
                
                if ($parentCheck->get_result()->num_rows === 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid parent folder']);
                    exit;
                }
            }
            
            // Generate UUID for folder ID
            $uuid = bin2hex(random_bytes(16));
            $folderId = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($uuid, 4));
            
            $stmt = $db->prepare("
                INSERT INTO folders (id, name, parent_id, user_id) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("sssi", $folderId, $name, $parentId, $_SESSION['user_id']);
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'folder' => [
                    'id' => $folderId,
                    'name' => $name,
                    'parent_id' => $parentId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'type' => 'folder'
                ]
            ]);
            break;
            
        case 'delete':
            // Delete a folder
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            $folderId = $_POST['folder_id'];
            
            // Check if folder belongs to user
            $folderCheck = $db->prepare("SELECT id FROM folders WHERE id = ? AND user_id = ?");
            $folderCheck->bind_param("si", $folderId, $_SESSION['user_id']);
            $folderCheck->execute();
            
            if ($folderCheck->get_result()->num_rows === 0) {
                http_response_code(403);
                echo json_encode(['error' => 'You do not have permission to delete this folder']);
                exit;
            }
            
            // Delete the folder (cascading will handle child folders)
            $stmt = $db->prepare("DELETE FROM folders WHERE id = ?");
            $stmt->bind_param("s", $folderId);
            $result = $stmt->execute();
            
            // Update any files in the folder to have null folder_id
            $updateFiles = $db->prepare("UPDATE file_uploads SET folder_id = NULL WHERE folder_id = ?");
            $updateFiles->bind_param("s", $folderId);
            $updateFiles->execute();
            
            echo json_encode(['success' => $result]);
            break;
            
        case 'rename':
            // Rename a folder
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            $folderId = $_POST['folder_id'];
            $newName = trim($_POST['name']);
            
            if (empty($newName)) {
                http_response_code(400);
                echo json_encode(['error' => 'Folder name cannot be empty']);
                exit;
            }
            
            // Check if folder belongs to user
            $folderCheck = $db->prepare("SELECT id FROM folders WHERE id = ? AND user_id = ?");
            $folderCheck->bind_param("si", $folderId, $_SESSION['user_id']);
            $folderCheck->execute();
            
            if ($folderCheck->get_result()->num_rows === 0) {
                http_response_code(403);
                echo json_encode(['error' => 'You do not have permission to rename this folder']);
                exit;
            }
            
            $stmt = $db->prepare("UPDATE folders SET name = ? WHERE id = ?");
            $stmt->bind_param("ss", $newName, $folderId);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'name' => $newName]);
            break;
            
        case 'move_file':
            // Move a file to a folder
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            $fileId = $_POST['file_id'];
            $folderId = $_POST['folder_id'] ?? null; // Can be null for root folder
            
            // Check if file belongs to user
            $fileCheck = $db->prepare("SELECT file_id FROM file_uploads WHERE file_id = ? AND uploaded_by = ?");
            $fileCheck->bind_param("si", $fileId, $_SESSION['user_id']);
            $fileCheck->execute();
            
            if ($fileCheck->get_result()->num_rows === 0) {
                http_response_code(403);
                echo json_encode(['error' => 'You do not have permission to move this file']);
                exit;
            }
            
            // Check if target folder belongs to user (if not null)
            if ($folderId) {
                $folderCheck = $db->prepare("SELECT id FROM folders WHERE id = ? AND user_id = ?");
                $folderCheck->bind_param("si", $folderId, $_SESSION['user_id']);
                $folderCheck->execute();
                
                if ($folderCheck->get_result()->num_rows === 0) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Invalid target folder']);
                    exit;
                }
            }
            
            // Update the file
            $stmt = $db->prepare("UPDATE file_uploads SET folder_id = ? WHERE file_id = ?");
            $stmt->bind_param("ss", $folderId, $fileId);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
