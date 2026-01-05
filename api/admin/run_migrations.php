<?php
/**
 * Database Migrations Runner
 * Applies pending database migrations automatically
 * 
 * Security: Requires either admin authentication or MIGRATIONS_TOKEN env var
 */

require_once __DIR__ . '/base.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Security check: require authentication or token
$authenticated = false;

// Check if user is authenticated admin
session_start();
if (isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
    $authenticated = true;
}

// Check for migrations token in header or env
$providedToken = $_SERVER['HTTP_X_MIGRATIONS_TOKEN'] ?? $_GET['token'] ?? null;
$expectedToken = getenv('MIGRATIONS_TOKEN');

if (!$authenticated && $expectedToken && $providedToken === $expectedToken) {
    $authenticated = true;
}

if (!$authenticated) {
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized. Admin access or valid MIGRATIONS_TOKEN required.'
    ]);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Create schema_migrations table if it doesn't exist
    $conn->exec("
        CREATE TABLE IF NOT EXISTS schema_migrations (
            id SERIAL PRIMARY KEY,
            version VARCHAR(255) UNIQUE NOT NULL,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Get list of applied migrations
    $stmt = $conn->query("SELECT version FROM schema_migrations ORDER BY version");
    $appliedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get list of migration files
    $migrationsDir = __DIR__ . '/../../database/migrations';
    if (!is_dir($migrationsDir)) {
        throw new Exception("Migrations directory not found: $migrationsDir");
    }
    
    $migrationFiles = glob($migrationsDir . '/*.sql');
    sort($migrationFiles);
    
    $results = [];
    $applied = 0;
    $skipped = 0;
    $errors = [];
    
    foreach ($migrationFiles as $file) {
        $filename = basename($file);
        $version = pathinfo($filename, PATHINFO_FILENAME);
        
        // Skip if already applied
        if (in_array($version, $appliedMigrations)) {
            $skipped++;
            $results[] = [
                'version' => $version,
                'status' => 'skipped',
                'message' => 'Already applied'
            ];
            continue;
        }
        
        // Read and execute migration
        $sql = file_get_contents($file);
        
        try {
            $conn->beginTransaction();
            
            // Execute migration SQL
            $conn->exec($sql);
            
            // Record migration as applied
            $stmt = $conn->prepare("INSERT INTO schema_migrations (version) VALUES (?)");
            $stmt->execute([$version]);
            
            $conn->commit();
            
            $applied++;
            $results[] = [
                'version' => $version,
                'status' => 'applied',
                'message' => 'Successfully applied'
            ];
            
        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = [
                'version' => $version,
                'error' => $e->getMessage()
            ];
            $results[] = [
                'version' => $version,
                'status' => 'failed',
                'message' => $e->getMessage()
            ];
        }
    }
    
    // Prepare response
    $response = [
        'success' => empty($errors),
        'summary' => [
            'total' => count($migrationFiles),
            'applied' => $applied,
            'skipped' => $skipped,
            'failed' => count($errors)
        ],
        'migrations' => $results
    ];
    
    if (!empty($errors)) {
        $response['errors'] = $errors;
    }
    
    http_response_code(empty($errors) ? 200 : 207); // 207 Multi-Status if some failed
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Migration system error: ' . $e->getMessage()
    ]);
}
