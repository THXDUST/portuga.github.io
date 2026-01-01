<?php
/**
 * Ouvidoria (Customer Support) API
 * Handles customer messages and responses
 */

require_once __DIR__ . '/admin/base.php';

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
            handlePost($conn, $action);
            break;
        case 'PUT':
            handlePut($conn, $action);
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
            // List all messages
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
                $stmt->bind_param("s", $status);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($sql . " ORDER BY o.created_at DESC");
            }
            
            $messages = $result->fetch_all(MYSQLI_ASSOC);
            sendSuccess($messages);
            break;
            
        case 'get':
            // Get single message
            $id = $_GET['id'] ?? null;
            if (!$id) {
                sendError('Message ID required');
            }
            
            $stmt = $conn->prepare("
                SELECT o.*, u.full_name as responded_by_name
                FROM ouvidoria o
                LEFT JOIN users u ON o.responded_by = u.id
                WHERE o.id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $message = $result->fetch_assoc();
            
            if (!$message) {
                sendError('Message not found', 404);
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
            $stmt->bind_param("s", $protocol);
            $stmt->execute();
            $result = $stmt->get_result();
            $message = $result->fetch_assoc();
            
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
            
            // Generate protocol number
            $protocolNumber = 'OUV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $stmt = $conn->prepare("
                INSERT INTO ouvidoria (protocol_number, full_name, email, phone, subject, message, image_path)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param("sssssss",
                $protocolNumber,
                $data['full_name'],
                $data['email'],
                $data['phone'] ?? null,
                $data['subject'],
                $data['message'],
                $data['image_path'] ?? null
            );
            
            if ($stmt->execute()) {
                sendSuccess([
                    'id' => $conn->insert_id,
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
            
            $stmt = $conn->prepare("
                UPDATE ouvidoria 
                SET status = 'resolvido', response = ?, responded_by = ?
                WHERE id = ?
            ");
            
            $respondedBy = $_SESSION['user_id'] ?? null;
            $stmt->bind_param("sii", $data['response'], $respondedBy, $data['id']);
            
            if ($stmt->execute()) {
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
            $stmt->bind_param("si", $data['status'], $data['id']);
            
            if ($stmt->execute()) {
                sendSuccess(null, 'Status updated successfully');
            } else {
                sendError('Failed to update status');
            }
            break;
            
        default:
            sendError('Invalid action');
    }
}
