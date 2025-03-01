CREATE TABLE IF NOT EXISTS `upload_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `total_size` bigint(20) NOT NULL,
  `total_chunks` int(11) NOT NULL,
  `uploaded_chunks` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL,
  `status` enum('in_progress','completed','failed') NOT NULL DEFAULT 'in_progress',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `file_id` (`file_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
