<?php
// Detailed Email Test with Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Detailed Email Configuration Test</h1>";

// Load email config
require_once 'includes/email_config.php';

echo "<h2>1. PHPMailer Check</h2>";
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "<p style='color: green;'>✅ PHPMailer class exists</p>";
} else {
    echo "<p style='color: red;'>❌ PHPMailer class not found</p>";
    exit();
}

echo "<h2>2. Environment Variables</h2>";
$mailUsername = getenv('MAIL_USERNAME') ?: 'jeanmarcaguilar829@gmail.com';
$mailPassword = getenv('MAIL_PASSWORD') ?: 'dadlprmmhqatdjda';

echo "<p><strong>MAIL_USERNAME:</strong> " . htmlspecialchars($mailUsername) . "</p>";
echo "<p><strong>MAIL_PASSWORD:</strong> " . (empty($mailPassword) ? 'NOT SET' : 'SET (' . strlen($mailPassword) . ' chars)') . "</p>";

echo "<h2>3. SMTP Connection Test</h2>";

try {
    $mail = new PHPMailer(true);
    
    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $mailUsername;
    $mail->Password   = $mailPassword;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    
    // Enable verbose debugging
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    $mail->Debugoutput = 'html';
    
    echo "<p>Attempting SMTP connection to gmail.com:465...</p>";
    
    // Test connection without sending
    $mail->SMTPConnect();
    
    echo "<p style='color: green;'>✅ SMTP connection successful</p>";
    
    // Test OTP generation
    echo "<h2>4. OTP Generation Test</h2>";
    $testOTP = generateOTP();
    echo "<p><strong>Generated OTP:</strong> " . $testOTP . "</p>";
    
    // Test email sending
    echo "<h2>5. Email Sending Test</h2>";
    $testEmail = 'jeanmarcaguilar829@gmail.com';
    $testName = 'Test User';
    
    echo "<p>Sending test email to $testEmail...</p>";
    
    $mail->setFrom($mailUsername, 'CyberShield Security');
    $mail->addAddress($testEmail, $testName);
    $mail->isHTML(true);
    $mail->Subject = 'Test Email - CyberShield';
    $mail->Body = '<h1>Test Email</h1><p>This is a test email from CyberShield.</p>';
    $mail->AltBody = 'Test Email - This is a test email from CyberShield.';
    
    $mail->send();
    echo "<p style='color: green;'>✅ Email sent successfully!</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p style='color: red;'>❌ PHPMailer Error: " . htmlspecialchars($mail->ErrorInfo) . "</p>";
    
    // Common issues and solutions
    echo "<h3>Common Issues & Solutions:</h3>";
    echo "<ul>";
    
    if (strpos($e->getMessage(), 'authentication') !== false) {
        echo "<li><strong>Authentication Failed:</strong> Check if the Gmail password is correct and if 2FA is enabled, use an App Password.</li>";
    }
    
    if (strpos($e->getMessage(), 'connection') !== false) {
        echo "<li><strong>Connection Failed:</strong> Check if port 465 is blocked by firewall/antivirus.</li>";
    }
    
    if (strpos($e->getMessage(), 'certificate') !== false) {
        echo "<li><strong>SSL Certificate Issue:</strong> XAMPP SSL configuration problem.</li>";
    }
    
    echo "</ul>";
}

echo "<h2>6. Server Information</h2>";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
echo "<p><strong>OpenSSL:</strong> " . (extension_loaded('openssl') ? 'Enabled' : 'Disabled') . "</p>";
echo "<p><strong>SMTP:</strong> " . (extension_loaded('sockets') ? 'Enabled' : 'Disabled') . "</p>";

?>
