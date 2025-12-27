<?php
/**
 * Hardcoded Users Configuration
 * Defines fixed users with different access levels for testing and demonstration
 */

/**
 * Get hardcoded users list
 * These users bypass database authentication and provide fixed credentials
 * 
 * Note: Uses hashed passwords for security best practices, even for test users
 * 
 * @return array List of hardcoded users with their credentials and roles
 */
function getHardcodedUsers() {
    return [
        // Cliente (Customer)
        // Password: customertest
        [
            'email' => 'customer@test',
            'password_hash' => '$2y$10$Q45qg9B2gVCXgY1uR7hSY.w7S3yzeFvtaMJL03dw7q5kS.ck/wlwa',
            'full_name' => 'Cliente Teste',
            'user_type' => 'customer',
            'role' => 'Cliente',
            'email_verified' => true,
            'is_active' => true,
            'id' => -1  // Negative ID convention: -1 for customer
        ],
        
        // Garçom (Waiter)
        // Password: waitertest
        [
            'email' => 'waiter@test',
            'password_hash' => '$2y$10$M3o7ahww2WDlKEoqjWJheu/jmImNetoMKJD2gwalqjKAKyI/IpngS',
            'full_name' => 'Garçom Teste',
            'user_type' => 'waiter',
            'role' => 'Atendente',
            'email_verified' => true,
            'is_active' => true,
            'id' => -2  // Negative ID convention: -2 for waiter
        ],
        
        // Admin (Administrator)
        // Password: admintest
        [
            'email' => 'admin@test',
            'password_hash' => '$2y$10$jtzSzEsBr33PGIyly00ArevHJh3HCydZkBmy3rR4Dc7sv2dMA8Frq',
            'full_name' => 'Administrador Teste',
            'user_type' => 'admin',
            'role' => 'Admin',
            'email_verified' => true,
            'is_active' => true,
            'id' => -3  // Negative ID convention: -3 for admin
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
        // Check email match and verify password hash
        if ($user['email'] === $email && password_verify($password, $user['password_hash'])) {
            // Remove password hash from returned data
            unset($user['password_hash']);
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
