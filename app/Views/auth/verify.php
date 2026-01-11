<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Verify Email'; ?> - <?php echo getSiteName(); ?></title>
    <link rel="icon" type="image/png" href="/icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php include __DIR__ . '/../partials/header.php'; ?>

    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
            <div class="text-center">
                <svg class="mx-auto h-16 w-16 text-yellow-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                
                <h2 class="text-2xl font-bold mb-4 text-gray-800">Verify Your Email</h2>
                
                <p class="text-gray-600 mb-6">
                    Please check your email inbox for a verification link to activate your account.
                </p>
                
                <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded mb-6">
                    <p class="text-sm">
                        A verification email has been sent to your registered email address.
                        Please click the link in the email to verify your account.
                    </p>
                </div>
                
                <div class="space-y-4">
                    <button 
                        onclick="resendVerification()" 
                        id="resendBtn"
                        class="w-full bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded transition duration-200">
                        Resend Verification Email
                    </button>
                    
                    <a href="/logout" class="block w-full text-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded transition duration-200">
                        Logout
                    </a>
                </div>
                
                <p class="text-sm text-gray-500 mt-6">
                    Didn't receive the email? Check your spam folder or click the resend button above.
                </p>
            </div>
        </div>
    </div>

    <script>
        function resendVerification() {
            const btn = document.getElementById('resendBtn');
            const originalText = btn.textContent;
            
            btn.disabled = true;
            btn.textContent = 'Sending...';
            btn.classList.add('opacity-50', 'cursor-not-allowed');
            
            // TODO: Implement actual resend logic via API endpoint
            fetch('/api/resend-verification', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    btn.textContent = 'Email Sent!';
                    btn.classList.remove('bg-blue-500', 'hover:bg-blue-600');
                    btn.classList.add('bg-green-500');
                    
                    setTimeout(() => {
                        btn.textContent = originalText;
                        btn.classList.remove('bg-green-500', 'opacity-50', 'cursor-not-allowed');
                        btn.classList.add('bg-blue-500', 'hover:bg-blue-600');
                        btn.disabled = false;
                    }, 3000);
                } else {
                    btn.textContent = 'Failed to send';
                    btn.classList.remove('bg-blue-500', 'hover:bg-blue-600');
                    btn.classList.add('bg-red-500');
                    
                    setTimeout(() => {
                        btn.textContent = originalText;
                        btn.classList.remove('bg-red-500', 'opacity-50', 'cursor-not-allowed');
                        btn.classList.add('bg-blue-500', 'hover:bg-blue-600');
                        btn.disabled = false;
                    }, 3000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again later.');
                btn.textContent = originalText;
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
                btn.disabled = false;
            });
        }
    </script>
    
    <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
