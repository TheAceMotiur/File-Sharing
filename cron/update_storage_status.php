<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../utils/dropbox_helper.php';

/**
 * This script updates the storage status for all Dropbox accounts
 * It's designed to be run as a cron job (e.g., every hour)
 */

try {
    $db = getDBConnection();
    
    // Get all Dropbox accounts
    $accounts = $db->query("SELECT * FROM dropbox_accounts")->fetch_all(MYSQLI_ASSOC);
    
    $updatedAccounts = 0;
    $errors = 0;
    
    foreach ($accounts as $account) {
        try {
            echo "Checking account ID {$account['id']} ({$account['app_key']})...\n";
            
            // Get space info for this account
            $spaceInfo = DropboxHelper::getAccountSpaceInfo($account);
            
            if (!$spaceInfo) {
                echo "  Failed to get space info\n";
                $errors++;
                continue;
            }
            
            $usedSpace = $spaceInfo['used'];
            $totalSpace = $spaceInfo['allocation']['allocated'];
            $availableSpace = $totalSpace - $usedSpace;
            $isFull = ($availableSpace < 10 * 1024 * 1024); // Mark as full if less than 10MB left
            
            echo "  Used space: " . number_format($usedSpace / (1024 * 1024), 2) . " MB\n";
            echo "  Total space: " . number_format($totalSpace / (1024 * 1024), 2) . " MB\n";
            echo "  Available space: " . number_format($availableSpace / (1024 * 1024), 2) . " MB\n";
            echo "  Status: " . ($isFull ? "FULL" : "Available") . "\n";
            
            // Update database
            $stmt = $db->prepare("UPDATE dropbox_accounts SET 
                is_full = ?, 
                last_space_check = NOW(),
                available_space = ?,
                total_space = ?
                WHERE id = ?");
                
            $stmt->bind_param("iiis", 
                $isFull,
                $availableSpace,
                $totalSpace,
                $account['id']
            );
            
            $stmt->execute();
            $updatedAccounts++;
        } catch (Exception $e) {
            echo "  Error processing account ID {$account['id']}: {$e->getMessage()}\n";
            $errors++;
        }
    }
    
    echo "Summary: Updated {$updatedAccounts} accounts, encountered {$errors} errors\n";
    
} catch (Exception $e) {
    echo "Fatal error: {$e->getMessage()}\n";
    exit(1);
}
