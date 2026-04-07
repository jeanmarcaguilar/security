<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log all requests
error_log("Simple email API called at " . date('Y-m-d H:i:s'));
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("Session data: " . print_r($_SESSION, true));

// TEMPORARILY DISABLE AUTHENTICATION FOR TESTING
// if (!isset($_SESSION['user_id'])) {
//     error_log("User not authenticated");
//     echo json_encode(['success' => false, 'error' => 'Not authenticated - please log in first']);
//     exit();
// }

// Get JSON input
$json = file_get_contents('php://input');
error_log("Raw input: " . $json);

$data = json_decode($json, true);

if (!$data) {
    error_log("Invalid JSON: " . json_last_error_msg());
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit();
}

$recipientEmail = $data['to'] ?? '';
$userId = $data['userId'] ?? '';

error_log("Email request: to=$recipientEmail, userId=$userId");

// Basic validation
if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
    exit();
}

if (empty($userId)) {
    echo json_encode(['success' => false, 'error' => 'User ID is required']);
    exit();
}

try {
    // Test database connection
    $database = new Database();
    $db = $database->getConnection();

    error_log("Database connected successfully");

    // Get user data
    $stmt = $db->prepare("SELECT id, full_name, email, store_name, last_assessment_score FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log("User not found: ID $userId");
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }

    error_log("User found: " . $user['full_name']);

    // Simulate email sending (just log it for now)
    error_log("SIMULATED: Would send email to $recipientEmail");
    error_log("User data: " . print_r($user, true));

    echo json_encode([
        'success' => true,
        'message' => 'Email sent successfully (simulated)',
        'recipient' => $recipientEmail,
        'userName' => $user['full_name'] ?: $user['store_name'],
        'userScore' => $user['last_assessment_score'],
        'debug' => [
            'session_id' => session_id(),
            'authenticated' => isset($_SESSION['user_id']),
            'user_id_in_session' => $_SESSION['user_id'] ?? 'none'
        ]
    ]);

} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>