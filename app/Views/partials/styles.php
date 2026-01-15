<style>
    /* Google Drive Layout */
    body {
        margin: 0;
        font-family: 'Inter', 'Roboto', 'Arial', sans-serif;
        background: #f8f9fa;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
    
    /* Sidebar */
    .sidebar {
        position: fixed;
        left: 0;
        top: 64px;
        width: 280px;
        height: calc(100vh - 64px);
        background: white;
        border-right: 1px solid #e0e0e0;
        overflow-y: auto;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 40;
    }
    
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }
        .sidebar.show {
            transform: translateX(0);
            box-shadow: 4px 0 12px rgba(0,0,0,0.1);
        }
    }
    
    /* Main Content */
    .main-content {
        margin-left: 280px;
        padding-top: 88px;
        min-height: 100vh;
    }
    
    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
        }
    }
    
    /* Cards */
    .item-card {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .item-card:hover {
        box-shadow: 0 4px 12px rgba(60,64,67,.15), 0 1px 3px rgba(60,64,67,.1);
        border-color: #1a73e8;
        transform: translateY(-2px);
    }
    
    /* List View */
    .list-item {
        transition: background 0.15s;
    }
    .list-item:hover {
        background: #f1f3f4;
    }
    .list-item.selected {
        background: #e8f0fe;
    }
    
    /* File Icons - Google Drive Colors */
    .folder-icon { color: #5f6368; }
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
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
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
    
    /* Button Styles */
    .btn-primary {
        background: #1a73e8;
        color: white;
    }
    .btn-primary:hover {
        background: #1765cc;
        box-shadow: 0 1px 2px rgba(60,64,67,.3), 0 1px 3px 1px rgba(60,64,67,.15);
    }
    
    /* Profile Card Animation */
    .profile-card {
        animation: slideIn 0.3s ease-out;
    }
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Vue Cloak */
    [v-cloak] {
        display: none;
    }
</style>
