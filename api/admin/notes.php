<?php
/**
 * Notes API
 * Handles announcements/communications displayed on homepage
 * 
 * Endpoints:
 * - GET /list - List active notes (public)
 * - GET /admin/list - List all notes (admin only)
 * - POST /create - Create new note (admin only)
 * - PUT /update - Update note (admin only)
 * - DELETE /delete - Delete note (admin only)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration
require_once __DIR__ . '/../../config/database.php';

// Get current user from session
function getCurrentUser() {
    session_start();
    return $_SESSION['user_id'] ?? null;
}

// Handle request
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['action'] ?? 'list';

$pdo = getDBConnection();

switch ($path) {
    case 'list':
        // Public endpoint - get active notes only
        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit();
        }
        
        try {
            $stmt = $pdo->query("
                SELECT id, title, content, note_type, display_order, created_at
                FROM system_notes
                WHERE is_active = TRUE
                AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)
                ORDER BY display_order ASC, created_at DESC
            ");
            
            $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'notes' => $notes
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao buscar notas']);
        }
        break;
        
    case 'admin-list':
        // Admin endpoint - get all notes
        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit();
        }
        
        // Check admin authentication
        $userId = getCurrentUser();
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            exit();
        }
        
        // TODO: Add role/permission check for admin access
        
        try {
            $stmt = $pdo->query("
                SELECT 
                    n.*,
                    u.full_name as created_by_name
                FROM system_notes n
                LEFT JOIN users u ON n.created_by = u.id
                ORDER BY n.display_order ASC, n.created_at DESC
            ");
            
            $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'notes' => $notes
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao buscar notas']);
        }
        break;
        
    case 'create':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit();
        }
        
        // Check admin authentication
        $userId = getCurrentUser();
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            exit();
        }
        
        // TODO: Add role/permission check for admin access
        
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = getCurrentUser();
        
        if (!isset($data['title']) || !isset($data['content'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit();
        }
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO system_notes (title, content, note_type, is_active, display_order, created_by, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['title'],
                $data['content'],
                $data['note_type'] ?? 'info',
                $data['is_active'] ?? true,
                $data['display_order'] ?? 0,
                $userId,
                $data['expires_at'] ?? null
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Nota criada com sucesso',
                'note_id' => $pdo->lastInsertId()
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao criar nota']);
        }
        break;
        
    case 'update':
        if ($method !== 'PUT') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit();
        }
        
        // Check admin authentication
        $userId = getCurrentUser();
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            exit();
        }
        
        // TODO: Add role/permission check for admin access
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing note ID']);
            exit();
        }
        
        try {
            $fields = [];
            $params = [];
            
            if (isset($data['title'])) {
                $fields[] = 'title = ?';
                $params[] = $data['title'];
            }
            if (isset($data['content'])) {
                $fields[] = 'content = ?';
                $params[] = $data['content'];
            }
            if (isset($data['note_type'])) {
                $fields[] = 'note_type = ?';
                $params[] = $data['note_type'];
            }
            if (isset($data['is_active'])) {
                $fields[] = 'is_active = ?';
                $params[] = $data['is_active'];
            }
            if (isset($data['display_order'])) {
                $fields[] = 'display_order = ?';
                $params[] = $data['display_order'];
            }
            if (isset($data['expires_at'])) {
                $fields[] = 'expires_at = ?';
                $params[] = $data['expires_at'];
            }
            
            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No fields to update']);
                exit();
            }
            
            $params[] = $data['id'];
            $sql = "UPDATE system_notes SET " . implode(', ', $fields) . " WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'Nota atualizada com sucesso'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar nota']);
        }
        break;
        
    case 'delete':
        if ($method !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit();
        }
        
        // Check admin authentication
        $userId = getCurrentUser();
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            exit();
        }
        
        // TODO: Add role/permission check for admin access
        
        $noteId = $_GET['id'] ?? null;
        
        if (!$noteId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing note ID']);
            exit();
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM system_notes WHERE id = ?");
            $stmt->execute([$noteId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Nota deletada com sucesso'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao deletar nota']);
        }
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
        break;
}
