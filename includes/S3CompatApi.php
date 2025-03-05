<?php
use Spatie\Dropbox\Client as DropboxClient;
require_once __DIR__ . '/S3Auth.php';

class S3CompatApi {
    private $db;
    private $dropbox;
    private $basePath;
    
    public function __construct() {
        $this->db = getDBConnection();
        $this->initDropbox();
        $this->basePath = '/s3api';
    }

    private function initDropbox() {
        $dropbox = $this->db->query("SELECT access_token FROM dropbox_accounts LIMIT 1")->fetch_assoc();
        if (!$dropbox) {
            throw new Exception('No Dropbox account configured');
        }
        $this->dropbox = new DropboxClient($dropbox['access_token']);
    }

    public function handleRequest() {
        // Add authentication check
        if (!S3Auth::authenticate()) {
            http_response_code(403);
            return ['error' => 'Invalid credentials'];
        }

        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_SERVER['PATH_INFO'] ?? '/';

        switch ($method) {
            case 'GET':
                if (isset($_GET['list-type'])) {
                    return $this->listObjects();
                }
                return $this->getObject($path);
            case 'PUT':
                return $this->putObject($path);
            case 'DELETE':
                return $this->deleteObject($path);
            case 'HEAD':
                return $this->headObject($path);
            default:
                http_response_code(405);
                return ['error' => 'Method not allowed'];
        }
    }

    private function listObjects() {
        try {
            $prefix = $_GET['prefix'] ?? '';
            $delimiter = $_GET['delimiter'] ?? '/';
            $maxKeys = (int)($_GET['max-keys'] ?? 1000);
            
            $response = $this->dropbox->listFolder($this->basePath . '/' . $prefix);
            
            $objects = [];
            foreach ($response['entries'] as $entry) {
                if ($entry['.tag'] === 'file') {
                    $objects[] = [
                        'Key' => substr($entry['path_display'], strlen($this->basePath) + 1),
                        'LastModified' => $entry['server_modified'],
                        'Size' => $entry['size'],
                        'ETag' => '"' . $entry['content_hash'] . '"'
                    ];
                }
            }

            return [
                'ListBucketResult' => [
                    'Name' => 'default',
                    'Prefix' => $prefix,
                    'MaxKeys' => $maxKeys,
                    'Delimiter' => $delimiter,
                    'IsTruncated' => false,
                    'Contents' => $objects
                ]
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getObject($path) {
        try {
            $stream = $this->dropbox->download($this->basePath . $path);
            
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            
            fpassthru($stream);
            exit;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function putObject($path) {
        try {
            $input = fopen('php://input', 'r');
            $this->dropbox->upload($this->basePath . $path, $input, 'overwrite');
            
            header('ETag: "' . md5_file('php://input') . '"');
            return ['success' => true];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function deleteObject($path) {
        try {
            $this->dropbox->delete($this->basePath . $path);
            return ['success' => true];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function headObject($path) {
        try {
            $metadata = $this->dropbox->getMetadata($this->basePath . $path);
            
            // Add required S3 headers
            $requestId = bin2hex(random_bytes(16));
            header('x-amz-request-id: ' . $requestId);
            header('x-amz-id-2: ' . base64_encode(random_bytes(16)));
            header('x-amz-version-id: null');
            
            // Set proper content type
            $contentType = MimeType::fromFilename($path) ?? 'application/octet-stream';
            header('Content-Type: ' . $contentType);
            
            // Standard headers
            header('Content-Length: ' . $metadata['size']);
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', strtotime($metadata['server_modified'])) . ' GMT');
            header('ETag: "' . $metadata['content_hash'] . '"');
            return '';
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}