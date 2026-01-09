<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\FileUpload;
use App\Models\Folder;

/**
 * Dashboard Controller
 * Handles user dashboard and file management
 */
class DashboardController extends Controller
{
    private $fileModel;
    private $folderModel;
    
    public function __construct()
    {
        $this->fileModel = new FileUpload();
        $this->folderModel = new Folder();
    }
    
    /**
     * Show dashboard
     */
    public function index()
    {
        $userId = $_SESSION['user_id'];
        $folderId = $this->get('folder');
        
        // Get files
        if ($folderId) {
            $files = $this->fileModel->getByFolder($folderId);
            $currentFolder = $this->folderModel->find($folderId);
        } else {
            $db = getDBConnection();
            $stmt = $db->prepare("SELECT * FROM file_uploads WHERE uploaded_by = ? AND deleted_at IS NULL ORDER BY created_at DESC");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $currentFolder = null;
        }
        
        // Get folders
        $folders = $this->folderModel->getByUser($userId);
        
        // Get user info
        $userModel = $this->model('User');
        $user = $userModel->find($userId);
        $storageUsed = $userModel->getStorageUsage($userId);
        
        $data = [
            'title' => 'Dashboard',
            'files' => $files,
            'folders' => $folders,
            'currentFolder' => $currentFolder,
            'user' => $user,
            'storageUsed' => $storageUsed,
            'storageLimit' => $user['premium'] ? 10737418240 : 1073741824 // 10GB : 1GB
        ];
        
        $this->view('user/dashboard', $data);
    }
    
    /**
     * Show upload page
     */
    public function upload()
    {
        $data = [
            'title' => 'Upload Files',
            'folders' => $this->folderModel->getByUser($_SESSION['user_id'])
        ];
        
        $this->view('user/upload', $data);
    }
    
    /**
     * Show profile page
     */
    public function profile()
    {
        $userModel = $this->model('User');
        $user = $userModel->find($_SESSION['user_id']);
        
        $data = [
            'title' => 'Profile',
            'user' => $user,
            'success' => $this->get('success'),
            'error' => $this->get('error')
        ];
        
        if ($this->isPost()) {
            $name = trim($this->post('name'));
            $email = trim($this->post('email'));
            
            if (empty($name) || empty($email)) {
                $data['error'] = "Name and email are required";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $data['error'] = "Invalid email address";
            } else {
                // Check if email is already taken by another user
                $existingUser = $userModel->findByEmail($email);
                if ($existingUser && $existingUser['id'] != $_SESSION['user_id']) {
                    $data['error'] = "Email already taken";
                } else {
                    $updated = $userModel->update($_SESSION['user_id'], [
                        'name' => $name,
                        'email' => $email
                    ]);
                    
                    if ($updated) {
                        $_SESSION['user_name'] = $name;
                        $data['success'] = "Profile updated successfully";
                        $data['user'] = $userModel->find($_SESSION['user_id']);
                    } else {
                        $data['error'] = "Failed to update profile";
                    }
                }
            }
        }
        
        $this->view('user/profile', $data);
    }
}
