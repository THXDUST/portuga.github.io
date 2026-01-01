<?php
/**
 * Security Functions
 * Provides double encryption, CSRF protection, input sanitization, and rate limiting
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Double encrypt password with bcrypt and HMAC-SHA256
 * @param string $password Plain text password
 * @param string $email User email for additional salt
 * @return string Encrypted password hash
 */
function doubleEncrypt($password, $email) {
    // First layer: bcrypt with random salt
    $bcryptHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Second layer: HMAC-SHA256 with encryption key and email as additional salt
    $hmacHash = hash_hmac('sha256', $bcryptHash . $email, ENCRYPTION_KEY);
    
    // Combine both hashes
    return $bcryptHash . ':' . $hmacHash;
}

/**
 * Verify password against double encrypted hash
 * @param string $password Plain text password to verify
 * @param string $storedHash Double encrypted hash from database
 * @param string $email User email
 * @return bool True if password matches
 */
function verifyPassword($password, $storedHash, $email) {
    if (empty($storedHash)) {
        return false;
    }
    
    // Check if hash is in new format (with HMAC)
    if (strpos($storedHash, ':') !== false) {
        // New format: bcrypt: hmac
        list($bcryptHash, $storedHmac) = explode(':', $storedHash, 2);
        
        // Verify bcrypt hash
        if (! password_verify($password, $bcryptHash)) {
            return false;
        }
        
        // Verify HMAC hash
        $calculatedHmac = hash_hmac('sha256', $bcryptHash . $email, ENCRYPTION_KEY);
        
        return hash_equals($storedHmac, $calculatedHmac);
    } else {
        // Old format: bcrypt only (legacy support)
        // This allows old passwords to work, but they should be migrated
        error_log("Warning: User $email is using legacy password format. Consider forcing password reset.");
        return password_verify($password, $storedHash);
    }
}

/**
 * Generate CSRF token
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * @param string $token Token to validate
 * @return bool True if valid
 */
function validateCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input data
 * @param mixed $data Data to sanitize
 * @return mixed Sanitized data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    
    return $data;
}

/**
 * Validate email format
 * @param string $email Email to validate
 * @return bool True if valid email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength
 * @param string $password Password to validate
 * @return array ['valid' => bool, 'message' => string]
 */
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    return [
        'valid' => empty($errors),
        'message' => implode('. ', $errors)
    ];
}

/**
 * Check rate limiting for login attempts
 * @param string $email Email to check
 * @param string $action Action type (default: 'login')
 * @param int $maxAttempts Maximum attempts allowed (default: 5)
 * @param int $timeWindow Time window in minutes (default: 15)
 * @return array ['allowed' => bool, 'attempts' => int, 'wait_time' => int]
 */
function checkRateLimit($email, $action = 'login', $maxAttempts = 5, $timeWindow = 15) {
    try {
        $pdo = getDBConnection();
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Clean up old attempts
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$timeWindow} minutes"));
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < ?");
        $stmt->execute([$cutoffTime]);
        
        // Count recent attempts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempts, MAX(attempted_at) as last_attempt
            FROM login_attempts 
            WHERE email = ? AND ip_address = ? AND attempted_at >= ?
        ");
        $stmt->execute([$email, $ip, $cutoffTime]);
        $result = $stmt->fetch();
        
        $attempts = $result['attempts'];
        $allowed = $attempts < $maxAttempts;
        
        $waitTime = 0;
        if (!$allowed && $result['last_attempt']) {
            $lastAttempt = strtotime($result['last_attempt']);
            $unlockTime = $lastAttempt + ($timeWindow * 60);
            $waitTime = max(0, $unlockTime - time());
        }
        
        return [
            'allowed' => $allowed,
            'attempts' => $attempts,
            'wait_time' => $waitTime
        ];
    } catch (Exception $e) {
        error_log("Rate limit check failed: " . $e->getMessage());
        return ['allowed' => true, 'attempts' => 0, 'wait_time' => 0];
    }
}

/**
 * Log login attempt
 * @param string $email Email
 * @param bool $success Whether login was successful
 */
function logLoginAttempt($email, $success = false) {
    try {
        $pdo = getDBConnection();
        $ip = $_SERVER['REMOTE_ADDR'];
        
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (email, ip_address, success) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$email, $ip, $success ? 1 : 0]);
    } catch (Exception $e) {
        error_log("Failed to log login attempt: " . $e->getMessage());
    }
}

/**
 * Generate random token
 * @param int $length Token length (default: 32)
 * @return string Random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Get client IP address
 * @return string IP address
 */
function getClientIP() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
               'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
