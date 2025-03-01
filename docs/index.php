<?php
session_start();
// Fixed config path
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation - OneNetly</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php include '../header.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="prose max-w-none">
            <!-- Introduction -->
            <div class="mb-12">
                <h1 class="text-3xl font-bold text-gray-900 mb-4">API Documentation</h1>
                <p class="text-lg text-gray-600">Welcome to the OneNetly API documentation. Here's how to use our endpoints.</p>
            </div>

            <!-- Upload Section -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">File Upload</h2>
                <p class="text-gray-600 mb-4">Upload files to our service.</p>

                <div class="space-y-4">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-sm font-medium text-gray-700 mb-2">POST /api/?action=upload</p>
                        <p class="text-sm text-gray-600 mb-2">Headers:</p>
                        <pre class="bg-gray-800 text-gray-100 rounded-md p-4 text-sm">
Content-Type: multipart/form-data</pre>
                        
                        <p class="text-sm text-gray-600 mt-4 mb-2">Response:</p>
                        <pre class="bg-gray-800 text-gray-100 rounded-md p-4 text-sm">
{
    "success": true,
    "downloadLink": "https://onenetly.com/download.php?id=unique_id"
}</pre>
                    </div>
                </div>
            </div>

            <!-- List Files Section -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">List Files</h2>
                <p class="text-gray-600 mb-4">Get a list of uploaded files.</p>

                <div class="space-y-4">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-sm font-medium text-gray-700 mb-2">GET /api/?action=files</p>
                        <p class="text-sm text-gray-600 mb-2">Response:</p>
                        <pre class="bg-gray-800 text-gray-100 rounded-md p-4 text-sm">
{
    "success": true,
    "files": [
        {
            "file_id": "unique_id",
            "file_name": "example.pdf",
            "size": 1048576,
            "created_at": "2024-01-01 12:00:00",
            "last_download_at": "2024-01-02 15:30:00"
        }
    ]
}</pre>
                    </div>
                </div>
            </div>

            <!-- Login Section -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Login</h2>
                <p class="text-gray-600 mb-4">Authenticate a user and get session.</p>

                <div class="space-y-4">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-sm font-medium text-gray-700 mb-2">POST /api/?action=login</p>
                        <p class="text-sm text-gray-600 mb-2">Request Body:</p>
                        <pre class="bg-gray-800 text-gray-100 rounded-md p-4 text-sm">
{
    "email": "user@example.com",
    "password": "your_password"
}</pre>
                        
                        <p class="text-sm text-gray-600 mt-4 mb-2">Success Response:</p>
                        <pre class="bg-gray-800 text-gray-100 rounded-md p-4 text-sm">
{
    "success": true,
    "message": "Login successful"
}</pre>

                        <p class="text-sm text-gray-600 mt-4 mb-2">Error Response:</p>
                        <pre class="bg-gray-800 text-gray-100 rounded-md p-4 text-sm">
{
    "success": false,
    "error": "Invalid email or password"
}</pre>
                    </div>
                </div>
            </div>

            <!-- Register Section -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Register</h2>
                <p class="text-gray-600 mb-4">Create a new user account.</p>

                <div class="space-y-4">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-sm font-medium text-gray-700 mb-2">POST /api/?action=register</p>
                        <p class="text-sm text-gray-600 mb-2">Request Body:</p>
                        <pre class="bg-gray-800 text-gray-100 rounded-md p-4 text-sm">
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "secure_password"
}</pre>
                        
                        <p class="text-sm text-gray-600 mt-4 mb-2">Success Response:</p>
                        <pre class="bg-gray-800 text-gray-100 rounded-md p-4 text-sm">
{
    "success": true,
    "message": "Registration successful"
}</pre>

                        <p class="text-sm text-gray-600 mt-4 mb-2">Error Response:</p>
                        <pre class="bg-gray-800 text-gray-100 rounded-md p-4 text-sm">
{
    "success": false,
    "error": "Email already registered"
}</pre>
                    </div>
                </div>
            </div>

            <!-- Code Examples -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Code Examples</h2>

                <!-- PHP Example -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">PHP Example</h3>
                    <pre class="bg-gray-800 text-gray-100 rounded-md p-4 text-sm overflow-x-auto">
// Upload file
$ch = curl_init("https://onenetly.com/api/?action=upload");
$file = new CURLFile("/path/to/file.pdf", "application/pdf", "file.pdf");

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ["file" => $file]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$result = json_decode($response, true);

if ($result["success"]) {
    echo "Download link: " . $result["downloadLink"];
}</pre>
                </div>

                <!-- JavaScript Example -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">JavaScript Example</h3>
                    <pre class="bg-gray-800 text-gray-100 rounded-md p-4 text-sm overflow-x-auto">
const uploadFile = async (file) => {
    const formData = new FormData();
    formData.append('file', file);

    const response = await fetch('/api/?action=upload', {
        method: 'POST',
        body: formData
    });

    const result = await response.json();
    if (result.success) {
        console.log('Download link:', result.downloadLink);
    }
};</pre>
                </div>

                <!-- Login Example -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Login Example</h3>
                    <pre class="bg-gray-800 text-gray-100 rounded-md p-4 text-sm overflow-x-auto">
// PHP Login Example
$ch = curl_init("https://onenetly.com/api/?action=login");
$data = [
    'email' => 'user@example.com',
    'password' => 'your_password'
];

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$result = json_decode($response, true);

if ($result["success"]) {
    echo "Login successful!";
}</pre>
                </div>

                <!-- Register Example -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Register Example</h3>
                    <pre class="bg-gray-800 text-gray-100 rounded-md p-4 text-sm overflow-x-auto">
// JavaScript Register Example
const register = async (userData) => {
    const response = await fetch('/api/?action=register', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            name: 'John Doe',
            email: 'john@example.com',
            password: 'secure_password'
        })
    });

    const result = await response.json();
    if (result.success) {
        console.log('Registration successful');
    }
};</pre>
                </div>
            </div>
        </div>
    </div>

    <?php include '../footer.php'; ?>
</body>
</html>