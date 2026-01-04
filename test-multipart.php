<?php
header('Content-Type: application/json');
echo json_encode([
    'post' => $_POST,
    'files' => $_FILES,
    'server_content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
]);
