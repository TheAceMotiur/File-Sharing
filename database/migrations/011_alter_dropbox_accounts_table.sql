ALTER TABLE `dropbox_accounts` 
ADD COLUMN `is_full` TINYINT(1) NOT NULL DEFAULT 0,
ADD COLUMN `last_space_check` TIMESTAMP NULL,
ADD COLUMN `available_space` BIGINT NULL,
ADD COLUMN `total_space` BIGINT NULL;
