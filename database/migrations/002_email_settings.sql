CREATE TABLE IF NOT EXISTS email_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mail_host VARCHAR(255) NOT NULL,
    mail_port INT NOT NULL,
    mail_username VARCHAR(255) NOT NULL,
    mail_password VARCHAR(255) NOT NULL,
    mail_encryption VARCHAR(20) DEFAULT 'tls',
    mail_from_address VARCHAR(255) NOT NULL,
    mail_from_name VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO email_settings (
    mail_host, 
    mail_port, 
    mail_username, 
    mail_password, 
    mail_encryption, 
    mail_from_address, 
    mail_from_name
) VALUES (
    'smtp.mailtrap.io',
    2525,
    'your-username',
    'your-password',
    'tls',
    'noreply@example.com',
    'Your App'
);
