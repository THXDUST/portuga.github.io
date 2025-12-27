<?php
/**
 * Roles Management API
 * Handles CRUD operations for roles
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
            // List all roles
            $result = $conn->query("
                SELECT r.id, r.name, r.description, r.created_at, r.updated_at,
                       COUNT(ur.id) as user_count
                FROM roles r
                LEFT JOIN user_roles ur ON r.id = ur.role_id
                GROUP BY r.id
                ORDER BY r.name
            ");
            $roles = $result->fetch_all(MYSQLI_ASSOC);
            sendSuccess($roles);
            break;
            
        case 'get':
            // Get single role with permissions
            $id = $_GET['id'] ?? null;
            if (!$id) {
                sendError('Role ID required');
            }
            
            $stmt = $conn->prepare("
                SELECT id, name, description, created_at, updated_at
                FROM roles
                WHERE id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $role = $result->fetch_assoc();
            
            if (!$role) {
                sendError('Role not found', 404);
            }
            
            // Get permissions for this role
            $stmt = $conn->prepare("
                SELECT p.id, p.name, p.description, p.resource, p.action
                FROM permissions p
                INNER JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $role['permissions'] = $result->fetch_all(MYSQLI_ASSOC);
            
            sendSuccess($role);
            break;
            
        case 'user-roles':
            // Get roles for a specific user
            $userId = $_GET['user_id'] ?? null;
            if (!$userId) {
                sendError('User ID required');
            }
            
            $stmt = $conn->prepare("
                SELECT r.id, r.name, r.description
                FROM roles r
                INNER JOIN user_roles ur ON r.id = ur.role_id
                WHERE ur.user_id = ?
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $roles = $result->fetch_all(MYSQLI_ASSOC);
            sendSuccess($roles);
            break;
            
        default:
            sendError('Invalid action');
    }
}

function handlePost($conn, $action) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'create':
            // Create new role
            validateRequired($data, ['name']);
            
            $stmt = $conn->prepare("
                INSERT INTO roles (name, description)
                VALUES (?, ?)
            ");
            $stmt->bind_param("ss", $data['name'], $data['description'] ?? null);
            
            if ($stmt->execute()) {
                $roleId = $conn->insert_id;
                
                // Assign permissions if provided
                if (isset($data['permission_ids']) && is_array($data['permission_ids'])) {
                    $stmt = $conn->prepare("
                        INSERT INTO role_permissions (role_id, permission_id)
                        VALUES (?, ?)
                    ");
                    
                    foreach ($data['permission_ids'] as $permId) {
                        $stmt->bind_param("ii", $roleId, $permId);
                        $stmt->execute();
                    }
                }
                
                sendSuccess(['id' => $roleId], 'Role created successfully');
            } else {
                sendError('Failed to create role');
            }
            break;
            
        case 'assign-user':
            // Assign role to user
            validateRequired($data, ['user_id', 'role_id']);
            
            $stmt = $conn->prepare("
                INSERT IGNORE INTO user_roles (user_id, role_id, assigned_by)
                VALUES (?, ?, ?)
            ");
            $assignedBy = $_SESSION['user_id'] ?? null;
            $stmt->bind_param("iii", $data['user_id'], $data['role_id'], $assignedBy);
            
            if ($stmt->execute()) {
                sendSuccess(null, 'Role assigned to user');
            } else {
                sendError('Failed to assign role');
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
            // Update role
            validateRequired($data, ['id', 'name']);
            
            $stmt = $conn->prepare("
                UPDATE roles 
                SET name = ?, description = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssi", $data['name'], $data['description'] ?? null, $data['id']);
            
            if ($stmt->execute()) {
                sendSuccess(null, 'Role updated successfully');
            } else {
                sendError('Failed to update role');
            }
            break;
            
        default:
            sendError('Invalid action');
    }
}

function handleDelete($conn, $action) {
    switch ($action) {
        case 'delete':
            // Delete role
            $id = $_GET['id'] ?? null;
            if (!$id) {
                sendError('Role ID required');
            }
            
            // Check if role has users
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_roles WHERE role_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result['count'] > 0) {
                sendError('Cannot delete role with assigned users', 400);
            }
            
            $stmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                sendSuccess(null, 'Role deleted successfully');
            } else {
                sendError('Failed to delete role');
            }
            break;
            
        case 'unassign-user':
            // Unassign role from user
            $userId = $_GET['user_id'] ?? null;
            $roleId = $_GET['role_id'] ?? null;
            
            if (!$userId || !$roleId) {
                sendError('User ID and Role ID required');
            }
            
            $stmt = $conn->prepare("
                DELETE FROM user_roles 
                WHERE user_id = ? AND role_id = ?
            ");
            $stmt->bind_param("ii", $userId, $roleId);
            
            if ($stmt->execute()) {
                sendSuccess(null, 'Role unassigned from user');
            } else {
                sendError('Failed to unassign role');
            }
            break;
            
        default:
            sendError('Invalid action');
    }
}
