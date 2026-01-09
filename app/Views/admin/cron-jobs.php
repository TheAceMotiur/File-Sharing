<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Cron Jobs'; ?> - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50">
    <div class="flex">
        <?php include __DIR__ . '/partials/sidebar.php'; ?>
        
        <div class="flex-1 ml-64">
            <div class="p-8">
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Cron Jobs Management</h1>
                        <p class="text-gray-600 mt-1">Manage automated tasks and schedules</p>
                    </div>
                    <button onclick="showAddModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>Add Cron Job
                    </button>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
                <?php endif; ?>

                <!-- Cron Setup Instructions -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
                        <div class="flex-1">
                            <h3 class="font-semibold text-blue-900 mb-2">Setup Instructions</h3>
                            <p class="text-blue-800 text-sm mb-2">Add this to your system crontab to enable automatic execution:</p>
                            <code class="block bg-blue-100 p-2 rounded text-sm font-mono">
                                * * * * * cd <?php echo BASE_PATH; ?> && php cron/master.php >> logs/cron.log 2>&1
                            </code>
                            <p class="text-blue-700 text-xs mt-2">This master cron will check and run all active jobs based on their schedules.</p>
                        </div>
                    </div>
                </div>

                <!-- Cron Jobs Table -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Schedule</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Run</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Runs</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($cronJobs as $job): ?>
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-lg <?php echo $job['is_active'] ? 'bg-green-100' : 'bg-gray-100'; ?>">
                                            <i class="fas fa-clock <?php echo $job['is_active'] ? 'text-green-600' : 'text-gray-400'; ?>"></i>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($job['name']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($job['description']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <code class="text-xs bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($job['schedule']); ?></code>
                                    <div class="text-xs text-gray-500 mt-1"><?php echo getCronDescription($job['schedule']); ?></div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php if ($job['last_run_at']): ?>
                                        <?php echo date('M d, Y H:i', strtotime($job['last_run_at'])); ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($job['last_run_status'] === 'success'): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle"></i> Success
                                        </span>
                                    <?php elseif ($job['last_run_status'] === 'failed'): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                            <i class="fas fa-times-circle"></i> Failed
                                        </span>
                                    <?php elseif ($job['last_run_status'] === 'running'): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                            <i class="fas fa-spinner fa-spin"></i> Running
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php echo number_format($job['run_count']); ?>
                                </td>
                                <td class="px-6 py-4 text-right text-sm font-medium">
                                    <button onclick='runJob(<?php echo $job["id"]; ?>)' 
                                            class="text-blue-600 hover:text-blue-900 mr-3" 
                                            title="Run Now">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <button onclick='viewOutput(<?php echo htmlspecialchars(json_encode($job), ENT_QUOTES, 'UTF-8'); ?>)' 
                                            class="text-purple-600 hover:text-purple-900 mr-3" 
                                            title="View Output">
                                        <i class="fas fa-file-alt"></i>
                                    </button>
                                    <button onclick='editJob(<?php echo htmlspecialchars(json_encode($job), ENT_QUOTES, 'UTF-8'); ?>)' 
                                            class="text-yellow-600 hover:text-yellow-900 mr-3" 
                                            title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick='toggleJob(<?php echo $job["id"]; ?>, <?php echo $job["is_active"]; ?>)' 
                                            class="<?php echo $job['is_active'] ? 'text-orange-600 hover:text-orange-900' : 'text-green-600 hover:text-green-900'; ?> mr-3" 
                                            title="<?php echo $job['is_active'] ? 'Disable' : 'Enable'; ?>">
                                        <i class="fas fa-<?php echo $job['is_active'] ? 'pause' : 'play'; ?>-circle"></i>
                                    </button>
                                    <button onclick='deleteJob(<?php echo $job["id"]; ?>, "<?php echo htmlspecialchars($job["name"], ENT_QUOTES, 'UTF-8'); ?>")' 
                                            class="text-red-600 hover:text-red-900" 
                                            title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($cronJobs)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-clock text-4xl mb-4"></i>
                                    <p>No cron jobs configured yet</p>
                                    <button onclick="showAddModal()" class="mt-4 text-blue-600 hover:text-blue-800">
                                        Add your first cron job
                                    </button>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showAddModal() {
            Swal.fire({
                title: 'Add Cron Job',
                html: `
                    <form id="addForm" method="POST" class="text-left">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
                            <input type="text" name="name" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea name="description" rows="2" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Command *</label>
                            <input type="text" name="command" required placeholder="php cron/your-script.php"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Schedule (Cron Format) *</label>
                            <input type="text" name="schedule" value="* * * * *" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Format: minute hour day month weekday</p>
                            <div class="text-xs text-gray-600 mt-2">
                                Examples:<br>
                                • <code>* * * * *</code> - Every minute<br>
                                • <code>*/5 * * * *</code> - Every 5 minutes<br>
                                • <code>0 * * * *</code> - Every hour<br>
                                • <code>0 0 * * *</code> - Daily at midnight<br>
                                • <code>0 2 * * *</code> - Daily at 2 AM
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_active" checked class="mr-2">
                                <span class="text-sm text-gray-700">Active</span>
                            </label>
                        </div>
                    </form>
                `,
                width: 600,
                showCancelButton: true,
                confirmButtonText: 'Add Cron Job',
                confirmButtonColor: '#3B82F6',
                preConfirm: () => {
                    document.getElementById('addForm').submit();
                    return false;
                }
            });
        }

        function editJob(job) {
            Swal.fire({
                title: 'Edit Cron Job',
                html: `
                    <form id="editForm" method="POST" class="text-left">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="${job.id}">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
                            <input type="text" name="name" value="${job.name}" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea name="description" rows="2" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">${job.description || ''}</textarea>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Command *</label>
                            <input type="text" name="command" value="${job.command}" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Schedule (Cron Format) *</label>
                            <input type="text" name="schedule" value="${job.schedule}" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_active" ${job.is_active ? 'checked' : ''} class="mr-2">
                                <span class="text-sm text-gray-700">Active</span>
                            </label>
                        </div>
                    </form>
                `,
                width: 600,
                showCancelButton: true,
                confirmButtonText: 'Update',
                confirmButtonColor: '#3B82F6',
                preConfirm: () => {
                    document.getElementById('editForm').submit();
                    return false;
                }
            });
        }

        function viewOutput(job) {
            Swal.fire({
                title: 'Last Run Output',
                html: `
                    <div class="text-left">
                        <div class="mb-4">
                            <strong>Command:</strong><br>
                            <code class="text-sm">${job.command}</code>
                        </div>
                        <div class="mb-4">
                            <strong>Last Run:</strong> ${job.last_run_at ? new Date(job.last_run_at).toLocaleString() : 'Never'}
                        </div>
                        <div class="mb-4">
                            <strong>Status:</strong> 
                            <span class="px-2 py-1 text-xs font-semibold rounded ${job.last_run_status === 'success' ? 'bg-green-100 text-green-800' : job.last_run_status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'}">
                                ${job.last_run_status || 'N/A'}
                            </span>
                        </div>
                        <div>
                            <strong>Output:</strong>
                            <pre class="mt-2 p-3 bg-gray-100 rounded text-xs overflow-auto max-h-64">${job.last_run_output || 'No output available'}</pre>
                        </div>
                    </div>
                `,
                width: 700,
                confirmButtonText: 'Close'
            });
        }

        function runJob(id) {
            Swal.fire({
                title: 'Run Cron Job?',
                text: 'This will execute the cron job immediately.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Run Now',
                confirmButtonColor: '#3B82F6'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="run">
                        <input type="hidden" name="id" value="${id}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function toggleJob(id, isActive) {
            const action = isActive ? 'disable' : 'enable';
            Swal.fire({
                title: `${action.charAt(0).toUpperCase() + action.slice(1)} Cron Job?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: action.charAt(0).toUpperCase() + action.slice(1),
                confirmButtonColor: isActive ? '#F59E0B' : '#10B981'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="${id}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function deleteJob(id, name) {
            Swal.fire({
                title: 'Delete Cron Job?',
                text: `Are you sure you want to delete "${name}"? This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                confirmButtonColor: '#EF4444'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="${id}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
    </script>
</body>
</html>

<?php
function getCronDescription($schedule) {
    $parts = explode(' ', $schedule);
    if (count($parts) < 5) return 'Invalid schedule';
    
    list($min, $hour, $day, $month, $weekday) = $parts;
    
    if ($schedule === '* * * * *') return 'Every minute';
    if ($schedule === '*/5 * * * *') return 'Every 5 minutes';
    if ($schedule === '0 * * * *') return 'Every hour';
    if ($schedule === '0 */6 * * *') return 'Every 6 hours';
    if ($schedule === '0 0 * * *') return 'Daily at midnight';
    if ($schedule === '0 2 * * *') return 'Daily at 2:00 AM';
    if ($schedule === '0 3 * * *') return 'Daily at 3:00 AM';
    
    return 'Custom schedule';
}
?>
