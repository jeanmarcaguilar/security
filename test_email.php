<?php
// Email Test Script
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Email Configuration Test</h1>";

// Load email config
require_once 'includes/email_config.php';

// Test OTP generation
$testOTP = generateOTP();
echo "<p><strong>Generated OTP:</strong> " . $testOTP . "</p>";

// Test email sending
$testEmail = 'jeanmarcaguilar829@gmail.com';
$testName = 'Test User';

echo "<h3>Testing Email Send...</h3>";

try {
    $result = sendOTPEmail($testEmail, $testName, $testOTP);
    
    if ($result) {
        echo "<p style='color: green;'>✅ Email function returned true</p>";
    } else {
        echo "<p style='color: red;'>❌ Email function returned false</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Exception: " . $e->getMessage() . "</p>";
}

// Check PHPMailer installation
echo "<h3>PHPMailer Check:</h3>";
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "<p style='color: green;'>✅ PHPMailer class exists</p>";
} else {
    echo "<p style='color: red;'>❌ PHPMailer class not found</p>";
}

// Check environment variables
echo "<h3>Environment Variables:</h3>";
$mailUsername = getenv('MAIL_USERNAME') ?: 'jeanmarcaguilar829@gmail.com';
$mailPassword = getenv('MAIL_PASSWORD') ?: 'dadlprmmhqatdjda';

echo "<p><strong>MAIL_USERNAME:</strong> " . $mailUsername . "</p>";
echo "<p><strong>MAIL_PASSWORD:</strong> " . (empty($mailPassword) ? 'NOT SET' : 'SET') . "</p>";

?>
