<?php
require_once __DIR__ . '/../config.php';

function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        // Check for remember token
        if (isset($_COOKIE['remember_token'])) {
            $db = App\Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("
                SELECT id, name 
                FROM users 
                WHERE remember_token = ? 
                AND remember_token_expires > NOW()
            ");
            $stmt->bind_param("s", $_COOKIE['remember_token']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($user = $result->fetch_assoc()) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                updatePremiumStatus($user['id'], $db);
                return true;
            }
        }
        
        header('Location: login.php');
        exit;
    }
    return true;
}

function checkEmailVerification() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT email_verified FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (!$result['email_verified']) {
            header('Location: verify.php?redirect=' . urlencode($_SERVER['PHP_SELF']));
            exit;
        }
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
}

// Update this function to update premium status in session
function updatePremiumStatus($userId, $db) {
    if (!$userId || !$db) return;
    
    try {
        $stmt = $db->prepare("SELECT premium FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            // Set both session variables for compatibility across the site
            $_SESSION['user_premium'] = (bool)$user['premium'];
            $_SESSION['premium'] = (bool)$user['premium']; 
        }
    } catch (Exception $e) {
        // Silent fail - default to non-premium if error
        $_SESSION['user_premium'] = false;
        $_SESSION['premium'] = false;
    }
}
?>
