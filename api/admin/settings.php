<?php
/**
 * Restaurant Settings API
 * Handles restaurant configuration management
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
        case 'all':
            // Get all settings
            $result = $conn->query("
                SELECT setting_key, setting_value, setting_type, description, updated_at
                FROM restaurant_settings
                ORDER BY setting_key
            ");
            $settings = [];
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $value = $row['setting_value'];
                
                // Parse value based on type
                switch ($row['setting_type']) {
                    case 'boolean':
                        $value = $value === 'true' || $value === '1';
                        break;
                    case 'number':
                        $value = floatval($value);
                        break;
                    case 'json':
                        $value = json_decode($value, true);
                        break;
                }
                
                $settings[$row['setting_key']] = [
                    'value' => $value,
                    'type' => $row['setting_type'],
                    'description' => $row['description'],
                    'updated_at' => $row['updated_at']
                ];
            }
            sendSuccess($settings);
            break;
            
        case 'get':
            // Get single setting
            $key = $_GET['key'] ?? null;
            if (!$key) {
                sendError('Setting key required');
            }
            
            $stmt = $conn->prepare("
                SELECT setting_value, setting_type
                FROM restaurant_settings
                WHERE setting_key = ?
            ");
            $stmt->execute([$key]);
            $setting = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$setting) {
                sendError('Setting not found', 404);
            }
            
            $value = $setting['setting_value'];
            switch ($setting['setting_type']) {
                case 'boolean':
                    $value = $value === 'true' || $value === '1';
                    break;
                case 'number':
                    $value = floatval($value);
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }
            
            sendSuccess($value);
            break;
            
        case 'status':
            // Get restaurant open/closed status
            $stmt = $conn->prepare("
                SELECT setting_value
                FROM restaurant_settings
                WHERE setting_key = 'is_open'
            ");
            $stmt->execute();
            $setting = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $isOpen = ($setting['setting_value'] ?? 'true') === 'true';
            sendSuccess(['is_open' => $isOpen]);
            break;
            
        default:
            sendError('Invalid action');
    }
}

function handleUpdate($conn, $action) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'update':
            // Update single setting
            validateRequired($data, ['key', 'value']);
            
            $value = $data['value'];
            $type = $data['type'] ?? 'string';
            
            // Convert value to string based on type
            if ($type === 'json') {
                $value = json_encode($value);
            } elseif ($type === 'boolean') {
                $value = $value ? 'true' : 'false';
            }
            
            $stmt = $conn->prepare("
                INSERT INTO restaurant_settings (setting_key, setting_value, setting_type, description, updated_by)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value),
                    setting_type = VALUES(setting_type),
                    updated_by = VALUES(updated_by)
            ");
            $updatedBy = $_SESSION['user_id'] ?? null;
            
            if ($stmt->execute([
                $data['key'], 
                $value, 
                $type,
                $data['description'] ?? null,
                $updatedBy
            ])) {
                sendSuccess(null, 'Setting updated successfully');
            } else {
                sendError('Failed to update setting');
            }
            break;
            
        case 'update-multiple':
            // Update multiple settings at once
            validateRequired($data, ['settings']);
            
            $conn->beginTransaction();
            try {
                $stmt = $conn->prepare("
                    INSERT INTO restaurant_settings (setting_key, setting_value, setting_type, updated_by)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        setting_value = VALUES(setting_value),
                        updated_by = VALUES(updated_by)
                ");
                $updatedBy = $_SESSION['user_id'] ?? null;
                
                foreach ($data['settings'] as $key => $setting) {
                    $value = $setting['value'];
                    $type = $setting['type'] ?? 'string';
                    
                    // Convert value to string based on type
                    if ($type === 'json') {
                        $value = json_encode($value);
                    } elseif ($type === 'boolean') {
                        $value = $value ? 'true' : 'false';
                    }
                    
                    $stmt->execute([$key, $value, $type, $updatedBy]);
                }
                
                $conn->commit();
                sendSuccess(null, 'Settings updated successfully');
            } catch (Exception $e) {
                $conn->rollBack();
                sendError('Failed to update settings: ' . $e->getMessage());
            }
            break;
            
        case 'toggle-status':
            // Toggle restaurant open/closed status
            $isOpen = $data['is_open'] ?? null;
            if ($isOpen === null) {
                sendError('is_open field required');
            }
            
            $value = $isOpen ? 'true' : 'false';
            $updatedBy = $_SESSION['user_id'] ?? null;
            
            $stmt = $conn->prepare("
                UPDATE restaurant_settings 
                SET setting_value = ?, updated_by = ?
                WHERE setting_key = 'is_open'
            ");
            
            if ($stmt->execute([$value, $updatedBy])) {
                // Log this action
                logAdminAction($conn, $updatedBy, 'toggle_status', 'settings', null, 
                    ['is_open' => $isOpen]);
                sendSuccess(['is_open' => $isOpen], 'Status updated successfully');
            } else {
                sendError('Failed to update status');
            }
            break;
            
        case 'update-hours':
            // Update operating hours
            validateRequired($data, ['type', 'start', 'end']);
            
            $allowedTypes = ['kitchen_hours', 'pizza_hours', 'delivery_hours'];
            if (!in_array($data['type'], $allowedTypes)) {
                sendError('Invalid hours type');
            }
            
            $hours = json_encode([
                'start' => $data['start'],
                'end' => $data['end']
            ]);
            
            $updatedBy = $_SESSION['user_id'] ?? null;
            
            $stmt = $conn->prepare("
                UPDATE restaurant_settings 
                SET setting_value = ?, updated_by = ?
                WHERE setting_key = ?
            ");
            
            if ($stmt->execute([$hours, $updatedBy, $data['type']])) {
                sendSuccess(null, 'Hours updated successfully');
            } else {
                sendError('Failed to update hours');
            }
            break;
            
        default:
            sendError('Invalid action');
    }
}
