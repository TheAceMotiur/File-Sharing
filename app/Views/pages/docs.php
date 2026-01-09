<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - <?php echo getSiteName(); ?></title>
    <link rel="icon" type="image/png" href="/icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php include __DIR__ . '/../partials/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8">API Documentation</h1>
        
        <div class="bg-white rounded-lg shadow p-8">
            <h2 class="text-2xl font-bold mb-4">Getting Started</h2>
            <p class="mb-6">Use our API to integrate file uploads into your applications.</p>
            
            <h3 class="text-xl font-bold mb-3">Authentication</h3>
            <p class="mb-4">All API requests require an API key. Get yours from your dashboard.</p>
            <div class="bg-gray-100 p-4 rounded mb-6">
                <code>Authorization: Bearer YOUR_API_KEY</code>
            </div>
            
            <h3 class="text-xl font-bold mb-3">Upload File</h3>
            <p class="mb-2"><strong>POST</strong> /api/upload</p>
            <div class="bg-gray-100 p-4 rounded mb-6">
                <pre>
{
  "file": "file binary",
  "folder_id": "optional"
}
                </pre>
            </div>
            
            <h3 class="text-xl font-bold mb-3">List Files</h3>
            <p class="mb-2"><strong>GET</strong> /api/files</p>
            
            <h3 class="text-xl font-bold mb-3">Delete File</h3>
            <p class="mb-2"><strong>POST</strong> /api/delete</p>
            <div class="bg-gray-100 p-4 rounded mb-6">
                <pre>
{
  "file_id": 123
}
                </pre>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
