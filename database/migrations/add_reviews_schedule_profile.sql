-- Migration: Add Reviews, Schedule, Profile, and Enhanced Maintenance Tables
-- Created: 2026-12-30
-- Description: Adds tables for review system, employee schedules, user profiles, and enhanced maintenance mode

USE portuga_db;

-- ============================================
-- REVIEWS SYSTEM
-- ============================================

-- Table: reviews (customer satisfaction reviews)
CREATE TABLE IF NOT EXISTS reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL COMMENT 'NULL for anonymous reviews',
    order_id INT NULL,
    rating INT NOT NULL CHECK (rating >= 0 AND rating <= 5) COMMENT 'Rating from 0 to 5 stars',
    comment TEXT,
    status ENUM('pendente', 'aprovado', 'rejeitado', 'arquivado') DEFAULT 'pendente',
    ip_address VARCHAR(45) NULL COMMENT 'For rate limiting',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_rating (rating),
    INDEX idx_created (created_at),
    INDEX idx_user (user_id),
    INDEX idx_ip_created (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- EMPLOYEE SCHEDULE SYSTEM
-- ============================================

-- Update employee_schedule table if exists, or create new one
DROP TABLE IF EXISTS employee_schedule;

CREATE TABLE employee_schedule (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    day_of_week ENUM('segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo') NOT NULL,
    shift_start TIME NOT NULL,
    lunch_start TIME NULL,
    lunch_end TIME NULL,
    shift_end TIME NOT NULL,
    notes TEXT,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_day (user_id, day_of_week),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- USER PROFILE SYSTEM
-- ============================================

-- Table: user_profile_photos
CREATE TABLE IF NOT EXISTS user_profile_photos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    photo_path VARCHAR(512) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: user_favorite_dishes
CREATE TABLE IF NOT EXISTS user_favorite_dishes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    menu_item_id INT NOT NULL,
    set_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: user_privacy_settings
CREATE TABLE IF NOT EXISTS user_privacy_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    show_statistics BOOLEAN DEFAULT TRUE,
    show_total_spent BOOLEAN DEFAULT TRUE,
    show_favorite_dish BOOLEAN DEFAULT TRUE,
    show_order_count BOOLEAN DEFAULT TRUE,
    show_last_review BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- NOTES/ANNOUNCEMENTS SYSTEM
-- ============================================

-- Table: notes (announcements/communications displayed on homepage)
CREATE TABLE IF NOT EXISTS notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    note_type ENUM('info', 'warning', 'success', 'promo') DEFAULT 'info',
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_active (is_active),
    INDEX idx_order (display_order),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ENHANCED MAINTENANCE MODE
-- ============================================

-- Update maintenance_mode table with new columns
ALTER TABLE maintenance_mode 
    ADD COLUMN IF NOT EXISTS restricted_pages JSON NULL COMMENT 'Array of page names in maintenance',
    ADD COLUMN IF NOT EXISTS page_messages JSON NULL COMMENT 'Custom messages per page';

-- ============================================
-- PERMISSIONS
-- ============================================

-- Insert new permissions for reviews system
INSERT INTO permissions (name, description, resource, action) VALUES
('reviews_view', 'Visualizar avaliações', 'reviews', 'read'),
('reviews_manage', 'Gerenciar avaliações (aprovar/rejeitar)', 'reviews', 'update'),
('reviews_access', 'Acesso ao sistema de avaliações', 'reviews', 'access'),
('reviews_delete', 'Deletar avaliações', 'reviews', 'delete')
ON CONFLICT (name) DO NOTHING;

-- Insert new permissions for schedule system
INSERT INTO permissions (name, description, resource, action) VALUES
('schedule_view', 'Visualizar horários', 'schedule', 'read'),
('schedule_manage', 'Gerenciar horários de funcionários', 'schedule', 'update'),
('schedule_access', 'Acesso ao sistema de horários', 'schedule', 'access'),
('schedule_view_own', 'Visualizar próprio horário', 'schedule', 'read_own'),
('schedule_view_all', 'Visualizar horários de todos', 'schedule', 'read_all')
ON CONFLICT (name) DO NOTHING;

-- Insert new permissions for profile system
INSERT INTO permissions (name, description, resource, action) VALUES
('profile_view_all', 'Ver perfis de todos os usuários', 'profile', 'read'),
('profile_edit_own', 'Editar próprio perfil', 'profile', 'update_own'),
('profile_edit_all', 'Editar perfis de todos', 'profile', 'update_all')
ON CONFLICT (name) DO NOTHING;

-- Insert new permissions for notes system
INSERT INTO permissions (name, description, resource, action) VALUES
('notes_view', 'Visualizar notas/avisos', 'notes', 'read'),
('notes_create', 'Criar notas/avisos', 'notes', 'create'),
('notes_update', 'Atualizar notas/avisos', 'notes', 'update'),
('notes_delete', 'Deletar notas/avisos', 'notes', 'delete'),
('notes_access', 'Acesso ao gerenciamento de notas', 'notes', 'access')
ON CONFLICT (name) DO NOTHING;

-- ============================================
-- DISPLAY RESULTS
-- ============================================

-- Show newly created tables
SHOW TABLES LIKE '%reviews%';
SHOW TABLES LIKE '%schedule%';
SHOW TABLES LIKE '%profile%';
SHOW TABLES LIKE '%notes%';

SELECT 'Migration completed successfully!' AS status;
