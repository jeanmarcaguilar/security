<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/email_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit();
}

$type = $data['type'] ?? ''; // 'profile' or 'password'

if (!in_array($type, ['profile', 'password'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid OTP type']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get user's email
    $user_query = "SELECT email, full_name FROM users WHERE id = :user_id";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $user_stmt->execute();
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }

    // Generate OTP
    $otp = generateOTP();

    // Store OTP in session with expiration
    $_SESSION['otp_' . $type] = [
        'code' => $otp,
        'expires_at' => time() + 300, // 5 minutes
        'attempts' => 0
    ];

    // Send OTP email
    $emailSent = sendOTPEmail($user['email'], $user['full_name'], $otp);

    // Always show OTP in development mode for testing
    echo json_encode([
        'success' => true,
        'message' => 'OTP generated for testing',
        'otp' => $otp, // Show OTP for development
        'email' => $user['email'],
        'dev_mode' => true
    ]);

} catch (PDOException $e) {
    error_log("OTP sending error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>