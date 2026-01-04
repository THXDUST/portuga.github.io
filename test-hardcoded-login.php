<?php
/**
 * Test Script for Hardcoded Users Authentication
 * Tests login functionality for admin@test, waiter@test, and customer@test
 */

require_once __DIR__ . '/config/hardcoded-users.php';

echo "========================================\n";
echo "TESTE DE AUTENTICAÇÃO - USUÁRIOS HARDCODED\n";
echo "========================================\n\n";

// Test credentials
$testUsers = [
    ['email' => 'admin@test', 'password' => 'admintest', 'expected' => 'Admin'],
    ['email' => 'waiter@test', 'password' => 'waitertest', 'expected' => 'Garçom'],
    ['email' => 'customer@test', 'password' => 'customertest', 'expected' => 'Cliente'],
];

echo "1. Testando Hashes de Senha\n";
echo "----------------------------\n";

foreach ($testUsers as $test) {
    $user = authenticateHardcodedUser($test['email'], $test['password']);
    
    if ($user) {
        echo "✅ {$test['email']}: AUTENTICADO\n";
        echo "   Nome: {$user['full_name']}\n";
        echo "   Tipo: {$user['user_type']}\n";
        echo "   Role: {$user['role']}\n";
        echo "   Redirect: " . getRedirectUrlForUserType($user['user_type']) . "\n";
    } else {
        echo "❌ {$test['email']}: FALHOU\n";
    }
    echo "\n";
}

echo "2. Testando Senhas Incorretas\n";
echo "------------------------------\n";

$wrongPassword = authenticateHardcodedUser('admin@test', 'wrongpassword');
if (!$wrongPassword) {
    echo "✅ Senha incorreta rejeitada corretamente\n";
} else {
    echo "❌ ERRO: Senha incorreta foi aceita!\n";
}

echo "\n3. Testando Email Inexistente\n";
echo "------------------------------\n";

$wrongEmail = authenticateHardcodedUser('notfound@test', 'anypassword');
if (!$wrongEmail) {
    echo "✅ Email inexistente rejeitado corretamente\n";
} else {
    echo "❌ ERRO: Email inexistente foi aceito!\n";
}

echo "\n4. Verificando Funções Auxiliares\n";
echo "----------------------------------\n";

echo "isHardcodedUser('admin@test'): " . (isHardcodedUser('admin@test') ? "✅ true" : "❌ false") . "\n";
echo "isHardcodedUser('normal@email.com'): " . (!isHardcodedUser('normal@email.com') ? "✅ false" : "❌ true") . "\n";
echo "getRedirectUrlForUserType('admin'): " . getRedirectUrlForUserType('admin') . "\n";
echo "getRedirectUrlForUserType('waiter'): " . getRedirectUrlForUserType('waiter') . "\n";
echo "getRedirectUrlForUserType('customer'): " . getRedirectUrlForUserType('customer') . "\n";

echo "\n========================================\n";
echo "TESTES CONCLUÍDOS\n";
echo "========================================\n";
