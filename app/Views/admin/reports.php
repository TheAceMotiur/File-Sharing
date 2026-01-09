<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <?php $currentPage = 'reports'; include __DIR__ . '/partials/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto">
            <div class="p-8">
                <div class="mb-6">
                    <h2 class="text-3xl font-bold">File Reports</h2>
                </div>

                <!-- Reports Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">File</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reporter</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reported</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($reports)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                    No reports found
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($reports as $report): ?>
                            <tr>
                                <td class="px-6 py-4 text-sm">#<?php echo $report['id']; ?></td>
                                <td class="px-6 py-4 text-sm"><?php echo htmlspecialchars($report['original_name'] ?? 'N/A'); ?></td>
                                <td class="px-6 py-4 text-sm" style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($report['reason']); ?></td>
                                <td class="px-6 py-4 text-sm"><?php echo htmlspecialchars($report['reporter_email'] ?? 'Anonymous'); ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full 
                                        <?php echo $report['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'; ?>">
                                        <?php echo ucfirst($report['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($report['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <?php if ($report['status'] === 'pending'): ?>
                                    <button onclick="resolveReport(<?php echo $report['id']; ?>)" class="text-green-600 hover:text-green-900 mr-2" title="Mark as Resolved">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button onclick="rejectReport(<?php echo $report['id']; ?>)" class="text-yellow-600 hover:text-yellow-900 mr-2" title="Reject Report">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($report['unique_id']): ?>
                                    <button onclick="deleteFile('<?php echo $report['unique_id']; ?>', <?php echo $report['id']; ?>)" class="text-red-600 hover:text-red-900" title="Delete File Everywhere">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <a href="/info/<?php echo $report['unique_id']; ?>" target="_blank" class="text-blue-600 hover:text-blue-900 ml-2" title="View File">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        async function resolveReport(reportId) {
            if (confirm('Mark this report as resolved?')) {
                try {
                    const response = await fetch(`/admin/reports/resolve/${reportId}`, {
                        method: 'POST'
                    });
                    if (response.ok) {
                        location.reload();
                    } else {
                        alert('Failed to resolve report');
                    }
                } catch (error) {
                    alert('Error: ' + error.message);
                }
            }
        }
        
        async function rejectReport(reportId) {
            if (confirm('Reject this report?')) {
                try {
                    const response = await fetch(`/admin/reports/reject/${reportId}`, {
                        method: 'POST'
                    });
                    if (response.ok) {
                        location.reload();
                    } else {
                        alert('Failed to reject report');
                    }
                } catch (error) {
                    alert('Error: ' + error.message);
                }
            }
        }
        
        async function deleteFile(uniqueId, reportId) {
            const result = await Swal.fire({
                title: 'Delete File Everywhere?',
                html: 'This will permanently delete the file from:<br><br>' +
                      '<i class="fas fa-database"></i> Database<br>' +
                      '<i class="fas fa-hdd"></i> Local Storage<br>' +
                      '<i class="fas fa-cloud"></i> Dropbox<br><br>' +
                      '<strong>This action cannot be undone!</strong>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            });
            
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Deleting...',
                    html: 'Please wait while we delete the file from all locations.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                try {
                    const response = await fetch(`/admin/reports/delete-file/${uniqueId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ report_id: reportId })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        await Swal.fire({
                            title: 'Deleted!',
                            text: 'File has been deleted from all locations.',
                            icon: 'success',
                            timer: 2000
                        });
                        location.reload();
                    } else {
                        Swal.fire('Error', data.error || 'Failed to delete file', 'error');
                    }
                } catch (error) {
                    Swal.fire('Error', 'An error occurred: ' + error.message, 'error');
                }
            }
        }
    </script>
</body>
</html>
