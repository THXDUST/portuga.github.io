-- Migration: Make user_id NOT NULL in ouvidoria table
-- Created: 2026-01-04
-- Description: Changes user_id from NULL to NOT NULL to enforce authentication requirement

-- First, update any existing NULL user_id records to a default system user
-- This prevents constraint violation errors
-- Note: You may need to create a system user first if one doesn't exist

-- Check if there are any NULL user_id records
DO $$
DECLARE
    null_count INTEGER;
BEGIN
    SELECT COUNT(*) INTO null_count FROM ouvidoria WHERE user_id IS NULL;
    
    IF null_count > 0 THEN
        RAISE NOTICE 'Found % records with NULL user_id. These need to be updated before making the column NOT NULL.', null_count;
        RAISE NOTICE 'Please update these records manually or delete them before running this migration.';
    ELSE
        RAISE NOTICE 'No NULL user_id records found. Safe to proceed.';
    END IF;
END $$;

-- Make user_id NOT NULL (only if no NULL values exist)
-- This will fail if there are NULL values, which is intentional
ALTER TABLE ouvidoria 
    ALTER COLUMN user_id SET NOT NULL;

-- Ensure the foreign key constraint exists
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints 
        WHERE constraint_name = 'ouvidoria_user_id_fkey' 
        AND table_name = 'ouvidoria'
    ) THEN
        ALTER TABLE ouvidoria 
            ADD CONSTRAINT ouvidoria_user_id_fkey 
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
    END IF;
END $$;

SELECT 'Migration completed: user_id is now NOT NULL in ouvidoria table' AS status;
