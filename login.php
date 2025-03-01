<?php
require_once __DIR__ . '/config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = "Both email and password are required";
    } else {
        try {
            // Include database configuration and get connection
            $db = getDBConnection();
            
            $stmt = $db->prepare("SELECT id, name, password, email_verified FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($user = $result->fetch_assoc()) {
                if (password_verify($password, $user['password'])) {
                    if (!$user['email_verified']) {
                        $_SESSION['user_id'] = $user['id']; // Set session for verification
                        header("Location: verify");
                        exit;
                    } else {
                        // Get admin status
                        $stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
                        $stmt->bind_param("i", $user['id']);
                        $stmt->execute();
                        $adminResult = $stmt->get_result()->fetch_assoc();

                        // Set session variables
                        session_regenerate_id(true); // Security best practice
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name']; 
                        $_SESSION['is_admin'] = $adminResult['is_admin'];

                        // Set session cookie parameters for 30 days
                        $params = session_get_cookie_params();
                        setcookie(session_name(), session_id(), [
                            'expires' => time() + (30 * 24 * 60 * 60),
                            'path' => $params['path'],
                            'domain' => $params['domain'],
                            'httponly' => true,
                            'secure' => false,
                            'samesite' => 'Lax'
                        ]);

                        if ($remember) {
                            // Generate remember token
                            $token = bin2hex(random_bytes(32));
                            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                            
                            $stmt = $db->prepare("UPDATE users SET remember_token = ?, remember_token_expires = ? WHERE id = ?");
                            $stmt->bind_param("ssi", $token, $expires, $user['id']);
                            $stmt->execute();
                            
                            setcookie('remember_token', $token, [
                                'expires' => time() + (30 * 24 * 60 * 60),
                                'path' => '/',
                                'secure' => true,
                                'httponly' => true,
                                'samesite' => 'Lax'
                            ]);
                        }

                        header("Location: dashboard");
                        exit;
                    }
                }
            }
            $error = "Invalid email or password";
        } catch (Exception $e) {
            $error = "Login failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - OneNetly</title>
    <link rel="icon" type="image/png" href="icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php include 'header.php'; ?>

    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white p-8 rounded-lg shadow-md w-96">
            <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Login</h2>
            
            <?php if (isset($_GET['registered'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    Registration successful! Please check your email to verify your account.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php 
                        $errorMessage = match($_GET['error']) {
                            'account_not_found' => 'Account not found. Please login again.',
                            default => 'An error occurred. Please try again.'
                        };
                        echo htmlspecialchars($errorMessage);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                    <input type="email" name="email" id="email" required
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div>
                    <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                    <input type="password" name="password" id="password" required
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="remember" id="remember" class="h-4 w-4 text-blue-600">
                    <label for="remember" class="ml-2 block text-gray-700 text-sm">Remember me</label>
                </div>

                <button type="submit"
                    class="w-full bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-600 focus:outline-none focus:shadow-outline">
                    Login
                </button>
            </form>

            <p class="mt-4 text-center text-gray-600">
                Don't have an account? 
                <a href="register" class="text-blue-500 hover:text-blue-700">Register here</a>
            </p>
            <p class="mt-2 text-center text-gray-600">
                <a href="forgot-password" class="text-blue-500 hover:text-blue-700">Forgot Password?</a>
            </p>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>