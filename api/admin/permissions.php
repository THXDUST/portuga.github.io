<?php
/**
 * Permissions Management API
 * Handles CRUD operations for permissions and role-permission assignments
 */

require_once __DIR__ . '/base.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// For now, skip auth check to allow initial setup
// checkAuth();

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
            // List all permissions
            $result = $conn->query("
                SELECT id, name, description, resource, action 
                FROM permissions 
                ORDER BY resource, action
            ");
            $permissions = $result->fetch_all(MYSQLI_ASSOC);
            sendSuccess($permissions);
            break;
            
        case 'by-role':
            // Get permissions for a specific role
            $roleId = $_GET['role_id'] ?? null;
            if (!$roleId) {
                sendError('Role ID required');
            }
            
            $stmt = $conn->prepare("
                SELECT p.id, p.name, p.description, p.resource, p.action
                FROM permissions p
                INNER JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = ?
                ORDER BY p.resource, p.action
            ");
            $stmt->bind_param("i", $roleId);
            $stmt->execute();
            $result = $stmt->get_result();
            $permissions = $result->fetch_all(MYSQLI_ASSOC);
            sendSuccess($permissions);
            break;
            
        case 'by-resource':
            // Get permissions grouped by resource
            $result = $conn->query("
                SELECT resource, 
                       JSON_ARRAYAGG(
                           JSON_OBJECT(
                               'id', id,
                               'name', name,
                               'action', action,
                               'description', description
                           )
                       ) as permissions
                FROM permissions
                GROUP BY resource
                ORDER BY resource
            ");
            $grouped = [];
            while ($row = $result->fetch_assoc()) {
                $grouped[$row['resource']] = json_decode($row['permissions'], true);
            }
            sendSuccess($grouped);
            break;
            
        default:
            sendError('Invalid action');
    }
}

function handlePost($conn, $action) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'create':
            // Create new permission
            validateRequired($data, ['name', 'resource', 'action']);
            
            $stmt = $conn->prepare("
                INSERT INTO permissions (name, description, resource, action)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("ssss", 
                $data['name'], 
                $data['description'] ?? null, 
                $data['resource'], 
                $data['action']
            );
            
            if ($stmt->execute()) {
                sendSuccess(['id' => $conn->insert_id], 'Permission created successfully');
            } else {
                sendError('Failed to create permission');
            }
            break;
            
        case 'assign':
            // Assign permission to role
            validateRequired($data, ['role_id', 'permission_id']);
            
            $stmt = $conn->prepare("
                INSERT IGNORE INTO role_permissions (role_id, permission_id)
                VALUES (?, ?)
            ");
            $stmt->bind_param("ii", $data['role_id'], $data['permission_id']);
            
            if ($stmt->execute()) {
                sendSuccess(null, 'Permission assigned to role');
            } else {
                sendError('Failed to assign permission');
            }
            break;
            
        case 'assign-multiple':
            // Assign multiple permissions to a role
            validateRequired($data, ['role_id', 'permission_ids']);
            
            $conn->begin_transaction();
            try {
                // First, remove existing permissions for this role
                $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                $stmt->bind_param("i", $data['role_id']);
                $stmt->execute();
                
                // Then add new permissions
                $stmt = $conn->prepare("
                    INSERT INTO role_permissions (role_id, permission_id)
                    VALUES (?, ?)
                ");
                
                foreach ($data['permission_ids'] as $permId) {
                    $stmt->bind_param("ii", $data['role_id'], $permId);
                    $stmt->execute();
                }
                
                $conn->commit();
                sendSuccess(null, 'Permissions updated successfully');
            } catch (Exception $e) {
                $conn->rollback();
                sendError('Failed to update permissions: ' . $e->getMessage());
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
            // Update permission
            validateRequired($data, ['id', 'name', 'resource', 'action']);
            
            $stmt = $conn->prepare("
                UPDATE permissions 
                SET name = ?, description = ?, resource = ?, action = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssssi", 
                $data['name'], 
                $data['description'] ?? null, 
                $data['resource'], 
                $data['action'],
                $data['id']
            );
            
            if ($stmt->execute()) {
                sendSuccess(null, 'Permission updated successfully');
            } else {
                sendError('Failed to update permission');
            }
            break;
            
        default:
            sendError('Invalid action');
    }
}

function handleDelete($conn, $action) {
    switch ($action) {
        case 'delete':
            // Delete permission
            $id = $_GET['id'] ?? null;
            if (!$id) {
                sendError('Permission ID required');
            }
            
            $stmt = $conn->prepare("DELETE FROM permissions WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                sendSuccess(null, 'Permission deleted successfully');
            } else {
                sendError('Failed to delete permission');
            }
            break;
            
        case 'revoke':
            // Revoke permission from role
            $roleId = $_GET['role_id'] ?? null;
            $permId = $_GET['permission_id'] ?? null;
            
            if (!$roleId || !$permId) {
                sendError('Role ID and Permission ID required');
            }
            
            $stmt = $conn->prepare("
                DELETE FROM role_permissions 
                WHERE role_id = ? AND permission_id = ?
            ");
            $stmt->bind_param("ii", $roleId, $permId);
            
            if ($stmt->execute()) {
                sendSuccess(null, 'Permission revoked from role');
            } else {
                sendError('Failed to revoke permission');
            }
            break;
            
        default:
            sendError('Invalid action');
    }
}
