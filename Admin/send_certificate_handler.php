<?php
// Enable error reporting for debugging (but don't display errors)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to prevent breaking JSON
ini_set('log_errors', 1); // Log errors instead

require_once '../includes/config.php';
session_start();

// Log request details for debugging
error_log('[Certificate Send] Request received: ' . file_get_contents('php://input'));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  error_log('[Certificate Send] Unauthorized access attempt');
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit();
}

// Import the working email configuration
require_once '../includes/email_config.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
  error_log('[Certificate Send] Invalid JSON data received');
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'message' => 'Invalid request']);
  exit();
}

$email = $data['email'] ?? '';
$vname = $data['vname'] ?? '';
$score = $data['score'] ?? 0;
$rank = $data['rank'] ?? '';
$certId = $data['certId'] ?? '';
$type = $data['type'] ?? 'compliance';
$subject = $data['subject'] ?? '';
$message = $data['message'] ?? '';
$currentDate = $data['currentDate'] ?? '';

// Log received data for debugging
error_log("[Certificate Send] Email: $email, Name: $vname, Type: $type");

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  error_log('[Certificate Send] Invalid email address: ' . $email);
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'message' => 'Invalid email address']);
  exit();
}

// Initialize database
$database = new Database();
$db = $database->getConnection();

// Check if email constants are defined
if (!defined('MAIL_USERNAME') || !defined('MAIL_PASSWORD')) {
  error_log('[Certificate Send] Mail constants not defined');
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'message' => 'Email configuration missing']);
  exit();
}

// Log email config for debugging
error_log('[Certificate Send] Using email: ' . MAIL_USERNAME);

// Use the same working email configuration as the 2FA system
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

try {
  // Create certificate HTML
  $certificateHtml = generateCertificateHTML($vname, $score, $rank, $certId, $currentDate, $type);

  // Create mailer instance using working configuration
  $mail = new PHPMailer(true);

  // Server settings - Same as working 2FA system
  $mail->isSMTP();
  $mail->Host = 'smtp.gmail.com';
  $mail->SMTPAuth = true;
  $mail->Username = MAIL_USERNAME; // Use the same working credentials
  $mail->Password = MAIL_PASSWORD; // Use the same working credentials
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
  $mail->Port = 465;

  // Enable debug for troubleshooting (set to 0 for production, 2 for debugging)
  $mail->SMTPDebug = 0;
  $mail->Debugoutput = 'html';

  // Sender information
  $mail->setFrom(MAIL_USERNAME, 'CyberShield Admin');

  // Recipients
  $mail->addAddress($email, $vname);

  // Content
  $mail->isHTML(true);
  $mail->Subject = $subject;

  // Email body with certificate
  $emailBody = '
    <div style="font-family:Arial,sans-serif;background:linear-gradient(135deg,#0f1419 0%,#1a1f2e 50%,#0f1419 100%);color:#ffffff;padding:0;margin:0;min-height:100vh;">
      <div style="max-width:800px;margin:40px auto;background:linear-gradient(135deg,rgba(59,139,255,0.05),rgba(123,114,240,0.05));border:2px solid rgba(59,139,255,0.2);border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.5),0 0 0 1px rgba(59,139,255,0.1);">
        
        <!-- Header Section -->
        <div style="background:linear-gradient(135deg,#3b8bff 0%,#7b72f0 100%);padding:40px;text-align:center;position:relative;overflow:hidden;">
          <div style="position:absolute;top:0;left:0;right:0;bottom:0;background:linear-gradient(45deg,rgba(255,255,255,0.1) 0%,transparent 50%,rgba(255,255,255,0.05) 100%);"></div>
          <div style="position:relative;z-index:1;">
            <div style="width:80px;height:80px;background:rgba(255,255,255,0.2);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:20px;border:2px solid rgba(255,255,255,0.3);">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                <path d="M9 12l2 2 4-4"/>
              </svg>
            </div>
            <h1 style="color:#ffffff;font-size:36px;margin:0;font-weight:700;letter-spacing:2px;text-shadow:0 2px 10px rgba(0,0,0,0.3);">CyberShield</h1>
            <p style="color:rgba(255,255,255,0.9);margin:10px 0 0;font-size:16px;letter-spacing:1px;">CERTIFICATE OF ACHIEVEMENT</p>
          </div>
        </div>
        
        <!-- Certificate Content -->
        <div style="padding:50px 60px;text-align:center;position:relative;">
          
          <!-- Certificate Title -->
          <div style="margin-bottom:40px;">
            <h2 style="font-family:Georgia,serif;font-size:32px;color:#3b8bff;margin:0 0 10px;font-weight:700;letter-spacing:1px;">Certificate of Excellence</h2>
            <div style="width:100px;height:3px;background:linear-gradient(90deg,#3b8bff,#7b72f0);margin:0 auto;border-radius:2px;"></div>
          </div>
          
          <!-- Recipient Name -->
          <div style="background:linear-gradient(135deg,rgba(59,139,255,0.1),rgba(123,114,240,0.1));border:2px solid rgba(59,139,255,0.3);border-radius:15px;padding:25px 40px;margin:30px 0;display:inline-block;position:relative;">
            <div style="position:absolute;top:-10px;left:-10px;right:-10px;bottom:-10px;border:2px solid rgba(59,139,255,0.2);border-radius:15px;"></div>
            <h3 style="font-family:Georgia,serif;font-size:28px;color:#ffffff;margin:0;font-weight:700;position:relative;z-index:1;">' . htmlspecialchars($vname) . '</h3>
          </div>
          
          <!-- Achievement Text -->
          <p style="font-family:Georgia,serif;font-size:18px;color:#8898b4;line-height:1.8;margin:30px 0;max-width:600px;margin-left:auto;margin-right:auto;">
            Has demonstrated exceptional performance in the CyberShield Assessment, achieving the highest standards of cybersecurity excellence and compliance.
          </p>
          
          <!-- Achievement Details -->
          <div style="display:flex;justify-content:center;gap:30px;margin:40px 0;flex-wrap:wrap;">
            <div style="background:rgba(16,217,130,0.1);border:2px solid rgba(16,217,130,0.3);border-radius:15px;padding:20px 30px;min-width:150px;">
              <div style="font-size:14px;color:#8898b4;margin-bottom:5px;">SCORE</div>
              <div style="font-size:32px;font-weight:700;color:#10d982;">' . $score . '%</div>
            </div>
            <div style="background:rgba(59,139,255,0.1);border:2px solid rgba(59,139,255,0.3);border-radius:15px;padding:20px 30px;min-width:150px;">
              <div style="font-size:14px;color:#8898b4;margin-bottom:5px;">RANK</div>
              <div style="font-size:32px;font-weight:700;color:#3b8bff;">' . htmlspecialchars($rank) . '</div>
            </div>
            <div style="background:rgba(123,114,240,0.1);border:2px solid rgba(123,114,240,0.3);border-radius:15px;padding:20px 30px;min-width:150px;">
              <div style="font-size:14px;color:#8898b4;margin-bottom:5px;">DATE</div>
              <div style="font-size:20px;font-weight:700;color:#7b72f0;">' . htmlspecialchars($currentDate) . '</div>
            </div>
          </div>
          
          <!-- Certificate ID -->
          <div style="margin-top:40px;padding-top:30px;border-top:1px solid rgba(255,255,255,0.1);">
            <p style="font-family:monospace;font-size:14px;color:#5c6a84;margin:0;">
              Certificate ID: <span style="color:#3b8bff;font-weight:700;">' . htmlspecialchars($certId) . '</span> | 
              Issued: <span style="color:#7b72f0;font-weight:700;">' . htmlspecialchars($currentDate) . '</span>
            </p>
          </div>
        </div>
        
        <!-- Footer -->
        <div style="background:rgba(0,0,0,0.3);padding:30px;text-align:center;border-top:1px solid rgba(255,255,255,0.1);">
          <div style="display:flex;justify-content:center;align-items:center;gap:15px;margin-bottom:15px;">
            <div style="width:40px;height:40px;background:linear-gradient(135deg,#3b8bff,#7b72f0);border-radius:10px;display:flex;align-items:center;justify-content:center;">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
              </svg>
            </div>
            <div style="text-align:left;">
              <div style="font-weight:700;color:#ffffff;font-size:14px;">CyberShield Security</div>
              <div style="color:#8898b4;font-size:12px;">Philippine E-Commerce Security Platform</div>
            </div>
          </div>
          <p style="color:#5c6a84;font-size:12px;margin:0;">
            2025 CyberShield | NPC Compliant | RA 10173 Aligned
          </p>
        </div>
      </div>
    </div>';

  $mail->Body = $emailBody;

  // Send email
  if ($mail->send()) {
    // Log certificate to database
    try {
      $user_id = $data['user_id'] ?? null;
      $recipient_name = $vname;
      $cert_type_label = getTypeLabel($type);

      $stmt = $db->prepare("
            INSERT INTO sent_certificates 
            (user_id, cert_id, cert_type, recipient_email, recipient_name, score, rank, subject_line, personal_message, sent_at, status) 
            VALUES 
            (:user_id, :cert_id, :cert_type, :recipient_email, :recipient_name, :score, :rank, :subject_line, :personal_message, NOW(), 'sent')
        ");

      $stmt->bindParam(':user_id', $user_id);
      $stmt->bindParam(':cert_id', $certId);
      $stmt->bindParam(':cert_type', $cert_type_label);
      $stmt->bindParam(':recipient_email', $email);
      $stmt->bindParam(':recipient_name', $recipient_name);
      $stmt->bindParam(':score', $score);
      $stmt->bindParam(':rank', $rank);
      $stmt->bindParam(':subject_line', $subject);
      $stmt->bindParam(':personal_message', $message);

      $stmt->execute();

      error_log("[Certificate Send] Successfully logged to database: User ID $user_id, Email: $email");

    } catch (PDOException $e) {
      error_log("[Certificate Send] Database logging failed: " . $e->getMessage());
      // Continue even if logging fails - email was sent successfully
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => "Certificate sent successfully to $email"]);
  } else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to send email: ' . $mail->ErrorInfo]);
  }

} catch (Exception $e) {
  // Log detailed error for debugging
  error_log("SMTP Error: " . $e->getMessage());

  // Provide user-friendly error messages
  $errorMessage = $e->getMessage();
  if (strpos($errorMessage, 'SMTP Error: Could not authenticate') !== false) {
    $userMessage = "SMTP Authentication Failed. Please check your email credentials and enable 2FA with App Password.";
  } elseif (strpos($errorMessage, 'Connection refused') !== false) {
    $userMessage = "SMTP Connection Failed. Please check your SMTP server settings and port.";
  } elseif (strpos($errorMessage, 'timeout') !== false) {
    $userMessage = "Connection Timeout. Please check your internet connection and SMTP server.";
  } else {
    $userMessage = "Email sending failed: " . $errorMessage;
  }

  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'message' => $userMessage, 'debug' => $errorMessage]);
}

function generateCertificateHTML($vname, $score, $rank, $certId, $currentDate, $type)
{
  $typeInfo = getTypeInfo($type);

  return "
    <div style='text-align: center; padding: 30px; background: linear-gradient(135deg, #f0f8ff, #e6f2ff); border-radius: 10px;'>
      <div style='width: 80px; height: 80px; background: linear-gradient(135deg, #3B8BFF, #7B72F0); border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: bold;'>
        {$rank}
      </div>
      
      <h1 style='font-family: Georgia, serif; font-size: 28px; color: #3B8BFF; margin-bottom: 10px;'> {$typeInfo['title']} </h1>
      <h2 style='font-family: Georgia, serif; font-size: 16px; color: #666; margin-bottom: 20px;'> {$typeInfo['subtitle']} </h2>
      
      <div style='background: white; padding: 15px 30px; border: 2px solid #3B8BFF; border-radius: 8px; display: inline-block; margin: 20px 0;'>
        <h3 style='font-family: Georgia, serif; font-size: 20px; color: #333; margin: 0;'> {$vname} </h3>
      </div>
      
      <p style='font-family: Georgia, serif; font-size: 14px; color: #666; line-height: 1.8; max-width: 400px; margin: 20px auto;'> {$typeInfo['body']} </p>
      
      <div style='background: linear-gradient(135deg, rgba(59,139,255,0.1), rgba(123,114,240,0.1)); border: 2px solid rgba(59,139,255,0.3); border-radius: 20px; padding: 10px 20px; display: inline-block; margin: 20px 0; font-family: monospace; font-weight: bold;'>
        Score: {$score}% | Rank: {$rank}
      </div>
      
      <p style='font-size: 12px; color: #888; margin-top: 20px;'> ID: {$certId} | Date: {$currentDate} </p>
    </div>
  ";
}

function getTypeInfo($type)
{
  $types = [
    'compliance' => [
      'title' => 'Certificate of Compliance',
      'subtitle' => 'CyberShield Assessment Program',
      'body' => 'Has successfully completed the CyberShield Cybersecurity Assessment and demonstrated compliance with required security standards.'
    ],
    'assessment' => [
      'title' => 'Certificate of Achievement',
      'subtitle' => 'CyberShield Assessment Completion',
      'body' => 'Has successfully completed the comprehensive CyberShield Assessment demonstrating proficiency in essential security domains.'
    ],
    'excellence' => [
      'title' => 'Certificate of Excellence',
      'subtitle' => 'CyberShield Honor Program',
      'body' => 'Has demonstrated exceptional performance in the CyberShield Assessment achieving the highest standards of cybersecurity excellence.'
    ],
    'training' => [
      'title' => 'Certificate of Completion',
      'subtitle' => 'Security Awareness Training Program',
      'body' => 'Has successfully completed the CyberShield Security Awareness Training gaining essential knowledge in cybersecurity best practices.'
    ]
  ];

  return $types[$type] ?? $types['compliance'];
}

function getTypeLabel($type)
{
  $labels = [
    'compliance' => 'Cybersecurity Compliance Certificate',
    'assessment' => 'Assessment Completion Certificate',
    'excellence' => 'Certificate of Excellence',
    'training' => 'Security Awareness Training Certificate'
  ];

  return $labels[$type] ?? 'Certificate';
}
?>