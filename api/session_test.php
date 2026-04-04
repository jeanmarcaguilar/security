<?php
session_start();

header('Content-Type: application/json');

// Check session status
$sessionStatus = [
    'session_id' => session_id(),
    'session_status' => session_status(),
    'session_data' => $_SESSION,
    'cookies' => $_COOKIE,
    'headers' => getallheaders()
];

echo json_encode([
    'success' => true,
    'message' => 'Session check complete',
    'session_info' => $sessionStatus,
    'user_logged_in' => isset($_SESSION['user_id']),
    'user_id' => $_SESSION['user_id'] ?? null
]);
?>
