<?php
/**
 * Get User Information and Permissions API
 * Returns current user data with roles and permissions
 */

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/hardcoded-users.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

startSecureSession();

try {
    // Check if user is logged in
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Not authenticated',
            'isLoggedIn' => false
        ]);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    $isHardcodedUser = $_SESSION['is_hardcoded_user'] ?? false;
    
    $userData = [
        'id' => $userId,
        'full_name' => $_SESSION['full_name'] ?? 'User',
        'isLoggedIn' => true,
        'isHardcoded' => $isHardcodedUser,
        'permissions' => [],
        'roles' => []
    ];
    
    // Handle hardcoded users
    if ($isHardcodedUser) {
        $hardcodedUsers = getHardcodedUsers();
        $currentUser = null;
        
        foreach ($hardcodedUsers as $user) {
            if ($user['id'] === $userId) {
                $currentUser = $user;
                break;
            }
        }
        
        if ($currentUser) {
            $userData['email'] = $currentUser['email'];
            $userData['user_type'] = $currentUser['user_type'];
            $userData['role'] = $currentUser['role'];
            $userData['role_id'] = $currentUser['role_id'];
            
            // Get permissions for hardcoded user's role
            if ($currentUser['role_id']) {
                $conn = getDBConnection();
                $stmt = $conn->prepare("
                    SELECT p.name, p.resource, p.action, p.description
                    FROM permissions p
                    INNER JOIN role_permissions rp ON p.id = rp.permission_id
                    WHERE rp.role_id = ?
                ");
                $stmt->bind_param("i", $currentUser['role_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $userData['permissions'][] = $row;
                }
                
                // Get role info
                $stmt = $conn->prepare("SELECT id, name, description FROM roles WHERE id = ?");
                $stmt->bind_param("i", $currentUser['role_id']);
                $stmt->execute();
                $roleResult = $stmt->get_result();
                if ($roleData = $roleResult->fetch_assoc()) {
                    $userData['roles'][] = $roleData;
                }
                
                $conn->close();
            }
            
            // Add default permissions for admin user type
            if ($currentUser['user_type'] === 'admin') {
                // Admin has all permissions by default
                $userData['hasAdminAccess'] = true;
            }
        }
    } else {
        // Get database user info
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("
            SELECT id, full_name, email, email_verified, created_at, last_login
            FROM users
            WHERE id = ? AND is_active = 1
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $dbUser = $result->fetch_assoc();
        
        if ($dbUser) {
            $userData['email'] = $dbUser['email'];
            $userData['email_verified'] = (bool)$dbUser['email_verified'];
            $userData['created_at'] = $dbUser['created_at'];
            $userData['last_login'] = $dbUser['last_login'];
            
            // Get user roles
            $stmt = $conn->prepare("
                SELECT r.id, r.name, r.description
                FROM roles r
                INNER JOIN user_roles ur ON r.id = ur.role_id
                WHERE ur.user_id = ?
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $userData['roles'][] = $row;
            }
            
            // Get user permissions
            $stmt = $conn->prepare("
                SELECT DISTINCT p.name, p.resource, p.action, p.description
                FROM permissions p
                INNER JOIN role_permissions rp ON p.id = rp.permission_id
                INNER JOIN user_roles ur ON rp.role_id = ur.role_id
                WHERE ur.user_id = ?
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $userData['permissions'][] = $row;
            }
        }
        
        $conn->close();
    }
    
    // Create a simple permission map for easier checking
    $userData['permissionMap'] = [];
    foreach ($userData['permissions'] as $perm) {
        $key = $perm['resource'] . '_' . $perm['action'];
        $userData['permissionMap'][$key] = true;
        // Also add by name for legacy checks
        $userData['permissionMap'][$perm['name']] = true;
    }
    
    // Check for admin panel access
    $userData['hasAdminAccess'] = 
        isset($userData['permissionMap']['admin_panel_access']) ||
        (isset($userData['user_type']) && $userData['user_type'] === 'admin');
    
    echo json_encode([
        'success' => true,
        'data' => $userData
    ]);
    
} catch (Exception $e) {
    error_log("Error getting user info: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
