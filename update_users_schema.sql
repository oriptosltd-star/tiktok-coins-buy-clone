-- Add missing columns to users table for tracking
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS registration_ip VARCHAR(45) NULL,
ADD COLUMN IF NOT EXISTS last_login_ip VARCHAR(45) NULL,
ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL;

-- Add index for performance
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_registration_ip (registration_ip);
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_last_login_ip (last_login_ip);
