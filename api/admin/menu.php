<?php
/**
 * Menu Management API
 * Handles menu groups and items CRUD operations
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
        case 'groups':
            // List all menu groups with subgroups
            $result = $conn->query("
                SELECT g.id, g.name, g.description, g.parent_id, 
                       g.display_order, g.is_active, g.created_at,
                       p.name as parent_name,
                       COUNT(DISTINCT i.id) as item_count,
                       COUNT(DISTINCT sg.id) as subgroup_count
                FROM menu_groups g
                LEFT JOIN menu_groups p ON g.parent_id = p.id
                LEFT JOIN menu_items i ON g.id = i.group_id
                LEFT JOIN menu_groups sg ON g.id = sg.parent_id
                GROUP BY g.id
                ORDER BY g.parent_id, g.display_order, g.name
            ");
            $groups = $result->fetchAll(PDO::FETCH_ASSOC);
            sendSuccess($groups);
            break;
            
        case 'items':
            // List all menu items with group info
            $groupId = $_GET['group_id'] ?? null;
            
            $sql = "
                SELECT i.id, i.name, i.description, i.price, i.image_url,
                       i.ingredients, i.is_available, i.display_order, i.created_at,
                       g.name as group_name
                FROM menu_items i
                INNER JOIN menu_groups g ON i.group_id = g.id
            ";
            
            if ($groupId) {
                $sql .= " WHERE i.group_id = ?";
                $stmt = $conn->prepare($sql . " ORDER BY i.display_order, i.name");
                $stmt->execute([$groupId]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $result = $conn->query($sql . " ORDER BY g.name, i.display_order, i.name");
                $items = $result->fetchAll(PDO::FETCH_ASSOC);
            }
            
            sendSuccess($items);
            break;
            
        case 'item':
            // Get single menu item
            $id = $_GET['id'] ?? null;
            if (!$id) {
                sendError('Item ID required');
            }
            
            $stmt = $conn->prepare("
                SELECT i.*, g.name as group_name
                FROM menu_items i
                INNER JOIN menu_groups g ON i.group_id = g.id
                WHERE i.id = ?
            ");
            $stmt->execute([$id]);
            $item = $result->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                sendError('Item not found', 404);
            }
            
            sendSuccess($item);
            break;
            
        case 'full-menu':
            // Get complete menu structure (groups with items)
            $result = $conn->query("
                SELECT id, name, description, parent_id, display_order, is_active
                FROM menu_groups
                WHERE is_active = TRUE
                ORDER BY parent_id, display_order, name
            ");
            $groups = $result->fetchAll(PDO::FETCH_ASSOC);
            
            $result = $conn->query("
                SELECT id, group_id, name, description, price, image_url, 
                       ingredients, is_available, display_order
                FROM menu_items
                WHERE is_available = TRUE
                ORDER BY display_order, name
            ");
            $items = $result->fetchAll(PDO::FETCH_ASSOC);
            
            // Organize items by group
            $menu = [];
            foreach ($groups as $group) {
                $group['items'] = array_filter($items, function($item) use ($group) {
                    return $item['group_id'] == $group['id'];
                });
                $menu[] = $group;
            }
            
            sendSuccess($menu);
            break;
            
        default:
            sendError('Invalid action');
    }
}

function handlePost($conn, $action) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'create-group':
            // Create menu group
            validateRequired($data, ['name']);
            
            $stmt = $conn->prepare("
                INSERT INTO menu_groups (name, description, parent_id, display_order, is_active)
                VALUES (?, ?, ?, ?, ?)
            ");
            $isActive = $data['is_active'] ?? true;
            
            if ($stmt->execute([
                $data['name'], 
                $data['description'] ?? null,
                $data['parent_id'] ?? null,
                $data['display_order'] ?? 0,
                $isActive
            ])) {
                sendSuccess(['id' => $conn->lastInsertId()], 'Group created successfully');
            } else {
                sendError('Failed to create group');
            }
            break;
            
        case 'create-item':
            // Create menu item
            validateRequired($data, ['group_id', 'name', 'price']);
            
            $stmt = $conn->prepare("
                INSERT INTO menu_items (group_id, name, description, price, image_url, 
                                       ingredients, is_available, display_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $isAvailable = $data['is_available'] ?? true;
            
            if ($stmt->execute([
                $data['group_id'],
                $data['name'],
                $data['description'] ?? null,
                $data['price'],
                $data['image_url'] ?? null,
                $data['ingredients'] ?? null,
                $isAvailable,
                $data['display_order'] ?? 0
            ])) {
                sendSuccess(['id' => $conn->lastInsertId()], 'Item created successfully');
            } else {
                sendError('Failed to create item');
            }
            break;
            
        default:
            sendError('Invalid action');
    }
}

function handlePut($conn, $action) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'update-group':
            // Update menu group
            validateRequired($data, ['id']);
            
            $updates = [];
            $values = [];
            
            foreach (['name', 'description', 'parent_id', 'display_order', 'is_active'] as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }
            
            if (empty($updates)) {
                sendError('No fields to update');
            }
            
            $sql = "UPDATE menu_groups SET " . implode(', ', $updates) . " WHERE id = ?";
            $values[] = $data['id'];
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt->execute($values)) {
                sendSuccess(null, 'Group updated successfully');
            } else {
                sendError('Failed to update group');
            }
            break;
            
        case 'update-item':
            // Update menu item
            validateRequired($data, ['id']);
            
            $updates = [];
            $types = '';
            $values = [];
            
            $fields = ['group_id', 'name', 'description', 'price', 'image_url', 
                      'ingredients', 'is_available', 'display_order'];
            
            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }
            
            if (empty($updates)) {
                sendError('No fields to update');
            }
            
            $sql = "UPDATE menu_items SET " . implode(', ', $updates) . " WHERE id = ?";
            $values[] = $data['id'];
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt->execute($values)) {
                sendSuccess(null, 'Item updated successfully');
            } else {
                sendError('Failed to update item');
            }
            break;
            
        case 'reorder':
            // Reorder items/groups
            validateRequired($data, ['items']);
            
            $conn->beginTransaction();
            try {
                $table = $data['type'] === 'group' ? 'menu_groups' : 'menu_items';
                $stmt = $conn->prepare("UPDATE $table SET display_order = ? WHERE id = ?");
                
                foreach ($data['items'] as $item) {
                    $stmt->execute([$item['order'], $item['id']]);
                }
                
                $conn->commit();
                sendSuccess(null, 'Order updated successfully');
            } catch (Exception $e) {
                $conn->rollBack();
                sendError('Failed to update order: ' . $e->getMessage());
            }
            break;
            
        default:
            sendError('Invalid action');
    }
}

function handleDelete($conn, $action) {
    switch ($action) {
        case 'delete-group':
            // Delete menu group
            $id = $_GET['id'] ?? null;
            if (!$id) {
                sendError('Group ID required');
            }
            
            // Check if group has items
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM menu_items WHERE group_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                sendError('Cannot delete group with items. Please move or delete items first.', 400);
            }
            
            $stmt = $conn->prepare("DELETE FROM menu_groups WHERE id = ?");
            
            if ($stmt->execute([$id])) {
                sendSuccess(null, 'Group deleted successfully');
            } else {
                sendError('Failed to delete group');
            }
            break;
            
        case 'delete-item':
            // Delete menu item
            $id = $_GET['id'] ?? null;
            if (!$id) {
                sendError('Item ID required');
            }
            
            $stmt = $conn->prepare("DELETE FROM menu_items WHERE id = ?");
            
            if ($stmt->execute([$id])) {
                sendSuccess(null, 'Item deleted successfully');
            } else {
                sendError('Failed to delete item');
            }
            break;
            
        default:
            sendError('Invalid action');
    }
}
