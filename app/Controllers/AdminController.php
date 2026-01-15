<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;
use App\Models\FileUpload;
use App\Models\Report;

/**
 * Admin Controller
 * Handles admin panel operations
 */
class AdminController extends Controller
{
    /**
     * Admin dashboard
     */
    public function dashboard()
    {
        $db = getDBConnection();
        
        // Get stats
        $totalUsers = $db->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
        $verifiedUsers = $db->query("SELECT COUNT(*) as count FROM users WHERE email_verified = 1")->fetch_assoc()['count'];
        $totalFiles = $db->query("SELECT COUNT(*) as count FROM file_uploads WHERE deleted_at IS NULL")->fetch_assoc()['count'];
        $totalStorage = $db->query("SELECT SUM(size) as total FROM file_uploads WHERE deleted_at IS NULL")->fetch_assoc()['total'] ?? 0;
        
        // Recent users
        $recentUsers = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
        
        // Recent files
        $recentFiles = $db->query("SELECT fu.*, u.name as user_name FROM file_uploads fu 
                                  LEFT JOIN users u ON fu.uploaded_by = u.id 
                                  WHERE fu.deleted_at IS NULL 
                                  ORDER BY fu.created_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
        
        $data = [
            'title' => 'Admin Dashboard',
            'totalUsers' => $totalUsers,
            'verifiedUsers' => $verifiedUsers,
            'totalFiles' => $totalFiles,
            'totalStorage' => formatFileSize($totalStorage),
            'recentUsers' => $recentUsers,
            'recentFiles' => $recentFiles
        ];
        
        $this->view('admin/dashboard', $data);
    }
    
    /**
     * Manage users
     */
    public function users()
    {
        $db = getDBConnection();
        $search = $this->get('search', '');
        
        if ($search) {
            $stmt = $db->prepare("SELECT * FROM users WHERE name LIKE ? OR email LIKE ? ORDER BY created_at DESC");
            $searchTerm = "%{$search}%";
            $stmt->bind_param("ss", $searchTerm, $searchTerm);
            $stmt->execute();
            $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            $users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
        }
        
        $data = [
            'title' => 'Manage Users',
            'users' => $users,
            'search' => $search
        ];
        
        $this->view('admin/users', $data);
    }
    
    /**
     * Manage files
     */
    public function files()
    {
        $db = getDBConnection();
        $search = $this->get('search', '');
        
        if ($search) {
            $stmt = $db->prepare("SELECT fu.*, u.name as user_name FROM file_uploads fu 
                                 LEFT JOIN users u ON fu.uploaded_by = u.id 
                                 WHERE fu.original_name LIKE ? AND fu.deleted_at IS NULL 
                                 ORDER BY fu.created_at DESC");
            $searchTerm = "%{$search}%";
            $stmt->bind_param("s", $searchTerm);
            $stmt->execute();
            $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            $files = $db->query("SELECT fu.*, u.name as user_name FROM file_uploads fu 
                                LEFT JOIN users u ON fu.uploaded_by = u.id 
                                WHERE fu.deleted_at IS NULL 
                                ORDER BY fu.created_at DESC 
                                LIMIT 100")->fetch_all(MYSQLI_ASSOC);
        }
        
        $data = [
            'title' => 'Manage Files',
            'files' => $files,
            'search' => $search
        ];
        
        $this->view('admin/files', $data);
    }
    
    /**
     * View reports
     */
    public function reports()
    {
        $reportModel = new Report();
        $reports = $reportModel->getAllWithPagination(50, 0);
        
        $data = [
            'title' => 'File Reports',
            'reports' => $reports
        ];
        
        $this->view('admin/reports', $data);
    }
    
    /**
     * Resolve a report
     */
    public function resolveReport($reportId)
    {
        $reportModel = new Report();
        $success = $reportModel->updateStatus((int)$reportId, 'resolved');
        
        if ($success) {
            return $this->json(['success' => true]);
        }
        return $this->json(['error' => 'Failed to resolve report'], 500);
    }
    
    /**
     * Reject a report
     */
    public function rejectReport($reportId)
    {
        $reportModel = new Report();
        $success = $reportModel->updateStatus((int)$reportId, 'rejected');
        
        if ($success) {
            return $this->json(['success' => true]);
        }
        return $this->json(['error' => 'Failed to reject report'], 500);
    }
    
    /**
     * Delete file from everywhere (database, local, dropbox)
     */
    public function deleteFileEverywhere($uniqueId)
    {
        try {
            $fileModel = $this->model('FileUpload');
            $file = $fileModel->findByUniqueId($uniqueId);
            
            if (!$file) {
                return $this->json(['error' => 'File not found'], 404);
            }
            
            $deleted = [];
            $errors = [];
            
            // Delete from Dropbox
            if ($file['dropbox_account_id'] && $file['dropbox_path']) {
                try {
                    $db = getDBConnection();
                    $stmt = $db->prepare("SELECT access_token FROM dropbox_accounts WHERE id = ?");
                    $stmt->bind_param("i", $file['dropbox_account_id']);
                    $stmt->execute();
                    $dropboxAccount = $stmt->get_result()->fetch_assoc();
                    
                    if ($dropboxAccount) {
                        $client = new \Spatie\Dropbox\Client($dropboxAccount['access_token']);
                        $client->delete($file['dropbox_path']);
                        $deleted[] = 'Dropbox';
                    }
                } catch (\Exception $e) {
                    $errors[] = 'Dropbox: ' . $e->getMessage();
                }
            }
            
            // Delete local files
            $localPath = __DIR__ . '/../../uploads/' . $file['unique_id'];
            if (is_dir($localPath)) {
                try {
                    $files = glob($localPath . '/*');
                    foreach ($files as $f) {
                        if (is_file($f)) unlink($f);
                    }
                    rmdir($localPath);
                    $deleted[] = 'Local Storage';
                } catch (\Exception $e) {
                    $errors[] = 'Local: ' . $e->getMessage();
                }
            }
            
            // Soft delete from database
            $db = getDBConnection();
            $stmt = $db->prepare("UPDATE file_uploads SET deleted_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $file['id']);
            if ($stmt->execute()) {
                $deleted[] = 'Database';
            } else {
                $errors[] = 'Database: Failed to mark as deleted';
            }
            
            // Update report if report_id provided
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['report_id'])) {
                $reportModel = new Report();
                $reportModel->updateStatus((int)$input['report_id'], 'resolved');
            }
            
            return $this->json([
                'success' => true,
                'deleted' => $deleted,
                'errors' => $errors
            ]);
            
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Site settings
     */
    public function settings()
    {
        $db = getDBConnection();
        
        if ($this->isPost()) {
            $siteName = $this->post('site_name');
            $siteUrl = $this->post('site_url');
            
            // Update settings
            $stmt = $db->prepare("UPDATE site_settings SET value = ? WHERE setting_key = 'site_name'");
            $stmt->bind_param("s", $siteName);
            $stmt->execute();
            
            $stmt = $db->prepare("UPDATE site_settings SET value = ? WHERE setting_key = 'site_url'");
            $stmt->bind_param("s", $siteUrl);
            $stmt->execute();
            
            $success = "Settings updated successfully";
        }
        
        // Get current settings
        $settings = $db->query("SELECT * FROM site_settings")->fetch_all(MYSQLI_ASSOC);
        $settingsArray = [];
        foreach ($settings as $setting) {
            $settingsArray[$setting['setting_key']] = $setting['value'];
        }
        
        $data = [
            'title' => 'Site Settings',
            'settings' => $settingsArray,
            'success' => $success ?? null
        ];
        
        $this->view('admin/settings', $data);
    }
    
    /**
     * Email settings
     */
    public function emailSettings()
    {
        $db = getDBConnection();
        
        if ($this->isPost()) {
            $smtpHost = $this->post('smtp_host');
            $smtpPort = $this->post('smtp_port');
            $smtpUser = $this->post('smtp_username');
            $smtpPass = $this->post('smtp_password');
            $smtpFrom = $this->post('smtp_from_email');
            $smtpName = $this->post('smtp_from_name');
            
            // Update email settings
            $settings = [
                'smtp_host' => $smtpHost,
                'smtp_port' => $smtpPort,
                'smtp_username' => $smtpUser,
                'smtp_password' => $smtpPass,
                'smtp_from_email' => $smtpFrom,
                'smtp_from_name' => $smtpName
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $db->prepare("UPDATE email_settings SET value = ? WHERE setting_key = ?");
                $stmt->bind_param("ss", $value, $key);
                $stmt->execute();
            }
            
            $success = "Email settings updated successfully";
        }
        
        // Get current settings
        $settings = $db->query("SELECT * FROM email_settings")->fetch_all(MYSQLI_ASSOC);
        $settingsArray = [];
        foreach ($settings as $setting) {
            $settingsArray[$setting['setting_key']] = $setting['value'];
        }
        
        $data = [
            'title' => 'Email Settings',
            'settings' => $settingsArray,
            'success' => $success ?? null
        ];
        
        $this->view('admin/email-settings', $data);
    }
    
    /**
     * Dropbox settings - Multiple accounts support
     */
    public function dropbox()
    {
        $db = getDBConnection();
        $action = $this->get('action');
        $id = $this->get('id');
        
        // Handle delete
        if ($action === 'delete' && $id) {
            $stmt = $db->prepare("DELETE FROM dropbox_accounts WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            return $this->redirect('/admin/dropbox?success=deleted');
        }
        
        // Handle add/update
        if ($this->isPost()) {
            $appKey = $this->post('app_key');
            $appSecret = $this->post('app_secret');
            $accessToken = $this->post('access_token', '');
            $refreshToken = $this->post('refresh_token', '');
            
            if (empty($appKey) || empty($appSecret)) {
                $error = "App Key and App Secret are required";
            } else {
                // Insert new account
                $stmt = $db->prepare("INSERT INTO dropbox_accounts (app_key, app_secret, access_token, refresh_token) 
                                     VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $appKey, $appSecret, $accessToken, $refreshToken);
                $stmt->execute();
                
                return $this->redirect('/admin/dropbox?success=added');
            }
        }
        
        // Get all Dropbox accounts
        $accounts = $db->query("SELECT * FROM dropbox_accounts ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
        
        $success = null;
        if ($this->get('success') === 'added') {
            $success = "Dropbox account added successfully";
        } elseif ($this->get('success') === 'deleted') {
            $success = "Dropbox account deleted successfully";
        }
        
        $data = [
            'title' => 'Dropbox Settings',
            'accounts' => $accounts,
            'success' => $success,
            'error' => $error ?? null
        ];
        
        $this->view('admin/dropbox', $data);
    }
    
    /**
     * Delete file (admin)
     */
    public function deleteFile()
    {
        if (!$this->isPost()) {
            return $this->json(['error' => 'Method not allowed'], 405);
        }
        
        $data = $this->getJsonInput();
        $fileId = $data['file_id'] ?? null;
        
        if (!$fileId) {
            return $this->json(['error' => 'File ID required'], 400);
        }
        
        $fileModel = new FileUpload();
        
        // Permanent delete - removes from filesystem, Dropbox, and database
        if ($fileModel->permanentDelete($fileId)) {
            return $this->json(['success' => true]);
        }
        
        return $this->json(['error' => 'Failed to delete'], 500);
    }
    
    /**
     * Cron Jobs Management
     */
    public function cronJobs()
    {
        $db = getDBConnection();
        
        // Handle actions
        if ($this->isPost()) {
            $action = $_POST['action'] ?? '';
            
            switch ($action) {
                case 'add':
                    $name = $_POST['name'] ?? '';
                    $description = $_POST['description'] ?? '';
                    $command = $_POST['command'] ?? '';
                    $schedule = $_POST['schedule'] ?? '* * * * *';
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    $stmt = $db->prepare("INSERT INTO cron_jobs (name, description, command, schedule, is_active) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssi", $name, $description, $command, $schedule, $is_active);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = 'Cron job added successfully';
                    } else {
                        $_SESSION['error'] = 'Failed to add cron job';
                    }
                    return $this->redirect('/admin/cron-jobs');
                    
                case 'edit':
                    $id = (int)$_POST['id'];
                    $name = $_POST['name'] ?? '';
                    $description = $_POST['description'] ?? '';
                    $command = $_POST['command'] ?? '';
                    $schedule = $_POST['schedule'] ?? '* * * * *';
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    $stmt = $db->prepare("UPDATE cron_jobs SET name = ?, description = ?, command = ?, schedule = ?, is_active = ? WHERE id = ?");
                    $stmt->bind_param("ssssii", $name, $description, $command, $schedule, $is_active, $id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = 'Cron job updated successfully';
                    } else {
                        $_SESSION['error'] = 'Failed to update cron job';
                    }
                    return $this->redirect('/admin/cron-jobs');
                    
                case 'delete':
                    $id = (int)$_POST['id'];
                    $stmt = $db->prepare("DELETE FROM cron_jobs WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = 'Cron job deleted successfully';
                    } else {
                        $_SESSION['error'] = 'Failed to delete cron job';
                    }
                    return $this->redirect('/admin/cron-jobs');
                    
                case 'toggle':
                    $id = (int)$_POST['id'];
                    $stmt = $db->prepare("UPDATE cron_jobs SET is_active = NOT is_active WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = 'Cron job status updated';
                    } else {
                        $_SESSION['error'] = 'Failed to update status';
                    }
                    return $this->redirect('/admin/cron-jobs');
                    
                case 'run':
                    $id = (int)$_POST['id'];
                    
                    // Get cron job details
                    $stmt = $db->prepare("SELECT command FROM cron_jobs WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $job = $result->fetch_assoc();
                    
                    if ($job) {
                        // Update status to running
                        $stmt = $db->prepare("UPDATE cron_jobs SET last_run_status = 'running', last_run_at = NOW() WHERE id = ?");
                        $stmt->bind_param("i", $id);
                        $stmt->execute();
                        
                        // Check if exec is disabled
                        $disabled = explode(',', ini_get('disable_functions'));
                        if (in_array('exec', $disabled)) {
                            $status = 'failed';
                            $output_text = "Error: exec() function is disabled in PHP configuration.\nPlease enable it in php.ini or use command line to run cron jobs.";
                            
                            $stmt = $db->prepare("UPDATE cron_jobs SET last_run_status = ?, last_run_output = ?, run_count = run_count + 1, last_run_at = NOW() WHERE id = ?");
                            $stmt->bind_param("ssi", $status, $output_text, $id);
                            $stmt->execute();
                            
                            $_SESSION['error'] = 'exec() is disabled. Use command line or enable exec() in php.ini';
                            return $this->redirect('/admin/cron-jobs');
                        }
                        
                        try {
                            // Prepare command with proper paths
                            $basePath = BASE_PATH;
                            
                            // Find correct PHP binary (not Apache's)
                            $phpPath = PHP_BINARY;
                            
                            // On Windows with XAMPP, PHP_BINARY might point to Apache
                            // Fix it to point to the actual PHP CLI binary
                            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                                if (strpos($phpPath, 'apache') !== false || strpos($phpPath, 'httpd') !== false) {
                                    // Try common XAMPP PHP CLI locations
                                    $possiblePaths = [
                                        'C:\\xampp\\php\\php.exe',
                                        dirname(dirname($phpPath)) . '\\php\\php.exe',
                                        'php' // Fallback to PATH
                                    ];
                                    
                                    foreach ($possiblePaths as $path) {
                                        if ($path === 'php' || file_exists($path)) {
                                            $phpPath = $path;
                                            break;
                                        }
                                    }
                                }
                            }
                            
                            // Parse command to ensure it uses absolute paths
                            $command = $job['command'];
                            
                            // Replace relative cron/ path with absolute path
                            if (strpos($command, 'cron/') !== false) {
                                $command = str_replace('cron/', $basePath . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR, $command);
                            }
                            
                            // If command starts with 'php', ensure we use full PHP path
                            if (stripos(trim($command), 'php ') === 0) {
                                // Extract the script path
                                $scriptPath = trim(substr($command, 4));
                                
                                // Build command with proper quoting for Windows
                                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                                    $command = '"' . $phpPath . '" "' . $scriptPath . '"';
                                } else {
                                    $command = escapeshellcmd($phpPath) . ' ' . escapeshellarg($scriptPath);
                                }
                            }
                            
                            // Change to base directory before execution
                            $oldDir = getcwd();
                            chdir($basePath);
                            
                            // Execute command
                            $output = [];
                            $return_var = 0;
                            exec($command . ' 2>&1', $output, $return_var);
                            
                            // Restore directory
                            chdir($oldDir);
                            
                            // Update results
                            $status = $return_var === 0 ? 'success' : 'failed';
                            $output_text = implode("\n", $output);
                            
                            // Add debug info
                            $debug_info = "Working Directory: $basePath\n";
                            $debug_info .= "PHP Binary: $phpPath\n";
                            $debug_info .= "Original Command: {$job['command']}\n";
                            $debug_info .= "Executed Command: $command\n";
                            $debug_info .= "Exit Code: $return_var\n";
                            $debug_info .= str_repeat("-", 50) . "\n\n";
                            
                            $output_text = $debug_info . ($output_text ?: '(No output)');
                            
                            $stmt = $db->prepare("UPDATE cron_jobs SET last_run_status = ?, last_run_output = ?, run_count = run_count + 1, last_run_at = NOW() WHERE id = ?");
                            $stmt->bind_param("ssi", $status, $output_text, $id);
                            $stmt->execute();
                            
                            if ($status === 'success') {
                                $_SESSION['success'] = 'Cron job executed successfully!';
                            } else {
                                $_SESSION['error'] = 'Cron job failed. Click the file icon to view error details.';
                            }
                        } catch (Exception $e) {
                            $status = 'failed';
                            $output_text = "Exception: " . $e->getMessage();
                            
                            $stmt = $db->prepare("UPDATE cron_jobs SET last_run_status = ?, last_run_output = ?, run_count = run_count + 1, last_run_at = NOW() WHERE id = ?");
                            $stmt->bind_param("ssi", $status, $output_text, $id);
                            $stmt->execute();
                            
                            $_SESSION['error'] = 'Exception occurred: ' . $e->getMessage();
                        }
                    } else {
                        $_SESSION['error'] = 'Cron job not found';
                    }
                    return $this->redirect('/admin/cron-jobs');
            }
        }
        
        // Get all cron jobs
        $result = $db->query("SELECT * FROM cron_jobs ORDER BY id ASC");
        $cronJobs = [];
        while ($row = $result->fetch_assoc()) {
            $cronJobs[] = $row;
        }
        
        $data = [
            'title' => 'Cron Jobs Management',
            'cronJobs' => $cronJobs,
            'pageTitle' => 'Cron Jobs Management',
            'currentPage' => 'cron-jobs'
        ];
        
        $this->view('admin/cron-jobs', $data);
    }
    

    
    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Format age in seconds to human readable
     */
    private function formatAge($seconds)
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        
        if ($days > 0) {
            return $days . 'd ' . $hours . 'h';
        } elseif ($hours > 0) {
            return $hours . 'h';
        } else {
            return floor($seconds / 60) . 'm';
        }
    }
}
