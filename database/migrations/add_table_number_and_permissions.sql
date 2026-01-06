-- Migration: Add table_number field to orders table
-- Run this if you already have an existing database

-- Add table_number column to orders table
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'orders' AND column_name = 'table_number'
    ) THEN
        ALTER TABLE orders ADD COLUMN table_number INT NULL;
    END IF;
END $$;

-- Create index if it doesn't exist
CREATE INDEX IF NOT EXISTS idx_table ON orders(table_number);

-- Add missing permissions (use ON CONFLICT for PostgreSQL idempotency)
INSERT INTO permissions (name, description, resource, action) VALUES
('admin_panel_access', 'Acesso ao painel administrativo', 'admin', 'access'),
('permissions_management', 'Gerenciar permissões', 'permissions', 'manage'),
('roles_management', 'Gerenciar cargos/roles', 'roles', 'manage'),
('users_management', 'Gerenciar usuários', 'users', 'manage'),
('financial_stats', 'Acesso às estatísticas financeiras', 'reports', 'financial'),
('settings_access', 'Acesso às configurações do sistema', 'settings', 'access'),
('resumes_access', 'Acesso aos currículos', 'resumes', 'access'),
('ouvidoria_access', 'Acesso à ouvidoria', 'ouvidoria', 'access'),
('orders_status', 'Mudança de estado dos pedidos', 'orders', 'status'),
('reports_access', 'Acesso aos relatórios', 'reports', 'access')
ON CONFLICT (name) DO NOTHING;

-- Assign all permissions to Admin role using subquery
-- This ensures the migration works even if role IDs are different
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'Admin'
ON CONFLICT (role_id, permission_id) DO NOTHING;

-- Assign appropriate permissions to Atendente role using subquery
-- Only assigns permissions that should be available to Atendente
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'Atendente' 
  AND p.name IN (
    'admin_panel_access',
    'order_view',
    'order_create', 
    'order_update',
    'orders_status',
    'menu_view'
  )
ON CONFLICT (role_id, permission_id) DO NOTHING;
