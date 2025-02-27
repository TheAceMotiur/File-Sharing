CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(255) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO site_settings (setting_key, setting_value) VALUES
('site_name', 'FilesWith'),
('site_description', 'File sharing platform'),
('contact_email', 'admin@example.com')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);