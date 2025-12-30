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
define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY') ?: '6748938b4ed0c9916b2c1fbcc507cba813aee27c6e4a6e52c978a690e23b42e2');
define('CSRF_SECRET', getenv('CSRF_SECRET') ?: 'f722c609a96f38e74491fe65501da8f88e87b83fdf331858cb4a899b1f07f5cd');

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
