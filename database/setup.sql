-- Database Setup Script for Portuga Authentication System
-- PostgreSQL 12+ compatible

-- Note: Database should be created externally (e.g., via Render.com or CREATE DATABASE command)
-- This script assumes the database already exists and you're connected to it

-- Table: users
-- Stores user account information
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(512) NULL,
    oauth_provider VARCHAR(20) DEFAULT 'none' CHECK (oauth_provider IN ('none', 'google', 'facebook', 'instagram')),
    oauth_id VARCHAR(255) NULL,
    email_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE
);

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_oauth ON users(oauth_provider, oauth_id);
CREATE INDEX IF NOT EXISTS idx_users_verification_token ON users(verification_token);

-- Trigger for updated_at on users table
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Table: login_attempts
-- Tracks login attempts for rate limiting
CREATE TABLE IF NOT EXISTS login_attempts (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE
);

CREATE INDEX IF NOT EXISTS idx_login_attempts_email_time ON login_attempts(email, attempted_at);
CREATE INDEX IF NOT EXISTS idx_login_attempts_ip_time ON login_attempts(ip_address, attempted_at);

-- Table: sessions
-- Manages active user sessions
CREATE TABLE IF NOT EXISTS sessions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_sessions_token ON sessions(session_token);
CREATE INDEX IF NOT EXISTS idx_sessions_user_id ON sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_expires ON sessions(expires_at);

-- Table: password_resets
-- Manages password reset tokens
CREATE TABLE IF NOT EXISTS password_resets (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    reset_token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE
);

CREATE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets(reset_token);
CREATE INDEX IF NOT EXISTS idx_password_resets_email ON password_resets(email);
CREATE INDEX IF NOT EXISTS idx_password_resets_expires ON password_resets(expires_at);

-- ============================================
-- ADMIN PANEL TABLES
-- ============================================

-- Table: roles
-- Stores user roles (Admin, Gerente, Atendente, Cozinha, Entregador)
CREATE TABLE IF NOT EXISTS roles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_roles_name ON roles(name);

CREATE TRIGGER roles_updated_at BEFORE UPDATE ON roles
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Table: permissions
-- Stores granular permissions
CREATE TABLE IF NOT EXISTS permissions (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    resource VARCHAR(50) NOT NULL,
    action VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_permissions_resource ON permissions(resource);
CREATE INDEX IF NOT EXISTS idx_permissions_action ON permissions(action);

-- Table: role_permissions
-- Maps permissions to roles
CREATE TABLE IF NOT EXISTS role_permissions (
    id SERIAL PRIMARY KEY,
    role_id INTEGER NOT NULL,
    permission_id INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE (role_id, permission_id)
);

CREATE INDEX IF NOT EXISTS idx_role_permissions_role ON role_permissions(role_id);
CREATE INDEX IF NOT EXISTS idx_role_permissions_permission ON role_permissions(permission_id);

-- Table: user_roles
-- Maps roles to users
CREATE TABLE IF NOT EXISTS user_roles (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    role_id INTEGER NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INTEGER NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE (user_id, role_id)
);

CREATE INDEX IF NOT EXISTS idx_user_roles_user ON user_roles(user_id);
CREATE INDEX IF NOT EXISTS idx_user_roles_role ON user_roles(role_id);

-- Table: menu_groups
-- Groups for menu organization (Pizzas → Salgadas, Doces)
CREATE TABLE IF NOT EXISTS menu_groups (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    parent_id INTEGER NULL,
    display_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES menu_groups(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_menu_groups_parent ON menu_groups(parent_id);
CREATE INDEX IF NOT EXISTS idx_menu_groups_order ON menu_groups(display_order);
CREATE INDEX IF NOT EXISTS idx_menu_groups_active ON menu_groups(is_active);

CREATE TRIGGER menu_groups_updated_at BEFORE UPDATE ON menu_groups
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Table: menu_items
-- Items in the menu
CREATE TABLE IF NOT EXISTS menu_items (
    id SERIAL PRIMARY KEY,
    group_id INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    image_url VARCHAR(512),
    ingredients TEXT,
    is_available BOOLEAN DEFAULT TRUE,
    display_order INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES menu_groups(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_menu_items_group ON menu_items(group_id);
CREATE INDEX IF NOT EXISTS idx_menu_items_available ON menu_items(is_available);
CREATE INDEX IF NOT EXISTS idx_menu_items_order ON menu_items(display_order);

CREATE TRIGGER menu_items_updated_at BEFORE UPDATE ON menu_items
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Table: orders
-- Enhanced order management
CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NULL,
    order_number VARCHAR(20) UNIQUE NOT NULL,
    table_number INTEGER NULL CHECK (table_number > 0),
    status VARCHAR(20) DEFAULT 'recebido' CHECK (status IN ('recebido', 'em_andamento', 'finalizado', 'cancelado')),
    order_type VARCHAR(10) NOT NULL CHECK (order_type IN ('viagem', 'local')),
    payment_method VARCHAR(20) NOT NULL CHECK (payment_method IN ('dinheiro', 'cartao_debito', 'cartao_credito', 'pix')),
    change_for DECIMAL(10, 2) NULL,
    delivery_address TEXT NULL,
    delivery_distance DECIMAL(5, 2) NULL,
    delivery_fee DECIMAL(10, 2) DEFAULT 0.00,
    pickup_time TIMESTAMP NULL,
    production_start_time TIMESTAMP NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
CREATE INDEX IF NOT EXISTS idx_orders_order_number ON orders(order_number);
CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at);
CREATE INDEX IF NOT EXISTS idx_orders_user ON orders(user_id);
CREATE INDEX IF NOT EXISTS idx_orders_table ON orders(table_number);

CREATE TRIGGER orders_updated_at BEFORE UPDATE ON orders
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Table: order_items
-- Items in each order
CREATE TABLE IF NOT EXISTS order_items (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL,
    menu_item_id INTEGER NULL,
    item_name VARCHAR(255) NOT NULL,
    item_price DECIMAL(10, 2) NOT NULL,
    quantity INTEGER NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    notes TEXT,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items(order_id);
CREATE INDEX IF NOT EXISTS idx_order_items_menu_item ON order_items(menu_item_id);

-- Table: order_notes
-- Additional notes/updates on orders
CREATE TABLE IF NOT EXISTS order_notes (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL,
    user_id INTEGER NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_order_notes_order ON order_notes(order_id);
CREATE INDEX IF NOT EXISTS idx_order_notes_created ON order_notes(created_at);

-- Table: restaurant_settings
-- General restaurant configuration
CREATE TABLE IF NOT EXISTS restaurant_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(10) DEFAULT 'string' CHECK (setting_type IN ('string', 'number', 'boolean', 'json')),
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INTEGER NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_restaurant_settings_key ON restaurant_settings(setting_key);

CREATE TRIGGER restaurant_settings_updated_at BEFORE UPDATE ON restaurant_settings
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Table: reports
-- Saved reports
CREATE TABLE IF NOT EXISTS reports (
    id SERIAL PRIMARY KEY,
    report_type VARCHAR(20) NOT NULL CHECK (report_type IN ('faturamento', 'produtos', 'fluxo_clientes', 'notas')),
    report_name VARCHAR(255) NOT NULL,
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    data JSONB,
    created_by INTEGER NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_reports_type ON reports(report_type);
CREATE INDEX IF NOT EXISTS idx_reports_dates ON reports(date_from, date_to);
CREATE INDEX IF NOT EXISTS idx_reports_created ON reports(created_at);

-- Table: resumes
-- Job applications
CREATE TABLE IF NOT EXISTS resumes (
    id SERIAL PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    desired_position VARCHAR(100) NOT NULL,
    resume_file_path VARCHAR(512),
    cover_letter TEXT,
    status VARCHAR(20) DEFAULT 'em_analise' CHECK (status IN ('em_analise', 'aprovado', 'rejeitado')),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INTEGER NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_resumes_status ON resumes(status);
CREATE INDEX IF NOT EXISTS idx_resumes_email ON resumes(email);
CREATE INDEX IF NOT EXISTS idx_resumes_created ON resumes(created_at);

CREATE TRIGGER resumes_updated_at BEFORE UPDATE ON resumes
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Table: ouvidoria
-- Customer support messages
CREATE TABLE IF NOT EXISTS ouvidoria (
    id SERIAL PRIMARY KEY,
    protocol_number VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    image_path VARCHAR(512) NULL,
    status VARCHAR(20) DEFAULT 'pendente' CHECK (status IN ('pendente', 'em_atendimento', 'resolvido')),
    response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_by INTEGER NULL,
    FOREIGN KEY (responded_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_ouvidoria_protocol ON ouvidoria(protocol_number);
CREATE INDEX IF NOT EXISTS idx_ouvidoria_status ON ouvidoria(status);
CREATE INDEX IF NOT EXISTS idx_ouvidoria_created ON ouvidoria(created_at);

CREATE TRIGGER ouvidoria_updated_at BEFORE UPDATE ON ouvidoria
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Table: maintenance_mode
-- System maintenance configuration
CREATE TABLE IF NOT EXISTS maintenance_mode (
    id SERIAL PRIMARY KEY,
    is_active BOOLEAN DEFAULT FALSE,
    restrict_all BOOLEAN DEFAULT FALSE,
    restrict_orders BOOLEAN DEFAULT FALSE,
    restrict_menu BOOLEAN DEFAULT FALSE,
    custom_message TEXT,
    activated_at TIMESTAMP NULL,
    activated_by INTEGER NULL,
    deactivated_at TIMESTAMP NULL,
    FOREIGN KEY (activated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_maintenance_mode_active ON maintenance_mode(is_active);

-- Table: employee_schedule
-- Employee work schedule
CREATE TABLE IF NOT EXISTS employee_schedule (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    role_function VARCHAR(100) NOT NULL,
    schedule_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    service_point_id INTEGER NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_employee_schedule_user ON employee_schedule(user_id);
CREATE INDEX IF NOT EXISTS idx_employee_schedule_date ON employee_schedule(schedule_date);
CREATE INDEX IF NOT EXISTS idx_employee_schedule_service_point ON employee_schedule(service_point_id);

CREATE TRIGGER employee_schedule_updated_at BEFORE UPDATE ON employee_schedule
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Table: service_points
-- Strategic service points (areas, tables, counters)
CREATE TABLE IF NOT EXISTS service_points (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    point_type VARCHAR(10) NOT NULL CHECK (point_type IN ('mesa', 'balcao', 'area', 'outro')),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_service_points_type ON service_points(point_type);
CREATE INDEX IF NOT EXISTS idx_service_points_active ON service_points(is_active);

CREATE TRIGGER service_points_updated_at BEFORE UPDATE ON service_points
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Table: admin_logs
-- Log all administrative actions
CREATE TABLE IF NOT EXISTS admin_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NULL,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50) NOT NULL,
    resource_id INTEGER NULL,
    details JSONB,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_admin_logs_user ON admin_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_admin_logs_action ON admin_logs(action);
CREATE INDEX IF NOT EXISTS idx_admin_logs_resource ON admin_logs(resource_type, resource_id);
CREATE INDEX IF NOT EXISTS idx_admin_logs_created ON admin_logs(created_at);

-- ============================================
-- INITIAL DATA
-- ============================================

-- Insert default roles
INSERT INTO roles (name, description) VALUES
('Admin', 'Administrador com acesso total ao sistema'),
('Gerente', 'Gerente com acesso a relatórios e configurações'),
('Atendente', 'Atendente com acesso a pedidos e cardápio'),
('Cozinha', 'Funcionário da cozinha com acesso apenas aos pedidos'),
('Entregador', 'Entregador com acesso aos pedidos para entrega')
ON CONFLICT (name) DO NOTHING;

-- Insert default permissions
INSERT INTO permissions (name, description, resource, action) VALUES
-- Admin panel access
('admin_panel_access', 'Acesso ao painel administrativo', 'admin', 'access'),
-- Order permissions
('order_view', 'Visualizar pedidos', 'orders', 'read'),
('order_create', 'Criar pedidos', 'orders', 'create'),
('order_update', 'Atualizar pedidos', 'orders', 'update'),
('order_delete', 'Deletar pedidos', 'orders', 'delete'),
-- Menu permissions
('menu_view', 'Visualizar cardápio', 'menu', 'read'),
('menu_create', 'Criar itens do cardápio', 'menu', 'create'),
('menu_update', 'Atualizar cardápio', 'menu', 'update'),
('menu_delete', 'Deletar itens do cardápio', 'menu', 'delete'),
-- User permissions
('user_view', 'Visualizar usuários', 'users', 'read'),
('user_create', 'Criar usuários', 'users', 'create'),
('user_update', 'Atualizar usuários', 'users', 'update'),
('user_delete', 'Deletar usuários', 'users', 'delete'),
-- Permission management
('permissions_management', 'Gerenciar permissões', 'permissions', 'manage'),
('roles_management', 'Gerenciar cargos/roles', 'roles', 'manage'),
('users_management', 'Gerenciar usuários', 'users', 'manage'),
-- Report permissions
('report_view', 'Visualizar relatórios', 'reports', 'read'),
('report_create', 'Criar relatórios', 'reports', 'create'),
('financial_stats', 'Acesso às estatísticas financeiras', 'reports', 'financial'),
-- Settings permissions
('settings_view', 'Visualizar configurações', 'settings', 'read'),
('settings_update', 'Atualizar configurações', 'settings', 'update'),
('settings_access', 'Acesso às configurações do sistema', 'settings', 'access'),
-- Resume permissions
('resume_view', 'Visualizar currículos', 'resumes', 'read'),
('resume_update', 'Atualizar status de currículos', 'resumes', 'update'),
('resumes_access', 'Acesso aos currículos', 'resumes', 'access'),
-- Ouvidoria permissions
('ouvidoria_view', 'Visualizar ouvidoria', 'ouvidoria', 'read'),
('ouvidoria_update', 'Responder ouvidoria', 'ouvidoria', 'update'),
('ouvidoria_access', 'Acesso à ouvidoria', 'ouvidoria', 'access'),
-- Order status permissions
('orders_status', 'Mudança de estado dos pedidos', 'orders', 'status'),
-- Reports access
('reports_access', 'Acesso aos relatórios', 'reports', 'access')
ON CONFLICT (name) DO NOTHING;

-- Insert default restaurant settings
INSERT INTO restaurant_settings (setting_key, setting_value, setting_type, description) VALUES
('restaurant_name', 'Portuga - Restaurante & Pizzaria', 'string', 'Nome do restaurante'),
('restaurant_phone', '5513997597759', 'string', 'Telefone principal'),
('restaurant_address', 'Endereço do Restaurante', 'string', 'Endereço completo'),
('is_open', 'true', 'boolean', 'Restaurante aberto/fechado'),
('kitchen_hours', '{"start": "11:00", "end": "23:00"}', 'json', 'Horário de funcionamento da cozinha'),
('pizza_hours', '{"start": "18:00", "end": "23:00"}', 'json', 'Horário de funcionamento da pizzaria'),
('delivery_hours', '{"start": "11:00", "end": "22:00"}', 'json', 'Horário de entregas'),
('max_delivery_distance', '18', 'number', 'Distância máxima de entrega em km'),
('delivery_fee_per_km', '2.5', 'number', 'Taxa de entrega por km'),
('min_delivery_fee', '5.0', 'number', 'Taxa mínima de entrega')
ON CONFLICT (setting_key) DO NOTHING;

-- Insert initial maintenance mode record
INSERT INTO maintenance_mode (id, is_active) VALUES (1, FALSE)
ON CONFLICT (id) DO NOTHING;
