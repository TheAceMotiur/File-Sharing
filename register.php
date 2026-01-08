<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ads.php'; // Include ads functionality

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Verify reCAPTCHA
    if (!isset($_POST['g-recaptcha-response'])) {
        $error = "Please complete the reCAPTCHA";
    } else {
        $recaptchaSecret = RECAPTCHA_SECRET_KEY;
        $recaptchaResponse = $_POST['g-recaptcha-response'];
        
        $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$recaptchaSecret}&response={$recaptchaResponse}");
        $captchaSuccess = json_decode($verify);
        
        if (!$captchaSuccess->success) {
            $error = "reCAPTCHA verification failed";
        } else if (empty($name) || empty($email) || empty($password)) {
            $error = "All fields are required";
        } else {
            try {
                // Include database configuration
                require_once __DIR__ . '/includes/EmailService.php';
                $db = getDBConnection();
                
                // Check if email already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error = "Email already registered";
                } else {
                    // Generate verification pin
                    $pin = sprintf("%06d", mt_rand(0, 999999));
                    
                    // Hash password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert new user
                    $stmt = $db->prepare("INSERT INTO users (name, email, password, verification_pin) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $name, $email, $hashedPassword, $pin);
                    
                    if ($stmt->execute()) {
                        // Get the new user's ID
                        $userId = $db->insert_id;
                        
                        // Set session variables
                        $_SESSION['user_id'] = $userId;
                        $_SESSION['user_name'] = $name; // Add this line to set the user's name

                        // Get email settings and send verification email
                        $result = $db->query("SELECT * FROM email_settings LIMIT 1");
                        $emailSettings = $result->fetch_assoc();

                        // Send verification email
                        $emailService = new EmailService($emailSettings);
                        try {
                            $emailService->sendVerificationEmail($email, $name, $pin);
                            header("Location: verify");
                            exit;
                        } catch (Exception $e) {
                            $error = "Registration successful but verification email failed to send: " . $e->getMessage();
                        }
                    } else {
                        $error = "Registration failed";
                    }
                }
            } catch (Exception $e) {
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo getSiteName(); ?></title>
    <link rel="icon" type="image/png" href="icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body class="bg-gray-50">
    <?php include 'header.php'; ?>

    <?php displayHorizontalAd(); // Top ad banner ?>

    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white p-8 rounded-lg shadow-md w-96">
            <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Create Account</h2>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label for="name" class="block text-gray-700 text-sm font-bold mb-2">Name</label>
                    <input type="text" name="name" id="name" required
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"
                        value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                </div>

                <div>
                    <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                    <input type="email" name="email" id="email" required
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div>
                    <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                    <input type="password" name="password" id="password" required
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"
                        minlength="6">
                </div>

                <div class="flex justify-center">
                    <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
                </div>

                <button type="submit"
                    class="w-full bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-600 focus:outline-none focus:shadow-outline">
                    Register
                </button>
            </form>

            <p class="mt-4 text-center text-gray-600">
                Already have an account? 
                <a href="login" class="text-blue-500 hover:text-blue-700">Login here</a>
            </p>
        </div>
    </div>

    <?php displayHomepageFeaturedAd(); // Bottom ad display ?>

    <?php include 'footer.php'; ?>
</body>
</html>