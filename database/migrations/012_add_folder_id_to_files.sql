-- First ensure the folders table exists (from migration 010)
-- Then add folder_id to file_uploads table

-- Check if folder_id column already exists, if not add it
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'file_uploads'
    AND COLUMN_NAME = 'folder_id'
);

-- Add column if it doesn't exist
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE file_uploads ADD COLUMN folder_id INT DEFAULT NULL AFTER uploaded_by',
    'SELECT "Column folder_id already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key constraint if column was just added
SET @fk_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'file_uploads'
    AND CONSTRAINT_NAME = 'fk_file_uploads_folder_id'
);

SET @sql = IF(@fk_exists = 0 AND @col_exists = 0,
    'ALTER TABLE file_uploads ADD CONSTRAINT fk_file_uploads_folder_id FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL',
    'SELECT "Foreign key already exists or not needed" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add indexes for better performance
SET @idx_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'file_uploads'
    AND INDEX_NAME = 'idx_folder_id'
);

SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_folder_id ON file_uploads(folder_id)',
    'SELECT "Index idx_folder_id already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'file_uploads'
    AND INDEX_NAME = 'idx_uploaded_by_folder'
);

SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_uploaded_by_folder ON file_uploads(uploaded_by, folder_id)',
    'SELECT "Index idx_uploaded_by_folder already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
