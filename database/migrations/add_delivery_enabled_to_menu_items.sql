-- Migration: Add delivery_enabled field to menu_items table
-- Description: Adds a boolean field to control if a menu item is available for delivery
-- Date: 2026-01-02

USE portuga_db;

-- Add delivery_enabled column to menu_items table
ALTER TABLE menu_items 
ADD COLUMN delivery_enabled BOOLEAN DEFAULT TRUE 
COMMENT 'Item disponível para entrega';

-- Update existing records to be available for delivery by default
UPDATE menu_items SET delivery_enabled = TRUE WHERE delivery_enabled IS NULL;
