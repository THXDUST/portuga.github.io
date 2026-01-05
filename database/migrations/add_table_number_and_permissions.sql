-- Migration: Add table_number field to orders table
-- Run this if you already have an existing database

-- Add table_number column to orders table
ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS table_number INT NULL,
ADD INDEX IF NOT EXISTS idx_table (table_number);

-- Add missing permissions (use ON CONFLICT for PostgreSQL)
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

-- Assign all permissions to Admin role (role_id = 1)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions
ON CONFLICT (role_id, permission_id) DO NOTHING;

-- Assign appropriate permissions to Atendente role (role_id = 3)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions 
WHERE name IN (
    'admin_panel_access',
    'order_view',
    'order_create', 
    'order_update',
    'orders_status',
    'menu_view'
)
ON CONFLICT (role_id, permission_id) DO NOTHING;
