CREATE TABLE IF NOT EXISTS file_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_id VARCHAR(255) NOT NULL UNIQUE,
    file_name VARCHAR(255) NOT NULL,
    size BIGINT UNSIGNED NOT NULL, -- Changed to BIGINT UNSIGNED for larger files
    upload_status ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    dropbox_path TEXT,
    uploaded_by INT,
    dropbox_account_id INT,
    last_download_at TIMESTAMP NULL,
    expires_at TIMESTAMP NOT NULL DEFAULT (CURRENT_TIMESTAMP + INTERVAL 180 DAY),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (dropbox_account_id) REFERENCES dropbox_accounts(id)
);
