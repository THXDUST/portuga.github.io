-- Migration: Add delivery_enabled field to menu_items table
-- Description: Adds a boolean field to control if a menu item is available for delivery
-- Date: 2026-01-02

-- Add delivery_enabled column to menu_items table
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'menu_items' AND column_name = 'delivery_enabled'
    ) THEN
        ALTER TABLE menu_items ADD COLUMN delivery_enabled BOOLEAN DEFAULT TRUE;
        COMMENT ON COLUMN menu_items.delivery_enabled IS 'Item disponível para entrega';
    END IF;
END $$;

-- Update existing records to be available for delivery by default
UPDATE menu_items SET delivery_enabled = TRUE WHERE delivery_enabled IS NULL;
