<?php
/**
 * Menu Management API
 * Handles menu groups and items CRUD operations
 */

// CRITICAL: Disable error display to prevent HTML output in JSON responses
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Set JSON content type immediately to ensure only JSON is returned
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/base.php';

// Debug mode - set to false in production
// WARNING: Debug mode logs detailed request data including form fields and file information.
// Only enable temporarily for troubleshooting and disable immediately after diagnosis.
// Can be controlled via MENU_DEBUG_MODE environment variable or hardcoded below.
// Set environment variable: MENU_DEBUG_MODE=true or export MENU_DEBUG_MODE=true
define('MENU_DEBUG_MODE', filter_var(getenv('MENU_DEBUG_MODE') ?: 'true', FILTER_VALIDATE_BOOLEAN));

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

/**
 * Debug logging function for troubleshooting (only when MENU_DEBUG_MODE is true)
 */
function debugLog($message, $data = null) {
    if (!MENU_DEBUG_MODE) return;
    
    $logFile = __DIR__ . '/debug_upload.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    if ($data !== null) {
        $logMessage .= ": " . print_r($data, true);
    }
    @file_put_contents($logFile, $logMessage . "\n", FILE_APPEND);
}

/**
 * Process uploaded image: validate, resize, compress
 * Returns array with 'success', 'data' (binary), 'mime_type', or 'message' on error
 */
function processImageUpload($file) {
    try {
        // Log upload attempt in debug mode
        debugLog('processImageUpload called', [
            'file_exists' => isset($file['tmp_name']),
            'error_code' => $file['error'] ?? 'not set'
        ]);
        
        // Validate that file was uploaded
        if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            debugLog('File validation failed: temporary file not found');
            return ['success' => false, 'message' => 'Arquivo temporário não encontrado. Tente novamente.'];
        }
        
        // Check for upload errors
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errorCode = $file['error'] ?? -1;
            debugLog('Upload error detected', ['error_code' => $errorCode]);
            
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'Arquivo excede o limite do servidor (upload_max_filesize)',
                UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o limite do formulário',
                UPLOAD_ERR_PARTIAL => 'Upload incompleto. Tente novamente.',
                UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado',
                UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada no servidor',
                UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar arquivo no disco',
                UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão PHP'
            ];
            
            $errorMessage = $errorMessages[$errorCode] ?? 'Erro desconhecido no upload (código: ' . $errorCode . ')';
            return ['success' => false, 'message' => $errorMessage];
        }
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $fileType = $file['type'] ?? 'unknown';
        
        if (!in_array($fileType, $allowedTypes)) {
            debugLog('Invalid file type', ['type' => $fileType]);
            return ['success' => false, 'message' => 'Tipo de arquivo inválido. Use JPEG, PNG ou WebP.'];
        }
        
        // Validate file size (5MB max before processing)
        if (!isset($file['size']) || $file['size'] > 5 * 1024 * 1024) {
            debugLog('File too large', ['size' => $file['size'] ?? 'unknown']);
            return ['success' => false, 'message' => 'Arquivo muito grande. Tamanho máximo: 5MB.'];
        }
        
        // Check if GD extension is available
        if (!extension_loaded('gd')) {
            debugLog('GD extension not loaded, using fallback');
            // Fallback: store original file without processing
            $imageData = @file_get_contents($file['tmp_name']);
            if ($imageData === false) {
                return ['success' => false, 'message' => 'Erro ao ler arquivo de imagem'];
            }
            return [
                'success' => true,
                'data' => base64_encode($imageData),
                'mime_type' => $fileType
            ];
        }
        
        // Load image based on type
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
            debugLog('Failed to create image from file');
            return ['success' => false, 'message' => 'Falha ao processar imagem. Arquivo pode estar corrompido.'];
        }
        
        // Get original dimensions
        $origWidth = @imagesx($image);
        $origHeight = @imagesy($image);
        
        if ($origWidth === false || $origHeight === false) {
            @imagedestroy($image);
            return ['success' => false, 'message' => 'Não foi possível determinar dimensões da imagem'];
        }
        
        debugLog('Image loaded successfully', [
            'width' => $origWidth,
            'height' => $origHeight
        ]);
        
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
        $resized = @imagecreatetruecolor($newWidth, $newHeight);
        if (!$resized) {
            @imagedestroy($image);
            return ['success' => false, 'message' => 'Erro ao criar imagem redimensionada (memória insuficiente?)'];
        }
        
        // Preserve transparency for PNG
        if ($fileType === 'image/png') {
            @imagealphablending($resized, false);
            @imagesavealpha($resized, true);
        }
        
        // Resize image
        $resizeResult = @imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        if (!$resizeResult) {
            @imagedestroy($image);
            @imagedestroy($resized);
            return ['success' => false, 'message' => 'Erro ao redimensionar imagem'];
        }
        
        // Convert to JPEG with compression
        ob_start();
        $jpegResult = @imagejpeg($resized, null, 80); // 80% quality
        $imageData = ob_get_clean();
        
        // Clean up
        @imagedestroy($image);
        @imagedestroy($resized);
        
        if (!$jpegResult || empty($imageData)) {
            return ['success' => false, 'message' => 'Erro ao converter imagem para JPEG'];
        }
        
        debugLog('Image processed successfully', ['size' => strlen($imageData)]);
        
        return [
            'success' => true,
            'data' => base64_encode($imageData),
            'mime_type' => 'image/jpeg'
        ];
        
    } catch (Exception $e) {
        debugLog('Exception in processImageUpload', ['error' => $e->getMessage()]);
        return ['success' => false, 'message' => 'Erro ao processar imagem: ' . $e->getMessage()];
    } catch (Error $e) {
        debugLog('Error in processImageUpload', ['error' => $e->getMessage()]);
        return ['success' => false, 'message' => 'Erro crítico ao processar imagem'];
    }
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
            
            // Log request start in debug mode
            debugLog('Processing ' . $action, [
                'has_files' => !empty($_FILES),
                'has_post' => !empty($_POST)
            ]);
            
            // Check if this is a multipart form upload (with file)
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                // Debug logging for multipart form data (only in debug mode)
                debugLog('Processing multipart form with image upload', [
                    'POST_keys' => array_keys($_POST),
                    'FILES_keys' => array_keys($_FILES),
                    'image_error' => $_FILES['image']['error'],
                    'image_size' => $_FILES['image']['size'],
                    'image_type' => $_FILES['image']['type']
                ]);
                
                // Process image upload
                $result = processImageUpload($_FILES['image']);
                if (!$result['success']) {
                    debugLog('Image upload failed', ['message' => $result['message']]);
                    sendError($result['message']);
                    return;
                }
                $imageData = $result['data'];
                $imageMimeType = $result['mime_type'];
                
                debugLog('Image processed successfully');
                
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
                
                debugLog('Parsed form data', [
                    'group_id' => $groupId,
                    'name' => $name,
                    'price' => $price
                ]);
            } else {
                // JSON request without file
                debugLog('Processing JSON request without image');
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
            
            if ($groupId === null || $groupId <= 0) {
                $errors[] = 'grupo (deve ser um número válido maior que zero)';
                debugLog('Validation failed: invalid group_id', ['value' => $groupId]);
            }
            
            if (empty($name)) {
                $errors[] = 'nome (não pode estar vazio)';
                debugLog('Validation failed: empty name');
            }
            
            if ($price === null || $price < 0) {
                $errors[] = 'preço (deve ser um número válido maior ou igual a zero)';
                debugLog('Validation failed: invalid price', ['value' => $price]);
            }
            
            if (!empty($errors)) {
                // Return generic error message to client (without exposing internal values)
                $errorMessage = 'Campos obrigatórios inválidos ou ausentes: ' . implode(', ', $errors);
                debugLog('Validation failed', ['errors' => $errors]);
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
                
                try {
                    $stmt = $conn->prepare($sql);
                    
                    if (!$stmt->execute($values)) {
                        $errorInfo = $stmt->errorInfo();
                        debugLog('UPDATE failed', ['errorInfo' => $errorInfo, 'sql' => $sql]);
                        error_log('UPDATE menu_items failed: ' . print_r($errorInfo, true));
                        
                        if (MENU_DEBUG_MODE) {
                            sendError('Failed to update item: ' . $errorInfo[2], 500);
                        } else {
                            sendError('Failed to update item', 500);
                        }
                        return;
                    }
                    
                    sendSuccess(['id' => $itemId], 'Item updated successfully');
                } catch (PDOException $e) {
                    debugLog('UPDATE exception', ['message' => $e->getMessage()]);
                    error_log('UPDATE menu_items exception: ' . $e->getMessage());
                    
                    if (MENU_DEBUG_MODE) {
                        sendError('Database error updating item: ' . $e->getMessage(), 500);
                    } else {
                        sendError('Erro no banco de dados ao atualizar item. Tente novamente.', 500);
                    }
                    return;
                }
            } else {
                // INSERT new item
                $sql = "INSERT INTO menu_items (group_id, name, description, price, image_url, 
                                       ingredients, is_available, delivery_enabled, display_order,
                                       image_data, image_mime_type)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                try {
                    $stmt = $conn->prepare($sql);
                    
                    if (!$stmt->execute([
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
                        $errorInfo = $stmt->errorInfo();
                        debugLog('INSERT failed', ['errorInfo' => $errorInfo, 'sql' => $sql]);
                        error_log('INSERT menu_items failed: ' . print_r($errorInfo, true));
                        
                        if (MENU_DEBUG_MODE) {
                            sendError('Failed to create item: ' . $errorInfo[2], 500);
                        } else {
                            sendError('Failed to create item', 500);
                        }
                        return;
                    }
                    
                    sendSuccess(['id' => $conn->lastInsertId()], 'Item created successfully');
                } catch (PDOException $e) {
                    debugLog('INSERT exception', ['message' => $e->getMessage()]);
                    error_log('INSERT menu_items exception: ' . $e->getMessage());
                    
                    if (MENU_DEBUG_MODE) {
                        sendError('Database error creating item: ' . $e->getMessage(), 500);
                    } else {
                        sendError('Erro no banco de dados ao criar item. Tente novamente.', 500);
                    }
                    return;
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

// ========================================
// MAIN EXECUTION
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Wrap entire execution in try-catch to prevent any uncaught errors
try {
    $conn = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    debugLog('Request received', [
        'method' => $method,
        'action' => $action
    ]);
    
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
} catch (PDOException $e) {
    // Database errors - log detailed error and return appropriate message
    $errorMessage = 'Database error: ' . $e->getMessage();
    error_log($errorMessage);
    debugLog('Database error', [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    // In debug mode, expose actual error for troubleshooting
    if (MENU_DEBUG_MODE) {
        sendError('Erro no banco de dados: ' . $e->getMessage(), 500);
    } else {
        sendError('Erro no banco de dados. Tente novamente.', 500);
    }
} catch (Exception $e) {
    // Generic errors - log and return detailed message
    $errorMessage = 'Exception: ' . $e->getMessage();
    error_log($errorMessage);
    debugLog('Exception caught', [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    sendError('Erro ao processar requisição: ' . $e->getMessage(), 500);
} catch (Error $e) {
    // Fatal errors - log detailed error
    $errorMessage = 'Fatal error: ' . $e->getMessage();
    error_log($errorMessage);
    debugLog('Fatal error caught', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    sendError('Erro crítico no servidor', 500);
}
