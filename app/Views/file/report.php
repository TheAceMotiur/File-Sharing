<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - OneNetly</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .report-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
        }
        .report-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .report-header i {
            font-size: 60px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        .report-header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }
        .file-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .file-info h5 {
            color: #667eea;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .file-info p {
            margin-bottom: 5px;
            color: #666;
        }
        .form-label {
            font-weight: 600;
            color: #333;
        }
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 40px;
            border-radius: 50px;
            color: white;
            font-weight: 600;
            width: 100%;
            transition: transform 0.2s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .alert {
            border-radius: 10px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="report-header">
            <i class="fas fa-flag"></i>
            <h1>Report File</h1>
            <p class="text-muted">Help us maintain a safe platform by reporting inappropriate content</p>
        </div>

        <div class="file-info">
            <h5><i class="fas fa-file me-2"></i>File Information</h5>
            <p><strong>Filename:</strong> <?= htmlspecialchars($file['original_name']) ?></p>
            <p><strong>Size:</strong> <?= number_format($file['size'] / 1024 / 1024, 2) ?> MB</p>
            <p><strong>Uploaded:</strong> <?= date('F j, Y', strtotime($file['created_at'])) ?></p>
        </div>

        <div id="alertContainer"></div>

        <form id="reportForm">
            <div class="mb-3">
                <label for="reason" class="form-label">Reason for Report <span class="text-danger">*</span></label>
                <select class="form-select" id="reason" name="reason" required>
                    <option value="">Select a reason...</option>
                    <option value="copyright">Copyright Infringement</option>
                    <option value="malware">Malware / Virus</option>
                    <option value="inappropriate">Inappropriate Content</option>
                    <option value="spam">Spam</option>
                    <option value="illegal">Illegal Content</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="details" class="form-label">Additional Details</label>
                <textarea class="form-control" id="details" name="details" rows="4" 
                          placeholder="Please provide additional information about your report..."></textarea>
            </div>

            <div class="mb-4">
                <label for="email" class="form-label">Your Email (Optional)</label>
                <input type="email" class="form-control" id="email" name="email" 
                       placeholder="your@email.com">
                <small class="text-muted">Provide your email if you'd like us to follow up with you</small>
            </div>

            <button type="submit" class="btn btn-submit">
                <i class="fas fa-paper-plane me-2"></i>Submit Report
            </button>
        </form>

        <div class="text-center mt-3">
            <a href="/info/<?= htmlspecialchars($file['unique_id']) ?>" class="text-muted">
                <i class="fas fa-arrow-left me-1"></i>Back to File Info
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('reportForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
            
            const formData = new FormData(e.target);
            const reason = formData.get('reason');
            const details = formData.get('details');
            const email = formData.get('email');
            
            // Combine reason and details
            const fullReason = details ? `${reason}: ${details}` : reason;
            
            try {
                const response = await fetch('/report/<?= htmlspecialchars($file['unique_id']) ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        reason: fullReason,
                        email: email || ''
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('success', 'Report Submitted Successfully!', 'Thank you for helping us maintain a safe platform. We will review your report shortly.');
                    e.target.reset();
                } else {
                    showAlert('danger', 'Error', data.error || 'Failed to submit report. Please try again.');
                }
            } catch (error) {
                showAlert('danger', 'Error', 'An error occurred. Please try again later.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
        
        function showAlert(type, title, message) {
            const alertContainer = document.getElementById('alertContainer');
            alertContainer.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <strong>${title}</strong> ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            alertContainer.querySelector('.alert').style.display = 'block';
            
            // Auto dismiss success alerts after 5 seconds
            if (type === 'success') {
                setTimeout(() => {
                    const alert = alertContainer.querySelector('.alert');
                    if (alert) {
                        const bsAlert = bootstrap.Alert.getInstance(alert) || new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                }, 5000);
            }
        }
    </script>
</body>
</html>
