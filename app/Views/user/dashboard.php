<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - <?php echo getSiteName(); ?></title>
    <link rel="icon" type="image/png" href="/icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/vue@3.3.4/dist/vue.global.prod.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/../partials/styles.php'; ?>
    <style>
        [v-cloak] { display: none; }
            width: 256px;
            background: white;
            border-right: 1px solid #e8eaed;
            position: fixed;
            left: 0;
            top: 64px;
            bottom: 0;
            overflow-y: auto;
        }
        
        .main-content {
            margin-left: 256px;
            padding-top: 64px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
        
        /* Top Header */
        .top-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 64px;
            background: white;
            border-bottom: 1px solid #e8eaed;
            z-index: 100;
        }
        
        /* Card Styles - Google Drive Like */
        .item-card { 
            transition: all 0.2s;
            background: white;
            border: 1px solid transparent;
        }
        .item-card:hover { 
            border-color: #1a73e8;
            box-shadow: 0 1px 3px rgba(60,64,67,.3), 0 4px 8px 3px rgba(60,64,67,.15);
            transform: translateY(-1px);
        }
        
        /* List View Style */
        .list-item {
            transition: background 0.1s;
        }
        .list-item:hover {
            background: #f1f3f4;
        }
        .list-item.selected {
            background: #e8f0fe;
        }
        
        /* Checkbox */
        .checkbox-circle {
            width: 20px;
            height: 20px;
            border: 2px solid #5f6368;
            border-radius: 50%;
            transition: all 0.2s;
        }
        .checkbox-circle.checked {
            background: #1a73e8;
            border-color: #1a73e8;
        }
        
        /* More Options Button */
        .more-btn {
            opacity: 0;
            transition: opacity 0.2s;
        }
        .list-item:hover .more-btn,
        .item-card:hover .more-btn {
            opacity: 1;
        }
        
        /* File Icons - Google Drive Colors */
        .folder-icon { 
            color: #5f6368;
        }
        
        .file-icon-pdf { color: #ea4335; }
        .file-icon-doc { color: #4285f4; }
        .file-icon-xls { color: #0f9d58; }
        .file-icon-ppt { color: #f4b400; }
        .file-icon-img { color: #ea4335; }
        .file-icon-video { color: #f4b400; }
        .file-icon-audio { color: #9c27b0; }
        .file-icon-zip { color: #607d8b; }
        .file-icon-code { color: #00bcd4; }
        .file-icon-default { color: #5f6368; }
        
        /* Context Menu */
        .context-menu { 
            position: fixed;
            z-index: 9999;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(60,64,67,.3), 0 1px 2px rgba(60,64,67,.15);
            min-width: 200px;
            animation: slideInUp 0.15s ease-out;
        }
        
        /* Modal Transitions */
        .modal-enter-active, .modal-leave-active {
            transition: all 0.2s ease;
        }
        .modal-enter-from, .modal-leave-to {
            opacity: 0;
            transform: scale(0.95);
        }
        
        /* Animations */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #dadce0;
            border-radius: 8px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #bdc1c6;
        }
        
        /* Floating Action Button */
        .fab-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 1000;
        }
        .fab {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: white;
            box-shadow: 0 1px 3px rgba(60,64,67,.3), 0 4px 8px 3px rgba(60,64,67,.15);
            transition: all 0.2s;
            color: #5f6368;
        }
        .fab:hover {
            box-shadow: 0 2px 6px rgba(60,64,67,.3), 0 8px 16px 6px rgba(60,64,67,.15);
            transform: scale(1.05);
        }
        
        /* Button Styles */
        .btn-primary {
            background: #1a73e8;
            color: white;
        }
        .btn-primary:hover {
            background: #1765cc;
            box-shadow: 0 1px 2px rgba(60,64,67,.3), 0 1px 3px 1px rgba(60,64,67,.15);
        }
        
        .grid-item:nth-child(4) { animation-delay: 0.2s; }
        .grid-item:nth-child(5) { animation-delay: 0.25s; }
        .grid-item:nth-child(6) { animation-delay: 0.3s; }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Button Hover Effects */
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
        }
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        /* Search Bar Focus Effect */
        .search-input:focus {
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }
        
        /* Fade In Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fadeIn {
            animation: fadeIn 0.5s ease-out;
        }
        
        /* Card hover effect enhancement */
        .item-card {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .item-card:hover {
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
    </style>
</head>
<body x-data="{ sidebarOpen: false }">
    <?php 
    $currentPage = 'dashboard';
    include __DIR__ . '/../partials/header.php'; 
    ?>

    <!-- Sidebar with Vue.js -->
    <div id="sidebar-app" v-cloak :class="{'show': sidebarOpen}" class="sidebar">
        <div class="p-6">
            <button @click="showUploadModal"
                    class="w-full flex items-center justify-center gap-3 px-5 py-3.5 bg-blue-600 text-white rounded-full hover:bg-blue-700 hover:shadow-lg transition-all duration-200 mb-8 font-medium">
                <div class="w-7 h-7 flex items-center justify-center">
                    <i class="fas fa-plus text-lg"></i>
                </div>
                <span>New</span>
            </button>
            
            <nav class="space-y-2">
                <a @click="selectMyFiles" 
                   :class="selectedView === 'myfiles' ? 'bg-blue-50 text-blue-600 font-medium shadow-sm' : 'text-gray-700 hover:bg-gray-100'"
                   class="flex items-center gap-4 px-5 py-3 rounded-xl cursor-pointer transition-all duration-200">
                    <i class="fas fa-folder w-5 text-lg"></i>
                    <span class="text-sm">My Files</span>
                </a>
                <a href="/profile"
                   class="flex items-center gap-4 px-5 py-3 rounded-xl cursor-pointer transition-all duration-200 text-gray-700 hover:bg-gray-100">
                    <i class="fas fa-user w-5 text-lg"></i>
                    <span class="text-sm">Profile</span>
                </a>
                <?php if (isAdmin()): ?>
                <a href="/admin"
                   class="flex items-center gap-4 px-5 py-3 rounded-xl cursor-pointer transition-all duration-200 text-gray-700 hover:bg-gray-100">
                    <i class="fas fa-cog w-5 text-lg"></i>
                    <span class="text-sm">Admin</span>
                </a>
                <?php endif; ?>
            </nav>
            
            <hr class="my-6 border-gray-200">
            
            <div class="px-5 py-3">
                <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Storage</p>
                <div class="space-y-3">
                    <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-2 rounded-full transition-all duration-300" :style="{width: storagePercent + '%'}"></div>
                    </div>
                    <p class="text-xs text-gray-600 font-medium"><?php echo formatFileSize($storageUsed); ?> of <?php echo formatFileSize(5 * 1024 * 1024 * 1024); ?> used</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div id="app" v-cloak class="main-content">
        <div class="px-6 py-4">
            
            <!-- Page Title and Breadcrumb -->
            <div class="mb-8 animate-fadeIn">
                <div v-if="!currentFolder" class="flex items-center justify-between">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900 mb-1">My Drive</h2>
                        <p class="text-sm text-gray-500">{{ filteredFolders.length + filteredFiles.length }} items</p>
                    </div>
                    <button @click="showCreateFolderModal = true" 
                            class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-all duration-200 shadow-sm hover:shadow-md flex items-center gap-2">
                        <i class="fas fa-folder-plus"></i>
                        <span>New Folder</span>
                    </button>
                </div>
                <nav v-else class="flex items-center text-sm">
                    <button @click="currentFolder = null" 
                            class="text-gray-600 hover:text-blue-600 flex items-center transition-colors duration-200 font-medium">
                        <i class="fas fa-home mr-2"></i>
                        My Drive
                    </button>
                    <i class="fas fa-chevron-right mx-3 text-gray-400"></i>
                    <span class="text-gray-900 font-semibold">{{ currentFolder.name }}</span>
                </nav>
            </div>
        
        <!-- Enhanced Loading State -->
        <div v-if="loading" class="text-center py-20">
            <div class="inline-block">
                <div class="w-20 h-20 border-4 border-purple-200 border-t-purple-600 rounded-full animate-spin"></div>
            </div>
            <h3 class="mt-6 text-xl font-semibold text-gray-700">Loading your files...</h3>
            <p class="mt-2 text-gray-500">Please wait a moment</p>
        </div>
        
        <!-- Grid View -->
        <div v-else-if="viewMode === 'grid'" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
            <!-- Folders -->
            <div v-for="folder in filteredFolders" 
                 :key="'folder-' + folder.id"
                 @click.prevent="openFolder(folder)"
                 @contextmenu.prevent="showContextMenu($event, folder)"
                 class="item-card group cursor-pointer transform hover:scale-105 transition-all duration-200">
                <div class="p-5">
                    <!-- Folder Header -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center group-hover:bg-blue-100 transition-colors duration-200">
                            <svg class="w-7 h-7 text-blue-500" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/>
                            </svg>
                        </div>
                        <button @click.stop="showContextMenu($event, folder)" 
                                class="opacity-0 group-hover:opacity-100 w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 transition-all duration-200">
                            <i class="fas fa-ellipsis-v text-gray-600 text-sm"></i>
                        </button>
                    </div>
                    <!-- Folder Name -->
                    <p class="text-sm font-semibold text-gray-900 truncate mb-1">
                        {{ folder.name }}
                    </p>
                    <p class="text-xs text-gray-500">
                        {{ getFolderFileCount(folder.id) }} items
                    </p>
                </div>
            </div>
            
            <!-- Files -->
            <div v-for="file in filteredFiles" 
                 :key="'file-' + file.id"
                 @click="handleFileClick(file)"
                 @contextmenu.prevent="showContextMenu($event, file)"
                 class="item-card group cursor-pointer transform hover:scale-105 transition-all duration-200">
                <div class="p-5">
                    <!-- File Header -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl flex items-center justify-center group-hover:shadow-md transition-all duration-200">
                            <img v-if="isImage(file.original_name)" 
                                 :src="'/uploads/' + file.unique_id" 
                                 :alt="file.original_name"
                                 class="w-full h-full object-cover rounded-xl"
                                 @error="$event.target.style.display='none'; $event.target.nextElementSibling.style.display='flex'">
                            <i v-show="!isImage(file.original_name)" :class="[getFileIcon(file.original_name), getFileColor(file.original_name)]" class="text-3xl"></i>
                        </div>
                        <button @click.stop="showContextMenu($event, file)" 
                                class="opacity-0 group-hover:opacity-100 w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 transition-all duration-200">
                            <i class="fas fa-ellipsis-v text-gray-600 text-sm"></i>
                        </button>
                    </div>
                    <!-- File Info -->
                    <p class="text-sm font-semibold text-gray-900 truncate mb-1" :title="file.original_name">
                        {{ file.original_name }}
                    </p>
                    <div class="flex items-center gap-2 text-xs text-gray-500">
                        <span class="font-medium">{{ formatFileSize(file.size) }}</span>
                    </div>
                </div>
            </div>
            
            <!-- Empty State -->
            <div v-if="filteredFolders.length === 0 && filteredFiles.length === 0"
                 class="col-span-full flex flex-col items-center justify-center py-24 animate-fadeIn">
                <div class="w-32 h-32 bg-gradient-to-br from-blue-50 to-purple-50 rounded-full flex items-center justify-center mb-6">
                    <svg class="w-16 h-16 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                    </svg>
                </div>
                <h3 class="text-2xl font-semibold text-gray-800 mb-2">
                    {{ searchQuery ? 'No files found' : 'Your drive is empty' }}
                </h3>
                <p class="text-base text-gray-500 mb-8 text-center max-w-md">
                    {{ searchQuery ? 'Try adjusting your search terms' : 'Upload your first file to get started' }}
                </p>
                <button v-if="!searchQuery" @click="showUploadModal = true"
                        class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg shadow-lg hover:shadow-xl transition-all duration-200 flex items-center gap-2">
                    <i class="fas fa-upload"></i>
                    <span>Upload Files</span>
                </button>
            </div>
        </div>
        
        <!-- List View -->
        <div v-else-if="viewMode === 'list'" class="bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Modified</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <!-- Folders -->
                    <tr v-for="folder in filteredFolders" 
                        :key="'folder-' + folder.id"
                        @click="openFolder(folder)"
                        @contextmenu.prevent="showContextMenu($event, folder)"
                        class="hover:bg-gray-50 cursor-pointer transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center">
                                    <svg class="w-8 h-8 text-gray-500" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">{{ folder.name }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">—</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ formatDate(folder.created_at) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button @click.stop="renameFolder(folder)" class="text-blue-600 hover:text-blue-900 mr-3">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button @click.stop="deleteFolder(folder)" class="text-red-600 hover:text-red-900">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    
                    <!-- Files -->
                    <tr v-for="file in filteredFiles" 
                        :key="'file-' + file.id"
                        @click="handleFileClick(file)"
                        @contextmenu.prevent="showContextMenu($event, file)"
                        class="hover:bg-gray-50 cursor-pointer">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center">
                                    <img v-if="isImage(file.original_name)" 
                                         :src="'/uploads/' + file.unique_id" 
                                         :alt="file.original_name"
                                         class="w-10 h-10 object-cover rounded"
                                         @error="$event.target.style.display='none'; $event.target.nextElementSibling.style.display='block'">
                                    <i v-show="!isImage(file.original_name)" :class="[getFileIcon(file.original_name), getFileColor(file.original_name)]" class="text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">{{ file.original_name }}</div>
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
                            <button @click.stop="renameFile(file)" class="text-blue-600 hover:text-blue-900 mr-3">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a :href="'/download/' + file.unique_id" 
                               @click.stop
                               class="text-green-600 hover:text-green-900 mr-3">
                                <i class="fas fa-download"></i>
                            </a>
                            <button @click.stop="deleteFile(file)" class="text-red-600 hover:text-red-900">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    
                    <!-- Empty State -->
                    <tr v-if="filteredFolders.length === 0 && filteredFiles.length === 0">
                        <td colspan="4" class="px-6 py-12 text-center">
                            <i class="fas fa-folder-open text-6xl text-gray-300 mb-4"></i>
                            <h3 class="text-xl font-medium text-gray-900 mb-2">No files found</h3>
                            <p class="text-gray-600">{{ searchQuery ? 'Try a different search' : 'Upload files to get started' }}</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Context Menu -->
        <div v-if="showingContextMenu" 
             :style="{ top: contextMenuPosition.y + 'px', left: contextMenuPosition.x + 'px' }"
             class="context-menu"
             @click="hideContextMenu">
            <div class="py-1">
                <button v-if="contextMenuItem.type === 'folder'"
                        @click="openFolder(contextMenuItem)"
                        class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center">
                    <i class="fas fa-folder-open w-5 mr-2"></i>
                    Open
                </button>
                <button v-if="contextMenuItem.type === 'file'"
                        @click="window.open('/download/' + contextMenuItem.unique_id, '_blank')"
                        class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center">
                    <i class="fas fa-download w-5 mr-2"></i>
                    Download
                </button>
                <button @click="contextMenuItem.type === 'folder' ? renameFolder(contextMenuItem) : renameFile(contextMenuItem)"
                        class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center">
                    <i class="fas fa-edit w-5 mr-2"></i>
                    Rename
                </button>
                <hr class="my-1">
                <button @click="contextMenuItem.type === 'folder' ? deleteFolder(contextMenuItem) : deleteFile(contextMenuItem)"
                        class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center">
                    <i class="fas fa-trash w-5 mr-2"></i>
                    Delete
                </button>
            </div>
        </div>
        
        <!-- Upload Files Modal -->
        <transition name="modal">
            <div v-if="showUploadModal" 
                 @click.self="showUploadModal = false"
                 class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-2xl max-w-3xl w-full mx-4 shadow-2xl max-h-[90vh] overflow-y-auto">
                    <!-- Modal Header -->
                    <div class="sticky top-0 bg-gradient-to-r from-blue-600 to-purple-600 text-white px-8 py-6 rounded-t-2xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-2xl font-bold">Upload Files</h3>
                                <p class="text-blue-100 text-sm mt-1">Drag and drop or click to browse</p>
                            </div>
                            <button @click="showUploadModal = false" 
                                    class="w-10 h-10 bg-white/20 hover:bg-white/30 rounded-full flex items-center justify-center transition-all">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Modal Body -->
                    <div class="p-8">
                        <!-- Folder Selection -->
                        <div class="mb-6">
                            <label class="block text-gray-700 font-semibold mb-3">
                                <i class="fas fa-folder mr-2 text-purple-600"></i>
                                Upload to folder (optional)
                            </label>
                            <select v-model="uploadFolderId" 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all">
                                <option value="">📁 Root (No folder)</option>
                                <option v-for="folder in folders" :key="folder.id" :value="folder.id">
                                    📂 {{ folder.name }}
                                </option>
                            </select>
                        </div>
                        
                        <!-- Upload Area -->
                        <div ref="uploadArea"
                             @dragover.prevent="isDragging = true"
                             @dragleave="isDragging = false"
                             @drop.prevent="handleDrop"
                             @click="$refs.fileInput.click()"
                             :class="isDragging ? 'border-purple-500 bg-purple-50' : 'border-gray-300 bg-gray-50'"
                             class="border-4 border-dashed rounded-2xl p-12 text-center cursor-pointer transition-all hover:border-purple-400 hover:bg-purple-50/50">
                            <div class="pointer-events-none">
                                <i class="fas fa-cloud-upload-alt text-7xl text-purple-400 mb-4"></i>
                                <p class="text-xl font-semibold text-gray-700 mb-2">Drag & drop files here</p>
                                <p class="text-gray-500 mb-4">or</p>
                                <div class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl font-medium shadow-lg">
                                    <i class="fas fa-folder-open mr-2"></i>
                                    Browse Files
                                </div>
                                <p class="text-sm text-gray-400 mt-4">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Maximum file size: 2 GB per file
                                </p>
                            </div>
                        </div>
                        <input ref="fileInput" 
                               type="file" 
                               @change="handleFileSelect" 
                               multiple 
                               class="hidden">
                        
                        <!-- Upload Progress List -->
                        <div v-if="uploadQueue.length > 0" class="mt-6 space-y-3">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-semibold text-gray-700">
                                    <i class="fas fa-list mr-2"></i>
                                    Upload Queue ({{ uploadQueue.length }})
                                </h4>
                                <button v-if="uploadQueue.some(u => u.status === 'completed')" 
                                        @click="clearCompleted"
                                        class="text-sm text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-broom mr-1"></i>
                                    Clear Completed
                                </button>
                            </div>
                            
                            <div v-for="upload in uploadQueue" 
                                 :key="upload.id" 
                                 class="bg-gray-50 rounded-xl p-4 border-2 border-gray-200">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-3 flex-1 min-w-0">
                                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-500 rounded-lg flex items-center justify-center text-white flex-shrink-0">
                                            <i :class="getFileIcon(upload.file.name)"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-medium text-gray-900 truncate" :title="upload.file.name">
                                                {{ upload.file.name }}
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                {{ formatFileSize(upload.file.size) }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span v-if="upload.status === 'uploading'" class="text-sm font-medium text-blue-600">
                                            {{ upload.progress }}%
                                        </span>
                                        <span v-if="upload.status === 'completed'" class="text-green-600">
                                            <i class="fas fa-check-circle text-xl"></i>
                                        </span>
                                        <span v-if="upload.status === 'error'" class="text-red-600">
                                            <i class="fas fa-times-circle text-xl"></i>
                                        </span>
                                        <button v-if="upload.status !== 'uploading'" 
                                                @click="removeUpload(upload.id)"
                                                class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200 transition-colors">
                                            <i class="fas fa-times text-gray-500"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Progress Bar -->
                                <div v-if="upload.status === 'uploading'" class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                                    <div class="bg-gradient-to-r from-blue-500 to-purple-500 h-2.5 rounded-full transition-all duration-500 ease-out" 
                                         :style="{ width: upload.progress + '%' }">
                                    </div>
                                </div>
                                
                                <!-- Success Message -->
                                <div v-if="upload.status === 'completed'" class="mt-2 flex items-center justify-between">
                                    <span class="text-sm text-green-600">
                                        <i class="fas fa-check mr-1"></i>
                                        Upload successful!
                                    </span>
                                    <a :href="upload.url" target="_blank" class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                                        View File
                                    </a>
                                </div>
                                
                                <!-- Error Message -->
                                <div v-if="upload.status === 'error'" class="mt-2">
                                    <span class="text-sm text-red-600">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        {{ upload.error || 'Upload failed' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Tips -->
                        <div class="mt-6 p-4 bg-blue-50 border-l-4 border-blue-500 rounded-lg">
                            <h5 class="font-semibold text-blue-900 mb-2">
                                <i class="fas fa-lightbulb mr-2"></i>
                                Quick Tips
                            </h5>
                            <ul class="text-sm text-blue-800 space-y-1">
                                <li>• You can upload multiple files at once</li>
                                <li>• Files are automatically backed up to cloud storage</li>
                                <li>• Use folders to organize your files</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="sticky bottom-0 bg-gray-50 px-8 py-4 rounded-b-2xl flex justify-between items-center border-t">
                        <p class="text-sm text-gray-600">
                            <i class="fas fa-shield-alt mr-1"></i>
                            All uploads are encrypted and secure
                        </p>
                        <button @click="showUploadModal = false" 
                                class="px-6 py-2 text-gray-700 hover:bg-gray-200 rounded-lg transition-all font-medium">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </transition>
        
        <!-- Create Folder Modal -->
        <transition name="fade">
            <div v-if="showCreateFolderModal" 
                 @click.self="showCreateFolderModal = false"
                 class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                    <h3 class="text-lg font-semibold mb-4">Create New Folder</h3>
                    <input v-model="newFolderName" 
                           @keyup.enter="createFolder"
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
        
        <!-- Floating Action Button (Mobile) -->
        <div class="fab-container md:hidden">
            <button @click="showUploadModal = true" 
                    class="fab flex items-center justify-center text-white shadow-2xl">
                <i class="fas fa-plus text-2xl"></i>
            </button>
        </div>
    </div>

    <?php include __DIR__ . '/../partials/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    
    <script>
    const { createApp } = Vue;
    
    // Shared state for communication between apps
    const sharedState = {
        showUploadModal: false,
        currentFolder: null,
        selectedView: 'myfiles'
    };
    
    // Sidebar App
    createApp({
        data() {
            return {
                sidebarOpen: false,
                selectedView: 'myfiles',
                storagePercent: Math.min(100, (<?php echo $storageUsed; ?> / (5 * 1024 * 1024 * 1024)) * 100)
            }
        },
        methods: {
            showUploadModal() {
                sharedState.showUploadModal = true;
            },
            selectMyFiles() {
                this.selectedView = 'myfiles';
                sharedState.selectedView = 'myfiles';
                sharedState.currentFolder = null;
            }
        },
        mounted() {
            // Listen to Alpine.js sidebar state
            document.addEventListener('alpine:init', () => {
                this.$watch('sidebarOpen', (value) => {
                    const body = document.querySelector('body');
                    if (body && body._x_dataStack && body._x_dataStack[0]) {
                        body._x_dataStack[0].sidebarOpen = value;
                    }
                });
            });
        }
    }).mount('#sidebar-app');
    
    // Main App
    createApp({
        data() {
            return {
                folders: <?php echo json_encode($folders); ?>,
                files: <?php echo json_encode($files); ?>,
                currentFolder: null,
                viewMode: 'grid',
                searchQuery: '',
                loading: false,
                showCreateFolderModal: false,
                newFolderName: '',
                showingContextMenu: false,
                contextMenuPosition: { x: 0, y: 0 },
                contextMenuItem: null,
                showUploadModal: false,
                uploadFolderId: '',
                uploadQueue: [],
                isDragging: false,
                uploadIdCounter: 0,
                selectedView: 'myfiles'
            }
        },
        watch: {
            // Watch shared state for changes
            showUploadModal(val) {
                if (sharedState.showUploadModal && !val) {
                    sharedState.showUploadModal = false;
                }
            }
        },
        computed: {
            filteredFolders() {
                if (!this.searchQuery) return this.folders.filter(f => !this.currentFolder || f.parent_id == this.currentFolder.id);
                return this.folders.filter(f => f.name.toLowerCase().includes(this.searchQuery.toLowerCase()));
            },
            filteredFiles() {
                if (!this.searchQuery) return this.files.filter(f => !this.currentFolder || f.folder_id == this.currentFolder.id);
                return this.files.filter(f => f.original_name.toLowerCase().includes(this.searchQuery.toLowerCase()));
            },
            totalDownloads() {
                return this.files.reduce((sum, file) => sum + (parseInt(file.downloads) || 0), 0);
            },
            syncedFilesCount() {
                return this.files.filter(f => f.storage_location === 'dropbox' && f.sync_status === 'synced').length;
            },
            lastUpload() {
                if (this.files.length === 0) return 'N/A';
                const latest = this.files.reduce((a, b) => new Date(a.created_at) > new Date(b.created_at) ? a : b);
                const date = new Date(latest.created_at);
                const now = new Date();
                const diffTime = Math.abs(now - date);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                if (diffDays === 0) return 'Today';
                if (diffDays === 1) return 'Yesterday';
                if (diffDays < 7) return `${diffDays} days ago`;
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            },
            storagePercent() {
                const maxStorage = 5 * 1024 * 1024 * 1024; // 5GB
                return Math.min(100, (<?php echo $storageUsed; ?> / maxStorage) * 100);
            }
        },
        methods: {
            getFileColor(filename) {
                const ext = filename.split('.').pop().toLowerCase();
                if (['pdf'].includes(ext)) return 'file-icon-pdf';
                if (['doc', 'docx'].includes(ext)) return 'file-icon-doc';
                if (['xls', 'xlsx'].includes(ext)) return 'file-icon-xls';
                if (['ppt', 'pptx'].includes(ext)) return 'file-icon-ppt';
                if (['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp'].includes(ext)) return 'file-icon-img';
                if (['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv'].includes(ext)) return 'file-icon-video';
                if (['mp3', 'wav', 'flac', 'aac', 'ogg'].includes(ext)) return 'file-icon-audio';
                if (['zip', 'rar', '7z', 'tar', 'gz'].includes(ext)) return 'file-icon-zip';
                if (['js', 'jsx', 'ts', 'tsx', 'py', 'php', 'java', 'cpp', 'c', 'html', 'css', 'json', 'xml'].includes(ext)) return 'file-icon-code';
                return 'file-icon-default';
            },
            isImage(filename) {
                const ext = filename.split('.').pop().toLowerCase();
                return ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp'].includes(ext);
            },
            openFolder(folder) {
                this.currentFolder = folder;
                this.searchQuery = '';
            },
            handleFileClick(file) {
                window.open('/info/' + file.unique_id, '_blank');
            },
            showContextMenu(event, item) {
                this.contextMenuItem = { ...item, type: item.unique_id ? 'file' : 'folder' };
                this.contextMenuPosition = { x: event.clientX, y: event.clientY };
                this.showingContextMenu = true;
            },
            hideContextMenu() {
                this.showingContextMenu = false;
            },
            createFolder() {
                if (!this.newFolderName.trim()) return;
                
                fetch('/api/folders', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        name: this.newFolderName,
                        parent_id: this.currentFolder?.id || null
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        this.showCreateFolderModal = false;
                        this.newFolderName = '';
                        location.reload();
                    } else {
                        Swal.fire('Error', data.error || 'Failed to create folder', 'error');
                    }
                })
                .catch(err => {
                    console.error('Create folder error:', err);
                    Swal.fire('Error', 'Network error occurred', 'error');
                });
            },
            renameFolder(folder) {
                Swal.fire({
                    title: 'Rename Folder',
                    input: 'text',
                    inputValue: folder.name,
                    showCancelButton: true,
                    confirmButtonText: 'Rename'
                }).then((result) => {
                    if (result.isConfirmed && result.value) {
                        fetch('/api/folders/rename', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({folder_id: folder.id, name: result.value})
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) location.reload();
                            else Swal.fire('Error', data.error || 'Failed to rename', 'error');
                        });
                    }
                });
            },
            renameFile(file) {
                Swal.fire({
                    title: 'Rename File',
                    input: 'text',
                    inputValue: file.original_name,
                    showCancelButton: true,
                    confirmButtonText: 'Rename'
                }).then((result) => {
                    if (result.isConfirmed && result.value) {
                        fetch('/api/rename', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({file_id: file.id, new_name: result.value})
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) location.reload();
                            else Swal.fire('Error', 'Failed to rename', 'error');
                        });
                    }
                });
            },
            deleteFolder(folder) {
                Swal.fire({
                    title: 'Delete Folder?',
                    text: 'This will delete the folder. Make sure it\'s empty first.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'Delete'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch('/api/folders/delete', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({folder_id: folder.id})
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Deleted!', 'Folder has been deleted', 'success');
                                location.reload();
                            } else {
                                Swal.fire('Error', data.error || 'Failed to delete folder', 'error');
                            }
                        })
                        .catch(err => {
                            Swal.fire('Error', 'An error occurred', 'error');
                        });
                    }
                });
            },
            deleteFile(file) {
                Swal.fire({
                    title: 'Delete File?',
                    text: 'This action cannot be undone',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'Delete'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch('/api/delete', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({file_id: file.id})
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) location.reload();
                            else Swal.fire('Error', 'Failed to delete', 'error');
                        });
                    }
                });
            },
            getFolderFileCount(folderId) {
                return this.files.filter(f => f.folder_id == folderId).length;
            },
            async shareFile(file) {
                const shareUrl = window.location.origin + '/info/' + file.unique_id;
                try {
                    await navigator.clipboard.writeText(shareUrl);
                    Swal.fire({
                        icon: 'success',
                        title: 'Link Copied!',
                        text: 'Share link copied to clipboard',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } catch (err) {
                    Swal.fire({
                        title: 'Share File',
                        html: `<input type="text" value="${shareUrl}" class="swal2-input" readonly onClick="this.select()">`,
                        showCancelButton: false,
                        confirmButtonText: 'Close'
                    });
                }
            },
            handleFileSelect(event) {
                const files = Array.from(event.target.files);
                this.uploadFiles(files);
                event.target.value = ''; // Reset input
            },
            handleDrop(event) {
                this.isDragging = false;
                const files = Array.from(event.dataTransfer.files);
                this.uploadFiles(files);
            },
            uploadFiles(files) {
                files.forEach(file => {
                    const uploadItem = {
                        id: ++this.uploadIdCounter,
                        file: file,
                        progress: 0,
                        status: 'uploading',
                        error: null,
                        url: null
                    };
                    this.uploadQueue.push(uploadItem);
                    this.uploadFile(uploadItem);
                });
            },
            uploadFile(uploadItem) {
                const formData = new FormData();
                formData.append('file', uploadItem.file);
                if (this.uploadFolderId) {
                    formData.append('folder_id', this.uploadFolderId);
                }

                const xhr = new XMLHttpRequest();

                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const progress = Math.round((e.loaded / e.total) * 100);
                        uploadItem.progress = progress;
                        // Smooth progress update
                        this.$forceUpdate();
                    }
                });

                xhr.addEventListener('load', () => {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                uploadItem.status = 'completed';
                                uploadItem.url = response.file.url;
                                // Add to files list
                                this.files.push(response.file);
                                // Show success notification
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Upload Complete!',
                                    text: uploadItem.file.name + ' has been uploaded successfully',
                                    timer: 2000,
                                    showConfirmButton: false,
                                    toast: true,
                                    position: 'top-end'
                                });
                            } else {
                                uploadItem.status = 'error';
                                uploadItem.error = response.error || 'Upload failed';
                            }
                        } catch (e) {
                            uploadItem.status = 'error';
                            uploadItem.error = 'Error parsing response: ' + e.message;
                        }
                    } else {
                        uploadItem.status = 'error';
                        uploadItem.error = 'Server error: ' + xhr.status;
                    }
                });

                xhr.addEventListener('error', () => {
                    uploadItem.status = 'error';
                    uploadItem.error = 'Network error occurred';
                });

                xhr.open('POST', '/api/upload');
                xhr.send(formData);
            },
            clearCompleted() {
                this.uploadQueue = this.uploadQueue.filter(u => u.status !== 'completed');
            },
            removeUpload(uploadId) {
                this.uploadQueue = this.uploadQueue.filter(u => u.id !== uploadId);
            },
            getFileIcon(filename) {
                const ext = filename.split('.').pop().toLowerCase();
                const icons = {
                    pdf: 'fas fa-file-pdf',
                    doc: 'fas fa-file-word', docx: 'fas fa-file-word',
                    xls: 'fas fa-file-excel', xlsx: 'fas fa-file-excel',
                    ppt: 'fas fa-file-powerpoint', pptx: 'fas fa-file-powerpoint',
                    zip: 'fas fa-file-archive', rar: 'fas fa-file-archive',
                    jpg: 'fas fa-file-image', jpeg: 'fas fa-file-image', png: 'fas fa-file-image', gif: 'fas fa-file-image',
                    mp4: 'fas fa-file-video', avi: 'fas fa-file-video', mov: 'fas fa-file-video',
                    mp3: 'fas fa-file-audio', wav: 'fas fa-file-audio'
                };
                return icons[ext] || 'fas fa-file';
            },
            formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
            },
            formatDate(date) {
                return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            }
        },
        mounted() {
            // Hide context menu on click outside
            document.addEventListener('click', () => {
                this.showingContextMenu = false;
            });
            
            // Watch for shared state changes from sidebar
            setInterval(() => {
                if (sharedState.showUploadModal && !this.showUploadModal) {
                    this.showUploadModal = true;
                    sharedState.showUploadModal = false;
                }
                if (sharedState.currentFolder !== undefined && this.currentFolder !== sharedState.currentFolder) {
                    this.currentFolder = sharedState.currentFolder;
                }
                if (sharedState.selectedView !== this.selectedView) {
                    this.selectedView = sharedState.selectedView;
                }
            }, 100);
        }
    }).mount('#app');
    </script>
</body>
</html>
