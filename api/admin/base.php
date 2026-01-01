<?php
/**
 * Base helper functions for admin API
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/session.php';

/**
 * Send JSON response
 */
function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Send error response
 */
function sendError($message, $statusCode = 400) {
    sendJSON(['error' => $message], $statusCode);
}

/**
 * Send success response
 */
function sendSuccess($data = null, $message = 'Success') {
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    sendJSON($response);
}

/**
 * Check if user is authenticated (basic check)
 */
function checkAuth() {
    // For now, we'll use a simple session check
    // In production, this should check against the database
    session_start();
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        sendError('Unauthorized', 401);
    }
}

/**
 * Check if user has permission
 */
function checkPermission($conn, $userId, $resource, $action) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM user_roles ur
        INNER JOIN role_permissions rp ON ur.role_id = rp.role_id
        INNER JOIN permissions p ON rp.permission_id = p.id
        WHERE ur.user_id = ? AND p.resource = ? AND p.action = ?
    ");
    $stmt->execute([$userId, $resource, $action]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}

/**
 * Log admin action
 */
function logAdminAction($conn, $userId, $action, $resourceType, $resourceId = null, $details = null) {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $detailsJson = $details ? json_encode($details) : null;
    
    $stmt = $conn->prepare("
        INSERT INTO admin_logs (user_id, action, resource_type, resource_id, details, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $action, $resourceType, $resourceId, $detailsJson, $ipAddress]);
}

/**
 * Get request body as JSON
 */
function getRequestBody() {
    $body = file_get_contents('php://input');
    return json_decode($body, true);
}

/**
 * Validate required fields
 */
function validateRequired($data, $fields) {
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        sendError('Missing required fields: ' . implode(', ', $missing), 400);
    }
}
