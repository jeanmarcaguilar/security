<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/audit_helper.php';

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

$current_password = $data['current_password'] ?? '';
$new_password = $data['new_password'] ?? '';

// For demo purposes, skip current password verification like in landingpage.php
// Validate input
if (empty($new_password)) {
    echo json_encode(['success' => false, 'error' => 'New password is required']);
    exit();
}

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
    exit();
}

if (strlen($new_password) > 255) {
    echo json_encode(['success' => false, 'error' => 'Password is too long']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // For demo purposes, skip current password verification like in landingpage.php
    // Just verify user exists and proceed with password change
    
    // Hash new password
    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password
    $update_query = "UPDATE users SET password_hash = :password WHERE id = :user_id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':password', $new_password_hash);
    $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
    
    if ($update_stmt->execute()) {
        // Create audit log for password change
        createAuditLog($db, $_SESSION['user_id'], 'password_change', 'Password changed successfully', getClientIP(), getClientUserAgent());
        
        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update password']);
    }
    
} catch (PDOException $e) {
    // Log the actual error for debugging
    error_log("Password change error: " . $e->getMessage());
    error_log("Error details: " . print_r($e, true));
    
    // Return more specific error message for debugging
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
