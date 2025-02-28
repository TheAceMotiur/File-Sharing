<?php
// includes/EmailService.php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mailer;
    private $settings;

    public function __construct($settings) {
        $this->settings = $settings;
        $this->validateSettings();
        $this->mailer = new PHPMailer(true);
        
        // Configure SMTP settings
        $this->mailer->isSMTP();
        $this->mailer->Host = $settings['mail_host'];
        $this->mailer->Port = (int)$settings['mail_port'];
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $settings['mail_username'];
        $this->mailer->Password = $settings['mail_password'];
        $this->mailer->SMTPSecure = $settings['mail_encryption'] ?? PHPMailer::ENCRYPTION_STARTTLS;
    }

    public function sendVerificationEmail($email, $name, $pin) {
        try {
            $this->mailer->setFrom($this->settings['mail_from_address'], $this->settings['mail_from_name']);
            $this->mailer->addAddress($email, $name);
            $this->mailer->isHTML(true);
            
            $this->mailer->Subject = 'Verify Your Email Address';
            $this->mailer->Body = "
                <h2>Hello {$name},</h2>
                <p>Please use the following PIN to verify your email address:</p>
                <h1>{$pin}</h1>
                <p>If you did not create an account, please ignore this email.</p>
            ";

            return $this->mailer->send();
        } catch (Exception $e) {
            throw new Exception('Email could not be sent. Mailer Error: ' . $this->mailer->ErrorInfo);
        }
    }

    public function sendPasswordResetEmail($email, $name, $resetLink) {
        try {
            $this->mailer->setFrom($this->settings['mail_from_address'], $this->settings['mail_from_name']);
            $this->mailer->addAddress($email, $name);
            $this->mailer->isHTML(true);
            
            $this->mailer->Subject = 'Password Reset Request';
            $this->mailer->Body = "
                <h2>Hello {$name},</h2>
                <p>We received a request to reset your password. Click the link below to reset your password:</p>
                <a href='{$resetLink}'>Reset Password</a>
                <p>If you did not request a password reset, please ignore this email.</p>
                <p>Thank you,<br>FreeNetly Team</p>
            ";

            return $this->mailer->send();
        } catch (Exception $e) {
            throw new Exception('Email could not be sent. Mailer Error: ' . $this->mailer->ErrorInfo);
        }
    }

    private function validateSettings() {
        $requiredSettings = ['mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name'];
        foreach ($requiredSettings as $setting) {
            if (empty($this->settings[$setting])) {
                throw new Exception("Missing required email setting: $setting");
            }
        }
    }
}