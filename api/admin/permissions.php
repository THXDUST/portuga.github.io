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
    error_log('Permissions API Error: ' . $e->getMessage());
    sendError($e->getMessage(), 500);
} catch (Error $e) {
    error_log('Permissions API Fatal Error: ' . $e->getMessage());
    sendError('Internal server error', 500);
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
            $permissions = $result->fetchAll(PDO::FETCH_ASSOC);
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
            $stmt->execute([$roleId]);
            $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendSuccess($permissions);
            break;
            
        case 'by-resource':
            // Get permissions grouped by resource (MySQL 5.7 compatible)
            $result = $conn->query("
                SELECT id, name, action, description, resource
                FROM permissions
                ORDER BY resource, action
            ");
            
            $grouped = [];
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $resource = $row['resource'];
                if (!isset($grouped[$resource])) {
                    $grouped[$resource] = [];
                }
                $grouped[$resource][] = [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'action' => $row['action'],
                    'description' => $row['description']
                ];
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
            
            if ($stmt->execute([
                $data['name'], 
                $data['description'] ?? null, 
                $data['resource'], 
                $data['action']
            ])) {
                sendSuccess(['id' => $conn->lastInsertId()], 'Permission created successfully');
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
            
            if ($stmt->execute([$data['role_id'], $data['permission_id']])) {
                sendSuccess(null, 'Permission assigned to role');
            } else {
                sendError('Failed to assign permission');
            }
            break;
            
        case 'assign-multiple':
            // Assign multiple permissions to a role
            validateRequired($data, ['role_id', 'permission_ids']);
            
            $conn->beginTransaction();
            try {
                // First, remove existing permissions for this role
                $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                $stmt->execute([$data['role_id']]);
                
                // Then add new permissions
                $stmt = $conn->prepare("
                    INSERT INTO role_permissions (role_id, permission_id)
                    VALUES (?, ?)
                ");
                
                foreach ($data['permission_ids'] as $permId) {
                    $stmt->execute([$data['role_id'], $permId]);
                }
                
                $conn->commit();
                sendSuccess(null, 'Permissions updated successfully');
            } catch (Exception $e) {
                $conn->rollBack();
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
            
            if ($stmt->execute([
                $data['name'], 
                $data['description'] ?? null, 
                $data['resource'], 
                $data['action'],
                $data['id']
            ])) {
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
            
            if ($stmt->execute([$id])) {
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
            
            if ($stmt->execute([$roleId, $permId])) {
                sendSuccess(null, 'Permission revoked from role');
            } else {
                sendError('Failed to revoke permission');
            }
            break;
            
        default:
            sendError('Invalid action');
    }
}
