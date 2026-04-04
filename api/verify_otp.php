<?php
session_start();
require_once '../includes/config.php';

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
$otp = $data['otp'] ?? '';

if (!in_array($type, ['profile', 'password'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid OTP type']);
    exit();
}

if (empty($otp)) {
    echo json_encode(['success' => false, 'error' => 'OTP is required']);
    exit();
}

try {
    // Check if OTP exists in session
    if (!isset($_SESSION['otp_' . $type])) {
        echo json_encode(['success' => false, 'error' => 'OTP not requested or expired']);
        exit();
    }
    
    $otpData = $_SESSION['otp_' . $type];
    
    // Check if OTP has expired
    if (time() > $otpData['expires_at']) {
        unset($_SESSION['otp_' . $type]);
        echo json_encode(['success' => false, 'error' => 'OTP has expired']);
        exit();
    }
    
    // Check attempts (max 3 attempts)
    if ($otpData['attempts'] >= 3) {
        unset($_SESSION['otp_' . $type]);
        echo json_encode(['success' => false, 'error' => 'Too many failed attempts. Please request a new OTP.']);
        exit();
    }
    
    // Verify OTP
    if ($otp === $otpData['code']) {
        // OTP is correct, clear it from session
        unset($_SESSION['otp_' . $type]);
        echo json_encode(['success' => true, 'message' => 'OTP verified successfully']);
    } else {
        // Increment attempts
        $_SESSION['otp_' . $type]['attempts']++;
        $remainingAttempts = 3 - $_SESSION['otp_' . $type]['attempts'];
        
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid OTP',
            'remaining_attempts' => $remainingAttempts
        ]);
    }
    
} catch (Exception $e) {
    error_log("OTP verification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>
