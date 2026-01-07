<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ads.php';
require_once __DIR__ . '/includes/auth.php';

checkEmailVerification();

try {
    $db = getDBConnection();
    
    // Get user info
    $stmt = $db->prepare("SELECT name, email, created_at, email_verified, premium FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    $_SESSION['premium'] = $user['premium'];
    $_SESSION['user_premium'] = $user['premium'];
 
    // Get statistics
    $stmt = $db->prepare("SELECT 
        COUNT(*) as total_files,
        COALESCE(SUM(size), 0) as total_size
        FROM file_uploads 
        WHERE uploaded_by = ? AND upload_status = 'completed'");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    // Get folder count
    $stmt = $db->prepare("SELECT COUNT(*) as total_folders FROM folders WHERE created_by = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $folderCount = $stmt->get_result()->fetch_assoc()['total_folders'];

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Files - <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/vue@3.3.4/dist/vue.global.prod.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        [v-cloak] { display: none; }
        
        .fade-enter-active, .fade-leave-active {
            transition: opacity 0.3s;
        }
        .fade-enter-from, .fade-leave-to {
            opacity: 0;
        }
        
        .slide-up-enter-active, .slide-up-leave-active {
            transition: all 0.3s;
        }
        .slide-up-enter-from {
            transform: translateY(10px);
            opacity: 0;
        }
        .slide-up-leave-to {
            transform: translateY(-10px);
            opacity: 0;
        }
        
        .item-card {
            transition: all 0.2s;
        }
        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .context-menu {
            position: fixed;
            z-index: 9999;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            min-width: 200px;
        }
        
        .drag-over {
            border-color: #3b82f6 !important;
            background-color: rgba(59, 130, 246, 0.05);
        }
        
        .drag-active {
            opacity: 0.5;
        }
        
        .folder-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .lightbox {
            background: rgba(0, 0, 0, 0.95);
        }
        
        .breadcrumb-item:hover {
            text-decoration: underline;
        }
        
        /* Progress bar animation */
        @keyframes progress-animation {
            0% { background-position: 0 0; }
            100% { background-position: 50px 0; }
        }
        
        .progress-animated {
            background: linear-gradient(
                90deg,
                #3b82f6 0%,
                #60a5fa 25%,
                #3b82f6 50%,
                #60a5fa 75%,
                #3b82f6 100%
            );
            background-size: 50px 100%;
            animation: progress-animation 1s linear infinite;
        }
        
        /* Spinner animation */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .fa-spinner {
            animation: spin 1s linear infinite;
        }
        
        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'header.php'; ?>
    
    <div id="app" v-cloak class="min-h-screen">
        <!-- Main Container -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            
            <!-- Header Section -->
            <div class="mb-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">My Files</h1>
                        <p class="text-gray-600 mt-1">
                            <i class="fas fa-folder mr-2"></i><?php echo number_format($folderCount); ?> folders • 
                            <i class="fas fa-file ml-2 mr-2"></i><?php echo number_format($stats['total_files']); ?> files • 
                            <i class="fas fa-hdd ml-2 mr-2"></i><?php echo number_format($stats['total_size'] / 1024 / 1024 / 1024, 2); ?> GB used
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button @click="showCreateFolderModal = true" 
                                class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            <i class="fas fa-folder-plus mr-2"></i>
                            New Folder
                        </button>
                        <button @click="showUploadModal = true" 
                                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors shadow-sm">
                            <i class="fas fa-cloud-upload-alt mr-2"></i>
                            Upload Files
                        </button>
                    </div>
                </div>
                
                <!-- Breadcrumb Navigation -->
                <nav class="flex items-center text-sm bg-white px-4 py-3 rounded-lg shadow-sm">
                    <button @click="navigateToFolder(null)" 
                            class="text-blue-600 hover:text-blue-800 breadcrumb-item flex items-center">
                        <i class="fas fa-home mr-2"></i>
                        My Files
                    </button>
                    <template v-for="(crumb, index) in breadcrumbs.slice(1)" :key="crumb.id">
                        <i class="fas fa-chevron-right mx-3 text-gray-400"></i>
                        <button @click="navigateToFolder(crumb.id)" 
                                class="text-blue-600 hover:text-blue-800 breadcrumb-item">
                            {{ crumb.name }}
                        </button>
                    </template>
                </nav>
            </div>
            
            <!-- Toolbar -->
            <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <!-- Search -->
                    <div class="flex-1 max-w-md">
                        <div class="relative">
                            <input v-model="searchQuery" 
                                   @input="debouncedSearch"
                                   type="text" 
                                   placeholder="Search files and folders..."
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <button v-if="searchQuery" 
                                    @click="searchQuery = ''; loadFiles(currentFolder)"
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- View Controls -->
                    <div class="flex items-center gap-4">
                        <!-- Sort -->
                        <select v-model="sortBy" 
                                @change="loadFiles(currentFolder)"
                                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                            <option value="name">Name</option>
                            <option value="created_at">Date</option>
                            <option value="size">Size</option>
                        </select>
                        
                        <!-- Sort Order -->
                        <button @click="toggleSortOrder"
                                class="p-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            <i :class="sortOrder === 'asc' ? 'fas fa-sort-amount-up' : 'fas fa-sort-amount-down'"></i>
                        </button>
                        
                        <!-- View Toggle -->
                        <div class="flex border border-gray-300 rounded-lg overflow-hidden">
                            <button @click="viewMode = 'grid'" 
                                    :class="viewMode === 'grid' ? 'bg-blue-50 text-blue-600' : 'bg-white text-gray-600'"
                                    class="px-3 py-2 hover:bg-gray-50 transition-colors">
                                <i class="fas fa-th"></i>
                            </button>
                            <button @click="viewMode = 'list'" 
                                    :class="viewMode === 'list' ? 'bg-blue-50 text-blue-600' : 'bg-white text-gray-600'"
                                    class="px-3 py-2 hover:bg-gray-50 transition-colors border-l border-gray-300">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Loading State -->
            <div v-if="loading" class="text-center py-12">
                <i class="fas fa-spinner fa-spin text-4xl text-blue-600"></i>
                <p class="mt-4 text-gray-600">Loading your files...</p>
            </div>
            
            <!-- Grid View -->
            <div v-else-if="viewMode === 'grid'" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <!-- Folders -->
                <div v-for="folder in folderItems" 
                     :key="'folder-' + folder.id"
                     @click="navigateToFolder(folder.id)"
                     @contextmenu.prevent="showContextMenu($event, folder)"
                     @dragover.prevent="onDragOver($event, folder)"
                     @dragleave.prevent="onDragLeave($event)"
                     @drop.prevent="onDrop($event, folder)"
                     :draggable="true"
                     @dragstart="onDragStart($event, folder)"
                     @dragend="onDragEnd"
                     class="item-card bg-white rounded-lg p-4 cursor-pointer border-2 border-gray-200 hover:border-blue-400 transition-all">
                    <div v-if="editingItem && editingItem.id === folder.id" @click.stop>
                        <input v-model="editingName" 
                               @keyup.enter="renameItem(folder)"
                               @keyup.esc="cancelRename"
                               @blur="renameItem(folder)"
                               ref="renameInput"
                               class="w-full px-2 py-1 border border-blue-500 rounded focus:outline-none">
                    </div>
                    <template v-else>
                        <div class="flex flex-col items-center">
                            <div class="w-16 h-16 flex items-center justify-center rounded-xl folder-icon text-white mb-3">
                                <i class="fas fa-folder text-3xl"></i>
                            </div>
                            <p class="text-sm font-medium text-gray-900 text-center truncate w-full" :title="folder.name">
                                {{ folder.name }}
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ folder.file_count + folder.subfolder_count }} items
                            </p>
                        </div>
                    </template>
                </div>
                
                <!-- Files -->
                <div v-for="file in fileItems" 
                     :key="'file-' + file.id"
                     @click="handleFileClick(file)"
                     @contextmenu.prevent="showContextMenu($event, file)"
                     :draggable="true"
                     @dragstart="onDragStart($event, file)"
                     @dragend="onDragEnd"
                     class="item-card bg-white rounded-lg p-4 cursor-pointer border-2 border-gray-200 hover:border-blue-400 transition-all">
                    <div v-if="editingItem && editingItem.id === file.id" @click.stop>
                        <input v-model="editingName" 
                               @keyup.enter="renameItem(file)"
                               @keyup.esc="cancelRename"
                               @blur="renameItem(file)"
                               ref="renameInput"
                               class="w-full px-2 py-1 border border-blue-500 rounded focus:outline-none">
                    </div>
                    <template v-else>
                        <div class="flex flex-col items-center">
                            <!-- File Icon/Thumbnail -->
                            <div v-if="file.is_image" class="w-16 h-16 mb-3 rounded-lg overflow-hidden bg-gray-100">
                                <img :src="'/download/' + file.id + '/download'" 
                                     :alt="file.name"
                                     class="w-full h-full object-cover"
                                     @error="$event.target.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%23999%22%3E%3Cpath d=%22M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z%22/%3E%3C/svg%3E'">
                            </div>
                            <div v-else class="w-16 h-16 flex items-center justify-center rounded-xl bg-gradient-to-br from-gray-400 to-gray-500 text-white mb-3">
                                <i :class="getFileIcon(file.name)" class="text-3xl"></i>
                            </div>
                            
                            <p class="text-sm font-medium text-gray-900 text-center truncate w-full" :title="file.name">
                                {{ file.name }}
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ formatFileSize(file.size) }}
                            </p>
                        </div>
                    </template>
                </div>
                
                <!-- Empty State -->
                <div v-if="folderItems.length === 0 && fileItems.length === 0" 
                     class="col-span-full text-center py-12">
                    <i class="fas fa-folder-open text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-medium text-gray-900 mb-2">This folder is empty</h3>
                    <p class="text-gray-600 mb-6">Upload files or create folders to get started</p>
                    <button @click="showUploadModal = true" 
                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-cloud-upload-alt mr-2"></i>
                        Upload Files
                    </button>
                </div>
            </div>
            
            <!-- List View -->
            <div v-else-if="viewMode === 'list'" class="bg-white rounded-lg shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Name
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Size
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Modified
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <!-- Folders -->
                        <tr v-for="folder in folderItems" 
                            :key="'folder-' + folder.id"
                            @contextmenu.prevent="showContextMenu($event, folder)"
                            @dragover.prevent="onDragOver($event, folder)"
                            @dragleave.prevent="onDragLeave($event)"
                            @drop.prevent="onDrop($event, folder)"
                            :draggable="true"
                            @dragstart="onDragStart($event, folder)"
                            @dragend="onDragEnd"
                            class="hover:bg-gray-50 cursor-pointer">
                            <td @click="navigateToFolder(folder.id)" class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-lg folder-icon">
                                        <i class="fas fa-folder text-white"></i>
                                    </div>
                                    <div class="ml-4">
                                        <div v-if="editingItem && editingItem.id === folder.id" @click.stop>
                                            <input v-model="editingName" 
                                                   @keyup.enter="renameItem(folder)"
                                                   @keyup.esc="cancelRename"
                                                   @blur="renameItem(folder)"
                                                   ref="renameInput"
                                                   class="px-2 py-1 border border-blue-500 rounded focus:outline-none">
                                        </div>
                                        <div v-else class="text-sm font-medium text-gray-900">{{ folder.name }}</div>
                                        <div class="text-sm text-gray-500">{{ folder.file_count + folder.subfolder_count }} items</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">—</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ formatDate(folder.created_at) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button @click.stop="startRename(folder)" class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button @click.stop="confirmDeleteItem(folder)" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        
                        <!-- Files -->
                        <tr v-for="file in fileItems" 
                            :key="'file-' + file.id"
                            @click="handleFileClick(file)"
                            @contextmenu.prevent="showContextMenu($event, file)"
                            :draggable="true"
                            @dragstart="onDragStart($event, file)"
                            @dragend="onDragEnd"
                            class="hover:bg-gray-50 cursor-pointer">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div v-if="file.is_image" class="flex-shrink-0 h-10 w-10 rounded overflow-hidden bg-gray-100">
                                        <img :src="'/download/' + file.id + '/download'" 
                                             :alt="file.name"
                                             class="w-full h-full object-cover">
                                    </div>
                                    <div v-else class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-lg bg-gradient-to-br from-gray-400 to-gray-500">
                                        <i :class="getFileIcon(file.name)" class="text-white"></i>
                                    </div>
                                    <div class="ml-4">
                                        <div v-if="editingItem && editingItem.id === file.id" @click.stop>
                                            <input v-model="editingName" 
                                                   @keyup.enter="renameItem(file)"
                                                   @keyup.esc="cancelRename"
                                                   @blur="renameItem(file)"
                                                   ref="renameInput"
                                                   class="px-2 py-1 border border-blue-500 rounded focus:outline-none">
                                        </div>
                                        <div v-else class="text-sm font-medium text-gray-900">{{ file.name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ formatFileSize(file.size) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ formatDate(file.created_at) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button @click.stop="startRename(file)" class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a :href="'/download/' + file.id" 
                                   @click.stop
                                   class="text-green-600 hover:text-green-900 mr-3">
                                    <i class="fas fa-download"></i>
                                </a>
                                <button @click.stop="confirmDeleteItem(file)" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        
                        <!-- Empty State -->
                        <tr v-if="folderItems.length === 0 && fileItems.length === 0">
                            <td colspan="4" class="px-6 py-12 text-center">
                                <i class="fas fa-folder-open text-6xl text-gray-300 mb-4"></i>
                                <h3 class="text-xl font-medium text-gray-900 mb-2">This folder is empty</h3>
                                <p class="text-gray-600">Upload files or create folders to get started</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Context Menu -->
        <div v-if="showingContextMenu" 
             :style="{ top: contextMenuPosition.y + 'px', left: contextMenuPosition.x + 'px' }"
             class="context-menu">
            <div class="py-1">
                <button v-if="contextMenuItem.type === 'folder'"
                        @click="navigateToFolder(contextMenuItem.id); hideContextMenu()"
                        class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center">
                    <i class="fas fa-folder-open w-5 mr-2"></i>
                    Open
                </button>
                <button v-if="contextMenuItem.type === 'file' && contextMenuItem.is_image"
                        @click="previewImage = contextMenuItem; hideContextMenu()"
                        class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center">
                    <i class="fas fa-eye w-5 mr-2"></i>
                    Preview
                </button>
                <button v-if="contextMenuItem.type === 'file'"
                        @click="downloadFile(contextMenuItem); hideContextMenu()"
                        class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center">
                    <i class="fas fa-download w-5 mr-2"></i>
                    Download
                </button>
                <button @click="startRename(contextMenuItem); hideContextMenu()"
                        class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center">
                    <i class="fas fa-edit w-5 mr-2"></i>
                    Rename
                </button>
                <hr class="my-1">
                <button @click="confirmDeleteItem(contextMenuItem); hideContextMenu()"
                        class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center">
                    <i class="fas fa-trash w-5 mr-2"></i>
                    Delete
                </button>
            </div>
        </div>
        
        <!-- Create Folder Modal -->
        <transition name="fade">
            <div v-if="showCreateFolderModal" 
                 @click.self="showCreateFolderModal = false"
                 class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                    <h3 class="text-lg font-semibold mb-4">Create New Folder</h3>
                    <input v-model="newFolderName" 
                           @keyup.enter="createFolder"
                           ref="folderNameInput"
                           placeholder="Folder name"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <div class="flex justify-end gap-3 mt-6">
                        <button @click="showCreateFolderModal = false" 
                                class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg">
                            Cancel
                        </button>
                        <button @click="createFolder" 
                                :disabled="!newFolderName.trim()"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                            Create
                        </button>
                    </div>
                </div>
            </div>
        </transition>
        
        <!-- Upload Modal -->
        <transition name="fade">
            <div v-if="showUploadModal" 
                 @click.self="cancelUpload"
                 class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Upload Files</h3>
                        
                        <!-- Drag and Drop Area -->
                        <div @dragover.prevent="uploadDragOver = true"
                             @dragleave.prevent="uploadDragOver = false"
                             @drop.prevent="handleFileDrop"
                             :class="uploadDragOver ? 'border-blue-500 bg-blue-50' : 'border-gray-300'"
                             class="border-2 border-dashed rounded-lg p-8 text-center mb-4 transition-colors">
                            <i class="fas fa-cloud-upload-alt text-5xl text-gray-400 mb-4"></i>
                            <p class="text-gray-600 mb-2">Drag and drop files here or</p>
                            <label class="inline-block px-4 py-2 bg-blue-600 text-white rounded-lg cursor-pointer hover:bg-blue-700">
                                Browse Files
                                <input type="file" 
                                       @change="handleFileSelect"
                                       multiple 
                                       class="hidden">
                            </label>
                        </div>
                        
                        <!-- File List -->
                        <div v-if="filesToUpload.length > 0" class="space-y-2 mb-4">
                            <!-- Overall progress (only show when uploading) -->
                            <div v-if="isUploading" class="mb-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-blue-900">
                                        Uploading {{ Object.values(uploadStatus).filter(s => s.completed).length }} of {{ filesToUpload.length }} files
                                    </span>
                                    <span class="text-sm font-semibold text-blue-900">
                                        {{ Math.round((Object.values(uploadStatus).filter(s => s.completed).length / filesToUpload.length) * 100) }}%
                                    </span>
                                </div>
                                <div class="w-full bg-blue-200 rounded-full h-2 overflow-hidden">
                                    <div :style="{ width: ((Object.values(uploadStatus).filter(s => s.completed).length / filesToUpload.length) * 100) + '%' }"
                                         class="bg-blue-600 h-2 rounded-full transition-all duration-300"></div>
                                </div>
                            </div>
                            
                            <!-- Individual files -->
                            <div v-for="(file, index) in filesToUpload" 
                                 :key="index"
                                 class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                                 :class="uploadStatus[index]?.uploading ? 'border-2 border-blue-400' : ''">
                                <div class="flex items-center flex-1 min-w-0">
                                    <i class="fas fa-file text-gray-400 mr-3"></i>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ file.name }}</p>
                                        <p class="text-xs text-gray-500">{{ formatFileSize(file.size) }}</p>
                                    </div>
                                </div>
                                <div v-if="uploadStatus[index]" class="ml-4 flex items-center">
                                    <div v-if="uploadStatus[index].uploading" class="flex items-center">
                                        <div class="w-32 bg-gray-200 rounded-full h-2.5 mr-3 overflow-hidden">
                                            <div :style="{ width: uploadStatus[index].progress + '%' }"
                                                 :class="uploadStatus[index].progress < 100 ? 'progress-animated' : 'bg-green-500'"
                                                 class="h-2.5 rounded-full transition-all duration-300"></div>
                                        </div>
                                        <span class="text-xs font-medium text-gray-700 w-10">{{ uploadStatus[index].progress }}%</span>
                                    </div>
                                    <i v-else-if="uploadStatus[index].completed" 
                                       class="fas fa-check-circle text-green-500 text-lg"></i>
                                    <i v-else-if="uploadStatus[index].error" 
                                       class="fas fa-exclamation-circle text-red-500 text-lg"
                                       title="Upload failed"></i>
                                </div>
                                <button v-else 
                                        @click="removeFile(index)"
                                        class="ml-2 text-gray-400 hover:text-red-600">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="flex justify-end gap-3 mt-6">
                            <button @click="cancelUpload" 
                                    :disabled="isUploading"
                                    class="px-5 py-2.5 text-gray-700 hover:bg-gray-100 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                {{ isUploading ? 'Uploading...' : 'Cancel' }}
                            </button>
                            <button @click="startUpload" 
                                    :disabled="filesToUpload.length === 0 || isUploading"
                                    class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center">
                                <i v-if="isUploading" class="fas fa-spinner fa-spin mr-2"></i>
                                <i v-else class="fas fa-cloud-upload-alt mr-2"></i>
                                {{ isUploading ? 'Uploading...' : 'Upload Files' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
        
        <!-- Image Preview Lightbox -->
        <transition name="fade">
            <div v-if="previewImage" 
                 @click.self="previewImage = null"
                 class="fixed inset-0 lightbox flex items-center justify-center z-50 p-4">
                <button @click="previewImage = null" 
                        class="absolute top-4 right-4 text-white text-2xl hover:text-gray-300 z-10">
                    <i class="fas fa-times"></i>
                </button>
                <div class="max-w-5xl max-h-full flex flex-col">
                    <img :src="'/download/' + previewImage.id + '/download'" 
                         :alt="previewImage.name"
                         class="max-w-full max-h-[80vh] object-contain">
                    <div class="text-center mt-4">
                        <p class="text-white text-lg">{{ previewImage.name }}</p>
                        <p class="text-gray-400 text-sm mt-1">{{ formatFileSize(previewImage.size) }}</p>
                        <div class="mt-4 flex justify-center gap-4">
                            <a :href="'/download/' + previewImage.id" 
                               class="px-4 py-2 bg-white text-gray-900 rounded-lg hover:bg-gray-100">
                                <i class="fas fa-download mr-2"></i>
                                Download
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
        
        <!-- Delete Confirmation Modal -->
        <transition name="fade">
            <div v-if="showDeleteConfirmation" 
                 @click.self="showDeleteConfirmation = false"
                 class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-lg p-6 max-w-md w-full">
                    <h3 class="text-lg font-semibold mb-4 text-red-600">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Confirm Delete
                    </h3>
                    <p class="text-gray-700 mb-6">
                        Are you sure you want to delete <strong>{{ deleteItem?.name }}</strong>?
                        <span v-if="deleteItem?.type === 'folder'">
                            This will also delete all files and subfolders inside it.
                        </span>
                        This action cannot be undone.
                    </p>
                    <div class="flex justify-end gap-3">
                        <button @click="showDeleteConfirmation = false; deleteItem = null" 
                                class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg">
                            Cancel
                        </button>
                        <button @click="deleteItem && performDelete(deleteItem)" 
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script>
    const { createApp } = Vue;
    
    createApp({
        data() {
            return {
                // Data
                folderItems: [],
                fileItems: [],
                breadcrumbs: [{ id: null, name: 'My Files' }],
                currentFolder: null,
                
                // UI State
                loading: true,
                viewMode: 'grid',
                sortBy: 'name',
                sortOrder: 'asc',
                searchQuery: '',
                searchTimeout: null,
                
                // Modals
                showCreateFolderModal: false,
                showUploadModal: false,
                showDeleteConfirmation: false,
                showingContextMenu: false,
                
                // Context Menu
                contextMenuPosition: { x: 0, y: 0 },
                contextMenuItem: null,
                
                // Folder Operations
                newFolderName: '',
                editingItem: null,
                editingName: '',
                deleteItem: null,
                
                // Upload
                filesToUpload: [],
                uploadStatus: {},
                isUploading: false,
                uploadDragOver: false,
                
                // Drag & Drop
                dragItem: null,
                dragOver: false,
                
                // Preview
                previewImage: null,
            };
        },
        
        mounted() {
            this.loadFiles();
            document.addEventListener('click', this.hideContextMenu);
            document.addEventListener('keydown', this.handleKeyboard);
            
            // Auto-focus folder name input when modal opens
            this.$watch('showCreateFolderModal', (isVisible) => {
                if (isVisible) {
                    this.$nextTick(() => {
                        this.$refs.folderNameInput?.focus();
                    });
                }
            });
        },
        
        beforeUnmount() {
            document.removeEventListener('click', this.hideContextMenu);
            document.removeEventListener('keydown', this.handleKeyboard);
        },
        
        methods: {
            // Load Files and Folders
            async loadFiles(folderId = null) {
                this.loading = true;
                try {
                    const params = new URLSearchParams({
                        action: 'list',
                        parent_id: folderId || '',
                        sort_by: this.sortBy,
                        sort_order: this.sortOrder,
                        search: this.searchQuery
                    });
                    
                    const response = await fetch(`/api/folders.php?${params}`);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.folderItems = data.folders || [];
                        this.fileItems = data.files || [];
                        this.breadcrumbs = data.breadcrumbs || [{ id: null, name: 'My Files' }];
                        this.currentFolder = data.current_folder;
                    } else {
                        throw new Error(data.error || 'Unknown error');
                    }
                } catch (error) {
                    console.error('Error loading files:', error);
                    
                    // Show more detailed error
                    let errorMessage = 'Failed to load files. ';
                    if (error.message.includes('HTTP error')) {
                        errorMessage += 'Server error. Please check if you are logged in.';
                    } else if (error.message.includes('JSON')) {
                        errorMessage += 'Invalid server response.';
                    } else {
                        errorMessage += error.message;
                    }
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Error Loading Files',
                        text: errorMessage,
                        footer: '<a href="/login.php">Click here to login</a>'
                    });
                    
                    // Set empty arrays so UI doesn't break
                    this.folderItems = [];
                    this.fileItems = [];
                } finally {
                    this.loading = false;
                }
            },
            
            // Navigation
            navigateToFolder(folderId) {
                this.currentFolder = folderId;
                this.loadFiles(folderId);
            },
            
            // Search
            debouncedSearch() {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.loadFiles(this.currentFolder);
                }, 300);
            },
            
            // Sorting
            toggleSortOrder() {
                this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
                this.loadFiles(this.currentFolder);
            },
            
            // Create Folder
            async createFolder() {
                if (!this.newFolderName.trim()) return;
                
                try {
                    const formData = new FormData();
                    formData.append('name', this.newFolderName);
                    if (this.currentFolder) {
                        formData.append('parent_id', this.currentFolder);
                    }
                    
                    const response = await fetch('/api/folders.php?action=create', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.folderItems.unshift(data.folder);
                        this.newFolderName = '';
                        this.showCreateFolderModal = false;
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: 'Folder created successfully!',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        throw new Error(data.error);
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message || 'Failed to create folder'
                    });
                }
            },
            
            // Rename
            startRename(item) {
                this.editingItem = item;
                this.editingName = item.name;
                this.$nextTick(() => {
                    const input = this.$refs.renameInput;
                    if (input) {
                        if (Array.isArray(input)) {
                            input[0]?.focus();
                            input[0]?.select();
                        } else {
                            input.focus();
                            input.select();
                        }
                    }
                });
            },
            
            cancelRename() {
                this.editingItem = null;
                this.editingName = '';
            },
            
            async renameItem(item) {
                if (!this.editingName.trim() || this.editingName === item.name) {
                    this.cancelRename();
                    return;
                }
                
                try {
                    const formData = new FormData();
                    
                    if (item.type === 'folder') {
                        formData.append('folder_id', item.id);
                        formData.append('name', this.editingName);
                        
                        const response = await fetch('/api/folders.php?action=rename', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            item.name = this.editingName;
                        } else {
                            throw new Error(data.error);
                        }
                    } else {
                        // For files, preserve extension
                        const oldName = item.name;
                        const lastDotIndex = oldName.lastIndexOf('.');
                        let newName = this.editingName;
                        
                        if (lastDotIndex > 0) {
                            const extension = oldName.substring(lastDotIndex);
                            if (!newName.endsWith(extension)) {
                                newName += extension;
                            }
                        }
                        
                        formData.append('file_id', item.id);
                        formData.append('new_name', newName);
                        formData.append('action', 'rename');
                        
                        const response = await fetch('/api/rename.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            item.name = newName;
                        } else {
                            throw new Error(data.error);
                        }
                    }
                    
                    this.cancelRename();
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Item renamed successfully!',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message || 'Failed to rename item'
                    });
                    this.cancelRename();
                }
            },
            
            // Delete
            confirmDeleteItem(item) {
                this.deleteItem = item;
                this.showDeleteConfirmation = true;
            },
            
            async performDelete(item) {
                try {
                    if (item.type === 'folder') {
                        const formData = new FormData();
                        formData.append('folder_id', item.id);
                        formData.append('action', 'delete');
                        
                        const response = await fetch('/api/folders.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            this.folderItems = this.folderItems.filter(f => f.id !== item.id);
                        } else {
                            throw new Error(data.error);
                        }
                    } else {
                        const formData = new FormData();
                        formData.append('file_id', item.id);
                        
                        const response = await fetch('/api/delete.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            this.fileItems = this.fileItems.filter(f => f.id !== item.id);
                            console.log('File deleted from:', data.deleted_from);
                        } else {
                            throw new Error(data.error);
                        }
                    }
                    
                    this.showDeleteConfirmation = false;
                    this.deleteItem = null;
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: item.type === 'folder' 
                            ? 'Folder and its contents deleted successfully' 
                            : 'File removed from all locations',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message || 'Failed to delete item'
                    });
                }
            },
            
            // File Operations
            handleFileClick(file) {
                if (file.is_image) {
                    this.previewImage = file;
                } else {
                    window.open(`/download/${file.id}`, '_blank');
                }
            },
            
            downloadFile(file) {
                window.open(`/download/${file.id}`, '_blank');
            },
            
            // Upload
            validateFile(file) {
                const maxSize = 2 * 1024 * 1024 * 1024; // 2GB
                const allowedExtensions = [
                    'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
                    'mp3', 'wav', 'ogg', 'm4a', 'aac',
                    'mp4', 'mpeg', 'webm', 'mov', 'avi',
                    'zip', 'rar', '7z', 'tar', 'gz',
                    'pdf', 'djvu',
                    'm3u8', 'm3u'
                ];
                
                const extension = file.name.split('.').pop().toLowerCase();
                
                if (!allowedExtensions.includes(extension)) {
                    return `File type .${extension} is not supported`;
                }
                
                if (file.size > maxSize) {
                    return `File size exceeds 2GB limit`;
                }
                
                return null;
            },
            
            handleFileSelect(event) {
                const files = Array.from(event.target.files);
                const validFiles = [];
                const errors = [];
                
                files.forEach(file => {
                    const error = this.validateFile(file);
                    if (error) {
                        errors.push(`${file.name}: ${error}`);
                    } else {
                        validFiles.push(file);
                    }
                });
                
                if (errors.length > 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Some files were rejected',
                        html: errors.join('<br>'),
                        confirmButtonText: 'OK'
                    });
                }
                
                this.filesToUpload.push(...validFiles);
                event.target.value = '';
            },
            
            handleFileDrop(event) {
                this.uploadDragOver = false;
                const files = Array.from(event.dataTransfer.files);
                const validFiles = [];
                const errors = [];
                
                files.forEach(file => {
                    const error = this.validateFile(file);
                    if (error) {
                        errors.push(`${file.name}: ${error}`);
                    } else {
                        validFiles.push(file);
                    }
                });
                
                if (errors.length > 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Some files were rejected',
                        html: errors.join('<br>'),
                        confirmButtonText: 'OK'
                    });
                }
                
                this.filesToUpload.push(...validFiles);
            },
            
            removeFile(index) {
                this.filesToUpload.splice(index, 1);
            },
            
            async startUpload() {
                this.isUploading = true;
                this.uploadStatus = {};
                
                try {
                    // Upload files one by one for better progress tracking
                    for (let i = 0; i < this.filesToUpload.length; i++) {
                        // Initialize status for this file
                        this.uploadStatus[i] = {
                            uploading: true,
                            progress: 0,
                            completed: false,
                            error: false
                        };
                        
                        const file = this.filesToUpload[i];
                        const formData = new FormData();
                        formData.append('files[]', file);
                        
                        if (this.currentFolder) {
                            formData.append('folder_id', this.currentFolder);
                        }
                        
                        try {
                            await this.uploadFile(formData, i);
                        } catch (error) {
                            console.error('Upload error:', error);
                            this.uploadStatus[i].error = true;
                            this.uploadStatus[i].uploading = false;
                        }
                    }
                    
                    this.isUploading = false;
                    const allSuccess = Object.values(this.uploadStatus).every(s => s.completed);
                    
                    if (allSuccess) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Upload Complete!',
                            text: `Successfully uploaded ${this.filesToUpload.length} file(s)`,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        
                        this.showUploadModal = false;
                        this.filesToUpload = [];
                        this.uploadStatus = {};
                        this.loadFiles(this.currentFolder);
                    } else {
                        const errorCount = Object.values(this.uploadStatus).filter(s => s.error).length;
                        const successCount = Object.values(this.uploadStatus).filter(s => s.completed).length;
                        
                        await Swal.fire({
                            icon: 'warning',
                            title: 'Upload Completed with Errors',
                            html: `
                                <p><strong>${successCount}</strong> file(s) uploaded successfully</p>
                                <p><strong>${errorCount}</strong> file(s) failed to upload</p>
                                <p class="text-sm text-gray-600 mt-2">Check the browser console for details</p>
                            `,
                            confirmButtonText: 'OK'
                        });
                        
                        // Refresh to show uploaded files
                        if (successCount > 0) {
                            this.loadFiles(this.currentFolder);
                        }
                    }
                } catch (error) {
                    console.error('Upload error:', error);
                    this.isUploading = false;
                    await Swal.fire({
                        icon: 'error',
                        title: 'Upload Failed',
                        text: error.message || 'An error occurred during upload',
                        confirmButtonText: 'OK'
                    });
                }
            },
            
            uploadFile(formData, index) {
                return new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    
                    // Track upload progress
                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) {
                            const percent = Math.round((e.loaded / e.total) * 100);
                            // Update progress reactively
                            if (this.uploadStatus[index]) {
                                this.uploadStatus[index].progress = percent;
                                // Force Vue to update
                                this.uploadStatus = {...this.uploadStatus};
                            }
                        }
                    });
                    
                    xhr.onload = () => {
                        if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                console.log('Upload response:', response); // Debug log
                                
                                if (response.success) {
                                    this.uploadStatus[index].completed = true;
                                    this.uploadStatus[index].uploading = false;
                                    this.uploadStatus[index].progress = 100;
                                    this.uploadStatus = {...this.uploadStatus};
                                    resolve(response);
                                } else {
                                    const errorMsg = response.error || 'Upload failed';
                                    console.error('Upload failed:', errorMsg, response.debug);
                                    throw new Error(errorMsg);
                                }
                            } catch (error) {
                                console.error('Parse/Upload error:', error, 'Response:', xhr.responseText);
                                this.uploadStatus[index].error = true;
                                this.uploadStatus[index].uploading = false;
                                this.uploadStatus = {...this.uploadStatus};
                                reject(error);
                            }
                        } else {
                            // Try to parse error response
                            try {
                                const errorResponse = JSON.parse(xhr.responseText);
                                console.error('Server error response:', errorResponse);
                                this.uploadStatus[index].error = true;
                                this.uploadStatus[index].uploading = false;
                                this.uploadStatus = {...this.uploadStatus};
                                reject(new Error(errorResponse.error || `Server error: ${xhr.status}`));
                            } catch (e) {
                                console.error('Server error (raw):', xhr.responseText);
                                this.uploadStatus[index].error = true;
                                this.uploadStatus[index].uploading = false;
                                this.uploadStatus = {...this.uploadStatus};
                                reject(new Error(`Server error: ${xhr.status}`));
                            }
                        }
                    };
                    
                    xhr.onerror = () => {
                        this.uploadStatus[index].error = true;
                        this.uploadStatus[index].uploading = false;
                        this.uploadStatus = {...this.uploadStatus};
                        reject(new Error('Network error'));
                    };
                    
                    xhr.ontimeout = () => {
                        this.uploadStatus[index].error = true;
                        this.uploadStatus[index].uploading = false;
                        this.uploadStatus = {...this.uploadStatus};
                        reject(new Error('Upload timeout'));
                    };
                    
                    // Set timeout to 10 minutes for large files
                    xhr.timeout = 600000;
                    
                    xhr.open('POST', '/upload.php', true);
                    xhr.send(formData);
                });
            },
            
            cancelUpload() {
                if (!this.isUploading) {
                    this.showUploadModal = false;
                    this.filesToUpload = [];
                    this.uploadStatus = {};
                }
            },
            
            // Drag and Drop
            onDragStart(event, item) {
                this.dragItem = item;
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', JSON.stringify(item));
                event.target.classList.add('drag-active');
            },
            
            onDragEnd(event) {
                event.target.classList.remove('drag-active');
                this.dragItem = null;
            },
            
            onDragOver(event, folder) {
                if (folder.type === 'folder' && this.dragItem && this.dragItem.id !== folder.id) {
                    event.target.closest('.item-card, tr')?.classList.add('drag-over');
                }
            },
            
            onDragLeave(event) {
                event.target.closest('.item-card, tr')?.classList.remove('drag-over');
            },
            
            async onDrop(event, folder) {
                event.target.closest('.item-card, tr')?.classList.remove('drag-over');
                
                if (!this.dragItem || this.dragItem.type !== 'file' || folder.type !== 'folder') {
                    return;
                }
                
                try {
                    const formData = new FormData();
                    formData.append('file_id', this.dragItem.id);
                    formData.append('target_folder_id', folder.id);
                    formData.append('action', 'move_file');
                    
                    const response = await fetch('/api/folders.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.fileItems = this.fileItems.filter(f => f.id !== this.dragItem.id);
                        Swal.fire({
                            icon: 'success',
                            title: 'Moved',
                            text: 'File moved successfully!',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        throw new Error(data.error);
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message || 'Failed to move file'
                    });
                }
            },
            
            // Context Menu
            showContextMenu(event, item) {
                this.contextMenuItem = item;
                this.showingContextMenu = true;
                
                const menuWidth = 200;
                const menuHeight = 200;
                
                let x = event.clientX;
                let y = event.clientY;
                
                if (x + menuWidth > window.innerWidth) {
                    x = window.innerWidth - menuWidth - 10;
                }
                if (y + menuHeight > window.innerHeight) {
                    y = window.innerHeight - menuHeight - 10;
                }
                
                this.contextMenuPosition = { x, y };
            },
            
            hideContextMenu() {
                this.showingContextMenu = false;
            },
            
            // Keyboard
            handleKeyboard(event) {
                if (event.key === 'Escape') {
                    if (this.showingContextMenu) this.hideContextMenu();
                    if (this.editingItem) this.cancelRename();
                    if (this.showUploadModal && !this.isUploading) this.cancelUpload();
                    if (this.showCreateFolderModal) this.showCreateFolderModal = false;
                    if (this.showDeleteConfirmation) {
                        this.showDeleteConfirmation = false;
                        this.deleteItem = null;
                    }
                    if (this.previewImage) this.previewImage = null;
                }
            },
            
            // Utilities
            getFileIcon(filename) {
                const ext = filename.split('.').pop().toLowerCase();
                const iconMap = {
                    pdf: 'fas fa-file-pdf',
                    doc: 'fas fa-file-word',
                    docx: 'fas fa-file-word',
                    xls: 'fas fa-file-excel',
                    xlsx: 'fas fa-file-excel',
                    ppt: 'fas fa-file-powerpoint',
                    pptx: 'fas fa-file-powerpoint',
                    zip: 'fas fa-file-archive',
                    rar: 'fas fa-file-archive',
                    '7z': 'fas fa-file-archive',
                    mp3: 'fas fa-file-audio',
                    wav: 'fas fa-file-audio',
                    mp4: 'fas fa-file-video',
                    avi: 'fas fa-file-video',
                    mkv: 'fas fa-file-video',
                    txt: 'fas fa-file-alt',
                    html: 'fas fa-file-code',
                    css: 'fas fa-file-code',
                    js: 'fas fa-file-code',
                    php: 'fas fa-file-code',
                    json: 'fas fa-file-code',
                };
                return iconMap[ext] || 'fas fa-file';
            },
            
            formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
            },
            
            formatDate(dateString) {
                const date = new Date(dateString);
                const now = new Date();
                const diffTime = Math.abs(now - date);
                const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
                
                if (diffDays === 0) return 'Today';
                if (diffDays === 1) return 'Yesterday';
                if (diffDays < 7) return `${diffDays} days ago`;
                
                return date.toLocaleDateString();
            }
        }
    }).mount('#app');
    </script>
</body>
</html>
