<?php
/**
 * Reviews API
 * Handles customer satisfaction reviews/ratings
 * 
 * Endpoints:
 * - POST /submit - Submit a new review (requires authentication)
 * - GET /list - List all reviews (admin only)
 * - PUT /update-status - Update review status (admin only)
 * - GET /statistics - Get review statistics (public)
 * - GET /can-review - Check if user can submit review
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
require_once __DIR__ . '/../config/database.php';

// Get database connection
function getDBConnection() {
    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME') ?: 'portuga_db';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    }
}

// Get current user from session/token
function getCurrentUser() {
    session_start();
    return $_SESSION['user_id'] ?? null;
}

// Get client IP address
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Check if user can submit a review (rate limiting: 1 per hour)
function canSubmitReview($pdo, $userId, $ipAddress) {
    $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM reviews 
        WHERE (user_id = ? OR ip_address = ?) 
        AND created_at > ?
    ");
    $stmt->execute([$userId, $ipAddress, $oneHourAgo]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'] == 0;
}

// Handle request
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['action'] ?? '';

$pdo = getDBConnection();

switch ($path) {
    case 'submit':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit();
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = getCurrentUser();
        $ipAddress = getClientIP();
        
        // Validate required fields
        if (!isset($data['rating']) || $data['rating'] < 0 || $data['rating'] > 5) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Rating must be between 0 and 5']);
            exit();
        }
        
        // Check 4-hour window if order_id is provided
        if (isset($data['order_id']) && $data['order_id']) {
            $orderCheck = $pdo->prepare("
                SELECT created_at, 
                       TIMESTAMPDIFF(HOUR, created_at, NOW()) as hours_elapsed,
                       TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutes_elapsed
                FROM orders 
                WHERE id = ? AND (user_id = ? OR user_id IS NULL)
            ");
            $orderCheck->execute([$data['order_id'], $userId]);
            $order = $orderCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Pedido não encontrado']);
                exit();
            }
            
            $hoursElapsed = (int)$order['hours_elapsed'];
            $minutesElapsed = (int)$order['minutes_elapsed'];
            
            // Check if more than 4 hours have passed
            if ($hoursElapsed >= 4) {
                http_response_code(403);
                echo json_encode([
                    'success' => false, 
                    'message' => 'O prazo para avaliar este pedido expirou. Você pode avaliar apenas dentro de 4 horas após fazer o pedido.',
                    'hours_elapsed' => $hoursElapsed
                ]);
                exit();
            }
            
            // Calculate remaining time
            $remainingMinutes = (4 * 60) - $minutesElapsed;
            $remainingHours = floor($remainingMinutes / 60);
            $remainingMins = $remainingMinutes % 60;
        }
        
        // Check rate limiting
        if (!canSubmitReview($pdo, $userId, $ipAddress)) {
            http_response_code(429);
            echo json_encode([
                'success' => false, 
                'message' => 'Você já enviou uma avaliação recentemente. Por favor, aguarde 1 hora antes de enviar outra.'
            ]);
            exit();
        }
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO reviews (user_id, order_id, rating, comment, ip_address, status)
                VALUES (?, ?, ?, ?, ?, 'pendente')
            ");
            
            $stmt->execute([
                $userId,
                $data['order_id'] ?? null,
                $data['rating'],
                $data['comment'] ?? null,
                $ipAddress
            ]);
            
            $message = 'Sua avaliação está em análise. Se aprovada, será publicada no Instagram do restaurante!';
            if (isset($remainingHours) && isset($remainingMins)) {
                $message .= " (Você tinha {$remainingHours}h {$remainingMins}min restantes para avaliar este pedido)";
            }
            
            echo json_encode([
                'success' => true, 
                'message' => $message,
                'review_id' => $pdo->lastInsertId()
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar avaliação']);
        }
        break;
        
    case 'can-review':
        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit();
        }
        
        $userId = getCurrentUser();
        $ipAddress = getClientIP();
        $orderId = $_GET['order_id'] ?? null;
        
        $response = [
            'success' => true,
            'can_review' => false,
            'message' => '',
            'remaining_time' => null
        ];
        
        // Check if order_id is provided for time window check
        if ($orderId) {
            $orderCheck = $pdo->prepare("
                SELECT created_at, 
                       TIMESTAMPDIFF(HOUR, created_at, NOW()) as hours_elapsed,
                       TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutes_elapsed
                FROM orders 
                WHERE id = ? AND (user_id = ? OR user_id IS NULL)
            ");
            $orderCheck->execute([$orderId, $userId]);
            $order = $orderCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                $response['message'] = 'Pedido não encontrado';
                echo json_encode($response);
                exit();
            }
            
            $hoursElapsed = (int)$order['hours_elapsed'];
            $minutesElapsed = (int)$order['minutes_elapsed'];
            
            // Check if more than 4 hours have passed
            if ($hoursElapsed >= 4) {
                $response['message'] = 'O prazo para avaliar este pedido expirou (4 horas após o pedido)';
                $response['hours_elapsed'] = $hoursElapsed;
                echo json_encode($response);
                exit();
            }
            
            // Calculate remaining time
            $remainingMinutes = (4 * 60) - $minutesElapsed;
            $remainingHours = floor($remainingMinutes / 60);
            $remainingMins = $remainingMinutes % 60;
            
            $response['remaining_time'] = [
                'hours' => $remainingHours,
                'minutes' => $remainingMins,
                'total_minutes' => $remainingMinutes
            ];
        }
        
        // Check rate limiting
        $canReview = canSubmitReview($pdo, $userId, $ipAddress);
        $response['can_review'] = $canReview;
        
        if ($canReview) {
            if (isset($response['remaining_time'])) {
                $response['message'] = "Você pode enviar uma avaliação. Tempo restante: {$remainingHours}h {$remainingMins}min";
            } else {
                $response['message'] = 'Você pode enviar uma avaliação';
            }
        } else {
            $response['message'] = 'Aguarde 1 hora para enviar outra avaliação';
        }
        
        echo json_encode($response);
        break;
        
    case 'list':
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
        
        $status = $_GET['status'] ?? null;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
        $offset = ($page - 1) * $perPage;
        
        $whereClause = $status ? "WHERE r.status = ?" : "";
        $params = $status ? [$status] : [];
        
        try {
            // Get total count
            $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM reviews r $whereClause");
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get reviews
            $stmt = $pdo->prepare("
                SELECT 
                    r.*,
                    u.full_name as user_name,
                    u.email as user_email,
                    a.full_name as approved_by_name
                FROM reviews r
                LEFT JOIN users u ON r.user_id = u.id
                LEFT JOIN users a ON r.approved_by = a.id
                $whereClause
                ORDER BY r.created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $params[] = $perPage;
            $params[] = $offset;
            $stmt->execute($params);
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'reviews' => $reviews,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage)
                ]
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao listar avaliações']);
        }
        break;
        
    case 'update-status':
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
        
        if (!isset($data['review_id']) || !isset($data['status'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit();
        }
        
        $validStatuses = ['pendente', 'aprovado', 'rejeitado', 'arquivado'];
        if (!in_array($data['status'], $validStatuses)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            exit();
        }
        
        try {
            $stmt = $pdo->prepare("
                UPDATE reviews 
                SET status = ?, 
                    approved_by = ?,
                    approved_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$data['status'], $userId, $data['review_id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Status da avaliação atualizado com sucesso'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar status']);
        }
        break;
        
    case 'statistics':
        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit();
        }
        
        try {
            // Get average rating and total reviews
            $stmt = $pdo->query("
                SELECT 
                    COUNT(*) as total_reviews,
                    AVG(rating) as average_rating,
                    COUNT(CASE WHEN status = 'aprovado' THEN 1 END) as approved_reviews
                FROM reviews
            ");
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get rating distribution
            $stmt = $pdo->query("
                SELECT 
                    rating,
                    COUNT(*) as count
                FROM reviews
                WHERE status = 'aprovado'
                GROUP BY rating
                ORDER BY rating DESC
            ");
            $distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format distribution
            $ratingDist = array_fill(0, 6, 0); // 0-5 stars
            foreach ($distribution as $row) {
                $ratingDist[$row['rating']] = (int)$row['count'];
            }
            
            echo json_encode([
                'success' => true,
                'statistics' => [
                    'total_reviews' => (int)$stats['total_reviews'],
                    'approved_reviews' => (int)$stats['approved_reviews'],
                    'average_rating' => round((float)$stats['average_rating'], 1),
                    'rating_distribution' => $ratingDist
                ]
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao buscar estatísticas']);
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
        
        $reviewId = $_GET['review_id'] ?? null;
        
        if (!$reviewId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing review_id']);
            exit();
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
            $stmt->execute([$reviewId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Avaliação deletada com sucesso'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao deletar avaliação']);
        }
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
        break;
}
