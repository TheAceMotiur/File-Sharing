<?php
require_once __DIR__ . '/config.php';
session_start();

try {
    $db = getDBConnection();
    
    // Validate token
    if (empty($_GET['token'])) {
        throw new Exception("Invalid password reset token");
    }
    
    $token = $_GET['token'];
    
    // Check if token exists and is valid
    $stmt = $db->prepare("SELECT user_id, expires_at FROM password_resets WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();
    
    if (!$reset || strtotime($reset['expires_at']) < time()) {
        throw new Exception("Invalid or expired reset token");
    }
    
    // Handle password reset
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if (empty($password) || strlen($password) < 6) {
            $error = "Password must be at least 6 characters long";
        } else if ($password !== $confirmPassword) {
            $error = "Passwords do not match";
        } else {
            // Update password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashedPassword, $reset['user_id']);
            
            if ($stmt->execute()) {
                // Delete used token
                $stmt = $db->prepare("DELETE FROM password_resets WHERE token = ?");
                $stmt->bind_param("s", $token);
                $stmt->execute();
                
                $success = "Password has been reset successfully";
            }
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/png" href="icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php include 'header.php'; ?>

    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white p-8 rounded-lg shadow-md w-96">
            <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Reset Password</h2>
            
            <?php if (isset($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($success); ?>
                    <p class="mt-2 text-sm">
                        <a href="login" class="text-green-700 font-semibold hover:underline">Click here to login</a>
                    </p>
                </div>
            <?php else: ?>
                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <div>
                        <label for="password" class="block text-gray-700 text-sm font-bold mb-2">New Password</label>
                        <input type="password" name="password" id="password" required
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"
                            minlength="6">
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" required
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"
                            minlength="6">
                    </div>

                    <button type="submit"
                        class="w-full bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-600 focus:outline-none focus:shadow-outline">
                        Reset Password
                    </button>
                </form>
            <?php endif; ?>

            <p class="mt-4 text-center text-gray-600">
                Remember your password? 
                <a href="login" class="text-blue-500 hover:text-blue-700">Login here</a>
            </p>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>