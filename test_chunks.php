<?php
session_start();

// Force login for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Use a test user ID
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chunk Upload Test</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <style>
        pre.code {
            background: #f4f4f8;
            padding: 1rem;
            border-radius: 0.5rem;
            font-size: 14px;
            overflow-x: auto;
        }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto bg-white p-8 rounded-xl shadow-md">
        <h1 class="text-3xl font-bold mb-6">Chunk Upload System Test</h1>
        
        <div class="mb-8">
            <h2 class="text-xl font-semibold mb-3">Test File Generator</h2>
            <div class="flex gap-4 mb-4">
                <button id="generate1MB" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                    Generate 1MB File
                </button>
                <button id="generate10MB" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                    Generate 10MB File
                </button>
                <button id="generate100MB" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                    Generate 100MB File
                </button>
            </div>
        </div>
        
        <div class="mb-8">
            <h2 class="text-xl font-semibold mb-3">Upload Test File</h2>
            <div class="border-2 border-dashed border-gray-300 p-8 text-center mb-4 rounded-lg" 
                 id="dropZone">
                <p class="mb-4">Drop file or click to select</p>
                <input type="file" id="fileInput" class="hidden">
                <button id="selectFile" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                    Select File
                </button>
            </div>
            <div id="fileDetails" class="hidden mb-4 p-4 bg-gray-100 rounded-lg">
                <p><strong>Name:</strong> <span id="fileName"></span></p>
                <p><strong>Size:</strong> <span id="fileSize"></span></p>
                <p><strong>Type:</strong> <span id="fileType"></span></p>
            </div>
            <button id="uploadFile" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 hidden">
                Start Upload
            </button>
        </div>
        
        <div id="uploadProgress" class="hidden mb-8">
            <h2 class="text-xl font-semibold mb-3">Upload Progress</h2>
            <div class="w-full bg-gray-200 rounded-full h-4 mb-2">
                <div id="progressBar" class="bg-green-600 h-4 rounded-full transition-all" style="width:0%"></div>
            </div>
            <div class="flex justify-between text-sm text-gray-600">
                <span id="progressText">0%</span>
                <span id="chunkInfo"></span>
                <span id="timeRemaining"></span>
            </div>
        </div>
        
        <div id="uploadResults" class="hidden mb-8">
            <h2 class="text-xl font-semibold mb-3">Results</h2>
            <div class="p-4 bg-green-100 border border-green-400 rounded">
                <p><strong>Download Link:</strong> <a id="downloadLink" href="#" class="text-blue-600 underline"></a></p>
                <p><strong>Upload Time:</strong> <span id="uploadTime"></span> seconds</p>
            </div>
        </div>
        
        <div id="errorContainer" class="hidden p-4 bg-red-100 border border-red-400 rounded mb-8">
            <h3 class="font-bold text-red-800 mb-2">Error</h3>
            <p id="errorMessage" class="text-red-700"></p>
        </div>
        
        <div class="mb-8">
            <h2 class="text-xl font-semibold mb-3">Log</h2>
            <div id="logContainer" class="h-64 overflow-y-auto p-4 bg-gray-800 text-green-400 font-mono text-sm rounded">
                <div id="log"></div>
            </div>
        </div>
        
        <div class="mb-8">
            <h2 class="text-xl font-semibold mb-3">Implementation Details</h2>
            <p class="mb-4">This test demonstrates the chunked file upload system that supports files up to 2GB. Here's how it works:</p>
            
            <h3 class="text-lg font-semibold mb-2">Client-Side Logic:</h3>
            <pre class="code">
// 1. Split file into chunks (default 2MB per chunk)
// 2. Initialize upload with file metadata
// 3. Upload each chunk with retry logic
// 4. Finalize upload when all chunks are complete
// 5. Get download link
            </pre>
            
            <h3 class="text-lg font-semibold mt-4 mb-2">Server-Side Logic:</h3>
            <pre class="code">
// 1. Create temporary directory for chunks
// 2. Save each uploaded chunk
// 3. When all chunks are uploaded, combine them
// 4. Process the complete file (upload to storage)
// 5. Generate and return download link
// 6. Clean up temporary files
            </pre>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // DOM Elements
            const dropZone = document.getElementById('dropZone');
            const fileInput = document.getElementById('fileInput');
            const selectFile = document.getElementById('selectFile');
            const uploadFile = document.getElementById('uploadFile');
            const fileDetails = document.getElementById('fileDetails');
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            const fileType = document.getElementById('fileType');
            const uploadProgress = document.getElementById('uploadProgress');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const chunkInfo = document.getElementById('chunkInfo');
            const timeRemaining = document.getElementById('timeRemaining');
            const uploadResults = document.getElementById('uploadResults');
            const downloadLink = document.getElementById('downloadLink');
            const uploadTime = document.getElementById('uploadTime');
            const errorContainer = document.getElementById('errorContainer');
            const errorMessage = document.getElementById('errorMessage');
            const logContainer = document.getElementById('logContainer');
            const log = document.getElementById('log');
            
            // Test file generation buttons
            document.getElementById('generate1MB').addEventListener('click', () => generateTestFile(1));
            document.getElementById('generate10MB').addEventListener('click', () => generateTestFile(10));
            document.getElementById('generate100MB').addEventListener('click', () => generateTestFile(100));
            
            // Variables for upload
            let selectedFile = null;
            const chunkSize = 2 * 1024 * 1024; // 2MB chunks
            let uploadStartTime = 0;
            
            // File selection via click
            selectFile.addEventListener('click', () => fileInput.click());
            fileInput.addEventListener('change', handleFileSelect);
            
            // Drop handling
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('border-blue-500', 'bg-blue-50');
            });
            
            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('border-blue-500', 'bg-blue-50');
            });
            
            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('border-blue-500', 'bg-blue-50');
                
                if (e.dataTransfer.files.length) {
                    handleFileSelection(e.dataTransfer.files[0]);
                }
            });
            
            // Upload button handling
            uploadFile.addEventListener('click', startUpload);
            
            // Functions
            function handleFileSelect(e) {
                if (e.target.files.length) {
                    handleFileSelection(e.target.files[0]);
                }
            }
            
            function handleFileSelection(file) {
                selectedFile = file;
                
                // Display file details
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                fileType.textContent = file.type || 'unknown';
                
                fileDetails.classList.remove('hidden');
                uploadFile.classList.remove('hidden');
                uploadResults.classList.add('hidden');
                errorContainer.classList.add('hidden');
                
                addToLog(`Selected file: ${file.name} (${formatFileSize(file.size)})`);
            }
            
            function formatFileSize(bytes) {
                if (bytes >= 1073741824) {
                    return (bytes / 1073741824).toFixed(2) + ' GB';
                } else if (bytes >= 1048576) {
                    return (bytes / 1048576).toFixed(2) + ' MB';
                } else if (bytes >= 1024) {
                    return (bytes / 1024).toFixed(2) + ' KB';
                } else {
                    return bytes + ' bytes';
                }
            }
            
            async function startUpload() {
                if (!selectedFile) {
                    showError('No file selected');
                    return;
                }
                
                // Reset UI
                uploadProgress.classList.remove('hidden');
                uploadFile.disabled = true;
                uploadFile.textContent = 'Uploading...';
                errorContainer.classList.add('hidden');
                uploadResults.classList.add('hidden');
                progressBar.style.width = '0%';
                progressText.textContent = '0%';
                
                uploadStartTime = Date.now();
                addToLog(`Starting upload of ${selectedFile.name}`);
                
                try {
                    const fileId = Date.now().toString(36) + Math.random().toString(36).substr(2, 5);
                    const totalChunks = Math.ceil(selectedFile.size / chunkSize);
                    
                    // 1. Initialize upload
                    addToLog(`Initializing upload (file ID: ${fileId}, chunks: ${totalChunks})`);
                    await initializeUpload(fileId, selectedFile.name, totalChunks, selectedFile.size);
                    
                    // 2. Upload chunks
                    let uploadedChunks = 0;
                    let totalUploaded = 0;
                    const startTime = Date.now();
                    
                    for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                        const start = chunkIndex * chunkSize;
                        const end = Math.min(selectedFile.size, start + chunkSize);
                        const chunk = selectedFile.slice(start, end);
                        
                        chunkInfo.textContent = `Chunk ${chunkIndex + 1} of ${totalChunks}`;
                        addToLog(`Uploading chunk ${chunkIndex + 1}/${totalChunks} (${formatFileSize(end - start)})`);
                        
                        await uploadChunk(chunk, fileId, chunkIndex, selectedFile.name, totalChunks);
                        
                        uploadedChunks++;
                        totalUploaded += (end - start);
                        const progress = Math.round((totalUploaded * 100) / selectedFile.size);
                        progressBar.style.width = `${progress}%`;
                        progressText.textContent = `${progress}%`;
                        
                        // Calculate time remaining
                        const elapsedTime = (Date.now() - startTime) / 1000; // seconds
                        const uploadSpeed = totalUploaded / elapsedTime; // bytes/second
                        const remainingBytes = selectedFile.size - totalUploaded;
                        
                        if (uploadSpeed > 0) {
                            const remainingTime = remainingBytes / uploadSpeed; // seconds
                            timeRemaining.textContent = `${formatTime(remainingTime)} remaining`;
                        }
                    }
                    
                    // 3. Finalize upload
                    addToLog('Finalizing upload...');
                    const result = await finalizeUpload(fileId, selectedFile.name, selectedFile.size);
                    
                    // Update UI with results
                    const totalTime = (Date.now() - uploadStartTime) / 1000;
                    uploadTime.textContent = totalTime.toFixed(2);
                    downloadLink.href = result.downloadLink;
                    downloadLink.textContent = result.downloadLink;
                    uploadResults.classList.remove('hidden');
                    
                    addToLog(`Upload completed in ${totalTime.toFixed(2)} seconds`);
                    addToLog(`Download link: ${result.downloadLink}`);
                    
                } catch (error) {
                    showError(error.message || 'Upload failed');
                    addToLog(`ERROR: ${error.message || 'Upload failed'}`);
                } finally {
                    uploadFile.disabled = false;
                    uploadFile.textContent = 'Start Upload';
                }
            }
            
            async function initializeUpload(fileId, fileName, totalChunks, fileSize) {
                const response = await fetch('chunk_upload.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'init',
                        fileId: fileId,
                        fileName: fileName,
                        totalChunks: totalChunks,
                        fileSize: fileSize
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`Failed to initialize upload: ${response.status} ${response.statusText}`);
                }
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Failed to initialize upload');
                }
                
                return data;
            }
            
            async function uploadChunk(chunk, fileId, chunkIndex, fileName, totalChunks) {
                const formData = new FormData();
                formData.append('chunk', chunk);
                formData.append('fileId', fileId);
                formData.append('chunkIndex', chunkIndex);
                formData.append('fileName', fileName);
                formData.append('totalChunks', totalChunks);
                
                let attempts = 0;
                const maxRetries = 3;
                
                while (attempts < maxRetries) {
                    try {
                        attempts++;
                        
                        const response = await fetch('chunk_upload.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        if (!response.ok) {
                            throw new Error(`Server returned ${response.status} ${response.statusText}`);
                        }
                        
                        const data = await response.json();
                        
                        if (!data.success) {
                            throw new Error(data.error || 'Chunk upload failed');
                        }
                        
                        return data;
                    } catch (error) {
                        if (attempts >= maxRetries) {
                            throw new Error(`Failed to upload chunk after ${maxRetries} attempts: ${error.message}`);
                        }
                        
                        // Wait with exponential backoff before retrying
                        const retryDelay = Math.pow(2, attempts) * 500;
                        addToLog(`Chunk ${chunkIndex + 1} upload failed (attempt ${attempts}), retrying in ${retryDelay}ms...`);
                        await new Promise(resolve => setTimeout(resolve, retryDelay));
                    }
                }
            }
            
            async function finalizeUpload(fileId, fileName, totalSize) {
                const formData = new FormData();
                formData.append('fileId', fileId);
                formData.append('fileName', fileName);
                formData.append('totalSize', totalSize);
                formData.append('chunksComplete', 'true');
                
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`Failed to finalize upload: ${response.status} ${response.statusText}`);
                }
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Failed to finalize upload');
                }
                
                return data;
            }
            
            function generateTestFile(sizeMB) {
                const size = sizeMB * 1024 * 1024;
                const buffer = new ArrayBuffer(size);
                const view = new Uint8Array(buffer);
                
                // Fill with pseudorandom data
                for (let i = 0; i < size; i += 4) {
                    const value = Math.floor(Math.random() * 256);
                    view[i] = value;
                    if (i + 1 < size) view[i + 1] = value;
                    if (i + 2 < size) view[i + 2] = value;
                    if (i + 3 < size) view[i + 3] = value;
                }
                
                const blob = new Blob([buffer], { type: 'application/octet-stream' });
                const testFile = new File([blob], `test-${sizeMB}MB.dat`, { type: 'application/octet-stream' });
                
                addToLog(`Generated test file: ${testFile.name} (${formatFileSize(testFile.size)})`);
                handleFileSelection(testFile);
            }
            
            function showError(message) {
                errorMessage.textContent = message;
                errorContainer.classList.remove('hidden');
            }
            
            function addToLog(message) {
                const timestamp = new Date().toLocaleTimeString();
                const logEntry = document.createElement('div');
                logEntry.innerHTML = `<span class="text-gray-500">[${timestamp}]</span> ${message}`;
                log.appendChild(logEntry);
                logContainer.scrollTop = logContainer.scrollHeight;
            }
            
            function formatTime(seconds) {
                if (seconds < 60) {
                    return `${Math.round(seconds)}s`;
                } else {
                    const minutes = Math.floor(seconds / 60);
                    const remainingSeconds = Math.round(seconds % 60);
                    return `${minutes}m ${remainingSeconds}s`;
                }
            }
            
            // Initial log message
            addToLog('Upload test interface ready');
        });
    </script>
</body>
</html>
