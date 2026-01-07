-- Add columns for local storage and sync tracking
ALTER TABLE file_uploads 
ADD COLUMN IF NOT EXISTS local_path TEXT AFTER dropbox_path,
ADD COLUMN IF NOT EXISTS storage_location ENUM('local', 'dropbox', 'syncing') DEFAULT 'local' AFTER local_path,
ADD COLUMN IF NOT EXISTS synced_at TIMESTAMP NULL AFTER storage_location;
