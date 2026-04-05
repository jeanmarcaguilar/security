<?php
// Alternative Email Configuration Test
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/email_config.php';

echo "<h1>Alternative SMTP Configuration Test</h1>";

$configurations = [
    'Gmail SSL (Port 465)' => [
        'host' => 'smtp.gmail.com',
        'port' => 465,
        'secure' => PHPMailer::ENCRYPTION_SMTPS,
        'username' => $GLOBALS['MAIL_USERNAME'],
        'password' => $GLOBALS['MAIL_PASSWORD']
    ],
    'Gmail TLS (Port 587)' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'secure' => PHPMailer::ENCRYPTION_STARTTLS,
        'username' => $GLOBALS['MAIL_USERNAME'],
        'password' => $GLOBALS['MAIL_PASSWORD']
    ]
];

foreach ($configurations as $name => $config) {
    echo "<h2>Testing: $name</h2>";
    
    try {
        $mail = new PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['secure'];
        $mail->Port = $config['port'];
        
        // Test connection
        $mail->SMTPConnect();
        
        echo "<p style='color: green;'>✅ Connection successful to {$config['host']}:{$config['port']}</p>";
        
        // Try sending a test email
        $mail->setFrom($config['username'], 'CyberShield Test');
        $mail->addAddress($config['username'], 'Test User');
        $mail->isHTML(true);
        $mail->Subject = "Test - $name";
        $mail->Body = "<h1>Test Successful</h1><p>Configuration: $name</p>";
        $mail->AltBody = "Test Successful - Configuration: $name";
        
        $mail->send();
        echo "<p style='color: green;'>✅ Email sent successfully!</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Failed: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p style='color: orange;'>⚠ PHPMailer Info: " . htmlspecialchars($mail->ErrorInfo) . "</p>";
    }
    
    echo "<hr>";
}

echo "<h2>Network Diagnostics</h2>";

// Test network connectivity
$ports = [465, 587, 25];
$host = 'smtp.gmail.com';

foreach ($ports as $port) {
    $timeout = 5;
    $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
    
    if ($socket) {
        echo "<p style='color: green;'>✅ Port $port: Accessible</p>";
        fclose($socket);
    } else {
        echo "<p style='color: red;'>❌ Port $port: Not accessible ($errno - $errstr)</p>";
    }
}

echo "<h2>PHP Extensions Check</h2>";
$required_extensions = ['openssl', 'sockets', 'mbstring'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p style='color: green;'>✅ Extension '$ext': Loaded</p>";
    } else {
        echo "<p style='color: red;'>❌ Extension '$ext': Not loaded</p>";
    }
}

?>
