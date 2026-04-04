<?php
// Simple test file to check if the API is working
header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'API test endpoint is working',
    'timestamp' => date('Y-m-d H:i:s'),
    'server_info' => [
        'php_version' => phpversion(),
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
    ]
]);
?>
