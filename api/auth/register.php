<?php
/**
 * User Registration API
 * Handles new user registration with email verification
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/session.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate CSRF token
    if (!isset($input['csrf_token']) || !validateCSRFToken($input['csrf_token'])) {
        throw new Exception('Invalid CSRF token');
    }
    
    // Sanitize inputs
    $fullName = sanitizeInput($input['full_name'] ?? '');
    $email = sanitizeInput($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';
    $termsAccepted = isset($input['terms_accepted']) && $input['terms_accepted'] === true;
    
    // Validate required fields
    if (empty($fullName)) {
        throw new Exception('Full name is required');
    }
    
    if (empty($email)) {
        throw new Exception('Email is required');
    }
    
    if (empty($password)) {
        throw new Exception('Password is required');
    }
    
    if (!$termsAccepted) {
        throw new Exception('You must accept the terms of use');
    }
    
    // Validate email format
    if (!validateEmail($email)) {
        throw new Exception('Invalid email format');
    }
    
    // Validate password match
    if ($password !== $confirmPassword) {
        throw new Exception('Passwords do not match');
    }
    
    // Validate password strength
    $passwordValidation = validatePasswordStrength($password);
    if (!$passwordValidation['valid']) {
        throw new Exception($passwordValidation['message']);
    }
    
    // Check if email already exists
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        throw new Exception('An account with this email already exists');
    }
    
    // Double encrypt password
    $passwordHash = doubleEncrypt($password, $email);
    
    // Generate verification token
    $verificationToken = generateToken();
    
    // Insert new user
    $stmt = $pdo->prepare("
        INSERT INTO users (full_name, email, password_hash, verification_token, email_verified)
        VALUES (?, ?, ?, ?, FALSE)
    ");
    
    $stmt->execute([$fullName, $email, $passwordHash, $verificationToken]);
    $userId = $pdo->lastInsertId();
    
    // Send verification email (in production, implement actual email sending)
    $verificationLink = "https://" . $_SERVER['HTTP_HOST'] . "/api/auth/verify-email.php?token=" . $verificationToken;
    
    // Log the verification link for development
    error_log("Verification link for $email: $verificationLink");
    
    // In production, send email here:
    // sendVerificationEmail($email, $fullName, $verificationLink);
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful! Please check your email to verify your account.',
        'user_id' => $userId,
        'verification_required' => true
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
