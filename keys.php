<?php
require_once __DIR__ . '/config.php';
session_start();
require_once __DIR__ . '/includes/auth.php';

// Check if user is logged in and verified
checkEmailVerification();

try {
    $db = getDBConnection();
    
    // Generate new API key
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch($_POST['action']) {
                case 'generate':
                    $name = trim($_POST['name']);
                    $apiKey = bin2hex(random_bytes(32));
                    
                    $stmt = $db->prepare("INSERT INTO api_keys (user_id, api_key, name) VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $_SESSION['user_id'], $apiKey, $name);
                    
                    if ($stmt->execute()) {
                        $success = "API key generated successfully";
                    } else {
                        $error = "Failed to generate API key";
                    }
                    break;
                    
                case 'delete':
                    $stmt = $db->prepare("DELETE FROM api_keys WHERE id = ? AND user_id = ?");
                    $stmt->bind_param("ii", $_POST['key_id'], $_SESSION['user_id']);
                    $stmt->execute();
                    break;
            }
        }
    }
    
    // Get user's API keys
    $stmt = $db->prepare("SELECT * FROM api_keys WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $apiKeys = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error = "An error occurred: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Keys - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/png" href="icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php include 'header.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">API Keys</h1>
                <button onclick="document.getElementById('generateModal').classList.remove('hidden')" 
                        class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                    Generate New Key
                </button>
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

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">API Key</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Last Used</th>
                            <th class="px-6 py-3 bg-gray-50 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($apiKeys as $key): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($key['name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-600">
                                    <?php echo htmlspecialchars($key['api_key']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($key['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $key['last_used_at'] ? date('M d, Y', strtotime($key['last_used_at'])) : 'Never'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this API key?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="key_id" value="<?php echo $key['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Generate Key Modal -->
    <div id="generateModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center">
        <div class="bg-white rounded-lg p-8 max-w-md w-full">
            <h2 class="text-xl font-bold mb-4">Generate New API Key</h2>
            <form method="POST">
                <input type="hidden" name="action" value="generate">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Key Name</label>
                        <input type="text" name="name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="document.getElementById('generateModal').classList.add('hidden')"
                                class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                            Generate
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
