-- Add sync_status column for tracking file sync status

-- Add sync_status column if it doesn't exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'file_uploads'
    AND COLUMN_NAME = 'sync_status'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE file_uploads ADD COLUMN sync_status ENUM(''pending'', ''syncing'', ''synced'', ''failed'') DEFAULT ''pending'' AFTER storage_location',
    'SELECT "Column sync_status already exists, skipping..." AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add dropbox_account_id column if it doesn't exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'file_uploads'
    AND COLUMN_NAME = 'dropbox_account_id'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE file_uploads ADD COLUMN dropbox_account_id INT DEFAULT NULL AFTER sync_status',
    'SELECT "Column dropbox_account_id already exists, skipping..." AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add used_storage column to dropbox_accounts if it doesn't exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dropbox_accounts'
    AND COLUMN_NAME = 'used_storage'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE dropbox_accounts ADD COLUMN used_storage BIGINT DEFAULT 0 AFTER refresh_token',
    'SELECT "Column used_storage already exists, skipping..." AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
