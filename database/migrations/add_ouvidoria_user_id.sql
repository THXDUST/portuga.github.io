-- Migration: Add user_id to ouvidoria table
-- Created: 2026-01-02
-- Description: Adds user_id field to ouvidoria table to track which user created the conversation

-- Add user_id column to ouvidoria table if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'ouvidoria' AND column_name = 'user_id'
    ) THEN
        ALTER TABLE ouvidoria ADD COLUMN user_id INT NULL;
        COMMENT ON COLUMN ouvidoria.user_id IS 'User who created the ouvidoria message';
    END IF;
END $$;

-- Create index if it doesn't exist
CREATE INDEX IF NOT EXISTS idx_user_id ON ouvidoria(user_id);

-- Add foreign key constraint if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints 
        WHERE constraint_name = 'fk_ouvidoria_user' 
        AND table_name = 'ouvidoria'
    ) THEN
        ALTER TABLE ouvidoria 
            ADD CONSTRAINT fk_ouvidoria_user 
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
    END IF;
END $$;

SELECT 'Migration completed: user_id added to ouvidoria table' AS status;
