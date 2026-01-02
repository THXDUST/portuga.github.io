<?php
/**
 * Maintenance Mode API
 * Handles system maintenance configuration
 */

require_once __DIR__ . '/base.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
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
        case 'PUT':
            handleUpdate($conn, $action);
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

function handleGet($conn, $action) {
    switch ($action) {
        case 'status':
            // Get current maintenance status
            $result = $conn->query("
                SELECT * FROM maintenance_mode WHERE id = 1
            ");
            $status = $result->fetch(PDO::FETCH_ASSOC);
            
            if (!$status) {
                // Initialize if not exists
                $conn->query("INSERT INTO maintenance_mode (id, is_active) VALUES (1, FALSE)");
                $status = [
                    'id' => 1,
                    'is_active' => false,
                    'restrict_all' => false,
                    'restrict_orders' => false,
                    'restrict_menu' => false,
                    'custom_message' => null
                ];
            }
            
            sendSuccess($status);
            break;
            
        default:
            sendError('Invalid action');
    }
}

function handleUpdate($conn, $action) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'toggle':
            // Toggle maintenance mode
            validateRequired($data, ['is_active']);
            
            $stmt = $conn->prepare("
                UPDATE maintenance_mode 
                SET is_active = ?, 
                    restrict_all = ?,
                    restrict_orders = ?,
                    restrict_menu = ?,
                    custom_message = ?,
                    activated_at = CASE WHEN ? = 1 THEN NOW() ELSE activated_at END,
                    deactivated_at = CASE WHEN ? = 0 THEN NOW() ELSE deactivated_at END,
                    activated_by = ?
                WHERE id = 1
            ");
            
            $isActive = $data['is_active'] ? 1 : 0;
            $restrictAll = $data['restrict_all'] ?? false ? 1 : 0;
            $restrictOrders = $data['restrict_orders'] ?? false ? 1 : 0;
            $restrictMenu = $data['restrict_menu'] ?? false ? 1 : 0;
            $customMessage = $data['custom_message'] ?? null;
            $activatedBy = $_SESSION['user_id'] ?? null;
            
            if ($stmt->execute([
                $isActive,
                $restrictAll,
                $restrictOrders,
                $restrictMenu,
                $customMessage,
                $isActive,
                $isActive,
                $activatedBy
            ])) {
                // Log this action
                logAdminAction($conn, $activatedBy, 'maintenance_mode_toggle', 'maintenance', 1, [
                    'is_active' => $isActive,
                    'restrict_all' => $restrictAll,
                    'restrict_orders' => $restrictOrders,
                    'restrict_menu' => $restrictMenu
                ]);
                
                sendSuccess([
                    'is_active' => $isActive
                ], 'Maintenance mode updated successfully');
            } else {
                sendError('Failed to update maintenance mode');
            }
            break;
            
        default:
            sendError('Invalid action');
    }
}
