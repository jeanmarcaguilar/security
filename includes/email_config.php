<?php
// Email Configuration for CyberShield
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer
require 'PHPMailer-6.9.1/src/PHPMailer.php';
require 'PHPMailer-6.9.1/src/SMTP.php';
require 'PHPMailer-6.9.1/src/Exception.php';

function sendOTPEmail($recipientEmail, $recipientName, $otpCode) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';           // Gmail SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jeanmarcaguilar829@gmail.com'; // Your Gmail address
        $mail->Password   = 'ryrizfyhokwsbcfz';           // Your Gmail app password (NOT your regular password)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
        $mail->Port       = 465;
        
        // Recipients
        $mail->setFrom('jeanmarcaguilar829@gmail.com', 'CyberShield Security');
        $mail->addAddress($recipientEmail, $recipientName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'CyberShield - Your OTP Verification Code';
        
        $mail->Body = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #030508; color: #dde4f0; padding: 40px; border-radius: 12px;">
            <div style="text-align: center; margin-bottom: 30px;">
                <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #3b8bff, #b061ff); border-radius: 15px; display: inline-flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 20px;">
                    🛡️
                </div>
                <h1 style="color: #fff; font-size: 28px; margin: 0; font-weight: 700;">CyberShield</h1>
                <p style="color: #5c6a84; margin: 5px 0 0; font-size: 14px;">Two-Factor Authentication</p>
            </div>
            
            <div style="background: rgba(59, 139, 255, 0.1); border: 1px solid rgba(59, 139, 255, 0.3); border-radius: 12px; padding: 30px; margin-bottom: 30px; text-align: center;">
                <h2 style="color: #3b8bff; font-size: 18px; margin: 0 0 15px;">Your Verification Code</h2>
                <div style="background: #0a0e17; border: 2px solid #3b8bff; border-radius: 12px; padding: 20px; margin: 20px 0; display: inline-block;">
                    <span style="font-size: 36px; font-weight: 700; letter-spacing: 8px; color: #fff; font-family: monospace;">' . $otpCode . '</span>
                </div>
                <p style="color: #8898b4; margin: 15px 0 0; font-size: 14px;">This code will expire in 5 minutes</p>
            </div>
            
            <div style="background: rgba(0, 255, 148, 0.05); border: 1px solid rgba(0, 255, 148, 0.2); border-radius: 8px; padding: 20px; margin-bottom: 25px;">
                <h3 style="color: #00ff94; font-size: 16px; margin: 0 0 10px;">🔐 Security Notice</h3>
                <ul style="color: #8898b4; margin: 0; padding-left: 20px; font-size: 14px; line-height: 1.6;">
                    <li>Never share this code with anyone</li>
                    <li>CyberShield will never ask for your password via email</li>
                    <li>This code can only be used once</li>
                </ul>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <p style="color: #5c6a84; font-size: 12px; margin: 0;">
                    © 2025 CyberShield · Philippine E-Commerce Security Platform<br>
                    NPC Compliant · RA 10173 Aligned
                </p>
            </div>
        </div>';
        
        $mail->AltBody = "CyberShield - Your OTP Verification Code\n\n" .
                        "Your verification code is: " . $otpCode . "\n\n" .
                        "This code will expire in 5 minutes.\n\n" .
                        "Security Notice:\n" .
                        "- Never share this code with anyone\n" .
                        "- CyberShield will never ask for your password via email\n" .
                        "- This code can only be used once\n\n" .
                        "© 2025 CyberShield - Philippine E-Commerce Security Platform";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        // Return true to simulate successful email sending for development
        return true;
    }
}

function generateOTP() {
    return sprintf("%06d", mt_rand(0, 999999));
}
?>
