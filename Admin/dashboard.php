<?php
require_once '../includes/config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header('Location: ../index.html');
  exit();
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get current user data - prioritize session variables set by profile update
if (isset($_SESSION['user_full_name'])) {
    // Use session data if available (set by profile update)
    $user = [
        'id' => $_SESSION['user_id'],
        'full_name' => $_SESSION['user_full_name'],
        'email' => $_SESSION['user_email'],
        'store_name' => $_SESSION['user_store_name'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'Admin'
    ];
} else {
    // Fallback to database query and initialize session variables
    $user_query = "SELECT * FROM users WHERE id = :user_id";
    $stmt = $db->prepare($user_query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Initialize session variables for consistency
    if ($user) {
        $_SESSION['user_full_name'] = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_store_name'] = $user['store_name'];
        $_SESSION['user_role'] = $user['role'];
    }
}

if (!$user) {
  header('Location: ../index.html');
  exit();
}

// Fetch users and assessment data from database
$users = [];
$assessments = [];
try {
    // Get all users
    $stmt = $db->prepare("SELECT id, username, email, full_name, store_name, role, is_active, last_assessment_score, last_assessment_date, total_assessments, created_at FROM users ORDER BY created_at DESC");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get real assessment data with overall scores
    $stmt = $db->prepare("
        SELECT a.*, u.full_name, u.store_name 
        FROM assessments a 
        JOIN users u ON a.vendor_id = u.id 
        ORDER BY a.assessment_date DESC
    ");
    $stmt->execute();
    $allAssessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get category scores for each assessment
    $assessmentCategories = [];
    foreach ($allAssessments as $assessment) {
        $stmt = $db->prepare("
            SELECT 
                category,
                COUNT(*) as total_questions,
                SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_answers,
                ROUND((SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 2) as category_score
            FROM assessment_answers 
            WHERE assessment_id = :assessment_id 
            GROUP BY category 
            ORDER BY category
        ");
        $stmt->bindParam(':assessment_id', $assessment['id']);
        $stmt->execute();
        $categoryScores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $assessmentCategories[$assessment['id']] = $categoryScores;
    }
    
    // Transform assessment data to match expected format - use overall scores
    foreach ($allAssessments as $assessment) {
        $assessments[] = [
            'id' => $assessment['id'],
            'vid' => $assessment['vendor_id'],
            'vname' => $assessment['full_name'] ?: $assessment['store_name'],
            'score' => $assessment['score'],
            'rank' => ($assessment['score'] >= 80) ? 'A' : (($assessment['score'] >= 60) ? 'B' : (($assessment['score'] >= 40) ? 'C' : 'D')),
            'cat' => 'Overall Assessment',
            'date' => date('Y-m-d', strtotime($assessment['assessment_date'])),
            'categories' => $assessmentCategories[$assessment['id']] ?? []
        ];
    }
    
    // If no real assessment data exists, create sample data for demonstration
    if (empty($assessments) && !empty($users)) {
        $categories = ['Access Control', 'Network Security', 'Data Encryption', 'Compliance', 'Incident Response', 'Physical Security'];
        
        foreach ($users as $u) {
            if ($u['last_assessment_score'] !== null) {
                $baseScore = $u['last_assessment_score'];
                
                foreach ($categories as $index => $category) {
                    $categoryVariation = (($u['id'] + $index) % 31) - 15;
                    $categoryScore = max(20, min(100, $baseScore + $categoryVariation));
                    $rank = ($categoryScore >= 80) ? 'A' : (($categoryScore >= 60) ? 'B' : (($categoryScore >= 40) ? 'C' : 'D'));
                    
                    $historicalDate = new DateTime($u['last_assessment_date'] ?: date('Y-m-d'));
                    for ($i = 5; $i >= 0; $i--) {
                        $date = clone $historicalDate;
                        $date->modify("-$i months");
                        $historicalVariation = ((($u['id'] + $index + $i) % 21) - 10);
                        $historicalScore = max(20, min(100, $categoryScore + $historicalVariation));
                        $historicalRank = ($historicalScore >= 80) ? 'A' : (($historicalScore >= 60) ? 'B' : (($historicalScore >= 40) ? 'C' : 'D'));
                        
                        $assessments[] = [
                            'id' => count($assessments) + 1,
                            'vid' => $u['id'],
                            'vname' => $u['full_name'] ?: $u['store_name'],
                            'score' => $historicalScore,
                            'rank' => $historicalRank,
                            'cat' => $category,
                            'date' => $date->format('Y-m-d')
                        ];
                    }
                }
            }
        }
    }
    
} catch(PDOException $exception) {
    error_log("Error fetching dashboard data: " . $exception->getMessage());
    $users = [];
    $assessments = [];
}

// Fetch audit log / daily login data
$auditLogs = [];
$dailyLogins = [];
try {
    // Try to fetch from audit_logs table if it exists
    $stmt = $db->prepare("SHOW TABLES LIKE 'audit_logs'");
    $stmt->execute();
    $auditTableExists = $stmt->rowCount() > 0;

    if ($auditTableExists) {
        $stmt = $db->prepare("
            SELECT al.*, u.full_name, u.username, u.store_name
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT 50
        ");
        $stmt->execute();
        $auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // FIXED: Daily logins for last 7 days - NO DUPLICATE DATES
        // Generate proper date range for last 7 days
        $dailyLogins = [];
        $endDate = new DateTime();
        $startDate = clone $endDate;
        $startDate->modify('-6 days'); // Last 7 days including today
        $endDate->modify('+1 day');
        $interval = new DateInterval('P1D');
        $dateRange = new DatePeriod($startDate, $interval, $endDate);
        
        // Get real login counts from database
        $realLogins = [];
        $stmt = $db->prepare("
            SELECT DATE(created_at) as login_date, COUNT(*) as login_count
            FROM audit_logs
            WHERE action = 'login' 
              AND created_at >= :start_date 
              AND created_at <= :end_date
            GROUP BY DATE(created_at)
        ");
        $stmt->bindParam(':start_date', $startDate->format('Y-m-d'));
        $stmt->bindParam(':end_date', $endDate->format('Y-m-d 23:59:59'));
        $stmt->execute();
        $realLoginsResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($realLoginsResult as $row) {
            $realLogins[$row['login_date']] = (int)$row['login_count'];
        }
        
        // Build the 7-day list with proper counts
        foreach ($dateRange as $date) {
            $dateStr = $date->format('Y-m-d');
            $count = isset($realLogins[$dateStr]) ? $realLogins[$dateStr] : 0;
            $dailyLogins[] = [
                'login_date' => $dateStr,
                'login_count' => (string)$count
            ];
        }
        
    } else {
        // If audit_logs table doesn't exist, generate demo data for last 7 days
        $dailyLogins = [];
        $endDate = new DateTime();
        $startDate = clone $endDate;
        $startDate->modify('-6 days'); // Last 7 days including today
        $endDate->modify('+1 day');
        $interval = new DateInterval('P1D');
        $dateRange = new DatePeriod($startDate, $interval, $endDate);
        
        $userCount = count($users);
        foreach ($dateRange as $date) {
            $dateStr = $date->format('Y-m-d');
            // Demo login counts - deterministic based on date
            $dateHash = abs(crc32($dateStr) % max(1, $userCount + 5));
            $loginCount = $dateHash % 12;
            $dailyLogins[] = [
                'login_date' => $dateStr,
                'login_count' => (string)$loginCount
            ];
        }
        
        // Generate consistent audit logs
        $actions = ['login', 'login', 'login', 'logout', 'view_report', 'update_profile', 'take_assessment', 'login', 'view_dashboard', 'login'];
        $actionLabels = [
            'login' => 'User Login',
            'logout' => 'User Logout',
            'view_report' => 'Viewed Report',
            'update_profile' => 'Updated Profile',
            'take_assessment' => 'Completed Assessment',
            'view_dashboard' => 'Viewed Dashboard'
        ];
        $now = new DateTime();
        $auditLogs = [];
        foreach ($users as $i => $u) {
            for ($j = 0; $j < 3; $j++) {
                $hoursAgo = ($i * 3 + $j * 7 + ($u['id'] % 12));
                $ts = clone $now;
                $ts->modify("-{$hoursAgo} hours");
                $action = $actions[($i + $j + $u['id']) % count($actions)];
                $auditLogs[] = [
                    'id' => count($auditLogs) + 1,
                    'user_id' => $u['id'],
                    'username' => $u['username'],
                    'full_name' => $u['full_name'] ?: $u['store_name'],
                    'action' => $action,
                    'action_label' => $actionLabels[$action] ?? ucfirst(str_replace('_', ' ', $action)),
                    'ip_address' => '192.168.' . ((($u['id'] * 7) % 10) + 1) . '.' . (($u['id'] * 13) % 254 + 1),
                    'created_at' => $ts->format('Y-m-d H:i:s')
                ];
            }
        }
        usort($auditLogs, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        $auditLogs = array_slice($auditLogs, 0, 50);
    }
} catch(PDOException $e) {
    error_log("Audit log error: " . $e->getMessage());
    $auditLogs = [];
    $dailyLogins = [];
}

$auditLogsJson = json_encode($auditLogs);
$dailyLoginsJson = json_encode($dailyLogins);

// Convert to JSON for JavaScript
$usersJson = json_encode($users);
$assessmentsJson = json_encode($assessments);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>Dashboard — CyberShield</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Syne:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap');

    :root {
      --font: 'Inter', sans-serif;
      --display: 'Syne', sans-serif;
      --mono: 'JetBrains Mono', monospace;
      --blue: #3B8BFF;
      --purple: #7B72F0;
      --teal: #00D4AA;
      --green: #10D982;
      --yellow: #F5B731;
      --orange: #FF8C42;
      --red: #FF3B5C;
      --t: .18s ease
    }

    [data-theme=dark] {
      --bg: #030508;
      --bg2: #080d16;
      --bg3: #0d1421;
      --border: rgba(59, 139, 255, .08);
      --border2: rgba(255, 255, 255, .07);
      --text: #dde4f0;
      --muted: #4a6080;
      --muted2: #8898b4;
      --card-bg: #0a1020;
      --shadow: 0 4px 24px rgba(0, 0, 0, .5)
    }

    [data-theme=light] {
      --bg: #f0f4f8;
      --bg2: #e8eef5;
      --bg3: #fff;
      --border: rgba(59, 139, 255, .12);
      --border2: rgba(0, 0, 0, .1);
      --text: #0f172a;
      --muted: #94a3b8;
      --muted2: #475569;
      --card-bg: #fff;
      --shadow: 0 4px 24px rgba(0, 0, 0, .1)
    }

    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0
    }

    html,
    body {
      height: 100%;
      overflow: hidden
    }

    body {
      font-family: var(--font);
      background: var(--bg);
      color: var(--text);
      transition: background .18s, color .18s
    }

    .bg-grid {
      position: fixed;
      inset: 0;
      pointer-events: none;
      z-index: 0;
      background-image: linear-gradient(rgba(59, 139, 255, .025) 1px, transparent 1px), linear-gradient(90deg, rgba(59, 139, 255, .025) 1px, transparent 1px);
      background-size: 40px 40px
    }

    #app {
      display: flex;
      height: 100vh;
      position: relative;
      z-index: 1
    }

    #sidebar {
      width: 228px;
      min-width: 228px;
      background: var(--bg2);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      transition: width .18s, min-width .18s;
      overflow: hidden;
      z-index: 10;
      flex-shrink: 0
    }

    #sidebar.collapsed {
      width: 58px;
      min-width: 58px
    }

    .sb-brand {
      display: flex;
      align-items: center;
      gap: .75rem;
      padding: 1rem .9rem .9rem;
      border-bottom: 1px solid var(--border);
      flex-shrink: 0
    }

    .shield {
      width: 34px;
      height: 34px;
      background: linear-gradient(135deg, var(--blue), var(--purple));
      border-radius: 9px;
      display: grid;
      place-items: center;
      flex-shrink: 0;
      box-shadow: 0 0 16px rgba(59, 139, 255, .3)
    }

    .sb-brand-text {
      flex: 1;
      overflow: hidden;
      white-space: nowrap
    }

    .sb-brand-text h2 {
      font-family: var(--display);
      font-size: .95rem;
      font-weight: 700;
      letter-spacing: 1px
    }

    .sb-brand-text .badge {
      font-family: var(--mono);
      font-size: .55rem;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      background: rgba(255, 59, 92, .12);
      color: var(--red);
      border: 1px solid rgba(255, 59, 92, .2);
      border-radius: 4px;
      padding: .08rem .38rem;
      display: inline-block;
      margin-top: .1rem
    }

    .sb-toggle {
      width: 22px;
      height: 22px;
      background: none;
      border: 1px solid var(--border2);
      border-radius: 5px;
      cursor: pointer;
      color: var(--muted2);
      display: grid;
      place-items: center;
      flex-shrink: 0;
      transition: var(--t)
    }

    .sb-toggle:hover {
      border-color: var(--blue);
      color: var(--text)
    }

    #sidebar.collapsed .sb-toggle svg {
      transform: rotate(180deg)
    }

    .sb-section {
      flex: 1;
      overflow-y: auto;
      overflow-x: hidden;
      padding: .65rem 0
    }

    .sb-section::-webkit-scrollbar {
      width: 3px
    }

    .sb-section::-webkit-scrollbar-thumb {
      background: var(--border2);
      border-radius: 2px
    }

    .sb-label {
      font-family: var(--mono);
      font-size: .55rem;
      letter-spacing: 2px;
      text-transform: uppercase;
      color: var(--muted);
      padding: .5rem .9rem .25rem;
      white-space: nowrap;
      overflow: hidden
    }

    #sidebar.collapsed .sb-label {
      opacity: 0
    }

    .sb-divider {
      height: 1px;
      background: var(--border);
      margin: .5rem .9rem
    }

    .sb-item {
      display: flex;
      align-items: center;
      gap: .65rem;
      padding: .52rem .9rem;
      cursor: pointer;
      color: var(--muted2);
      font-size: .82rem;
      font-weight: 500;
      text-decoration: none;
      transition: var(--t);
      white-space: nowrap;
      overflow: hidden;
      position: relative
    }

    .sb-item:hover {
      background: rgba(59, 139, 255, .07);
      color: var(--text)
    }

    .sb-item.active {
      background: rgba(59, 139, 255, .1);
      color: var(--blue)
    }

    .sb-item.active::before {
      content: '';
      position: absolute;
      left: 0;
      top: 20%;
      bottom: 20%;
      width: 3px;
      background: var(--blue);
      border-radius: 0 3px 3px 0
    }

    .sb-icon {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 18px;
      flex-shrink: 0
    }

    .sb-text {
      overflow: hidden
    }

    #sidebar.collapsed .sb-text {
      display: none
    }

    .sb-footer {
      border-top: 1px solid var(--border);
      padding: .75rem .9rem;
      flex-shrink: 0
    }

    .sb-user {
      display: flex;
      align-items: center;
      gap: .65rem;
      overflow: hidden
    }

    .sb-avatar {
      width: 30px;
      height: 30px;
      border-radius: 8px;
      background: linear-gradient(135deg, var(--red), var(--orange));
      color: #fff;
      display: grid;
      place-items: center;
      font-size: .75rem;
      font-weight: 700;
      flex-shrink: 0;
      font-family: var(--display)
    }

    .sb-user-info {
      overflow: hidden;
      white-space: nowrap
    }

    .sb-user-info p {
      font-size: .82rem;
      font-weight: 600
    }

    .sb-user-info span {
      font-size: .68rem;
      color: var(--muted2)
    }

    #sidebar.collapsed .sb-user-info {
      display: none
    }

    .btn-sb-logout {
      display: flex;
      align-items: center;
      gap: .35rem;
      margin-top: .65rem;
      width: 100%;
      background: rgba(255, 59, 92, .08);
      border: 1px solid rgba(255, 59, 92, .18);
      color: var(--red);
      font-family: var(--font);
      font-size: .75rem;
      font-weight: 600;
      border-radius: 7px;
      padding: .42rem .8rem;
      cursor: pointer;
      transition: var(--t)
    }

    .btn-sb-logout:hover {
      background: rgba(255, 59, 92, .15)
    }

    #sidebar.collapsed .btn-sb-logout span {
      display: none
    }

    #main {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      min-width: 0
    }

    .topbar {
      height: 54px;
      min-height: 54px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 1.5rem;
      background: var(--bg2);
      border-bottom: 1px solid var(--border);
      gap: 1rem;
      flex-shrink: 0
    }

    .tb-bc {
      display: flex;
      align-items: center;
      gap: .4rem
    }

    .tb-app {
      font-family: var(--mono);
      font-size: .68rem;
      color: var(--muted);
      letter-spacing: .5px
    }

    .tb-title {
      font-family: var(--display);
      font-size: 1.05rem;
      letter-spacing: 1px
    }

    .tb-sub {
      font-family: var(--mono);
      font-size: .63rem;
      letter-spacing: .5px;
      color: var(--muted);
      margin-top: 1px
    }

    .tb-right {
      display: flex;
      align-items: center;
      gap: .55rem
    }

    .tb-search-wrap {
      position: relative
    }

    .tb-search-icon {
      position: absolute;
      left: .65rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--muted2);
      pointer-events: none
    }

    .tb-search {
      background: rgba(255, 255, 255, .04);
      border: 1px solid var(--border2);
      border-radius: 8px;
      padding: .38rem .8rem .38rem 2rem;
      font-family: var(--font);
      font-size: .78rem;
      color: var(--text);
      outline: none;
      width: 200px;
      transition: var(--t)
    }

    .tb-search:focus {
      border-color: rgba(59, 139, 255, .4)
    }

    .tb-search::placeholder {
      color: var(--muted)
    }

    .tb-date {
      font-family: var(--mono);
      font-size: .65rem;
      color: var(--muted2);
      white-space: nowrap
    }

    .tb-divider {
      width: 1px;
      height: 20px;
      background: var(--border2);
      margin: 0 .2rem
    }

    .tb-icon-btn {
      width: 32px;
      height: 32px;
      border-radius: 7px;
      border: 1px solid var(--border2);
      background: rgba(255, 255, 255, .04);
      cursor: pointer;
      display: grid;
      place-items: center;
      color: var(--muted2);
      transition: var(--t);
      flex-shrink: 0
    }

    .tb-icon-btn:hover {
      border-color: var(--blue);
      color: var(--text)
    }

    .tb-admin {
      display: flex;
      align-items: center;
      gap: .55rem;
      background: rgba(255, 255, 255, .04);
      border: 1px solid var(--border2);
      border-radius: 9px;
      padding: .28rem .65rem .28rem .28rem;
      cursor: pointer;
      transition: var(--t)
    }

    .tb-admin:hover {
      border-color: rgba(255, 59, 92, .28);
      background: rgba(255, 59, 92, .06)
    }

    .tb-admin-av {
      width: 28px;
      height: 28px;
      border-radius: 7px;
      background: linear-gradient(135deg, var(--red), var(--orange));
      color: #fff;
      display: grid;
      place-items: center;
      font-size: .7rem;
      font-weight: 700;
      flex-shrink: 0;
      font-family: var(--display)
    }

    .tb-admin-info {
      display: flex;
      flex-direction: column
    }

    .tb-admin-name {
      font-size: .78rem;
      font-weight: 600;
      line-height: 1.2
    }

    .tb-admin-role {
      font-size: .6rem;
      color: var(--red);
      letter-spacing: .5px;
      font-family: var(--mono)
    }

    .notif-wrap {
      position: relative
    }

    .notif-dot {
      position: absolute;
      top: 5px;
      right: 5px;
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--red);
      border: 1.5px solid var(--bg2)
    }

    .np {
      position: absolute;
      right: 0;
      top: calc(100% + 8px);
      width: 280px;
      background: var(--bg3);
      border: 1px solid var(--border2);
      border-radius: 10px;
      box-shadow: var(--shadow);
      z-index: 100
    }

    .np.hidden {
      display: none
    }

    .np-hdr {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: .75rem 1rem;
      border-bottom: 1px solid var(--border);
      font-size: .82rem;
      font-weight: 600
    }

    .np-hdr button {
      font-size: .72rem;
      color: var(--muted2);
      background: none;
      border: none;
      cursor: pointer
    }

    .np-empty {
      font-size: .8rem;
      color: var(--muted2);
      padding: 1rem;
      text-align: center
    }

    .np-item {
      display: flex;
      gap: .6rem;
      padding: .7rem 1rem;
      border-bottom: 1px solid var(--border);
      font-size: .78rem
    }

    .np-item:last-child {
      border-bottom: none
    }

    .np-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--red);
      flex-shrink: 0;
      margin-top: 4px
    }

    .content {
      flex: 1;
      overflow-y: auto;
      padding: 1.5rem
    }

    .content::-webkit-scrollbar {
      width: 4px
    }

    .content::-webkit-scrollbar-thumb {
      background: var(--border2);
      border-radius: 2px
    }

    .sec-hdr {
      margin-bottom: 1.25rem
    }

    .sec-hdr h2 {
      font-family: var(--display);
      font-size: 1.25rem;
      font-weight: 700;
      letter-spacing: .5px
    }

    .sec-hdr p {
      font-size: .82rem;
      color: var(--muted2);
      margin-top: .2rem
    }

    .card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 12px;
      box-shadow: var(--shadow);
      transition: border-color .18s
    }

    .card:hover {
      border-color: var(--border2)
    }

    .stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: .9rem;
      margin-bottom: 1.25rem
    }

    .stat-card {
      padding: 1.15rem 1.25rem;
      position: relative;
      overflow: hidden
    }

    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 2px;
      background: var(--accent, var(--blue));
      opacity: .7
    }

    .si {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      display: grid;
      place-items: center;
      margin-bottom: .65rem
    }

    .slabel {
      font-family: var(--mono);
      font-size: .6rem;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      color: var(--muted2);
      margin-bottom: .3rem
    }

    .sval {
      font-family: var(--display);
      font-size: 1.9rem;
      font-weight: 700;
      line-height: 1
    }

    .ssub {
      font-size: .7rem;
      color: var(--muted);
      margin-top: .3rem
    }

    .charts-grid {
      display: grid;
      gap: .9rem;
      margin-bottom: 1.25rem
    }

    .chart-card {
      padding: 1.15rem 1.25rem
    }

    .chart-card h3 {
      font-family: var(--mono);
      font-size: .65rem;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      color: var(--muted2);
      margin-bottom: .85rem;
      display: flex;
      align-items: center;
      gap: .5rem
    }

    .chart-card h3::before {
      content: '';
      width: 10px;
      height: 3px;
      background: var(--blue);
      border-radius: 2px;
      flex-shrink: 0
    }

    .cw {
      width: 100%
    }

    .cw.sm {
      height: 160px
    }

    .cw.md {
      height: 190px
    }

    .cw.lg {
      height: 240px
    }

    .tbl-card {
      padding: 1.25rem 1.5rem
    }

    .tbl-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: .65rem;
      margin-bottom: 1rem
    }

    .tbl-bar h3 {
      font-family: var(--display);
      font-size: 1rem;
      font-weight: 700
    }

    .frow {
      display: flex;
      align-items: center;
      gap: .5rem;
      flex-wrap: wrap
    }

    .fsel {
      background: rgba(255, 255, 255, .04);
      border: 1px solid var(--border2);
      border-radius: 7px;
      padding: .38rem .75rem;
      font-family: var(--font);
      font-size: .78rem;
      color: var(--text);
      cursor: pointer;
      outline: none;
      transition: var(--t)
    }

    .fsel:focus {
      border-color: var(--blue)
    }

    [data-theme=light] .fsel {
      background: #fff
    }

    .tw {
      overflow-x: auto
    }

    table {
      width: 100%;
      border-collapse: collapse
    }

    thead th {
      text-align: left;
      padding: .55rem .75rem;
      font-family: var(--mono);
      font-size: .6rem;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      color: var(--muted2);
      border-bottom: 1px solid var(--border);
      white-space: nowrap
    }

    tbody tr {
      border-bottom: 1px solid var(--border);
      transition: background .18s
    }

    tbody tr:last-child {
      border-bottom: none
    }

    tbody tr:hover {
      background: rgba(59, 139, 255, .04)
    }

    tbody td {
      padding: .65rem .75rem;
      font-size: .82rem
    }

    .rank {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 22px;
      height: 22px;
      border-radius: 5px;
      font-family: var(--mono);
      font-size: .7rem;
      font-weight: 700
    }

    .rA {
      background: rgba(16, 217, 130, .15);
      color: var(--green)
    }

    .rB {
      background: rgba(245, 183, 49, .15);
      color: var(--yellow)
    }

    .rC {
      background: rgba(255, 140, 66, .15);
      color: var(--orange)
    }

    .rD {
      background: rgba(255, 59, 92, .15);
      color: var(--red)
    }

    .sbw {
      display: flex;
      align-items: center;
      gap: .6rem
    }

    .sbb {
      flex: 1;
      height: 4px;
      background: var(--border2);
      border-radius: 2px
    }

    .sbf {
      height: 100%;
      border-radius: 2px
    }

    .sbn {
      font-family: var(--mono);
      font-size: .72rem;
      color: var(--muted2);
      min-width: 32px;
      text-align: right
    }

    .pgn {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: .4rem;
      margin-top: 1rem
    }

    .pb {
      min-width: 30px;
      height: 30px;
      border-radius: 6px;
      border: 1px solid var(--border2);
      background: none;
      font-family: var(--mono);
      font-size: .72rem;
      color: var(--muted2);
      cursor: pointer;
      display: grid;
      place-items: center;
      transition: var(--t)
    }

    .pb:hover,
    .pb.active {
      border-color: var(--blue);
      color: var(--blue);
      background: rgba(59, 139, 255, .07)
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      padding: .42rem .9rem;
      border-radius: 8px;
      font-family: var(--font);
      font-size: .78rem;
      font-weight: 600;
      cursor: pointer;
      transition: var(--t);
      border: none;
      text-decoration: none
    }

    .btn-p {
      background: var(--blue);
      color: #fff
    }

    .btn-p:hover {
      background: #2e7ae8
    }

    .btn-s {
      background: rgba(255, 255, 255, .05);
      color: var(--muted2);
      border: 1px solid var(--border2)
    }

    .btn-s:hover {
      border-color: var(--blue);
      color: var(--text)
    }

    .btn-d {
      background: rgba(255, 59, 92, .1);
      color: var(--red);
      border: 1px solid rgba(255, 59, 92, .2)
    }

    .btn-d:hover {
      background: rgba(255, 59, 92, .2)
    }

    .btn-sm {
      font-size: .72rem;
      padding: .32rem .7rem
    }

    .sdot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      display: inline-block;
      flex-shrink: 0
    }

    .sdot-g {
      background: var(--green);
      box-shadow: 0 0 6px rgba(16, 217, 130, .5)
    }

    .sdot-y {
      background: var(--yellow)
    }

    .sdot-r {
      background: var(--red);
      box-shadow: 0 0 6px rgba(255, 59, 92, .5)
    }

    .mo {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .6);
      display: grid;
      place-items: center;
      z-index: 200;
      backdrop-filter: blur(4px)
    }

    .mo.hidden {
      display: none
    }

    .modal {
      background: var(--bg3);
      border: 1px solid var(--border2);
      border-radius: 14px;
      width: min(90vw, 560px);
      box-shadow: 0 20px 60px rgba(0, 0, 0, .6);
      animation: su .2s ease
    }

    @keyframes su {
      from {
        opacity: 0;
        transform: translateY(20px)
      }

      to {
        opacity: 1;
        transform: none
      }
    }

    .mhdr {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1rem 1.25rem;
      border-bottom: 1px solid var(--border)
    }

    .mhdr h3 {
      font-family: var(--display);
      font-size: 1rem;
      font-weight: 700
    }

    .mcl {
      width: 28px;
      height: 28px;
      border-radius: 7px;
      border: 1px solid var(--border2);
      background: none;
      color: var(--muted2);
      cursor: pointer;
      display: grid;
      place-items: center;
      transition: var(--t)
    }

    .mcl:hover {
      border-color: var(--red);
      color: var(--red)
    }

    .mbdy {
      padding: 1.25rem
    }

    .ts {
      position: relative;
      display: inline-block;
      width: 38px;
      height: 21px;
      flex-shrink: 0
    }

    .ts input {
      opacity: 0;
      width: 0;
      height: 0
    }

    .tsl {
      position: absolute;
      inset: 0;
      cursor: pointer;
      background: rgba(255, 255, 255, .1);
      border-radius: 21px;
      transition: var(--t)
    }

    .tsl::before {
      content: '';
      position: absolute;
      height: 15px;
      width: 15px;
      left: 3px;
      bottom: 3px;
      background: var(--muted2);
      border-radius: 50%;
      transition: var(--t)
    }

    .ts input:checked+.tsl {
      background: var(--blue)
    }

    .ts input:checked+.tsl::before {
      transform: translateX(17px);
      background: #fff
    }

    #toast-c {
      position: fixed;
      bottom: 1.25rem;
      right: 1.25rem;
      display: flex;
      flex-direction: column;
      gap: .5rem;
      z-index: 300
    }

    .toast {
      background: var(--bg3);
      border: 1px solid var(--border2);
      border-radius: 9px;
      padding: .75rem 1rem;
      font-size: .82rem;
      box-shadow: var(--shadow);
      display: flex;
      align-items: center;
      gap: .6rem;
      animation: sl .2s ease;
      min-width: 240px
    }

    @keyframes sl {
      from {
        opacity: 0;
        transform: translateX(20px)
      }

      to {
        opacity: 1;
        transform: none
      }
    }

    .ti {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      flex-shrink: 0
    }

    .fi {
      background: rgba(255, 255, 255, .04);
      border: 1px solid var(--border2);
      border-radius: 8px;
      padding: .5rem .85rem;
      font-family: var(--font);
      font-size: .82rem;
      color: var(--text);
      outline: none;
      transition: var(--t);
      width: 100%
    }

    .fi:focus {
      border-color: var(--blue)
    }

    .fi[readonly],
    .fi:disabled {
      opacity: .6;
      cursor: not-allowed
    }

    textarea.fi {
      resize: vertical
    }

    [data-theme=light] .fi {
      background: #f8fafc
    }

    .fl {
      font-family: var(--mono);
      font-size: .62rem;
      letter-spacing: 1px;
      text-transform: uppercase;
      color: var(--muted);
      display: block;
      margin-bottom: .4rem
    }

    .fg {
      margin-bottom: .85rem
    }

    .pref-r {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: .6rem 0;
      border-bottom: 1px solid var(--border)
    }

    .pref-r:last-child {
      border-bottom: none
    }
  </style>
</head>

<body>
  <div class="bg-grid"></div>
  <div id="app">

    <aside id="sidebar">
      <div class="sb-brand">
        <div class="shield"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white"
            stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
          </svg></div>
        <div class="sb-brand-text">
          <h2>CyberShield</h2><span class="badge">Admin Panel</span>
        </div>
        <button class="sb-toggle" onclick="toggleSidebar()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6" />
          </svg></button>
      </div>
      <div class="sb-section">
        <div class="sb-label">Navigation</div>
        <a class="sb-item active" href="dashboard.php"><span class="sb-icon"><svg width="15" height="15"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"
              stroke-linejoin="round">
              <rect x="3" y="3" width="7" height="7" rx="1.2" />
              <rect x="14" y="3" width="7" height="7" rx="1.2" />
              <rect x="3" y="14" width="7" height="7" rx="1.2" />
              <rect x="14" y="14" width="7" height="7" rx="1.2" />
            </svg></span><span class="sb-text">Dashboard</span></a>
        <a class="sb-item" href="reports.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="20" x2="18" y2="10" />
              <line x1="12" y1="20" x2="12" y2="4" />
              <line x1="6" y1="20" x2="6" y2="14" />
            </svg></span><span class="sb-text">Reports</span></a>
        <a class="sb-item" href="users.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
            </svg></span><span class="sb-text">Users</span></a>
        <a class="sb-item" href="heatmap.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="7" height="7" />
              <rect x="14" y="3" width="7" height="7" />
              <rect x="14" y="14" width="7" height="7" />
              <rect x="3" y="14" width="7" height="7" />
            </svg></span><span class="sb-text">Risk Heatmap</span></a>
        <a class="sb-item" href="activity.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <polyline points="14 2 14 8 20 8" />
              <line x1="16" y1="13" x2="8" y2="13" />
              <line x1="16" y1="17" x2="8" y2="17" />
            </svg></span><span class="sb-text">Activity Log</span></a>
        <a class="sb-item" href="settings.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="8" r="4" />
              <path d="M6 20v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2" />
            </svg></span><span class="sb-text">Settings</span></a>
        <div class="sb-divider"></div>
        <div class="sb-label">Tools</div>
        <a class="sb-item" href="compare.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="8" x2="6" y2="8" />
              <line x1="21" y1="16" x2="3" y2="16" />
            </svg></span><span class="sb-text">Compare</span></a>
        <a class="sb-item" href="email.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
              <polyline points="22,6 12,13 2,6" />
            </svg></span><span class="sb-text">Email Report</span></a>
        <div class="sb-divider"></div>
        <div class="sb-label">Quick Actions</div>
        <a class="sb-item" onclick="showToast('CSV exported','green')"><span class="sb-icon"><svg width="15" height="15"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"
              stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
              <polyline points="7 10 12 15 17 10" />
              <line x1="12" y1="15" x2="12" y2="3" />
            </svg></span><span class="sb-text">Export CSV</span></a>
        <a class="sb-item" onclick="showToast('PDF exported','green')"><span class="sb-icon"><svg width="15" height="15"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"
              stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <polyline points="14 2 14 8 20 8" />
            </svg></span><span class="sb-text">Export PDF</span></a>
        <a class="sb-item" onclick="showToast('Data refreshed','blue')"><span class="sb-icon"><svg width="15"
              height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
              stroke-linecap="round" stroke-linejoin="round">
              <polyline points="23 4 23 10 17 10" />
              <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
            </svg></span><span class="sb-text">Refresh Data</span></a>
      </div>
      <div class="sb-footer">
        <div class="sb-user">
          <div class="sb-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
          <div class="sb-user-info">
            <p><?php echo htmlspecialchars($user['full_name']); ?></p><span><?php echo htmlspecialchars($user['email']); ?></span>
          </div>
        </div>
        <button class="btn-sb-logout" onclick="doLogout()">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <polyline points="16 17 21 12 16 7" />
            <line x1="21" y1="12" x2="9" y2="12" />
          </svg>
          <span>Sign Out</span>
        </button>
      </div>
    </aside>
    <div id="main">

      <div class="topbar">
        <div>
          <div class="tb-bc">
            <span class="tb-app">CyberShield</span>
            <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"
              stroke-linecap="round">
              <path d="M6 4l4 4-4 4" />
            </svg>
            <span class="tb-title">Dashboard Overview</span>
          </div>
          <p class="tb-sub">Vendor cybersecurity risk summary</p>
        </div>
        <div class="tb-right">
          <div class="tb-search-wrap">
            <span class="tb-search-icon"><svg width="12" height="12" viewBox="0 0 20 20" fill="none">
                <circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.7" />
                <path d="M15 15l3 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
              </svg></span>
            <input type="text" class="tb-search" placeholder="Search users, scores…" autocomplete="off" />
          </div>
          <span class="tb-date" id="tb-date"></span>
          <div class="tb-divider"></div>
          <button class="tb-icon-btn" onclick="toggleTheme()" title="Toggle theme">
            <svg id="tmoon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
              stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
            </svg>
            <svg id="tsun" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
              stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:none">
              <circle cx="12" cy="12" r="5" />
              <line x1="12" y1="1" x2="12" y2="3" />
              <line x1="12" y1="21" x2="12" y2="23" />
              <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
              <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
              <line x1="1" y1="12" x2="3" y2="12" />
              <line x1="21" y1="12" x2="23" y2="12" />
            </svg>
          </button>
          <div class="notif-wrap">
            <button class="tb-icon-btn" onclick="toggleNotif()" title="Alerts" style="position:relative">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
              </svg>
              <span class="notif-dot" id="notif-dot"></span>
            </button>
            <div class="np hidden" id="np">
              <div class="np-hdr"><span>Alerts</span><button onclick="clearNotifs()">Clear all</button></div>
              <div id="np-list">
                <div class="np-item"><span class="np-dot"></span><span>Apex Corp dropped to rank D</span></div>
                <div class="np-item"><span class="np-dot"></span><span>3 vendors need compliance review</span></div>
              </div>
            </div>
          </div>
          <div class="tb-divider"></div>
          <a class="tb-admin" href="settings.php">
            <div class="tb-admin-av"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
            <div class="tb-admin-info"><span class="tb-admin-name"><?php echo htmlspecialchars($user['full_name']); ?></span><span class="tb-admin-role"><?php echo htmlspecialchars($user['role'] ?? 'Admin'); ?></span>
            </div>
            <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"
              stroke-linecap="round" style="color:var(--muted);margin-left:.2rem">
              <path d="M4 6l4 4 4-4" />
            </svg>
          </a>
        </div>
      </div>
      <div class="content">

        <div class="sec-hdr">
          <h2>Dashboard Overview</h2>
          <p>User cybersecurity risk summary across all assessments.</p>
        </div>
        <div class="stats-row" id="stats-row">
          <div class="card stat-card" style="--accent:var(--blue)">
            <div class="si" style="background:rgba(59,139,255,.12);color:var(--blue)"><svg width="15" height="15"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"
                stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
              </svg></div>
            <div class="slabel">Total Vendors</div>
            <div class="sval" id="sv-total">—</div>
            <div class="ssub">Registered users</div>
          </div>
          <div class="card stat-card" style="--accent:var(--teal)">
            <div class="si" style="background:rgba(0,212,170,.12);color:var(--teal)"><svg width="15" height="15"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"
                stroke-linejoin="round">
                <line x1="18" y1="20" x2="18" y2="10" />
                <line x1="12" y1="20" x2="12" y2="4" />
                <line x1="6" y1="20" x2="6" y2="14" />
              </svg></div>
            <div class="slabel">Average Score</div>
            <div class="sval" id="sv-avg">—</div>
            <div class="ssub">Platform-wide avg</div>
          </div>
          <div class="card stat-card" style="--accent:var(--green)">
            <div class="si" style="background:rgba(0,255,148,.12);color:var(--green)"><svg width="15" height="15"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"
                stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
              </svg></div>
            <div class="slabel">Low Risk (A)</div>
            <div class="sval" id="sv-a" style="color:var(--green)">—</div>
            <div class="ssub">Rank A vendors</div>
          </div>
          <div class="card stat-card" style="--accent:var(--yellow)">
            <div class="si" style="background:rgba(255,214,10,.1);color:var(--yellow)"><svg width="15" height="15"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"
                stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
              </svg></div>
            <div class="slabel">Moderate (B)</div>
            <div class="sval" id="sv-b" style="color:var(--yellow)">—</div>
            <div class="ssub">Rank B vendors</div>
          </div>
          <div class="card stat-card" style="--accent:var(--orange)">
            <div class="si" style="background:rgba(255,140,66,.12);color:var(--orange)"><svg width="15" height="15"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"
                stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                <line x1="12" y1="9" x2="12" y2="13" />
                <line x1="12" y1="17" x2="12.01" y2="17" />
              </svg></div>
            <div class="slabel">High Risk (C)</div>
            <div class="sval" id="sv-c" style="color:var(--orange)">—</div>
            <div class="ssub">Rank C vendors</div>
          </div>
          <div class="card stat-card" style="--accent:var(--red)">
            <div class="si" style="background:rgba(255,59,92,.12);color:var(--red)"><svg width="15" height="15"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"
                stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <line x1="15" y1="9" x2="9" y2="15" />
                <line x1="9" y1="9" x2="15" y2="15" />
              </svg></div>
            <div class="slabel">Critical (D)</div>
            <div class="sval" id="sv-d" style="color:var(--red)">—</div>
            <div class="ssub">Rank D vendors</div>
          </div>
        </div>
        <div class="charts-grid" style="grid-template-columns:1fr 1fr">
          <div class="card chart-card">
            <h3>Risk Distribution</h3>
            <div class="cw sm"><canvas id="pie-chart"></canvas></div>
          </div>
          <div class="card chart-card">
            <h3>Daily Logins — Last 7 Days (<?php echo $startDate->format('M j'); ?> - <?php echo (clone $endDate)->modify('-1 day')->format('M j'); ?>)</h3>
            <div class="cw sm"><canvas id="login-bar-chart"></canvas></div>
            <div style="margin-top:0.75rem;text-align:center">
              <button class="btn btn-s btn-sm" onclick="showAuditLogModal()">📋 View Audit Log</button>
            </div>
          </div>
        </div>
        <div class="charts-grid" style="grid-template-columns:1fr 1fr">
          <div class="card chart-card">
            <h3>Risk Level Count</h3>
            <div class="cw sm"><canvas id="bar-chart"></canvas></div>
          </div>
          <div class="card chart-card">
            <h3>Login Action Breakdown</h3>
            <div class="cw sm"><canvas id="action-pie-chart"></canvas></div>
          </div>
        </div>
        <div class="sec-hdr">
          <h2>Advanced Analytics</h2>
          <p>Aggregated insights across all users.</p>
        </div>
        <div
          style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.9rem;margin-bottom:1.25rem">
          <div class="card analytic-card" style="padding:1rem 1.15rem">
            <div class="analytic-label"
              style="font-family:var(--mono);font-size:.58rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted)">
              Highest Score</div>
            <div style="font-family:var(--display);font-size:1.5rem;font-weight:700;color:var(--green)" id="an-hi">—
            </div>
            <div style="font-size:.72rem;color:var(--muted2);margin-top:.2rem" id="an-hi-v">—</div>
          </div>
          <div class="card analytic-card" style="padding:1rem 1.15rem">
            <div class="analytic-label"
              style="font-family:var(--mono);font-size:.58rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted)">
              Lowest Score</div>
            <div style="font-family:var(--display);font-size:1.5rem;font-weight:700;color:var(--red)" id="an-lo">—</div>
            <div style="font-size:.72rem;color:var(--muted2);margin-top:.2rem" id="an-lo-v">—</div>
          </div>
          <div class="card analytic-card" style="padding:1rem 1.15rem">
            <div class="analytic-label"
              style="font-family:var(--mono);font-size:.58rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted)">
              Total Assessments</div>
            <div style="font-family:var(--display);font-size:1.5rem;font-weight:700" id="an-tot">—</div>
            <div style="font-size:.72rem;color:var(--muted2);margin-top:.2rem">All time</div>
          </div>
        </div>
        <div class="card tbl-card">
          <div class="tbl-bar">
            <h3>Recent Assessments</h3>
            <div class="frow">
              <select class="fsel" id="rank-filter" onchange="renderTbl()">
                <option value="">All Ranks</option>
                <option value="A">A — Low</option>
                <option value="B">B — Moderate</option>
                <option value="C">C — High</option>
                <option value="D">D — Critical</option>
              </select>
              <button class="btn btn-s btn-sm"
                onclick="document.getElementById('rank-filter').value='';renderTbl()">Clear</button>
              <button class="btn btn-p btn-sm" onclick="showToast('Exported','green')">⬇ Export</button>
            </div>
          </div>
          <div class="tw">
            <table>
              <thead>
                <tr>
                  <th></th>
                  <th>User</th>
                  <th>Score</th>
                  <th>Rank</th>
                  <th>Category</th>
                  <th>Date</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="tbl-body"></tbody>
            </table>
          </div>
          <div class="pgn" id="pgn"></div>
        </div>

      </div>
    </div>
  </div>

  <div id="modal-overlay" class="mo hidden" onclick="if(event.target===this)closeModal()">
    <div class="modal">
      <div class="mhdr">
        <h3 id="modal-title">Detail</h3><button class="mcl" onclick="closeModal()"><svg width="13" height="13"
            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg></button>
      </div>
      <div class="mbdy" id="modal-body"></div>
    </div>
  </div>
  <div id="toast-c"></div>
  <script>
    // Real database data passed from PHP - CONSISTENT DATA THAT DOESN'T CHANGE ON REFRESH
    const DB_USERS = <?php echo $usersJson; ?>;
    const DB_ASSESSMENTS = <?php echo $assessmentsJson; ?>;
    const DB_AUDIT_LOGS = <?php echo $auditLogsJson; ?>;
    const DB_DAILY_LOGINS = <?php echo $dailyLoginsJson; ?>;
    
    // Helper functions for data processing
    function getRank(score) {
      if (score === null || score === undefined) return null;
      return (score >= 80) ? 'A' : ((score >= 60) ? 'B' : ((score >= 40) ? 'C' : 'D'));
    }
    
    function getScoreColor(score) {
      if (score === null || score === undefined) return 'var(--red)';
      return score >= 80 ? 'var(--green)' : score >= 60 ? 'var(--yellow)' : score >= 40 ? 'var(--orange)' : 'var(--red)';
    }

    function sc(s) { return s >= 80 ? 'var(--green)' : s >= 60 ? 'var(--yellow)' : s >= 40 ? 'var(--orange)' : 'var(--red)' }
    function isDark() { return document.documentElement.getAttribute('data-theme') === 'dark' }
    function ax() { return isDark() ? { tick: '#8898b4', grid: 'rgba(59,139,255,.04)', tt: '#0d1421', ttB: 'rgba(255,255,255,.1)', tc: '#dde4f0', bc: '#8898b4' } : { tick: '#64748b', grid: 'rgba(0,0,0,.06)', tt: '#fff', ttB: 'rgba(0,0,0,.1)', tc: '#0f172a', bc: '#475569' } }
    const CC = { A: { s: '#10D982', b: 'rgba(16,217,130,.55)' }, B: { s: '#F5B731', b: 'rgba(245,183,49,.55)' }, C: { s: '#FF7A45', b: 'rgba(255,122,69,.55)' }, D: { s: '#FF4D6A', b: 'rgba(255,77,106,.55)' } };
    function riskCounts() {
      const lat = {};
      DB_ASSESSMENTS.forEach(a => { 
        if (!lat[a.vid] || a.date > lat[a.vid].date) lat[a.vid] = a; 
      });
      const c = { A: 0, B: 0, C: 0, D: 0 };
      Object.values(lat).forEach(a => c[a.rank]++);
      return c;
    }
    function toggleSidebar() {
      document.getElementById('sidebar').classList.toggle('collapsed');
      localStorage.setItem('cs_sb', document.getElementById('sidebar').classList.contains('collapsed') ? '1' : '0');
    }
    function toggleTheme() {
      const d = !isDark();
      document.documentElement.setAttribute('data-theme', d ? 'dark' : 'light');
      localStorage.setItem('cs_th', d ? 'dark' : 'light');
      const m = document.getElementById('tmoon'), s = document.getElementById('tsun');
      if (m) m.style.display = d ? '' : 'none';
      if (s) s.style.display = d ? 'none' : '';
      if (typeof onThemeChange === 'function') onThemeChange();
    }
    function toggleNotif() {
      const p = document.getElementById('np');
      if (p) p.classList.toggle('hidden');
    }
    function clearNotifs() {
      const l = document.getElementById('np-list');
      if (l) l.innerHTML = '<p class="np-empty">No alerts</p>';
      const d = document.getElementById('notif-dot');
      if (d) d.style.display = 'none';
      const p = document.getElementById('np');
      if (p) p.classList.add('hidden');
    }
    function showToast(msg, color = 'blue') {
      const cols = { blue: 'var(--blue)', green: 'var(--green)', red: 'var(--red)', yellow: 'var(--yellow)' };
      const t = document.createElement('div'); t.className = 'toast';
      t.innerHTML = `<span class="ti" style="background:${cols[color] || cols.blue}"></span><span>${msg}</span>`;
      document.getElementById('toast-c').appendChild(t);
      setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; setTimeout(() => t.remove(), 300); }, 2500);
    }
    function doLogout() {
      if (confirm('Are you sure you want to sign out?')) {
        window.location.href = 'logout.php';
      }
    }
    function closeModal() { document.getElementById('modal-overlay').classList.add('hidden') }
    function showAuditLogModal() {
      document.getElementById('modal-title').textContent = 'Audit Log';
      
      const actionColors = {
        login: 'var(--green)', logout: 'var(--muted2)', take_assessment: 'var(--blue)',
        view_report: 'var(--teal)', update_profile: 'var(--yellow)', view_dashboard: 'var(--purple)'
      };
      const actionIcons = {
        login: '→', logout: '←', take_assessment: '✓', view_report: '📋', update_profile: '✎', view_dashboard: '⊞'
      };
      
      const auditLogHtml = DB_AUDIT_LOGS.slice(0, 20).map((a, idx) => {
        const color = actionColors[a.action] || 'var(--muted2)';
        const icon = actionIcons[a.action] || '•';
        const label = a.action_label || a.action.replace(/_/g,' ').replace(/\b\w/g, c=>c.toUpperCase());
        const name = a.full_name || a.username || 'Unknown';
        const initials = name.split(' ').map(w=>w[0]).join('').toUpperCase().slice(0,2);
        const ts = a.created_at ? new Date(a.created_at) : new Date();
        
        const dateStr = ts.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        const timeStr = ts.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        const fullDateTime = `${dateStr} ${timeStr}`;
        
        const ipAddress = a.ip_address || '127.0.0.1';
        const ipDisplay = ipAddress.length > 15 ? `${ipAddress.substring(0, 12)}...` : ipAddress;
        
        return `
          <div style="display:flex;align-items:center;gap:.8rem;padding:.75rem;border-bottom:1px solid var(--border);border-radius:6px;margin-bottom:.5rem;background:rgba(255,255,255,.02)">
            <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--blue),var(--purple));color:#fff;display:grid;place-items:center;font-size:.7rem;font-weight:700;flex-shrink:0;font-family:var(--display)">${initials}</div>
            <div style="flex:1">
              <div style="font-weight:600;font-size:.85rem;margin-bottom:.2rem">${name}</div>
              <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
                <span style="display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .5rem;border-radius:15px;font-size:.7rem;font-weight:600;background:${color}18;color:${color};border:1px solid ${color}30">${icon} ${label}</span>
                <span style="font-family:var(--mono);font-size:.7rem;color:var(--muted2)" title="${ipAddress}">${ipDisplay}</span>
                <span style="font-family:var(--mono);font-size:.7rem;color:var(--muted2)" title="${fullDateTime}">${dateStr}</span>
                <span style="font-family:var(--mono);font-size:.7rem;color:var(--muted2)">${timeStr}</span>
              </div>
            </div>
            <span class="sdot ${a.action==='login'?'sdot-g':a.action==='logout'?'sdot-r':'sdot-y'}" style="display:inline-block"></span>
          </div>
        `;
      }).join('');
      
      document.getElementById('modal-body').innerHTML = `
        <div style="max-height:500px;overflow-y:auto;padding:.5rem">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;padding:0 .5rem">
            <div style="font-family:var(--mono);font-size:.7rem;color:var(--muted2)">Showing latest 20 activities</div>
            <button class="btn btn-s btn-sm" onclick="showToast('Audit log exported','green')">⬇ Export</button>
          </div>
          ${auditLogHtml || '<div style="text-align:center;padding:2rem;color:var(--muted2)">No audit log data available</div>'}
        </div>
      `;
      document.getElementById('modal-overlay').classList.remove('hidden');
    }
    document.addEventListener('DOMContentLoaded', () => {
      const th = localStorage.getItem('cs_th') || 'dark';
      document.documentElement.setAttribute('data-theme', th);
      const m = document.getElementById('tmoon'), s = document.getElementById('tsun');
      if (m) m.style.display = th === 'dark' ? '' : 'none';
      if (s) s.style.display = th === 'dark' ? 'none' : '';
      const sb = localStorage.getItem('cs_sb');
      if (sb === '1') document.getElementById('sidebar').classList.add('collapsed');
      const d = document.getElementById('tb-date');
      if (d) d.textContent = new Date().toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
      if (typeof pageInit === 'function') pageInit();
    });

    let pg = 1, PS = 8, charts = {}, auditPg = 1, AUDIT_PS = 10;
    function pageInit() {
      const c = riskCounts(), tot = DB_USERS.length;
      const avg = DB_ASSESSMENTS.length > 0 ? Math.round(DB_ASSESSMENTS.reduce((a, b) => a + b.score, 0) / DB_ASSESSMENTS.length) : 0;
      
      document.getElementById('sv-total').textContent = tot;
      document.getElementById('sv-avg').textContent = avg + '%';
      document.getElementById('sv-a').textContent = c.A;
      document.getElementById('sv-b').textContent = c.B;
      document.getElementById('sv-c').textContent = c.C;
      document.getElementById('sv-d').textContent = c.D;
      
      if (DB_ASSESSMENTS.length > 0) {
        const hi = DB_ASSESSMENTS.reduce((a, b) => b.score > a.score ? b : a);
        const lo = DB_ASSESSMENTS.reduce((a, b) => b.score < a.score ? b : a);
        document.getElementById('an-hi').textContent = hi.score + '%';
        document.getElementById('an-hi-v').textContent = hi.vname;
        document.getElementById('an-lo').textContent = lo.score + '%';
        document.getElementById('an-lo-v').textContent = lo.vname;
      }
      
      document.getElementById('an-tot').textContent = DB_ASSESSMENTS.length;
      
      renderTbl(); renderCharts(); renderAuditCharts();
    }
    function renderTbl() {
      const f = document.getElementById('rank-filter').value;
      
      // Group assessments by user and get only the latest overall score for each user
      const userLatestScores = {};
      DB_ASSESSMENTS.forEach(a => {
        if (!userLatestScores[a.vid] || a.date > userLatestScores[a.vid].date) {
          userLatestScores[a.vid] = a;
        }
      });
      
      // Convert to array and filter by rank if specified
      let d = Object.values(userLatestScores);
      if (f) d = d.filter(a => a.rank === f);
      
      // Sort by date (most recent first)
      d.sort((a, b) => new Date(b.date) - new Date(a.date));
      
      const tp = Math.ceil(d.length / PS);
      if (pg > tp) pg = 1;
      const sl = d.slice((pg - 1) * PS, pg * PS);
      document.getElementById('tbl-body').innerHTML = sl.map(a => `
    <tr onclick="openModal(${a.id})" style="cursor:pointer">
      <td><input type="checkbox" onclick="event.stopPropagation()"></td>
      <td style="font-weight:600">${a.vname}</td>
      <td><div class="sbw"><div class="sbb"><div class="sbf" style="width:${a.score}%;background:${sc(a.score)}"></div></div><span class="sbn">${a.score}%</span></div></td>
      <td><span class="rank r${a.rank}">${a.rank}</span></td>
      <td style="color:var(--muted2);font-size:.78rem">Overall Assessment</td>
      <td style="color:var(--muted2);font-family:var(--mono);font-size:.72rem">${a.date}</td>
      <td><button class="btn btn-s btn-sm" onclick="event.stopPropagation();openModal(${a.id})">View</button></td>
    </tr>`).join('');
      let ph = ''; for (let i = 1; i <= tp; i++)ph += `<button class="pb ${i === pg ? 'active' : ''}" onclick="pg=${i};renderTbl()">${i}</button>`;
      document.getElementById('pgn').innerHTML = ph;
    }
    function openModal(id) {
      const a = DB_ASSESSMENTS.find(x => x.id === id);
      if (!a) return;
      
      document.getElementById('modal-title').textContent = a.vname + ' - Assessment Details';
      
      // Build category scores HTML
      let categoriesHtml = '';
      if (a.categories && a.categories.length > 0) {
        categoriesHtml = a.categories.map(cat => `
          <div style="display:flex;align-items:center;justify-content:space-between;padding:.6rem .8rem;background:rgba(255,255,255,.02);border:1px solid var(--border);border-radius:6px;margin-bottom:.5rem">
            <div>
              <div style="font-weight:600;font-size:.85rem;margin-bottom:.2rem">${cat.category.charAt(0).toUpperCase() + cat.category.slice(1).replace(/_/g, ' ')}</div>
              <div style="font-family:var(--mono);font-size:.7rem;color:var(--muted2)">${cat.correct_answers}/${cat.total_questions} correct</div>
            </div>
            <div style="text-align:right">
              <div style="font-family:var(--display);font-size:1.1rem;font-weight:700;color:${sc(cat.category_score)}">${cat.category_score}%</div>
              <div style="margin-top:.2rem"><span class="rank r${getRank(cat.category_score)}">${getRank(cat.category_score)}</span></div>
            </div>
          </div>
        `).join('');
      } else {
        categoriesHtml = '<div style="text-align:center;padding:2rem;color:var(--muted2)">No category data available</div>';
      }
      
      document.getElementById('modal-body').innerHTML = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.85rem;margin-bottom:1rem">
          <div style="padding:.75rem;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:8px">
            <div style="font-family:var(--mono);font-size:.58rem;letter-spacing:1px;text-transform:uppercase;color:var(--muted)">Overall Score</div>
            <div style="font-family:var(--display);font-size:1.5rem;font-weight:700;color:${sc(a.score)}">${a.score}%</div>
          </div>
          <div style="padding:.75rem;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:8px">
            <div style="font-family:var(--mono);font-size:.58rem;letter-spacing:1px;text-transform:uppercase;color:var(--muted)">Overall Rank</div>
            <div style="margin-top:.4rem"><span class="rank r${a.rank}">${a.rank}</span></div>
          </div>
        </div>
        
        <div style="margin-bottom:1rem">
          <h4 style="font-family:var(--mono);font-size:.7rem;letter-spacing:1px;text-transform:uppercase;color:var(--muted);margin-bottom:.6rem">Category Breakdown</h4>
          ${categoriesHtml}
        </div>
        
        <div style="padding:.85rem;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:8px;font-size:.8rem;color:var(--muted2)">
          <div style="margin-bottom:.3rem">Assessment Date: <b style="color:var(--text)">${a.date}</b></div>
          <div>Assessment ID: <b style="color:var(--text)">#${a.id}</b></div>
          ${a.rank === 'D' ? '<br><div style="color:var(--red);font-weight:600">⚠ Critical Risk — immediate action required.</div>' : ''}
        </div>
      `;
      
      document.getElementById('modal-overlay').classList.remove('hidden');
    }
    function renderCharts() {
      const c = riskCounts(), a = ax();
      if (charts.bar) charts.bar.destroy();
      if (charts.pie) charts.pie.destroy();
      if (charts.line) charts.line.destroy();
      const bCtx = document.getElementById('bar-chart');
      if (bCtx) charts.bar = new Chart(bCtx, { type: 'bar', data: { labels: ['A', 'B', 'C', 'D'], datasets: [{ data: [c.A, c.B, c.C, c.D], backgroundColor: [CC.A.b, CC.B.b, CC.C.b, CC.D.b], borderColor: [CC.A.s, CC.B.s, CC.C.s, CC.D.s], borderWidth: 2, borderRadius: 5, borderSkipped: false }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { backgroundColor: a.tt, borderColor: a.ttB, borderWidth: 1, titleColor: a.tc, bodyColor: a.bc, padding: 10 } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1, color: a.tick, font: { size: 10 } }, grid: { color: a.grid } }, x: { ticks: { color: a.tick, font: { size: 10 } }, grid: { display: false } } } } });
      const pCtx = document.getElementById('pie-chart');
      if (pCtx) charts.pie = new Chart(pCtx, { type: 'doughnut', data: { labels: ['A', 'B', 'C', 'D'], datasets: [{ data: [c.A, c.B, c.C, c.D], backgroundColor: [CC.A.s, CC.B.s, CC.C.s, CC.D.s], borderWidth: 2, borderColor: isDark() ? '#030508' : '#fff', hoverOffset: 5 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { font: { size: 9 }, padding: 10, color: a.tick } }, tooltip: { backgroundColor: a.tt, borderColor: a.ttB, borderWidth: 1, titleColor: a.tc, bodyColor: a.bc } } } });
      const byM = {}; DB_ASSESSMENTS.forEach(x => { const k = x.date.slice(0, 7); if (!byM[k]) byM[k] = []; byM[k].push(x.score); });
      const keys = Object.keys(byM).sort().slice(-8);
      const vals = keys.map(k => Math.round(byM[k].reduce((a, b) => a + b, 0) / byM[k].length));
      const lCtx = document.getElementById('line-chart');
      if (lCtx) charts.line = new Chart(lCtx, { type: 'line', data: { labels: keys.map(k => { const [y, m] = k.split('-'); return new Date(y, m - 1).toLocaleDateString('en-US', { month: 'short', year: '2-digit' }); }), datasets: [{ label: 'Avg Score', data: vals, borderColor: '#7B72F0', backgroundColor: isDark() ? 'rgba(91,79,232,.1)' : 'rgba(91,79,232,.12)', fill: true, tension: .4, pointBackgroundColor: '#7B72F0', pointBorderColor: isDark() ? '#030508' : '#fff', pointBorderWidth: 2, pointRadius: 4, pointHoverRadius: 6 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { backgroundColor: a.tt, borderColor: a.ttB, borderWidth: 1, titleColor: a.tc, bodyColor: a.bc, padding: 10 } }, scales: { y: { min: 0, max: 100, ticks: { color: a.tick, font: { size: 10 }, callback: v => v + '%' }, grid: { color: a.grid } }, x: { ticks: { color: a.tick, font: { size: 10 } }, grid: { display: false } } } } });
    }
    function onThemeChange() { renderCharts(); renderAuditCharts(); }

    // FIXED: Render audit charts with NO DUPLICATE DATES
    function renderAuditCharts() {
      const a = ax();
      if (charts.loginBar) charts.loginBar.destroy();
      if (charts.actionPie) charts.actionPie.destroy();

      // Daily login bar chart - USING FIXED DATA FROM PHP (NO DUPLICATES)
      console.log('Daily Logins Data:', DB_DAILY_LOGINS);
      
      const labels = DB_DAILY_LOGINS.map(d => {
        const dt = new Date(d.login_date + 'T12:00:00');
        return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
      });
      
      const counts = DB_DAILY_LOGINS.map(d => parseInt(d.login_count));
      
      console.log('Labels:', labels);
      console.log('Counts:', counts);
      
      const lbCtx = document.getElementById('login-bar-chart');
      if (lbCtx) {
        charts.loginBar = new Chart(lbCtx, {
          type: 'bar',
          data: {
            labels: labels,
            datasets: [{
              label: 'Logins',
              data: counts,
              backgroundColor: 'rgba(0,212,170,.55)',
              borderColor: '#00D4AA',
              borderWidth: 2,
              borderRadius: 6,
              borderSkipped: false
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  title: (tooltipItems) => {
                    return labels[tooltipItems[0].dataIndex];
                  },
                  label: (tooltipItem) => {
                    return `Logins: ${tooltipItem.raw}`;
                  }
                },
                backgroundColor: a.tt,
                borderColor: a.ttB,
                borderWidth: 1,
                titleColor: a.tc,
                bodyColor: a.bc,
                padding: 10
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                ticks: { 
                  stepSize: 1, 
                  color: a.tick, 
                  font: { size: 10 } 
                },
                grid: { color: a.grid }
              },
              x: {
                ticks: { 
                  color: a.tick, 
                  font: { size: 10 }, 
                  maxRotation: 45, 
                  minRotation: 45 
                },
                grid: { display: false }
              }
            }
          }
        });
      }

      // Action breakdown pie chart
      const actionCounts = {};
      DB_AUDIT_LOGS.forEach(l => { 
        actionCounts[l.action] = (actionCounts[l.action] || 0) + 1; 
      });
      const actionLabelsArr = Object.keys(actionCounts).map(k => 
        k.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())
      );
      const actionVals = Object.values(actionCounts);
      const pieColors = ['#00D4AA','#3B8BFF','#7B72F0','#F5B731','#FF8C42','#FF3B5C'];
      const apCtx = document.getElementById('action-pie-chart');
      if (apCtx) {
        if (charts.actionPie) charts.actionPie.destroy();
        charts.actionPie = new Chart(apCtx, {
          type: 'doughnut',
          data: {
            labels: actionLabelsArr,
            datasets: [{ 
              data: actionVals, 
              backgroundColor: pieColors.slice(0, actionVals.length), 
              borderWidth: 2, 
              borderColor: isDark() ? '#030508' : '#fff',
              hoverOffset: 5
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { 
                position: 'bottom', 
                labels: { 
                  font: { size: 9 }, 
                  padding: 8, 
                  color: a.tick 
                } 
              },
              tooltip: { 
                backgroundColor: a.tt, 
                borderColor: a.ttB, 
                borderWidth: 1, 
                titleColor: a.tc, 
                bodyColor: a.bc 
              }
            }
          }
        });
      }
    }
  </script>
</body>
</html>