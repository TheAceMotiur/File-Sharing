<?php
require_once __DIR__ . '/../config.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: ../login.php');
    exit;
}

if (isset($_GET['name'])) {
    $backupDir = __DIR__ . '/../backups';
    $backupPath = $backupDir . '/' . $_GET['name'];

    if (file_exists($backupPath) && is_dir($backupPath)) {
        // Create temporary zip file
        $zipFile = tempnam(sys_get_temp_dir(), 'backup_');
        $zip = new ZipArchive();
        
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            // Add all files from backup directory
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($backupPath),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $relativePath = substr($file->getRealPath(), strlen($backupPath) + 1);
                    $zip->addFile($file->getRealPath(), $relativePath);
                }
            }
            
            $zip->close();

            // Send the file to the browser
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $_GET['name'] . '.zip"');
            header('Content-Length: ' . filesize($zipFile));
            header('Pragma: no-cache');
            
            readfile($zipFile);
            unlink($zipFile); // Delete temporary file
            exit;
        }
    }
}

// If something goes wrong, redirect back to backup page
header('Location: backup.php');
exit;
