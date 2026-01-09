-- Add columns for local storage and sync tracking

-- Add dropbox_path column if it doesn't exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'file_uploads'
    AND COLUMN_NAME = 'dropbox_path'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE file_uploads ADD COLUMN dropbox_path TEXT AFTER folder_id',
    'SELECT "Column dropbox_path already exists, skipping..." AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add local_path column if it doesn't exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'file_uploads'
    AND COLUMN_NAME = 'local_path'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE file_uploads ADD COLUMN local_path TEXT AFTER dropbox_path',
    'SELECT "Column local_path already exists, skipping..." AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add storage_location column if it doesn't exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'file_uploads'
    AND COLUMN_NAME = 'storage_location'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE file_uploads ADD COLUMN storage_location ENUM(''local'', ''dropbox'', ''syncing'') DEFAULT ''local'' AFTER local_path',
    'SELECT "Column storage_location already exists, skipping..." AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add synced_at column if it doesn't exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'file_uploads'
    AND COLUMN_NAME = 'synced_at'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE file_uploads ADD COLUMN synced_at TIMESTAMP NULL AFTER storage_location',
    'SELECT "Column synced_at already exists, skipping..." AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
