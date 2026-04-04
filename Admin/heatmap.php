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

// Get current admin user data - prioritize session variables set by profile update
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

    

    // Check if assessments table has data

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM assessments");

    $stmt->execute();

    $assessmentCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    

    if ($assessmentCount > 0) {

        // Get real assessment data from assessments table with individual category breakdowns

        $stmt = $db->prepare("

            SELECT 

                a.id,

                a.vendor_id as vid,

                u.full_name,

                u.store_name,

                a.score,

                a.rank,

                a.password_score,

                a.phishing_score,

                a.device_score,

                a.network_score,

                a.social_engineering_score,

                a.data_handling_score,

                DATE(a.assessment_date) as date

            FROM assessments a

            JOIN users u ON a.vendor_id = u.id

            ORDER BY a.assessment_date DESC

        ");

        $stmt->execute();

        $assessmentData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        

        // Create category-based assessments from individual scores

        $categories = ['Access Control', 'Network Security', 'Data Encryption', 'Compliance', 'Incident Response', 'Physical Security'];

        $categoryFields = ['password_score', 'network_score', 'data_handling_score', 'social_engineering_score', 'device_score', 'phishing_score'];

        

        foreach ($assessmentData as $assessment) {

            // Create separate assessment records for each category

            foreach ($categories as $index => $category) {

                $categoryField = $categoryFields[$index];

                $categoryScore = $assessment[$categoryField];

                

                if ($categoryScore > 0) { // Only include categories that have scores

                    $rank = ($categoryScore >= 80) ? 'A' : (($categoryScore >= 60) ? 'B' : (($categoryScore >= 40) ? 'C' : 'D'));

                    

                    $assessments[] = [

                        'id' => $assessment['id'] . '_' . $index, // Unique ID for each category

                        'vid' => $assessment['vid'],

                        'vname' => $assessment['full_name'] ?: $assessment['store_name'],

                        'score' => (int)$categoryScore,

                        'rank' => $rank,

                        'cat' => $category,

                        'date' => $assessment['date']

                    ];

                }

            }

        }

    } else {

        // Create persistent demo data that won't change on refresh

        // First, check if we already have persistent demo data

        $stmt = $db->prepare("SELECT COUNT(*) as count FROM assessments");

        $stmt->execute();

        $assessmentCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        

        if ($assessmentCount == 0 && !empty($users)) {

            // Create persistent demo data in database (runs only once)

            $categories = ['Access Control', 'Network Security', 'Data Encryption', 'Compliance', 'Incident Response', 'Physical Security'];

            $categoryFields = ['password_score', 'phishing_score', 'device_score', 'network_score', 'social_engineering_score', 'data_handling_score'];

            

            foreach ($users as $user) {

                // Use a deterministic score based on user ID (same every time)

                $baseScore = 50 + (($user['id'] * 7) % 45); // Range 50-95

                

                $totalScore = 0;

                $validCategories = 0;

                

                foreach ($categories as $index => $category) {

                    // Use deterministic variation based on user ID and category index (NOT random)

                    $categoryVariation = (($user['id'] + $index) % 31) - 15;

                    $categoryScore = max(20, min(100, $baseScore + $categoryVariation));

                    

                    $totalScore += $categoryScore;

                    $validCategories++;

                    

                    // Prepare data for insertion

                    $insertData = [

                        'vendor_id' => $user['id'],

                        'score' => $categoryScore,

                        'time_spent' => 300, // 5 minutes

                        'questions_answered' => 5,

                        'total_questions' => 5,

                        'assessment_date' => date('Y-m-d H:i:s'),

                        'assessment_token' => uniqid('demo_', true),

                        'session_id' => session_id()

                    ];

                    

                    // Set individual category scores

                    $insertData[$categoryFields[$index]] = $categoryScore;

                    

                    // Calculate overall rank

                    $rank = ($categoryScore >= 80) ? 'A' : (($categoryScore >= 60) ? 'B' : (($categoryScore >= 40) ? 'C' : 'D'));

                    $insertData['rank'] = $rank;

                    

                    // Build dynamic INSERT query

                    $fields = array_keys($insertData);

                    $placeholders = array_fill(0, count($fields), '?');

                    

                    $sql = "INSERT INTO assessments (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";

                    $stmt = $db->prepare($sql);

                    $stmt->execute(array_values($insertData));

                }

                

                // Update user with average score

                $avgScore = round($totalScore / $validCategories);

                $stmt = $db->prepare("UPDATE users SET last_assessment_score = ?, total_assessments = ?, last_assessment_date = NOW() WHERE id = ?");

                $stmt->execute([$avgScore, $validCategories, $user['id']]);

            }

        }

        

        // Now fetch the data we just created

        $stmt = $db->prepare("

            SELECT 

                a.id,

                a.vendor_id as vid,

                u.full_name,

                u.store_name,

                a.password_score,

                a.phishing_score,

                a.device_score,

                a.network_score,

                a.social_engineering_score,

                a.data_handling_score,

                DATE(a.assessment_date) as date

            FROM assessments a

            JOIN users u ON a.vendor_id = u.id

            ORDER BY a.assessment_date DESC

        ");

        $stmt->execute();

        $assessmentData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        

        // Create category-based assessments from individual scores

        $categories = ['Access Control', 'Network Security', 'Data Encryption', 'Compliance', 'Incident Response', 'Physical Security'];

        $categoryFields = ['password_score', 'network_score', 'data_handling_score', 'social_engineering_score', 'device_score', 'phishing_score'];

        

        foreach ($assessmentData as $assessment) {

            // Create separate assessment records for each category

            foreach ($categories as $index => $category) {

                $categoryField = $categoryFields[$index];

                $categoryScore = $assessment[$categoryField];

                

                if ($categoryScore > 0) { // Only include categories that have scores

                    $rank = ($categoryScore >= 80) ? 'A' : (($categoryScore >= 60) ? 'B' : (($categoryScore >= 40) ? 'C' : 'D'));

                    

                    $assessments[] = [

                        'id' => $assessment['id'] . '_' . $index, // Unique ID for each category

                        'vid' => $assessment['vid'],

                        'vname' => $assessment['full_name'] ?: $assessment['store_name'],

                        'score' => (int)$categoryScore,

                        'rank' => $rank,

                        'cat' => $category,

                        'date' => $assessment['date']

                    ];

                }

            }

        }

    }

    

} catch(PDOException $exception) {

    error_log("Error fetching heatmap data: " . $exception->getMessage());

    // Fallback to empty arrays if database fails

    $users = [];

    $assessments = [];

}



// Convert to JSON for JavaScript

$usersJson = json_encode($users);

$assessmentsJson = json_encode($assessments);

?>

<!DOCTYPE html>

<html lang="en" data-theme="dark">



<head>

  <meta charset="UTF-8" />

  <meta name="viewport" content="width=device-width,initial-scale=1.0" />

  <title>Risk Heatmap — CyberShield</title>

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



    #hm-grid {

      display: grid;

      gap: 4px;

      min-width: 640px

    }



    .hm-hdr {

      font-family: var(--mono);

      font-size: .58rem;

      letter-spacing: 1px;

      text-transform: uppercase;

      color: var(--muted);

      padding: .3rem .4rem;

      text-align: center

    }



    .hm-label {

      font-size: .78rem;

      color: var(--muted2);

      display: flex;

      align-items: center;

      padding: .3rem .6rem;

      white-space: nowrap;

      overflow: hidden;

      max-width: 140px

    }



    .hm-cell {

      height: 36px;

      border-radius: 5px;

      display: grid;

      place-items: center;

      font-family: var(--mono);

      font-size: .68rem;

      font-weight: 600;

      cursor: pointer;

      transition: transform .15s, box-shadow .15s

    }



    .hm-cell:hover {

      transform: scale(1.06);

      box-shadow: 0 4px 12px rgba(0, 0, 0, .3);

      z-index: 1;

      position: relative

    }



    .hm-legend {

      display: flex;

      align-items: center;

      gap: .65rem;

      margin-bottom: 1rem;

      font-size: .72rem;

      color: var(--muted2)

    }



    .hm-leg-bar {

      flex: 1;

      max-width: 140px;

      height: 8px;

      border-radius: 4px;

      background: linear-gradient(90deg, var(--red), var(--yellow), var(--green))

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

        <a class="sb-item" href="dashboard.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"

              fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">

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

        <a class="sb-item active" href="heatmap.php"><span class="sb-icon"><svg width="15" height="15"

              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"

              stroke-linejoin="round">

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

        <a class="sb-item" onclick="location.reload()"><span class="sb-icon"><svg width="15"

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

            <span class="tb-title">Risk Heatmap</span>

          </div>

          <p class="tb-sub">Per-category visual breakdown</p>

        </div>

        <div class="tb-right">

          <div class="tb-search-wrap">

            <span class="tb-search-icon"><svg width="12" height="12" viewBox="0 0 20 20" fill="none">

                <circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.7" />

                <path d="M15 15l3 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />

              </svg></span>

            <input type="text" class="tb-search" id="searchInput" placeholder="Search vendors..." autocomplete="off" onkeyup="filterHeatmap()" />

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

          <h2>Risk Heatmap</h2>

          <p>Per-vendor, per-category score breakdown at a glance.</p>

        </div>

        <div class="card" style="padding:1.25rem 1.5rem">

          <div class="hm-legend"><span>Poor</span>

            <div class="hm-leg-bar"></div><span>Excellent</span>

          </div>

          <div style="overflow-x:auto">

            <div id="hm-grid"></div>

          </div>

          <div style="margin-top:1rem;padding:0.75rem;background:var(--bg2);border-radius:8px;font-size:0.75rem;color:var(--muted2);text-align:center">

            📊 Data is persistent and does not change on refresh | 🖱️ Click any cell for details

          </div>

        </div>



      </div>

    </div>

  </div>



  <div id="modal-overlay" class="mo hidden" onclick="if(event.target===this)closeModal()">

    <div class="modal">

      <div class="mhdr">

        <h3 id="modal-title">Assessment Detail</h3><button class="mcl" onclick="closeModal()"><svg width="13" height="13"

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

    // Real database data passed from PHP - THIS DATA IS PERSISTENT

    const DB_USERS = <?php echo $usersJson; ?>;

    const DB_ASSESSMENTS = <?php echo $assessmentsJson; ?>;

    

    console.log('📊 Loaded assessments count:', DB_ASSESSMENTS.length);

    console.log('👥 Loaded users count:', DB_USERS.length);

    

    // Helper functions

    function getScoreColor(score) {

      if (score === null || score === undefined) return '#FF4D6A';

      return score >= 80 ? '#10D982' : score >= 60 ? '#F5B731' : score >= 40 ? '#FF8C42' : '#FF4D6A';

    }

    

    function getScoreOpacity(score) {

      if (score === null || score === undefined) return 0.2;

      return (0.2 + (score / 100) * 0.7).toFixed(2);

    }

    

    function getRankLabel(rank) {

      const labels = {

        'A': 'Excellent',

        'B': 'Good',

        'C': 'Needs Improvement',

        'D': 'Critical'

      };

      return labels[rank] || rank;

    }

    

    function isDark() { return document.documentElement.getAttribute('data-theme') === 'dark'; }

    

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

      renderHeatmap();

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

    

    function closeModal() { document.getElementById('modal-overlay').classList.add('hidden'); }

    

    function viewAssessmentDetail(vendorName, category, score, rank, date) {

      const rankLabel = getRankLabel(rank);

      const scoreColor = getScoreColor(score);

      

      document.getElementById('modal-title').textContent = `${vendorName} - ${category}`;

      document.getElementById('modal-body').innerHTML = `

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.85rem;margin-bottom:1rem">

          <div style="padding:.75rem;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:8px">

            <div style="font-family:var(--mono);font-size:.58rem;letter-spacing:1px;color:var(--muted)">Score</div>

            <div style="font-family:var(--display);font-size:1.5rem;font-weight:700;color:${scoreColor}">${score}%</div>

          </div>

          <div style="padding:.75rem;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:8px">

            <div style="font-family:var(--mono);font-size:.58rem;letter-spacing:1px;color:var(--muted)">Rank</div>

            <div style="margin-top:.4rem"><span class="rank r${rank}">${rank}</span> - ${rankLabel}</div>

          </div>

        </div>

        <div style="padding:.85rem;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:8px;margin-bottom:1rem">

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem">

            <div><span style="color:var(--muted)">Vendor:</span> <b>${vendorName}</b></div>

            <div><span style="color:var(--muted)">Category:</span> <b>${category}</b></div>

            <div><span style="color:var(--muted)">Assessment Date:</span> <b>${date || 'N/A'}</b></div>

          </div>

        </div>

        <div style="padding:.85rem;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:8px">

          <div style="font-family:var(--mono);font-size:.58rem;letter-spacing:1px;color:var(--muted);margin-bottom:.5rem">Recommendations</div>

          ${score >= 80 ? '<span style="color:var(--green)">✓ Excellent security posture in this category. Maintain current practices.</span>' : 

            score >= 60 ? '<span style="color:var(--yellow)">⚠ Moderate risk. Review and improve security controls for this category.</span>' :

            '<span style="color:var(--red)">✗ Critical risk. Immediate action required for this category.</span>'}

        </div>

      `;

      document.getElementById('modal-overlay').classList.remove('hidden');

    }

    

    function filterHeatmap() {

      const searchTerm = document.getElementById('searchInput').value.toLowerCase();

      renderHeatmap(searchTerm);

    }

    

    function renderHeatmap(searchTerm = '') {

      // Get unique categories from real data

      const categories = [...new Set(DB_ASSESSMENTS.map(a => a.cat))];

      

      // Get unique users

      const uniqueUsers = [];

      const seenUsers = new Set();

      

      DB_ASSESSMENTS.forEach(assessment => {

        if (!seenUsers.has(assessment.vid)) {

          seenUsers.add(assessment.vid);

          uniqueUsers.push({

            id: assessment.vid,

            name: assessment.vname

          });

        }

      });

      

      // Filter users by search term

      let users = uniqueUsers;

      if (searchTerm) {

        users = users.filter(user => user.name.toLowerCase().includes(searchTerm));

      }

      

      const cols = categories.length;

      const grid = document.getElementById('hm-grid');

      

      if (users.length === 0) {

        grid.innerHTML = '<div style="padding:2rem;text-align:center;color:var(--muted2);grid-column:1/-1">No vendors found matching your search</div>';

        return;

      }

      

      grid.style.gridTemplateColumns = `160px repeat(${cols},1fr)`;

      

      let html = '<div class="hm-hdr"></div>';

      categories.forEach(c => html += `<div class="hm-hdr">${c.split(' ').slice(0, 2).join(' ')}</div>`);

      

      users.forEach(user => {

        html += `<div class="hm-label" title="${user.name}">${user.name.length > 15 ? user.name.substring(0, 12) + '...' : user.name}</div>`;

        

        categories.forEach(category => {

          // Find assessment for this user and category (use latest if multiple)

          const assessments = DB_ASSESSMENTS.filter(a => a.vid === user.id && a.cat === category);

          // Sort by date and get the most recent

          assessments.sort((a, b) => new Date(b.date) - new Date(a.date));

          const assessment = assessments[0]; // Most recent assessment

          

          if (assessment) {

            const color = getScoreColor(assessment.score);

            const opacity = getScoreOpacity(assessment.score);

            html += `<div class="hm-cell" style="background:${color};opacity:${opacity}" 

                     title="${assessment.vname}: ${assessment.score}% in ${category} (${assessment.date})" 

                     onclick="viewAssessmentDetail('${assessment.vname.replace(/'/g, "\\'")}','${category}',${assessment.score},'${assessment.rank}','${assessment.date}')">

                     ${assessment.score}%</div>`;

          } else {

            html += `<div class="hm-cell" style="background:#ccc;opacity:0.3" title="No assessment data available for this category" onclick="showToast('No assessment data available for ${user.name} in ${category}','yellow')">—</div>`;

          }

        });

      });

      

      grid.innerHTML = html;

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

      

      renderHeatmap();

    });

  </script>

</body>

</html>