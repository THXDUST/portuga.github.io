<?php
/**
 * User Login API
 * Handles user authentication with rate limiting
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/session.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate CSRF token
    if (!isset($input['csrf_token']) || !validateCSRFToken($input['csrf_token'])) {
        throw new Exception('Invalid CSRF token');
    }
    
    // Sanitize inputs
    $email = sanitizeInput($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $rememberMe = isset($input['remember_me']) && $input['remember_me'] === true;
    
    // Validate required fields
    if (empty($email)) {
        throw new Exception('Email is required');
    }
    
    if (empty($password)) {
        throw new Exception('Password is required');
    }
    
    // Validate email format
    if (!validateEmail($email)) {
        throw new Exception('Invalid email format');
    }
    
    // Check rate limiting
    $rateLimit = checkRateLimit($email, 'login', 5, 15);
    
    if (!$rateLimit['allowed']) {
        $waitMinutes = ceil($rateLimit['wait_time'] / 60);
        throw new Exception("Too many login attempts. Please try again in $waitMinutes minutes.");
    }
    
    // Get user from database
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT id, full_name, email, password_hash, email_verified, is_active, oauth_provider
        FROM users 
        WHERE email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    // Check if user exists
    if (!$user) {
        logLoginAttempt($email, false);
        throw new Exception('Invalid email or password');
    }
    
    // Check if user is active
    if (!$user['is_active']) {
        logLoginAttempt($email, false);
        throw new Exception('Your account has been deactivated. Please contact support.');
    }
    
    // Check if user registered via OAuth
    if ($user['oauth_provider'] !== 'none') {
        logLoginAttempt($email, false);
        throw new Exception('This account uses ' . ucfirst($user['oauth_provider']) . ' login. Please use the "Sign in with ' . ucfirst($user['oauth_provider']) . '" button.');
    }
    
    // Verify password with double encryption
    if (!verifyPassword($password, $user['password_hash'], $email)) {
        logLoginAttempt($email, false);
        throw new Exception('Invalid email or password');
    }
    
    // Check if email is verified (optional - can be disabled for testing)
    // if (!$user['email_verified']) {
    //     throw new Exception('Please verify your email address before logging in.');
    // }
    
    // Log successful login attempt
    logLoginAttempt($email, true);
    
    // Create session
    $sessionToken = createSession($user['id'], $rememberMe);
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful!',
        'user' => [
            'id' => $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'email_verified' => $user['email_verified']
        ],
        'session_token' => $sessionToken
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
