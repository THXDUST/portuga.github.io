<?php
/**
 * Employee Schedule API
 * Handles employee work schedules
 * 
 * Endpoints:
 * - POST /create - Create schedule entry (admin only)
 * - GET /list - List schedules (admin or with permission)
 * - GET /my-schedule - Get own schedule (authenticated users)
 * - PUT /update - Update schedule entry (admin only)
 * - DELETE /delete - Delete schedule entry (admin only)
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

// Get current user from session
function getCurrentUser() {
    session_start();
    return $_SESSION['user_id'] ?? null;
}

// Handle request
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['action'] ?? '';

$pdo = getDBConnection();

switch ($path) {
    case 'create':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit();
        }
        
        // TODO: Add admin authentication check
        
        $data = json_decode(file_get_contents('php://input'), true);
        $currentUserId = getCurrentUser();
        
        if (!isset($data['user_id']) || !isset($data['day_of_week']) || 
            !isset($data['shift_start']) || !isset($data['shift_end'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit();
        }
        
        $validDays = ['segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo'];
        if (!in_array($data['day_of_week'], $validDays)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid day of week']);
            exit();
        }
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO employee_schedule 
                (user_id, day_of_week, shift_start, lunch_start, lunch_end, shift_end, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['user_id'],
                $data['day_of_week'],
                $data['shift_start'],
                $data['lunch_start'] ?? null,
                $data['lunch_end'] ?? null,
                $data['shift_end'],
                $data['notes'] ?? null,
                $currentUserId
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Horário criado com sucesso',
                'schedule_id' => $pdo->lastInsertId()
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao criar horário: ' . $e->getMessage()]);
        }
        break;
        
    case 'list':
        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit();
        }
        
        // TODO: Add authentication and permission check
        
        $userId = $_GET['user_id'] ?? null;
        $dayOfWeek = $_GET['day_of_week'] ?? null;
        
        try {
            $whereConditions = [];
            $params = [];
            
            if ($userId) {
                $whereConditions[] = "s.user_id = ?";
                $params[] = $userId;
            }
            
            if ($dayOfWeek) {
                $whereConditions[] = "s.day_of_week = ?";
                $params[] = $dayOfWeek;
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            $stmt = $pdo->prepare("
                SELECT 
                    s.*,
                    u.full_name as user_name,
                    u.email as user_email,
                    c.full_name as created_by_name
                FROM employee_schedule s
                JOIN users u ON s.user_id = u.id
                LEFT JOIN users c ON s.created_by = c.id
                $whereClause
                ORDER BY 
                    FIELD(s.day_of_week, 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo'),
                    s.shift_start
            ");
            
            $stmt->execute($params);
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'schedules' => $schedules
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao buscar horários']);
        }
        break;
        
    case 'my-schedule':
        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit();
        }
        
        $userId = getCurrentUser();
        
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            exit();
        }
        
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    s.*,
                    u.full_name as user_name
                FROM employee_schedule s
                JOIN users u ON s.user_id = u.id
                WHERE s.user_id = ?
                ORDER BY 
                    FIELD(s.day_of_week, 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo'),
                    s.shift_start
            ");
            
            $stmt->execute([$userId]);
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get next shift
            $daysOfWeek = ['domingo', 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado'];
            $today = strtolower($daysOfWeek[date('w')]);
            
            $nextShift = null;
            foreach ($schedules as $schedule) {
                if ($schedule['day_of_week'] === $today) {
                    $nextShift = $schedule;
                    break;
                }
            }
            
            echo json_encode([
                'success' => true,
                'schedules' => $schedules,
                'next_shift' => $nextShift
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao buscar horários']);
        }
        break;
        
    case 'update':
        if ($method !== 'PUT') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit();
        }
        
        // TODO: Add admin authentication check
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing schedule ID']);
            exit();
        }
        
        try {
            $fields = [];
            $params = [];
            
            if (isset($data['day_of_week'])) {
                $fields[] = 'day_of_week = ?';
                $params[] = $data['day_of_week'];
            }
            if (isset($data['shift_start'])) {
                $fields[] = 'shift_start = ?';
                $params[] = $data['shift_start'];
            }
            if (isset($data['lunch_start'])) {
                $fields[] = 'lunch_start = ?';
                $params[] = $data['lunch_start'];
            }
            if (isset($data['lunch_end'])) {
                $fields[] = 'lunch_end = ?';
                $params[] = $data['lunch_end'];
            }
            if (isset($data['shift_end'])) {
                $fields[] = 'shift_end = ?';
                $params[] = $data['shift_end'];
            }
            if (isset($data['notes'])) {
                $fields[] = 'notes = ?';
                $params[] = $data['notes'];
            }
            
            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No fields to update']);
                exit();
            }
            
            $params[] = $data['id'];
            $sql = "UPDATE employee_schedule SET " . implode(', ', $fields) . " WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'Horário atualizado com sucesso'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar horário']);
        }
        break;
        
    case 'delete':
        if ($method !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit();
        }
        
        // TODO: Add admin authentication check
        
        $scheduleId = $_GET['id'] ?? null;
        
        if (!$scheduleId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing schedule ID']);
            exit();
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM employee_schedule WHERE id = ?");
            $stmt->execute([$scheduleId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Horário deletado com sucesso'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao deletar horário']);
        }
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
        break;
}
