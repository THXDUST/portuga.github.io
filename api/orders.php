<?php
/**
 * Enhanced Orders API
 * Handles complete order management with Kanban status
 */

require_once __DIR__ . '/admin/base.php';

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
            // List all orders with filters
            $status = $_GET['status'] ?? null;
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;
            
            $sql = "
                SELECT o.*, u.full_name as customer_name,
                       (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE 1=1
            ";
            
            $params = [];
            $types = '';
            
            if ($status) {
                $sql .= " AND o.status = ?";
                $types .= 's';
                $params[] = $status;
            }
            
            if ($dateFrom) {
                $sql .= " AND DATE(o.created_at) >= ?";
                $types .= 's';
                $params[] = $dateFrom;
            }
            
            if ($dateTo) {
                $sql .= " AND DATE(o.created_at) <= ?";
                $types .= 's';
                $params[] = $dateTo;
            }
            
            $sql .= " ORDER BY o.created_at DESC";
            
            if (!empty($params)) {
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $result = $conn->query($sql);
                $orders = $result->fetchAll(PDO::FETCH_ASSOC);
            }
            
            sendSuccess($orders);
            break;
            
        case 'get':
            // Get single order with all details
            $id = $_GET['id'] ?? null;
            if (!$id) {
                sendError('Order ID required');
            }
            
            $stmt = $conn->prepare("
                SELECT o.*, u.full_name as customer_name, u.email as customer_email
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.id = ?
            ");
            $stmt->execute([$id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                sendError('Order not found', 404);
            }
            
            // Get order items
            $stmt = $conn->prepare("
                SELECT * FROM order_items WHERE order_id = ? ORDER BY id
            ");
            $stmt->execute([$id]);
            $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get order notes
            $stmt = $conn->prepare("
                SELECT on.*, u.full_name as author_name
                FROM order_notes on
                LEFT JOIN users u ON on.user_id = u.id
                WHERE on.order_id = ?
                ORDER BY on.created_at DESC
            ");
            $stmt->execute([$id]);
            $order['notes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendSuccess($order);
            break;
            
        case 'kanban':
            // Get orders organized by Kanban columns
            $result = $conn->query("
                SELECT o.*, u.full_name as customer_name,
                       (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.status != 'cancelado'
                ORDER BY o.created_at DESC
            ");
            
            $kanban = [
                'recebido' => [],
                'em_andamento' => [],
                'finalizado' => []
            ];
            
            while ($order = $result->fetch(PDO::FETCH_ASSOC)) {
                $status = $order['status'];
                if (isset($kanban[$status])) {
                    $kanban[$status][] = $order;
                }
            }
            
            sendSuccess($kanban);
            break;
            
        case 'statistics':
            // Get order statistics
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total_orders,
                    COUNT(CASE WHEN status = 'recebido' THEN 1 END) as recebido,
                    COUNT(CASE WHEN status = 'em_andamento' THEN 1 END) as em_andamento,
                    COUNT(CASE WHEN status = 'finalizado' THEN 1 END) as finalizado,
                    COUNT(CASE WHEN status = 'cancelado' THEN 1 END) as cancelado,
                    SUM(total) as total_revenue,
                    AVG(total) as average_order_value
                FROM orders
                WHERE DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendSuccess($stats);
            break;
            
        default:
            sendError('Invalid action');
    }
}

function handlePost($conn, $action) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'create':
            // Create new order
            validateRequired($data, ['items', 'order_type', 'payment_method']);
            
            // Check if restaurant is open
            // Note: If settings table is empty or is_open setting doesn't exist,
            // we default to allowing orders (fail-open) to prevent blocking legitimate orders
            // during initial setup or if the setting is accidentally deleted.
            $settingsResult = $conn->query("
                SELECT setting_value 
                FROM restaurant_settings 
                WHERE setting_key = 'is_open'
            ");
            
            if ($settingsResult) {
                $setting = $settingsResult->fetch(PDO::FETCH_ASSOC);
                if ($setting) {
                    $isOpen = ($setting['setting_value'] === '1' || $setting['setting_value'] === 'true');
                    
                    if (!$isOpen) {
                        sendError('Desculpe, o restaurante está fechado no momento. Não estamos aceitando pedidos.', 400);
                        return;
                    }
                }
            }
            // If no setting found, default to open (fail-open behavior)
            
            $conn->beginTransaction();
            try {
                // Generate order number
                $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Calculate totals
                $subtotal = 0;
                foreach ($data['items'] as $item) {
                    $subtotal += $item['price'] * $item['quantity'];
                }
                
                $deliveryFee = $data['delivery_fee'] ?? 0;
                $total = $subtotal + $deliveryFee;
                
                // Insert order
                $stmt = $conn->prepare("
                    INSERT INTO orders (
                        user_id, order_number, table_number, status, order_type, payment_method,
                        change_for, delivery_address, delivery_distance, delivery_fee,
                        pickup_time, production_start_time, subtotal, total, notes,
                        pickup_name, customer_name,
                        phone_number, cep, address_street, address_number, 
                        address_complement, address_neighborhood, address_city, address_state
                    ) VALUES (?, ?, ?, 'recebido', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $userId = $data['user_id'] ?? null;
                $tableNumber = $data['table_number'] ?? null;
                $changeFor = $data['change_for'] ?? null;
                $deliveryAddress = $data['delivery_address'] ?? null;
                $deliveryDistance = $data['delivery_distance'] ?? null;
                $pickupTime = $data['pickup_time'] ?? null;
                $productionStartTime = $data['production_start_time'] ?? null;
                $notes = $data['notes'] ?? null;
                $pickupName = $data['pickup_name'] ?? null;
                $customerName = $data['customer_name'] ?? null;
                
                // New address fields
                $phoneNumber = $data['phone_number'] ?? null;
                $cep = $data['cep'] ?? null;
                $addressStreet = $data['address_street'] ?? null;
                $addressNumber = $data['address_number'] ?? null;
                $addressComplement = $data['address_complement'] ?? null;
                $addressNeighborhood = $data['address_neighborhood'] ?? null;
                $addressCity = $data['address_city'] ?? null;
                $addressState = $data['address_state'] ?? null;
                
                // Log order creation for debugging
                error_log("Creating order - Type: {$data['order_type']}, Table: " . ($tableNumber ?? 'NULL') . ", User: " . ($userId ?? 'NULL'));
                
                $stmt->execute([
                    $userId, $orderNumber, $tableNumber, $data['order_type'], $data['payment_method'],
                    $changeFor, $deliveryAddress, $deliveryDistance, $deliveryFee,
                    $pickupTime, $productionStartTime, $subtotal, $total, $notes,
                    $pickupName, $customerName,
                    $phoneNumber, $cep, $addressStreet, $addressNumber,
                    $addressComplement, $addressNeighborhood, $addressCity, $addressState
                ]);
                $orderId = $conn->lastInsertId();
                
                // Insert order items
                $stmt = $conn->prepare("
                    INSERT INTO order_items (order_id, menu_item_id, item_name, item_price, quantity, subtotal, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($data['items'] as $item) {
                    $itemSubtotal = $item['price'] * $item['quantity'];
                    $menuItemId = $item['menu_item_id'] ?? null;
                    $itemNotes = $item['notes'] ?? null;
                    
                    $stmt->execute([$orderId, $menuItemId, $item['name'], $item['price'],
                        $item['quantity'], $itemSubtotal, $itemNotes
                    ]);
                }
                
                $conn->commit();
                sendSuccess([
                    'id' => $orderId,
                    'order_number' => $orderNumber
                ], 'Order created successfully');
                
            } catch (Exception $e) {
                $conn->rollBack();
                sendError('Failed to create order: ' . $e->getMessage());
            }
            break;
            
        case 'add-note':
            // Add note to order
            validateRequired($data, ['order_id', 'note']);
            
            $stmt = $conn->prepare("
                INSERT INTO order_notes (order_id, user_id, note)
                VALUES (?, ?, ?)
            ");
            $userId = $_SESSION['user_id'] ?? null;
            if ($stmt->execute([$data['order_id'], $userId, $data['note']])) {
                sendSuccess(['id' => $conn->lastInsertId()], 'Note added successfully');
            } else {
                sendError('Failed to add note');
            }
            break;
            
        default:
            sendError('Invalid action');
    }
}

function handlePut($conn, $action) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'update-status':
            // Update order status (for Kanban drag & drop)
            validateRequired($data, ['id', 'status']);
            
            $allowedStatuses = ['recebido', 'em_andamento', 'finalizado', 'cancelado'];
            if (!in_array($data['status'], $allowedStatuses)) {
                sendError('Invalid status');
            }
            
            $completedAt = $data['status'] === 'finalizado' ? date('Y-m-d H:i:s') : null;
            
            $stmt = $conn->prepare("
                UPDATE orders 
                SET status = ?, completed_at = ?
                WHERE id = ?
            ");
            if ($stmt->execute([$data['status'], $completedAt, $data['id']])) {
                // Add automatic note
                $noteStmt = $conn->prepare("
                    INSERT INTO order_notes (order_id, note)
                    VALUES (?, ?)
                ");
                $note = "Status alterado para: " . $data['status'];
                $noteStmt->execute([$data['id'], $note]);
                
                sendSuccess(null, 'Status updated successfully');
            } else {
                sendError('Failed to update status');
            }
            break;
            
        case 'update':
            // Update order details
            validateRequired($data, ['id']);
            
            $updates = [];
            $values = [];
            
            $allowedFields = ['order_type', 'payment_method', 'change_for', 
                            'delivery_address', 'delivery_distance', 'delivery_fee',
                            'pickup_time', 'production_start_time', 'notes'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }
            
            if (empty($updates)) {
                sendError('No fields to update');
            }
            
            $sql = "UPDATE orders SET " . implode(', ', $updates) . " WHERE id = ?";
            $values[] = $data['id'];
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt->execute($values)) {
                sendSuccess(null, 'Order updated successfully');
            } else {
                sendError('Failed to update order');
            }
            break;
            
        default:
            sendError('Invalid action');
    }
}

function handleDelete($conn, $action) {
    switch ($action) {
        case 'cancel':
            // Cancel order (soft delete)
            $id = $_GET['id'] ?? null;
            if (!$id) {
                sendError('Order ID required');
            }
            
            $stmt = $conn->prepare("UPDATE orders SET status = 'cancelado' WHERE id = ?");
            if ($stmt->execute([$id])) {
                sendSuccess(null, 'Order cancelled successfully');
            } else {
                sendError('Failed to cancel order');
            }
            break;
            
        case 'delete':
            // Permanently delete order
            $id = $_GET['id'] ?? null;
            if (!$id) {
                sendError('Order ID required');
            }
            
            $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
            if ($stmt->execute([$id])) {
                sendSuccess(null, 'Order deleted successfully');
            } else {
                sendError('Failed to delete order');
            }
            break;
            
        default:
            sendError('Invalid action');
    }
}
