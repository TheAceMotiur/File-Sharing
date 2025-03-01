
CREATE TABLE IF NOT EXISTS `upload_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_size` bigint(20) NOT NULL DEFAULT 0,
  `total_chunks` int(11) NOT NULL DEFAULT 0,
  `uploaded_chunks` int(11) NOT NULL DEFAULT 0,
  `status` enum('in_progress','completed','failed','abandoned') NOT NULL DEFAULT 'in_progress',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp