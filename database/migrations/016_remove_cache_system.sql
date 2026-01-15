-- Remove cache-related cron job
DELETE FROM cron_jobs WHERE name = 'Cleanup Cache';

-- Remove cache-related columns from file_downloads table
ALTER TABLE file_downloads 
DROP COLUMN IF EXISTS last_cached,
DROP COLUMN IF EXISTS cache_path;
