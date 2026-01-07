<?php
// Prevent any output before JSON
ob_start();

require_once __DIR__ . '/../config.php';

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear any buffered output and start fresh
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
    $db = getDBConnection();
    $action = $_GET['action'] ?? $_POST['action'] ?? null;
    
    if (!$action) {
        throw new Exception('No action specified');
    }
    
    switch ($action) {
        case 'list':
            // List folders and files in current folder
            $parentId = isset($_GET['parent_id']) && $_GET['parent_id'] !== '' ? (int)$_GET['parent_id'] : null;
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $sortBy = $_GET['sort_by'] ?? 'name';
            $sortOrder = $_GET['sort_order'] ?? 'asc';
            
            // Validate sort parameters
            $allowedSortFields = ['name', 'created_at', 'size'];
            $allowedSortOrders = ['asc', 'desc'];
            
            if (!in_array($sortBy, $allowedSortFields)) $sortBy = 'name';
            if (!in_array($sortOrder, $allowedSortOrders)) $sortOrder = 'asc';
            
            // Build breadcrumbs
            $breadcrumbs = [['id' => null, 'name' => 'My Files']];
            if ($parentId) {
                $stmt = $db->prepare("
                    SELECT id, name, parent_id 
                    FROM folders 
                    WHERE id = ? AND created_by = ?
                ");
                $stmt->bind_param("ii", $parentId, $_SESSION['user_id']);
                $stmt->execute();
                $currentFolder = $stmt->get_result()->fetch_assoc();
                
                if (!$currentFolder) {
                    throw new Exception('Folder not found');
                }
                
                // Build breadcrumb trail
                $tempId = $parentId;
                $trail = [];
                while ($tempId) {
                    $stmt = $db->prepare("SELECT id, name, parent_id FROM folders WHERE id = ?");
                    $stmt->bind_param("i", $tempId);
                    $stmt->execute();
                    $folder = $stmt->get_result()->fetch_assoc();
                    if ($folder) {
                        array_unshift($trail, ['id' => $folder['id'], 'name' => $folder['name']]);
                        $tempId = $folder['parent_id'];
                    } else {
                        break;
                    }
                }
                $breadcrumbs = array_merge($breadcrumbs, $trail);
            }
            
            // Get folders
            $foldersQuery = "
                SELECT id, name, created_at, 
                       (SELECT COUNT(*) FROM folders f2 WHERE f2.parent_id = folders.id) as subfolder_count,
                       (SELECT COUNT(*) FROM file_uploads WHERE folder_id = folders.id AND upload_status = 'completed') as file_count
                FROM folders 
                WHERE created_by = ? 
                AND " . ($parentId ? "parent_id = ?" : "parent_id IS NULL");
            
            if ($search) {
                $foldersQuery .= " AND name LIKE ?";
            }
            
            $foldersQuery .= " ORDER BY name " . $sortOrder;
            
            $stmt = $db->prepare($foldersQuery);
            if ($parentId && $search) {
                $searchParam = "%$search%";
                $stmt->bind_param("iis", $_SESSION['user_id'], $parentId, $searchParam);
            } elseif ($parentId) {
                $stmt->bind_param("ii", $_SESSION['user_id'], $parentId);
            } elseif ($search) {
                $searchParam = "%$search%";
                $stmt->bind_param("is", $_SESSION['user_id'], $searchParam);
            } else {
                $stmt->bind_param("i", $_SESSION['user_id']);
            }
            
            $stmt->execute();
            $folders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Format folders
            $formattedFolders = array_map(function($folder) {
                return [
                    'id' => $folder['id'],
                    'name' => $folder['name'],
                    'type' => 'folder',
                    'created_at' => $folder['created_at'],
                    'subfolder_count' => (int)$folder['subfolder_count'],
                    'file_count' => (int)$folder['file_count'],
                    'size' => 0
                ];
            }, $folders);
            
            // Get files
            $filesQuery = "
                SELECT file_id as id, file_name as name, size, created_at, 
                       CASE 
                           WHEN LOWER(file_name) LIKE '%.jpg' OR 
                                LOWER(file_name) LIKE '%.jpeg' OR 
                                LOWER(file_name) LIKE '%.png' OR 
                                LOWER(file_name) LIKE '%.gif' OR 
                                LOWER(file_name) LIKE '%.webp' OR
                                LOWER(file_name) LIKE '%.bmp'
                           THEN 1 
                           ELSE 0 
                       END as is_image
                FROM file_uploads 
                WHERE uploaded_by = ? 
                AND upload_status = 'completed'
                AND " . ($parentId ? "folder_id = ?" : "folder_id IS NULL");
            
            if ($search) {
                $filesQuery .= " AND file_name LIKE ?";
            }
            
            // Add sorting
            $filesQuery .= " ORDER BY ";
            if ($sortBy === 'name') {
                $filesQuery .= "file_name";
            } elseif ($sortBy === 'size') {
                $filesQuery .= "size";
            } else {
                $filesQuery .= "created_at";
            }
            $filesQuery .= " " . $sortOrder;
            
            $stmt = $db->prepare($filesQuery);
            if ($parentId && $search) {
                $searchParam = "%$search%";
                $stmt->bind_param("iis", $_SESSION['user_id'], $parentId, $searchParam);
            } elseif ($parentId) {
                $stmt->bind_param("ii", $_SESSION['user_id'], $parentId);
            } elseif ($search) {
                $searchParam = "%$search%";
                $stmt->bind_param("is", $_SESSION['user_id'], $searchParam);
            } else {
                $stmt->bind_param("i", $_SESSION['user_id']);
            }
            
            $stmt->execute();
            $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Format files
            $formattedFiles = array_map(function($file) {
                return [
                    'id' => $file['id'],
                    'name' => $file['name'],
                    'type' => 'file',
                    'size' => (int)$file['size'],
                    'created_at' => $file['created_at'],
                    'is_image' => (bool)$file['is_image']
                ];
            }, $files);
            
            // Clean buffer and send JSON
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'folders' => $formattedFolders,
                'files' => $formattedFiles,
                'breadcrumbs' => $breadcrumbs,
                'current_folder' => $parentId
            ]);
            exit;
            
        case 'create':
            // Create new folder
            $folderName = trim($_POST['name'] ?? '');
            $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
            
            if (empty($folderName)) {
                throw new Exception('Folder name is required');
            }
            
            // Check if folder with same name exists in same parent
            $checkQuery = "SELECT id FROM folders WHERE name = ? AND created_by = ? AND " . 
                         ($parentId ? "parent_id = ?" : "parent_id IS NULL");
            $stmt = $db->prepare($checkQuery);
            if ($parentId) {
                $stmt->bind_param("sii", $folderName, $_SESSION['user_id'], $parentId);
            } else {
                $stmt->bind_param("si", $folderName, $_SESSION['user_id']);
            }
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception('A folder with this name already exists');
            }
            
            // Create folder
            if ($parentId) {
                $stmt = $db->prepare("INSERT INTO folders (name, parent_id, created_by) VALUES (?, ?, ?)");
                $stmt->bind_param("sii", $folderName, $parentId, $_SESSION['user_id']);
            } else {
                $stmt = $db->prepare("INSERT INTO folders (name, created_by) VALUES (?, ?)");
                $stmt->bind_param("si", $folderName, $_SESSION['user_id']);
            }
            
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $folderId = $stmt->insert_id;
                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'folder' => [
                        'id' => $folderId,
                        'name' => $folderName,
                        'type' => 'folder',
                        'created_at' => date('Y-m-d H:i:s'),
                        'subfolder_count' => 0,
                        'file_count' => 0,
                        'size' => 0
                    ]
                ]);
                exit;
            } else {
                throw new Exception('Failed to create folder');
            }
            break;
            
        case 'rename':
            // Rename folder
            $folderId = (int)$_POST['folder_id'];
            $newName = trim($_POST['name'] ?? '');
            
            if (empty($newName)) {
                throw new Exception('Folder name is required');
            }
            
            // Verify ownership
            $stmt = $db->prepare("SELECT id, parent_id FROM folders WHERE id = ? AND created_by = ?");
            $stmt->bind_param("ii", $folderId, $_SESSION['user_id']);
            $stmt->execute();
            $folder = $stmt->get_result()->fetch_assoc();
            
            if (!$folder) {
                throw new Exception('Folder not found');
            }
            
            // Check for duplicate name in same parent
            $checkQuery = "SELECT id FROM folders WHERE name = ? AND created_by = ? AND id != ? AND " . 
                         ($folder['parent_id'] ? "parent_id = ?" : "parent_id IS NULL");
            $stmt = $db->prepare($checkQuery);
            if ($folder['parent_id']) {
                $stmt->bind_param("siii", $newName, $_SESSION['user_id'], $folderId, $folder['parent_id']);
            } else {
                $stmt->bind_param("sii", $newName, $_SESSION['user_id'], $folderId);
            }
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception('A folder with this name already exists');
            }
            
            // Update folder name
            $stmt = $db->prepare("UPDATE folders SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $newName, $folderId);
            $stmt->execute();
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Folder renamed successfully'
            ]);
            exit;
            break;
            
        case 'delete':
            // Delete folder and its contents
            $folderId = (int)$_POST['folder_id'];
            
            // Verify ownership
            $stmt = $db->prepare("SELECT id FROM folders WHERE id = ? AND created_by = ?");
            $stmt->bind_param("ii", $folderId, $_SESSION['user_id']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                throw new Exception('Folder not found');
            }
            
            // Delete folder (cascade will handle subfolders and set files to NULL)
            $stmt = $db->prepare("DELETE FROM folders WHERE id = ?");
            $stmt->bind_param("i", $folderId);
            $stmt->execute();
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Folder deleted successfully'
            ]);
            exit;
            break;
            
        case 'move_file':
            // Move file to folder
            $fileId = $_POST['file_id'] ?? '';
            $targetFolderId = isset($_POST['target_folder_id']) && $_POST['target_folder_id'] !== '' 
                             ? (int)$_POST['target_folder_id'] : null;
            
            // Verify file ownership
            $stmt = $db->prepare("SELECT file_id FROM file_uploads WHERE file_id = ? AND uploaded_by = ?");
            $stmt->bind_param("si", $fileId, $_SESSION['user_id']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                throw new Exception('File not found');
            }
            
            // If target folder specified, verify ownership
            if ($targetFolderId) {
                $stmt = $db->prepare("SELECT id FROM folders WHERE id = ? AND created_by = ?");
                $stmt->bind_param("ii", $targetFolderId, $_SESSION['user_id']);
                $stmt->execute();
                if ($stmt->get_result()->num_rows === 0) {
                    throw new Exception('Target folder not found');
                }
            }
            
            // Move file
            if ($targetFolderId) {
                $stmt = $db->prepare("UPDATE file_uploads SET folder_id = ? WHERE file_id = ?");
                $stmt->bind_param("is", $targetFolderId, $fileId);
            } else {
                $stmt = $db->prepare("UPDATE file_uploads SET folder_id = NULL WHERE file_id = ?");
                $stmt->bind_param("s", $fileId);
            }
            $stmt->execute();
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'File moved successfully'
            ]);
            exit;
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}
