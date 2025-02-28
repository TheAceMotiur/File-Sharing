<?php
require_once __DIR__ . '/config.php';
session_start();
require_once __DIR__ . '/includes/auth.php';

// Check if user is logged in and verified
checkEmailVerification();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}
 
try {
    // Include database configuration and get connection
    $db = getDBConnection();
    
    // Get user data function
    function getUserData($db, $userId) {
        $stmt = $db->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    // Get initial user data
    $user = getUserData($db, $_SESSION['user_id']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        
        // Update name if provided
        if (!empty($name) && $name !== $user['name']) {
            $stmt = $db->prepare("UPDATE users SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $name, $_SESSION['user_id']);
            if ($stmt->execute()) {
                $_SESSION['user_name'] = $name;
                $success = "Profile updated successfully";
                // Refresh user data after update
                $user = getUserData($db, $_SESSION['user_id']);
            } else {
                $error = "Failed to update profile";
            }
        }
        
        // Update password if both fields are provided
        if (!empty($currentPassword) && !empty($newPassword)) {
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if (password_verify($currentPassword, $result['password'])) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashedPassword, $_SESSION['user_id']);
                if ($stmt->execute()) {
                    $success = "Password updated successfully";
                    // Clear password fields after successful update
                    $currentPassword = '';
                    $newPassword = '';
                } else {
                    $error = "Failed to update password";
                }
            } else {
                $error = "Current password is incorrect";
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
    <title>Profile Settings</title>
    <link rel="icon" type="image/png" href="icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<?php include 'header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md p-6 max-w-2xl mx-auto">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">Profile Settings</h1>

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

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded bg-gray-50" readonly>
                </div>

                <div>
                    <label for="name" class="block text-gray-700 text-sm font-bold mb-2">Name</label>
                    <input type="text" name="name" id="name" 
                        value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"
                        required>
                </div>

                <hr class="my-6">

                <h2 class="text-xl font-semibold text-gray-800 mb-4">Change Password</h2>
                
                <div>
                    <label for="current_password" class="block text-gray-700 text-sm font-bold mb-2">Current Password</label>
                    <input type="password" name="current_password" id="current_password"
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"
                        minlength="6">
                </div>

                <div>
                    <label for="new_password" class="block text-gray-700 text-sm font-bold mb-2">New Password</label>
                    <input type="password" name="new_password" id="new_password"
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"
                        minlength="6">
                </div>

                <div class="flex items-center justify-between pt-4">
                    <button type="submit"
                        class="bg-blue-500 text-white font-bold py-2 px-6 rounded hover:bg-blue-600 focus:outline-none focus:shadow-outline">
                        Save Changes
                    </button>
                    <a href="dashboard" 
                        class="bg-gray-500 text-white font-bold py-2 px-6 rounded hover:bg-gray-600 focus:outline-none focus:shadow-outline">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>