<?php
/**
 * Setup Checker and Auto-Fixer
 * Verifies and creates missing configuration files
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$results = [
    'checks' => [],
    'fixes' => [],
    'errors' => []
];

// Function to add result
function addResult(&$results, $type, $message, $status = 'info') {
    $results[$type][] = ['message' => $message, 'status' => $status];
}

// Check 1: Verify config directory exists
$configDir = __DIR__ . '/config';
if (!is_dir($configDir)) {
    addResult($results, 'checks', 'Config directory does not exist', 'error');
    if (mkdir($configDir, 0755, true)) {
        addResult($results, 'fixes', 'Created config directory', 'success');
    } else {
        addResult($results, 'errors', 'Failed to create config directory', 'error');
    }
} else {
    addResult($results, 'checks', 'Config directory exists', 'success');
}

// Check 2: Verify database.php exists
$databaseFile = $configDir . '/database.php';
if (!file_exists($databaseFile)) {
    addResult($results, 'checks', 'database.php does not exist', 'error');
    
    // Create database.php
    $databaseContent = <<<'PHP'
<?php
/**
 * Database Configuration
 * Handles PostgreSQL connection with PDO and environment variables support
 * Supports both individual env vars and DATABASE_URL format (Render.com)
 */

// Load environment variables from .env file if exists
function loadEnv($path = __DIR__ . '/../.env') {
    if (!file_exists($path)) {
        return;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
}

loadEnv();

// Parse DATABASE_URL if provided (Render.com format: postgresql://user:pass@host:port/dbname)
$databaseUrl = getenv('DATABASE_URL');
if ($databaseUrl) {
    $dbConfig = parse_url($databaseUrl);
    define('DB_HOST', $dbConfig['host'] ?? 'localhost');
    define('DB_PORT', $dbConfig['port'] ?? 5432);
    define('DB_NAME', ltrim($dbConfig['path'] ?? '/portuga_db', '/'));
    define('DB_USER', $dbConfig['user'] ?? 'postgres');
    define('DB_PASS', $dbConfig['pass'] ?? '');
} else {
    // Fall back to individual environment variables
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_PORT', getenv('DB_PORT') ?: 5432);
    define('DB_NAME', getenv('DB_NAME') ?: 'portuga_db');
    define('DB_USER', getenv('DB_USER') ?: 'postgres');
    define('DB_PASS', getenv('DB_PASS') ?: '');
}

define('DB_CHARSET', 'utf8');

// Security keys
define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY') ?: '6748938b4ed0c9916b2c1fbcc507cba813aee27c6e4a6e52c978a690e23b42e2');
define('CSRF_SECRET', getenv('CSRF_SECRET') ?: 'f722c609a96f38e74491fe65501da8f88e87b83fdf331858cb4a899b1f07f5cd');

/**
 * Get database connection
 * @return PDO Database connection object
 * @throws PDOException if connection fails
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Set PostgreSQL client encoding
            $pdo->exec("SET NAMES 'UTF8'");
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new PDOException("Database connection failed. Please try again later.");
        }
    }
    
    return $pdo;
}
PHP;
    
    if (file_put_contents($databaseFile, $databaseContent) !== false) {
        addResult($results, 'fixes', 'Created database.php successfully', 'success');
    } else {
        addResult($results, 'errors', 'Failed to create database.php', 'error');
    }
} else {
    addResult($results, 'checks', 'database.php exists', 'success');
}

// Check 3: Test database connection
try {
    require_once $databaseFile;
    addResult($results, 'checks', 'database.php loaded successfully', 'success');
    
    // Show configuration
    addResult($results, 'checks', 'DB_HOST: ' . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED'), 'info');
    addResult($results, 'checks', 'DB_PORT: ' . (defined('DB_PORT') ? DB_PORT : 'NOT DEFINED'), 'info');
    addResult($results, 'checks', 'DB_NAME: ' . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED'), 'info');
    addResult($results, 'checks', 'DB_USER: ' . (defined('DB_USER') ? DB_USER : 'NOT DEFINED'), 'info');
    
    // Test connection
    $pdo = getDBConnection();
    addResult($results, 'checks', 'Database connection successful!', 'success');
    
    // Get PostgreSQL version
    $version = $pdo->query('SELECT version()')->fetchColumn();
    addResult($results, 'checks', 'PostgreSQL Version: ' . $version, 'info');
    
} catch (Exception $e) {
    addResult($results, 'errors', 'Database connection failed: ' . $e->getMessage(), 'error');
}

// Check 4: Verify database directory exists
$databaseDir = __DIR__ . '/database';
if (!is_dir($databaseDir)) {
    addResult($results, 'checks', 'Database directory does not exist', 'warning');
} else {
    addResult($results, 'checks', 'Database directory exists', 'success');
    
    // Check if setup.sql exists
    $setupFile = $databaseDir . '/setup.sql';
    if (file_exists($setupFile)) {
        addResult($results, 'checks', 'setup.sql exists', 'success');
    } else {
        addResult($results, 'checks', 'setup.sql does not exist', 'warning');
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Checker - Portuga</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section h2 {
            color: #333;
            font-size: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .result-item {
            padding: 12px;
            margin-bottom: 8px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .result-item.success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        
        .result-item.error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
        
        .result-item.warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
        }
        
        .result-item.info {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            color: #0c5460;
        }
        
        .icon {
            font-size: 20px;
            font-weight: bold;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            margin-top: 20px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #5568d3;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Setup Checker</h1>
        <p class="subtitle">Verificação e correção automática de configuração</p>
        
        <?php if (!empty($results['checks'])): ?>
        <div class="section">
            <h2>✓ Verificações</h2>
            <?php foreach ($results['checks'] as $check): ?>
                <div class="result-item <?php echo $check['status']; ?>">
                    <span class="icon">
                        <?php 
                        echo $check['status'] === 'success' ? '✓' : 
                             ($check['status'] === 'error' ? '✗' : 
                             ($check['status'] === 'warning' ? '⚠' : 'ℹ'));
                        ?>
                    </span>
                    <span><?php echo htmlspecialchars($check['message']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($results['fixes'])): ?>
        <div class="section">
            <h2>🔨 Correções Aplicadas</h2>
            <?php foreach ($results['fixes'] as $fix): ?>
                <div class="result-item <?php echo $fix['status']; ?>">
                    <span class="icon">✓</span>
                    <span><?php echo htmlspecialchars($fix['message']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($results['errors'])): ?>
        <div class="section">
            <h2>⚠️ Erros</h2>
            <?php foreach ($results['errors'] as $error): ?>
                <div class="result-item error">
                    <span class="icon">✗</span>
                    <span><?php echo htmlspecialchars($error['message']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="actions">
            <a href="dbsetup.php" class="btn">Configurar Banco de Dados</a>
            <a href="check-setup.php" class="btn btn-secondary">Recarregar Verificação</a>
            <a href="index.php" class="btn btn-secondary">Ir para Home</a>
        </div>
    </div>
</body>
</html>