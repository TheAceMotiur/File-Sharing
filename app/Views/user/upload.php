<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - <?php echo getSiteName(); ?></title>
    <link rel="icon" type="image/png" href="/icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include __DIR__ . '/../partials/header.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <div class="bg-white rounded-lg shadow p-8">
            <h1 class="text-3xl font-bold mb-8">Upload Files</h1>
            
            <div id="upload-area" class="border-4 border-dashed border-gray-300 rounded-lg p-12 text-center hover:border-blue-500 transition cursor-pointer">
                <i class="fas fa-cloud-upload-alt text-6xl text-gray-400 mb-4"></i>
                <p class="text-xl text-gray-600 mb-2">Drag & drop files here</p>
                <p class="text-gray-500 mb-4">or</p>
                <label for="file-input" class="bg-blue-500 text-white px-6 py-3 rounded-lg cursor-pointer hover:bg-blue-600 inline-block">
                    <i class="fas fa-folder-open mr-2"></i>Choose Files
                </label>
                <input type="file" id="file-input" multiple class="hidden">
                <p class="text-sm text-gray-400 mt-4">Maximum file size: 2 GB per file</p>
            </div>

            <!-- Folder Selection -->
            <?php if (!empty($folders)): ?>
            <div class="mt-6">
                <label class="block text-gray-700 font-medium mb-2">Upload to folder (optional):</label>
                <select id="folder-select" class="w-full px-4 py-2 border rounded-lg">
                    <option value="">Root (No folder)</option>
                    <?php foreach ($folders as $folder): ?>
                    <option value="<?php echo $folder['id']; ?>"><?php echo htmlspecialchars($folder['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Upload Progress -->
            <div id="upload-list" class="mt-8 space-y-4"></div>
        </div>
    </div>

    <script>
    const uploadArea = document.getElementById('upload-area');
    const fileInput = document.getElementById('file-input');
    const uploadList = document.getElementById('upload-list');
    const folderSelect = document.getElementById('folder-select');

    // Drag and drop
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('border-blue-500', 'bg-blue-50');
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('border-blue-500', 'bg-blue-50');
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('border-blue-500', 'bg-blue-50');
        handleFiles(e.dataTransfer.files);
    });

    uploadArea.addEventListener('click', (e) => {
        // Don't trigger if clicking the label
        if (e.target.tagName !== 'LABEL' && !e.target.closest('label')) {
            fileInput.click();
        }
    });
    fileInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
        e.target.value = ''; // Reset input so same file can be selected again
    });

    function handleFiles(files) {
        for (let file of files) {
            uploadFile(file);
        }
    }

    function uploadFile(file) {
        const folderId = folderSelect ? folderSelect.value : '';
        const formData = new FormData();
        formData.append('file', file);
        if (folderId) formData.append('folder_id', folderId);

        const progressDiv = document.createElement('div');
        progressDiv.className = 'bg-gray-50 rounded-lg p-4';
        progressDiv.innerHTML = `
            <div class="flex items-center justify-between mb-2">
                <span class="font-medium">${file.name}</span>
                <span class="text-sm text-gray-500" id="progress-${file.name}">0%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-blue-500 h-2 rounded-full transition-all" style="width: 0%" id="bar-${file.name}"></div>
            </div>
        `;
        uploadList.appendChild(progressDiv);

        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percent = (e.loaded / e.total) * 100;
                document.getElementById(`progress-${file.name}`).textContent = Math.round(percent) + '%';
                document.getElementById(`bar-${file.name}`).style.width = percent + '%';
            }
        });

        xhr.addEventListener('load', () => {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        progressDiv.innerHTML = `
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-green-600"><i class="fas fa-check-circle mr-2"></i>${file.name}</span>
                                <span class="text-sm">
                                    <a href="${response.file.url}" target="_blank" class="text-blue-500 hover:text-blue-700">View</a>
                                </span>
                            </div>
                        `;
                    } else {
                        progressDiv.innerHTML = `
                            <span class="text-red-600"><i class="fas fa-times-circle mr-2"></i>Failed: ${response.error || 'Unknown error'}</span>
                        `;
                    }
                } catch (e) {
                    progressDiv.innerHTML = `
                        <span class="text-red-600"><i class="fas fa-times-circle mr-2"></i>Error parsing response: ${e.message}</span>
                    `;
                    console.error('Response:', xhr.responseText);
                }
            } else {
                progressDiv.innerHTML = `
                    <span class="text-red-600"><i class="fas fa-times-circle mr-2"></i>Server error: ${xhr.status}</span>
                `;
                console.error('Status:', xhr.status, 'Response:', xhr.responseText);
            }
        });

        xhr.addEventListener('error', () => {
            progressDiv.innerHTML = `
                <span class="text-red-600"><i class="fas fa-times-circle mr-2"></i>Network error occurred</span>
            `;
        });

        xhr.open('POST', '/api/upload');
        xhr.send(formData);
    }
    </script>

    <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
