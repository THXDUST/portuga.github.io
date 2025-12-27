<?php
/**
 * Users Management API
 * Handles user account management with role assignments
 */

require_once __DIR__ . '/base.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$conn = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGet($conn, $action);
            break;
        case 'POST':
            handlePost($conn, $action);
            break;
        case 'PUT':
            handlePut($conn, $action);
            break;
        case 'DELETE':
            handleDelete($conn, $action);
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

function handleGet($conn, $action) {
    switch ($action) {
        case 'list':
            // List all users with their roles
            $result = $conn->query("
                SELECT u.id, u.full_name, u.email, u.is_active, 
                       u.created_at, u.last_login,
                       GROUP_CONCAT(r.name SEPARATOR ', ') as roles
                FROM users u
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id
                GROUP BY u.id
                ORDER BY u.created_at DESC
            ");
            $users = $result->fetch_all(MYSQLI_ASSOC);
            sendSuccess($users);
            break;
            
        case 'get':
            // Get single user with detailed role information
            $id = $_GET['id'] ?? null;
            if (!$id) {
                sendError('User ID required');
            }
            
            $stmt = $conn->prepare("
                SELECT id, full_name, email, email_verified, is_active, 
                       created_at, updated_at, last_login
                FROM users
                WHERE id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if (!$user) {
                sendError('User not found', 404);
            }
            
            // Get user's roles
            $stmt = $conn->prepare("
                SELECT r.id, r.name, r.description, ur.assigned_at
                FROM roles r
                INNER JOIN user_roles ur ON r.id = ur.role_id
                WHERE ur.user_id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user['roles'] = $result->fetch_all(MYSQLI_ASSOC);
            
            sendSuccess($user);
            break;
            
        default:
            sendError('Invalid action');
    }
}

function handlePost($conn, $action) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'create':
            // Create new user
            validateRequired($data, ['full_name', 'email', 'password']);
            
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $data['email']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                sendError('Email already exists', 400);
            }
            
            // Hash password (double encryption as per existing system)
            $passwordHash = password_hash(hash('sha256', $data['password']), PASSWORD_BCRYPT);
            
            $stmt = $conn->prepare("
                INSERT INTO users (full_name, email, password_hash, email_verified, is_active)
                VALUES (?, ?, ?, TRUE, TRUE)
            ");
            $stmt->bind_param("sss", $data['full_name'], $data['email'], $passwordHash);
            
            if ($stmt->execute()) {
                $userId = $conn->insert_id;
                
                // Assign roles if provided
                if (isset($data['role_ids']) && is_array($data['role_ids'])) {
                    $stmt = $conn->prepare("
                        INSERT INTO user_roles (user_id, role_id, assigned_by)
                        VALUES (?, ?, ?)
                    ");
                    $assignedBy = $_SESSION['user_id'] ?? null;
                    
                    foreach ($data['role_ids'] as $roleId) {
                        $stmt->bind_param("iii", $userId, $roleId, $assignedBy);
                        $stmt->execute();
                    }
                }
                
                sendSuccess(['id' => $userId], 'User created successfully');
            } else {
                sendError('Failed to create user');
            }
            break;
            
        default:
            sendError('Invalid action');
    }
}

function handlePut($conn, $action) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'update':
            // Update user information
            validateRequired($data, ['id']);
            
            $updates = [];
            $types = '';
            $values = [];
            
            if (isset($data['full_name'])) {
                $updates[] = 'full_name = ?';
                $types .= 's';
                $values[] = $data['full_name'];
            }
            
            if (isset($data['email'])) {
                $updates[] = 'email = ?';
                $types .= 's';
                $values[] = $data['email'];
            }
            
            if (isset($data['is_active'])) {
                $updates[] = 'is_active = ?';
                $types .= 'i';
                $values[] = $data['is_active'] ? 1 : 0;
            }
            
            if (empty($updates)) {
                sendError('No fields to update');
            }
            
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
            $types .= 'i';
            $values[] = $data['id'];
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$values);
            
            if ($stmt->execute()) {
                sendSuccess(null, 'User updated successfully');
            } else {
                sendError('Failed to update user');
            }
            break;
            
        case 'change-password':
            // Change user password
            validateRequired($data, ['id', 'password']);
            
            $passwordHash = password_hash(hash('sha256', $data['password']), PASSWORD_BCRYPT);
            
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->bind_param("si", $passwordHash, $data['id']);
            
            if ($stmt->execute()) {
                sendSuccess(null, 'Password updated successfully');
            } else {
                sendError('Failed to update password');
            }
            break;
            
        default:
            sendError('Invalid action');
    }
}

function handleDelete($conn, $action) {
    switch ($action) {
        case 'delete':
            // Soft delete (deactivate) user
            $id = $_GET['id'] ?? null;
            if (!$id) {
                sendError('User ID required');
            }
            
            $stmt = $conn->prepare("UPDATE users SET is_active = FALSE WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                sendSuccess(null, 'User deactivated successfully');
            } else {
                sendError('Failed to deactivate user');
            }
            break;
            
        case 'permanent-delete':
            // Permanently delete user (use with caution)
            $id = $_GET['id'] ?? null;
            if (!$id) {
                sendError('User ID required');
            }
            
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                sendSuccess(null, 'User permanently deleted');
            } else {
                sendError('Failed to delete user');
            }
            break;
            
        default:
            sendError('Invalid action');
    }
}
