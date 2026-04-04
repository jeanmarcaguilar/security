<?php
// Activity Logger Helper for CyberShield Admin Panel
// Include this file in admin pages to enable automatic activity logging

require_once 'config.php';
require_once 'audit_helper.php';

// Global activity logging function
function logActivity($action_type, $action_description = '') {
    if (!isset($_SESSION['user_id'])) {
        return false; // Don't log if no user session
    }
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "INSERT INTO audit_log (user_id, action_type, action_description, ip_address, user_agent) 
                  VALUES (:user_id, :action_type, :action_description, :ip_address, :user_agent)";
        
        $stmt = $db->prepare($query);
        
        $user_id = $_SESSION['user_id'];
        $ip_address = getClientIP();
        $user_agent = getClientUserAgent();
        
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':action_type', $action_type);
        $stmt->bindParam(':action_description', $action_description);
        $stmt->bindParam(':ip_address', $ip_address);
        $stmt->bindParam(':user_agent', $user_agent);
        
        return $stmt->execute();
    } catch(PDOException $exception) {
        error_log("Activity logging error: " . $exception->getMessage());
        return false;
    }
}

// Convenience functions for common actions
function logProfileUpdate($field = '') {
    $description = $field ? "Profile updated: $field" : "Profile updated";
    return logActivity('profile_update', $description);
}

    function logAssessmentComplete($score = '', $vendor = '') {
    $description = "Assessment completed";
    if ($score) $description .= " with score: $score";
    if ($vendor) $description .= " for vendor: $vendor";
    return logActivity('assessment_complete', $description);
}

function logDataClear($type = '') {
    $description = $type ? "Data cleared: $type" : "Data cleared";
    return logActivity('data_clear', $description);
}

function logExport($format = '', $records = 0) {
    $description = "Data exported";
    if ($format) $description .= " as $format";
    if ($records > 0) $description .= " ($records records)";
    return logActivity('export', $description);
}

function logUserAction($action, $details = '') {
    $description = $details ? "$action: $details" : $action;
    return logActivity('other', $description);
}

// Function to log page views (optional)
function logPageView($page_name = '') {
    $description = $page_name ? "Viewed $page_name page" : "Page viewed";
    return logActivity('other', $description);
}

// Function to get recent activities for display
function getRecentActivities($limit = 50, $filter = '', $offset = 0) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT al.*, u.username, u.full_name 
                  FROM audit_log al 
                  LEFT JOIN users u ON al.user_id = u.id";
        
        if ($filter) {
            $query .= " WHERE al.action_type = :filter";
        }
        
        $query .= " ORDER BY al.created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $db->prepare($query);
        
        if ($filter) {
            $stmt->bindParam(':filter', $filter);
        }
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $exception) {
        error_log("Get activities error: " . $exception->getMessage());
        return [];
    }
}

// Function to get total count of activities for pagination
function getActivitiesCount($filter = '') {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT COUNT(*) as total FROM audit_log al";
        
        if ($filter) {
            $query .= " WHERE al.action_type = :filter";
        }
        
        $stmt = $db->prepare($query);
        
        if ($filter) {
            $stmt->bindParam(':filter', $filter);
        }
        
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['total'];
    } catch(PDOException $exception) {
        error_log("Get activities count error: " . $exception->getMessage());
        return 0;
    }
}

// Function to get activity statistics
function getActivityStats($days = 30) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT 
                    action_type, 
                    COUNT(*) as count,
                    DATE(created_at) as date
                  FROM audit_log 
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                  GROUP BY action_type, DATE(created_at)
                  ORDER BY date DESC";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $exception) {
        error_log("Get activity stats error: " . $exception->getMessage());
        return [];
    }
}
?>
