<?php require_once __DIR__ . '/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S3 API Test - <?php echo getSiteName(); ?></title>
    <script src="https://sdk.amazonaws.com/js/aws-sdk-2.1001.0.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php include 'header.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h1 class="text-3xl font-bold mb-8">S3 API Test</h1>

            <!-- Configuration -->
            <div class="mb-8">
                <h2 class="text-xl font-semibold mb-4">Configuration</h2>
                <input type="text" id="apiKey" placeholder="Enter your API key" 
                       class="w-full p-2 border rounded mb-4">
                <button onclick="initializeS3()" 
                        class="bg-blue-500 text-white px-4 py-2 rounded">
                    Initialize S3 Client
                </button>
            </div>

            <!-- File Upload -->
            <div class="mb-8">
                <h2 class="text-xl font-semibold mb-4">Upload File</h2>
                <input type="file" id="fileInput" class="mb-4">
                <button onclick="uploadFile()" 
                        class="bg-green-500 text-white px-4 py-2 rounded">
                    Upload
                </button>
            </div>

            <!-- File List -->
            <div class="mb-8">
                <h2 class="text-xl font-semibold mb-4">File List</h2>
                <button onclick="listFiles()" 
                        class="bg-purple-500 text-white px-4 py-2 rounded mb-4">
                    Refresh List
                </button>
                <div id="fileList" class="border rounded p-4"></div>
            </div>

            <!-- Results -->
            <div class="mt-8">
                <h2 class="text-xl font-semibold mb-4">Results</h2>
                <pre id="results" class="bg-gray-100 p-4 rounded"></pre>
            </div>
        </div>
    </div>

    <script>
    let s3;

    function initializeS3() {
        const apiKey = document.getElementById('apiKey').value;
        
        AWS.config.update({
            accessKeyId: apiKey,
            secretAccessKey: apiKey,
            region: 'us-east-1'
        });

        s3 = new AWS.S3({
            endpoint: window.location.origin + '/s3',
            s3ForcePathStyle: true,
            signatureVersion: 'v4',
            params: {
                Bucket: 'default' // Set default bucket
            }
        });

        showResult('S3 client initialized');
    }

    function uploadFile() {
        const file = document.getElementById('fileInput').files[0];
        if (!file) {
            showResult('Please select a file first');
            return;
        }

        const params = {
            Key: file.name,
            Body: file,
            ContentType: file.type
        };

        s3.upload(params)
            .on('httpUploadProgress', (evt) => {
                const percentComplete = Math.round((evt.loaded * 100) / evt.total);
                showResult(`Upload progress: ${percentComplete}%`);
            })
            .send((err, data) => {
                if (err) {
                    showResult('Upload error: ' + err);
                } else {
                    showResult('Upload successful: ' + JSON.stringify(data, null, 2));
                    listFiles();
                }
            });
    }

    function listFiles() {
        const params = {
            Bucket: 'default'
        };

        s3.listObjects(params, (err, data) => {
            if (err) {
                showResult('List error: ' + err);
            } else {
                showResult('Files listed: ' + JSON.stringify(data, null, 2));
                displayFiles(data.Contents);
            }
        });
    }

    function deleteFile(key) {
        const params = {
            Bucket: 'default',
            Key: key
        };

        s3.deleteObject(params, (err, data) => {
            if (err) {
                showResult('Delete error: ' + err);
            } else {
                showResult('File deleted: ' + key);
                listFiles();
            }
        });
    }

    function downloadFile(key) {
        const params = {
            Bucket: 'default',
            Key: key
        };

        s3.getObject(params, (err, data) => {
            if (err) {
                showResult('Download error: ' + err);
            } else {
                const blob = new Blob([data.Body]);
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = key;
                a.click();
            }
        });
    }

    function displayFiles(files) {
        const fileList = document.getElementById('fileList');
        fileList.innerHTML = files.map(file => `
            <div class="flex items-center justify-between py-2 border-b">
                <span>${file.Key}</span>
                <div>
                    <button onclick="downloadFile('${file.Key}')"
                            class="bg-blue-500 text-white px-2 py-1 rounded text-sm">
                        Download
                    </button>
                    <button onclick="deleteFile('${file.Key}')"
                            class="bg-red-500 text-white px-2 py-1 rounded text-sm ml-2">
                        Delete
                    </button>
                </div>
            </div>
        `).join('');
    }

    function showResult(message) {
        const results = document.getElementById('results');
        if (typeof message === 'object') {
            message = JSON.stringify(message, null, 2);
        }
        results.textContent = message;
    }
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>
