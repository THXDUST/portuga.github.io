<?php
/**
 * Resumes/Curriculums API
 * Handles job application management
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
            // List all resumes
            $status = $_GET['status'] ?? null;
            
            $sql = "
                SELECT r.*, u.full_name as reviewed_by_name
                FROM resumes r
                LEFT JOIN users u ON r.reviewed_by = u.id
                WHERE 1=1
            ";
            
            if ($status) {
                $sql .= " AND r.status = ?";
                $stmt = $conn->prepare($sql . " ORDER BY r.created_at DESC");
                $stmt->execute([$status]);
                $resumes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $result = $conn->query($sql . " ORDER BY r.created_at DESC");
                $resumes = $result->fetchAll(PDO::FETCH_ASSOC);
            }
            
            sendSuccess($resumes);
            break;
            
        case 'get':
            // Get single resume
            $id = $_GET['id'] ?? null;
            if (!$id) {
                sendError('Resume ID required');
            }
            
            $stmt = $conn->prepare("
                SELECT r.*, u.full_name as reviewed_by_name
                FROM resumes r
                LEFT JOIN users u ON r.reviewed_by = u.id
                WHERE r.id = ?
            ");
            $stmt->execute([$id]);
            $resume = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$resume) {
                sendError('Resume not found', 404);
            }
            
            sendSuccess($resume);
            break;
            
        case 'statistics':
            // Get resume statistics
            $result = $conn->query("
                SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'em_analise' THEN 1 END) as em_analise,
                    COUNT(CASE WHEN status = 'aprovado' THEN 1 END) as aprovado,
                    COUNT(CASE WHEN status = 'rejeitado' THEN 1 END) as rejeitado,
                    COUNT(DISTINCT desired_position) as positions_count
                FROM resumes
            ");
            $stats = $result->fetch(PDO::FETCH_ASSOC);
            
            sendSuccess($stats);
            break;
            
        default:
            sendError('Invalid action');
    }
}

function handlePost($conn, $action) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'submit':
            // Submit new resume (from public form)
            validateRequired($data, ['full_name', 'email', 'phone', 'desired_position']);
            
            $stmt = $conn->prepare("
                INSERT INTO resumes (full_name, email, phone, desired_position, resume_file_path, cover_letter)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([
                $data['full_name'],
                $data['email'],
                $data['phone'],
                $data['desired_position'],
                $data['resume_file_path'] ?? null,
                $data['cover_letter'] ?? null
            ])) {
                sendSuccess(['id' => $conn->lastInsertId()], 'Resume submitted successfully');
            } else {
                sendError('Failed to submit resume');
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
            // Update resume status
            validateRequired($data, ['id', 'status']);
            
            $allowedStatuses = ['em_analise', 'aprovado', 'rejeitado'];
            if (!in_array($data['status'], $allowedStatuses)) {
                sendError('Invalid status');
            }
            
            // Ensure session is started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $reviewedBy = $_SESSION['user_id'] ?? null;
            $notes = $data['notes'] ?? null;
            
            // If reviewed_by is provided, validate the user exists
            if ($reviewedBy) {
                $checkUser = $conn->prepare("SELECT id FROM users WHERE id = ?");
                $checkUser->execute([$reviewedBy]);
                if (!$checkUser->fetch()) {
                    // User doesn't exist, set reviewed_by to NULL instead
                    $reviewedBy = null;
                }
            }
            
            $stmt = $conn->prepare("
                UPDATE resumes 
                SET status = ?, notes = ?, reviewed_by = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            if ($stmt->execute([$data['status'], $notes, $reviewedBy, $data['id']])) {
                sendSuccess(null, 'Status updated successfully');
            } else {
                $errorInfo = $stmt->errorInfo();
                sendError('Failed to update status: ' . ($errorInfo[2] ?? 'Unknown error'));
            }
            break;
            
        case 'add-note':
            // Add note to resume
            validateRequired($data, ['id', 'notes']);
            
            session_start();
            
            $stmt = $conn->prepare("
                UPDATE resumes 
                SET notes = ?, reviewed_by = ?
                WHERE id = ?
            ");
            
            $reviewedBy = $_SESSION['user_id'] ?? null;
            
            if ($stmt->execute([$data['notes'], $reviewedBy, $data['id']])) {
                sendSuccess(null, 'Note added successfully');
            } else {
                sendError('Failed to add note');
            }
            break;
            
        default:
            sendError('Invalid action');
    }
}
