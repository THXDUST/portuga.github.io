<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

session_start();
$_SESSION['user_id'] = -3;
$_SESSION['is_hardcoded_user'] = true;

echo json_encode([
    'test' => 'Session test',
    'user_id' => $_SESSION['user_id'] ?? null,
    'is_hardcoded' => $_SESSION['is_hardcoded_user'] ?? false
]);
