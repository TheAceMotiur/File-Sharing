-- First, drop the foreign key constraint
ALTER TABLE file_uploads DROP FOREIGN KEY fk_folder_id;

-- Then remove the column
ALTER TABLE file_uploads DROP COLUMN folder_id;
