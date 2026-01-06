-- Migration: Add Reviews, Schedule, Profile, and Enhanced Maintenance Tables
-- Created: 2026-12-30
-- Description: Adds tables for review system, employee schedules, user profiles, and enhanced maintenance mode

-- ============================================
-- REVIEWS SYSTEM
-- ============================================

-- Table: reviews (customer satisfaction reviews)
CREATE TABLE IF NOT EXISTS reviews (
    id SERIAL PRIMARY KEY,
    user_id INT NULL,
    order_id INT NULL,
    rating INT NOT NULL CHECK (rating >= 0 AND rating <= 5),
    comment TEXT,
    status TEXT CHECK (status IN ('pendente', 'aprovado', 'rejeitado', 'arquivado')) DEFAULT 'pendente',
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

COMMENT ON COLUMN reviews.user_id IS 'NULL for anonymous reviews';
COMMENT ON COLUMN reviews.rating IS 'Rating from 0 to 5 stars';
COMMENT ON COLUMN reviews.ip_address IS 'For rate limiting';

CREATE INDEX IF NOT EXISTS idx_reviews_status ON reviews(status);
CREATE INDEX IF NOT EXISTS idx_reviews_rating ON reviews(rating);
CREATE INDEX IF NOT EXISTS idx_reviews_created ON reviews(created_at);
CREATE INDEX IF NOT EXISTS idx_reviews_user ON reviews(user_id);
CREATE INDEX IF NOT EXISTS idx_reviews_ip_created ON reviews(ip_address, created_at);

-- ============================================
-- EMPLOYEE SCHEDULE SYSTEM
-- ============================================

-- Update employee_schedule table if exists, or create new one
DROP TABLE IF EXISTS employee_schedule;

CREATE TABLE employee_schedule (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    day_of_week TEXT CHECK (day_of_week IN ('segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo')) NOT NULL,
    shift_start TIME NOT NULL,
    lunch_start TIME NULL,
    lunch_end TIME NULL,
    shift_end TIME NOT NULL,
    notes TEXT,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_schedule_user_day ON employee_schedule(user_id, day_of_week);
CREATE INDEX IF NOT EXISTS idx_schedule_user ON employee_schedule(user_id);

-- Trigger for updated_at on employee_schedule table
CREATE TRIGGER employee_schedule_updated_at BEFORE UPDATE ON employee_schedule
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================
-- USER PROFILE SYSTEM
-- ============================================

-- Table: user_profile_photos
CREATE TABLE IF NOT EXISTS user_profile_photos (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    photo_path VARCHAR(512) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_profile_photos_user ON user_profile_photos(user_id);

-- Trigger for updated_at on user_profile_photos table
CREATE TRIGGER user_profile_photos_updated_at BEFORE UPDATE ON user_profile_photos
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Table: user_favorite_dishes
CREATE TABLE IF NOT EXISTS user_favorite_dishes (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    menu_item_id INT NOT NULL,
    set_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_favorite_dishes_user ON user_favorite_dishes(user_id);

-- Table: user_privacy_settings
CREATE TABLE IF NOT EXISTS user_privacy_settings (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    show_statistics BOOLEAN DEFAULT TRUE,
    show_total_spent BOOLEAN DEFAULT TRUE,
    show_favorite_dish BOOLEAN DEFAULT TRUE,
    show_order_count BOOLEAN DEFAULT TRUE,
    show_last_review BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_privacy_settings_user ON user_privacy_settings(user_id);

-- Trigger for updated_at on user_privacy_settings table
CREATE TRIGGER user_privacy_settings_updated_at BEFORE UPDATE ON user_privacy_settings
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================
-- NOTES/ANNOUNCEMENTS SYSTEM
-- ============================================

-- Table: notes (announcements/communications displayed on homepage)
CREATE TABLE IF NOT EXISTS notes (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    note_type TEXT CHECK (note_type IN ('info', 'warning', 'success', 'promo')) DEFAULT 'info',
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_notes_active ON notes(is_active);
CREATE INDEX IF NOT EXISTS idx_notes_order ON notes(display_order);
CREATE INDEX IF NOT EXISTS idx_notes_expires ON notes(expires_at);

-- Trigger for updated_at on notes table
CREATE TRIGGER notes_updated_at BEFORE UPDATE ON notes
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================
-- ENHANCED MAINTENANCE MODE
-- ============================================

-- Update maintenance_mode table with new columns
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'maintenance_mode' AND column_name = 'restricted_pages'
    ) THEN
        ALTER TABLE maintenance_mode ADD COLUMN restricted_pages JSON NULL;
        COMMENT ON COLUMN maintenance_mode.restricted_pages IS 'Array of page names in maintenance';
    END IF;
    
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'maintenance_mode' AND column_name = 'page_messages'
    ) THEN
        ALTER TABLE maintenance_mode ADD COLUMN page_messages JSON NULL;
        COMMENT ON COLUMN maintenance_mode.page_messages IS 'Custom messages per page';
    END IF;
END $$;

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
SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename LIKE '%reviews%';
SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename LIKE '%schedule%';
SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename LIKE '%profile%';
SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename LIKE '%notes%';

SELECT 'Migration completed successfully!' AS status;
