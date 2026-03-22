<?php
// ============================================================
//  CyberShield — index.php
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

    // ── LOGIN ─────────────────────────────────────────────
    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$username || !$password) {
            jsonOut(['success'=>false,'error'=>'Username and password are required.']);
        }

        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = :u AND is_active = 1 LIMIT 1');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            jsonOut(['success'=>false,'error'=>'Invalid username or password.']);
        }

        // Start session
        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['full_name']  = $user['full_name'];
        $_SESSION['email']      = $user['email'];
        $_SESSION['store_name'] = $user['store_name'];
        $_SESSION['role']       = $user['role'];

        logActivity($user['id'], 'login', $user['full_name'] . ' logged in.');

        jsonOut([
            'success'    => true,
            'user' => [
                'id'         => $user['id'],
                'username'   => $user['username'],
                'full_name'  => $user['full_name'],
                'email'      => $user['email'],
                'store_name' => $user['store_name'],
                'role'       => $user['role'],
            ]
        ]);
    }

    // ── LOGOUT ────────────────────────────────────────────
    if ($action === 'logout') {
        if (isset($_SESSION['user_id'])) {
            logActivity($_SESSION['user_id'], 'logout', ($_SESSION['full_name'] ?? 'User') . ' logged out.');
        }
        session_destroy();
        jsonOut(['success' => true]);
    }

    // ── CHECK SESSION ─────────────────────────────────────
    if ($action === 'check_session') {
        if (!isset($_SESSION['user_id'])) {
            jsonOut(['loggedIn' => false]);
        }
        jsonOut([
            'loggedIn' => true,
            'user' => [
                'id'         => $_SESSION['user_id'],
                'username'   => $_SESSION['username'],
                'full_name'  => $_SESSION['full_name'],
                'email'      => $_SESSION['email'],
                'store_name' => $_SESSION['store_name'],
                'role'       => $_SESSION['role'],
            ]
        ]);
    }

    // ── Require auth for everything below ─────────────────
    if (!isset($_SESSION['user_id'])) {
        jsonOut(['success'=>false,'error'=>'Not authenticated.']);
    }
    $uid = (int)$_SESSION['user_id'];

    // ── GET VENDORS ───────────────────────────────────────
    if ($action === 'get_vendors') {
        $db   = getDB();
        $rows = $db->query('SELECT * FROM vendor_latest_assessments ORDER BY score DESC')->fetchAll();
        jsonOut(['success'=>true,'vendors'=>$rows]);
    }

    // ── GET ASSESSMENTS for current user's vendor ─────────
    if ($action === 'get_my_assessments') {
        $db   = getDB();
        // find vendor linked to this user (by email match or first vendor for demo)
        $stmt = $db->prepare('
            SELECT va.*, v.name as vendor_name
            FROM vendor_assessments va
            JOIN vendors v ON v.id = va.vendor_id
            WHERE va.assessed_by = :uid
            ORDER BY va.created_at DESC');
        $stmt->execute([':uid'=>$uid]);
        jsonOut(['success'=>true,'assessments'=>$stmt->fetchAll()]);
    }

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

    // ── GET LEADERBOARD ───────────────────────────────────
    if ($action === 'get_leaderboard') {
        $db = getDB();
        $rows = $db->query('
            SELECT v.name, v.store_name, va.score, va.rank,
                   va.password_score, va.phishing_score, va.device_score, va.network_score,
                   va.created_at
            FROM vendors v
            JOIN vendor_assessments va ON va.vendor_id = v.id
            WHERE va.id = (
                SELECT MAX(id) FROM vendor_assessments WHERE vendor_id = v.id
            )
            ORDER BY va.score DESC
        ')->fetchAll();
        jsonOut(['success'=>true,'leaderboard'=>$rows]);
    }

    // ── UPDATE PROFILE ────────────────────────────────────
    if ($action === 'update_profile') {
        $data      = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $fullName  = trim($data['full_name'] ?? '');
        $storeName = trim($data['store_name'] ?? '');

        if (!$fullName) jsonOut(['success'=>false,'error'=>'Full name is required.']);

        $db   = getDB();
        $stmt = $db->prepare('UPDATE users SET full_name=:fn, store_name=:sn WHERE id=:id');
        $stmt->execute([':fn'=>$fullName,':sn'=>$storeName,':id'=>$uid]);

        $_SESSION['full_name']  = $fullName;
        $_SESSION['store_name'] = $storeName;

        logActivity($uid, 'profile', 'Profile updated.');
        jsonOut(['success'=>true]);
    }

    // ── CHANGE PASSWORD ───────────────────────────────────
    if ($action === 'change_password') {
        $data    = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $current = $data['current_password'] ?? '';
        $newPass = $data['new_password'] ?? '';

        $db   = getDB();
        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id=:id');
        $stmt->execute([':id'=>$uid]);
        $row  = $stmt->fetch();

        if (!password_verify($current, $row['password_hash'])) {
            jsonOut(['success'=>false,'error'=>'Current password is incorrect.']);
        }
        if (strlen($newPass) < 6) {
            jsonOut(['success'=>false,'error'=>'New password must be at least 6 characters.']);
        }

        $hash = password_hash($newPass, PASSWORD_BCRYPT);
        $db->prepare('UPDATE users SET password_hash=:h WHERE id=:id')
           ->execute([':h'=>$hash,':id'=>$uid]);

        logActivity($uid, 'profile', 'Password changed.');
        jsonOut(['success'=>true]);
    }

    // ── FORGOT PASSWORD — check email ─────────────────────
    if ($action === 'forgot_check_email') {
        $email = trim($_POST['email'] ?? '');
        $db    = getDB();
        $stmt  = $db->prepare('SELECT id FROM users WHERE email=:e AND is_active=1 LIMIT 1');
        $stmt->execute([':e'=>$email]);
        if (!$stmt->fetch()) {
            jsonOut(['success'=>false,'error'=>'Email not found.']);
        }
        // Generate a reset code and store in session
        $code = 'CS-RESET-' . strtoupper(substr(md5(uniqid($email, true)), 0, 6));
        $_SESSION['reset_email']  = $email;
        $_SESSION['reset_code']   = $code;
        $_SESSION['reset_expiry'] = time() + 600; // 10 min
        jsonOut(['success'=>true,'code'=>$code]); // In production, email this instead
    }

    // ── FORGOT PASSWORD — verify code ─────────────────────
    if ($action === 'forgot_verify_code') {
        $code = trim($_POST['code'] ?? '');
        if (
            !isset($_SESSION['reset_code'], $_SESSION['reset_expiry']) ||
            time() > $_SESSION['reset_expiry'] ||
            strtoupper($code) !== strtoupper($_SESSION['reset_code'])
        ) {
            jsonOut(['success'=>false,'error'=>'Invalid or expired code.']);
        }
        $_SESSION['reset_verified'] = true;
        jsonOut(['success'=>true]);
    }

    // ── FORGOT PASSWORD — set new password ────────────────
    if ($action === 'forgot_reset_password') {
        if (empty($_SESSION['reset_verified']) || empty($_SESSION['reset_email'])) {
            jsonOut(['success'=>false,'error'=>'Reset session expired.']);
        }
        $newPass = $_POST['new_password'] ?? '';
        if (strlen($newPass) < 6) {
            jsonOut(['success'=>false,'error'=>'Password must be at least 6 characters.']);
        }
        $hash  = password_hash($newPass, PASSWORD_BCRYPT);
        $db    = getDB();
        $stmt  = $db->prepare('UPDATE users SET password_hash=:h WHERE email=:e');
        $stmt->execute([':h'=>$hash,':e'=>$_SESSION['reset_email']]);

        unset($_SESSION['reset_email'], $_SESSION['reset_code'],
              $_SESSION['reset_expiry'], $_SESSION['reset_verified']);

        jsonOut(['success'=>true]);
    }

    // ── SAVE PRODUCT ──────────────────────────────────────
    if ($action === 'save_product') {
        $data  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id    = (int)($data['id'] ?? 0);
        $name  = trim($data['name'] ?? '');
        $desc  = trim($data['description'] ?? '');
        $price = (float)($data['price'] ?? 0);
        $stock = (int)($data['stock'] ?? 0);
        $cat   = $data['category'] ?? 'Other';
        $stat  = in_array($data['status'] ?? '', ['active','inactive']) ? $data['status'] : 'active';
        $img   = trim($data['image_url'] ?? '');

        if (!$name || $price < 0 || $stock < 0) {
            jsonOut(['success'=>false,'error'=>'Name, price, and stock are required.']);
        }

        $db = getDB();
        if ($id) {
            $stmt = $db->prepare('UPDATE products SET name=:n,description=:d,price=:p,stock=:s,category=:c,status=:st,image_url=:i WHERE id=:id AND user_id=:uid');
            $stmt->execute([':n'=>$name,':d'=>$desc,':p'=>$price,':s'=>$stock,':c'=>$cat,':st'=>$stat,':i'=>$img,':id'=>$id,':uid'=>$uid]);
        } else {
            $stmt = $db->prepare('INSERT INTO products (user_id,name,description,price,stock,category,status,image_url) VALUES (:uid,:n,:d,:p,:s,:c,:st,:i)');
            $stmt->execute([':uid'=>$uid,':n'=>$name,':d'=>$desc,':p'=>$price,':s'=>$stock,':c'=>$cat,':st'=>$stat,':i'=>$img]);
            $id = (int)$db->lastInsertId();
        }
        jsonOut(['success'=>true,'id'=>$id]);
    }

    // ── GET PRODUCTS ──────────────────────────────────────
    if ($action === 'get_products') {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM products WHERE user_id=:uid ORDER BY created_at DESC');
        $stmt->execute([':uid'=>$uid]);
        jsonOut(['success'=>true,'products'=>$stmt->fetchAll()]);
    }

    // ── DELETE PRODUCT ────────────────────────────────────
    if ($action === 'delete_product') {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id   = (int)($data['id'] ?? 0);
        $db   = getDB();
        $db->prepare('DELETE FROM products WHERE id=:id AND user_id=:uid')
           ->execute([':id'=>$id,':uid'=>$uid]);
        jsonOut(['success'=>true]);
    }

    // ── LOG ACTIVITY ──────────────────────────────────────
    if ($action === 'log_activity') {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $type = $data['action_type'] ?? 'alert';
        $desc = $data['action_description'] ?? '';
        $allowed = ['login','logout','export','flag','refresh','alert','theme','profile','assessment','email'];
        if (!in_array($type, $allowed, true)) $type = 'alert';
        logActivity($uid, $type, $desc);
        jsonOut(['success'=>true]);
    }

    // ── EXPORT CSV ────────────────────────────────────────
    if ($action === 'export_csv') {
        $db   = getDB();
        $stmt = $db->prepare('
            SELECT va.*, v.name as vendor_name, v.email as vendor_email
            FROM vendor_assessments va
            JOIN vendors v ON v.id = va.vendor_id
            WHERE va.assessed_by = :uid
            ORDER BY va.created_at DESC');
        $stmt->execute([':uid'=>$uid]);
        $rows = $stmt->fetchAll();

        logActivity($uid, 'export', 'Exported assessment data to CSV.');

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="cybershield_assessments.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Vendor','Email','Score','Rank','Password','Phishing','Device','Network','Date','Notes']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['vendor_name'], $r['vendor_email'],
                $r['score'], $r['rank'],
                $r['password_score'], $r['phishing_score'],
                $r['device_score'], $r['network_score'],
                $r['created_at'], $r['assessment_notes'],
            ]);
        }
        fclose($out);
        exit;
    }

    // ── GET ACTIVITY LOG ──────────────────────────────────
    if ($action === 'get_activity_log') {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM activity_log WHERE user_id=:uid ORDER BY created_at DESC LIMIT 50');
        $stmt->execute([':uid'=>$uid]);
        jsonOut(['success'=>true,'logs'=>$stmt->fetchAll()]);
    }

    // Unknown action
    jsonOut(['success'=>false,'error'=>'Unknown action.']);
}
// ══════════════════════════════════════════════════════════
//  NOT AN AJAX REQUEST — serve the HTML page
// ══════════════════════════════════════════════════════════
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CyberShield — Hygiene Assessment</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<link rel="stylesheet" href="style.css"/>

<!-- ── PHP-Powered API Bridge ── -->
<script>
// All API calls now go through index.php?action=…
const API = {
    base: 'index.php',

    async post(action, data = {}) {
        const res = await fetch(`${API.base}?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
            credentials: 'same-origin'
        });
        return res.json();
    },

    async get(action, params = {}) {
        const qs = new URLSearchParams({ action, ...params });
        const res = await fetch(`${API.base}?${qs}`, { credentials: 'same-origin' });
        return res.json();
    },

    // ── Auth ─────────────────────────────────────────────
    async login(username, password) {
        const fd = new FormData();
        fd.append('username', username);
        fd.append('password', password);
        const res = await fetch(`${API.base}?action=login`, {
            method: 'POST', body: fd, credentials: 'same-origin'
        });
        return res.json();
    },

    async logout()         { return API.post('logout'); },
    async checkSession()   { return API.get('check_session'); },

    // ── Data ─────────────────────────────────────────────
    async saveAssessment(data)  { return API.post('save_assessment', data); },
    async getMyAssessments()    { return API.get('get_my_assessments'); },
    async getVendors()          { return API.get('get_vendors'); },
    async getLeaderboard()      { return API.get('get_leaderboard'); },
    async updateProfile(data)   { return API.post('update_profile', data); },
    async changePassword(data)  { return API.post('change_password', data); },
    async saveProduct(data)     { return API.post('save_product', data); },
    async getProducts()         { return API.get('get_products'); },
    async deleteProduct(id)     { return API.post('delete_product', { id }); },
    async logActivity(type, desc) {
        return API.post('log_activity', { action_type: type, action_description: desc });
    },
    async forgotCheckEmail(email) {
        const fd = new FormData(); fd.append('email', email);
        const res = await fetch(`${API.base}?action=forgot_check_email`, {
            method: 'POST', body: fd, credentials: 'same-origin'
        });
        return res.json();
    },
    async forgotVerifyCode(code) {
        const fd = new FormData(); fd.append('code', code);
        const res = await fetch(`${API.base}?action=forgot_verify_code`, {
            method: 'POST', body: fd, credentials: 'same-origin'
        });
        return res.json();
    },
    async forgotResetPassword(newPassword) {
        const fd = new FormData(); fd.append('new_password', newPassword);
        const res = await fetch(`${API.base}?action=forgot_reset_password`, {
            method: 'POST', body: fd, credentials: 'same-origin'
        });
        return res.json();
    },

    exportCSV() {
        window.location.href = `${API.base}?action=export_csv`;
    }
};

// ── Override the old localStorage-based auth functions ───
async function checkSession() {
    const r = await API.checkSession();
    if (r.loggedIn) {
        // Hydrate globals the existing script.js expects
        window._phpUser = r.user;
        return true;
    }
    showLoginScreen();
    return false;
}

async function doLogin(username, password) {
    const r = await API.login(username, password);
    if (r.success) {
        window._phpUser = r.user;
        hideLoginScreen();
        bootApp();
    } else {
        showLoginError(r.error || 'Login failed.');
    }
}

async function doLogout() {
    await API.logout();
    window.location.reload();
}

// ── Bridge: persist assessments to DB on completion ──────
// Call this from script.js after scoring:
//   saveAssessmentToDB({ score, rank, password_score, phishing_score, device_score, network_score, assessment_notes })
async function saveAssessmentToDB(payload) {
    return API.saveAssessment(payload);
}

// ── Bridge: fetch leaderboard from DB ────────────────────
async function fetchLeaderboardFromDB() {
    const r = await API.getLeaderboard();
    return r.success ? r.leaderboard : [];
}

// ── Bridge: fetch this user's assessments from DB ────────
async function fetchMyAssessmentsFromDB() {
    const r = await API.getMyAssessments();
    return r.success ? r.assessments : [];
}

// ── Bridge: save/load products ────────────────────────────
async function saveProductToDB(data)    { return API.saveProduct(data); }
async function fetchProductsFromDB()    { return API.getProducts(); }
async function deleteProductFromDB(id)  { return API.deleteProduct(id); }

// ── Bridge: profile ────────────────────────────────────────
async function saveProfileToDB(data)    { return API.updateProfile(data); }
async function changePasswordInDB(cur, newP) {
    return API.changePassword({ current_password: cur, new_password: newP });
}

// ── Bridge: export ─────────────────────────────────────────
function exportCSVFromDB() { API.exportCSV(); }

// ── Show / hide login screen helpers ──────────────────────
function showLoginScreen() {
    document.getElementById('app').classList.add('hidden');
    document.getElementById('login-screen').classList.remove('hidden');
}
function hideLoginScreen() {
    document.getElementById('login-screen').classList.add('hidden');
    document.getElementById('app').classList.remove('hidden');
}
function showLoginError(msg) {
    const el = document.getElementById('login-error');
    if (el) { el.textContent = msg; el.style.display = 'block'; }
}
</script>
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
      <a class="sidebar-item active" id="nav-dashboard" href="dashboard.php">
        <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.2"/><rect x="14" y="3" width="7" height="7" rx="1.2"/><rect x="3" y="14" width="7" height="7" rx="1.2"/><rect x="14" y="14" width="7" height="7" rx="1.2"/></svg></span>
        <span class="sidebar-label">Dashboard</span>
        <span class="sidebar-tooltip">Dashboard</span>
      </a>
      <a class="sidebar-item" id="nav-assessment" href="assessment.php">
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
      <div class="sidebar-user-card" onclick="showPage('profile')" title="View profile">
        <div class="sidebar-avatar" id="sidebar-avatar">D</div>
        <div class="sidebar-user-info">
          <div class="sidebar-user-name" id="sidebar-name">Demo User</div>
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
          <span id="topbar-page-title">Dashboard</span>
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
        <div class="topbar-user" onclick="showPage('profile')" title="My Profile">
          <div class="topbar-avatar" id="nav-avatar">D</div>
          <div class="topbar-user-info">
            <span class="topbar-user-name" id="nav-name">Demo User</span>
            <span class="topbar-user-role">Vendor</span>
          </div>
          <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6l4 4 4-4"/></svg>
        </div>
      </div>
    </header>

    <!-- DASHBOARD -->
    <div id="page-dashboard" class="page hidden">
      <div class="page-inner fade-in">
        <div class="page-header">
          <div>
            <h2 class="page-title">Good day, <span id="dash-greeting">User</span></h2>
            <p class="page-subtitle">Here's your cybersecurity hygiene overview for today.</p>
          </div>
          <button class="btn btn-primary" onclick="startAssessment()">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            Start Assessment
          </button>
        </div>
        <div id="welcome-banner" class="welcome-banner hidden">
          <div class="welcome-inner">
            <div class="welcome-icon-wrap"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--blue)"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
            <h3>Welcome to CyberShield</h3>
            <p>You haven't taken an assessment yet. Start your first quiz to discover your cybersecurity posture — it only takes a few minutes.</p>
            <button class="btn btn-primary btn-lg" onclick="startAssessment()">Start My First Assessment</button>
          </div>
        </div>
        <div class="stats-grid" id="stats-grid">
          <div class="card stat-card"><div class="stat-label">Latest Score</div><div class="stat-val mono" id="stat-score">—</div><div class="stat-sub" id="stat-rank-text">No assessments yet</div></div>
          <div class="card stat-card"><div class="stat-label">Risk Rank</div><div class="stat-val" id="stat-rank">—</div><div class="stat-sub" id="stat-rank-sub">—</div></div>
          <div class="card stat-card"><div class="stat-label">Assessments Done</div><div class="stat-val mono" id="stat-count">0</div><div class="stat-sub">Total sessions</div></div>
          <div class="card stat-card"><div class="stat-label">Trend</div><div class="stat-val" id="stat-trend">—</div><div class="stat-sub" id="stat-trend-sub">—</div></div>
        </div>
        <div class="card tip-card" id="tip-of-day">
          <div class="tip-card-header">
            <span class="tip-card-label">Tip of the Day</span>
            <button class="btn-ghost-sm" onclick="refreshTip()">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
              Refresh
            </button>
          </div>
          <p class="tip-card-text" id="tip-text">Loading tip…</p>
        </div>
        <div class="card section-card">
          <div class="section-title">Badges &amp; Achievements</div>
          <div id="dash-badges" class="badges-row"></div>
        </div>
        <div class="quick-actions">
          <div class="action-card" onclick="startAssessment()">
            <div class="action-icon blue"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
            <div class="action-text"><h4>New Assessment</h4><p>Take a fresh 12-question quiz</p></div>
            <svg class="action-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </div>
          <div class="action-card" onclick="showPage('results')">
            <div class="action-icon green"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
            <div class="action-text"><h4>View Results</h4><p>Charts &amp; recommendations</p></div>
            <svg class="action-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </div>
          <div class="action-card" onclick="showPage('leaderboard')">
            <div class="action-icon purple"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6l4-4 4 4"/><path d="M12 2v13"/><path d="M20 21H4"/><path d="M17 12h3v9"/><path d="M4 12h3v9"/></svg></div>
            <div class="action-text"><h4>Leaderboard</h4><p>Compare with other vendors</p></div>
            <svg class="action-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </div>
          <div class="action-card" onclick="showPage('tips')">
            <div class="action-icon orange"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
            <div class="action-text"><h4>Security Tips</h4><p>Guides to improve your score</p></div>
            <svg class="action-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </div>
        </div>
        <div class="card chart-card"><div class="chart-card-header">Risk Score Trend</div><div class="chart-wrap"><canvas id="trend-chart"></canvas></div></div>
        <div class="card section-card"><div class="section-title">Assessment History</div><div id="history-container"><p class="empty-state">No assessments taken yet. Start one now!</p></div></div>
      </div>
    </div>

    <!-- ASSESSMENT -->
    <div id="page-assessment" class="page hidden">
      <div class="page-inner fade-in">
        <div class="assess-header">
          <div class="assess-topbar">
            <h2 class="assess-title">Cyber Hygiene Assessment</h2>
            <button class="btn btn-outline btn-sm" onclick="confirmQuitAssessment()">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              Quit
            </button>
          </div>
          <div class="progress-meta">
            <span id="progress-label">Question 1 of 12</span>
            <div class="progress-meta-right">
              <div class="timer-wrap" id="timer-wrap">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span class="timer-val" id="timer-val">30</span>
              </div>
              <span class="progress-pct" id="progress-pct">0%</span>
            </div>
          </div>
          <div class="progress-bar-wrap"><div class="progress-bar-fill" id="progress-fill" style="width:0%"></div></div>
          <div class="timer-bar-wrap"><div class="timer-bar-fill" id="timer-bar-fill"></div></div>
        </div>
        <div class="card q-card" id="q-card"></div>
      </div>
    </div>

    <!-- RESULTS -->
    <div id="page-results" class="page hidden">
      <div class="page-inner fade-in">
        <div class="page-header">
          <div><h2 class="page-title">My Results</h2><p class="page-subtitle">Latest assessment breakdown and recommendations</p></div>
          <div class="btn-group">
            <button class="btn btn-outline btn-sm" onclick="exportCSVFromDB()"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> CSV</button>
            <button class="btn btn-outline btn-sm" onclick="exportPDF()"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> PDF</button>
            <button class="btn btn-outline btn-sm" onclick="printResult()"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg> Print</button>
          </div>
        </div>
        <div class="result-hero" id="result-hero"><div class="spinner-wrap"><div class="spinner"></div></div></div>
        <div class="card section-card" id="badges-card" style="display:none;"><div class="section-title">Badges Earned</div><div id="result-badges" class="badges-row"></div></div>
        <div class="results-grid">
          <div class="card" style="padding:1.75rem;"><div class="section-title" style="margin-bottom:1.25rem;">Category Breakdown</div><div style="position:relative;height:270px;"><canvas id="radar-chart"></canvas></div></div>
          <div class="card reco-card" style="padding:1.75rem;"><div class="section-title" style="margin-bottom:1rem;">Recommendations</div><ul class="reco-list" id="reco-list"></ul></div>
        </div>
        <div class="card" style="padding:1.75rem;margin-bottom:1.5rem;">
          <div class="card-row-header"><div class="section-title" style="margin:0;">Answer Review</div><button class="btn btn-outline btn-sm" id="review-toggle-btn" onclick="toggleReview()">Show Review</button></div>
          <div id="review-container" class="hidden" style="margin-top:1.25rem;"></div>
        </div>
        <div class="card chart-card" style="margin-bottom:1.5rem;"><div class="chart-card-header">Progress Over Time</div><div class="chart-wrap"><canvas id="trend-chart-2"></canvas></div></div>
        <div class="card section-card" style="margin-bottom:2rem;"><div class="section-title">Learning Resources</div><p class="section-sub">Targeted videos based on your weakest categories</p><div class="video-grid" id="video-grid"></div></div>
      </div>
    </div>

    <!-- LEADERBOARD -->
    <div id="page-leaderboard" class="page hidden">
      <div class="page-inner fade-in">
        <div class="page-header"><div><h2 class="page-title">Leaderboard</h2><p class="page-subtitle">Vendor cybersecurity rankings across the platform</p></div></div>
        <div class="card" style="padding:1.75rem;">
          <div class="card-row-header">
            <div class="section-title" style="margin:0;">Vendor Rankings</div>
            <div class="filter-group">
              <button class="filter-btn lb-filter-btn active" onclick="filterLeaderboard('all',this)">All</button>
              <button class="filter-btn lb-filter-btn risk-a" onclick="filterLeaderboard('A',this)">Low Risk</button>
              <button class="filter-btn lb-filter-btn risk-b" onclick="filterLeaderboard('B',this)">Moderate</button>
              <button class="filter-btn lb-filter-btn risk-cd" onclick="filterLeaderboard('CD',this)">High Risk</button>
            </div>
          </div>
          <div id="leaderboard-list" style="margin-top:1.25rem;"></div>
        </div>
      </div>
    </div>

    <!-- PROFILE -->
    <div id="page-profile" class="page hidden">
      <div class="page-inner fade-in">
        <div class="page-header"><div><h2 class="page-title">My Profile</h2><p class="page-subtitle">Manage your account information and preferences</p></div></div>
        <div class="profile-grid">
          <div class="profile-col">
            <div class="card" style="padding:1.75rem;">
              <div class="profile-sec-title">Account Information</div>
              <div class="profile-avatar-wrap">
                <div class="profile-avatar" id="profile-avatar-big">D</div>
                <div><div class="profile-display-name" id="profile-name-display">Demo User</div><div class="profile-display-email" id="profile-email-display">demo@company.com</div></div>
              </div>
              <div class="form-group"><label>Display Name</label><input type="text" id="profile-name" placeholder="Your full name"/></div>
              <div class="form-group"><label>Email Address</label><input type="text" id="profile-email" readonly/></div>
              <div class="form-group"><label>Store / Company Name</label><input type="text" id="profile-company" placeholder="Your store name"/></div>
              <button class="btn btn-primary btn-full" onclick="saveProfile()"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Save Changes</button>
            </div>
            <!-- Change Password Card -->
            <div class="card" style="padding:1.75rem;margin-top:1.25rem;">
              <div class="profile-sec-title">Change Password</div>
              <div class="form-group"><label>Current Password</label><input type="password" id="pw-current" placeholder="••••••••"/></div>
              <div class="form-group"><label>New Password</label><input type="password" id="pw-new" placeholder="••••••••"/></div>
              <div class="form-group"><label>Confirm New Password</label><input type="password" id="pw-confirm" placeholder="••••••••"/></div>
              <div id="pw-change-error" class="form-error" style="display:none;margin-bottom:.75rem;"></div>
              <button class="btn btn-primary btn-full" onclick="submitPasswordChange()">Update Password</button>
            </div>
            <div class="card" style="padding:1.75rem;">
              <div class="profile-sec-title">Preferences</div>
              <div class="pref-row"><div class="pref-text"><div class="pref-label">Dark Mode</div><div class="pref-sub">Switch between dark and light theme</div></div><label class="toggle-switch"><input type="checkbox" id="pref-dark" onchange="applyTheme(this.checked)"/><span class="toggle-slider"></span></label></div>
              <div class="pref-row"><div class="pref-text"><div class="pref-label">Question Timer</div><div class="pref-sub">30-second countdown per question</div></div><label class="toggle-switch"><input type="checkbox" id="pref-timer" checked/><span class="toggle-slider"></span></label></div>
              <div class="pref-row"><div class="pref-text"><div class="pref-label">Notifications</div><div class="pref-sub">Alerts after each assessment</div></div><label class="toggle-switch"><input type="checkbox" id="pref-notif" checked/><span class="toggle-slider"></span></label></div>
              <div class="pref-row"><div class="pref-text"><div class="pref-label">Large Text Mode</div><div class="pref-sub">Bigger text for easier reading</div></div><label class="toggle-switch"><input type="checkbox" id="pref-a11y" onchange="toggleAccessibility()"/><span class="toggle-slider"></span></label></div>
            </div>
          </div>
          <div class="profile-col">
            <div class="card" style="padding:1.75rem;"><div class="profile-sec-title">Your Statistics</div><div class="profile-stats-grid" id="profile-stats-grid"></div></div>
            <div class="card" style="padding:1.75rem;"><div class="profile-sec-title">Earned Badges</div><div id="profile-badges" class="badges-row"></div></div>
            <div class="card danger-card" style="padding:1.75rem;">
              <div class="profile-sec-title danger-sec-title">Danger Zone</div>
              <p class="danger-desc">Permanently delete all local assessment history and cached data.</p>
              <button class="btn-danger" onclick="clearAllData()"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg> Clear Local Data</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- SECURITY TIPS -->
    <div id="page-tips" class="page hidden">
      <div class="page-inner fade-in">
        <div class="page-header"><div><h2 class="page-title">Security Tips</h2><p class="page-subtitle">Practical guides to strengthen your cybersecurity posture</p></div></div>
        <div class="filter-bar">
          <button class="filter-btn active" onclick="filterTips('all',this)">All Tips</button>
          <button class="filter-btn" onclick="filterTips('password',this)">Passwords</button>
          <button class="filter-btn" onclick="filterTips('phishing',this)">Phishing</button>
          <button class="filter-btn" onclick="filterTips('device',this)">Devices</button>
          <button class="filter-btn" onclick="filterTips('network',this)">Networks</button>
        </div>
        <div class="tips-grid" id="tips-grid"></div>
      </div>
    </div>

    <!-- TERMS & PRIVACY -->
    <div id="page-terms" class="page hidden">
      <div class="page-inner fade-in">
        <div class="page-header"><div><h2 class="page-title" data-i18n="terms_title">Terms &amp; Privacy</h2><p class="page-subtitle" data-i18n="terms_sub">Last updated: March 2025</p></div></div>
        <div class="terms-layout">
          <div class="card terms-toc">
            <div class="terms-toc-title">Contents</div>
            <a class="terms-nav-item" onclick="scrollToSection('terms-s1')">1. Introduction</a>
            <a class="terms-nav-item" onclick="scrollToSection('terms-s2')">2. Data We Collect</a>
            <a class="terms-nav-item" onclick="scrollToSection('terms-s3')">3. How We Use Data</a>
            <a class="terms-nav-item" onclick="scrollToSection('terms-s4')">4. Data Storage</a>
            <a class="terms-nav-item" onclick="scrollToSection('terms-s5')">5. Your Rights</a>
            <a class="terms-nav-item" onclick="scrollToSection('terms-s6')">6. Contact Us</a>
          </div>
          <div class="terms-content">
            <div class="card terms-section" id="terms-s1"><h3 class="terms-heading">1. Introduction</h3><p class="terms-body">CyberShield Vendor Hygiene Assessment Platform is designed to help organizations evaluate their cybersecurity awareness and practices. By using this Platform, you agree to these Terms of Service and our Privacy Policy.</p></div>
            <div class="card terms-section" id="terms-s2"><h3 class="terms-heading">2. Data We Collect</h3><p class="terms-body">Assessment responses, scores, and account information are stored securely in our MySQL database hosted on your server.</p><div class="terms-highlight">Your data is stored server-side and protected by PHP session authentication.</div></div>
            <div class="card terms-section" id="terms-s3"><h3 class="terms-heading">3. How We Use Data</h3><p class="terms-body">Data is used to display scores, track progress, generate recommendations, and maintain leaderboard standings.</p></div>
            <div class="card terms-section" id="terms-s4"><h3 class="terms-heading">4. Data Storage &amp; Security</h3><p class="terms-body">All data is stored in a MySQL database. Passwords are hashed using bcrypt. Sessions expire automatically for security.</p></div>
            <div class="card terms-section" id="terms-s5"><h3 class="terms-heading">5. Your Rights</h3><p class="terms-body">You may request export or deletion of your data by contacting the administrator.</p><button class="btn btn-outline btn-sm" onclick="clearAllData()" style="margin-top:1rem;">Clear Local Cache</button></div>
            <div class="card terms-section" id="terms-s6"><h3 class="terms-heading">6. Contact Us</h3><p class="terms-body">For questions, contact the CyberShield platform administrator at <strong>admin@cybershield.ph</strong>.</p></div>
          </div>
        </div>
      </div>
    </div>

    <!-- SELLER STORE -->
    <div id="page-seller-store" class="page hidden">
      <div class="page-inner fade-in">
        <div class="page-header">
          <div><h2 class="page-title">My Store</h2><p class="page-subtitle">Manage your product listings, inventory, and postings</p></div>
          <button class="btn btn-primary" onclick="openProductModal()">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Product
          </button>
        </div>
        <div class="stats-grid" id="seller-stats-grid">
          <div class="card stat-card"><div class="stat-label">Total Products</div><div class="stat-val mono" id="s-stat-total">0</div><div class="stat-sub">Listed items</div></div>
          <div class="card stat-card"><div class="stat-label">Active Listings</div><div class="stat-val mono" id="s-stat-active" style="color:var(--green)">0</div><div class="stat-sub">Visible to buyers</div></div>
          <div class="card stat-card"><div class="stat-label">Out of Stock</div><div class="stat-val mono" id="s-stat-oos" style="color:var(--red)">0</div><div class="stat-sub">Needs restocking</div></div>
          <div class="card stat-card"><div class="stat-label">Total Value</div><div class="stat-val mono" id="s-stat-value" style="font-size:1.5rem">₱0</div><div class="stat-sub">Inventory worth</div></div>
        </div>
        <div class="card" style="padding:1rem 1.5rem;margin-bottom:1.25rem;">
          <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
            <div class="topbar-search" style="flex:1;min-width:180px;">
              <svg width="13" height="13" viewBox="0 0 20 20" fill="none"><circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.7"/><path d="M15 15l3 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
              <input type="text" id="product-search" placeholder="Search products…" oninput="renderProductGrid()" autocomplete="off"/>
            </div>
            <div class="filter-group" style="margin:0;">
              <button class="filter-btn active" onclick="filterProducts('all',this)">All</button>
              <button class="filter-btn" onclick="filterProducts('active',this)">Active</button>
              <button class="filter-btn" onclick="filterProducts('inactive',this)">Inactive</button>
              <button class="filter-btn" onclick="filterProducts('out_of_stock',this)">Out of Stock</button>
            </div>
          </div>
        </div>
        <div id="product-grid" class="product-grid"></div>
        <div id="product-empty" class="card" style="padding:3rem;text-align:center;display:none;">
          <div style="font-size:2.5rem;margin-bottom:1rem;">📦</div>
          <h3 style="margin-bottom:.5rem;font-family:var(--display);letter-spacing:1px;">No products yet</h3>
          <p style="color:var(--text2);font-size:.85rem;margin-bottom:1.5rem;">Start building your store by adding your first product listing.</p>
          <button class="btn btn-primary" onclick="openProductModal()">Add Your First Product</button>
        </div>
      </div>
    </div>

    <!-- SELLER ANALYTICS -->
    <div id="page-seller-analytics" class="page hidden">
      <div class="page-inner fade-in">
        <div class="page-header">
          <div><h2 class="page-title">Analytics Dashboard</h2><p class="page-subtitle">Performance insights and data-driven metrics for your store</p></div>
          <div class="filter-group" style="margin:0;">
            <button class="filter-btn active" onclick="setAnalyticsPeriod(7,this)">7D</button>
            <button class="filter-btn" onclick="setAnalyticsPeriod(30,this)">30D</button>
            <button class="filter-btn" onclick="setAnalyticsPeriod(90,this)">90D</button>
          </div>
        </div>
        <div class="stats-grid">
          <div class="card stat-card kpi-card"><div class="kpi-icon" style="background:rgba(59,139,255,.12);color:var(--blue)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div><div class="stat-label">Total Revenue</div><div class="stat-val mono" id="kpi-revenue" style="font-size:1.6rem">₱0</div><div class="stat-sub kpi-trend" id="kpi-revenue-trend">—</div></div>
          <div class="card stat-card kpi-card"><div class="kpi-icon" style="background:rgba(0,232,130,.12);color:var(--green)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg></div><div class="stat-label">Orders Placed</div><div class="stat-val mono" id="kpi-orders">0</div><div class="stat-sub kpi-trend" id="kpi-orders-trend">—</div></div>
          <div class="card stat-card kpi-card"><div class="kpi-icon" style="background:rgba(176,97,255,.12);color:var(--purple)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div><div class="stat-label">Product Views</div><div class="stat-val mono" id="kpi-views">0</div><div class="stat-sub kpi-trend" id="kpi-views-trend">—</div></div>
          <div class="card stat-card kpi-card"><div class="kpi-icon" style="background:rgba(255,140,66,.12);color:var(--orange)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg></div><div class="stat-label">Engagement Rate</div><div class="stat-val mono" id="kpi-engage">0%</div><div class="stat-sub kpi-trend" id="kpi-engage-trend">—</div></div>
        </div>
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem;margin-bottom:1.25rem;" class="analytics-grid-2">
          <div class="card chart-card"><div class="chart-card-header">Revenue Over Time</div><div class="chart-wrap" style="height:240px;"><canvas id="analytics-revenue-chart"></canvas></div></div>
          <div class="card chart-card"><div class="chart-card-header">Sales by Category</div><div class="chart-wrap" style="height:240px;"><canvas id="analytics-category-chart"></canvas></div></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem;" class="analytics-grid-2">
          <div class="card chart-card"><div class="chart-card-header">Views vs Orders</div><div class="chart-wrap" style="height:220px;"><canvas id="analytics-views-chart"></canvas></div></div>
          <div class="card chart-card"><div class="chart-card-header">Customer Engagement Score</div><div class="chart-wrap" style="height:220px;"><canvas id="analytics-engagement-chart"></canvas></div></div>
        </div>
        <div class="card" style="padding:1.75rem;margin-bottom:2rem;">
          <div class="section-title" style="margin-bottom:1.25rem;">Top Performing Products</div>
          <div id="analytics-top-products"></div>
        </div>
      </div>
    </div>

  </div><!-- end main-content -->
</div><!-- end app -->

<!-- PRODUCT MODAL -->
<div id="product-modal" class="fp-overlay hidden" onclick="closeProductModal(event)">
  <div class="fp-card product-modal-card" style="max-width:560px;">
    <button class="fp-close-btn" onclick="closeProductModal()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    <h3 id="product-modal-title" style="font-family:var(--display);font-size:1.55rem;letter-spacing:1px;margin-bottom:1.5rem;">Add New Product</h3>
    <input type="hidden" id="product-edit-id"/>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
      <div class="form-group" style="grid-column:1/-1"><label>Product Name *</label><input type="text" id="p-name" placeholder="e.g. Wireless Headphones"/></div>
      <div class="form-group" style="grid-column:1/-1"><label>Description</label><textarea id="p-desc" rows="3" placeholder="Describe your product…" style="font-family:var(--font);font-size:.85rem;background:var(--surface);border:1px solid var(--border2);border-radius:var(--radius-sm);color:var(--text);padding:.6rem .85rem;width:100%;resize:vertical;"></textarea></div>
      <div class="form-group"><label>Price (₱) *</label><input type="number" id="p-price" placeholder="0.00" min="0" step="0.01"/></div>
      <div class="form-group"><label>Stock Quantity *</label><input type="number" id="p-stock" placeholder="0" min="0"/></div>
      <div class="form-group"><label>Category</label>
        <select id="p-category" style="font-family:var(--font);font-size:.85rem;background:var(--surface);border:1px solid var(--border2);border-radius:var(--radius-sm);color:var(--text);padding:.6rem .85rem;width:100%;">
          <option value="Electronics">Electronics</option>
          <option value="Clothing">Clothing</option>
          <option value="Home &amp; Garden">Home &amp; Garden</option>
          <option value="Sports">Sports</option>
          <option value="Books">Books</option>
          <option value="Food &amp; Beverage">Food &amp; Beverage</option>
          <option value="Health &amp; Beauty">Health &amp; Beauty</option>
          <option value="Other">Other</option>
        </select>
      </div>
      <div class="form-group"><label>Status</label>
        <select id="p-status" style="font-family:var(--font);font-size:.85rem;background:var(--surface);border:1px solid var(--border2);border-radius:var(--radius-sm);color:var(--text);padding:.6rem .85rem;width:100%;">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <div class="form-group" style="grid-column:1/-1"><label>Product Image URL</label><input type="text" id="p-image" placeholder="https://… (leave blank for default)"/></div>
    </div>
    <div id="product-modal-error" class="form-error" style="display:none;margin-bottom:.75rem;"></div>
    <div style="display:flex;gap:.75rem;margin-top:.5rem;">
      <button class="btn btn-primary" style="flex:1" onclick="saveProduct()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Save Product
      </button>
      <button class="btn btn-outline" onclick="closeProductModal()">Cancel</button>
    </div>
  </div>
</div>

<!-- FORGOT PASSWORD OVERLAY -->
<div id="forgot-overlay" class="fp-overlay hidden" onclick="closeForgotOverlay(event)">
  <div class="fp-card">
    <button class="fp-close-btn" onclick="closeForgotPassword()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    <div class="fp-step" id="fp-step-1">
      <div class="fp-step-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
      <h3>Reset Password</h3>
      <p>Enter your registered email address to receive a reset code.</p>
      <div class="form-group" style="margin-top:1rem;"><label>Email Address</label><input type="email" id="fp-email" placeholder="demo@company.com"/></div>
      <div id="fp-error" class="form-error" style="display:none;">Email not found.</div>
      <button class="btn btn-primary btn-full" onclick="doForgotStep1()">Send Reset Code</button>
    </div>
    <div class="fp-step hidden" id="fp-step-2">
      <div class="fp-step-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
      <h3>Enter Reset Code</h3>
      <p>We generated a code for <strong id="fp-sent-email"></strong></p>
      <div class="fp-code-box"><span class="fp-code" id="fp-code"></span></div>
      <div class="form-group" style="margin-top:.75rem;"><label>Reset Code</label><input type="text" id="fp-code-input" placeholder="CS-RESET-XXXXXX"/></div>
      <div id="fp-code-error" class="form-error" style="display:none;">Invalid code. Please try again.</div>
      <button class="btn btn-primary btn-full" onclick="doForgotStep2()">Verify Code</button>
    </div>
    <div class="fp-step hidden" id="fp-step-3">
      <div class="fp-step-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
      <h3>Set New Password</h3>
      <p>Choose a strong password for your account.</p>
      <div class="form-group" style="margin-top:1rem;"><label>New Password</label><input type="password" id="fp-newpass" placeholder="••••••••"/></div>
      <div class="form-group"><label>Confirm Password</label><input type="password" id="fp-confirmpass" placeholder="••••••••"/></div>
      <div class="pass-strength-bar"><div id="pass-strength-fill" class="pass-strength-fill"></div></div>
      <div id="pass-strength-label" class="pass-strength-label"></div>
      <div id="fp-pass-error" class="form-error" style="display:none;"></div>
      <button class="btn btn-primary btn-full" style="margin-top:.75rem;" onclick="doForgotStep3()">Reset Password</button>
    </div>
    <div class="fp-step hidden" id="fp-step-4">
      <div class="fp-step-icon success"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
      <h3>Password Reset!</h3>
      <p>Your password has been updated. You can now sign in.</p>
      <button class="btn btn-primary btn-full" style="margin-top:1rem;" onclick="closeForgotPassword()">Back to Sign In</button>
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

<!-- ── DB-aware login & boot overrides ── -->
<script>
// Fill demo credentials
function fillDemo(u, p) {
    document.getElementById('login-username').value = u;
    document.getElementById('login-password').value = p;
}

// Login button handler — calls PHP API
async function attemptLogin() {
    const btn  = document.getElementById('login-btn');
    const user = document.getElementById('login-username').value.trim();
    const pass = document.getElementById('login-password').value;
    const err  = document.getElementById('login-error');

    err.style.display = 'none';
    btn.disabled = true;
    btn.textContent = 'Signing in…';

    try {
        await doLogin(user, pass);
    } catch(e) {
        showLoginError('Connection error. Check your server.');
    }

    btn.disabled = false;
    btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg> Sign In';
}

// Override forgot password steps to use PHP API
async function doForgotStep1() {
    const email = document.getElementById('fp-email').value.trim();
    const errEl = document.getElementById('fp-error');
    errEl.style.display = 'none';

    const r = await API.forgotCheckEmail(email);
    if (!r.success) { errEl.textContent = r.error || 'Email not found.'; errEl.style.display = 'block'; return; }

    document.getElementById('fp-sent-email').textContent = email;
    document.getElementById('fp-code').textContent = r.code; // Show in UI; in prod, email it
    document.getElementById('fp-step-1').classList.add('hidden');
    document.getElementById('fp-step-2').classList.remove('hidden');
}

async function doForgotStep2() {
    const code  = document.getElementById('fp-code-input').value.trim();
    const errEl = document.getElementById('fp-code-error');
    errEl.style.display = 'none';

    const r = await API.forgotVerifyCode(code);
    if (!r.success) { errEl.style.display = 'block'; return; }

    document.getElementById('fp-step-2').classList.add('hidden');
    document.getElementById('fp-step-3').classList.remove('hidden');
}

async function doForgotStep3() {
    const np = document.getElementById('fp-newpass').value;
    const cp = document.getElementById('fp-confirmpass').value;
    const errEl = document.getElementById('fp-pass-error');
    errEl.style.display = 'none';

    if (np !== cp) { errEl.textContent = 'Passwords do not match.'; errEl.style.display = 'block'; return; }
    if (np.length < 6) { errEl.textContent = 'Password too short.'; errEl.style.display = 'block'; return; }

    const r = await API.forgotResetPassword(np);
    if (!r.success) { errEl.textContent = r.error; errEl.style.display = 'block'; return; }

    document.getElementById('fp-step-3').classList.add('hidden');
    document.getElementById('fp-step-4').classList.remove('hidden');
}

// Override profile save to use PHP API
async function saveProfile() {
    const fullName  = document.getElementById('profile-name').value.trim();
    const storeName = document.getElementById('profile-company').value.trim();
    const r = await API.updateProfile({ full_name: fullName, store_name: storeName });
    if (r.success) {
        document.getElementById('sidebar-name').textContent = fullName;
        document.getElementById('nav-name').textContent     = fullName;
        if (typeof showToast === 'function') showToast('Profile saved!', 'success');
    } else {
        alert(r.error || 'Could not save profile.');
    }
}

// Change password via PHP
async function submitPasswordChange() {
    const cur  = document.getElementById('pw-current').value;
    const newP = document.getElementById('pw-new').value;
    const con  = document.getElementById('pw-confirm').value;
    const errEl = document.getElementById('pw-change-error');
    errEl.style.display = 'none';

    if (newP !== con) { errEl.textContent = 'Passwords do not match.'; errEl.style.display = 'block'; return; }
    const r = await changePasswordInDB(cur, newP);
    if (r.success) {
        document.getElementById('pw-current').value = '';
        document.getElementById('pw-new').value     = '';
        document.getElementById('pw-confirm').value = '';
        if (typeof showToast === 'function') showToast('Password updated!', 'success');
    } else {
        errEl.textContent = r.error || 'Failed to update password.';
        errEl.style.display = 'block';
    }
}

// Boot: check PHP session, then launch app or show login
(async () => {
    const r = await API.checkSession();
    if (r.loggedIn) {
        window._phpUser = r.user;
        // Populate sidebar & topbar with real user info
        const u = r.user;
        const initial = (u.full_name || u.username || 'U')[0].toUpperCase();
        document.getElementById('sidebar-avatar').textContent = initial;
        document.getElementById('sidebar-name').textContent   = u.full_name || u.username;
        document.getElementById('nav-avatar').textContent     = initial;
        document.getElementById('nav-name').textContent       = u.full_name || u.username;
        document.getElementById('dash-greeting').textContent  = u.full_name || u.username;

        // Pre-fill profile fields
        if (document.getElementById('profile-name'))    document.getElementById('profile-name').value    = u.full_name || '';
        if (document.getElementById('profile-email'))   document.getElementById('profile-email').value   = u.email || '';
        if (document.getElementById('profile-company')) document.getElementById('profile-company').value = u.store_name || '';
        if (document.getElementById('profile-name-display'))  document.getElementById('profile-name-display').textContent  = u.full_name || '';
        if (document.getElementById('profile-email-display')) document.getElementById('profile-email-display').textContent = u.email || '';
        if (document.getElementById('profile-avatar-big'))    document.getElementById('profile-avatar-big').textContent   = initial;

        hideLoginScreen();
        if (typeof bootApp === 'function') bootApp();
    } else {
        showLoginScreen();
    }
})();
</script>
</body>
</html>