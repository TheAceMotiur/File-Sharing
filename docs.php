<?php
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation - <?php echo getSiteName(); ?></title>
    <link rel="icon" type="image/png" href="icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/default.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
    <script>hljs.highlightAll();</script>
</head> 
<body class="bg-gray-50">
    <?php include 'header.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h1 class="text-3xl font-bold mb-8">API Documentation</h1>

            <!-- Authentication -->
            <section class="mb-8">
                <h2 class="text-2xl font-semibold mb-4">Authentication</h2>
                <p class="mb-4">All API requests require an API key passed in the X-Api-Key header. Get your API key from the <a href="/keys.php" class="text-blue-600 hover:underline">API Keys page</a>.</p>
                <pre><code class="language-bash">curl -H "X-Api-Key: YOUR_API_KEY" https://<?php echo $_SERVER['HTTP_HOST']; ?>/api.php</code></pre>
            </section>

            <!-- List Files -->
            <section class="mb-8">
                <h2 class="text-2xl font-semibold mb-4">List Files</h2>
                <p class="mb-2"><span class="font-semibold">GET</span> /api.php?action=list</p>
                <h3 class="text-lg font-semibold mt-4 mb-2">Example Request</h3>
                <pre><code class="language-bash">curl -H "X-Api-Key: YOUR_API_KEY" https://<?php echo $_SERVER['HTTP_HOST']; ?>/api.php?action=list</code></pre>
                
                <h3 class="text-lg font-semibold mt-4 mb-2">Response</h3>
                <pre><code class="language-json">{
    "success": true,
    "data": {
        "files": [
            {
                "file_id": "abc123",
                "file_name": "example.pdf",
                "size": 1024567,
                "created_at": "2024-01-01 12:00:00",
                "last_download_at": "2024-01-02 15:30:00",
                "status": "completed"
            }
        ]
    }
}</code></pre>
            </section>

            <!-- Upload File -->
            <section class="mb-8">
                <h2 class="text-2xl font-semibold mb-4">Upload File</h2>
                <p class="mb-2"><span class="font-semibold">POST</span> /api.php?action=upload</p>
                <h3 class="text-lg font-semibold mt-4 mb-2">Example Request</h3>
                <pre><code class="language-bash">curl -X POST https://<?php echo $_SERVER['HTTP_HOST']; ?>/api.php?action=upload \
    -H "X-Api-Key: YOUR_API_KEY" \
    -F "file=@/path/to/file.pdf"</code></pre>
                
                <h3 class="text-lg font-semibold mt-4 mb-2">Response</h3>
                <pre><code class="language-json">{
    "success": true,
    "data": {
        "file_id": "abc123",
        "download_url": "https://<?php echo $_SERVER['HTTP_HOST']; ?>/download/abc123"
    }
}</code></pre>

                <div class="mt-4 p-4 bg-yellow-50 rounded-md">
                    <p class="text-sm text-yellow-700">
                        <strong>Note:</strong> Maximum file size is 100MB.
                    </p>
                </div>
            </section>

            <!-- Delete File -->
            <section class="mb-8">
                <h2 class="text-2xl font-semibold mb-4">Delete File</h2>
                <p class="mb-2"><span class="font-semibold">GET</span> /api.php?action=delete&file_id=FILE_ID</p>
                <h3 class="text-lg font-semibold mt-4 mb-2">Example Request</h3>
                <pre><code class="language-bash">curl -H "X-Api-Key: YOUR_API_KEY" https://fileswith.com/api.php?action=delete&file_id=abc123</code></pre>
                
                <h3 class="text-lg font-semibold mt-4 mb-2">Response</h3>
                <pre><code class="language-json">{
    "success": true,
    "data": {
        "success": true,
        "message": "File deleted successfully"
    }
}</code></pre>
            </section>

            <!-- Rename File -->
            <section class="mb-8">
                <h2 class="text-2xl font-semibold mb-4">Rename File</h2>
                <p class="mb-2"><span class="font-semibold">POST</span> /api.php?action=rename&file_id=FILE_ID&new_name=NEW_NAME</p>
                <h3 class="text-lg font-semibold mt-4 mb-2">Example Request</h3>
                <pre><code class="language-bash">curl -X POST -H "X-Api-Key: YOUR_API_KEY" \
    "https://fileswith.com/api.php?action=rename&file_id=abc123&new_name=newname.pdf"</code></pre>
                
                <h3 class="text-lg font-semibold mt-4 mb-2">Response</h3>
                <pre><code class="language-json">{
    "success": true,
    "data": {
        "file_id": "abc123",
        "new_name": "newname.pdf"
    }
}</code></pre>
            </section>

            <!-- Error Handling -->
            <section class="mb-8">
                <h2 class="text-2xl font-semibold mb-4">Error Handling</h2>
                <p class="mb-4">All API errors return JSON with success: false and an error message:</p>
                <pre><code class="language-json">{
    "success": false,
    "error": "Error message here"
}</code></pre>
                
                <h3 class="text-lg font-semibold mt-4 mb-2">Common Errors</h3>
                <ul class="list-disc ml-6">
                    <li class="mb-2">API key is required</li>
                    <li class="mb-2">Invalid API key</li>
                    <li class="mb-2">File too large (>100MB)</li>
                    <li class="mb-2">No file uploaded</li>
                    <li class="mb-2">No storage available</li>
                    <li class="mb-2">File not found or unauthorized</li>
                </ul>
            </section>

            <!-- PHP Example -->
            <section class="mb-8">
                <h2 class="text-2xl font-semibold mb-4">PHP Example</h2>
                <pre><code class="language-php"><?php echo htmlspecialchars('<?php
$API_KEY = "YOUR_API_KEY";
$API_URL = "https://fileswith.com/api.php";

function makeRequest($url, $options = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Api-Key: " . $GLOBALS["API_KEY"]]);
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    return json_decode($response, true);
}

// Examples of file operations
$files = makeRequest($API_URL . "?action=list");

$upload = makeRequest($API_URL . "?action=upload", [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => [
        "file" => new CURLFile("/path/to/file.pdf")
    ]
]);

$delete = makeRequest($API_URL . "?action=delete&file_id=abc123");'); ?></code></pre>
            </section>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>