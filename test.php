<?php
$API_URL = 'https://fileswith.com/api.php';
$API_KEY = '59d144a41422d3f7881fefdae6ffd53b020264f57a5c7da10bd1345bb54e6fb1';

function makeRequest($url, $apiKey, $method = 'GET', $data = null) {
    $ch = curl_init($url);
    
    $headers = [
        'X-Api-Key: ' . $apiKey,
        'Accept: application/json'
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FAILONERROR => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => $method // Added to support DELETE method
    ]);
    
    if (($method === 'POST' || $method === 'DELETE') && $data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch) . "\n";
    }
    
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

// Function to delete a file
function deleteFile($apiUrl, $apiKey, $fileId) {
    return makeRequest(
        $apiUrl . '?action=delete&file_id=' . urlencode($fileId),
        $apiKey,
        'DELETE'
    );
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $data = ['file' => new CURLFile($_FILES['file']['tmp_name'], $_FILES['file']['type'], $_FILES['file']['name'])];
    $result = makeRequest($API_URL . '?action=upload', $API_KEY, 'POST', $data);
    $uploadResponse = $result['body'];
}

// Handle delete request
if (isset($_POST['delete']) && isset($_POST['file_id'])) {
    $deleteResult = deleteFile($API_URL, $API_KEY, $_POST['file_id']);
    if ($deleteResult['code'] === 200 && isset($deleteResult['body']['success'])) {
        $deleteMessage = "File deleted successfully";
        // Refresh file list
        $result = makeRequest($API_URL . '?action=list', $API_KEY);
        $files = $result['body']['data']['files'] ?? [];
    } else {
        $deleteError = $deleteResult['body']['error'] ?? 'Failed to delete file';
    }
}

// Handle rename request
if (isset($_POST['rename']) && isset($_POST['file_id']) && isset($_POST['new_name'])) {
    $result = makeRequest(
        $API_URL . '?action=rename',
        $API_KEY,
        'POST',
        [
            'file_id' => $_POST['file_id'],
            'new_name' => $_POST['new_name']
        ]
    );
    if ($result['code'] === 200 && isset($result['body']['success'])) {
        $renameMessage = "File renamed successfully";
        // Refresh file list
        $result = makeRequest($API_URL . '?action=list', $API_KEY);
        $files = $result['body']['data']['files'] ?? [];
    } else {
        $renameError = $result['body']['error'] ?? 'Failed to rename file';
    }
}

// Get file list
$result = makeRequest($API_URL . '?action=list', $API_KEY);
$files = $result['body']['data']['files'] ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Test Interface</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Upload Section -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Upload File</h2>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Select File</label>
                    <input type="file" name="file" required
                           class="mt-1 block w-full text-sm text-gray-500
                                  file:mr-4 file:py-2 file:px-4
                                  file:rounded-full file:border-0
                                  file:text-sm file:font-semibold
                                  file:bg-blue-50 file:text-blue-700
                                  hover:file:bg-blue-100">
                </div>
                <button type="submit" 
                        class="inline-flex items-center px-4 py-2 border border-transparent 
                               text-sm font-medium rounded-md shadow-sm text-white 
                               bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 
                               focus:ring-offset-2 focus:ring-blue-500">
                    Upload File
                </button>
            </form>

            <?php if (isset($uploadResponse)): ?>
                <div class="mt-4 p-4 rounded-md <?php echo $uploadResponse['success'] ? 'bg-green-50' : 'bg-red-50'; ?>">
                    <?php if ($uploadResponse['success']): ?>
                        <p class="text-green-700">File uploaded successfully!</p>
                        <a href="<?php echo $uploadResponse['data']['download_url']; ?>" 
                           class="text-blue-600 hover:underline" 
                           target="_blank">
                            View File
                        </a>
                    <?php else: ?>
                        <p class="text-red-700">Error: <?php echo $uploadResponse['error']; ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Files List -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold">Your Files</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">File Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($files)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                    No files found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($files as $file): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo htmlspecialchars($file['file_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo number_format($file['size'] / 1024 / 1024, 2); ?> MB
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $file['created_at']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap space-x-3">
                                        <a href="https://fileswith.com/download/<?php echo $file['file_id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900 inline-block">
                                            Download
                                        </a>
                                        <button onclick="showRenameModal('<?php echo $file['file_id']; ?>', '<?php echo htmlspecialchars($file['file_name']); ?>')"
                                                class="text-blue-600 hover:text-blue-900 inline-block">
                                            Rename
                                        </button>
                                        <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this file?');">
                                            <input type="hidden" name="file_id" value="<?php echo $file['file_id']; ?>">
                                            <button type="submit" name="delete" 
                                                    class="text-red-600 hover:text-red-900 inline-block">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (isset($deleteMessage)): ?>
            <div class="mt-4 p-4 rounded-md bg-green-50">
                <p class="text-green-700"><?php echo $deleteMessage; ?></p>
            </div>
        <?php elseif (isset($deleteError)): ?>
            <div class="mt-4 p-4 rounded-md bg-red-50">
                <p class="text-red-700">Error: <?php echo $deleteError; ?></p>
            </div>
        <?php endif; ?>

        <?php if (isset($renameMessage)): ?>
            <div class="mt-4 p-4 rounded-md bg-green-50">
                <p class="text-green-700"><?php echo $renameMessage; ?></p>
            </div>
        <?php elseif (isset($renameError)): ?>
            <div class="mt-4 p-4 rounded-md bg-red-50">
                <p class="text-red-700">Error: <?php echo $renameError; ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Rename Modal -->
    <div id="renameModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center">
        <div class="bg-white rounded-lg p-8 max-w-md w-full">
            <h2 class="text-xl font-bold mb-4">Rename File</h2>
            <form method="POST" onsubmit="return validateRename(this);">
                <input type="hidden" name="file_id" id="renameFileId">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">New Name</label>
                        <input type="text" name="new_name" id="renameInput" required 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                               pattern="[A-Za-z0-9\-_.() ]+">
                        <p class="mt-1 text-sm text-gray-500">Only letters, numbers, spaces, and -_.() are allowed</p>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="document.getElementById('renameModal').classList.add('hidden')"
                                class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit" name="rename" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                            Rename
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
    function validateRename(form) {
        const newName = form.new_name.value;
        if (!/^[A-Za-z0-9\-_.() ]+$/.test(newName)) {
            alert('Invalid filename. Only letters, numbers, spaces, and -_.() are allowed');
            return false;
        }
        return true;
    }
    
    function showRenameModal(fileId, currentName) {
        // Remove extension from current name
        const nameWithoutExt = currentName.substring(0, currentName.lastIndexOf('.'));
        
        document.getElementById('renameFileId').value = fileId;
        document.getElementById('renameInput').value = nameWithoutExt;
        document.getElementById('renameModal').classList.remove('hidden');
        document.getElementById('renameInput').select();
    }
    </script>

    <script>
    function deleteFile(fileId, fileName) {
        if (confirm(`Are you sure you want to delete ${fileName}?`)) {
            fetch('<?php echo $API_URL; ?>?action=delete&file_id=' + fileId, {
                method: 'DELETE',
                headers: {
                    'X-Api-Key': '<?php echo $API_KEY; ?>',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Refresh the page or remove the row
                    location.reload();
                } else {
                    alert('Error deleting file: ' + data.error);
                }
            })
            .catch(error => {
                alert('Error deleting file: ' + error);
            });
        }
    }
    </script>
</body>
</html>