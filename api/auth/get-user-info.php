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
        $roleId = $_SESSION['role_id'] ?? null;
        $fullName = $_SESSION['full_name'] ?? 'Unknown User';
        
        // Get hardcoded user details
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
            $roleId = $currentUser['role_id'];
        }
        
        if ($roleId) {
            try {
                $pdo = getDBConnection();
                
                // Get role name
                $stmt = $pdo->prepare("SELECT name, description FROM roles WHERE id = ?");
                $stmt->execute([$roleId]);
                $roleData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($roleData) {
                    $userData['roles'][] = [
                        'id' => $roleId,
                        'name' => $roleData['name'],
                        'description' => $roleData['description']
                    ];
                }
                
                // Get permissions
                $stmt = $pdo->prepare("
                    SELECT p.id, p.name, p.resource, p.action, p.description
                    FROM permissions p
                    INNER JOIN role_permissions rp ON p.id = rp.permission_id
                    WHERE rp.role_id = ?
                ");
                $stmt->execute([$roleId]);
                $userData['permissions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                error_log("Failed to fetch permissions for hardcoded user: " . $e->getMessage());
            }
        }
    } else {
        // Get database user info
        try {
            $pdo = getDBConnection();
            
            $stmt = $pdo->prepare("
                SELECT id, full_name, email, email_verified, created_at, last_login
                FROM users
                WHERE id = ? AND is_active = 1
            ");
            $stmt->execute([$userId]);
            $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($dbUser) {
                $userData['email'] = $dbUser['email'];
                $userData['email_verified'] = (bool)$dbUser['email_verified'];
                $userData['created_at'] = $dbUser['created_at'];
                $userData['last_login'] = $dbUser['last_login'];
                
                // Get user roles
                $stmt = $pdo->prepare("
                    SELECT r.id, r.name, r.description
                    FROM roles r
                    INNER JOIN user_roles ur ON r.id = ur.role_id
                    WHERE ur.user_id = ?
                ");
                $stmt->execute([$userId]);
                $userData['roles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get user permissions
                $stmt = $pdo->prepare("
                    SELECT DISTINCT p.id, p.name, p.resource, p.action, p.description
                    FROM permissions p
                    INNER JOIN role_permissions rp ON p.id = rp.permission_id
                    INNER JOIN user_roles ur ON rp.role_id = ur.role_id
                    WHERE ur.user_id = ?
                ");
                $stmt->execute([$userId]);
                $userData['permissions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
        } catch (Exception $e) {
            error_log("Failed to fetch database user info: " . $e->getMessage());
        }
    }
    
    // Create a simple permission map for easier checking
    $userData['permissionMap'] = [];
    foreach (($userData['permissions'] ?? []) as $perm) {
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
