<?php
/**
 * Database Configuration
 * Handles MySQL connection with PDO and environment variables support
 */

// Load environment variables from .env file if exists
function loadEnv($path = __DIR__ . '/../.env') {
    if (!file_exists($path)) {
        return;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
}

loadEnv();

// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'portuga_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Security keys
define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY') ?: 'change_this_to_random_key_in_production');
define('CSRF_SECRET', getenv('CSRF_SECRET') ?: 'change_this_to_random_csrf_secret');

/**
 * Get database connection
 * @return PDO Database connection object
 * @throws PDOException if connection fails
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new PDOException("Database connection failed. Please try again later.");
        }
    }
    
    return $pdo;
}

/**
 * Close database connection
 */
function closeDBConnection() {
    global $pdo;
    $pdo = null;
}
