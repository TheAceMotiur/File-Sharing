-- Create users table if it doesn't exist
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add admin column
ALTER TABLE users
ADD COLUMN IF NOT EXISTS is_admin BOOLEAN DEFAULT FALSE;

-- Create email index
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);

-- Add verification and reset columns
ALTER TABLE users
ADD COLUMN IF NOT EXISTS email_verified BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) NULL,
ADD COLUMN IF NOT EXISTS reset_token_expires TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS verification_pin VARCHAR(6) NULL,
ADD COLUMN IF NOT EXISTS remember_token VARCHAR(64) NULL,
ADD COLUMN IF NOT EXISTS remember_token_expires TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN premium TINYINT(1) DEFAULT 0;
 