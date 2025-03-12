-- Check if the foreign key constraint exists before dropping it
SET @constraint_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'file_uploads'
    AND CONSTRAINT_NAME = 'fk_folder_id'
);

SET @sql = IF(@constraint_exists > 0,
    'ALTER TABLE file_uploads DROP FOREIGN KEY fk_folder_id',
    'SELECT "Foreign key constraint does not exist, skipping..." AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if the column exists before trying to drop it
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'file_uploads'
    AND COLUMN_NAME = 'folder_id'
);

SET @sql = IF(@column_exists > 0,
    'ALTER TABLE file_uploads DROP COLUMN folder_id',
    'SELECT "Column folder_id does not exist, skipping..." AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
