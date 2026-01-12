-- Migration: Add delivery address fields to orders table
-- Date: 2026-01-12
-- Description: Add complete address fields for delivery orders and fix waiter_id index

-- Fix waiter_id index (move to correct position near column definition)
-- This index was being created too late in setup.sql, causing errors
-- The waiter_id column is added earlier in setup.sql, so the index should be near it
CREATE INDEX IF NOT EXISTS idx_orders_waiter ON orders(waiter_id);

-- Add delivery address fields for complete address information
ALTER TABLE orders ADD COLUMN IF NOT EXISTS phone_number VARCHAR(20);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS cep VARCHAR(10);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS address_street VARCHAR(255);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS address_number VARCHAR(20);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS address_complement VARCHAR(100);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS address_neighborhood VARCHAR(100);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS address_city VARCHAR(100);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS address_state VARCHAR(2);

-- Add comments for documentation
COMMENT ON COLUMN orders.phone_number IS 'Phone number for delivery contact';
COMMENT ON COLUMN orders.cep IS 'CEP (postal code) for delivery address';
COMMENT ON COLUMN orders.address_street IS 'Street name for delivery address';
COMMENT ON COLUMN orders.address_number IS 'Street number for delivery address';
COMMENT ON COLUMN orders.address_complement IS 'Complement/reference for delivery address';
COMMENT ON COLUMN orders.address_neighborhood IS 'Neighborhood for delivery address';
COMMENT ON COLUMN orders.address_city IS 'City for delivery address';
COMMENT ON COLUMN orders.address_state IS 'State abbreviation (2 letters) for delivery address';
