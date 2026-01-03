<?php
/**
 * User Profile API
 * Handles user profile information, photos, statistics, and preferences
 * 
 * Endpoints:
 * - GET /info - Get user profile information
 * - POST /upload-photo - Upload profile photo
 * - PUT /update-favorite-dish - Update favorite dish
 * - PUT /toggle-section - Toggle section visibility
 * - GET /statistics - Get user statistics
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

// Get current user from session
function getCurrentUser() {
    session_start();
    return $_SESSION['user_id'] ?? null;
}

// Censor email for privacy
function censorEmail($email) {
    if (!$email) return '';
    
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;
    
    $username = $parts[0];
    $domain = $parts[1];
    
    $visibleLength = min(3, strlen($username));
    $censored = substr($username, 0, $visibleLength) . '***';
    
    return $censored . '@' . $domain;
}

// Handle request
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['action'] ?? '';

$pdo = getDBConnection();

switch ($path) {
    case 'info':
        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit();
        }
        
        $userId = $_GET['user_id'] ?? getCurrentUser();
        
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            exit();
        }
        
        try {
            // Get user basic info
            $stmt = $pdo->prepare("
                SELECT 
                    u.id,
                    u.full_name,
                    u.email,
                    u.created_at,
                    u.oauth_provider,
                    pp.photo_path,
                    fd.menu_item_id as favorite_dish_id,
                    mi.name as favorite_dish_name
                FROM users u
                LEFT JOIN user_profile_photos pp ON u.id = pp.user_id
                LEFT JOIN user_favorite_dishes fd ON u.id = fd.user_id
                LEFT JOIN menu_items mi ON fd.menu_item_id = mi.id
                WHERE u.id = ?
            ");
            
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit();
            }
            
            // Censor email
            $user['email_censored'] = censorEmail($user['email']);
            unset($user['email']); // Remove uncensored email from response
            
            // Get user roles
            $stmt = $pdo->prepare("
                SELECT r.name, r.description
                FROM user_roles ur
                JOIN roles r ON ur.role_id = r.id
                WHERE ur.user_id = ?
            ");
            $stmt->execute([$userId]);
            $user['roles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get privacy settings
            $stmt = $pdo->prepare("
                SELECT * FROM user_privacy_settings WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $privacy = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$privacy) {
                // Create default privacy settings
                $stmt = $pdo->prepare("
                    INSERT INTO user_privacy_settings (user_id) VALUES (?)
                ");
                $stmt->execute([$userId]);
                $privacy = [
                    'show_statistics' => true,
                    'show_total_spent' => true,
                    'show_favorite_dish' => true,
                    'show_order_count' => true,
                    'show_last_review' => true
                ];
            }
            
            $user['privacy_settings'] = $privacy;
            
            echo json_encode([
                'success' => true,
                'user' => $user
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao buscar informações do perfil']);
        }
        break;
        
    case 'statistics':
        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit();
        }
        
        $userId = $_GET['user_id'] ?? getCurrentUser();
        
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            exit();
        }
        
        try {
            // Get total spent
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(total), 0) as total_spent
                FROM orders
                WHERE user_id = ? AND status != 'cancelado'
            ");
            $stmt->execute([$userId]);
            $totalSpent = $stmt->fetch(PDO::FETCH_ASSOC)['total_spent'];
            
            // Get total orders
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total_orders
                FROM orders
                WHERE user_id = ? AND status != 'cancelado'
            ");
            $stmt->execute([$userId]);
            $totalOrders = $stmt->fetch(PDO::FETCH_ASSOC)['total_orders'];
            
            // Get most ordered item
            $stmt = $pdo->prepare("
                SELECT 
                    oi.item_name,
                    SUM(oi.quantity) as total_quantity
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                WHERE o.user_id = ? AND o.status != 'cancelado'
                GROUP BY oi.item_name
                ORDER BY total_quantity DESC
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $mostOrdered = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get last review
            $stmt = $pdo->prepare("
                SELECT rating, comment, created_at
                FROM reviews
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $lastReview = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if user is employee/admin and get additional stats
            $stmt = $pdo->prepare("
                SELECT r.name
                FROM user_roles ur
                JOIN roles r ON ur.role_id = r.id
                WHERE ur.user_id = ?
            ");
            $stmt->execute([$userId]);
            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $isEmployee = !empty(array_intersect($roles, ['Admin', 'Gerente', 'Atendente', 'Cozinha']));
            
            $employeeStats = null;
            if ($isEmployee) {
                // Get top 5 most sold items (for employees)
                $stmt = $pdo->query("
                    SELECT 
                        oi.item_name,
                        SUM(oi.quantity) as total_sold
                    FROM order_items oi
                    JOIN orders o ON oi.order_id = o.id
                    WHERE o.status != 'cancelado'
                    GROUP BY oi.item_name
                    ORDER BY total_sold DESC
                    LIMIT 5
                ");
                $topDishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $employeeStats = [
                    'top_dishes' => $topDishes
                ];
            }
            
            echo json_encode([
                'success' => true,
                'statistics' => [
                    'total_spent' => (float)$totalSpent,
                    'total_orders' => (int)$totalOrders,
                    'most_ordered' => $mostOrdered,
                    'last_review' => $lastReview,
                    'is_employee' => $isEmployee,
                    'employee_stats' => $employeeStats
                ]
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao buscar estatísticas']);
        }
        break;
        
    case 'upload-photo':
        if ($method !== 'POST') {
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
        
        // Handle file upload
        if (!isset($_FILES['photo'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
            exit();
        }
        
        $file = $_FILES['photo'];
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        
        if (!in_array($file['type'], $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid file type']);
            exit();
        }
        
        if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'File too large (max 5MB)']);
            exit();
        }
        
        // Create uploads directory if it doesn't exist
        $uploadDir = __DIR__ . '/../uploads/profiles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $userId . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            try {
                // Save to database
                // Note: user_profile_photos table is not in setup.sql - this feature may not be fully implemented
                // If this feature is needed, add the table definition to setup.sql:
                // CREATE TABLE IF NOT EXISTS user_profile_photos (
                //     user_id INTEGER PRIMARY KEY,
                //     photo_path VARCHAR(512) NOT NULL,
                //     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                //     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                // );
                $stmt = $pdo->prepare("
                    INSERT INTO user_profile_photos (user_id, photo_path, updated_at)
                    VALUES (?, ?, CURRENT_TIMESTAMP)
                    ON CONFLICT (user_id) 
                    DO UPDATE SET photo_path = EXCLUDED.photo_path, updated_at = CURRENT_TIMESTAMP
                ");
                
                $photoPath = 'uploads/profiles/' . $filename;
                $stmt->execute([$userId, $photoPath]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Foto enviada com sucesso',
                    'photo_path' => $photoPath
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erro ao salvar foto no banco de dados']);
            }
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao fazer upload da foto']);
        }
        break;
        
    case 'update-favorite-dish':
        if ($method !== 'PUT') {
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
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['menu_item_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing menu_item_id']);
            exit();
        }
        
        try {
            // Note: user_favorite_dishes table is not in setup.sql - this feature may not be fully implemented
            // If this feature is needed, add the table definition to setup.sql:
            // CREATE TABLE IF NOT EXISTS user_favorite_dishes (
            //     user_id INTEGER PRIMARY KEY,
            //     menu_item_id INTEGER NOT NULL,
            //     set_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            //     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            //     FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
            // );
            $stmt = $pdo->prepare("
                INSERT INTO user_favorite_dishes (user_id, menu_item_id, set_at)
                VALUES (?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT (user_id)
                DO UPDATE SET menu_item_id = EXCLUDED.menu_item_id, set_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->execute([$userId, $data['menu_item_id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Prato favorito atualizado com sucesso'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar prato favorito']);
        }
        break;
        
    case 'toggle-section':
        if ($method !== 'PUT') {
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
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['section']) || !isset($data['visible'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit();
        }
        
        $validSections = ['show_statistics', 'show_total_spent', 'show_favorite_dish', 'show_order_count', 'show_last_review'];
        
        if (!in_array($data['section'], $validSections)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid section']);
            exit();
        }
        
        try {
            // Ensure user has privacy settings
            $stmt = $pdo->prepare("
                INSERT INTO user_privacy_settings (user_id) 
                VALUES (?)
                ON DUPLICATE KEY UPDATE user_id = user_id
            ");
            $stmt->execute([$userId]);
            
            // Update specific setting
            $sql = "UPDATE user_privacy_settings SET {$data['section']} = ? WHERE user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['visible'] ? 1 : 0, $userId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Configuração de privacidade atualizada'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar configuração']);
        }
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
        break;
}
