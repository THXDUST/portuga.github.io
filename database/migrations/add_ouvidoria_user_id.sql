-- Migration: Add user_id to ouvidoria table
-- Created: 2026-01-02
-- Description: Adds user_id field to ouvidoria table to track which user created the conversation

USE portuga_db;

-- Add user_id column to ouvidoria table if it doesn't exist
ALTER TABLE ouvidoria 
ADD COLUMN IF NOT EXISTS user_id INT NULL COMMENT 'User who created the ouvidoria message' AFTER id,
ADD INDEX IF NOT EXISTS idx_user_id (user_id);

-- Add foreign key constraint if it doesn't exist
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = 'portuga_db' 
    AND TABLE_NAME = 'ouvidoria' 
    AND CONSTRAINT_NAME = 'fk_ouvidoria_user'
);

SET @query = IF(@fk_exists = 0, 
    'ALTER TABLE ouvidoria ADD CONSTRAINT fk_ouvidoria_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT "Foreign key already exists"'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migration completed: user_id added to ouvidoria table' AS status;
