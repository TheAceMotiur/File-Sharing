<?php
require_once __DIR__ . '/config.php';
session_start();

// Include required files
require_once __DIR__ . '/includes/EmailService.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

// Store redirect URL if provided
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'dashboard';
$_SESSION['redirect_after_verify'] = $redirect;

try {
    $db = getDBConnection();
    
    // Check verification status
    $stmt = $db->prepare("SELECT email_verified, name, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    // If already verified, redirect to dashboard with flag
    if ($user['email_verified']) {
        $_SESSION['verified'] = true; // Use a different flag name
        header('Location: dashboard');
        exit;
    }

    // Handle resend request
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['resend'])) {
            // Generate new PIN
            $newPin = sprintf("%06d", mt_rand(0, 999999));
            
            // Update PIN in database
            $stmt = $db->prepare("UPDATE users SET verification_pin = ? WHERE id = ?");
            $stmt->bind_param("si", $newPin, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                // Get email settings
                $result = $db->query("SELECT * FROM email_settings LIMIT 1");
                $emailSettings = $result->fetch_assoc();

                // Send verification email
                $emailService = new EmailService($emailSettings);
                $emailService->sendVerificationEmail($user['email'], $user['name'], $newPin);
                $success = "Verification PIN has been resent to your email!";
            }
        }
        // Process PIN verification
        else if (isset($_POST['pin'])) {
            $pin = trim($_POST['pin']);
            
            if (empty($pin)) {
                $error = "Verification PIN is required";
            } else {
                // Verify PIN
                $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND verification_pin = ? AND email_verified = 0");
                $stmt->bind_param("is", $_SESSION['user_id'], $pin);
                $stmt->execute();
                
                if ($stmt->get_result()->fetch_assoc()) {
                    // Update user verification status
                    $stmt = $db->prepare("UPDATE users SET email_verified = 1, verification_pin = NULL WHERE id = ?");
                    $stmt->bind_param("i", $_SESSION['user_id']);
                    
                    if ($stmt->execute()) {
                        $_SESSION['verified'] = true; // Use consistent flag name
                        $_SESSION['user_name'] = $user['name'];
                        $redirectTo = $_SESSION['redirect_after_verify'] ?? 'dashboard.php';
                        unset($_SESSION['redirect_after_verify']);
                        header('Location: ' . $redirectTo);
                        exit;
                    }
                } else {
                    $error = "Invalid verification PIN";
                }
            }
        }
    }

} catch (Exception $e) {
    $error = "An error occurred: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <link rel="icon" type="image/png" href="icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include 'header.php'; ?>

    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-md w-96">
            <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Email Verification</h2>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <p class="text-gray-600 mb-6 text-center">
                Please enter the verification PIN sent to your email address.
            </p>

            <form method="POST" class="space-y-4">
                <div>
                    <label for="pin" class="block text-gray-700 text-sm font-bold mb-2">Verification PIN</label>
                    <input type="text" name="pin" id="pin" required
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"
                        placeholder="Enter 6-digit PIN">
                </div>

                <button type="submit"
                    class="w-full bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-600 focus:outline-none focus:shadow-outline">
                    Verify Email
                </button>
            </form>

            <div class="mt-4 text-center">
                <p class="text-sm text-gray-600">
                    Didn't receive the PIN?
                    <form method="POST" class="inline">
                        <input type="hidden" name="resend" value="1">
                        <button type="submit" class="text-blue-500 hover:text-blue-700 underline">
                            Resend verification email
                        </button>
                    </form>
                </p>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>