<?php
session_start();
require_once '../includes/config.php';

// Load PHPMailer with correct paths
require_once '../includes/PHPMailer-6.9.1/src/PHPMailer.php';
require_once '../includes/PHPMailer-6.9.1/src/SMTP.php';
require_once '../includes/PHPMailer-6.9.1/src/Exception.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// TEMPORARILY DISABLE AUTHENTICATION FOR TESTING
// if (!isset($_SESSION['user_id'])) {
//     error_log("Email send failed: Not authenticated");
//     echo json_encode(['success' => false, 'error' => 'Not authenticated']);
//     exit();
// }

// Get JSON input
$json = file_get_contents('php://input');
error_log("Raw JSON input: " . $json);

$data = json_decode($json, true);

if (!$data) {
    error_log("Email send failed: Invalid JSON data - " . json_last_error_msg());
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit();
}

$recipientEmail = $data['to'] ?? '';
$userId = $data['userId'] ?? '';
$subject = $data['subject'] ?? 'CyberShield Risk Assessment Report';
$note = $data['note'] ?? '';

error_log("Email data: to=$recipientEmail, userId=$userId, subject=$subject");

// Validate email
if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
    error_log("Email send failed: Invalid recipient email - $recipientEmail");
    echo json_encode(['success' => false, 'error' => 'Invalid recipient email']);
    exit();
}

if (empty($userId)) {
    echo json_encode(['success' => false, 'error' => 'User ID is required']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    error_log("Database connection established");
    
    // Get user data
    $user_query = "SELECT id, full_name, email, store_name, last_assessment_score, last_assessment_date FROM users WHERE id = :user_id";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->bindParam(':user_id', $userId);
    $user_stmt->execute();
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("User query executed, found user: " . ($user ? 'yes' : 'no'));
    
    if (!$user) {
        error_log("Email send failed: User not found - ID: $userId");
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }
    
    // Send assessment report email
    error_log("Attempting to send email to $recipientEmail");
    $emailSent = sendAssessmentReportEmail($recipientEmail, $user, $subject, $note);
    
    error_log("Email sending result: " . ($emailSent ? 'success' : 'failed'));
    
    if ($emailSent) {
        echo json_encode([
            'success' => true, 
            'message' => 'Assessment report sent successfully',
            'recipient' => $recipientEmail,
            'userName' => $user['full_name'] ?: $user['store_name']
        ]);
    } else {
        error_log("Email send failed: Email function returned false");
        echo json_encode(['success' => false, 'error' => 'Failed to send assessment report email']);
    }
    
} catch (PDOException $e) {
    error_log("Assessment report email sending error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in email sending: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

function sendAssessmentReportEmail($recipientEmail, $user, $subject, $note) {
    $mail = new PHPMailer(true);
    
    try {
        error_log("Starting email sending process to $recipientEmail");
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jeanmarcaguilar829@gmail.com';
        $mail->Password   = 'ryrizfyhokwsbcfz';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        
        error_log("SMTP settings configured");
        
        // Recipients
        $mail->setFrom('jeanmarcaguilar829@gmail.com', 'CyberShield Security');
        $mail->addAddress($recipientEmail);
        
        error_log("Recipients set");
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        
        error_log("Email subject set: $subject");
        
        $userName = $user['full_name'] ?: $user['store_name'];
        $score = $user['last_assessment_score'];
        $date = $user['last_assessment_date'] ? date('F j, Y', strtotime($user['last_assessment_date'])) : 'N/A';
        
        error_log("User data: name=$userName, score=$score, date=$date");
        
        // Determine rank and color
        if ($score >= 80) {
            $rank = 'A';
            $riskLevel = 'Low Risk';
            $rankColor = '#10D982';
        } elseif ($score >= 60) {
            $rank = 'B';
            $riskLevel = 'Moderate';
            $rankColor = '#F5B731';
        } elseif ($score >= 40) {
            $rank = 'C';
            $riskLevel = 'High Risk';
            $rankColor = '#FF7A45';
        } else {
            $rank = 'D';
            $riskLevel = 'Critical';
            $rankColor = '#FF4D6A';
        }
        
        error_log("Rank calculated: $rank - $riskLevel");
        
        $mail->Body = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #030508; color: #dde4f0; padding: 40px; border-radius: 12px;">
            <div style="text-align: center; margin-bottom: 30px;">
                <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #3b8bff, #b061ff); border-radius: 15px; display: inline-flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 20px;">
                    🛡️
                </div>
                <h1 style="color: #fff; font-size: 28px; margin: 0; font-weight: 700;">CyberShield</h1>
                <p style="color: #5c6a84; margin: 5px 0 0; font-size: 14px;">Risk Assessment Report</p>
            </div>
            
            <div style="background: rgba(59, 139, 255, 0.1); border: 1px solid rgba(59, 139, 255, 0.3); border-radius: 12px; padding: 30px; margin-bottom: 30px;">
                <h2 style="color: #3b8bff; font-size: 18px; margin: 0 0 15px;">Dear ' . htmlspecialchars($userName) . ',</h2>
                <p style="color: #8898b4; margin: 0 0 20px; font-size: 14px; line-height: 1.6;">Your latest CyberShield risk assessment results are now available:</p>
                
                <div style="background: #0a0e17; border: 2px solid ' . $rankColor . '; border-radius: 12px; padding: 25px; margin: 20px 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <span style="color: #8898b4; font-size: 14px;">Overall Score</span>
                        <span style="font-size: 32px; font-weight: 700; color: ' . $rankColor . ';">' . $score . '%</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: #8898b4; font-size: 14px;">Risk Rank</span>
                        <div style="text-align: right;">
                            <div style="font-size: 24px; font-weight: 700; color: ' . $rankColor . ';">' . $rank . '</div>
                            <div style="font-size: 12px; color: #8898b4;">' . $riskLevel . '</div>
                        </div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px;">
                    <div>
                        <span style="color: #8898b4; font-size: 12px;">Category</span>
                        <div style="color: #fff; font-size: 14px; font-weight: 600;">Overall Assessment</div>
                    </div>
                    <div>
                        <span style="color: #8898b4; font-size: 12px;">Assessment Date</span>
                        <div style="color: #fff; font-size: 14px; font-weight: 600;">' . $date . '</div>
                    </div>
                </div>
            </div>';
        
        if (!empty($note)) {
            $mail->Body .= '
            <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; padding: 20px; margin-bottom: 25px;">
                <h3 style="color: #3b8bff; font-size: 16px; margin: 0 0 10px;">📝 Additional Note</h3>
                <p style="color: #8898b4; margin: 0; font-size: 14px; line-height: 1.6; font-style: italic;">' . htmlspecialchars($note) . '</p>
            </div>';
        }
        
        $mail->Body .= '
            <div style="background: rgba(0, 255, 148, 0.05); border: 1px solid rgba(0, 255, 148, 0.2); border-radius: 8px; padding: 20px; margin-bottom: 25px;">
                <h3 style="color: #00ff94; font-size: 16px; margin: 0 0 10px;">🔍 What This Means</h3>
                <ul style="color: #8898b4; margin: 0; padding-left: 20px; font-size: 14px; line-height: 1.6;">
                    <li>Your score reflects your current security posture</li>
                    <li>Regular assessments help maintain compliance</li>
                    <li>Review detailed recommendations in your dashboard</li>
                </ul>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <p style="color: #5c6a84; font-size: 12px; margin: 0;">
                    Best regards,<br>
                    <strong>CyberShield Admin</strong><br><br>
                    &copy; 2025 CyberShield · Philippine E-Commerce Security Platform<br>
                    NPC Compliant · RA 10173 Aligned
                </p>
            </div>
        </div>';
        
        error_log("Email body created");
        
        $mail->AltBody = "CyberShield Risk Assessment Report\n\n" .
                        "Dear " . $userName . ",\n\n" .
                        "Your latest CyberShield risk assessment results:\n\n" .
                        "Score: " . $score . "%\n" .
                        "Rank: " . $rank . " - " . $riskLevel . "\n" .
                        "Category: Overall Assessment\n" .
                        "Date: " . $date . "\n\n" .
                        (!empty($note) ? "Additional Note:\n" . $note . "\n\n" : "") .
                        "What This Means:\n" .
                        "- Your score reflects your current security posture\n" .
                        "- Regular assessments help maintain compliance\n" .
                        "- Review detailed recommendations in your dashboard\n\n" .
                        "Best regards,\n" .
                        "CyberShield Admin\n\n" .
                        "&copy; 2025 CyberShield - Philippine E-Commerce Security Platform";
        
        error_log("Attempting to send email via SMTP");
        $mail->send();
        error_log("Email sent successfully");
        return true;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}
?>
