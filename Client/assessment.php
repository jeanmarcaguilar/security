<?php
// ============================================================
//  CyberShield — assessment.php
//  PHP + MySQL backend integration
// ============================================================
session_start();

// ── Database configuration ────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');          // ← change to your MySQL username
define('DB_PASS', '');              // ← change to your MySQL password
define('DB_NAME', 'cybershield');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ── Helper: JSON response ─────────────────────────────────
function jsonOut(array $data): void {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// ── Log activity ──────────────────────────────────────────
function logActivity(int $userId, string $type, string $desc): void {
    try {
        $db  = getDB();
        $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $sql = 'INSERT INTO activity_log (user_id, action_type, action_description, ip_address, user_agent)
                VALUES (:uid, :type, :desc, :ip, :ua)';
        $db->prepare($sql)->execute([':uid'=>$userId,':type'=>$type,':desc'=>$desc,':ip'=>$ip,':ua'=>$ua]);
    } catch (Exception $e) { /* silent */ }
}

// ══════════════════════════════════════════════════════════
//  AJAX / API ROUTING  (?action=…)
// ══════════════════════════════════════════════════════════
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action) {
    header('Content-Type: application/json');

    // ── Require auth for everything below ─────────────────
    if (!isset($_SESSION['user_id'])) {
        jsonOut(['success'=>false,'error'=>'Not authenticated.']);
    }
    $uid = (int)$_SESSION['user_id'];

    // ── SAVE ASSESSMENT ───────────────────────────────────
    if ($action === 'save_assessment') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) $data = $_POST;

        $vendorId      = (int)($data['vendor_id'] ?? 0);
        $score         = (float)($data['score'] ?? 0);
        $rank          = $data['rank'] ?? 'D';
        $passScore     = (float)($data['password_score'] ?? 0);
        $phishScore    = (float)($data['phishing_score'] ?? 0);
        $devScore      = (float)($data['device_score'] ?? 0);
        $netScore      = (float)($data['network_score'] ?? 0);
        $notes         = $data['assessment_notes'] ?? '';

        if (!$vendorId) {
            // Auto-link to a vendor via user's email / create a stub
            $db   = getDB();
            $stmt = $db->prepare('SELECT id FROM vendors WHERE email = :e LIMIT 1');
            $stmt->execute([':e' => $_SESSION['email']]);
            $v = $stmt->fetch();
            if ($v) {
                $vendorId = (int)$v['id'];
            } else {
                // Create vendor stub for this user
                $ins = $db->prepare('INSERT INTO vendors (name, email, industry, contact_person) VALUES (:n,:e,:i,:c)');
                $ins->execute([
                    ':n' => $_SESSION['store_name'],
                    ':e' => $_SESSION['email'],
                    ':i' => 'General',
                    ':c' => $_SESSION['full_name'],
                ]);
                $vendorId = (int)$db->lastInsertId();
            }
        } else {
            $db = getDB();
        }

        $allowed = ['A','B','C','D'];
        if (!in_array($rank, $allowed, true)) $rank = 'D';

        $stmt = $db->prepare('
            INSERT INTO vendor_assessments
                (vendor_id, score, rank, password_score, phishing_score, device_score, network_score, assessment_notes, assessed_by)
            VALUES
                (:vid,:sc,:rk,:ps,:ph,:ds,:ns,:nt,:uid)');
        $stmt->execute([
            ':vid'=>$vendorId, ':sc'=>$score, ':rk'=>$rank,
            ':ps'=>$passScore, ':ph'=>$phishScore,
            ':ds'=>$devScore,  ':ns'=>$netScore,
            ':nt'=>$notes,     ':uid'=>$uid,
        ]);

        logActivity($uid, 'assessment', "Completed assessment — Score: {$score}, Rank: {$rank}");
        jsonOut(['success'=>true,'assessment_id'=>(int)$db->lastInsertId()]);
    }

    // Unknown action
    jsonOut(['success'=>false,'error'=>'Unknown action.']);
}

// ══════════════════════════════════════════════════════════
//  NOT AN AJAX REQUEST — serve the HTML page
// ══════════════════════════════════════════════════════════

// Guard: redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Get user data
$user_query = "SELECT * FROM users WHERE id = :user_id";
$stmt = getDB()->prepare($user_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fallback in case user row is not found
if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Assessment questions database
$questions = [
    ['id' => 1, 'category' => 'password', 'text' => 'Do you use a password manager to store and generate strong passwords?', 'options' => ['Yes, always' => 100, 'Sometimes' => 50, 'No, I remember them' => 25, 'I use the same password everywhere' => 0]],
    ['id' => 2, 'category' => 'password', 'text' => 'How often do you change your passwords?', 'options' => ['Every 30 days' => 100, 'Every 90 days' => 75, 'Every 6 months' => 50, 'Only when forced' => 25, 'Never' => 0]],
    ['id' => 3, 'category' => 'password', 'text' => 'Do you use multi-factor authentication (MFA) on your important accounts?', 'options' => ['Yes, on all accounts' => 100, 'On most accounts' => 75, 'On a few accounts' => 50, 'No' => 0]],
    ['id' => 4, 'category' => 'phishing', 'text' => 'How do you verify suspicious emails asking for credentials?', 'options' => ['Contact sender through known channel' => 100, 'Check email headers' => 75, 'Look for spelling errors' => 50, 'Click links to verify' => 25, 'I always trust emails' => 0]],
    ['id' => 5, 'category' => 'phishing', 'text' => 'Have you completed security awareness training in the past year?', 'options' => ['Yes, with certification' => 100, 'Yes, online course' => 75, 'Only watched videos' => 50, 'No training' => 0]],
    ['id' => 6, 'category' => 'phishing', 'text' => 'What do you do when you receive an unexpected attachment?', 'options' => ['Verify with sender before opening' => 100, 'Scan with antivirus' => 75, 'Open if it looks legitimate' => 25, 'Always open' => 0]],
    ['id' => 7, 'category' => 'device', 'text' => 'Is your device protected with antivirus/anti-malware software?', 'options' => ['Yes, always updated' => 100, 'Yes, but not always updated' => 50, 'No antivirus' => 0]],
    ['id' => 8, 'category' => 'device', 'text' => 'Do you lock your device when away from it?', 'options' => ['Always immediately' => 100, 'Sometimes' => 50, 'Never' => 0]],
    ['id' => 9, 'category' => 'device', 'text' => 'How often do you update your operating system and applications?', 'options' => ['Automatically updated' => 100, 'Weekly manual checks' => 75, 'Monthly' => 50, 'When reminded' => 25, 'Never' => 0]],
    ['id' => 10, 'category' => 'network', 'text' => 'Do you use a VPN when connecting to public Wi-Fi?', 'options' => ['Always' => 100, 'Sometimes' => 50, 'Never' => 0]],
    ['id' => 11, 'category' => 'network', 'text' => 'Is your home Wi-Fi secured with WPA2/WPA3 encryption?', 'options' => ['Yes, with strong password' => 100, 'Yes, default password' => 50, 'No encryption' => 0, 'Not sure' => 25]],
    ['id' => 12, 'category' => 'network', 'text' => 'Do you have a firewall enabled on your network/devices?', 'options' => ['Yes, hardware and software' => 100, 'Software only' => 75, 'Hardware only' => 50, 'No firewall' => 0]]
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CyberShield — Security Assessment</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<link rel="stylesheet" href="style.css"/>

<!-- ── PHP-Powered API Bridge ── -->
<script>
// All API calls route through index.php (auth) and assessment.php (assessment save)
const API = {
    base: 'index.php',
    assessBase: 'assessment.php',

    async post(action, data = {}, base) {
        const b = base || API.base;
        const res = await fetch(`${b}?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
            credentials: 'same-origin'
        });
        return res.json();
    },

    async get(action, params = {}, base) {
        const b = base || API.base;
        const qs = new URLSearchParams({ action, ...params });
        const res = await fetch(`${b}?${qs}`, { credentials: 'same-origin' });
        return res.json();
    },

    // ── Auth (all go through index.php) ──────────────────
    async logout()       { return API.post('logout'); },
    async checkSession() { return API.get('check_session'); },

    // ── Assessment ────────────────────────────────────────
    async saveAssessment(data) { return API.post('save_assessment', data, API.assessBase); },

    // ── Activity log ─────────────────────────────────────
    async logActivity(type, desc) {
        return API.post('log_activity', { action_type: type, action_description: desc });
    }
};

// ── Auth helpers (matching index.php pattern) ────────────
async function checkSession() {
    const r = await API.checkSession();
    if (r.loggedIn) {
        window._phpUser = r.user;
        return true;
    }
    window.location.href = 'index.php';
    return false;
}

async function doLogout() {
    await API.logout();
    window.location.href = 'index.php';
}

// ── Bridge: persist assessments to DB ────────────────────
async function saveAssessmentToDB(payload) {
    return API.saveAssessment(payload);
}

// ── Show / hide app helpers (mirrors index.php) ──────────
function showLoginScreen() {
    window.location.href = 'index.php';
}
function hideLoginScreen() {
    document.getElementById('app').classList.remove('hidden');
}
</script>
    <style>
        .assessment-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .question-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-2);
            transition: all 0.3s ease;
        }
        .question-number {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        .question-text {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            line-height: 1.4;
        }
        .category-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .category-password { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        .category-phishing { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }
        .category-device { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .category-network { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        
        .options-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .option-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            border-radius: 12px;
            background: var(--navy-3);
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
        }
        .option-item:hover {
            background: var(--navy-2);
            transform: translateX(4px);
        }
        .option-item.selected {
            border-color: var(--primary);
            background: rgba(59, 130, 246, 0.1);
        }
        .option-radio {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--text-3);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .option-item.selected .option-radio {
            border-color: var(--primary);
        }
        .option-item.selected .option-radio::after {
            content: '';
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary);
        }
        .option-text {
            flex: 1;
            font-size: 0.9rem;
        }
        .option-score {
            font-size: 0.75rem;
            color: var(--text-3);
        }
        
        .progress-section {
            position: sticky;
            top: 20px;
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-2);
        }
        .progress-bar-container {
            background: var(--navy-3);
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin: 0.5rem 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), #3b82f6);
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        .nav-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }
        .timer {
            font-family: monospace;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
        }
        .warning-timer {
            color: #ef4444;
            animation: pulse 1s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .results-preview {
            background: var(--navy-3);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
<div class="bg-grid"></div>
<div id="app" class="hidden">

  <!-- SIDEBAR -->
  <aside id="sidebar" class="sidebar">
    <div class="sidebar-logo">
      <div class="shield-icon">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <span class="sidebar-brand">CyberShield</span>
    </div>

    <nav class="sidebar-nav">
      <p class="sidebar-section-label">Main Menu</p>
      <a class="sidebar-item" id="nav-dashboard" href="index.php">
        <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.2"/><rect x="14" y="3" width="7" height="7" rx="1.2"/><rect x="3" y="14" width="7" height="7" rx="1.2"/><rect x="14" y="14" width="7" height="7" rx="1.2"/></svg></span>
        <span class="sidebar-label">Dashboard</span>
        <span class="sidebar-tooltip">Dashboard</span>
      </a>
      <a class="sidebar-item active" id="nav-assessment" href="assessment.php">
        <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
        <span class="sidebar-label">Take Assessment</span>
        <span class="sidebar-tooltip">Assessment</span>
      </a>
      <a class="sidebar-item" id="nav-results" href="result.php">
        <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>
        <span class="sidebar-label">My Results</span>
        <span class="sidebar-tooltip">Results</span>
      </a>
      <a class="sidebar-item" id="nav-leaderboard" href="leaderboard.php">
        <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6l4-4 4 4"/><path d="M12 2v13"/><path d="M20 21H4"/><path d="M17 12h3v9"/><path d="M4 12h3v9"/></svg></span>
        <span class="sidebar-label">Leaderboard</span>
        <span class="sidebar-tooltip">Leaderboard</span>
      </a>
      <p class="sidebar-section-label" style="margin-top:1.25rem;">Seller Hub</p>
      <a class="sidebar-item" id="nav-seller-store" href="seller-store.php">
        <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg></span>
        <span class="sidebar-label">My Store</span>
        <span class="sidebar-tooltip">My Store</span>
      </a>
      <a class="sidebar-item" id="nav-seller-analytics" href="seller-analytics.php">
        <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><polyline points="2 20 22 20"/></svg></span>
        <span class="sidebar-label">Analytics</span>
        <span class="sidebar-tooltip">Analytics</span>
      </a>
      <p class="sidebar-section-label" style="margin-top:1.25rem;">Account</p>
      <a class="sidebar-item" id="nav-profile" href="profile.php">
        <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
        <span class="sidebar-label">My Profile</span>
        <span class="sidebar-tooltip">Profile</span>
      </a>
      <a class="sidebar-item" id="nav-tips" href="security-tips.php">
        <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span>
        <span class="sidebar-label">Security Tips</span>
        <span class="sidebar-tooltip">Tips</span>
      </a>
      <a class="sidebar-item" id="nav-terms" href="terms.php">
        <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>
        <span class="sidebar-label">Terms &amp; Privacy</span>
        <span class="sidebar-tooltip">Terms</span>
      </a>
    </nav>

    <div class="sidebar-bottom">
      <div class="sidebar-user-card" onclick="window.location.href='profile.php'" title="View profile">
        <div class="sidebar-avatar" id="sidebar-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
        <div class="sidebar-user-info">
          <div class="sidebar-user-name" id="sidebar-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
          <div class="sidebar-user-role">Vendor Account</div>
        </div>
        <svg class="sidebar-chevron" width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </div>
      <button class="sidebar-signout-btn" onclick="doLogout()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span>Sign Out</span>
      </button>
    </div>
  </aside>

  <button id="sidebar-toggle" class="sidebar-toggle" onclick="toggleSidebar()" title="Toggle sidebar">
    <span id="sidebar-toggle-icon"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></span>
  </button>

  <button id="mobile-menu-btn" class="mobile-menu-btn" onclick="toggleMobileSidebar()">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    Menu
  </button>

  <!-- MAIN CONTENT -->
  <div id="main-content" class="main-content">

    <!-- TOPBAR -->
    <header id="topbar" class="topbar">
      <div class="topbar-left">
        <div class="topbar-breadcrumb">
          <span class="topbar-app-name">CyberShield</span>
          <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:var(--muted)"><path d="M6 4l4 4-4 4"/></svg>
          <span id="topbar-page-title">Security Assessment</span>
        </div>
      </div>
      <div class="topbar-right">
        <div class="topbar-search">
          <svg width="13" height="13" viewBox="0 0 20 20" fill="none"><circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.7"/><path d="M15 15l3 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
          <input type="text" id="global-search" placeholder="Search…" oninput="handleGlobalSearch(this.value)" autocomplete="off"/>
        </div>
        <div class="topbar-divider"></div>
        <button class="topbar-ctrl-btn" id="lang-btn" onclick="cycleLang()" title="Change language">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
          <span id="lang-label">EN</span>
        </button>
        <button class="topbar-ctrl-btn" id="a11y-btn" onclick="toggleAccessibility()" title="Toggle large text">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="2"/><path d="M9 11h6M12 11v9"/><path d="M7.5 16h2M14.5 16h2"/></svg>
        </button>
        <button class="topbar-ctrl-btn" id="theme-toggle" onclick="toggleTheme()" title="Toggle theme">
          <svg id="theme-icon-moon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
          <svg id="theme-icon-sun" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:none"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>
        </button>
        <div class="topbar-divider"></div>
        <div class="notif-wrap">
          <button class="topbar-ctrl-btn notif-btn" id="notif-btn" onclick="toggleNotifPanel()" title="Notifications">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="notif-dot hidden" id="notif-dot"></span>
          </button>
          <div class="notif-panel hidden" id="notif-panel">
            <div class="notif-header"><span>Notifications</span><button onclick="clearNotifs()">Clear all</button></div>
            <div id="notif-list"><p class="notif-empty">No notifications</p></div>
          </div>
        </div>
        <div class="topbar-user" onclick="window.location.href='profile.php'" title="My Profile">
          <div class="topbar-avatar" id="nav-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
          <div class="topbar-user-info">
            <span class="topbar-user-name" id="nav-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
            <span class="topbar-user-role">Vendor</span>
          </div>
          <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6l4 4 4-4"/></svg>
        </div>
      </div>
    </header>

    <!-- ASSESSMENT PAGE -->
    <div id="page-assessment" class="page">
      <div class="page-inner fade-in">
        <div class="page-header">
          <div>
            <h2 class="page-title">Security Assessment</h2>
            <p class="page-subtitle">Evaluate your cybersecurity hygiene across key domains</p>
          </div>
        </div>
        <div class="assessment-container">
          <!-- Progress Section -->
          <div class="progress-section">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <div>
                <strong>Question <span id="current-q-num">1</span> of <span id="total-q"><?php echo count($questions); ?></span></strong>
                <div class="progress-bar-container">
                  <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
                </div>
              </div>
              <div class="timer" id="timer">00:30</div>
            </div>
          </div>
          
          <!-- Questions Container -->
          <div id="questions-container"></div>
          
          <!-- Navigation Buttons -->
          <div class="nav-buttons">
            <button class="btn btn-secondary" id="prev-btn" onclick="prevQuestion()" disabled>← Previous</button>
            <button class="btn btn-primary" id="next-btn" onclick="nextQuestion()">Next →</button>
            <button class="btn btn-success" id="submit-btn" onclick="submitAssessment()" style="display: none;">Submit Assessment</button>
          </div>
          
          <!-- Live Score Preview -->
          <div class="results-preview" id="score-preview" style="display: none;">
            <h4>Current Score Preview</h4>
            <div style="font-size: 2rem; font-weight: 700; color: var(--primary);" id="preview-score">0%</div>
            <div id="preview-rank"></div>
          </div>
        </div>
      </div>
    </div>

  </div><!-- end main-content -->
</div><!-- end app -->

<!-- MODAL OVERLAY -->
<div id="modal-overlay" class="modal-overlay hidden" onclick="closeModal(event)">
  <div class="modal modal-sm" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h3>Confirm Submission</h3>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div id="modal-body">
      <p>Are you sure you want to submit this assessment?</p>
      <p style="font-size: 0.85rem; color: var(--text-3);">You cannot change your answers after submission.</p>
      <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
        <button class="btn btn-primary" onclick="confirmSubmit()">Yes, Submit</button>
        <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- SEARCH OVERLAY -->
<div id="search-overlay" class="search-overlay hidden" onclick="closeSearchOverlay(event)">
  <div class="search-results-panel">
    <div class="search-results-header"><span>Search Results</span><button onclick="closeSearchOverlay()"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
    <div id="search-results-list"></div>
  </div>
</div>

<div id="print-area" class="hidden"></div>

<script src="script.js"></script>
<script src="sidebar.js"></script>

<!-- ── DB-aware session check & user data population ── -->
<script>
// Boot: verify PHP session, then reveal app or redirect to login
(async () => {
    try {
        const r = await API.checkSession();
        if (r.loggedIn) {
            window._phpUser = r.user;
            const u = r.user;
            const initial = (u.full_name || u.username || 'U')[0].toUpperCase();

            // Update sidebar user info
            if (document.getElementById('sidebar-avatar')) document.getElementById('sidebar-avatar').textContent = initial;
            if (document.getElementById('sidebar-name'))   document.getElementById('sidebar-name').textContent   = u.full_name || u.username;

            // Update topbar user info
            if (document.getElementById('nav-avatar')) document.getElementById('nav-avatar').textContent = initial;
            if (document.getElementById('nav-name'))   document.getElementById('nav-name').textContent   = u.full_name || u.username;

            // Reveal app
            document.getElementById('app').classList.remove('hidden');
        } else {
            window.location.href = 'index.php';
        }
    } catch (error) {
        console.error('Session check failed:', error);
        window.location.href = 'index.php';
    }
})();
</script>
    
    <script>
        const questions = <?php echo json_encode($questions); ?>;
        let userAnswers = new Array(questions.length).fill(null);
        let currentQuestion = 0;
        let timerInterval = null;
        let timeLeft = 30;
        let timerEnabled = true;
        
        function getCategoryClass(category) {
            return `category-${category}`;
        }
        
        function renderQuestion(index) {
            const q = questions[index];
            const selectedValue = userAnswers[index];
            
            let optionsHtml = '';
            for (const [text, score] of Object.entries(q.options)) {
                const isSelected = selectedValue === score;
                optionsHtml += `
                    <div class="option-item ${isSelected ? 'selected' : ''}" onclick="selectAnswer(${index}, ${score}, '${text.replace(/'/g, "\\'")}')">
                        <div class="option-radio"></div>
                        <div class="option-text">${escapeHtml(text)}</div>
                        <div class="option-score">${score}%</div>
                    </div>
                `;
            }
            
            const html = `
                <div class="question-card">
                    <div class="category-badge ${getCategoryClass(q.category)}">${q.category.toUpperCase()}</div>
                    <div class="question-number">Question ${index + 1} of ${questions.length}</div>
                    <div class="question-text">${escapeHtml(q.text)}</div>
                    <div class="options-list">
                        ${optionsHtml}
                    </div>
                </div>
            `;
            
            document.getElementById('questions-container').innerHTML = html;
            document.getElementById('current-q-num').textContent = index + 1;
            
            // Update progress bar
            const progress = ((index + 1) / questions.length) * 100;
            document.getElementById('progress-fill').style.width = `${progress}%`;
            
            // Update navigation buttons
            document.getElementById('prev-btn').disabled = index === 0;
            
            if (index === questions.length - 1) {
                document.getElementById('next-btn').style.display = 'none';
                document.getElementById('submit-btn').style.display = 'inline-flex';
            } else {
                document.getElementById('next-btn').style.display = 'inline-flex';
                document.getElementById('submit-btn').style.display = 'none';
            }
            
            // Reset and start timer
            resetTimer();
            if (timerEnabled && !userAnswers[index]) {
                startTimer();
            }
        }
        
        function selectAnswer(questionIndex, score, text) {
            userAnswers[questionIndex] = score;
            renderQuestion(currentQuestion);
            
            // Auto-advance if timer is enabled and answer selected
            if (timerEnabled && currentQuestion < questions.length - 1) {
                setTimeout(() => nextQuestion(), 300);
            }
            
            updateScorePreview();
        }
        
        function nextQuestion() {
            if (currentQuestion < questions.length - 1) {
                currentQuestion++;
                renderQuestion(currentQuestion);
            }
        }
        
        function prevQuestion() {
            if (currentQuestion > 0) {
                currentQuestion--;
                renderQuestion(currentQuestion);
            }
        }
        
        function resetTimer() {
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
            timeLeft = 30;
            updateTimerDisplay();
        }
        
        function startTimer() {
            if (timerInterval) clearInterval(timerInterval);
            timerInterval = setInterval(() => {
                if (timeLeft <= 1) {
                    clearInterval(timerInterval);
                    timerInterval = null;
                    // Auto-select default answer (first option) if none selected
                    if (userAnswers[currentQuestion] === null) {
                        const firstOption = Object.values(questions[currentQuestion].options)[0];
                        selectAnswer(currentQuestion, firstOption, Object.keys(questions[currentQuestion].options)[0]);
                    }
                } else {
                    timeLeft--;
                    updateTimerDisplay();
                }
            }, 1000);
        }
        
        function updateTimerDisplay() {
            const timerEl = document.getElementById('timer');
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerEl.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 10) {
                timerEl.classList.add('warning-timer');
            } else {
                timerEl.classList.remove('warning-timer');
            }
        }
        
        function calculateScore() {
            let totalScore = 0;
            let answeredCount = 0;
            
            for (let i = 0; i < userAnswers.length; i++) {
                if (userAnswers[i] !== null) {
                    totalScore += userAnswers[i];
                    answeredCount++;
                }
            }
            
            if (answeredCount === 0) return 0;
            return Math.round(totalScore / answeredCount);
        }
        
        function calculateCategoryScores() {
            const categories = {
                password: { total: 0, count: 0 },
                phishing: { total: 0, count: 0 },
                device: { total: 0, count: 0 },
                network: { total: 0, count: 0 }
            };
            
            for (let i = 0; i < questions.length; i++) {
                const q = questions[i];
                const answer = userAnswers[i];
                if (answer !== null) {
                    categories[q.category].total += answer;
                    categories[q.category].count++;
                }
            }
            
            return {
                password: categories.password.count ? Math.round(categories.password.total / categories.password.count) : 0,
                phishing: categories.phishing.count ? Math.round(categories.phishing.total / categories.phishing.count) : 0,
                device: categories.device.count ? Math.round(categories.device.total / categories.device.count) : 0,
                network: categories.network.count ? Math.round(categories.network.total / categories.network.count) : 0
            };
        }
        
        function getRank(score) {
            if (score >= 80) return { letter: 'A', text: 'Low Risk - Excellent security practices', color: '#10b981' };
            if (score >= 60) return { letter: 'B', text: 'Moderate Risk - Good foundation, room for improvement', color: '#3b82f6' };
            if (score >= 40) return { letter: 'C', text: 'High Risk - Significant improvements needed', color: '#f59e0b' };
            return { letter: 'D', text: 'Critical Risk - Immediate action required', color: '#ef4444' };
        }
        
        function updateScorePreview() {
            const score = calculateScore();
            const rank = getRank(score);
            const preview = document.getElementById('score-preview');
            const previewScore = document.getElementById('preview-score');
            const previewRank = document.getElementById('preview-rank');
            
            if (userAnswers.some(a => a !== null)) {
                preview.style.display = 'block';
                previewScore.textContent = `${score}%`;
                previewRank.innerHTML = `<span class="rank-badge rank-${rank.letter.toLowerCase()}">${rank.letter}</span> - ${rank.text}`;
            }
        }
        
        function submitAssessment() {
            // Check if all questions are answered
            const unanswered = userAnswers.filter(a => a === null).length;
            if (unanswered > 0) {
                alert(`Please answer all questions. ${unanswered} question(s) remaining.`);
                // Jump to first unanswered question
                const firstUnanswered = userAnswers.findIndex(a => a === null);
                if (firstUnanswered !== -1) {
                    currentQuestion = firstUnanswered;
                    renderQuestion(currentQuestion);
                }
                return;
            }
            
            document.getElementById('modal-overlay').classList.remove('hidden');
        }
        
        async function confirmSubmit() {
            closeModal();
            
            const score = calculateScore();
            const rank = getRank(score);
            const categoryScores = calculateCategoryScores();
            
            // Save to database via API
            const assessmentData = {
                vendor_id: 0, // Will be auto-linked
                score: score,
                rank: rank.letter,
                password_score: categoryScores.password,
                phishing_score: categoryScores.phishing,
                device_score: categoryScores.device,
                network_score: categoryScores.network,
                assessment_notes: `Completed on ${new Date().toLocaleString()}`
            };
            
            try {
                const result = await API.saveAssessment(assessmentData);
                
                if (result.success) {
                    // Store results in localStorage for results page
                    localStorage.setItem('lastAssessment', JSON.stringify({
                        score: score,
                        rank: rank.letter,
                        categoryScores: categoryScores,
                        answers: userAnswers,
                        date: new Date().toISOString()
                    }));
                    
                    // Redirect to results page
                    window.location.href = 'result.php';
                } else {
                    alert('Error saving assessment: ' + (result.error || result.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error saving assessment. Please try again.');
            }
        }
        
        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
        
        function closeModal(event) {
            if (event && event.target !== event.currentTarget) return;
            document.getElementById('modal-overlay').classList.add('hidden');
        }
        
        // Initialize on load
        document.addEventListener('DOMContentLoaded', () => {
            renderQuestion(0);
        });
    </script>
</body>
</html>