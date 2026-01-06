-- Add TEXT column for storing Base64-encoded dish images in database
-- Using TEXT instead of BYTEA because application stores Base64 strings
ALTER TABLE menu_items ADD COLUMN IF NOT EXISTS image_data TEXT;
ALTER TABLE menu_items ADD COLUMN IF NOT EXISTS image_mime_type VARCHAR(100);

-- Add partial index for faster queries when image exists
CREATE INDEX IF NOT EXISTS idx_menu_items_has_image ON menu_items(id) WHERE image_data IS NOT NULL;

-- Add comments explaining the storage format
COMMENT ON COLUMN menu_items.image_data IS 'Base64-encoded image data (JPEG format after compression)';
COMMENT ON COLUMN menu_items.image_mime_type IS 'MIME type of the stored image (typically image/jpeg)';
