-- Fix image_data column type: Change from BYTEA to TEXT
-- The application stores Base64-encoded strings, not raw binary data
-- BYTEA columns would double-encode the data causing retrieval issues

-- Drop existing index if it exists
DROP INDEX IF EXISTS idx_menu_items_has_image;

-- Change column type from BYTEA to TEXT
ALTER TABLE menu_items 
  ALTER COLUMN image_data TYPE TEXT USING image_data::TEXT;

-- Recreate the index
CREATE INDEX IF NOT EXISTS idx_menu_items_has_image 
  ON menu_items(id) 
  WHERE image_data IS NOT NULL;

-- Add comment explaining the storage format
COMMENT ON COLUMN menu_items.image_data IS 'Base64-encoded image data (JPEG format)';
COMMENT ON COLUMN menu_items.image_mime_type IS 'MIME type of the stored image (e.g., image/jpeg)';
