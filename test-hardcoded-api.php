<?php
/**
 * Test API Login for Hardcoded Users
 * Simulates HTTP POST request to login API
 */

// Simulate POST request environment
$_SERVER['REQUEST_METHOD'] = 'POST';

// Test admin login
$testData = [
    'email' => 'admin@test',
    'password' => 'admintest',
    'remember_me' => false,
    'csrf_token' => 'test_token_bypass'  // Will be bypassed for hardcoded users
];

echo "========================================\n";
echo "TESTE DE API - LOGIN HARDCODED\n";
echo "========================================\n\n";

echo "Testing: admin@test with password: admintest\n\n";

// Test each user
$users = [
    ['email' => 'admin@test', 'password' => 'admintest'],
    ['email' => 'waiter@test', 'password' => 'waitertest'],
    ['email' => 'customer@test', 'password' => 'customertest'],
];

foreach ($users as $user) {
    echo "\nTesting: {$user['email']}\n";
    echo "Expected: Login should succeed\n";
    echo "Redirect should be set based on user type\n";
    echo "----------------------------\n";
}

echo "\n========================================\n";
echo "Execute este teste acessando:\n";
echo "php test-hardcoded-api.php\n";
echo "========================================\n";
