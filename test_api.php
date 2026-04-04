<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/email_config.php';

// Simulate a logged-in user
$_SESSION['user_id'] = 1;

// Test sending OTP
header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get user's email (using a test user ID)
    $user_query = "SELECT email, full_name FROM users WHERE id = :user_id";
    $stmt = $db->prepare($user_query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }
    
    // Generate OTP
    $otp = generateOTP();
    
    // Store OTP in session
    $_SESSION['otp_profile'] = [
        'code' => $otp,
        'expires' => time() + 300,
        'attempts' => 0
    ];
    
    // For testing, don't actually send email, just show the OTP
    echo json_encode([
        'success' => true, 
        'message' => 'OTP generated successfully (email sending disabled for testing)',
        'otp' => $otp, // Only for testing!
        'email' => $user['email']
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
