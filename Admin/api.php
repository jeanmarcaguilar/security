<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_vendors':
        getVendors($db);
        break;
    case 'get_assessments':
        getAssessments($db);
        break;
    case 'get_stats':
        getStats($db);
        break;
    case 'get_activity_log':
        getActivityLog($db);
        break;
    case 'get_risk_distribution':
        getRiskDistribution($db);
        break;
    case 'get_trend_data':
        getTrendData($db);
        break;
    case 'flag_vendor':
        flagVendor($db);
        break;
    case 'send_email':
        sendEmail($db);
        break;
    case 'log_activity':
        logActivity($db);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function getVendors($db)
{
    $query = "SELECT v.*, 
              (SELECT score FROM vendor_assessments WHERE vendor_id = v.id ORDER BY created_at DESC LIMIT 1) as latest_score,
              (SELECT rank FROM vendor_assessments WHERE vendor_id = v.id ORDER BY created_at DESC LIMIT 1) as latest_rank,
              (SELECT COUNT(*) FROM vendor_assessments WHERE vendor_id = v.id) as assessment_count
              FROM vendors v 
              WHERE v.is_active = 1 
              ORDER BY v.name";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($vendors);
}

function getAssessments($db)
{
    $vendor_id = $_GET['vendor_id'] ?? null;
    $rank_filter = $_GET['rank'] ?? '';

    $query = "SELECT va.*, v.name as vendor_name 
              FROM vendor_assessments va 
              JOIN vendors v ON va.vendor_id = v.id 
              WHERE 1=1";

    $params = [];

    if ($vendor_id) {
        $query .= " AND va.vendor_id = :vendor_id";
        $params[':vendor_id'] = $vendor_id;
    }

    if ($rank_filter) {
        if ($rank_filter === 'CD') {
            $query .= " AND va.rank IN ('C', 'D')";
        } else {
            $query .= " AND va.rank = :rank";
            $params[':rank'] = $rank_filter;
        }
    }

    $query .= " ORDER BY va.created_at DESC";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($assessments);
}

function getStats($db)
{
    $query = "SELECT 
                COUNT(DISTINCT v.id) as total_vendors,
                AVG(va.score) as avg_score,
                COUNT(CASE WHEN va.rank IN ('C', 'D') THEN 1 END) as high_risk_count,
                COUNT(CASE WHEN va.rank = 'A' THEN 1 END) as low_risk_count,
                COUNT(CASE WHEN va.rank = 'B' THEN 1 END) as moderate_risk_count,
                COUNT(CASE WHEN va.rank = 'C' THEN 1 END) as high_risk_count_only,
                COUNT(CASE WHEN va.rank = 'D' THEN 1 END) as critical_risk_count
              FROM vendors v
              LEFT JOIN vendor_assessments va ON v.id = va.vendor_id
              WHERE v.is_active = 1";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode($stats);
}

function getActivityLog($db)
{
    $filter = $_GET['filter'] ?? '';
    $limit = $_GET['limit'] ?? 50;

    $query = "SELECT al.*, u.full_name 
              FROM activity_log al 
              LEFT JOIN users u ON al.user_id = u.id 
              WHERE 1=1";

    $params = [];

    if ($filter) {
        $query .= " AND al.action_type = :action_type";
        $params[':action_type'] = $filter;
    }

    $query .= " ORDER BY al.created_at DESC LIMIT :limit";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($activities);
}

function getRiskDistribution($db)
{
    $query = "SELECT rank, COUNT(*) as count, AVG(score) as avg_score 
              FROM vendor_assessments 
              GROUP BY rank 
              ORDER BY rank";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($distribution);
}

function getTrendData($db)
{
    $days = $_GET['days'] ?? 30;

    $query = "SELECT 
                DATE(created_at) as date,
                AVG(score) as avg_score,
                COUNT(*) as assessment_count
              FROM vendor_assessments 
              WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL :days DAY)
              GROUP BY DATE(created_at)
              ORDER BY date";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':days', $days, PDO::PARAM_INT);
    $stmt->execute();
    $trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($trend);
}

function flagVendor($db)
{
    $vendor_id = $_POST['vendor_id'] ?? null;
    $flagged = $_POST['flagged'] ?? false;

    if (!$vendor_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Vendor ID required']);
        return;
    }

    $query = "UPDATE vendors SET flagged = :flagged WHERE id = :vendor_id";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':flagged', $flagged, PDO::PARAM_BOOL);
    $stmt->bindValue(':vendor_id', $vendor_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        logActivity($db, 'flag', "Vendor " . ($flagged ? 'flagged' : 'unflagged') . " for review");
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update vendor']);
    }
}

function sendEmail($db)
{
    $vendor_id = $_POST['vendor_id'] ?? null;
    $recipient_email = $_POST['recipient_email'] ?? null;
    $subject = $_POST['subject'] ?? null;
    $message = $_POST['message'] ?? null;
    $additional_notes = $_POST['additional_notes'] ?? '';

    if (!$vendor_id || !$recipient_email || !$subject || !$message) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }

    $query = "INSERT INTO email_reports (vendor_id, recipient_email, subject, message, additional_notes, sent_by, status) 
              VALUES (:vendor_id, :recipient_email, :subject, :message, :additional_notes, :sent_by, 'sent')";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':vendor_id', $vendor_id, PDO::PARAM_INT);
    $stmt->bindValue(':recipient_email', $recipient_email);
    $stmt->bindValue(':subject', $subject);
    $stmt->bindValue(':message', $message);
    $stmt->bindValue(':additional_notes', $additional_notes);
    $stmt->bindValue(':sent_by', $_SESSION['user_id'], PDO::PARAM_INT);

    if ($stmt->execute()) {
        logActivity($db, 'email', "Email report sent to vendor");
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to send email']);
    }
}

function logActivity($db, $action_type = null, $action_description = null)
{
    if ($action_type && $action_description) {
        $query = "INSERT INTO activity_log (user_id, action_type, action_description, ip_address) 
                  VALUES (:user_id, :action_type, :action_description, :ip_address)";

        $stmt = $db->prepare($query);
        $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':action_type', $action_type);
        $stmt->bindValue(':action_description', $action_description);
        $stmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? '');
        $stmt->execute();
    }
}
?>