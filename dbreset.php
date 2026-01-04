<?php
/**
 * Database Reset Script
 * This script drops all tables and recreates them
 * USE WITH CAUTION - ALL DATA WILL BE LOST! 
 */

// Prevent direct access in production
$RESET_ENABLED = true; // Set to false in production! 
$RESET_PASSWORD = 'portuga2026'; // Change this to a secure password! 

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Reset - Portuga</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background:  linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        
        h1 {
            color:  #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            color: #856404;
        }
        
        .danger {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            color: #721c24;
        }
        
        . success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            color: #155724;
        }
        
        .error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            color: #721c24;
        }
        
        . form-group {
            margin:  20px 0;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight:  600;
            color: #555;
        }
        
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size:  16px;
            transition:  border-color 0.3s;
        }
        
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            width: 100%;
            padding:  15px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight:  600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        . btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .log {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            max-height: 300px;
            overflow-y:  auto;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .log-item {
            padding: 5px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .log-item:last-child {
            border-bottom: none;
        }
        
        .log-success {
            color: #28a745;
        }
        
        .log-error {
            color: #dc3545;
        }
        
        .log-info {
            color: #17a2b8;
        }
        
        .disabled {
            background: #6c757d ! important;
            cursor: not-allowed !important;
            opacity: 0.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗑️ Database Reset</h1>
        <p style="color: #666; margin-bottom: 20px;">Portuga Restaurant Management System</p>
        
        <?php
        if (! $RESET_ENABLED) {
            echo '<div class="error">';
            echo '<strong>❌ Reset Desabilitado</strong><br>';
            echo 'Esta funcionalidade está desabilitada.  Edite o arquivo e altere $RESET_ENABLED para true. ';
            echo '</div>';
            echo '<a href="index.html" class="btn btn-secondary">← Voltar ao Site</a>';
            echo '</body></html>';
            exit;
        }
        
        // Process form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'] ??  '';
            
            if ($password !== $RESET_PASSWORD) {
                echo '<div class="error">';
                echo '<strong>❌ Senha Incorreta</strong><br>';
                echo 'A senha fornecida está incorreta. ';
                echo '</div>';
            } else {
                // Load database configuration
                require_once __DIR__ . '/config/database.php';
                
                echo '<div class="log">';
                echo '<div class="log-item log-info">📋 Iniciando reset do banco de dados...</div>';
                
                try {
                    $pdo = getDBConnection();
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // Array of tables to drop in correct order
                    $tables = [
                        'admin_logs',
                        'employee_schedule',
                        'order_notes',
                        'order_items',
                        'orders',
                        'menu_items',
                        'menu_groups',
                        'user_roles',
                        'role_permissions',
                        'permissions',
                        'roles',
                        'sessions',
                        'password_resets',
                        'login_attempts',
                        'restaurant_settings',
                        'reports',
                        'resumes',
                        'ouvidoria',
                        'maintenance_mode',
                        'service_points',
                        'users'
                    ];
                    
                    // Drop all tables
                    echo '<div class="log-item log-info">🗑️ Dropando tabelas...</div>';
                    foreach ($tables as $table) {
                        try {
                            $pdo->exec("DROP TABLE IF EXISTS $table CASCADE");
                            echo '<div class="log-item log-success">✓ Tabela dropada:  ' . $table . '</div>';
                        } catch (PDOException $e) {
                            echo '<div class="log-item log-error">✗ Erro ao dropar ' . $table . ': ' .  $e->getMessage() . '</div>';
                        }
                    }
                    
                    // Drop function
                    echo '<div class="log-item log-info">🔧 Removendo funções...</div>';
                    try {
                        $pdo->exec("DROP FUNCTION IF EXISTS update_updated_at_column() CASCADE");
                        echo '<div class="log-item log-success">✓ Função removida:  update_updated_at_column()</div>';
                    } catch (PDOException $e) {
                        echo '<div class="log-item log-error">✗ Erro ao remover função: ' . $e->getMessage() . '</div>';
                    }
                    
                    // Read and execute setup. sql
                    echo '<div class="log-item log-info">📦 Recriando estrutura do banco de dados...</div>';
                    $setupSQL = file_get_contents(__DIR__ . '/database/setup.sql');
                    
                    if ($setupSQL === false) {
                        throw new Exception('Não foi possível ler o arquivo setup.sql');
                    }
                    
                    // Execute the setup script
                    try {
                        $pdo->exec($setupSQL);
                        echo '<div class="log-item log-success">✓ Banco de dados recriado com sucesso!</div>';
                    } catch (PDOException $e) {
                        echo '<div class="log-item log-error">✗ Erro ao executar setup. sql: ' . $e->getMessage() . '</div>';
                    }
                    
                    echo '<div class="log-item log-success">✅ Reset concluído com sucesso!</div>';
                    echo '</div>';
                    
                    echo '<div class="success">';
                    echo '<strong>✅ Sucesso!</strong><br>';
                    echo 'O banco de dados foi resetado completamente. <br>';
                    echo 'Todas as tabelas foram recriadas com os dados iniciais.';
                    echo '</div>';
                    
                    echo '<a href="index.html" class="btn btn-secondary">← Voltar ao Site</a>';
                    echo '<a href="admin. html" class="btn btn-secondary">Ir para Admin</a>';
                    
                } catch (PDOException $e) {
                    echo '<div class="log-item log-error">❌ Erro fatal: ' . $e->getMessage() . '</div>';
                    echo '</div>';
                    
                    echo '<div class="error">';
                    echo '<strong>❌ Erro ao Conectar</strong><br>';
                    echo 'Não foi possível conectar ao banco de dados:  ' . htmlspecialchars($e->getMessage());
                    echo '</div>';
                } catch (Exception $e) {
                    echo '<div class="log-item log-error">❌ Erro:  ' . $e->getMessage() . '</div>';
                    echo '</div>';
                    
                    echo '<div class="error">';
                    echo '<strong>❌ Erro</strong><br>';
                    echo htmlspecialchars($e->getMessage());
                    echo '</div>';
                }
            }
        } else {
            // Show form
            ?>
            <div class="danger">
                <strong>⚠️ ATENÇÃO - OPERAÇÃO DESTRUTIVA!</strong><br>
                Esta ação irá: 
                <ul style="margin:  10px 0 0 20px;">
                    <li>Dropar todas as tabelas do banco de dados</li>
                    <li>Apagar TODOS os dados (usuários, pedidos, menu, etc.)</li>
                    <li>Recriar as tabelas vazias com dados iniciais</li>
                </ul>
            </div>
            
            <div class="warning">
                <strong>💾 Antes de continuar:</strong><br>
                ✓ Faça backup dos dados importantes<br>
                ✓ Tenha certeza de que deseja fazer isso<br>
                ✓ Esta ação NÃO pode ser desfeita
            </div>
            
            <form method="POST" onsubmit="return confirm('⚠️ TEM CERTEZA ABSOLUTA?\n\nTodos os dados serão perdidos permanentemente!\n\nClique OK para continuar ou Cancelar para abortar.');">
                <div class="form-group">
                    <label for="password">🔑 Senha de Confirmação</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Digite a senha de reset" 
                        required
                        autocomplete="off"
                    >
                    <small style="color: #666; display: block; margin-top: 5px;">
                        Senha padrão: <code>portuga2026</code> (altere no arquivo)
                    </small>
                </div>
                
                <button type="submit" class="btn btn-danger">
                    🗑️ DROPAR E RECRIAR BANCO DE DADOS
                </button>
                
                <a href="index. html" class="btn btn-secondary">
                    ← Cancelar e Voltar
                </a>
            </form>
            <?php
            }
        ?>
    </div>
</body>
</html>
