<?php
/**
 * Email Verification API
 * Verifies user email address using token
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/security.php';

// Get verification token from URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    die('Invalid verification link. No token provided.');
}

try {
    $pdo = getDBConnection();
    
    // Find user with this verification token
    $stmt = $pdo->prepare("
        SELECT id, email, full_name, email_verified 
        FROM users 
        WHERE verification_token = ?
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        die('Invalid verification link. Token not found.');
    }
    
    if ($user['email_verified']) {
        // Already verified
        header('Location: /login.html?verified=already');
        exit();
    }
    
    // Update user as verified
    $stmt = $pdo->prepare("
        UPDATE users 
        SET email_verified = TRUE, verification_token = NULL 
        WHERE id = ?
    ");
    $stmt->execute([$user['id']]);
    
    // Redirect to login page with success message
    header('Location: /login.html?verified=success');
    exit();
    
} catch (Exception $e) {
    error_log("Email verification failed: " . $e->getMessage());
    die('An error occurred during email verification. Please try again or contact support.');
}
