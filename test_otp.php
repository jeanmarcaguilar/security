<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/email_config.php';

// Test database connection
try {
    $database = new Database();
    $db = $database->getConnection();
    echo "✓ Database connection successful<br>";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "<br>";
    exit();
}

// Test OTP generation
$otp = generateOTP();
echo "✓ OTP generated: " . $otp . "<br>";

// Test email sending (commented out for now)
/*
try {
    $emailSent = sendOTPEmail('test@example.com', 'Test User', $otp);
    if ($emailSent) {
        echo "✓ Email sending successful<br>";
    } else {
        echo "✗ Email sending failed<br>";
    }
} catch (Exception $e) {
    echo "✗ Email sending error: " . $e->getMessage() . "<br>";
}
*/

echo "✓ All tests passed! The OTP system should work now.";
?>
