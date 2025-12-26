<?php
/**
 * Session Management
 * Secure session handling with token-based authentication
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Start secure session
 */
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure session parameters
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
        ini_set('session.cookie_samesite', 'Lax');
        
        session_start();
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 1800) { // 30 minutes
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }
}

/**
 * Check if user is logged in
 * @return bool True if logged in
 */
function isLoggedIn() {
    startSecureSession();
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
        return false;
    }
    
    // Verify session token in database
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT s.*, u.is_active 
            FROM sessions s
            JOIN users u ON s.user_id = u.id
            WHERE s.session_token = ? AND s.user_id = ? AND s.expires_at > NOW()
        ");
        $stmt->execute([$_SESSION['session_token'], $_SESSION['user_id']]);
        $session = $stmt->fetch();
        
        if ($session && $session['is_active']) {
            return true;
        }
        
        // Invalid or expired session
        destroySession();
        return false;
        
    } catch (Exception $e) {
        error_log("Session verification failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get current user data
 * @return array|null User data or null if not logged in
 */
function getUserData() {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT id, full_name, email, oauth_provider, email_verified, created_at, last_login
            FROM users 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Failed to get user data: " . $e->getMessage());
        return null;
    }
}

/**
 * Create user session
 * @param int $userId User ID
 * @param bool $rememberMe Whether to extend session
 * @return string Session token
 */
function createSession($userId, $rememberMe = false) {
    startSecureSession();
    
    try {
        $pdo = getDBConnection();
        
        // Generate session token
        $sessionToken = bin2hex(random_bytes(32));
        $expiresIn = $rememberMe ? 30 : 1; // days
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresIn} days"));
        
        $ip = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Store session in database
        $stmt = $pdo->prepare("
            INSERT INTO sessions (user_id, session_token, ip_address, user_agent, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $sessionToken, $ip, $userAgent, $expiresAt]);
        
        // Update user's last login
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
        
        // Set session variables
        $_SESSION['user_id'] = $userId;
        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['created'] = time();
        
        return $sessionToken;
        
    } catch (Exception $e) {
        error_log("Failed to create session: " . $e->getMessage());
        throw new Exception("Failed to create session");
    }
}

/**
 * Destroy current session
 */
function destroySession() {
    startSecureSession();
    
    if (isset($_SESSION['session_token'])) {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("DELETE FROM sessions WHERE session_token = ?");
            $stmt->execute([$_SESSION['session_token']]);
        } catch (Exception $e) {
            error_log("Failed to delete session from database: " . $e->getMessage());
        }
    }
    
    // Clear all session variables
    $_SESSION = [];
    
    // Delete session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy session
    session_destroy();
}

/**
 * Refresh session expiration
 */
function refreshSession() {
    if (!isLoggedIn()) {
        return;
    }
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            UPDATE sessions 
            SET expires_at = DATE_ADD(NOW(), INTERVAL 1 DAY)
            WHERE session_token = ?
        ");
        $stmt->execute([$_SESSION['session_token']]);
    } catch (Exception $e) {
        error_log("Failed to refresh session: " . $e->getMessage());
    }
}

/**
 * Clean up expired sessions
 */
function cleanupExpiredSessions() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE expires_at < NOW()");
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Failed to cleanup expired sessions: " . $e->getMessage());
    }
}

/**
 * Require authentication (redirect if not logged in)
 * @param string $redirectUrl URL to redirect to if not logged in
 */
function requireAuth($redirectUrl = '/login.html') {
    if (!isLoggedIn()) {
        header("Location: $redirectUrl");
        exit();
    }
}
