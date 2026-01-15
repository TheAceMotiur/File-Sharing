<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - <?php echo getSiteName(); ?></title>
    <link rel="icon" type="image/png" href="/icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/vue@3.3.4/dist/vue.global.prod.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        [v-cloak] { display: none; }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-slide-in { animation: slideIn 0.5s ease-out; }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        .file-icon-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
        }
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .copy-success {
            animation: pulse 0.5s ease-in-out;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <?php include __DIR__ . '/../partials/header.php'; ?>

    <div id="app" v-cloak class="container mx-auto px-4 py-12 max-w-4xl animate-slide-in">
        <!-- Main Card -->
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <!-- Header Section with Gradient -->
            <div class="bg-gradient-to-r from-blue-500 via-purple-500 to-pink-500 px-8 py-12 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-4xl font-bold mb-2">File Ready to Download</h1>
                        <p class="text-blue-100"><i class="fas fa-shield-alt mr-2"></i>Secure & Fast Download</p>
                    </div>
                    <div class="hidden md:block">
                        <div class="w-24 h-24 bg-white/20 backdrop-blur-lg rounded-full flex items-center justify-center">
                            <i class="fas fa-cloud-download-alt text-5xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- File Info Section -->
            <div class="p-8">
                <!-- File Icon & Name -->
                <div class="flex items-center justify-center mb-8">
                    <div class="file-icon-container w-32 h-32 rounded-2xl flex items-center justify-center transform hover:scale-110 transition-transform duration-300">
                        <i :class="getFileIcon('<?php echo htmlspecialchars($file['original_name']); ?>')" class="text-7xl text-white"></i>
                    </div>
                </div>

                <div class="text-center mb-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-2 break-all">
                        <?php echo htmlspecialchars($file['original_name']); ?>
                    </h2>
                    <p class="text-gray-500">{{ fileExtension }} file</p>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <div class="stat-card bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4 text-center">
                        <i class="fas fa-hdd text-3xl text-blue-600 mb-2"></i>
                        <p class="text-sm text-gray-600 mb-1">File Size</p>
                        <p class="text-lg font-bold text-gray-800"><?php echo $fileSize; ?></p>
                    </div>
                    <div class="stat-card bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4 text-center">
                        <i class="fas fa-download text-3xl text-green-600 mb-2"></i>
                        <p class="text-sm text-gray-600 mb-1">Downloads</p>
                        <p class="text-lg font-bold text-gray-800">{{ downloads }}</p>
                    </div>
                    <div class="stat-card bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-4 text-center">
                        <i class="fas fa-calendar text-3xl text-purple-600 mb-2"></i>
                        <p class="text-sm text-gray-600 mb-1">Uploaded</p>
                        <p class="text-lg font-bold text-gray-800"><?php echo $uploadDate; ?></p>
                    </div>
                    <div class="stat-card bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-4 text-center">
                        <i class="fas fa-shield-alt text-3xl text-orange-600 mb-2"></i>
                        <p class="text-sm text-gray-600 mb-1">Status</p>
                        <p class="text-lg font-bold text-gray-800">Secure</p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-3">
                    <a href="<?php echo $downloadUrl; ?>" 
                       class="btn-primary w-full py-4 rounded-xl text-white font-bold text-lg flex items-center justify-center gap-3 shadow-lg">
                        <i class="fas fa-download text-xl"></i>
                        Download Now
                    </a>
                    
                    <button @click="copyLink" 
                            :class="{'copy-success': copied}"
                            class="w-full py-4 rounded-xl font-bold text-lg flex items-center justify-center gap-3 transition-all duration-300"
                            :style="copied ? 'background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;' : 'background: #f3f4f6; color: #374151;'">
                        <i :class="copied ? 'fas fa-check' : 'fas fa-copy'" class="text-xl"></i>
                        {{ copied ? 'Link Copied!' : 'Copy Download Link' }}
                    </button>

                    <div class="flex gap-3">
                        <a :href="'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(currentUrl)" 
                           target="_blank"
                           class="flex-1 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium flex items-center justify-center gap-2 transition-all">
                            <i class="fab fa-facebook"></i>
                            Share
                        </a>
                        <a :href="'https://twitter.com/intent/tweet?url=' + encodeURIComponent(currentUrl)" 
                           target="_blank"
                           class="flex-1 py-3 bg-sky-500 hover:bg-sky-600 text-white rounded-xl font-medium flex items-center justify-center gap-2 transition-all">
                            <i class="fab fa-twitter"></i>
                            Tweet
                        </a>
                        <a :href="'https://wa.me/?text=' + encodeURIComponent(currentUrl)" 
                           target="_blank"
                           class="flex-1 py-3 bg-green-600 hover:bg-green-700 text-white rounded-xl font-medium flex items-center justify-center gap-2 transition-all">
                            <i class="fab fa-whatsapp"></i>
                            WhatsApp
                        </a>
                    </div>
                </div>

                <!-- Report Link -->
                <div class="mt-6 text-center">
                    <a href="/report/<?php echo $file['unique_id']; ?>" 
                       class="text-red-500 hover:text-red-700 text-sm font-medium inline-flex items-center gap-2 transition-colors">
                        <i class="fas fa-flag"></i>
                        Report this file
                    </a>
                </div>

                <!-- Security Notice -->
                <div class="mt-8 p-4 bg-blue-50 border-l-4 border-blue-500 rounded-lg">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-info-circle text-blue-600 text-xl mt-1"></i>
                        <div>
                            <p class="font-semibold text-blue-900 mb-1">Security Notice</p>
                            <p class="text-sm text-blue-800">Always scan downloaded files with antivirus software before opening. Never run executable files from untrusted sources.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    const { createApp } = Vue;

    createApp({
        data() {
            return {
                copied: false,
                downloads: <?php echo $file['downloads'] ?? 0; ?>,
                currentUrl: window.location.href,
                fileName: '<?php echo addslashes(htmlspecialchars($file['original_name'])); ?>'
            }
        },
        computed: {
            fileExtension() {
                const ext = this.fileName.split('.').pop().toUpperCase();
                return ext;
            }
        },
        methods: {
            async copyLink() {
                try {
                    await navigator.clipboard.writeText(this.currentUrl);
                    this.copied = true;
                    setTimeout(() => {
                        this.copied = false;
                    }, 3000);
                } catch (err) {
                    alert('Failed to copy link');
                }
            },
            getFileIcon(filename) {
                const ext = filename.split('.').pop().toLowerCase();
                const iconMap = {
                    // Documents
                    pdf: 'fas fa-file-pdf',
                    doc: 'fas fa-file-word',
                    docx: 'fas fa-file-word',
                    txt: 'fas fa-file-alt',
                    // Images
                    jpg: 'fas fa-file-image',
                    jpeg: 'fas fa-file-image',
                    png: 'fas fa-file-image',
                    gif: 'fas fa-file-image',
                    svg: 'fas fa-file-image',
                    // Videos
                    mp4: 'fas fa-file-video',
                    avi: 'fas fa-file-video',
                    mkv: 'fas fa-file-video',
                    mov: 'fas fa-file-video',
                    // Audio
                    mp3: 'fas fa-file-audio',
                    wav: 'fas fa-file-audio',
                    flac: 'fas fa-file-audio',
                    // Archives
                    zip: 'fas fa-file-archive',
                    rar: 'fas fa-file-archive',
                    '7z': 'fas fa-file-archive',
                    tar: 'fas fa-file-archive',
                    // Code
                    js: 'fas fa-file-code',
                    html: 'fas fa-file-code',
                    css: 'fas fa-file-code',
                    php: 'fas fa-file-code',
                    py: 'fas fa-file-code',
                    // Spreadsheets
                    xls: 'fas fa-file-excel',
                    xlsx: 'fas fa-file-excel',
                    csv: 'fas fa-file-excel',
                    // Presentations
                    ppt: 'fas fa-file-powerpoint',
                    pptx: 'fas fa-file-powerpoint'
                };
                return iconMap[ext] || 'fas fa-file';
            }
        }
    }).mount('#app');
    </script>

    <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
