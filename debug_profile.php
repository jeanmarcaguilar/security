<?php
session_start();
require_once 'includes/config.php';

// Simulate a logged-in user for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

// Get user data
$database = new Database();
$db = $database->getConnection();

$user_query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($user_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>OTP Debug Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .btn { padding: 10px 20px; background: #3b8bff; color: white; border: none; cursor: pointer; margin: 10px; }
        .error { color: red; }
        .success { color: green; }
        #debug { background: #f5f5f5; padding: 10px; margin: 10px 0; white-space: pre-wrap; }
    </style>
</head>
<body>
    <h1>OTP System Debug Test</h1>
    
    <p>Logged in user: <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['email'] . ')'); ?></p>
    
    <button class="btn" onclick="testSendOTP()">Test Send OTP</button>
    <button class="btn" onclick="testVerifyOTP()">Test Verify OTP (123456)</button>
    
    <div id="debug"></div>
    
    <script>
        function log(message) {
            const debug = document.getElementById('debug');
            debug.textContent += new Date().toLocaleTimeString() + ': ' + message + '\n';
        }
        
        async function testSendOTP() {
            log('Testing send OTP...');
            try {
                const response = await fetch('api/send_otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type: 'profile' })
                });
                
                log('Response status: ' + response.status);
                const result = await response.json();
                log('Response: ' + JSON.stringify(result, null, 2));
                
                if (result.success) {
                    log('✓ OTP sent successfully!');
                } else {
                    log('✗ OTP send failed: ' + result.error);
                }
            } catch (error) {
                log('✗ Network error: ' + error.message);
            }
        }
        
        async function testVerifyOTP() {
            log('Testing verify OTP with 123456...');
            try {
                const response = await fetch('api/verify_otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type: 'profile', otp: '123456' })
                });
                
                log('Response status: ' + response.status);
                const result = await response.json();
                log('Response: ' + JSON.stringify(result, null, 2));
                
                if (result.success) {
                    log('✓ OTP verified successfully!');
                } else {
                    log('✗ OTP verification failed: ' + result.error);
                }
            } catch (error) {
                log('✗ Network error: ' + error.message);
            }
        }
    </script>
</body>
</html>
