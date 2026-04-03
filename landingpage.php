<?php
require_once 'includes/config.php';
require_once 'includes/audit_helper.php';

// Handle login POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    session_start();
    
    if ($_POST['action'] === 'login') {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Username and password are required']);
            exit;
        }
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            if (!$db) {
                echo json_encode(['success' => false, 'message' => 'Database connection failed - no connection object returned']);
                exit;
            }
            
            $stmt = $db->prepare("SELECT id, username, password_hash, email, full_name, store_name, role FROM users WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // For demo purposes, we'll just simulate OTP requirement
                echo json_encode([
                    'success' => true, 
                    'require_otp' => true,
                    'user_id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'full_name' => $user['full_name'],
                    'message' => 'Please enter the 6-digit code sent to your device'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
            }
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        } catch(Exception $e) {
            echo json_encode(['success' => false, 'message' => 'General error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'verify_otp') {
        $user_id = sanitizeInput($_POST['user_id'] ?? '');
        $username = sanitizeInput($_POST['username'] ?? '');
        $role = sanitizeInput($_POST['role'] ?? '');
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Get user info from database
            $stmt = $db->prepare("SELECT id, username, role, full_name FROM users WHERE id = ? AND username = ?");
            $stmt->execute([$user_id, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Set PHP session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['logged_in'] = true;
                
                // Create audit log for successful login
                createAuditLog($db, $user['id'], 'login', 'User logged in successfully', getClientIP(), getClientUserAgent());
                
                // Determine redirect URL based on role
                $redirect_url = $user['role'] === 'Admin' ? 
                    'http://localhost/security/Admin/dashboard.php' : 
                    'http://localhost/security/Client/index.php?role=' . strtolower($user['role']) . '&user=' . $user['username'];
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'OTP verified successfully',
                    'redirect_url' => $redirect_url
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid user data']);
            }
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'register') {
        $fullname = sanitizeInput($_POST['fullname'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $store = sanitizeInput($_POST['store'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm'] ?? '';
        
        if (empty($fullname) || empty($email) || empty($store) || empty($password) || empty($confirm)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit;
        }
        
        if (!validateEmail($email)) {
            echo json_encode(['success' => false, 'message' => 'Valid email address is required']);
            exit;
        }
        
        if (strlen($password) < 8) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
            exit;
        }
        
        if ($password !== $confirm) {
            echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
            exit;
        }
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Check if email exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Email address already registered']);
                exit;
            }
            
            // Generate unique username
            $base_username = explode('@', $email)[0];
            $username = $base_username;
            $counter = 1;
            
            while (true) {
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if (!$stmt->fetch()) break;
                $username = $base_username . $counter;
                $counter++;
            }
            
            // Insert new user
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, email, full_name, store_name, role) VALUES (?, ?, ?, ?, ?, 'Seller')");
            $stmt->execute([$username, $password_hash, $email, $fullname, $store]);
            
            $_SESSION['user_id'] = $db->lastInsertId();
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'Seller';
            $_SESSION['full_name'] = $fullname;
            $_SESSION['logged_in'] = true;
            
            $redirect_url = 'http://localhost/security/Client/index.php?role=seller&user=' . $username;
            echo json_encode(['success' => true, 'message' => 'Account created successfully', 'redirect_url' => $redirect_url]);
            
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Registration failed']);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CyberShield — Know Your Cyber Risk</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;700&family=Bebas+Neue&display=swap" rel="stylesheet">
<style>
:root {
  --blue: #3b8bff; --purple: #b061ff; --green: #00ff94;
  --red: #ff3b5c; --bg: #030508; --border: rgba(255,255,255,0.06);
  --text: #dde4f0; --muted: #5c6a84; --dim: #2a3a52;
  --input-bg: rgba(255,255,255,0.03);
  --input-border: rgba(255,255,255,0.08);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Space Grotesk', sans-serif;
  background: var(--bg); color: var(--text);
  min-height: 100vh; display: flex; overflow-x: hidden;
}
body::before {
  content: ''; position: fixed; inset: 0; z-index: 0; pointer-events: none;
  background-image:
    linear-gradient(rgba(59,139,255,0.04) 1px, transparent 1px),
    linear-gradient(90deg, rgba(59,139,255,0.04) 1px, transparent 1px);
  background-size: 60px 60px; animation: gridMove 30s linear infinite;
}
@keyframes gridMove { to { background-position: 60px 60px; } }

.orb1,.orb2,.orb3 { position: fixed; border-radius: 50%; filter: blur(140px); pointer-events: none; z-index: 0; }
.orb1 { width:700px;height:700px;background:#3b8bff;top:-250px;left:-250px;opacity:.10;animation:orbf 14s ease-in-out infinite alternate; }
.orb2 { width:600px;height:600px;background:#b061ff;bottom:-200px;right:-150px;opacity:.10;animation:orbf 16s ease-in-out infinite alternate-reverse; }
.orb3 { width:300px;height:300px;background:#00ff94;top:50%;left:50%;opacity:.04;animation:orbf 10s ease-in-out infinite; }
@keyframes orbf { from{transform:scale(1) translate(0,0)} to{transform:scale(1.2) translate(30px,20px)} }

/* ═══ LEFT ═══ */
.left {
  flex: 1; background: rgba(6,9,16,0.97); border-right: 1px solid var(--border);
  padding: 48px 64px; display: flex; flex-direction: column; justify-content: space-between;
  position: relative; z-index: 1; overflow: hidden;
}
.left::before {
  content: ''; position: absolute; top:0;right:0;width:300px;height:300px;
  background: radial-gradient(ellipse at top right, rgba(59,139,255,0.07), transparent 70%);
  pointer-events: none;
}
.logo { display:flex;align-items:center;gap:14px; }
.logo-icon {
  width:46px;height:46px; background:linear-gradient(135deg,#3b8bff,#b061ff);
  border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;
  animation:logoGlow 4s ease-in-out infinite alternate;
}
@keyframes logoGlow {
  from{box-shadow:0 0 0 1px rgba(59,139,255,0.3),0 0 24px rgba(59,139,255,0.3)}
  to{box-shadow:0 0 0 1px rgba(176,97,255,0.5),0 0 40px rgba(176,97,255,0.4)}
}
.logo-name {
  font-family:'Bebas Neue',sans-serif;font-size:30px;letter-spacing:4px;
  background:linear-gradient(90deg,#fff 0%,#3b8bff 100%);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.logo-tag { margin-left:auto;font-family:'JetBrains Mono',monospace;font-size:9px;letter-spacing:2px;color:#2a3a52;text-transform:uppercase; }

.hero { flex:1;display:flex;flex-direction:column;justify-content:center;padding:40px 0 24px; }
.eyebrow {
  display:inline-flex;align-items:center;gap:8px;
  background:rgba(0,255,148,0.07);border:1px solid rgba(0,255,148,0.18);
  color:var(--green);font-family:'JetBrains Mono',monospace;
  font-size:10px;letter-spacing:2.5px;text-transform:uppercase;
  padding:6px 14px;border-radius:4px;margin-bottom:24px;width:fit-content;
  animation:fadeUp .6s ease both;
}
.eyebrow-dot { width:6px;height:6px;border-radius:50%;background:var(--green);animation:blink 2s step-end infinite; }
@keyframes blink { 50%{opacity:0} }
@keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }

.headline {
  font-family:'Bebas Neue',sans-serif;
  font-size:clamp(56px,5.5vw,84px);line-height:.9;letter-spacing:1px;color:#fff;
  margin-bottom:22px;animation:fadeUp .6s .1s ease both;
}
.headline .blue  { color:var(--blue);text-shadow:0 0 60px rgba(59,139,255,0.4); }
.headline .green { color:var(--green);text-shadow:0 0 60px rgba(0,255,148,0.35); }

.desc { font-size:13px;color:#5c6a84;line-height:1.85;max-width:440px;margin-bottom:28px;animation:fadeUp .6s .2s ease both; }
.desc strong { color:#8898b4;font-weight:500; }

.ticker-box {
  background:rgba(255,59,92,0.05);border:1px solid rgba(255,59,92,0.12);
  border-left:3px solid var(--red);border-radius:8px;padding:10px 16px;
  display:flex;align-items:center;gap:14px;overflow:hidden;
  animation:fadeUp .6s .25s ease both;position:relative;margin-bottom:24px;
}
.ticker-box::after {
  content:'';position:absolute;right:0;top:0;bottom:0;width:60px;
  background:linear-gradient(to right,transparent,rgba(6,9,16,0.95));pointer-events:none;
}
.ticker-label {
  font-family:'JetBrains Mono',monospace;font-size:9px;letter-spacing:2px;
  color:var(--red);white-space:nowrap;flex-shrink:0;display:flex;align-items:center;gap:6px;
}
.ticker-label::before { content:'';display:inline-block;width:6px;height:6px;border-radius:50%;background:var(--red);animation:blink 1s step-end infinite; }
.ticker-track { overflow:hidden;flex:1; }
.ticker-inner { display:flex;gap:48px;white-space:nowrap;animation:scroll 24s linear infinite; }
@keyframes scroll { from{transform:translateX(0)} to{transform:translateX(-50%)} }
.ticker-item { font-size:11px;color:#5c6a84; }
.ticker-item b { color:#8898b4;font-weight:600; }

.features { display:flex;flex-wrap:wrap;gap:8px;margin-bottom:24px;animation:fadeUp .6s .3s ease both; }
.feature-pill {
  display:flex;align-items:center;gap:6px;
  background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);
  padding:5px 11px;border-radius:20px;font-size:11px;color:#4d5d7a;transition:all .2s;
}
.feature-pill:hover { border-color:rgba(59,139,255,0.3);color:#8898b4; }
.feature-pill .dot { width:5px;height:5px;border-radius:50%;flex-shrink:0; }

.stats { display:flex;gap:0;animation:fadeUp .6s .35s ease both; }
.stat { flex:1;padding:16px 20px;border-right:1px solid rgba(255,255,255,0.05);position:relative;transition:background .2s; }
.stat:first-child { border-left:1px solid rgba(255,255,255,0.05); }
.stat:hover { background:rgba(59,139,255,0.04); }
.stat::before { content:'';position:absolute;top:0;left:0;right:0;height:1px;background:rgba(255,255,255,0.05); }
.stat-n { font-family:'Bebas Neue',sans-serif;font-size:34px;color:var(--blue);line-height:1; }
.stat-l { font-size:9px;color:#3a4d66;text-transform:uppercase;letter-spacing:1.5px;margin-top:3px; }

.footer-row { display:flex;justify-content:space-between;align-items:center; }
.footer-txt { font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--dim); }
.ph-badge {
  display:flex;align-items:center;gap:6px;
  font-family:'JetBrains Mono',monospace;font-size:9px;color:#2a3a52;
  border:1px solid rgba(255,255,255,0.04);border-radius:4px;padding:4px 10px;
}

/* ═══ RIGHT: Sign In Form ═══ */
.right {
  width: 500px; flex-shrink: 0;
  background: #060910;
  display: flex; align-items: center; justify-content: center;
  padding: 48px 52px;
  position: relative; z-index: 1;
}
.right::before {
  content: '';
  position: absolute; left: 0; top: 10%; bottom: 10%;
  width: 1px;
  background: linear-gradient(to bottom, transparent, rgba(59,139,255,0.15), rgba(176,97,255,0.15), transparent);
}

.form-box { width: 100%; }

.form-scanner {
  height: 2px; border-radius: 999px;
  background: linear-gradient(90deg, transparent, var(--blue), var(--purple), transparent);
  margin-bottom: 36px;
  animation: scanPulse 3s ease-in-out infinite;
}
@keyframes scanPulse { 0%,100% { opacity: 0.25; transform: scaleX(0.5); } 50% { opacity: 1; transform: scaleX(1); } }

.form-eyebrow {
  display: inline-flex; align-items: center; gap: 8px;
  font-family: 'JetBrains Mono', monospace; font-size: 9px; letter-spacing: 3px;
  color: var(--dim); text-transform: uppercase; margin-bottom: 16px;
}
.form-eyebrow::before { content: '//'; color: var(--blue); font-size: 12px; }

.form-title {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 3rem; letter-spacing: 2px; line-height: 0.9;
  margin-bottom: 10px;
  background: linear-gradient(135deg, #fff 30%, var(--blue) 100%);
  background-clip: text;
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
}
.form-sub {
  font-size: 12px; color: var(--muted); line-height: 1.7; margin-bottom: 28px;
}
.form-sub a {
  color: var(--blue); text-decoration: none; font-weight: 500;
  border-bottom: 1px solid rgba(59,139,255,0.3); transition: border-color 0.2s;
}
.form-sub a:hover { border-color: var(--blue); }

/* Role selector */
.role-label-row {
  font-family: 'JetBrains Mono', monospace; font-size: 9px; letter-spacing: 2px;
  color: var(--dim); text-transform: uppercase; margin-bottom: 8px;
}
.role-selector {
  display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;
  margin-bottom: 24px;
}
.role-btn {
  padding: 10px 8px; border-radius: 8px; cursor: pointer; text-align: center;
  border: 1px solid rgba(255,255,255,0.07);
  background: rgba(255,255,255,0.02);
  transition: all 0.2s; font-size: 11px; color: #3a4d66;
  font-family: 'Space Grotesk', sans-serif;
}
.role-btn .role-icon { display: block; font-size: 18px; margin-bottom: 4px; }
.role-btn:hover { border-color: rgba(59,139,255,0.3); color: #8898b4; }
.role-btn.active {
  border-color: rgba(59,139,255,0.4); background: rgba(59,139,255,0.08);
  color: var(--blue);
}

/* Fields */
.field-group { display: flex; flex-direction: column; gap: 14px; margin-bottom: 18px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field-label {
  font-family: 'JetBrains Mono', monospace; font-size: 9px; letter-spacing: 2px;
  color: var(--dim); text-transform: uppercase; display: flex; align-items: center; gap: 8px;
}
.field-label .required { color: var(--red); }
.input-wrap { position: relative; }
.input-icon {
  position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
  font-size: 15px; pointer-events: none; opacity: 0.5;
}
.field input {
  width: 100%; padding: 13px 14px 13px 42px;
  background: var(--input-bg);
  border: 1px solid var(--input-border);
  border-radius: 9px; color: var(--text);
  font-family: 'Space Grotesk', sans-serif; font-size: 14px;
  transition: all 0.2s; outline: none;
  -webkit-text-fill-color: var(--text);
}
.field input::placeholder { color: #2a3a52; }
.field input:focus {
  border-color: var(--blue);
  background: rgba(59,139,255,0.04);
  box-shadow: 0 0 0 3px rgba(59,139,255,0.1);
}
.pwd-toggle {
  position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer;
  font-size: 16px; opacity: 0.4; transition: opacity 0.2s; padding: 2px;
}
.pwd-toggle:hover { opacity: 0.8; }

.form-options {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 20px;
}
.checkbox-label {
  display: flex; align-items: center; gap: 8px; cursor: pointer;
  font-size: 12px; color: var(--muted); user-select: none;
}
.checkbox-label input[type="checkbox"] { display: none; }
.custom-check {
  width: 16px; height: 16px; border-radius: 4px;
  border: 1px solid rgba(255,255,255,0.12);
  background: rgba(255,255,255,0.03);
  display: flex; align-items: center; justify-content: center;
  transition: all 0.2s; font-size: 10px; flex-shrink: 0;
}
.checkbox-label input:checked + .custom-check { background: var(--blue); border-color: var(--blue); color: #fff; }
.forgot-link {
  font-size: 10px; color: var(--muted); text-decoration: none;
  font-family: 'JetBrains Mono', monospace; letter-spacing: 1px;
  transition: color 0.2s;
}
.forgot-link:hover { color: var(--blue); }

.btn-submit {
  width: 100%; padding: 15px;
  background: linear-gradient(135deg, var(--blue) 0%, #5b6fff 100%);
  color: #fff; font-weight: 700; font-size: 15px; letter-spacing: 0.5px;
  border: none; border-radius: 10px; cursor: pointer;
  font-family: 'Space Grotesk', sans-serif;
  box-shadow: 0 4px 28px rgba(59,139,255,0.3);
  transition: all .2s; display: flex; align-items: center; justify-content: center; gap: 10px;
  position: relative; overflow: hidden;
}
.btn-submit::before {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
  opacity: 0; transition: opacity 0.2s;
}
.btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 40px rgba(59,139,255,0.45); }
.btn-submit:hover::before { opacity: 1; }
.btn-submit:active { transform: none; }
.submit-arrow { transition: transform 0.2s; }
.btn-submit:hover .submit-arrow { transform: translateX(4px); }

.status-bar {
  display: flex; align-items: center; gap: 8px;
  margin-top: 16px; padding: 10px 14px;
  background: rgba(0,255,148,0.05); border: 1px solid rgba(0,255,148,0.12);
  border-radius: 7px; font-size: 11px; color: #3a4d66;
  font-family: 'JetBrains Mono', monospace;
}
.status-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--green); animation: blink 2s step-end infinite; flex-shrink: 0; }

/* Error & Alert */
.field.error input { border-color: rgba(255,59,92,0.5); box-shadow: 0 0 0 3px rgba(255,59,92,0.1); }
.field-error {
  font-family: 'JetBrains Mono', monospace; font-size: 9px; color: var(--red);
  letter-spacing: 1px; display: none;
}
.field.error .field-error { display: block; }
.alert {
  padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;
  font-size: 12px; display: none; align-items: center; gap: 10px;
}
.alert.show { display: flex; }
.alert.success { background: rgba(0,255,148,0.07); border: 1px solid rgba(0,255,148,0.2); color: var(--green); }
.alert.error   { background: rgba(255,59,92,0.07);  border: 1px solid rgba(255,59,92,0.2);  color: var(--red); }

/* Auth Toggle */
.auth-toggle {
  display: flex;
  align-items: center;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 12px;
  padding: 5px;
  gap: 4px;
  margin-bottom: 24px;
  width: 100%;
}
.auth-tab {
  flex: 1;
  padding: 11px 0;
  text-align: center;
  border-radius: 8px;
  cursor: pointer;
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--muted);
  background: transparent;
  border: none;
  transition: all 0.22s ease;
  user-select: none;
}
.auth-tab.active {
  background: rgba(59,139,255,0.15);
  color: var(--blue);
  box-shadow: 0 0 0 1px rgba(59,139,255,0.3);
}

/* Signup button green tint */
.btn-signup-submit {
  background: linear-gradient(135deg, #00c97a 0%, #00a362 100%);
  box-shadow: 0 4px 28px rgba(0,201,122,0.25);
}
.btn-signup-submit:hover { box-shadow: 0 8px 40px rgba(0,201,122,0.4); }

/* OTP Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(3, 5, 8, 0.95);
  backdrop-filter: blur(8px);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  animation: fadeIn 0.3s ease;
}

.modal-overlay.active {
  display: flex;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.modal-container {
  width: 100%;
  max-width: 420px;
  background: #0a0e17;
  border: 1px solid rgba(59, 139, 255, 0.2);
  border-radius: 24px;
  padding: 32px;
  position: relative;
  overflow: hidden;
  animation: slideUp 0.4s ease;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(59, 139, 255, 0.2);
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal-container::before {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle at center, rgba(59, 139, 255, 0.1), transparent 70%);
  animation: rotate 20s linear infinite;
  z-index: 0;
}

@keyframes rotate {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.modal-content {
  position: relative;
  z-index: 1;
}

.modal-icon {
  width: 64px;
  height: 64px;
  background: linear-gradient(135deg, var(--blue), var(--purple));
  border-radius: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
  margin: 0 auto 24px;
  animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(59, 139, 255, 0.4); }
  50% { box-shadow: 0 0 0 20px rgba(59, 139, 255, 0); }
}

.modal-title {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 32px;
  text-align: center;
  margin-bottom: 8px;
  background: linear-gradient(135deg, #fff, var(--blue));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.modal-subtitle {
  text-align: center;
  color: var(--muted);
  font-size: 13px;
  margin-bottom: 28px;
  font-family: 'JetBrains Mono', monospace;
  letter-spacing: 0.5px;
}

.modal-subtitle strong {
  color: var(--blue);
  font-weight: 500;
}

.otp-input-group {
  display: flex;
  gap: 12px;
  justify-content: center;
  margin-bottom: 28px;
}

.otp-digit {
  width: 56px;
  height: 64px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 12px;
  font-family: 'JetBrains Mono', monospace;
  font-size: 28px;
  font-weight: 600;
  color: var(--text);
  text-align: center;
  outline: none;
  transition: all 0.2s;
}

.otp-digit:focus {
  border-color: var(--blue);
  background: rgba(59, 139, 255, 0.05);
  box-shadow: 0 0 0 3px rgba(59, 139, 255, 0.1);
}

.otp-digit:valid {
  border-color: var(--green);
}

.otp-timer {
  text-align: center;
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px;
  color: var(--muted);
  margin-bottom: 20px;
  padding: 8px;
  background: rgba(0, 255, 148, 0.05);
  border: 1px solid rgba(0, 255, 148, 0.1);
  border-radius: 8px;
}

.otp-timer.expiring {
  color: var(--red);
  border-color: rgba(255, 59, 92, 0.3);
}

.otp-actions {
  display: flex;
  gap: 12px;
  margin-top: 20px;
}

.otp-btn {
  flex: 1;
  padding: 14px;
  border: none;
  border-radius: 10px;
  font-family: 'Space Grotesk', sans-serif;
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.otp-btn-primary {
  background: linear-gradient(135deg, var(--blue), #5b6fff);
  color: white;
  box-shadow: 0 4px 20px rgba(59, 139, 255, 0.3);
}

.otp-btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 30px rgba(59, 139, 255, 0.4);
}

.otp-btn-secondary {
  background: transparent;
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: var(--muted);
}

.otp-btn-secondary:hover:not(:disabled) {
  border-color: var(--blue);
  color: var(--blue);
  background: rgba(59, 139, 255, 0.05);
}

.otp-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
  transform: none !important;
}

.modal-close {
  position: absolute;
  top: 20px;
  right: 20px;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.08);
  color: var(--muted);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 16px;
  z-index: 2;
}

.modal-close:hover {
  border-color: var(--red);
  color: var(--red);
  background: rgba(255, 59, 92, 0.05);
}

.otp-info {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 16px;
  padding: 12px;
  background: rgba(59, 139, 255, 0.05);
  border: 1px solid rgba(59, 139, 255, 0.1);
  border-radius: 8px;
  font-size: 11px;
  color: var(--muted);
  font-family: 'JetBrains Mono', monospace;
}

.otp-info-icon {
  color: var(--blue);
  font-size: 14px;
}

@media (max-width: 480px) {
  .modal-container {
    margin: 20px;
    padding: 24px;
  }
  
  .otp-digit {
    width: 45px;
    height: 54px;
    font-size: 24px;
  }
}

@media (max-width: 900px) {
  body { flex-direction: column; }
  .left { padding: 32px; border-right: none; border-bottom: 1px solid var(--border); }
  .hero { padding: 24px 0 16px; }
  .right { width: 100%; padding: 40px 32px; }
}
@media (max-width: 480px) {
  .left { padding: 24px 20px; }
  .right { padding: 32px 20px; }
  .stats { flex-wrap: wrap; }
  .stat { min-width: 50%; }
}
</style>
</head>
<body>
<div class="orb1"></div>
<div class="orb2"></div>
<div class="orb3"></div>

<!-- ═══ LEFT: Hero ═══ -->
<div class="left">
  <div class="logo">
    <div class="logo-icon">🛡️</div>
    <div class="logo-name">CYBERSHIELD</div>
    <div class="logo-tag">v2.0 · PH Edition</div>
  </div>

  <div class="hero">
    <div class="eyebrow">
      <span class="eyebrow-dot"></span>
      LIVE THREAT MONITORING
    </div>

    <div class="headline">
      KNOW YOUR<br>
      <span class="blue">CYBER</span><br>
      <span class="green">RISK</span>
    </div>

    <p class="desc">
      AI-powered cyber hygiene assessment built for <strong>Philippine e-commerce sellers</strong>.
      Answer 30 questions, receive your risk rank (A–D), and get personalized
      recommendations to protect your store, customers, and data.
    </p>

    <div class="ticker-box">
      <span class="ticker-label">THREATS</span>
      <div class="ticker-track">
        <div class="ticker-inner">
          <span class="ticker-item"><b>Phishing</b> — #1 attack vector for SME sellers</span>
          <span class="ticker-item"><b>Ransomware</b> — avg ₱2.4M recovery cost</span>
          <span class="ticker-item"><b>Data Breach</b> — 81% caused by weak passwords</span>
          <span class="ticker-item"><b>Card Fraud</b> — 40% rise in e-commerce scams</span>
          <span class="ticker-item"><b>Supply Chain</b> — compromised plugins up 350% YoY</span>
          <span class="ticker-item"><b>Account Takeover</b> — 3x increase in PH marketplace sellers</span>
          <span class="ticker-item"><b>Phishing</b> — #1 attack vector for SME sellers</span>
          <span class="ticker-item"><b>Ransomware</b> — avg ₱2.4M recovery cost</span>
          <span class="ticker-item"><b>Data Breach</b> — 81% caused by weak passwords</span>
          <span class="ticker-item"><b>Card Fraud</b> — 40% rise in e-commerce scams</span>
          <span class="ticker-item"><b>Supply Chain</b> — compromised plugins up 350% YoY</span>
          <span class="ticker-item"><b>Account Takeover</b> — 3x increase in PH marketplace sellers</span>
        </div>
      </div>
    </div>

    <div class="features">
      <div class="feature-pill"><span class="dot" style="background:#00ff94"></span>NPC Compliant</div>
      <div class="feature-pill"><span class="dot" style="background:#3b8bff"></span>AI-Powered</div>
      <div class="feature-pill"><span class="dot" style="background:#b061ff"></span>30 Questions</div>
      <div class="feature-pill"><span class="dot" style="background:#ffa500"></span>Free Assessment</div>
      <div class="feature-pill"><span class="dot" style="background:#ff3b5c"></span>Instant Results</div>
    </div>

    <div class="stats">
      <div class="stat"><div class="stat-n">30</div><div class="stat-l">Questions</div></div>
      <div class="stat"><div class="stat-n">10</div><div class="stat-l">Categories</div></div>
      <div class="stat"><div class="stat-n">A–D</div><div class="stat-l">Risk Ranks</div></div>
      <div class="stat"><div class="stat-n">AI</div><div class="stat-l">Powered</div></div>
    </div>
  </div>

  <div class="footer-row">
    <div class="footer-txt">© 2025 CyberShield · For Philippine E-Commerce Sellers</div>
    <div class="ph-badge">🇵🇭 PH · NPC Aligned</div>
  </div>
</div>

<!-- ═══ RIGHT: Auth Panel ═══ -->
<div class="right">
  <div class="form-box">
    <div class="form-scanner"></div>
    <div class="form-eyebrow">Authentication</div>

    <!-- Pill Tab Switcher -->
    <div class="auth-toggle">
      <div class="auth-tab active" id="tab-signin" onclick="switchTab('signin')">Sign In</div>
      <div class="auth-tab" id="tab-signup" onclick="switchTab('signup')">Sign Up</div>
    </div>

    <div class="alert" id="alert"></div>

    <!-- ── SIGN IN FORM ── -->
    <div id="form-signin">
      <div class="form-title">SIGN IN<br>TO PORTAL</div>
      <p class="form-sub">No account yet? <a href="#" onclick="switchTab('signup'); return false;">Create one here</a></p>

      <form id="signin-form" onsubmit="return handleSignIn(event)">
        <div class="field-group">
          <div class="field" id="si-field-user">
            <div class="field-label">Username <span class="required">*</span></div>
            <div class="input-wrap">
              <span class="input-icon">👤</span>
              <input type="text" id="si-username" name="username" placeholder="Enter your username" autocomplete="username">
            </div>
            <div class="field-error">Username is required.</div>
          </div>
          <div class="field" id="si-field-pass">
            <div class="field-label">Password <span class="required">*</span></div>
            <div class="input-wrap">
              <span class="input-icon">🔑</span>
              <input type="password" id="si-password" name="password" placeholder="Enter your password" autocomplete="current-password">
              <button class="pwd-toggle" type="button" onclick="togglePwd('si-password', this)">👁</button>
            </div>
            <div class="field-error">Password is required.</div>
          </div>
        </div>

        <div class="form-options">
          <label class="checkbox-label">
            <input type="checkbox" id="remember">
            <span class="custom-check"></span>
            Remember me
          </label>
          <a href="#" class="forgot-link">Forgot password?</a>
        </div>

        <button type="submit" class="btn-submit">
          Sign In &nbsp;<span class="submit-arrow">→</span>
        </button>
      </form>
    </div>

    <!-- ── SIGN UP FORM ── -->
    <div id="form-signup" style="display:none;">
      <div class="form-title">CREATE ACCOUNT<br>FOR FREE</div>
      <p class="form-sub">Already have an account? <a href="#" onclick="switchTab('signin'); return false;">Sign in here</a></p>

      <form id="signup-form" onsubmit="return handleSignUp(event)">
        <div class="field-group">
          <div class="field" id="su-field-fullname">
            <div class="field-label">Full Name <span class="required">*</span></div>
            <div class="input-wrap">
              <span class="input-icon">🪪</span>
              <input type="text" id="su-fullname" name="fullname" placeholder="Enter your full name" autocomplete="name">
            </div>
            <div class="field-error">Full name is required.</div>
          </div>
          <div class="field" id="su-field-email">
            <div class="field-label">Email Address <span class="required">*</span></div>
            <div class="input-wrap">
              <span class="input-icon">✉️</span>
              <input type="email" id="su-email" name="email" placeholder="you@example.com" autocomplete="email">
            </div>
            <div class="field-error">Valid email is required.</div>
          </div>
          <div class="field" id="su-field-store">
            <div class="field-label">Store / Business Name <span class="required">*</span></div>
            <div class="input-wrap">
              <span class="input-icon">🏪</span>
              <input type="text" id="su-store" name="store" placeholder="Your shop name">
            </div>
            <div class="field-error">Store name is required.</div>
          </div>
          <div class="field" id="su-field-pass">
            <div class="field-label">Password <span class="required">*</span></div>
            <div class="input-wrap">
              <span class="input-icon">🔑</span>
              <input type="password" id="su-password" name="password" placeholder="Min. 8 characters" autocomplete="new-password">
              <button class="pwd-toggle" type="button" onclick="togglePwd('su-password', this)">👁</button>
            </div>
            <div class="field-error">Password must be at least 8 characters.</div>
          </div>
          <div class="field" id="su-field-confirm">
            <div class="field-label">Confirm Password <span class="required">*</span></div>
            <div class="input-wrap">
              <span class="input-icon">🔒</span>
              <input type="password" id="su-confirm" name="confirm" placeholder="Repeat your password" autocomplete="new-password">
              <button class="pwd-toggle" type="button" onclick="togglePwd('su-confirm', this)">👁</button>
            </div>
            <div class="field-error">Passwords do not match.</div>
          </div>
        </div>

        <label class="checkbox-label" style="margin-bottom:20px;">
          <input type="checkbox" id="su-agree">
          <span class="custom-check"></span>
          I agree to the <a href="#" style="color:var(--blue);text-decoration:none;border-bottom:1px solid rgba(59,139,255,0.3);">Terms &amp; Privacy Policy</a>
        </label>

        <button type="submit" class="btn-submit btn-signup-submit">
          Create Account &nbsp;<span class="submit-arrow">→</span>
        </button>

        <div class="status-bar">
          <span class="status-dot"></span>
          Your data is protected · NPC &amp; RA 10173 Aligned
        </div>
      </form>
    </div>

  </div>
</div>

<!-- ═══ OTP MODAL (Non-Functional Demo Version) ═══ -->
<div class="modal-overlay" id="otpModal">
  <div class="modal-container">
    <div class="modal-close" onclick="closeOTPModal()">✕</div>
    <div class="modal-content">
      <div class="modal-icon">🔐</div>
      <h2 class="modal-title">Two-Factor Authentication</h2>
      <p class="modal-subtitle">Enter the 6-digit code sent to <strong id="otpUserEmail">your device</strong></p>
      
      <div class="otp-input-group">
        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" id="otp1" onkeyup="handleOTPInput(event, 1)" onkeydown="handleOTPBackspace(event, 1)">
        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" id="otp2" onkeyup="handleOTPInput(event, 2)" onkeydown="handleOTPBackspace(event, 2)">
        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" id="otp3" onkeyup="handleOTPInput(event, 3)" onkeydown="handleOTPBackspace(event, 3)">
        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" id="otp4" onkeyup="handleOTPInput(event, 4)" onkeydown="handleOTPBackspace(event, 4)">
        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" id="otp5" onkeyup="handleOTPInput(event, 5)" onkeydown="handleOTPBackspace(event, 5)">
        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" id="otp6" onkeyup="handleOTPInput(event, 6)" onkeydown="handleOTPBackspace(event, 6)">
      </div>
      
      <div class="otp-timer" id="otpTimer">
        Code expires in <span id="timerSeconds">05:00</span>
      </div>
      
      <div class="otp-info">
        <span class="otp-info-icon">ℹ️</span>
        <span>Enter each digit in the boxes above - type continuously</span>
      </div>
      
      <div class="otp-actions">
        <button class="otp-btn otp-btn-secondary" onclick="resendOTP()" id="resendBtn">
          <span>↻</span> Resend
        </button>
        <button class="otp-btn otp-btn-primary" onclick="verifyOTP()" id="verifyBtn">
          Verify &nbsp;<span class="submit-arrow">→</span>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// OTP Modal state
let currentUserId = null;
let currentUsername = null;
let currentUserRole = null;
let currentUserFullName = null;
let timerInterval = null;
let timeLeft = 300; // 5 minutes in seconds

function switchTab(mode) {
  const isSignup = mode === 'signup';
  document.getElementById('tab-signin').classList.toggle('active', !isSignup);
  document.getElementById('tab-signup').classList.toggle('active', isSignup);
  document.getElementById('form-signin').style.display = isSignup ? 'none' : 'block';
  document.getElementById('form-signup').style.display = isSignup ? 'block' : 'none';
  document.getElementById('alert').className = 'alert';
}

function togglePwd(inputId, btn) {
  const input = document.getElementById(inputId);
  if (input.type === 'password') { input.type = 'text'; btn.textContent = '🙈'; }
  else { input.type = 'password'; btn.textContent = '👁'; }
}

function showAlert(type, msg) {
  const el = document.getElementById('alert');
  el.textContent = (type === 'success' ? '✅ ' : '⚠ ') + msg;
  el.className = 'alert show ' + type;
}

function clearErrors(...ids) {
  ids.forEach(id => document.getElementById(id).classList.remove('error'));
}

function handleSignIn(event) {
  event.preventDefault();
  
  const form = document.getElementById('signin-form');
  const formData = new FormData(form);
  
  clearErrors('si-field-user', 'si-field-pass');
  document.getElementById('alert').className = 'alert';

  let ok = true;
  if (!formData.get('username')) { 
    document.getElementById('si-field-user').classList.add('error'); ok = false; 
  }
  if (!formData.get('password')) { 
    document.getElementById('si-field-pass').classList.add('error'); ok = false; 
  }
  if (!ok) return false;

  // Add action to form data
  formData.append('action', 'login');

  fetch('', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(result => {
    if (result.success) {
      if (result.require_otp) {
        // Store user data
        currentUserId = result.user_id;
        currentUsername = result.username;
        currentUserRole = result.role;
        currentUserFullName = result.full_name;
        
        // Update OTP display
        document.getElementById('otpUserEmail').textContent = result.username;
        
        // Reset OTP inputs
        for (let i = 1; i <= 6; i++) {
          document.getElementById(`otp${i}`).value = '';
        }
        
        // Show modal
        document.getElementById('otpModal').classList.add('active');
        
        // Setup OTP input and focus
        setupOTPInput();
        
        // Start timer
        startOTPTimer();
        
        showAlert('success', result.message);
      } else {
        showAlert('success', result.message);
        setTimeout(() => {
          window.location.href = result.redirect_url;
        }, 1800);
      }
    } else {
      showAlert('error', result.message);
    }
  })
  .catch(error => {
    console.error('Login error:', error);
    showAlert('error', 'Network error. Please check your connection and try again.');
  });

  return false;
}

function handleSignUp(event) {
  event.preventDefault();
  
  const form = document.getElementById('signup-form');
  const formData = new FormData(form);
  
  clearErrors('su-field-fullname','su-field-email','su-field-store','su-field-pass','su-field-confirm');
  document.getElementById('alert').className = 'alert';

  let ok = true;
  if (!formData.get('fullname')) { 
    document.getElementById('su-field-fullname').classList.add('error'); ok = false; 
  }
  if (!formData.get('email') || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.get('email'))) { 
    document.getElementById('su-field-email').classList.add('error'); ok = false; 
  }
  if (!formData.get('store')) { 
    document.getElementById('su-field-store').classList.add('error'); ok = false; 
  }
  if (formData.get('password').length < 8) { 
    document.getElementById('su-field-pass').classList.add('error'); ok = false; 
  }
  if (formData.get('password') !== formData.get('confirm')) { 
    document.getElementById('su-field-confirm').classList.add('error'); ok = false; 
  }
  if (!ok) return false;

  if (!document.getElementById('su-agree').checked) { 
    showAlert('error', 'Please agree to the Terms & Privacy Policy.'); 
    return false; 
  }

  // Add action to form data
  formData.append('action', 'register');

  fetch('', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(result => {
    if (result.success) {
      showAlert('success', result.message);
      setTimeout(() => {
        window.location.href = result.redirect_url;
      }, 1800);
    } else {
      showAlert('error', result.message);
    }
  })
  .catch(error => {
    console.error('Registration error:', error);
    showAlert('error', 'Network error. Please check your connection and try again.');
  });

  return false;
}

// OTP Functions (Non-Functional Demo Version)
function handleOTPInput(event, boxNumber) {
  const input = event.target;
  const value = input.value;
  
  // Only allow numbers
  input.value = value.replace(/[^0-9]/g, '');
  
  // Move to next box if a digit was entered
  if (input.value.length === 1 && boxNumber < 6) {
    document.getElementById(`otp${boxNumber + 1}`).focus();
  }
  
  // Auto-verify when all 6 digits are filled
  if (boxNumber === 6) {
    let allFilled = true;
    for (let i = 1; i <= 6; i++) {
      if (!document.getElementById(`otp${i}`).value) {
        allFilled = false;
        break;
      }
    }
    if (allFilled) {
      setTimeout(() => verifyOTP(), 500);
    }
  }
}

function handleOTPBackspace(event, boxNumber) {
  const input = event.target;
  
  // Handle backspace
  if (event.key === 'Backspace' && !input.value && boxNumber > 1) {
    event.preventDefault();
    document.getElementById(`otp${boxNumber - 1}`).focus();
  }
}

function setupOTPInput() {
  // Focus first box when modal opens
  document.getElementById('otp1').focus();
}

function verifyOTP() {
  // Collect OTP digits
  let otpCode = '';
  for (let i = 1; i <= 6; i++) {
    otpCode += document.getElementById(`otp${i}`).value;
  }
  
  if (otpCode.length !== 6) {
    showAlert('error', 'Please enter the complete 6-digit OTP');
    return;
  }
  
  // Show loading state
  const verifyBtn = document.getElementById('verifyBtn');
  const originalText = verifyBtn.innerHTML;
  verifyBtn.innerHTML = 'Verifying...';
  verifyBtn.disabled = true;
  
  // Verify OTP and set session
  const formData = new FormData();
  formData.append('action', 'verify_otp');
  formData.append('user_id', currentUserId);
  formData.append('username', currentUsername);
  formData.append('role', currentUserRole);
  
  fetch('', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(result => {
    // Close modal
    closeOTPModal();
    
    // Show success and redirect
    showAlert('success', 'OTP verified successfully! Redirecting...');
    
    // Use redirect URL from server response or fallback to client-side logic
    let redirect_url = result.redirect_url || (
      currentUserRole === 'Admin' ? 
        'http://localhost/security/Admin/dashboard.php' : 
        'http://localhost/security/Client/index.html?role=' + currentUserRole.toLowerCase() + '&user=' + currentUsername
    );
    
    setTimeout(() => {
      window.location.href = redirect_url;
    }, 1800);
    
    // Reset button (though modal is closed)
    verifyBtn.innerHTML = originalText;
    verifyBtn.disabled = false;
  })
  .catch(error => {
    console.error('OTP verification error:', error);
    showAlert('error', 'Verification failed. Please try again.');
    verifyBtn.innerHTML = originalText;
    verifyBtn.disabled = false;
  });
}

function resendOTP() {
  const resendBtn = document.getElementById('resendBtn');
  const originalText = resendBtn.innerHTML;
  resendBtn.innerHTML = 'Sending...';
  resendBtn.disabled = true;
  
  // Simulate resend delay
  setTimeout(() => {
    // Reset timer
    startOTPTimer();
    
    // Clear inputs
    for (let i = 1; i <= 6; i++) {
      document.getElementById(`otp${i}`).value = '';
    }
    document.getElementById('otp1').focus();
    
    // Reset button states
    document.getElementById('verifyBtn').disabled = false;
    document.getElementById('otpTimer').classList.remove('expiring');
    
    showAlert('success', 'New OTP has been sent to your device');
    
    resendBtn.innerHTML = originalText;
    resendBtn.disabled = false;
  }, 1000);
}

function closeOTPModal() {
  document.getElementById('otpModal').classList.remove('active');
  if (timerInterval) {
    clearInterval(timerInterval);
  }
}

function startOTPTimer() {
  if (timerInterval) {
    clearInterval(timerInterval);
  }
  
  timeLeft = 300; // Reset to 5 minutes
  updateTimerDisplay();
  
  timerInterval = setInterval(() => {
    timeLeft--;
    updateTimerDisplay();
    
    if (timeLeft <= 0) {
      clearInterval(timerInterval);
      document.getElementById('otpTimer').classList.add('expiring');
      document.getElementById('timerSeconds').textContent = 'Expired';
      document.getElementById('resendBtn').disabled = false;
      document.getElementById('verifyBtn').disabled = true;
      showAlert('error', 'OTP has expired. Please request a new one.');
    } else if (timeLeft < 60) {
      document.getElementById('otpTimer').classList.add('expiring');
    }
  }, 1000);
}

function updateTimerDisplay() {
  const minutes = Math.floor(timeLeft / 60);
  const seconds = timeLeft % 60;
  document.getElementById('timerSeconds').textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
}

// Close modal when clicking outside
document.getElementById('otpModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeOTPModal();
  }
});

document.addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    const isSignup = document.getElementById('form-signup').style.display !== 'none';
    if (isSignup) {
      document.getElementById('signup-form').dispatchEvent(new Event('submit'));
    } else {
      document.getElementById('signin-form').dispatchEvent(new Event('submit'));
    }
  }
  
  // Close modal on Escape key
  if (e.key === 'Escape' && document.getElementById('otpModal').classList.contains('active')) {
    closeOTPModal();
  }
});

document.getElementById('remember').addEventListener('change', function() {
  this.parentElement.querySelector('.custom-check').textContent = this.checked ? '✓' : '';
});
document.getElementById('su-agree').addEventListener('change', function() {
  this.parentElement.querySelector('.custom-check').textContent = this.checked ? '✓' : '';
});
</script>
</body>
</html>