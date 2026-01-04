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
require_once __DIR__ . '/../../config/hardcoded-users.php';
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
    
    // Get email first to check if it's a hardcoded user
    $email = $input['email'] ?? '';
    
    // Validate CSRF token APENAS para usuários não-hardcoded
    // Para hardcoded users, verificar primeiro se é um usuário hardcoded
    $isHardcodedEmail = isHardcodedUser($email);
    
    if (!$isHardcodedEmail) {
        if (!isset($input['csrf_token']) || !validateCSRFToken($input['csrf_token'])) {
            throw new Exception('Invalid CSRF token');
        }
    }
    
    // Não sanitizar emails de usuários hardcoded (domínio @test)
    if (!str_ends_with($email, '@test')) {
        $email = sanitizeInput($email);
    }
    
    $password = $input['password'] ?? '';
    $rememberMe = isset($input['remember_me']) && $input['remember_me'] === true;
    
    // Validate required fields
    if (empty($email)) {
        throw new Exception('Email is required');
    }
    
    if (empty($password)) {
        throw new Exception('Password is required');
    }
    
    // Check for hardcoded users first (before email validation)
    // Debug: verificar se está encontrando usuários hardcoded
    error_log("Attempting hardcoded auth for: " . $email);
    $hardcodedUser = authenticateHardcodedUser($email, $password);
    
    if ($hardcodedUser) {
        error_log("Hardcoded user authenticated successfully: " . $email);
        // Hardcoded user authentication successful
        // Skip rate limiting and database checks for hardcoded users
        
        // Fetch permissions from database based on role_id
        // Note: Customer users have null role_id and no permissions, which is intentional
        $permissions = [];
        $permissionMap = [];
        $hasAdminAccess = false;
        
        if ($hardcodedUser['role_id']) {
            try {
                $pdo = getDBConnection();
                
                // Get permissions for this role
                $stmt = $pdo->prepare("
                    SELECT p.id, p.name AS permission_name, p.description, p.resource, p.action
                    FROM permissions p
                    INNER JOIN role_permissions rp ON p.id = rp.permission_id
                    WHERE rp.role_id = ?
                ");
                $stmt->execute([$hardcodedUser['role_id']]);
                $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Create permission map for easy checking
                foreach ($permissions as $perm) {
                    // Add null checks for permission fields
                    if (isset($perm['permission_name'])) {
                        $permissionMap[$perm['permission_name']] = true;
                    }
                    // Also add resource_action format
                    if (isset($perm['resource']) && isset($perm['action'])) {
                        $permissionMap[$perm['resource'] . '_' . $perm['action']] = true;
                    }
                }
                
                // Check for admin panel access
                // Note: 'admin_panel_access' is defined in database/setup.sql
                $hasAdminAccess = isset($permissionMap['admin_panel_access']);
                
            } catch (Exception $e) {
                error_log("Failed to fetch permissions for hardcoded user: " . $e->getMessage());
            }
        }
        
        // Create session for hardcoded user
        $sessionToken = createSessionForHardcodedUser(
            $hardcodedUser['id'], 
            $hardcodedUser['full_name'], 
            $hardcodedUser['role_id'],
            $rememberMe
        );
        
        // Return success response with full data including permissions
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Login successful!',
            'user' => [
                'id' => $hardcodedUser['id'],
                'full_name' => $hardcodedUser['full_name'],
                'email' => $hardcodedUser['email'],
                'email_verified' => $hardcodedUser['email_verified'],
                'user_type' => $hardcodedUser['user_type'],
                'role' => $hardcodedUser['role'],
                'role_id' => $hardcodedUser['role_id'],
                'is_hardcoded' => true,
                'permissions' => $permissions,
                'permissionMap' => $permissionMap,
                'hasAdminAccess' => $hasAdminAccess
            ],
            'session_token' => $sessionToken,
            'redirect_url' => getRedirectUrlForUserType($hardcodedUser['user_type'])
        ]);
        exit();
    } else {
        error_log("Hardcoded auth failed for: " . $email);
    }
    
    // Not a hardcoded user, proceed with database authentication
    // Validate email format for database users
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
        'session_token' => $sessionToken,
        'redirect_url' => '/index.html'  // Default redirect for database users
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
