<?php
// Audit logging helper functions
function createAuditLog($db, $user_id, $action_type, $action_description, $ip_address = null, $user_agent = null) {
    try {
        $query = "INSERT INTO audit_log (user_id, action_type, action_description, ip_address, user_agent) 
                  VALUES (:user_id, :action_type, :action_description, :ip_address, :user_agent)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':action_type', $action_type);
        $stmt->bindParam(':action_description', $action_description);
        $stmt->bindParam(':ip_address', $ip_address);
        $stmt->bindParam(':user_agent', $user_agent);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Audit log error: " . $e->getMessage());
        return false;
    }
}

function getClientIP() {
    // Check for shared internet/ISP IP
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    // Check for IP behind proxy
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    // Check for IP from real IP header
    elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    }
    // Default to REMOTE_ADDR
    else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
    
    // Clean up the IP (handle multiple IPs)
    $ip = explode(',', $ip);
    $ip = trim($ip[0]);
    
    // Validate IP
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
        return $ip;
    }
    
    // Handle localhost/development environments
    if (in_array($ip, ['127.0.0.1', '::1', 'localhost', '0.0.0.0'])) {
        // For development, return a more realistic demo IP
        return '192.168.1.100'; // Demo IP for development
    }
    
    return $ip;
}

function getClientUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}
?>
