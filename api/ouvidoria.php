<?php
/**
 * Ouvidoria (Customer Support) API
 * Handles customer messages and responses
 */

// Set JSON header immediately to prevent HTML errors from appearing in response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Wrap everything in try-catch to ensure JSON response on all errors
try {
    require_once __DIR__ . '/admin/base.php';
    
    // Start session at the beginning to ensure user authentication is available
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $conn = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
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
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    // Log the error for debugging
    error_log('Ouvidoria API Error: ' . $e->getMessage());
    
    // Always return JSON, even on fatal errors
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
    exit;
}

function handleGet($conn, $action) {
    switch ($action) {
        case 'list':
            // List all messages (admin)
            $status = $_GET['status'] ?? null;
            
            $sql = "
                SELECT o.*, u.full_name as responded_by_name
                FROM ouvidoria o
                LEFT JOIN users u ON o.responded_by = u.id
                WHERE 1=1
            ";
            
            if ($status) {
                $sql .= " AND o.status = ?";
                $stmt = $conn->prepare($sql . " ORDER BY o.created_at DESC");
                $stmt->execute([$status]);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $result = $conn->query($sql . " ORDER BY o.created_at DESC");
                $messages = $result->fetchAll(PDO::FETCH_ASSOC);
            }
            
            sendSuccess($messages);
            break;
            
        case 'my-chats':
            // List user's own chats
            $userId = $_SESSION['user_id'] ?? null;
            
            if (!$userId) {
                sendError('Authentication required', 401);
            }
            
            // Check if user_id column exists in the table
            $checkColumn = $conn->query("
                SELECT column_name 
                FROM information_schema.columns 
                WHERE table_name='ouvidoria' AND column_name='user_id'
            ");
            $hasUserIdColumn = $checkColumn && $checkColumn->rowCount() > 0;
            
            if (!$hasUserIdColumn) {
                // Table doesn't have user_id column, return empty array
                sendSuccess([]);
                return;
            }
            
            $stmt = $conn->prepare("
                SELECT id, protocol_number, subject, status, created_at, updated_at,
                       CASE WHEN response IS NOT NULL THEN TRUE ELSE FALSE END as has_response
                FROM ouvidoria
                WHERE user_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$userId]);
            $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendSuccess($chats);
            break;
            
        case 'get':
            // Get single message (admin or message owner)
            $id = $_GET['id'] ?? null;
            if (!$id) {
                sendError('Message ID required');
            }
            
            $userId = $_SESSION['user_id'] ?? null;
            
            $stmt = $conn->prepare("
                SELECT o.*, u.full_name as responded_by_name
                FROM ouvidoria o
                LEFT JOIN users u ON o.responded_by = u.id
                WHERE o.id = ?
            ");
            $stmt->execute([$id]);
            $message = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$message) {
                sendError('Message not found', 404);
            }
            
            // Check if user_id column exists and if user is authorized
            if (isset($message['user_id']) && $message['user_id'] && $message['user_id'] != $userId) {
                // User can only view their own messages (unless admin)
                // TODO: Add admin check here
                sendError('Unauthorized', 403);
            }
            
            sendSuccess($message);
            break;
            
        case 'by-protocol':
            // Get message by protocol number (for customer checking)
            $protocol = $_GET['protocol'] ?? null;
            if (!$protocol) {
                sendError('Protocol number required');
            }
            
            $stmt = $conn->prepare("
                SELECT protocol_number, status, response, created_at, updated_at
                FROM ouvidoria
                WHERE protocol_number = ?
            ");
            $stmt->execute([$protocol]);
            $message = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$message) {
                sendError('Message not found', 404);
            }
            
            sendSuccess($message);
            break;
            
        default:
            sendError('Invalid action');
    }
}

function handlePost($conn, $action) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'submit':
            // Submit new message (from public form)
            validateRequired($data, ['full_name', 'email', 'subject', 'message']);
            
            // Get user_id if logged in (optional field)
            $userId = $_SESSION['user_id'] ?? null;
            
            // Generate protocol number
            $protocolNumber = 'OUV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Check if user_id column exists in the table
            $checkColumn = $conn->query("
                SELECT column_name 
                FROM information_schema.columns 
                WHERE table_name='ouvidoria' AND column_name='user_id'
            ");
            $hasUserIdColumn = $checkColumn && $checkColumn->rowCount() > 0;
            
            if ($hasUserIdColumn) {
                // Table has user_id column
                $stmt = $conn->prepare("
                    INSERT INTO ouvidoria (user_id, protocol_number, full_name, email, phone, subject, message, image_path)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $result = $stmt->execute([
                    $userId,
                    $protocolNumber, 
                    $data['full_name'], 
                    $data['email'], 
                    $data['phone'] ?? null, 
                    $data['subject'], 
                    $data['message'], 
                    $data['image_path'] ?? null
                ]);
            } else {
                // Table doesn't have user_id column (legacy schema)
                $stmt = $conn->prepare("
                    INSERT INTO ouvidoria (protocol_number, full_name, email, phone, subject, message, image_path)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $result = $stmt->execute([
                    $protocolNumber, 
                    $data['full_name'], 
                    $data['email'], 
                    $data['phone'] ?? null, 
                    $data['subject'], 
                    $data['message'], 
                    $data['image_path'] ?? null
                ]);
            }
            
            if ($result) {
                sendSuccess([
                    'id' => $conn->lastInsertId(),
                    'protocol_number' => $protocolNumber
                ], 'Message submitted successfully');
            } else {
                sendError('Failed to submit message');
            }
            break;
            
        default:
            sendError('Invalid action');
    }
}

function handlePut($conn, $action) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'respond':
            // Respond to message
            validateRequired($data, ['id', 'response']);
            
            // Check if user is authenticated (session already started at top of file)
            if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] === null) {
                sendError('Authentication required. Please log in to respond to messages.', 401);
                return;
            }
            
            $respondedBy = $_SESSION['user_id'];
            
            // Verify that the user exists in the database before attempting the foreign key reference
            $checkUser = $conn->prepare("SELECT id FROM users WHERE id = ?");
            $checkUser->execute([$respondedBy]);
            if (!$checkUser->fetch()) {
                sendError('Invalid user session. User does not exist in the database.', 403);
                return;
            }
            
            $stmt = $conn->prepare("
                UPDATE ouvidoria 
                SET status = 'resolvido', response = ?, responded_by = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            if ($stmt->execute([$data['response'], $respondedBy, $data['id']])) {
                sendSuccess(null, 'Response sent successfully');
            } else {
                sendError('Failed to send response');
            }
            break;
            
        case 'update-status':
            // Update message status
            validateRequired($data, ['id', 'status']);
            
            $allowedStatuses = ['pendente', 'em_atendimento', 'resolvido'];
            if (!in_array($data['status'], $allowedStatuses)) {
                sendError('Invalid status');
            }
            
            $stmt = $conn->prepare("
                UPDATE ouvidoria 
                SET status = ?
                WHERE id = ?
            ");
            if ($stmt->execute([$data['status'], $data['id']])) {
                sendSuccess(null, 'Status updated successfully');
            } else {
                sendError('Failed to update status');
            }
            break;
            
        default:
            sendError('Invalid action');
    }
}
