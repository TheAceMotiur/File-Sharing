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
    <style>
        [v-cloak] { display: none; }
        .item-card { transition: all 0.2s; }
        .item-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .folder-icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .context-menu { position: fixed; z-index: 9999; background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); min-width: 200px; }
        .fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
        .fade-enter-from, .fade-leave-to { opacity: 0; }
    </style>
</head>
<body class="bg-gray-50">
    <?php include __DIR__ . '/../partials/header.php'; ?>

    <div id="app" v-cloak class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        
        <!-- Header Section -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">My Files</h1>
                    <p class="text-gray-600 mt-1">
                        <i class="fas fa-folder mr-2"></i>{{ folders.length }} folders • 
                        <i class="fas fa-file ml-2 mr-2"></i>{{ files.length }} files • 
                        <i class="fas fa-hdd ml-2 mr-2"></i><?php echo formatFileSize($storageUsed); ?>
                    </p>
                </div>
                <div class="flex gap-2">
                    <button @click="showCreateFolderModal = true" 
                            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-folder-plus mr-2"></i>
                        New Folder
                    </button>
                    <a href="/upload" 
                       class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm">
                        <i class="fas fa-cloud-upload-alt mr-2"></i>
                        Upload Files
                    </a>
                </div>
            </div>
            
            <!-- Breadcrumb Navigation -->
            <nav v-if="currentFolder" class="flex items-center text-sm bg-white px-4 py-3 rounded-lg shadow-sm">
                <button @click="currentFolder = null" 
                        class="text-blue-600 hover:text-blue-800 flex items-center">
                    <i class="fas fa-home mr-2"></i>
                    My Files
                </button>
                <i class="fas fa-chevron-right mx-3 text-gray-400"></i>
                <span class="text-gray-900 font-medium">{{ currentFolder.name }}</span>
            </nav>
        </div>
        
        <!-- Toolbar -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <!-- Search -->
                <div class="flex-1 max-w-md">
                    <div class="relative">
                        <input v-model="searchQuery" 
                               type="text" 
                               placeholder="Search files and folders..."
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <button v-if="searchQuery" 
                                @click="searchQuery = ''"
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <!-- View Toggle -->
                <div class="flex border border-gray-300 rounded-lg overflow-hidden">
                    <button @click="viewMode = 'grid'" 
                            :class="viewMode === 'grid' ? 'bg-blue-50 text-blue-600' : 'bg-white text-gray-600'"
                            class="px-3 py-2 hover:bg-gray-50">
                        <i class="fas fa-th"></i>
                    </button>
                    <button @click="viewMode = 'list'" 
                            :class="viewMode === 'list' ? 'bg-blue-50 text-blue-600' : 'bg-white text-gray-600'"
                            class="px-3 py-2 hover:bg-gray-50 border-l border-gray-300">
                        <i class="fas fa-list"></i>
                    </button>
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
            <div v-for="folder in filteredFolders" 
                 :key="'folder-' + folder.id"
                 @click="openFolder(folder)"
                 @contextmenu.prevent="showContextMenu($event, folder)"
                 class="item-card bg-white rounded-lg p-4 cursor-pointer border-2 border-gray-200 hover:border-blue-400">
                <div class="flex flex-col items-center">
                    <div class="w-16 h-16 flex items-center justify-center rounded-xl folder-icon text-white mb-3">
                        <i class="fas fa-folder text-3xl"></i>
                    </div>
                    <p class="text-sm font-medium text-gray-900 text-center truncate w-full" :title="folder.name">
                        {{ folder.name }}
                    </p>
                </div>
            </div>
            
            <!-- Files -->
            <div v-for="file in filteredFiles" 
                 :key="'file-' + file.id"
                 @click="handleFileClick(file)"
                 @contextmenu.prevent="showContextMenu($event, file)"
                 class="item-card bg-white rounded-lg p-4 cursor-pointer border-2 border-gray-200 hover:border-blue-400 relative">
                <div class="flex flex-col items-center">
                    <div class="w-16 h-16 flex items-center justify-center rounded-xl bg-gradient-to-br from-gray-400 to-gray-500 text-white mb-3">
                        <i :class="getFileIcon(file.original_name)" class="text-3xl"></i>
                    </div>
                    <p class="text-sm font-medium text-gray-900 text-center truncate w-full" :title="file.original_name">
                        {{ file.original_name }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ formatFileSize(file.size) }}
                    </p>
                    <div class="absolute top-2 right-2">
                        <span v-if="file.storage_location === 'dropbox' && file.sync_status === 'synced'" 
                              title="Synced to Dropbox"
                              class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-100 text-green-700">
                            <i class="fab fa-dropbox"></i>
                        </span>
                        <span v-else-if="file.sync_status === 'pending'" 
                              title="Pending sync"
                              class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-yellow-100 text-yellow-700">
                            <i class="fas fa-clock"></i>
                        </span>
                        <span v-else-if="file.sync_status === 'syncing'" 
                              title="Syncing..."
                              class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-700">
                            <i class="fas fa-sync fa-spin"></i>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Empty State -->
            <div v-if="filteredFolders.length === 0 && filteredFiles.length === 0" 
                 class="col-span-full text-center py-12">
                <i class="fas fa-folder-open text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-medium text-gray-900 mb-2">No files found</h3>
                <p class="text-gray-600 mb-6">{{ searchQuery ? 'Try a different search' : 'Upload files to get started' }}</p>
                <a href="/upload" 
                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-cloud-upload-alt mr-2"></i>
                    Upload Files
                </a>
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
                        class="hover:bg-gray-50 cursor-pointer">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-lg folder-icon">
                                    <i class="fas fa-folder text-white"></i>
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
                                <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-lg bg-gradient-to-br from-gray-400 to-gray-500">
                                    <i :class="getFileIcon(file.original_name)" class="text-white"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">{{ file.original_name }}</div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <span v-if="file.storage_location === 'dropbox' && file.sync_status === 'synced'" 
                                              class="inline-flex items-center text-green-600">
                                            <i class="fab fa-dropbox mr-1"></i> Synced
                                        </span>
                                        <span v-else-if="file.sync_status === 'pending'" 
                                              class="inline-flex items-center text-yellow-600">
                                            <i class="fas fa-clock mr-1"></i> Pending
                                        </span>
                                        <span v-else-if="file.sync_status === 'syncing'" 
                                              class="inline-flex items-center text-blue-600">
                                            <i class="fas fa-sync fa-spin mr-1"></i> Syncing
                                        </span>
                                        <span v-else 
                                              class="inline-flex items-center text-gray-500">
                                            <i class="fas fa-hdd mr-1"></i> Local
                                        </span>
                                    </div>
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
    </div>

    <?php include __DIR__ . '/../partials/footer.php'; ?>

    <script>
    const { createApp } = Vue;
    
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
                contextMenuItem: null
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
            }
        },
        methods: {
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
        }
    }).mount('#app');
    </script>
</body>
</html>
