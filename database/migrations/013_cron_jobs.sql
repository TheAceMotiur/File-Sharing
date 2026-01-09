CREATE TABLE IF NOT EXISTS cron_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    command VARCHAR(500) NOT NULL,
    schedule VARCHAR(100) NOT NULL COMMENT 'Cron format: * * * * *',
    is_active TINYINT(1) DEFAULT 1,
    last_run_at DATETIME NULL,
    last_run_status ENUM('success', 'failed', 'running') NULL,
    last_run_output TEXT NULL,
    run_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_is_active (is_active),
    INDEX idx_last_run (last_run_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert existing cron jobs
INSERT INTO cron_jobs (name, description, command, schedule, is_active) VALUES
('Sync to Dropbox', 'Syncs pending files to Dropbox accounts automatically', 'php cron/sync_to_dropbox.php', '* * * * *', 1),
('Cleanup Local Files', 'Removes failed syncs and orphaned local files', 'php cron/cleanup_local_files.php', '0 * * * *', 1),
('Refresh Dropbox Tokens', 'Refreshes expired Dropbox access tokens', 'php cron/refresh_dropbox_tokens.php', '0 */6 * * *', 1),
('Cleanup Old Files', 'Removes files older than retention period', 'php cron/cleanup.php', '0 2 * * *', 1),
('Cleanup Upload Chunks', 'Removes incomplete upload chunks', 'php cron/cleanup_chunks.php', '0 3 * * *', 1),
('Cleanup Cache', 'Removes old cached files and manages cache size', 'php cron/cleanup_cache.php', '0 3 * * *', 1);
