<?php
/**
 * Hardcoded Users Configuration
 * Defines fixed users with different access levels for testing and demonstration
 */

/**
 * Get hardcoded users list
 * These users bypass database authentication and provide fixed credentials
 * 
 * @return array List of hardcoded users with their credentials and roles
 */
function getHardcodedUsers() {
    return [
        // Cliente (Customer)
        [
            'email' => 'customer@test',
            'password' => 'customertest',
            'full_name' => 'Cliente Teste',
            'user_type' => 'customer',
            'role' => 'Cliente',
            'email_verified' => true,
            'is_active' => true,
            'id' => -1  // Negative ID to distinguish from database users
        ],
        
        // Garçom (Waiter)
        [
            'email' => 'waiter@test',
            'password' => 'waitertest',
            'full_name' => 'Garçom Teste',
            'user_type' => 'waiter',
            'role' => 'Atendente',
            'email_verified' => true,
            'is_active' => true,
            'id' => -2  // Negative ID to distinguish from database users
        ],
        
        // Admin (Administrator)
        [
            'email' => 'admin@test',
            'password' => 'admintest',
            'full_name' => 'Administrador Teste',
            'user_type' => 'admin',
            'role' => 'Admin',
            'email_verified' => true,
            'is_active' => true,
            'id' => -3  // Negative ID to distinguish from database users
        ]
    ];
}

/**
 * Authenticate hardcoded user
 * Checks credentials against hardcoded users list
 * 
 * @param string $email User email
 * @param string $password Plain text password
 * @return array|null User data if authentication successful, null otherwise
 */
function authenticateHardcodedUser($email, $password) {
    $hardcodedUsers = getHardcodedUsers();
    
    foreach ($hardcodedUsers as $user) {
        // Direct string comparison for hardcoded users (secure for testing only)
        if ($user['email'] === $email && $user['password'] === $password) {
            // Remove password from returned data
            unset($user['password']);
            return $user;
        }
    }
    
    return null;
}

/**
 * Check if email belongs to a hardcoded user
 * 
 * @param string $email Email to check
 * @return bool True if email is a hardcoded user
 */
function isHardcodedUser($email) {
    $hardcodedUsers = getHardcodedUsers();
    
    foreach ($hardcodedUsers as $user) {
        if ($user['email'] === $email) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get redirect URL based on user type
 * 
 * @param string $userType User type (customer, waiter, admin)
 * @return string Redirect URL
 */
function getRedirectUrlForUserType($userType) {
    switch ($userType) {
        case 'admin':
            return '/admin.html';
        case 'waiter':
            return '/pedidos.html';  // Waiter goes to orders page
        case 'customer':
        default:
            return '/index.html';  // Customer goes to main page
    }
}
