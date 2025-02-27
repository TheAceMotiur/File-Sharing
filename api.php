<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use Spatie\Dropbox\Client as DropboxClient;

class API {
    private $db;
    private $userId;
    
    public function __construct() {
        $this->db = getDBConnection();
    }
    
    private function validateApiKey() {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
        
        if (empty($apiKey)) {
            throw new Exception('API key is required');
        }

        $stmt = $this->db->prepare("SELECT user_id FROM api_keys WHERE api_key = ?");
        $stmt->bind_param("s", $apiKey);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row['user_id'];
        }
        throw new Exception('Invalid API key');
    }

    private function uploadFile() {
        if (!isset($_FILES['file'])) {
            throw new Exception('No file uploaded');
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed');
        }

        // 100MB size limit
        if ($file['size'] > 100 * 1024 * 1024) {
            throw new Exception('File size exceeds 100 MB limit');
        }

        // Get Dropbox account with available storage
        $dropbox = $this->db->query("
            SELECT da.*, 
                   COALESCE(SUM(fu.size), 0) as used_storage
            FROM dropbox_accounts da
            LEFT JOIN file_uploads fu ON fu.dropbox_account_id = da.id 
                AND fu.upload_status = 'completed'
            GROUP BY da.id
            HAVING used_storage < 2147483648 OR used_storage IS NULL
            LIMIT 1
        ")->fetch_assoc();

        if (!$dropbox) {
            throw new Exception('No storage available');
        }

        // Initialize Dropbox client
        $client = new DropboxClient($dropbox['access_token']);
        
        // Generate unique file ID
        $fileId = uniqid();
        
        // Upload to Dropbox
        $dropboxPath = "/{$fileId}/{$file['name']}";
        $fileContents = file_get_contents($file['tmp_name']);
        $client->upload($dropboxPath, $fileContents, 'add');
        
        // Save to database
        $stmt = $this->db->prepare("INSERT INTO file_uploads (
            file_id, 
            file_name, 
            size, 
            upload_status, 
            dropbox_path, 
            dropbox_account_id, 
            uploaded_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $status = 'completed';
        $stmt->bind_param("ssissii", 
            $fileId,
            $file['name'],
            $file['size'],
            $status,
            $dropboxPath,
            $dropbox['id'],
            $this->userId
        );
        $stmt->execute();

        return [
            'file_id' => $fileId,
            'download_url' => "https://" . $_SERVER['HTTP_HOST'] . "/download/" . $fileId
        ];
    }

    private function listFiles() {
        $stmt = $this->db->prepare("SELECT 
            file_id,
            file_name,
            size,
            created_at,
            last_download_at,
            upload_status
            FROM file_uploads 
            WHERE upload_status = 'completed'
            AND uploaded_by = ?
            ORDER BY created_at DESC");
        
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $files = [];

        while ($row = $result->fetch_assoc()) {
            $files[] = [
                'file_id' => $row['file_id'],
                'file_name' => $row['file_name'],
                'size' => (int)$row['size'],
                'created_at' => $row['created_at'],
                'last_download_at' => $row['last_download_at'],
                'status' => $row['upload_status']
            ];
        }

        return ['files' => $files];
    }

    private function deleteFile($fileId) {
        if (empty($fileId)) {
            throw new Exception('File ID is required');
        }

        // Check if file exists and belongs to user
        $stmt = $this->db->prepare("
            SELECT * FROM file_uploads 
            WHERE file_id = ? 
            AND uploaded_by = ?
            AND upload_status = 'completed'
        ");
        $stmt->bind_param("si", $fileId, $this->userId);
        $stmt->execute();
        $file = $stmt->get_result()->fetch_assoc();

        if (!$file) {
            throw new Exception('File not found or unauthorized');
        }

        // Get Dropbox account
        $dropbox = $this->db->query("SELECT access_token FROM dropbox_accounts LIMIT 1")->fetch_assoc();
        $client = new DropboxClient($dropbox['access_token']);

        try {
            // Delete from Dropbox
            $dropboxPath = "/{$fileId}/{$file['file_name']}";
            $client->delete($dropboxPath);

            // Delete from database
            $stmt = $this->db->prepare("DELETE FROM file_uploads WHERE file_id = ?");
            $stmt->bind_param("s", $fileId);
            $stmt->execute();

            return [
                'success' => true,
                'message' => 'File deleted successfully'
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to delete file: ' . $e->getMessage());
        }
    }

    private function renameFile($fileId, $newName) {
        try {
            // Get file info with Dropbox account details
            $stmt = $this->db->prepare("
                SELECT fu.*, da.access_token 
                FROM file_uploads fu
                JOIN dropbox_accounts da ON fu.dropbox_account_id = da.id
                WHERE fu.file_id = ? 
                AND fu.uploaded_by = ?
                AND fu.upload_status = 'completed'
                LIMIT 1
            ");
            $stmt->bind_param("si", $fileId, $this->userId);
            $stmt->execute();
            $file = $stmt->get_result()->fetch_assoc();
            
            if (!$file) {
                throw new Exception('File not found or unauthorized');
            }

            // Process filename
            $originalExt = pathinfo($file['file_name'], PATHINFO_EXTENSION);
            $newNameWithoutExt = pathinfo($newName, PATHINFO_FILENAME);
            $finalName = $newNameWithoutExt . '.' . $originalExt;

            // Basic filename validation
            if (!preg_match('/^[\w\-. ]+$/', $finalName)) {
                throw new Exception('Invalid filename. Only letters, numbers, spaces, hyphens, underscores and dots are allowed.');
            }

            // Setup paths
            $oldPath = '/' . $fileId . '/' . $file['file_name'];
            $newPath = '/' . $fileId . '/' . $finalName;

            // Use Dropbox API directly
            $ch = curl_init('https://api.dropboxapi.com/2/files/move_v2');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $file['access_token'],
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'from_path' => $oldPath,
                    'to_path' => $newPath,
                    'allow_ownership_transfer' => false
                ])
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new Exception('Failed to rename file in Dropbox');
            }

            // Update database
            $stmt = $this->db->prepare("
                UPDATE file_uploads 
                SET file_name = ?,
                    dropbox_path = ?
                WHERE file_id = ?
            ");
            $stmt->bind_param("sss", $finalName, $newPath, $fileId);
            
            if (!$stmt->execute()) {
                throw new Exception('Database update failed');
            }

            return [
                'success' => true,
                'file_id' => $fileId,
                'new_name' => $finalName
            ];
            
        } catch (Exception $e) {
            throw new Exception('Failed to rename file: ' . $e->getMessage());
        }
    }

    public function handleRequest() {
        try {
            $this->userId = $this->validateApiKey();
            $action = $_GET['action'] ?? 'list';

            switch ($action) {
                case 'upload':
                    $response = $this->uploadFile();
                    break;
                    
                case 'list':
                    $response = $this->listFiles();
                    break;

                case 'delete':
                    $fileId = $_GET['file_id'] ?? '';
                    $response = $this->deleteFile($fileId);
                    break;

                case 'rename':
                    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                        throw new Exception('Method not allowed. Use POST.');
                    }
                    $fileId = $_POST['file_id'] ?? '';
                    $newName = $_POST['new_name'] ?? '';
                    if (empty($fileId) || empty($newName)) {
                        throw new Exception('File ID and new name are required');
                    }
                    $response = $this->renameFile($fileId, $newName);
                    break;
                    
                default:
                    throw new Exception('Invalid action');
            }

            echo json_encode([
                'success' => true,
                'data' => $response
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}

$api = new API();
$api->handleRequest();