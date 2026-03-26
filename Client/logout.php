<?php
require_once '../config.php';
session_start();

// Log the logout activity if user is logged in
if (isset($_SESSION['user_id'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action_type, action_description, ip_address, user_agent) VALUES (?, 'logout', 'Client logged out', ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    } catch (PDOException $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}

// Destroy all session data
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to landing page
header('Location: ../landingpage.php');
exit();
?>
