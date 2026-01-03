<?php
/**
 * Database Setup Script
 * Executes the database/setup.sql file to create all tables
 * 
 * SECURITY WARNING: This file should be deleted after initial setup
 * or protected with strong authentication in production
 */

// Include database configuration
require_once __DIR__ . '/config/database.php';

// Set execution time limit for large SQL files
set_time_limit(300);

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'details' => [],
    'errors' => []
];

try {
    // Check if setup.sql exists
    $sqlFile = __DIR__ . '/database/setup.sql';
    
    if (! file_exists($sqlFile)) {
        throw new Exception("Arquivo setup.sql não encontrado em:  " . $sqlFile);
    }
    
    // Read SQL file
    $sql = file_get_contents($sqlFile);
    
    if ($sql === false) {
        throw new Exception("Não foi possível ler o arquivo setup.sql");
    }
    
    // Get database connection (connect to PostgreSQL server)
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $response['details'][] = "✓ Conexão com PostgreSQL estabelecida";
    
    // Split SQL into individual statements
    // Remove comments and split by semicolon
    $sql = preg_replace('/--.*$/m', '', $sql); // Remove single-line comments
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql); // Remove multi-line comments
    
    // PostgreSQL handles statements differently - no DELIMITER needed
    // Split by semicolon for individual statements
    
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return ! empty($stmt);
        }
    );
    
    $response['details'][] = "✓ Arquivo SQL carregado:  " . count($statements) . " comandos encontrados";
    
    // Execute statements
    $executedCount = 0;
    $skippedCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $index => $statement) {
        if (empty(trim($statement))) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $executedCount++;
            
            // Log important operations
            if (stripos($statement, 'CREATE TABLE') !== false) {
                preg_match('/CREATE TABLE.*?(\w+)\s*\(/i', $statement, $matches);
                if (isset($matches[1])) {
                    $response['details'][] = "✓ Tabela criada: " . $matches[1];
                }
            } elseif (stripos($statement, 'CREATE INDEX') !== false) {
                // Silently create indexes
            } elseif (stripos($statement, 'CREATE TRIGGER') !== false) {
                preg_match('/CREATE TRIGGER\s+(\w+)/i', $statement, $matches);
                if (isset($matches[1])) {
                    $response['details'][] = "✓ Trigger criado: " . $matches[1];
                }
            } elseif (stripos($statement, 'CREATE FUNCTION') !== false || stripos($statement, 'CREATE OR REPLACE FUNCTION') !== false) {
                preg_match('/FUNCTION\s+(\w+)/i', $statement, $matches);
                if (isset($matches[1])) {
                    $response['details'][] = "✓ Função criada: " . $matches[1];
                }
            } elseif (stripos($statement, 'INSERT') !== false) {
                $response['details'][] = "✓ Dados iniciais inseridos";
            }
            
        } catch (PDOException $e) {
            // Some errors are acceptable (e.g., table already exists)
            if (stripos($e->getMessage(), 'already exists') !== false) {
                $skippedCount++;
                preg_match('/table.*?`(\w+)`/i', $statement, $matches);
                if (isset($matches[1])) {
                    $response['details'][] = "⚠ Tabela já existe: " . $matches[1];
                }
            } else {
                $errorCount++;
                $response['errors'][] = "Erro no comando #" . ($index + 1) . ": " . $e->getMessage();
            }
        }
    }
    
    // Summary
    $response['details'][] = "\n=== RESUMO ===";
    $response['details'][] = "Comandos executados: " . $executedCount;
    $response['details'][] = "Comandos ignorados (já existem): " . $skippedCount;
    $response['details'][] = "Erros: " . $errorCount;
    
    if ($errorCount === 0) {
        $response['success'] = true;
        $response['message'] = "✅ Banco de dados configurado com sucesso!";
    } else {
        $response['success'] = false;
        $response['message'] = "⚠️ Configuração concluída com alguns erros. Verifique os detalhes abaixo.";
    }
    
    // Verify tables were created
    $stmt = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $response['details'][] = "\n=== TABELAS CRIADAS (" . count($tables) . ") ===";
    foreach ($tables as $table) {
        $response['details'][] = "• " . $table;
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = "❌ Erro ao configurar banco de dados";
    $response['errors'][] = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup do Banco de Dados - Portuga</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family:  -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow:  0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 800px;
            width: 100%;
            padding: 40px;
        }
        
        .header {
            text-align:  center;
            margin-bottom:  30px;
        }
        
        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom:  10px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .alert {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background:  #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        .alert-warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        
        .alert h2 {
            font-size: 20px;
            margin-bottom: 10px;
        }
        
        .details {
            background: #f8f9fa;
            border:  1px solid #dee2e6;
            border-radius:  6px;
            padding: 20px;
            margin-top: 20px;
            max-height: 400px;
            overflow-y:  auto;
        }
        
        .details h3 {
            color: #333;
            font-size: 16px;
            margin-bottom:  15px;
        }
        
        .details ul {
            list-style: none;
        }
        
        .details li {
            padding: 5px 0;
            color: #555;
            font-size: 14px;
            font-family: 'Courier New', monospace;
            white-space: pre-wrap;
        }
        
        .errors {
            background: #fff5f5;
            border: 1px solid #feb2b2;
            border-radius: 6px;
            padding: 20px;
            margin-top: 20px;
        }
        
        . errors h3 {
            color: #c53030;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .errors ul {
            list-style:  disc;
            margin-left: 20px;
        }
        
        .errors li {
            color: #742a2a;
            padding: 5px 0;
            font-size: 14px;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius:  8px;
            padding: 20px;
            margin-top:  30px;
            text-align: center;
        }
        
        .warning-box strong {
            color: #856404;
            display: block;
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .warning-box p {
            color: #856404;
            font-size:  14px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
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
        
        @media (max-width: 600px) {
            .container {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🗄️ Setup do Banco de Dados</h1>
            <p>Portuga - Restaurante & Pizzaria</p>
        </div>
        
        <?php if ($response['success']): ?>
            <div class="alert alert-success">
                <h2><?php echo $response['message']; ?></h2>
            </div>
        <?php elseif (!empty($response['errors'])): ?>
            <div class="alert alert-error">
                <h2><?php echo $response['message']; ?></h2>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <h2><?php echo $response['message']; ?></h2>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($response['details'])): ?>
            <div class="details">
                <h3>📋 Detalhes da Execução</h3>
                <ul>
                    <?php foreach ($response['details'] as $detail): ?>
                        <li><?php echo htmlspecialchars($detail); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($response['errors'])): ?>
            <div class="errors">
                <h3>⚠️ Erros Encontrados</h3>
                <ul>
                    <?php foreach ($response['errors'] as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="warning-box">
            <strong>⚠️ AVISO DE SEGURANÇA</strong>
            <p>Este arquivo deve ser DELETADO após a configuração inicial do banco de dados! </p>
            <p>Manter este arquivo acessível é um risco de segurança. </p>
        </div>
        
        <div style="text-align: center;">
            <a href="index.php" class="btn">Voltar para Home</a>
        </div>
    </div>
</body>
</html>
