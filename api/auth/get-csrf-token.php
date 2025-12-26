<?php
/**
 * Get CSRF Token API
 * Returns a CSRF token for use in authentication forms
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = generateCSRFToken();
    
    echo json_encode([
        'success' => true,
        'token' => $token
    ]);
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
