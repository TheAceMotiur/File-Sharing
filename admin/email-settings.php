<?php
require_once __DIR__ . '/../config.php';
session_start();

// Security check
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: ../login.php');
    exit;
}

try {
    // Include database configuration
    $db = getDBConnection();

    // Fetch current settings
    $result = $db->query("SELECT * FROM email_settings LIMIT 1");
    $emailSettings = $result->fetch_assoc();

    // Handle settings update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Create variables for binding
        $host = $_POST['settings']['smtp_host'] ?? '';
        $port = (int)$_POST['settings']['smtp_port'] ?? 587;
        $username = $_POST['settings']['smtp_username'] ?? '';
        $password = $_POST['settings']['smtp_password'] ?? '';
        $encryption = 'tls';
        $from = $_POST['settings']['mail_from'] ?? '';
        $fromName = $_POST['settings']['mail_from_name'] ?? '';

        // Check if record exists
        $check = $db->query("SELECT COUNT(*) as count FROM email_settings")->fetch_assoc();
        
        if ($check['count'] == 0) {
            $stmt = $db->prepare("
                INSERT INTO email_settings (
                    mail_host, mail_port, mail_username, mail_password,
                    mail_encryption, mail_from_address, mail_from_name
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
        } else {
            $stmt = $db->prepare("
                UPDATE email_settings SET 
                mail_host = ?,
                mail_port = ?,
                mail_username = ?,
                mail_password = ?,
                mail_encryption = ?,
                mail_from_address = ?,
                mail_from_name = ?
                WHERE id = 1
            ");
        }
        
        $stmt->bind_param("sisssss", 
            $host,
            $port,
            $username,
            $password,
            $encryption,
            $from,
            $fromName
        );

        if ($stmt->execute()) {
            $success = "Email settings updated successfully";
            $result = $db->query("SELECT * FROM email_settings LIMIT 1");
            $emailSettings = $result->fetch_assoc();
        } else {
            $error = "Failed to update settings";
        }
    }

} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Settings - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50" x-data="{ sidebarOpen: false }">
    <!-- Mobile Sidebar Toggle Button -->
    <div class="lg:hidden fixed top-4 left-4 z-50">
        <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-700">
            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path :class="{'hidden': sidebarOpen, 'inline-flex': !sidebarOpen }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                <path :class="{'hidden': !sidebarOpen, 'inline-flex': sidebarOpen }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Sidebar Overlay -->
    <div x-show="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 z-10 bg-gray-900 opacity-50 transition-opacity lg:hidden"></div>

    <div class="flex min-h-screen">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="lg:ml-64 flex-1 p-4 lg:p-8">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Email Settings</h1>
            </div>

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

            <div class="bg-white rounded-lg shadow-sm p-6">
                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">SMTP Host</label>
                            <input type="text" name="settings[smtp_host]" 
                                value="<?php echo htmlspecialchars($emailSettings['mail_host'] ?? ''); ?>"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">SMTP Port</label>
                            <input type="number" name="settings[smtp_port]" 
                                value="<?php echo htmlspecialchars($emailSettings['mail_port'] ?? ''); ?>"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">SMTP Username</label>
                            <input type="text" name="settings[smtp_username]" 
                                value="<?php echo htmlspecialchars($emailSettings['mail_username'] ?? ''); ?>"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">SMTP Password</label>
                            <input type="password" name="settings[smtp_password]" 
                                value="<?php echo htmlspecialchars($emailSettings['mail_password'] ?? ''); ?>"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Mail From Address</label>
                            <input type="email" name="settings[mail_from]" 
                                value="<?php echo htmlspecialchars($emailSettings['mail_from_address'] ?? ''); ?>"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Mail From Name</label>
                            <input type="text" name="settings[mail_from_name]" 
                                value="<?php echo htmlspecialchars($emailSettings['mail_from_name'] ?? ''); ?>"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="submit" 
                            class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>