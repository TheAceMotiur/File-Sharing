<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;

/**
 * Auth Controller
 * Handles authentication related actions
 */
class AuthController extends Controller
{
    private $userModel;
    
    public function __construct()
    {
        $this->userModel = new User();
    }
    
    /**
     * Show login form
     */
    public function login()
    {
        $data = [
            'title' => 'Login',
            'error' => null,
            'registered' => $this->get('registered'),
            'errorType' => $this->get('error')
        ];
        
        if ($this->isPost()) {
            $email = trim($this->post('email'));
            $password = $this->post('password');
            $remember = $this->post('remember') !== null;
            
            if (empty($email) || empty($password)) {
                $data['error'] = "Both email and password are required";
            } else {
                $user = $this->userModel->authenticate($email, $password);
                
                if ($user) {
                    if (!$user['email_verified']) {
                        $_SESSION['user_id'] = $user['id'];
                        $this->redirect('/verify');
                    }
                    
                    // Set session variables
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['is_admin'] = $user['is_admin'];
                    $_SESSION['premium'] = $user['premium'];
                    $_SESSION['user_premium'] = $user['premium'];
                    
                    // Set session cookie for 30 days
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
                        $token = bin2hex(random_bytes(32));
                        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                        
                        $this->userModel->setRememberToken($user['id'], $token, $expires);
                        
                        setcookie('remember_token', $token, [
                            'expires' => time() + (30 * 24 * 60 * 60),
                            'path' => '/',
                            'secure' => true,
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]);
                    }
                    
                    $this->redirect('/dashboard');
                } else {
                    $data['error'] = "Invalid email or password";
                }
            }
        }
        
        $this->view('auth/login', $data);
    }
    
    /**
     * Show registration form
     */
    public function register()
    {
        $data = [
            'title' => 'Register',
            'error' => null,
            'name' => '',
            'email' => ''
        ];
        
        if ($this->isPost()) {
            $name = trim($this->post('name'));
            $email = trim($this->post('email'));
            $password = $this->post('password');
            $confirmPassword = $this->post('confirm_password');
            
            $data['name'] = $name;
            $data['email'] = $email;
            
            // Validation
            if (empty($name) || empty($email) || empty($password)) {
                $data['error'] = "All fields are required";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $data['error'] = "Invalid email address";
            } elseif (strlen($password) < 6) {
                $data['error'] = "Password must be at least 6 characters";
            } elseif ($password !== $confirmPassword) {
                $data['error'] = "Passwords do not match";
            } elseif ($this->userModel->findByEmail($email)) {
                $data['error'] = "Email already registered";
            } else {
                // Create user
                $userId = $this->userModel->register([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password
                ]);
                
                if ($userId) {
                    // TODO: Send verification email
                    $this->redirect('/login?registered=1');
                } else {
                    $data['error'] = "Registration failed. Please try again.";
                }
            }
        }
        
        $this->view('auth/register', $data);
    }
    
    /**
     * Logout user
     */
    public function logout()
    {
        // Clear remember token
        if (isset($_SESSION['user_id'])) {
            $this->userModel->setRememberToken($_SESSION['user_id'], '', '');
        }
        
        // Destroy session
        session_destroy();
        
        // Clear remember cookie
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        $this->redirect('/login');
    }
    
    /**
     * Show forgot password form
     */
    public function forgotPassword()
    {
        $data = [
            'title' => 'Forgot Password',
            'error' => null,
            'success' => null
        ];
        
        if ($this->isPost()) {
            $email = trim($this->post('email'));
            
            if (empty($email)) {
                $data['error'] = "Email is required";
            } else {
                $user = $this->userModel->findByEmail($email);
                
                if ($user) {
                    // TODO: Generate reset token and send email
                    $data['success'] = "Password reset link sent to your email";
                } else {
                    // Don't reveal if email exists
                    $data['success'] = "If that email is registered, you will receive a reset link";
                }
            }
        }
        
        $this->view('auth/forgot-password', $data);
    }
    
    /**
     * Reset password
     */
    public function resetPassword()
    {
        $data = [
            'title' => 'Reset Password',
            'error' => null,
            'success' => null,
            'token' => $this->get('token', '')
        ];
        
        if ($this->isPost()) {
            $token = $this->post('token');
            $password = $this->post('password');
            $confirmPassword = $this->post('confirm_password');
            
            if (empty($token)) {
                $data['error'] = "Invalid reset token";
            } elseif (empty($password) || empty($confirmPassword)) {
                $data['error'] = "All fields are required";
            } elseif ($password !== $confirmPassword) {
                $data['error'] = "Passwords do not match";
            } elseif (strlen($password) < 8) {
                $data['error'] = "Password must be at least 8 characters";
            } else {
                // Verify token and update password
                $db = getDBConnection();
                $stmt = $db->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
                $stmt->bind_param("s", $token);
                $stmt->execute();
                $reset = $stmt->get_result()->fetch_assoc();
                
                if ($reset) {
                    // Update password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
                    $stmt->bind_param("ss", $hashedPassword, $reset['email']);
                    
                    if ($stmt->execute()) {
                        // Delete used token
                        $stmt = $db->prepare("DELETE FROM password_resets WHERE token = ?");
                        $stmt->bind_param("s", $token);
                        $stmt->execute();
                        
                        $data['success'] = "Password reset successfully. You can now login.";
                    } else {
                        $data['error'] = "Failed to reset password";
                    }
                } else {
                    $data['error'] = "Invalid or expired reset token";
                }
            }
        }
        
        $this->view('auth/reset-password', $data);
    }
    
    /**
     * Show verify email page
     */
    public function verify()
    {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
        
        $data = [
            'title' => 'Verify Email',
            'redirect' => $this->get('redirect', '/dashboard')
        ];
        
        $this->view('auth/verify', $data);
    }
}
