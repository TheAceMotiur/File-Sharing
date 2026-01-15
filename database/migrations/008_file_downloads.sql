CREATE TABLE IF NOT EXISTS file_downloads (
    file_id VARCHAR(255),
    download_count INT DEFAULT 0,
    PRIMARY KEY (file_id)
); 