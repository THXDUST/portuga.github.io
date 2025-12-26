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

-- Display table structure
SHOW TABLES;
