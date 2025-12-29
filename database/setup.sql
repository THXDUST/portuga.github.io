-- Database Setup Script for Portuga Authentication System
-- MySQL 5.7+ compatible

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS portuga_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE portuga_db;

-- Table: users
-- Stores user account information
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(512) NULL COMMENT 'NULL for OAuth users',
    oauth_provider ENUM('none', 'google', 'facebook', 'instagram') DEFAULT 'none',
    oauth_id VARCHAR(255) NULL,
    email_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_email (email),
    INDEX idx_oauth (oauth_provider, oauth_id),
    INDEX idx_verification_token (verification_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: login_attempts
-- Tracks login attempts for rate limiting
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE,
    INDEX idx_email_time (email, attempted_at),
    INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sessions
-- Manages active user sessions
CREATE TABLE IF NOT EXISTS sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (session_token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: password_resets
-- Manages password reset tokens
CREATE TABLE IF NOT EXISTS password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    reset_token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    INDEX idx_token (reset_token),
    INDEX idx_email (email),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: Create a cleanup event to remove expired records
-- Note: Events might not be supported on all hosting providers
DELIMITER $$

CREATE EVENT IF NOT EXISTS cleanup_expired_data
ON SCHEDULE EVERY 1 DAY
DO BEGIN
    -- Delete old login attempts (older than 30 days)
    DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- Delete expired sessions
    DELETE FROM sessions WHERE expires_at < NOW();
    
    -- Delete expired password reset tokens
    DELETE FROM password_resets WHERE expires_at < NOW();
END$$

DELIMITER ;

-- Insert a test user (optional, for development)
-- Password: Test123! (double encrypted)
-- Note: This should be removed in production or used only for initial testing
-- INSERT INTO users (full_name, email, password_hash, email_verified) 
-- VALUES ('Test User', 'test@example.com', 'your_encrypted_password_here', TRUE);

-- ============================================
-- ADMIN PANEL TABLES
-- ============================================

-- Table: roles
-- Stores user roles (Admin, Gerente, Atendente, Cozinha, Entregador)
CREATE TABLE IF NOT EXISTS roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: permissions
-- Stores granular permissions
CREATE TABLE IF NOT EXISTS permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    resource VARCHAR(50) NOT NULL COMMENT 'Resource type: orders, menu, users, etc',
    action VARCHAR(20) NOT NULL COMMENT 'Action: create, read, update, delete',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_resource (resource),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: role_permissions
-- Maps permissions to roles
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role_id, permission_id),
    INDEX idx_role (role_id),
    INDEX idx_permission (permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: user_roles
-- Maps roles to users
CREATE TABLE IF NOT EXISTS user_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_role (user_id, role_id),
    INDEX idx_user (user_id),
    INDEX idx_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: menu_groups
-- Groups for menu organization (Pizzas → Salgadas, Doces)
CREATE TABLE IF NOT EXISTS menu_groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    parent_id INT NULL COMMENT 'For subgroups',
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES menu_groups(id) ON DELETE SET NULL,
    INDEX idx_parent (parent_id),
    INDEX idx_order (display_order),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: menu_items
-- Items in the menu
CREATE TABLE IF NOT EXISTS menu_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    image_url VARCHAR(512),
    ingredients TEXT,
    is_available BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES menu_groups(id) ON DELETE CASCADE,
    INDEX idx_group (group_id),
    INDEX idx_available (is_available),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: orders
-- Enhanced order management
CREATE TABLE IF NOT EXISTS orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    order_number VARCHAR(20) UNIQUE NOT NULL,
    table_number INT NULL CHECK (table_number > 0) COMMENT 'Table number for in-restaurant orders',
    status ENUM('recebido', 'em_andamento', 'finalizado', 'cancelado') DEFAULT 'recebido',
    order_type ENUM('viagem', 'local') NOT NULL,
    payment_method ENUM('dinheiro', 'cartao_debito', 'cartao_credito', 'pix') NOT NULL,
    change_for DECIMAL(10, 2) NULL COMMENT 'If payment is cash, how much change needed',
    delivery_address TEXT NULL,
    delivery_distance DECIMAL(5, 2) NULL COMMENT 'Distance in km',
    delivery_fee DECIMAL(10, 2) DEFAULT 0.00,
    pickup_time DATETIME NULL,
    production_start_time DATETIME NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_order_number (order_number),
    INDEX idx_created (created_at),
    INDEX idx_user (user_id),
    INDEX idx_table (table_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: order_items
-- Items in each order
CREATE TABLE IF NOT EXISTS order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    menu_item_id INT NULL,
    item_name VARCHAR(255) NOT NULL,
    item_price DECIMAL(10, 2) NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    notes TEXT,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE SET NULL,
    INDEX idx_order (order_id),
    INDEX idx_menu_item (menu_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: order_notes
-- Additional notes/updates on orders
CREATE TABLE IF NOT EXISTS order_notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    user_id INT NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_order (order_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: restaurant_settings
-- General restaurant configuration
CREATE TABLE IF NOT EXISTS restaurant_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: reports
-- Saved reports
CREATE TABLE IF NOT EXISTS reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    report_type ENUM('faturamento', 'produtos', 'fluxo_clientes', 'notas') NOT NULL,
    report_name VARCHAR(255) NOT NULL,
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    data JSON,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_type (report_type),
    INDEX idx_dates (date_from, date_to),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: resumes
-- Job applications
CREATE TABLE IF NOT EXISTS resumes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    desired_position VARCHAR(100) NOT NULL,
    resume_file_path VARCHAR(512),
    cover_letter TEXT,
    status ENUM('em_analise', 'aprovado', 'rejeitado') DEFAULT 'em_analise',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_email (email),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ouvidoria
-- Customer support messages
CREATE TABLE IF NOT EXISTS ouvidoria (
    id INT PRIMARY KEY AUTO_INCREMENT,
    protocol_number VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    image_path VARCHAR(512) NULL,
    status ENUM('pendente', 'em_atendimento', 'resolvido') DEFAULT 'pendente',
    response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    responded_by INT NULL,
    FOREIGN KEY (responded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_protocol (protocol_number),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: maintenance_mode
-- System maintenance configuration
CREATE TABLE IF NOT EXISTS maintenance_mode (
    id INT PRIMARY KEY AUTO_INCREMENT,
    is_active BOOLEAN DEFAULT FALSE,
    restrict_all BOOLEAN DEFAULT FALSE,
    restrict_orders BOOLEAN DEFAULT FALSE,
    restrict_menu BOOLEAN DEFAULT FALSE,
    custom_message TEXT,
    activated_at TIMESTAMP NULL,
    activated_by INT NULL,
    deactivated_at TIMESTAMP NULL,
    FOREIGN KEY (activated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: employee_schedule
-- Employee work schedule
CREATE TABLE IF NOT EXISTS employee_schedule (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    role_function VARCHAR(100) NOT NULL COMMENT 'Specific function/position',
    schedule_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    service_point_id INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_date (schedule_date),
    INDEX idx_service_point (service_point_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: service_points
-- Strategic service points (areas, tables, counters)
CREATE TABLE IF NOT EXISTS service_points (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    point_type ENUM('mesa', 'balcao', 'area', 'outro') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (point_type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: admin_logs
-- Log all administrative actions
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50) NOT NULL,
    resource_id INT NULL,
    details JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INITIAL DATA
-- ============================================

-- Insert default roles
INSERT IGNORE INTO roles (name, description) VALUES
('Admin', 'Administrador com acesso total ao sistema'),
('Gerente', 'Gerente com acesso a relatórios e configurações'),
('Atendente', 'Atendente com acesso a pedidos e cardápio'),
('Cozinha', 'Funcionário da cozinha com acesso apenas aos pedidos'),
('Entregador', 'Entregador com acesso aos pedidos para entrega');

-- Insert default permissions
INSERT IGNORE INTO permissions (name, description, resource, action) VALUES
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
('reports_access', 'Acesso aos relatórios', 'reports', 'access');

-- Insert default restaurant settings
INSERT IGNORE INTO restaurant_settings (setting_key, setting_value, setting_type, description) VALUES
('restaurant_name', 'Portuga - Restaurante & Pizzaria', 'string', 'Nome do restaurante'),
('restaurant_phone', '5513997597759', 'string', 'Telefone principal'),
('restaurant_address', 'Endereço do Restaurante', 'string', 'Endereço completo'),
('is_open', 'true', 'boolean', 'Restaurante aberto/fechado'),
('kitchen_hours', '{"start": "11:00", "end": "23:00"}', 'json', 'Horário de funcionamento da cozinha'),
('pizza_hours', '{"start": "18:00", "end": "23:00"}', 'json', 'Horário de funcionamento da pizzaria'),
('delivery_hours', '{"start": "11:00", "end": "22:00"}', 'json', 'Horário de entregas'),
('max_delivery_distance', '18', 'number', 'Distância máxima de entrega em km'),
('delivery_fee_per_km', '2.5', 'number', 'Taxa de entrega por km'),
('min_delivery_fee', '5.0', 'number', 'Taxa mínima de entrega');

-- Insert initial maintenance mode record
INSERT IGNORE INTO maintenance_mode (id, is_active) VALUES (1, FALSE);

-- Display table structure
SHOW TABLES;
