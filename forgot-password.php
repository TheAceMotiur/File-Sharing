<?php
require_once __DIR__ . '/config.php';
session_start();
require_once __DIR__ . '/includes/EmailService.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    try {
        $db = getDBConnection();
        
        // Check if email exists
        $stmt = $db->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Delete any existing reset tokens for this user
            $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            
            // Save reset token
            $stmt = $db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user['id'], $token, $expires);
            $stmt->execute();
            
            // Get email settings
            $result = $db->query("SELECT * FROM email_settings LIMIT 1");
            $emailSettings = $result->fetch_assoc();
            
            if (!$emailSettings) {
                throw new Exception("Email settings not configured");
            }
            
            // Send reset email
            $emailService = new EmailService($emailSettings);
            $resetLink = "https://" . $_SERVER['HTTP_HOST'] . "/reset-password.php?token=" . $token;
            
            try {
                $emailService->sendPasswordResetEmail($email, $user['name'], $resetLink);
                $success = "If an account exists with this email, you will receive password reset instructions.";
            } catch (Exception $e) {
                error_log("Password reset email failed: " . $e->getMessage());
                $success = "If an account exists with this email, you will receive password reset instructions.";
            }
        } else {
            // Show same message even if email doesn't exist (security)
            $success = "If an account exists with this email, you will receive password reset instructions.";
        }
        
    } catch (Exception $e) {
        error_log("Password reset error: " . $e->getMessage());
        $error = "An error occurred. Please try again later.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - FilesWith</title>
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
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <p class="text-gray-600 mb-6 text-center">
                Enter your email address and we'll send you instructions to reset your password.
            </p>

            <form method="POST" class="space-y-4">
                <div>
                    <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email Address</label>
                    <input type="email" name="email" id="email" required
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <button type="submit"
                    class="w-full bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-600 focus:outline-none focus:shadow-outline">
                    Send Reset Link
                </button>
            </form>

            <p class="mt-4 text-center text-gray-600">
                Remember your password? 
                <a href="login.php" class="text-blue-500 hover:text-blue-700">Login here</a>
            </p>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>