<?php
require_once __DIR__ . '/../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

try {
    $db = getDBConnection();
    
    // Verify admin status
    $stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user['is_admin']) {
        header('Location: ../dashboard.php');
        exit;
    }

    // Define cron jobs with their details
    $cronJobs = [
        [
            'name' => 'Cleanup Expired Files',
            'file' => 'cleanup.php',
            'description' => 'Deletes expired files (180+ days old for free users) from Dropbox and database. Also cleans up cache records older than 7 days.',
            'schedule' => 'Daily at 2:00 AM',
            'cron_syntax' => '0 2 * * *',
            'command_linux' => 'cd ' . BASE_PATH . '/cron && php cleanup.php',
            'command_windows' => 'cd ' . BASE_PATH . '\cron && php cleanup.php',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />'
        ],
        [
            'name' => 'Cleanup Abandoned Chunks',
            'file' => 'cleanup_chunks.php',
            'description' => 'Removes abandoned chunk directories that are older than 24 hours from failed or incomplete uploads.',
            'schedule' => 'Every hour',
            'cron_syntax' => '0 * * * *',
            'command_linux' => 'cd ' . BASE_PATH . '/cron && php cleanup_chunks.php',
            'command_windows' => 'cd ' . BASE_PATH . '\cron && php cleanup_chunks.php',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />'
        ],
        [
            'name' => 'Refresh Dropbox Tokens',
            'file' => 'refresh_dropbox_tokens.php',
            'description' => 'Refreshes Dropbox access tokens for all connected accounts to maintain authentication.',
            'schedule' => 'Every 3 hours',
            'cron_syntax' => '0 */3 * * *',
            'command_linux' => 'cd ' . BASE_PATH . '/cron && php refresh_dropbox_tokens.php',
            'command_windows' => 'cd ' . BASE_PATH . '\cron && php refresh_dropbox_tokens.php',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />'
        ],
        [
            'name' => 'Sync Local Files to Dropbox',
            'file' => 'sync_to_dropbox.php',
            'description' => 'Syncs locally stored files to Dropbox storage. Processes up to 10 files per run and manages storage distribution across accounts.',
            'schedule' => 'Every 5 minutes',
            'cron_syntax' => '*/5 * * * *',
            'command_linux' => 'cd ' . BASE_PATH . '/cron && php sync_to_dropbox.php',
            'command_windows' => 'cd ' . BASE_PATH . '\cron && php sync_to_dropbox.php',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />'
        ]
    ];

    // Handle test run
    if (isset($_POST['test_run']) && isset($_POST['file'])) {
        $file = basename($_POST['file']);
        $cronPath = BASE_PATH . '/cron/' . $file;
        
        if (file_exists($cronPath)) {
            ob_start();
            include $cronPath;
            $output = ob_get_clean();
            $success = "Cron job executed. Output: " . ($output ?: "No output");
        } else {
            $error = "Cron file not found: $file";
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
    <title>Cron Jobs - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100" x-data="{ sidebarOpen: false }">
    <div class="flex h-screen overflow-hidden">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col overflow-hidden lg:ml-64">
            <!-- Mobile Header -->
            <header class="lg:hidden bg-white shadow-sm z-10">
                <div class="flex items-center justify-between p-4">
                    <button @click="sidebarOpen = !sidebarOpen" class="text-gray-500 hover:text-gray-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <h1 class="text-xl font-semibold">Cron Jobs</h1>
                    <div class="w-6"></div>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100">
                <div class="container mx-auto px-4 py-8">
                    <div class="mb-6">
                        <h1 class="text-3xl font-bold text-gray-800">Cron Jobs Management</h1>
                        <p class="text-gray-600 mt-2">Setup and manage automated tasks for your application</p>
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

                    <!-- Setup Instructions -->
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-6 mb-6 rounded-lg">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-lg font-medium text-blue-800">Setup Instructions</h3>
                                <div class="mt-2 text-sm text-blue-700">
                                    <p class="mb-2"><strong>For Linux/Unix:</strong></p>
                                    <ol class="list-decimal ml-5 mb-4">
                                        <li>Open your crontab: <code class="bg-blue-100 px-2 py-1 rounded">crontab -e</code></li>
                                        <li>Copy the commands below and paste them into your crontab</li>
                                        <li>Save and exit</li>
                                    </ol>
                                    
                                    <p class="mb-2"><strong>For Windows:</strong></p>
                                    <ol class="list-decimal ml-5">
                                        <li>Open Task Scheduler</li>
                                        <li>Create a new basic task</li>
                                        <li>Set the trigger according to the schedule</li>
                                        <li>Set the action to "Start a program" with: <code class="bg-blue-100 px-2 py-1 rounded">php</code></li>
                                        <li>Add the full path to the cron file as an argument</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cron Jobs List -->
                    <div class="grid grid-cols-1 gap-6">
                        <?php foreach ($cronJobs as $index => $job): ?>
                            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                                <div class="p-6">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 bg-blue-100 rounded-lg p-3">
                                                <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <?php echo $job['icon']; ?>
                                                </svg>
                                            </div>
                                            <div class="ml-4">
                                                <h3 class="text-xl font-semibold text-gray-900"><?php echo htmlspecialchars($job['name']); ?></h3>
                                                <p class="text-sm text-gray-500 mt-1">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                        <?php echo htmlspecialchars($job['schedule']); ?>
                                                    </span>
                                                    <span class="ml-2 text-gray-400">
                                                        <code class="bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($job['cron_syntax']); ?></code>
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="file" value="<?php echo htmlspecialchars($job['file']); ?>">
                                            <button type="submit" name="test_run" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                                <i class="fas fa-play mr-2"></i>
                                                Test Run
                                            </button>
                                        </form>
                                    </div>

                                    <p class="mt-4 text-gray-600"><?php echo htmlspecialchars($job['description']); ?></p>

                                    <div class="mt-4 space-y-3">
                                        <!-- Linux Command -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                <i class="fab fa-linux text-gray-500"></i> Linux/Unix Crontab Entry:
                                            </label>
                                            <div class="flex items-center">
                                                <input 
                                                    type="text" 
                                                    readonly 
                                                    value="<?php echo htmlspecialchars($job['cron_syntax'] . ' ' . $job['command_linux']); ?>"
                                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-l-md bg-gray-50 text-sm font-mono"
                                                    id="linux-cmd-<?php echo $index; ?>">
                                                <button 
                                                    onclick="copyToClipboard('linux-cmd-<?php echo $index; ?>')"
                                                    class="px-4 py-2 bg-gray-700 text-white rounded-r-md hover:bg-gray-800 transition-colors">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Windows Command -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                <i class="fab fa-windows text-gray-500"></i> Windows Task Scheduler Command:
                                            </label>
                                            <div class="flex items-center">
                                                <input 
                                                    type="text" 
                                                    readonly 
                                                    value="<?php echo htmlspecialchars($job['command_windows']); ?>"
                                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-l-md bg-gray-50 text-sm font-mono"
                                                    id="windows-cmd-<?php echo $index; ?>">
                                                <button 
                                                    onclick="copyToClipboard('windows-cmd-<?php echo $index; ?>')"
                                                    class="px-4 py-2 bg-gray-700 text-white rounded-r-md hover:bg-gray-800 transition-colors">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- File Path -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                <i class="fas fa-file-code text-gray-500"></i> File Location:
                                            </label>
                                            <code class="block px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-sm">
                                                <?php echo htmlspecialchars(BASE_PATH . '/cron/' . $job['file']); ?>
                                            </code>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Quick Setup Guide -->
                    <div class="mt-8 bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">
                            <i class="fas fa-book text-blue-600 mr-2"></i>
                            Quick Setup Guide
                        </h2>
                        
                        <div class="grid md:grid-cols-2 gap-6">
                            <!-- Linux Setup -->
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700 mb-3">
                                    <i class="fab fa-linux mr-2"></i>Linux/Unix Setup
                                </h3>
                                <div class="bg-gray-50 p-4 rounded-md">
                                    <ol class="list-decimal ml-4 space-y-2 text-sm text-gray-700">
                                        <li>Open terminal</li>
                                        <li>Type: <code class="bg-gray-200 px-2 py-1 rounded">crontab -e</code></li>
                                        <li>Copy all the Linux commands above</li>
                                        <li>Paste them into the crontab file</li>
                                        <li>Save and exit (Ctrl+X, then Y, then Enter)</li>
                                        <li>Verify with: <code class="bg-gray-200 px-2 py-1 rounded">crontab -l</code></li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Windows Setup -->
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700 mb-3">
                                    <i class="fab fa-windows mr-2"></i>Windows Setup
                                </h3>
                                <div class="bg-gray-50 p-4 rounded-md">
                                    <ol class="list-decimal ml-4 space-y-2 text-sm text-gray-700">
                                        <li>Open Task Scheduler (search in Start menu)</li>
                                        <li>Click "Create Basic Task"</li>
                                        <li>Name it (e.g., "OneNetly Cleanup")</li>
                                        <li>Set trigger based on schedule</li>
                                        <li>Action: "Start a program"</li>
                                        <li>Program: <code class="bg-gray-200 px-2 py-1 rounded">php.exe</code> (full path)</li>
                                        <li>Arguments: Copy the file path from above</li>
                                        <li>Repeat for each cron job</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            element.select();
            element.setSelectionRange(0, 99999); // For mobile devices
            
            navigator.clipboard.writeText(element.value).then(() => {
                // Show feedback
                const button = element.nextElementSibling;
                const originalHTML = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i>';
                button.classList.add('bg-green-600');
                button.classList.remove('bg-gray-700');
                
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                    button.classList.remove('bg-green-600');
                    button.classList.add('bg-gray-700');
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        }
    </script>
</body>
</html>
