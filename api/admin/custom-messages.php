<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);

// Start output buffering to prevent any accidental output
ob_start();

// Set JSON header
header('Content-Type: application/json');

// Include database connection
require_once __DIR__ . '/../config.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get action from query string
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            getCustomMessage($pdo);
            break;
        case 'save':
            saveCustomMessage($pdo);
            break;
        case 'list':
            listCustomMessages($pdo);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}

/**
 * Get a custom message by key
 */
function getCustomMessage($pdo) {
    $messageKey = $_GET['message_key'] ?? '';
    
    if (empty($messageKey)) {
        throw new Exception('Message key is required');
    }
    
    $stmt = $pdo->prepare("
        SELECT id, message_key, message_title, message_content, is_active, created_at, updated_at
        FROM custom_messages
        WHERE message_key = ?
    ");
    
    $stmt->execute([$messageKey]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => $message ?: null
    ]);
}

/**
 * Save or update a custom message
 */
function saveCustomMessage($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $messageKey = $input['message_key'] ?? '';
    $messageTitle = $input['message_title'] ?? '';
    $messageContent = $input['message_content'] ?? '';
    $isActive = isset($input['is_active']) ? (bool)$input['is_active'] : true;
    
    if (empty($messageKey)) {
        throw new Exception('Message key is required');
    }
    
    if (empty($messageTitle)) {
        throw new Exception('Message title is required');
    }
    
    // Check if message exists
    $stmt = $pdo->prepare("SELECT id FROM custom_messages WHERE message_key = ?");
    $stmt->execute([$messageKey]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Update existing message
        $stmt = $pdo->prepare("
            UPDATE custom_messages
            SET message_title = ?,
                message_content = ?,
                is_active = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE message_key = ?
        ");
        
        $stmt->execute([
            $messageTitle,
            $messageContent,
            $isActive ? 1 : 0,
            $messageKey
        ]);
    } else {
        // Insert new message
        $stmt = $pdo->prepare("
            INSERT INTO custom_messages (message_key, message_title, message_content, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        
        $stmt->execute([
            $messageKey,
            $messageTitle,
            $messageContent,
            $isActive ? 1 : 0
        ]);
    }
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Message saved successfully'
    ]);
}

/**
 * List all custom messages
 */
function listCustomMessages($pdo) {
    $stmt = $pdo->query("
        SELECT id, message_key, message_title, is_active, created_at, updated_at
        FROM custom_messages
        ORDER BY message_key ASC
    ");
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => $messages
    ]);
}
