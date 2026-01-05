-- Add BLOB column for storing dish images directly in database
ALTER TABLE menu_items ADD COLUMN IF NOT EXISTS image_data BYTEA;
ALTER TABLE menu_items ADD COLUMN IF NOT EXISTS image_mime_type VARCHAR(100);

-- Add index for faster queries
CREATE INDEX IF NOT EXISTS idx_menu_items_has_image ON menu_items((image_data IS NOT NULL));
