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

        // Parse request path 
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = str_replace('/s3/', '', $path);
        
        // Split path into bucket and key parts
        $parts = explode('/', $path);
        $bucket = array_shift($parts);
        $key = implode('/', $parts);

        // Log request details
        error_log("S3 Request: Method=$method, Bucket=$bucket, Key=$key");
        error_log("Query: " . print_r($_GET, true));

        switch ($method) {
            case 'GET':
                if ($key === '' && isset($_GET['list-type'])) {
                    return $this->listObjects($bucket);
                }
                return $this->getObject($bucket, $key);
            case 'PUT':
                return $this->putObject($bucket, $key);
            case 'DELETE':
                return $this->deleteObject($bucket, $key); 
            case 'HEAD':
                return $this->headObject($bucket, $key);
            default:
                http_response_code(405);
                return ['error' => 'Method not allowed']; 
        }
    }

    private function listObjects($bucket) {
        try {
            // For now just list everything under basePath
            $response = $this->dropbox->listFolder($this->basePath);
            
            $objects = [];
            foreach ($response['entries'] as $entry) {
                if ($entry['.tag'] === 'file') {
                    $objects[] = [
                        'Key' => str_replace($this->basePath . '/', '', $entry['path_display']),
                        'LastModified' => $entry['server_modified'],
                        'Size' => $entry['size'],
                        'ETag' => '"' . $entry['content_hash'] . '"'
                    ];
                }
            }

            return [
                'ListBucketResult' => [
                    'Name' => $bucket,
                    'Prefix' => $_GET['prefix'] ?? '',
                    'MaxKeys' => 1000,
                    'IsTruncated' => false,
                    'Contents' => $objects
                ]
            ];

        } catch (Exception $e) {
            error_log("List error: " . $e->getMessage());
            http_response_code(500);
            return ['error' => $e->getMessage()];
        }
    }

    private function getObject($bucket, $key) {
        try {
            $stream = $this->dropbox->download($this->basePath . '/' . $key);
            
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($key) . '"');
            
            fpassthru($stream);
            exit;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function putObject($bucket, $key) {
        try {
            // Get content type from headers
            $contentType = $_SERVER['CONTENT_TYPE'] ?? 'application/octet-stream';
            
            // Log upload attempt
            error_log("S3 Upload: Attempting to upload to path: " . $key);
            error_log("Content-Type: " . $contentType);

            // Read the raw input
            $input = fopen('php://input', 'r');
            if (!$input) {
                throw new Exception('Failed to read request body');
            }

            // Upload to Dropbox
            $result = $this->dropbox->upload($this->basePath . '/' . $key, $input, 'overwrite');
            
            // Calculate ETag (MD5 of file)
            $etag = md5(stream_get_contents($input));
            
            // Set S3-compatible response headers
            $requestId = bin2hex(random_bytes(16));
            header('x-amz-request-id: ' . $requestId);
            header('x-amz-id-2: ' . base64_encode(random_bytes(16)));
            header('ETag: "' . $etag . '"');
            
            // Set proper response code
            http_response_code(200);
            
            // Return S3-compatible response
            return [
                'PutObjectResult' => [
                    'ETag' => '"' . $etag . '"',
                    'ServerSideEncryption' => 'AES256'
                ]
            ];

        } catch (Exception $e) {
            error_log("S3 Upload Error: " . $e->getMessage());
            http_response_code(400);
            return [
                'error' => $e->getMessage(),
                'code' => 400
            ];
        } finally {
            if (isset($input) && is_resource($input)) {
                fclose($input);
            }
        }
    }

    private function deleteObject($bucket, $key) {
        try {
            $this->dropbox->delete($this->basePath . '/' . $key);
            return ['success' => true];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function headObject($bucket, $key) {
        try {
            $metadata = $this->dropbox->getMetadata($this->basePath . '/' . $key);
            
            // Add required S3 headers
            $requestId = bin2hex(random_bytes(16));
            header('x-amz-request-id: ' . $requestId);
            header('x-amz-id-2: ' . base64_encode(random_bytes(16)));
            header('x-amz-version-id: null');
            
            // Set proper content type
            $contentType = MimeType::fromFilename($key) ?? 'application/octet-stream';
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