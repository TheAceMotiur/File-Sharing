<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

// Initialize variables
$fixes = [];
$fixAttempts = [];

// Process fix requests
if (isset($_POST['fix']) && $_POST['fix'] == 'memory_limits') {
    // Create or update php.ini override file
    $iniContent = <<<EOT
; Custom PHP settings for large uploads
upload_max_filesize = 2048M
post_max_size = 2048M
memory_limit = 512M
max_execution_time = 3600
max_input_time = 3600
display_errors = Off
log_errors = On
file_uploads = On
session.gc_maxlifetime = 14400
EOT;
    
    $wrote = file_put_contents(__DIR__ . '/php.ini', $iniContent);
    $fixAttempts[] = "Creating custom php.ini: " . ($wrote ? 'Success' : 'Failed');
}

if (isset($_POST['fix']) && $_POST['fix'] == 'create_temp') {
    $tempDir = __DIR__ . '/temp';
    if (!is_dir($tempDir)) {
        $created = mkdir($tempDir, 0755, true);
        $fixAttempts[] = "Creating temp directory: " . ($created ? 'Success' : 'Failed');
        
        if ($created) {
            file_put_contents($tempDir . '/.htaccess', "Options -Indexes\nOrder allow,deny\nDeny from all");
            file_put_contents($tempDir . '/.gitignore', "*\n!.gitignore\n!.htaccess");
            $fixAttempts[] = "Added security files to temp directory";
        }
    } else {
        $fixAttempts[] = "Temp directory already exists";
    }
    
    // Check and fix permissions
    if (is_dir($tempDir) && !is_writable($tempDir)) {
        @chmod($tempDir, 0755);
        $fixAttempts[] = "Fixing temp directory permissions: " . (is_writable($tempDir) ? 'Success' : 'Failed');
    }
}

if (isset($_POST['fix']) && $_POST['fix'] == 'htaccess') {
    $htaccessContent = <<<EOT
# Increase PHP limits for file uploads
<IfModule mod_php.c>
    php_value upload_max_filesize 2048M
    php_value post_max_size 2048M
    php_value memory_limit 512M
    php_value max_execution_time 3600
    php_value max_input_time 3600
    php_flag display_errors Off
    php_value session.gc_maxlifetime 14400
</IfModule>

# Increase timeout for Apache
<IfModule mod_reqtimeout.c>
    RequestReadTimeout header=60 body=3600
</IfModule>

# Allow large uploads
<IfModule mod_security.c>
    SecFilterEngine Off
    SecFilterScanPOST Off
</IfModule>

# Prevent timeout
<IfModule mod_fcgid.c>
    FcgidIOTimeout 3600
    FcgidConnectTimeout 3600
    FcgidIdleTimeout 3600
    FcgidBusyTimeout 3600
</IfModule>
EOT;
    
    $wrote = file_put_contents(__DIR__ . '/.htaccess', $htaccessContent);
    $fixAttempts[] = "Creating .htaccess file: " . ($wrote ? 'Success' : 'Failed');
}

// Get chunk_upload.php content
$chunkUploadFile = __DIR__ . '/chunk_upload.php';
$hasChunkUploadFile = file_exists($chunkUploadFile);
$chunkUploadContent = '';
if ($hasChunkUploadFile) {
    $chunkUploadContent = file_get_contents($chunkUploadFile);
}

// Detect issues and suggest fixes
if (intval(ini_get('upload_max_filesize')) < 2048 || intval(ini_get('post_max_size')) < 2048) {
    $fixes[] = [
        'issue' => 'PHP upload limits are too low for 2GB files',
        'fix' => 'memory_limits',
        'description' => 'Create a custom php.ini file to increase limits'
    ];
}

$tempDir = __DIR__ . '/temp';
if (!is_dir($tempDir) || !is_writable($tempDir)) {
    $fixes[] = [
        'issue' => 'Temporary directory is missing or not writable',
        'fix' => 'create_temp',
        'description' => 'Create the temp directory with proper permissions'
    ];
}

if (!file_exists(__DIR__ . '/.htaccess')) {
    $fixes[] = [
        'issue' => '.htaccess file is missing',
        'fix' => 'htaccess',
        'description' => 'Create .htaccess file to adjust server settings'
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload System Diagnostics</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Upload System Diagnostics</h1>
        
        <?php if (!empty($fixAttempts)): ?>
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2h-1V9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">Fix Results</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <ul class="list-disc pl-5 space-y-1">
                                <?php foreach ($fixAttempts as $attempt): ?>
                                    <li><?php echo htmlspecialchars($attempt); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="mt-4">
                            <a href="upload-diagnostics.php" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                                Refresh diagnostics <span aria-hidden="true">&rarr;</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($fixes)): ?>
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Issues Detected</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <ul class="list-disc pl-5 space-y-1">
                                <?php foreach ($fixes as $fix): ?>
                                    <li class="mb-4">
                                        <strong><?php echo htmlspecialchars($fix['issue']); ?></strong><br>
                                        <?php echo htmlspecialchars($fix['description']); ?><br>
                                        <form method="post" class="mt-2">
                                            <input type="hidden" name="fix" value="<?php echo htmlspecialchars($fix['fix']); ?>">
                                            <button type="submit" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                Apply Fix
                                            </button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
            <div class="px-4 py-5 sm:px-6">
                <h2 class="text-lg leading-6 font-medium text-gray-900">PHP Configuration</h2>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">Settings that affect file uploads</p>
            </div>
            <div class="border-t border-gray-200">
                <dl>
                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">PHP Version</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                            <?php echo PHP_VERSION; ?>
                            <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo version_compare(PHP_VERSION, '7.4.0', '>=') ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                <?php echo version_compare(PHP_VERSION, '7.4.0', '>=') ? 'OK' : 'Recommended: 7.4+'; ?>
                            </span>
                        </dd>
                    </div>
                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">upload_max_filesize</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                            <?php echo ini_get('upload_max_filesize'); ?>
                            <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo (intval(ini_get('upload_max_filesize')) >= 2048) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo (intval(ini_get('upload_max_filesize')) >= 2048) ? 'OK' : 'Too Low (Recommended: 2048M)'; ?>
                            </span>
                        </dd>
                    </div>
                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">post_max_size</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                            <?php echo ini_get('post_max_size'); ?>
                            <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo (intval(ini_get('post_max_size')) >= 2048) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo (intval(ini_get('post_max_size')) >= 2048) ? 'OK' : 'Too Low (Recommended: 2048M)'; ?>
                            </span>
                        </dd>
                    </div>
                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">max_execution_time</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                            <?php echo ini_get('max_execution_time'); ?>
                            <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo (intval(ini_get('max_execution_time')) >= 300 || intval(ini_get('max_execution_time')) == 0) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo (intval(ini_get('max_execution_time')) >= 300 || intval(ini_get('max_execution_time')) == 0) ? 'OK' : 'Too Low (Recommended: 300)'; ?>
                            </span>
                        </dd>
                    </div>
                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">memory_limit</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                            <?php echo ini_get('memory_limit'); ?>
                            <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo (intval(ini_get('memory_limit')) >= 512 || intval(ini_get('memory_limit')) == -1) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo (intval(ini_get('memory_limit')) >= 512 || intval(ini_get('memory_limit')) == -1) ? 'OK' : 'Too Low (Recommended: 512M)'; ?>
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
        
        <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
            <div class="px-4 py-5 sm:px-6">
                <h2 class="text-lg leading-6 font-medium text-gray-900">Directory Permissions</h2>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">Check if necessary directories are writable</p>
            </div>
            <div class="border-t border-gray-200">
                <dl>
                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Temp Directory</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                            <?php
                            $tempDir = __DIR__ . '/temp';
                            $tempDirExists = is_dir($tempDir);
                            $tempDirWritable = $tempDirExists && is_writable($tempDir);
                            $tempDirCreatable = !$tempDirExists && is_writable(__DIR__);
                            ?>
                            Path: <?php echo $tempDir; ?><br>
                            <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo ($tempDirExists && $tempDirWritable) || $tempDirCreatable ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php 
                                if ($tempDirExists && $tempDirWritable) echo 'OK';
                                elseif ($tempDirCreatable) echo 'Can be created';
                                else echo 'PROBLEM';
                                ?>
                            </span>
                            <br>
                            <?php 
                            if (!$tempDirExists) {
                                echo "Directory does not exist. ";
                                echo $tempDirCreatable ? "Can be created automatically." : "Cannot create directory (permission denied).";
                            } else {
                                echo "Directory exists. ";
                                echo $tempDirWritable ? "Is writable." : "Not writable (permission denied).";
                            }
                            ?>
                        </dd>
                    </div>
                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">PHP Upload Directory</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                            <?php
                            $uploadDir = sys_get_temp_dir();
                            $uploadDirWritable = is_writable($uploadDir);
                            ?>
                            Path: <?php echo $uploadDir; ?><br>
                            <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $uploadDirWritable ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $uploadDirWritable ? 'OK' : 'PROBLEM'; ?>
                            </span>
                            <br>
                            <?php echo $uploadDirWritable ? "Is writable." : "Not writable (permission denied)."; ?>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
        
        <?php if (!$tempDirExists && $tempDirCreatable): ?>
            <p>Creating temp directory...</p>
            <?php 
            if (mkdir($tempDir, 0755, true)) {
                file_put_contents($tempDir . '/.gitignore', "*\n!.gitignore");
                echo "<p class='pass'>Successfully created temp directory!</p>";
            } else {
                echo "<p class='fail'>Failed to create temp directory.</p>";
            }
            ?>
        <?php endif; ?>
        
        <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
            <div class="px-4 py-5 sm:px-6">
                <h2 class="text-lg leading-6 font-medium text-gray-900">Test Chunk Upload</h2>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">Run a test to ensure chunk uploads work correctly</p>
            </div>
            <div class="border-t border-gray-200">
                <div id="uploadTest" class="px-4 py-5 sm:px-6">
                    <button id="testButton" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Run Test Upload</button>
                    <div id="testResults" class="mt-4"></div>
                </div>
            </div>
        </div>
        
        <script>
            document.getElementById('testButton').addEventListener('click', async function() {
                const results = document.getElementById('testResults');
                results.innerHTML = '<p>Starting test...</p>';
                
                try {
                    // Create a test file (500KB)
                    const testFile = new Blob([new ArrayBuffer(500 * 1024)], {type: 'application/octet-stream'});
                    Object.defineProperty(testFile, 'name', {value: 'test-chunk.bin'});
                    
                    // Generate a unique ID
                    const fileId = Date.now().toString(36);
                    const totalChunks = 2;
                    
                    // Step 1: Initialize upload
                    results.innerHTML += '<p>Step 1: Initializing upload...</p>';
                    
                    const initResponse = await fetch('chunk_upload.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'init',
                            fileId: fileId,
                            fileName: testFile.name,
                            totalChunks: totalChunks,
                            fileSize: testFile.size
                        })
                    });
                    
                    if (!initResponse.ok) {
                        const errorData = await initResponse.text();
                        throw new Error(`Init failed: ${errorData}`);
                    }
                    
                    const initData = await initResponse.json();
                    results.innerHTML += `<p style="color:green">✓ Initialization successful: ${JSON.stringify(initData)}</p>`;
                    
                    // Step 2: Upload first chunk
                    results.innerHTML += '<p>Step 2: Uploading first chunk...</p>';
                    
                    const chunk1 = testFile.slice(0, 250 * 1024);
                    const formData1 = new FormData();
                    formData1.append('chunk', chunk1);
                    formData1.append('fileId', fileId);
                    formData1.append('chunkIndex', 0);
                    formData1.append('fileName', testFile.name);
                    formData1.append('totalChunks', totalChunks);
                    
                    const chunk1Response = await fetch('chunk_upload.php', {
                        method: 'POST',
                        body: formData1
                    });
                    
                    if (!chunk1Response.ok) {
                        const errorData = await chunk1Response.text();
                        throw new Error(`Chunk 1 upload failed: ${errorData}`);
                    }
                    
                    const chunk1Data = await chunk1Response.json();
                    results.innerHTML += `<p style="color:green">✓ First chunk uploaded: ${JSON.stringify(chunk1Data)}</p>`;
                    
                    // Step 3: Upload second chunk
                    results.innerHTML += '<p>Step 3: Uploading second chunk...</p>';
                    
                    const chunk2 = testFile.slice(250 * 1024);
                    const formData2 = new FormData();
                    formData2.append('chunk', chunk2);
                    formData2.append('fileId', fileId);
                    formData2.append('chunkIndex', 1);
                    formData2.append('fileName', testFile.name);
                    formData2.append('totalChunks', totalChunks);
                    
                    const chunk2Response = await fetch('chunk_upload.php', {
                        method: 'POST',
                        body: formData2
                    });
                    
                    if (!chunk2Response.ok) {
                        const errorData = await chunk2Response.text();
                        throw new Error(`Chunk 2 upload failed: ${errorData}`);
                    }
                    
                    const chunk2Data = await chunk2Response.json();
                    results.innerHTML += `<p style="color:green">✓ Second chunk uploaded: ${JSON.stringify(chunk2Data)}</p>`;
                    
                    results.innerHTML += '<p style="color:green">✓ Test completed successfully!</p>';
                    
                } catch (error) {
                    results.innerHTML += `<p style="color:red">✗ Error: ${error.message}</p>`;
                    console.error(error);
                }
            });
        </script>
        
        <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
            <div class="px-4 py-5 sm:px-6">
                <h2 class="text-lg leading-6 font-medium text-gray-900">Recommendations</h2>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">Suggestions to improve your upload system</p>
            </div>
            <div class="border-t border-gray-200">
                <div class="px-4 py-5 sm:px-6">
                    <ul class="list-disc pl-5 space-y-1 text-sm text-gray-700">
                        <li>Make sure the temp directory has proper write permissions (chmod 755)</li>
                        <li>For large file uploads, increase PHP limits in php.ini:
                            <pre class="bg-gray-100 p-2 rounded">
upload_max_filesize = 2048M
post_max_size = 2048M
max_execution_time = 600
memory_limit = 512M</pre>
                        </li>
                        <li>If using Apache, ensure the LimitRequestBody directive is set high enough</li>
                        <li>If using Nginx, check client_max_body_size setting</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
