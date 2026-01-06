<?php
/**
 * Menu Management API
 * Handles menu groups and items CRUD operations
 */

require_once __DIR__ . '/base.php';

// Debug mode - set to false in production
// WARNING: Debug mode logs detailed request data including form fields and file information.
// Only enable temporarily for troubleshooting and disable immediately after diagnosis.
define('MENU_DEBUG_MODE', false);

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
                // Debug logging for multipart form data (only in debug mode)
                if (MENU_DEBUG_MODE) {
                    error_log('DEBUG: Processing multipart form with image upload');
                    error_log('DEBUG: $_POST data: ' . json_encode($_POST));
                    error_log('DEBUG: $_FILES data: ' . json_encode(array_map(function($file) {
                        return ['name' => $file['name'], 'type' => $file['type'], 'size' => $file['size'], 'error' => $file['error']];
                    }, $_FILES)));
                }
                
                // Process image upload
                $result = processImageUpload($_FILES['image']);
                if (!$result['success']) {
                    sendError($result['message']);
                    return;
                }
                $imageData = $result['data'];
                $imageMimeType = $result['mime_type'];
                
                // Get form data from $_POST and sanitize with improved validation
                $itemId = isset($_POST['id']) && $_POST['id'] !== '' ? $_POST['id'] : null;
                
                // Improved numeric validation - trim whitespace and handle edge cases
                $groupIdRaw = isset($_POST['group_id']) ? trim($_POST['group_id']) : '';
                $groupId = (is_numeric($groupIdRaw) && $groupIdRaw !== '') ? intval($groupIdRaw) : null;
                
                $name = isset($_POST['name']) ? trim($_POST['name']) : null;
                $description = isset($_POST['description']) ? trim($_POST['description']) : null;
                
                $priceRaw = isset($_POST['price']) ? trim($_POST['price']) : '';
                $price = (is_numeric($priceRaw) && $priceRaw !== '') ? floatval($priceRaw) : null;
                
                $isAvailable = isset($_POST['is_available']) && $_POST['is_available'] === '1';
                $deliveryEnabled = isset($_POST['delivery_enabled']) && $_POST['delivery_enabled'] === '1';
                $imageUrl = null; // No legacy image_url when uploading file
                $ingredients = isset($_POST['ingredients']) ? trim($_POST['ingredients']) : null;
                
                $displayOrderRaw = isset($_POST['display_order']) ? trim($_POST['display_order']) : '0';
                $displayOrder = (is_numeric($displayOrderRaw) && $displayOrderRaw !== '') ? intval($displayOrderRaw) : 0;
                
                // Debug log parsed values (only in debug mode)
                if (MENU_DEBUG_MODE) {
                    error_log('DEBUG: Parsed values - group_id: ' . var_export($groupId, true) . ', name: ' . var_export($name, true) . ', price: ' . var_export($price, true));
                }
            } else {
                // JSON request without file
                $data = getRequestBody();
                $itemId = isset($data['id']) && $data['id'] !== '' ? $data['id'] : null;
                $groupId = isset($data['group_id']) && is_numeric($data['group_id']) ? intval($data['group_id']) : null;
                $name = isset($data['name']) ? trim($data['name']) : null;
                $description = isset($data['description']) ? trim($data['description']) : null;
                $price = isset($data['price']) && is_numeric($data['price']) ? floatval($data['price']) : null;
                $isAvailable = $data['is_available'] ?? true;
                $deliveryEnabled = $data['delivery_enabled'] ?? true;
                $imageUrl = $data['image_url'] ?? null;
                $ingredients = isset($data['ingredients']) ? trim($data['ingredients']) : null;
                $displayOrder = isset($data['display_order']) && is_numeric($data['display_order']) ? intval($data['display_order']) : 0;
            }
            
            // Strict validation with proper type checking and detailed error messages
            $errors = [];
            $debugInfo = [];
            
            if ($groupId === null || $groupId <= 0) {
                $errors[] = 'grupo (deve ser um número válido maior que zero)';
                $debugInfo['group_id'] = isset($_POST['group_id']) ? $_POST['group_id'] : (isset($data['group_id']) ? $data['group_id'] : 'NOT SET');
            }
            
            if (empty($name)) {
                $errors[] = 'nome (não pode estar vazio)';
                $debugInfo['name'] = isset($_POST['name']) ? $_POST['name'] : (isset($data['name']) ? $data['name'] : 'NOT SET');
            }
            
            if ($price === null || $price < 0) {
                $errors[] = 'preço (deve ser um número válido maior ou igual a zero)';
                $debugInfo['price'] = isset($_POST['price']) ? $_POST['price'] : (isset($data['price']) ? $data['price'] : 'NOT SET');
            }
            
            if (!empty($errors)) {
                // Log detailed debug info server-side only
                if (MENU_DEBUG_MODE && !empty($debugInfo)) {
                    error_log('DEBUG: Validation failed - Valores recebidos: ' . json_encode($debugInfo));
                }
                
                // Return generic error message to client (without exposing internal values)
                $errorMessage = 'Campos obrigatórios inválidos ou ausentes: ' . implode(', ', $errors);
                sendError($errorMessage);
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
