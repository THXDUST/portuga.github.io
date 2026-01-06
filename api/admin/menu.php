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

/**
 * Process uploaded image: validate, resize, compress
 * Returns array with 'success', 'data' (binary), 'mime_type', or 'message' on error
 */
function processImageUpload($file) {
    // Validate file
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $fileType = $file['type'];
    
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Tipo de arquivo inválido. Use JPEG, PNG ou WebP.'];
    }
    
    // Validate file size (5MB max before processing)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'Arquivo muito grande. Tamanho máximo: 5MB.'];
    }
    
    // Check if GD extension is available
    if (!extension_loaded('gd')) {
        // Fallback: store original file without processing
        $imageData = file_get_contents($file['tmp_name']);
        return [
            'success' => true,
            'data' => $imageData,
            'mime_type' => $fileType
        ];
    }
    
    // Load image
    $image = null;
    switch ($fileType) {
        case 'image/jpeg':
        case 'image/jpg':
            $image = @imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/webp':
            $image = @imagecreatefromwebp($file['tmp_name']);
            break;
    }
    
    if (!$image) {
        return ['success' => false, 'message' => 'Falha ao processar imagem.'];
    }
    
    // Get original dimensions
    $origWidth = imagesx($image);
    $origHeight = imagesy($image);
    
    // Calculate new dimensions (max 1024px on longest side)
    $maxDimension = 1024;
    if ($origWidth > $maxDimension || $origHeight > $maxDimension) {
        if ($origWidth > $origHeight) {
            $newWidth = $maxDimension;
            $newHeight = (int)(($maxDimension / $origWidth) * $origHeight);
        } else {
            $newHeight = $maxDimension;
            $newWidth = (int)(($maxDimension / $origHeight) * $origWidth);
        }
    } else {
        $newWidth = $origWidth;
        $newHeight = $origHeight;
    }
    
    // Create resized image
    $resized = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG
    if ($fileType === 'image/png') {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
    }
    
    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
    
    // Convert to JPEG with compression
    ob_start();
    imagejpeg($resized, null, 80); // 80% quality
    $imageData = ob_get_clean();
    
    imagedestroy($image);
    imagedestroy($resized);
    
    return [
        'success' => true,
        'data' => $imageData,
        'mime_type' => 'image/jpeg'
    ];
}

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
                GROUP BY g.id, g.name, g.description, g.parent_id, 
                         g.display_order, g.is_active, g.created_at, p.name
                ORDER BY g.parent_id, g.display_order, g.name
            ");
            $groups = $result->fetchAll(PDO::FETCH_ASSOC);
            sendSuccess($groups);
            break;
            
        case 'items':
            // List all menu items with group info
            $groupId = $_GET['group_id'] ?? null;
            
            $sql = "
                SELECT i.id, i.group_id, i.name, i.description, i.price, i.image_url,
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
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
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
    switch ($action) {
        case 'create-group':
            // Create menu group
            $data = getRequestBody();
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
        case 'update-item':
            // Create or update menu item with optional image upload
            $imageData = null;
            $imageMimeType = null;
            $isUpdate = ($action === 'update-item');
            $data = null; // Initialize to prevent undefined variable warnings
            
            // Check if this is a multipart form upload (with file)
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                // Process image upload
                $result = processImageUpload($_FILES['image']);
                if (!$result['success']) {
                    sendError($result['message']);
                    return;
                }
                $imageData = $result['data'];
                $imageMimeType = $result['mime_type'];
                
                // Get form data from $_POST
                $itemId = $_POST['id'] ?? null;
                $groupId = $_POST['group_id'] ?? null;
                $name = $_POST['name'] ?? null;
                $description = $_POST['description'] ?? null;
                $price = $_POST['price'] ?? null;
                $isAvailable = isset($_POST['is_available']) && $_POST['is_available'] === '1';
                $deliveryEnabled = isset($_POST['delivery_enabled']) && $_POST['delivery_enabled'] === '1';
                $imageUrl = null; // No legacy image_url when uploading file
                $ingredients = $_POST['ingredients'] ?? null;
                $displayOrder = $_POST['display_order'] ?? 0;
            } else {
                // JSON request without file
                $data = getRequestBody();
                $itemId = $data['id'] ?? null;
                $groupId = $data['group_id'] ?? null;
                $name = $data['name'] ?? null;
                $description = $data['description'] ?? null;
                $price = $data['price'] ?? null;
                $isAvailable = $data['is_available'] ?? true;
                $deliveryEnabled = $data['delivery_enabled'] ?? true;
                $imageUrl = $data['image_url'] ?? null;
                $ingredients = $data['ingredients'] ?? null;
                $displayOrder = $data['display_order'] ?? 0;
            }
            
            if (!$groupId || !$name || !$price) {
                sendError('Missing required fields: group_id, name, price');
                return;
            }
            
            if ($isUpdate) {
                if (!$itemId) {
                    sendError('Missing item ID for update');
                    return;
                }
                
                // Build dynamic UPDATE query
                $updates = [];
                $values = [];
                
                $updates[] = "group_id = ?";
                $values[] = $groupId;
                $updates[] = "name = ?";
                $values[] = $name;
                $updates[] = "description = ?";
                $values[] = $description ?: null;
                $updates[] = "price = ?";
                $values[] = $price;
                $updates[] = "is_available = ?";
                $values[] = $isAvailable;
                $updates[] = "delivery_enabled = ?";
                $values[] = $deliveryEnabled;
                
                // Only update image if new one was uploaded
                if ($imageData !== null) {
                    $updates[] = "image_data = ?";
                    $values[] = $imageData;
                    $updates[] = "image_mime_type = ?";
                    $values[] = $imageMimeType;
                }
                
                $values[] = $itemId;
                
                $sql = "UPDATE menu_items SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $conn->prepare($sql);
                
                if ($stmt->execute($values)) {
                    sendSuccess(['id' => $itemId], 'Item updated successfully');
                } else {
                    sendError('Failed to update item');
                }
            } else {
                // INSERT new item
                $stmt = $conn->prepare("
                    INSERT INTO menu_items (group_id, name, description, price, image_url, 
                                           ingredients, is_available, delivery_enabled, display_order,
                                           image_data, image_mime_type)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                if ($stmt->execute([
                    $groupId,
                    $name,
                    $description ?: null,
                    $price,
                    $imageUrl,
                    $ingredients,
                    $isAvailable,
                    $deliveryEnabled,
                    $displayOrder,
                    $imageData,
                    $imageMimeType
                ])) {
                    sendSuccess(['id' => $conn->lastInsertId()], 'Item created successfully');
                } else {
                    sendError('Failed to create item');
                }
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
                      'ingredients', 'is_available', 'delivery_enabled', 'display_order'];
            
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
