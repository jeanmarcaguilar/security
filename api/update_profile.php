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

$full_name = trim($data['full_name'] ?? '');
$email = trim($data['email'] ?? '');
$store_name = trim($data['store_name'] ?? '');

// Validate input
if (empty($full_name)) {
    echo json_encode(['success' => false, 'error' => 'Display name is required']);
    exit();
}

if (empty($email)) {
    echo json_encode(['success' => false, 'error' => 'Email address is required']);
    exit();
}

// Email format validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Please enter a valid email address']);
    exit();
}

if (strlen($full_name) > 100) {
    echo json_encode(['success' => false, 'error' => 'Display name is too long']);
    exit();
}

if (strlen($email) > 255) {
    echo json_encode(['success' => false, 'error' => 'Email address is too long']);
    exit();
}

if (strlen($store_name) > 100) {
    echo json_encode(['success' => false, 'error' => 'Store name is too long']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get current user data for comparison
    $user_query = "SELECT full_name, email, store_name FROM users WHERE id = :user_id";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $user_stmt->execute();
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    // Check if email is already taken by another user
    $email_check_query = "SELECT id FROM users WHERE email = :email AND id != :user_id";
    $email_stmt = $db->prepare($email_check_query);
    $email_stmt->bindParam(':email', $email);
    $email_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $email_stmt->execute();

    if ($email_stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'error' => 'Email address is already in use']);
        exit();
    }

    $query = "UPDATE users SET full_name = :full_name, email = :email, store_name = :store_name WHERE id = :user_id";
    $stmt = $db->prepare($query);

    $stmt->bindParam(':full_name', $full_name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':store_name', $store_name);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);

    if ($stmt->execute()) {
        // Update session data
        $_SESSION['user_full_name'] = $full_name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_store_name'] = $store_name;

        // Create audit log for profile update
        $changes = [];
        if ($user['full_name'] !== $full_name)
            $changes[] = "name to '$full_name'";
        if ($user['email'] !== $email)
            $changes[] = "email to '$email'";
        if ($user['store_name'] !== $store_name)
            $changes[] = "store name to '$store_name'";

        $action_description = "Updated profile: " . implode(', ', $changes);
        createAuditLog($db, $_SESSION['user_id'], 'profile_update', $action_description, getClientIP(), getClientUserAgent());

        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update profile']);
    }

} catch (PDOException $e) {
    // Log the actual error for debugging
    error_log("Profile update error: " . $e->getMessage());
    error_log("Error details: " . print_r($e, true));

    // Return more specific error message for debugging
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>