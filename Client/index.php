<?php
require_once '../includes/config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit();
}

$database = new Database();
$db = $database->getConnection();

// Get user data
$user_query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($user_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get current page from URL parameter
$page = $_GET['page'] ?? 'dashboard';

// Questions will be loaded from database
// $questions = [...]; // Remove hardcoded questions array

// Tips will be loaded from database
// $tips = [...]; // Remove hardcoded tips array

// Get user's assessment statistics
$stats_query = "SELECT 
    COUNT(*) as total_assessments,
    AVG(score) as avg_score,
    MAX(score) as best_score,
    MIN(score) as worst_score,
    (SELECT score FROM assessments WHERE vendor_id = :user_id ORDER BY created_at DESC LIMIT 1) as latest_score,
    (SELECT rank FROM assessments WHERE vendor_id = :user_id ORDER BY created_at DESC LIMIT 1) as latest_rank
    FROM assessments 
    WHERE vendor_id = :user_id";
$stmt = $db->prepare($stats_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get latest assessment for this user's vendor
$assessment_query = "SELECT a.*, u.store_name as vendor_name 
    FROM assessments a 
    JOIN users u ON a.vendor_id = u.id 
    WHERE u.id = :user_id 
    ORDER BY a.created_at DESC LIMIT 1";
$stmt = $db->prepare($assessment_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$latest_assessment = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all assessments for history
$history_query = "SELECT a.*, u.store_name as vendor_name 
    FROM assessments a 
    JOIN users u ON a.vendor_id = u.id 
    WHERE u.id = :user_id   
    ORDER BY a.created_at DESC";
$stmt = $db->prepare($history_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get products for this seller
$products_query = "SELECT * FROM products WHERE user_id = :user_id ORDER BY created_at DESC";
$stmt = $db->prepare($products_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's badge achievements from database
$badges_query = "SELECT b.*, ua.earned_at, ua.points_earned 
    FROM user_achievements ua 
    JOIN badges b ON ua.badge_id = b.id 
    WHERE ua.user_id = :user_id 
    ORDER BY ua.earned_at DESC";
$stmt = $db->prepare($badges_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$earned_badges = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get badge statistics
$badge_stats_query = "SELECT 
    COUNT(*) as total_badges,
    SUM(b.points) as total_points,
    COUNT(CASE WHEN b.category = 'assessment' THEN 1 END) as assessment_badges,
    COUNT(CASE WHEN b.category = 'consistency' THEN 1 END) as consistency_badges
    FROM user_achievements ua 
    JOIN badges b ON ua.badge_id = b.id 
    WHERE ua.user_id = :user_id";
$stmt = $db->prepare($badge_stats_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$badge_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get leaderboard data
$leaderboard_query = "SELECT u.store_name, 
    a.score, a.rank, a.password_score, a.phishing_score, 
    a.device_score, a.network_score, a.created_at
    FROM assessments a
    JOIN users u ON a.vendor_id = u.id
    WHERE a.id IN (SELECT MAX(id) FROM assessments GROUP BY vendor_id)
    ORDER BY a.score DESC";
$stmt = $db->prepare($leaderboard_query);
$stmt->execute();
$leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all assessments history for results page
$history_query2 = "SELECT a.*, u.store_name as vendor_name 
    FROM assessments a 
    JOIN users u ON a.vendor_id = u.id 
    WHERE u.id = :user_id 
    ORDER BY a.created_at DESC";
$stmt = $db->prepare($history_query2);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Store/analytics stats
$totalProducts = count($products);
$activeProducts = count(array_filter($products, fn($p) => $p['status'] === 'active'));
$outOfStock = count(array_filter($products, fn($p) => ($p['stock'] ?? 0) == 0));
$totalValue = array_sum(array_column($products, 'price'));

// Security analytics derived from assessment history
$riskDistribution = ['low' => 0, 'moderate' => 0, 'high' => 0, 'critical' => 0];
$topWeaknessLabel = '--';
$categoryAverages = [
  'password' => 0,
  'phishing' => 0,
  'device' => 0,
  'network' => 0,
  'social' => 0,
  'data' => 0
];

if (!empty($history)) {
  foreach ($history as $assessment) {
    $score = (float) ($assessment['score'] ?? 0);
    if ($score >= 80) {
      $riskDistribution['low']++;
    } elseif ($score >= 60) {
      $riskDistribution['moderate']++;
    } elseif ($score >= 40) {
      $riskDistribution['high']++;
    } else {
      $riskDistribution['critical']++;
    }

    $categoryAverages['password'] += (float) ($assessment['password_score'] ?? 0);
    $categoryAverages['phishing'] += (float) ($assessment['phishing_score'] ?? 0);
    $categoryAverages['device'] += (float) ($assessment['device_score'] ?? 0);
    $categoryAverages['network'] += (float) ($assessment['network_score'] ?? 0);
    $categoryAverages['social'] += (float) ($assessment['social_engineering_score'] ?? 0);
    $categoryAverages['data'] += (float) ($assessment['data_handling_score'] ?? 0);
  }

  $count = count($history);
  foreach ($categoryAverages as $key => $value) {
    $categoryAverages[$key] = round($value / $count, 1);
  }

  $labelMap = [
    'password' => 'Password',
    'phishing' => 'Phishing',
    'device' => 'Device',
    'network' => 'Network',
    'social' => 'Social',
    'data' => 'Data'
  ];
  asort($categoryAverages);
  $topWeaknessKey = array_key_first($categoryAverages);
  $topWeaknessLabel = $topWeaknessKey ? $labelMap[$topWeaknessKey] : '--';
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
  header('Content-Type: application/json');

  if ($_GET['action'] === 'save_assessment') {
    try {
      // Get POST data
      $input = json_decode(file_get_contents('php://input'), true);

      // Insert assessment directly using user_id as vendor_id
      $insert_assessment = "INSERT INTO assessments 
                                  (vendor_id, score, rank, password_score, phishing_score, device_score, network_score, social_engineering_score, data_handling_score, time_spent, questions_answered, total_questions, assessment_date, assessment_token, session_id) 
                                  VALUES (:vendor_id, :score, :rank, :password_score, :phishing_score, :device_score, :network_score, :social_engineering_score, :data_handling_score, :time_spent, :questions_answered, :total_questions, NOW(), :assessment_token, :session_id)";
      $stmt = $db->prepare($insert_assessment);
      $stmt->bindParam(':vendor_id', $_SESSION['user_id']);
      $stmt->bindParam(':score', $input['score']);
      $stmt->bindParam(':rank', $input['rank']);
      $stmt->bindParam(':password_score', $input['password_score']);
      $stmt->bindParam(':phishing_score', $input['phishing_score']);
      $stmt->bindParam(':device_score', $input['device_score']);
      $stmt->bindParam(':network_score', $input['network_score']);
      $stmt->bindParam(':social_engineering_score', $input['social_engineering_score'] ?? 0);
      $stmt->bindParam(':data_handling_score', $input['data_handling_score'] ?? 0);
      $stmt->bindParam(':time_spent', $input['time_spent'] ?? 0);
      $stmt->bindParam(':questions_answered', $input['questions_answered'] ?? 100);
      $stmt->bindParam(':total_questions', $input['total_questions'] ?? 100);
      $stmt->bindParam(':assessment_token', $input['assessment_token'] ?? bin2hex(random_bytes(32)));
      $stmt->bindParam(':session_id', $input['session_id'] ?? bin2hex(random_bytes(32)));
      $stmt->execute();

      $assessment_id = $db->lastInsertId();

      // Award badges using the badge system
      require_once '../includes/badge_system.php';
      $badge_system = new BadgeSystem($db, $_SESSION['user_id']);

      $assessment_data = [
        'score' => $input['score'],
        'rank' => $input['rank'],
        'password_score' => $input['password_score'],
        'phishing_score' => $input['phishing_score'],
        'device_score' => $input['device_score'],
        'network_score' => $input['network_score'],
        'id' => $assessment_id
      ];

      $awarded_badges = $badge_system->awardBadgesForAssessment($assessment_data);

      echo json_encode([
        'success' => true,
        'assessment_id' => $assessment_id,
        'awarded_badges' => $awarded_badges
      ]);

    } catch (Exception $e) {
      echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
      ]);
    }
    exit;
  }
}

?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>Dashboard — CyberShield</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
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
      background: rgba(16, 217, 130, .12);
      color: var(--green);
      border: 1px solid rgba(16, 217, 130, .2);
      border-radius: 4px;
      padding: .08rem .38rem;
      display: inline-block;
      margin-top: .1rem
    }

    .sb-toggle {
      width: 28px;
      height: 28px;
      background: rgba(59, 139, 255, 0.1);
      border: 1px solid var(--blue);
      border-radius: 6px;
      cursor: pointer;
      color: var(--blue);
      display: grid;
      place-items: center;
      flex-shrink: 0;
      transition: var(--t);
      z-index: 100;
    }

    .sb-toggle:hover {
      background: rgba(59, 139, 255, 0.2);
      border-color: var(--blue);
      color: var(--text);
      transform: scale(1.05);
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
      background: linear-gradient(135deg, var(--blue), var(--purple));
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
      border-color: rgba(59, 139, 255, .28);
      background: rgba(59, 139, 255, .06)
    }

    .tb-admin-av {
      width: 28px;
      height: 28px;
      border-radius: 7px;
      background: linear-gradient(135deg, var(--blue), var(--purple));
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
      color: var(--blue);
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
          <h2>CyberShield</h2><span class="badge">Client Portal</span>
        </div>
        <button class="sb-toggle" onclick="toggleSidebar()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6" />
          </svg></button>
      </div>
      <div class="sb-section">
        <div class="sb-label">Navigation</div>
        <a class="sb-item active" href="index.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="7" height="7" rx="1.2" />
              <rect x="14" y="3" width="7" height="7" rx="1.2" />
              <rect x="3" y="14" width="7" height="7" rx="1.2" />
              <rect x="14" y="14" width="7" height="7" rx="1.2" />
            </svg></span><span class="sb-text">Dashboard</span></a>
        <a class="sb-item" id="nav-assessment" href="assessment.php"><span class="sb-icon"><svg width="15" height="15"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"
              stroke-linejoin="round">
              <path d="M9 11l3 3L22 4" />
              <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
            </svg></span><span class="sb-text">Take Assessment</span></a>
        <a class="sb-item" href="result.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="20" x2="18" y2="10" />
              <line x1="12" y1="20" x2="12" y2="4" />
              <line x1="6" y1="20" x2="6" y2="14" />
            </svg></span><span class="sb-text">Results</span></a>
        <a class="sb-item" href="review.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <path d="M8 6l4-4 4 4" />
              <path d="M12 2v13" />
              <path d="M20 21H4" />
              <path d="M17 12h3v9" />
              <path d="M4 12h3v9" />
            </svg></span><span class="sb-text">Review</span></a>
        <div class="sb-divider"></div>
        <div class="sb-label">Account</div>
        <a class="sb-item" href="profile.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
              <circle cx="12" cy="7" r="4" />
            </svg></span><span class="sb-text">Profile</span></a>
        <a class="sb-item" href="security-tips.php"><span class="sb-icon"><svg width="15" height="15"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"
              stroke-linejoin="round">
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg></span><span class="sb-text">Security Tips</span></a>
        <a class="sb-item" href="terms.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <polyline points="14 2 14 8 20 8" />
              <line x1="16" y1="13" x2="8" y2="13" />
              <line x1="16" y1="17" x2="8" y2="17" />
            </svg></span><span class="sb-text">Terms & Privacy</span></a>
      </div>
      <div class="sb-footer">
        <div class="sb-user">
          <div class="sb-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
          <div class="sb-user-info">
            <p><?php echo htmlspecialchars($user['full_name']); ?></p><span>Vendor Account</span>
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
          <p class="tb-sub">Your cybersecurity posture summary</p>
        </div>
        <div class="tb-right">
          <div class="tb-search-wrap">
            <span class="tb-search-icon"><svg width="12" height="12" viewBox="0 0 20 20" fill="none">
                <circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.7" />
                <path d="M15 15l3 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
              </svg></span>
            <input type="text" class="tb-search" placeholder="Search assessments, tips…" autocomplete="off" />
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
            <button class="tb-icon-btn" onclick="toggleNotif()" title="Notifications" style="position:relative">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
              </svg>
              <span class="notif-dot" id="notif-dot"></span>
            </button>

            <div class="np hidden" id="np">
              <div class="np-hdr"><span>Notifications</span><button onclick="clearNotifs()">Clear all</button></div>
              <div id="np-list">
                <p class="np-empty">No notifications</p>
              </div>
            </div>
          </div>
          <div class="tb-divider"></div>
          <a class="tb-admin" href="#">
            <div class="tb-admin-av"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
            <div class="tb-admin-info"><span
                class="tb-admin-name"><?php echo htmlspecialchars($user['full_name']); ?></span><span
                class="tb-admin-role">Vendor</span></div>
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
          <p>Your cybersecurity posture summary and recent activity.</p>
        </div>
        <div class="stats-row" id="stats-row">
          <div class="card stat-card" style="--accent:var(--blue)">
            <div class="si" style="background:rgba(59,139,255,.12);color:var(--blue)"><svg width="15" height="15"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"
                stroke-linejoin="round">
                <path d="M9 11l3 3L22 4" />
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
              </svg></div>
            <div class="slabel">Latest Score</div>
            <div class="sval" id="sv-score"><?php echo $stats['latest_score'] ?? '--'; ?></div>
            <div class="ssub">
              <?php echo $stats['latest_rank'] ? 'Rank ' . $stats['latest_rank'] : 'No assessments yet'; ?></div>
          </div>
          <div class="card stat-card" style="--accent:var(--teal)">
            <div class="si" style="background:rgba(0,212,170,.12);color:var(--teal)"><svg width="15" height="15"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"
                stroke-linejoin="round">
                <line x1="18" y1="20" x2="18" y2="10" />
                <line x1="12" y1="20" x2="12" y2="4" />
                <line x1="6" y1="20" x2="6" y2="14" />
              </svg></div>
            <div class="slabel">Risk Rank</div>
            <div class="sval" id="sv-rank"><?php echo $stats['latest_rank'] ?? '--'; ?></div>
            <div class="ssub"><?php
            if ($stats['latest_rank'] === 'A')
              echo 'Low Risk';
            elseif ($stats['latest_rank'] === 'B')
              echo 'Moderate Risk';
            elseif ($stats['latest_rank'] === 'C')
              echo 'High Risk';
            elseif ($stats['latest_rank'] === 'D')
              echo 'Critical Risk';
            else
              echo '--';
            ?></div>
          </div>
          <div class="card stat-card" style="--accent:var(--green)">
            <div class="si" style="background:rgba(0,255,148,.12);color:var(--green)"><svg width="15" height="15"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"
                stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
              </svg></div>
            <div class="slabel">Assessments</div>
            <div class="sval" id="sv-total"><?php echo $stats['total_assessments']; ?></div>
            <div class="ssub">Total completed</div>
          </div>
          <div class="card stat-card" style="--accent:var(--yellow)">
            <div class="si" style="background:rgba(255,214,10,.1);color:var(--yellow)"><svg width="15" height="15"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"
                stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
              </svg></div>
            <div class="slabel">Trend</div>
            <div class="sval" id="sv-trend"><?php
            if ($stats['total_assessments'] > 1)
              echo '↑ Improving';
            elseif ($stats['total_assessments'] == 1)
              echo 'First';
            else
              echo '--';
            ?></div>
            <div class="ssub"><?php
            if ($stats['total_assessments'] > 1)
              echo 'Keep it up!';
            elseif ($stats['total_assessments'] == 1)
              echo 'Great start!';
            else
              echo '--';
            ?></div>
          </div>
        </div>

        <div class="sec-hdr">
          <h2>Security Overview</h2>
          <p>Your cybersecurity dashboard summary.</p>
        </div>

        <div class="sec-hdr">
          <h2>Security Analytics</h2>
          <p>Your cybersecurity performance trends and detailed insights.</p>
        </div>
        
        <!-- Analytics Charts Grid -->
        <div class="charts-grid" style="grid-template-columns: 1fr 1fr; gap: 0.75rem;">
          <!-- Score Progress Chart -->
          <div class="card chart-card" style="padding: 0.75rem;">
            <h3 style="font-size: 0.6rem; margin-bottom: 0.5rem;">Score Progress</h3>
            <div class="cw sm" style="height: 140px;">
              <canvas id="scoreProgressChart"></canvas>
            </div>
          </div>
          
          <!-- Category Performance Chart -->
          <div class="card chart-card" style="padding: 0.75rem;">
            <h3 style="font-size: 0.6rem; margin-bottom: 0.5rem;">Security Categories</h3>
            <div class="cw sm" style="height: 140px;">
              <canvas id="categoryChart"></canvas>
            </div>
          </div>
          
          <!-- Performance Timeline -->
          <div class="card chart-card" style="padding: 0.75rem;">
            <h3 style="font-size: 0.6rem; margin-bottom: 0.5rem;">Performance Timeline</h3>
            <div class="cw sm" style="height: 140px;">
              <canvas id="timelineChart"></canvas>
            </div>
          </div>

          <!-- Risk Distribution Chart -->
          <div class="card chart-card" style="padding: 0.75rem;">
            <h3 style="font-size: 0.6rem; margin-bottom: 0.5rem;">Risk Distribution</h3>
            <div class="cw sm" style="height: 140px;">
              <canvas id="riskDistributionChart"></canvas>
            </div>
          </div>
        </div>

        <!-- Analytics Summary Cards -->
        <div class="stats-row" style="gap: 0.5rem;">
          <div class="card stat-card" style="--accent:var(--orange); padding: 0.75rem;">
            <div class="si" style="background:rgba(255,140,66,.12);color:var(--orange); width: 24px; height: 24px;">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
              </svg>
            </div>
            <div class="slabel">Avg Response</div>
            <div class="sval" style="font-size: 1.4rem;" id="avg-time">
              <?php 
              if (!empty($history)) {
                $total_time = array_sum(array_column($history, 'time_spent'));
                $avg_time = round($total_time / count($history) / 60, 1);
                echo $avg_time . 'm';
              } else {
                echo '--';
              }
              ?>
            </div>
            <div class="ssub">Time per assessment</div>
          </div>
          
          <div class="card stat-card" style="--accent:var(--teal); padding: 0.75rem;">
            <div class="si" style="background:rgba(0,212,170,.12);color:var(--teal); width: 24px; height: 24px;">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
              </svg>
            </div>
            <div class="slabel">Peak Score</div>
            <div class="sval" style="font-size: 1.4rem;" id="peak-score"><?php echo $stats['best_score'] ?? '--'; ?></div>
            <div class="ssub">Best performance</div>
          </div>

          <div class="card stat-card" style="--accent:var(--red); padding: 0.75rem;">
            <div class="si" style="background:rgba(255,59,92,.12);color:var(--red); width: 24px; height: 24px;">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 9v4"/>
                <path d="M12 17h.01"/>
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
              </svg>
            </div>
            <div class="slabel">Top Weakness</div>
            <div class="sval" style="font-size: 1.4rem;" id="top-weakness"><?php echo $topWeaknessLabel; ?></div>
            <div class="ssub">Lowest average category</div>
          </div>

        </div>

        <div class="sec-hdr">
          <h2>Assessment History</h2>
          <p>Your past security assessments and performance trends.</p>
        </div>
        <div class="card tbl-card">
          <div class="tbl-bar">
            <h3>Recent Assessments</h3>
            <div class="frow">
              <button class="btn btn-p btn-sm" onclick="startAssessment()">New Assessment</button>
            </div>
          </div>
          <div class="tw">
            <table>
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Score</th>
                  <th>Rank</th>
                  <th>Duration</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($history)): ?>
                  <tr>
                    <td colspan="5" style="text-align:center;color:var(--muted2);padding:2rem">No assessments taken yet.
                      Start one now!</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($history as $assessment): ?>
                    <tr>
                      <td><?php echo date('M j, Y', strtotime($assessment['created_at'])); ?></td>
                      <td>
                        <div class="sbw">
                          <div class="sbb">
                            <div class="sbf" style="width:<?php echo $assessment['score']; ?>%;background:<?php
                               echo $assessment['score'] >= 80 ? 'var(--green)' :
                                 ($assessment['score'] >= 60 ? 'var(--yellow)' :
                                   ($assessment['score'] >= 40 ? 'var(--orange)' : 'var(--red)')); ?>"></div>
                          </div>
                          <span class="sbn"><?php echo $assessment['score']; ?>%</span>
                        </div>
                      </td>
                      <td><span class="rank r<?php echo $assessment['rank']; ?>"><?php echo $assessment['rank']; ?></span>
                      </td>
                      <td><?php echo $assessment['duration'] ?? 'N/A'; ?></td>
                      <td><button class="btn btn-s btn-sm" disabled>View</button></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
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
    // Client-side functionality
    function isDark() { return document.documentElement.getAttribute('data-theme') === 'dark' }
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
      if (l) l.innerHTML = '<p class="np-empty">No notifications</p>';
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
    function closeModal() { document.getElementById('modal-overlay').classList.add('hidden') }

    function startAssessment() {
      window.location.href = 'assessment.php';
    }

    function showPage(page) {
      showToast(`Navigating to ${page}...`, 'blue');
      setTimeout(() => {
        window.location.href = `?page=${page}`;
      }, 500);
    }

    function doLogout() {
      const modal = document.getElementById('modal-overlay');
      const modalTitle = document.getElementById('modal-title');
      const modalBody = document.getElementById('modal-body');

      modalTitle.textContent = 'Confirm Logout';
      modalBody.innerHTML = `
    <div style="text-align: center; padding: 1rem;">
      <div style="font-size: 3rem; margin-bottom: 1rem; color: var(--red);">🚪</div>
      <h3 style="margin-bottom: 0.5rem; color: var(--text);">Are you sure you want to sign out?</h3>
      <p style="color: var(--muted2); margin-bottom: 1.5rem;">You will be redirected to the landing page.</p>
      <div style="display: flex; gap: 0.75rem; justify-content: center;">
        <button class="btn btn-s" onclick="closeModal()" style="padding: 0.5rem 1.5rem;">Cancel</button>
        <button class="btn btn-d" onclick="confirmLogout()" style="padding: 0.5rem 1.5rem;">Sign Out</button>
      </div>
    </div>
  `;

      modal.classList.remove('hidden');
    }

    function confirmLogout() {
      window.location.href = '../landingpage.php';
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

      // Initialize Analytics Charts
      initializeAnalyticsCharts();

      if (typeof pageInit === 'function') pageInit();
    });

    function initializeAnalyticsCharts() {
      const isDarkTheme = document.documentElement.getAttribute('data-theme') === 'dark';
      const textColor = isDarkTheme ? '#dde4f0' : '#0f172a';
      const gridColor = isDarkTheme ? 'rgba(59, 139, 255, 0.08)' : 'rgba(59, 139, 255, 0.12)';
      
      Chart.defaults.color = textColor;
      Chart.defaults.borderColor = gridColor;

      // Score Progress Chart
      const scoreCtx = document.getElementById('scoreProgressChart');
      if (scoreCtx) {
        new Chart(scoreCtx, {
          type: 'line',
          data: {
            labels: <?php echo json_encode(array_map(function($a) { return date('M j', strtotime($a['created_at'])); }, array_slice($history, 0, 10))); ?>,
            datasets: [{
              label: 'Score Progress',
              data: <?php echo json_encode(array_map(function($a) { return $a['score']; }, array_slice($history, 0, 10))); ?>,
              borderColor: getComputedStyle(document.documentElement).getPropertyValue('--blue'),
              backgroundColor: 'rgba(59, 139, 255, 0.1)',
              borderWidth: 3,
              fill: true,
              tension: 0.4,
              pointRadius: 5,
              pointBackgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--blue'),
              pointBorderColor: '#fff',
              pointBorderWidth: 2,
              pointHoverRadius: 7
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false },
              tooltip: {
                backgroundColor: isDarkTheme ? '#0d1421' : '#fff',
                titleColor: textColor,
                bodyColor: textColor,
                borderColor: gridColor,
                borderWidth: 1,
                padding: 12,
                displayColors: false,
                callbacks: {
                  label: function(context) {
                    return `Score: ${context.parsed.y}%`;
                  }
                }
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                max: 100,
                grid: { color: gridColor },
                ticks: { color: textColor, callback: value => value + '%' }
              },
              x: {
                grid: { display: false },
                ticks: { color: textColor }
              }
            }
          }
        });
      }

      // Category Performance Chart
      const categoryCtx = document.getElementById('categoryChart');
      if (categoryCtx) {
        const latestAssessment = <?php echo json_encode($latest_assessment); ?>;
        const categoryData = [
          latestAssessment.password_score || 0,
          latestAssessment.phishing_score || 0,
          latestAssessment.device_score || 0,
          latestAssessment.network_score || 0,
          latestAssessment.social_engineering_score || 0,
          latestAssessment.data_handling_score || 0
        ];
        
        new Chart(categoryCtx, {
          type: 'pie',
          data: {
            labels: ['Password Security', 'Phishing Detection', 'Device Security', 'Network Security', 'Social Engineering', 'Data Handling'],
            datasets: [{
              data: categoryData,
              backgroundColor: [
                'rgba(59, 139, 255, 0.8)',  // Blue
                'rgba(16, 217, 130, 0.8)',  // Green
                'rgba(245, 183, 49, 0.8)',  // Yellow
                'rgba(255, 140, 66, 0.8)',  // Orange
                'rgba(123, 114, 240, 0.8)', // Purple
                'rgba(0, 212, 170, 0.8)'    // Teal
              ],
              borderColor: [
                'rgba(59, 139, 255, 1)',
                'rgba(16, 217, 130, 1)',
                'rgba(245, 183, 49, 1)',
                'rgba(255, 140, 66, 1)',
                'rgba(123, 114, 240, 1)',
                'rgba(0, 212, 170, 1)'
              ],
              borderWidth: 2
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                position: 'right',
                labels: { 
                  color: textColor, 
                  padding: 15, 
                  font: { size: 11 },
                  usePointStyle: true,
                  pointStyle: 'circle'
                }
              },
              tooltip: {
                callbacks: {
                  label: function(context) {
                    return `${context.label}: ${context.parsed}%`;
                  }
                }
              }
            }
          }
        });
      }

      // Performance Timeline Chart
      const timelineCtx = document.getElementById('timelineChart');
      if (timelineCtx) {
        new Chart(timelineCtx, {
          type: 'bar',
          data: {
            labels: <?php echo json_encode(array_map(function($a) { return date('M j', strtotime($a['created_at'])); }, array_slice($history, 0, 8))); ?>,
            datasets: [{
              label: 'Assessment Score',
              data: <?php echo json_encode(array_map(function($a) { return $a['score']; }, array_slice($history, 0, 8))); ?>,
              backgroundColor: function(context) {
                const value = context.parsed.y;
                if (value >= 80) return 'rgba(16, 217, 130, 0.8)';
                if (value >= 60) return 'rgba(245, 183, 49, 0.8)';
                if (value >= 40) return 'rgba(255, 140, 66, 0.8)';
                return 'rgba(255, 59, 92, 0.8)';
              },
              borderColor: function(context) {
                const value = context.parsed.y;
                if (value >= 80) return 'rgba(16, 217, 130, 1)';
                if (value >= 60) return 'rgba(245, 183, 49, 1)';
                if (value >= 40) return 'rgba(255, 140, 66, 1)';
                return 'rgba(255, 59, 92, 1)';
              },
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
                  label: function(context) {
                    return `Score: ${context.parsed.y}%`;
                  }
                }
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                max: 100,
                grid: { color: gridColor },
                ticks: { color: textColor, callback: value => value + '%' }
              },
              x: {
                grid: { display: false },
                ticks: { color: textColor }
              }
            }
          }
        });
      }

      // Risk distribution chart
      const riskCtx = document.getElementById('riskDistributionChart');
      if (riskCtx) {
        const riskData = <?php echo json_encode(array_values($riskDistribution)); ?>;
        new Chart(riskCtx, {
          type: 'doughnut',
          data: {
            labels: ['Low', 'Moderate', 'High', 'Critical'],
            datasets: [{
              data: riskData,
              backgroundColor: [
                'rgba(16, 217, 130, 0.8)',
                'rgba(245, 183, 49, 0.8)',
                'rgba(255, 140, 66, 0.8)',
                'rgba(255, 59, 92, 0.8)'
              ],
              borderColor: isDarkTheme ? '#0d1421' : '#ffffff',
              borderWidth: 2
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                position: 'bottom',
                labels: { color: textColor, boxWidth: 10, font: { size: 10 } }
              }
            },
            cutout: '62%'
          }
        });
      }

      // Category gap to target chart
      const comparisonCtx = document.getElementById('comparisonChart');
      if (comparisonCtx) {
        const categoryAverageScores = <?php echo json_encode(array_values($categoryAverages)); ?>;
        const targetScore = 80;
        const gapData = categoryAverageScores.map(score => Math.max(0, targetScore - score));

        new Chart(comparisonCtx, {
          type: 'bar',
          data: {
            labels: ['Password', 'Phishing', 'Device', 'Network', 'Social', 'Data'],
            datasets: [{
              label: 'Gap to Target',
              data: gapData,
              backgroundColor: 'rgba(255, 59, 92, 0.7)',
              borderColor: 'rgba(255, 59, 92, 1)',
              borderWidth: 1.5,
              borderRadius: 4
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
              y: {
                beginAtZero: true,
                max: 80,
                grid: { color: gridColor },
                ticks: { color: textColor, callback: value => value + '%' }
              },
              x: {
                grid: { display: false },
                ticks: { color: textColor, maxRotation: 0, autoSkip: false }
              }
            }
          }
        });
      }
    }
  </script>
</body>

</html>