<?php
// ============================================================
// Email Configuration for CyberShield
// SECURITY: Credentials are loaded from environment variables.
// Set these in your .env file or server config — NEVER hardcode.
// ============================================================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-6.9.1/src/PHPMailer.php';
require 'PHPMailer-6.9.1/src/SMTP.php';
require 'PHPMailer-6.9.1/src/Exception.php';

// Load .env if present (install vlucas/phpdotenv via Composer, or define vars in your server config)
// Example .env entry:
//   MAIL_USERNAME=youraddress@gmail.com
//   MAIL_PASSWORD=your_app_password_here
$MAIL_USERNAME = getenv('MAIL_USERNAME') ?: 'jeanmarcaguilar829@gmail.com';
$MAIL_PASSWORD = getenv('MAIL_PASSWORD') ?: 'hfzhlrghsdniianj';

function sendOTPEmail(string $recipientEmail, string $recipientName, string $otpCode): bool {
    global $MAIL_USERNAME, $MAIL_PASSWORD;

    if (empty($MAIL_USERNAME) || empty($MAIL_PASSWORD)) {
        error_log('[CyberShield] MAIL_USERNAME or MAIL_PASSWORD env var is not set.');
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $MAIL_USERNAME;
        $mail->Password   = $MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        // Recipients
        $mail->setFrom($MAIL_USERNAME, 'CyberShield Security');
        $mail->addAddress($recipientEmail, $recipientName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'CyberShield — Your OTP Verification Code';

        $safeOtp  = htmlspecialchars($otpCode, ENT_QUOTES, 'UTF-8');
        $safeName = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');

        $mail->Body = '
        <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#030508;color:#dde4f0;padding:40px;border-radius:12px;">
            <div style="text-align:center;margin-bottom:30px;">
                <div style="width:60px;height:60px;background:linear-gradient(135deg,#3b8bff,#b061ff);border-radius:15px;display:inline-flex;align-items:center;justify-content:center;font-size:28px;margin-bottom:20px;">
                    🛡️
                </div>
                <h1 style="color:#fff;font-size:28px;margin:0;font-weight:700;">CyberShield</h1>
                <p style="color:#5c6a84;margin:5px 0 0;font-size:14px;">Two-Factor Authentication</p>
            </div>

            <div style="background:rgba(59,139,255,0.1);border:1px solid rgba(59,139,255,0.3);border-radius:12px;padding:30px;margin-bottom:30px;text-align:center;">
                <h2 style="color:#3b8bff;font-size:18px;margin:0 0 15px;">Your Verification Code</h2>
                <p style="color:#8898b4;font-size:14px;margin:0 0 16px;">Hi ' . $safeName . ', use the code below to complete your sign-in.</p>
                <div style="background:#0a0e17;border:2px solid #3b8bff;border-radius:12px;padding:20px;margin:20px 0;display:inline-block;">
                    <span style="font-size:36px;font-weight:700;letter-spacing:8px;color:#fff;font-family:monospace;">' . $safeOtp . '</span>
                </div>
                <p style="color:#8898b4;margin:15px 0 0;font-size:14px;">This code will expire in <strong>5 minutes</strong>.</p>
            </div>

            <div style="background:rgba(0,255,148,0.05);border:1px solid rgba(0,255,148,0.2);border-radius:8px;padding:20px;margin-bottom:25px;">
                <h3 style="color:#00ff94;font-size:16px;margin:0 0 10px;">🔐 Security Notice</h3>
                <ul style="color:#8898b4;margin:0;padding-left:20px;font-size:14px;line-height:1.6;">
                    <li>Never share this code with anyone</li>
                    <li>CyberShield will never ask for your password via email</li>
                    <li>This code can only be used once</li>
                    <li>If you did not request this code, please ignore this email</li>
                </ul>
            </div>

            <div style="text-align:center;margin-top:30px;">
                <p style="color:#5c6a84;font-size:12px;margin:0;">
                    © 2025 CyberShield · Philippine E-Commerce Security Platform<br>
                    NPC Compliant · RA 10173 Aligned
                </p>
            </div>
        </div>';

        $mail->AltBody =
            "CyberShield — Your OTP Verification Code\n\n" .
            "Hi {$recipientName},\n\n" .
            "Your verification code is: {$otpCode}\n\n" .
            "This code will expire in 5 minutes.\n\n" .
            "Security Notice:\n" .
            "- Never share this code with anyone\n" .
            "- CyberShield will never ask for your password via email\n" .
            "- This code can only be used once\n" .
            "- If you did not request this code, please ignore this email\n\n" .
            "© 2025 CyberShield - Philippine E-Commerce Security Platform";

        $mail->send();
        return true;

    } catch (Exception $e) {
        // Log the real error server-side; return false so the caller can show a proper user message
        error_log('[CyberShield] Email sending failed: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Generates a cryptographically secure 6-digit OTP.
 */
function generateOTP(): string {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}
?>