<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload System Diagnostics</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; max-width: 800px; margin: 0 auto; padding: 20px; }
        .section { margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
        .pass { color: green; }
        .fail { color: red; }
        .warning { color: orange; }
        table { border-collapse: collapse; width: 100%; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
        tr:nth-child(even) { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Upload System Diagnostics</h1>
    
    <div class="section">
        <h2>PHP Configuration</h2>
        <table>
            <tr><th>Setting</th><th>Value</th><th>Status</th></tr>
            <tr>
                <td>PHP Version</td>
                <td><?php echo PHP_VERSION; ?></td>
                <td class="<?php echo version_compare(PHP_VERSION, '7.4.0', '>=') ? 'pass' : 'warning'; ?>">
                    <?php echo version_compare(PHP_VERSION, '7.4.0', '>=') ? 'OK' : 'Recommended: 7.4 or higher'; ?>
                </td>
            </tr>
            <tr>
                <td>upload_max_filesize</td>
                <td><?php echo ini_get('upload_max_filesize'); ?></td>
                <td class="<?php echo (intval(ini_get('upload_max_filesize')) >= 2048) ? 'pass' : 'warning'; ?>">
                    <?php echo (intval(ini_get('upload_max_filesize')) >= 2048) ? 'OK' : 'Recommended: at least 2048M'; ?>
                </td>
            </tr>
            <tr>
                <td>post_max_size</td>
                <td><?php echo ini_get('post_max_size'); ?></td>
                <td class="<?php echo (intval(ini_get('post_max_size')) >= 2048) ? 'pass' : 'warning'; ?>">
                    <?php echo (intval(ini_get('post_max_size')) >= 2048) ? 'OK' : 'Recommended: at least 2048M'; ?>
                </td>
            </tr>
            <tr>
                <td>max_execution_time</td>
                <td><?php echo ini_get('max_execution_time'); ?></td>
                <td class="<?php echo (intval(ini_get('max_execution_time')) >= 300 || intval(ini_get('max_execution_time')) == 0) ? 'pass' : 'warning'; ?>">
                    <?php echo (intval(ini_get('max_execution_time')) >= 300 || intval(ini_get('max_execution_time')) == 0) ? 'OK' : 'Recommended: at least 300'; ?>
                </td>
            </tr>
            <tr>
                <td>memory_limit</td>
                <td><?php echo ini_get('memory_limit'); ?></td>
                <td class="<?php echo (intval(ini_get('memory_limit')) >= 512 || intval(ini_get('memory_limit')) == -1) ? 'pass' : 'warning'; ?>">
                    <?php echo (intval(ini_get('memory_limit')) >= 512 || intval(ini_get('memory_limit')) == -1) ? 'OK' : 'Recommended: at least 512M'; ?>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="section">
        <h2>Directory Permissions</h2>
        <?php
        $tempDir = __DIR__ . '/temp';
        $tempDirExists = is_dir($tempDir);
        $tempDirWritable = $tempDirExists && is_writable($tempDir);
        $tempDirCreatable = !$tempDirExists && is_writable(__DIR__);
        
        $uploadDir = sys_get_temp_dir();
        $uploadDirWritable = is_writable($uploadDir);
        ?>
        
        <table>
            <tr><th>Directory</th><th>Status</th><th>Details</th></tr>
            <tr>
                <td>Temp Directory</td>
                <td class="<?php echo ($tempDirExists && $tempDirWritable) || $tempDirCreatable ? 'pass' : 'fail'; ?>">
                    <?php 
                    if ($tempDirExists && $tempDirWritable) echo 'OK';
                    elseif ($tempDirCreatable) echo 'Can be created';
                    else echo 'PROBLEM';
                    ?>
                </td>
                <td>
                    Path: <?php echo $tempDir; ?><br>
                    <?php 
                    if (!$tempDirExists) {
                        echo "Directory does not exist. ";
                        echo $tempDirCreatable ? "Can be created automatically." : "Cannot create directory (permission denied).";
                    } else {
                        echo "Directory exists. ";
                        echo $tempDirWritable ? "Is writable." : "Not writable (permission denied).";
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <td>PHP Upload Directory</td>
                <td class="<?php echo $uploadDirWritable ? 'pass' : 'fail'; ?>">
                    <?php echo $uploadDirWritable ? 'OK' : 'PROBLEM'; ?>
                </td>
                <td>
                    Path: <?php echo $uploadDir; ?><br>
                    <?php echo $uploadDirWritable ? "Is writable." : "Not writable (permission denied)."; ?>
                </td>
            </tr>
        </table>
        
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
    </div>
    
    <div class="section">
        <h2>Test Chunk Upload</h2>
        <div id="uploadTest">
            <button id="testButton">Run Test Upload</button>
            <div id="testResults"></div>
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
    </div>
    
    <div class="section">
        <h2>Recommendations</h2>
        <ul>
            <li>Make sure the temp directory has proper write permissions (chmod 755)</li>
            <li>For large file uploads, increase PHP limits in php.ini:
                <pre>
upload_max_filesize = 2048M
post_max_size = 2048M
max_execution_time = 600
memory_limit = 512M</pre>
            </li>
            <li>If using Apache, ensure the LimitRequestBody directive is set high enough</li>
            <li>If using Nginx, check client_max_body_size setting</li>
        </ul>
    </div>
</body>
</html>
