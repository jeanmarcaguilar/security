<?php
session_start();
require_once 'includes/email_config.php';

echo "<h2>Current OTP Codes in Session</h2>";

if (isset($_SESSION['otp_profile'])) {
    $profile_otp = $_SESSION['otp_profile'];
    echo "<p><strong>Profile OTP:</strong> " . $profile_otp['code'] . "</p>";
    echo "<p><strong>Expires:</strong> " . date('Y-m-d H:i:s', $profile_otp['expires_at']) . "</p>";
    echo "<p><strong>Attempts:</strong> " . $profile_otp['attempts'] . "</p>";
} else {
    echo "<p>No profile OTP found in session</p>";
}

if (isset($_SESSION['otp_password'])) {
    $password_otp = $_SESSION['otp_password'];
    echo "<p><strong>Password OTP:</strong> " . $password_otp['code'] . "</p>";
    echo "<p><strong>Expires:</strong> " . date('Y-m-d H:i:s', $password_otp['expires_at']) . "</p>";
    echo "<p><strong>Attempts:</strong> " . $password_otp['attempts'] . "</p>";
} else {
    echo "<p>No password OTP found in session</p>";
}

// Generate a new OTP for testing
$new_otp = generateOTP();
echo "<h3>New Test OTP: " . $new_otp . "</h3>";
echo "<p>This OTP is valid for testing purposes.</p>";
?>
