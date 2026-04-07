<?php
// ============================================================
// CyberShield — Landing / Auth Page
// Fixes applied:
//   1. session_start() moved to top of file
//   2. OTP brute-force attempt limiting (max 5 per session)
//   3. Email not leaked in login JSON response
//   4. BASE_URL constant replaces hardcoded localhost
//   5. mt_rand() replaced with random_int() in generateOTP (in email_config)
//   6. Error details not exposed in PDO catch blocks in production
// ============================================================
session_start();

require_once 'includes/config.php';
require_once 'includes/audit_helper.php';
require_once 'includes/email_config.php';

// Define base URL once — change this when deploying
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/security');
}

// ── Helper: build redirect URL by role ──────────────────────
function buildRedirectUrl(string $role, string $username): string
{
    if ($role === 'Admin') {
        return BASE_URL . '/Admin/dashboard.php';
    }
    return BASE_URL . '/Client/index.php?role=' . strtolower($role) . '&user=' . urlencode($username);
}

// ── Helper: mask email for display (user@example.com → u***@example.com) ──
function maskEmail(string $email): string
{
    [$local, $domain] = explode('@', $email, 2);
    $masked = substr($local, 0, 1) . str_repeat('*', max(1, strlen($local) - 1));
    return $masked . '@' . $domain;
}

// ── Helper: OTP attempt tracking ────────────────────────────
function incrementOtpAttempts(string $key): int
{
    $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;
    return $_SESSION[$key];
}

function resetOtpAttempts(string $key): void
{
    unset($_SESSION[$key]);
}

// ============================================================
// Handle POST requests
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ── LOGIN ───────────────────────────────────────────────
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
                echo json_encode(['success' => false, 'message' => 'Database connection failed']);
                exit;
            }

            $stmt = $db->prepare(
                "SELECT id, username, password_hash, email, full_name, store_name, role
                 FROM users WHERE username = ? AND is_active = 1"
            );
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                // Reset any previous OTP state
                unset($_SESSION['login_otp'], $_SESSION['login_otp_time'], $_SESSION['login_otp_attempts']);

                $otpCode = generateOTP();
                $_SESSION['login_otp'] = $otpCode;
                $_SESSION['login_otp_time'] = time();
                $_SESSION['login_user_id'] = $user['id'];   // store server-side — not trusted from client
                $_SESSION['login_username'] = $user['username'];
                $_SESSION['login_role'] = $user['role'];
                $_SESSION['login_fullname'] = $user['full_name'];

                $emailSent = sendOTPEmail($user['email'], $user['full_name'], $otpCode);

                // Do NOT send email address back to the client
                $maskedEmail = maskEmail($user['email']);

                if ($emailSent) {
                    $msg = 'A 6-digit code was sent to ' . $maskedEmail;
                } else {
                    $msg = 'Email delivery failed. Please contact support or try again.';
                    // In development you can temporarily show the OTP:
                    // $msg = 'DEV ONLY — OTP: ' . $otpCode;
                }

                echo json_encode([
                    'success' => true,
                    'require_otp' => true,
                    'email_hint' => $maskedEmail,   // safe masked hint for the UI
                    'message' => $msg,
                ]);
            } else {
                // Generic message — do not reveal whether username or password was wrong
                echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
            }

        } catch (PDOException $e) {
            error_log('[CyberShield] Login DB error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'A database error occurred. Please try again.']);
        }
        exit;
    }

    // ── VERIFY LOGIN OTP ────────────────────────────────────
    if ($_POST['action'] === 'verify_otp') {
        $otp = sanitizeInput($_POST['otp'] ?? '');

        if (strlen($otp) !== 6 || !ctype_digit($otp)) {
            echo json_encode(['success' => false, 'message' => 'Invalid OTP format']);
            exit;
        }

        if (!isset($_SESSION['login_otp'], $_SESSION['login_otp_time'])) {
            echo json_encode(['success' => false, 'message' => 'OTP session expired. Please log in again.']);
            exit;
        }

        // Check expiry (5 minutes)
        if (time() - $_SESSION['login_otp_time'] > 300) {
            unset($_SESSION['login_otp'], $_SESSION['login_otp_time']);
            resetOtpAttempts('login_otp_attempts');
            echo json_encode(['success' => false, 'message' => 'OTP expired. Please log in again.']);
            exit;
        }

        // Brute-force guard
        $attempts = incrementOtpAttempts('login_otp_attempts');
        if ($attempts > 5) {
            unset(
                $_SESSION['login_otp'],
                $_SESSION['login_otp_time'],
                $_SESSION['login_user_id'],
                $_SESSION['login_username'],
                $_SESSION['login_role'],
                $_SESSION['login_fullname']
            );
            resetOtpAttempts('login_otp_attempts');
            echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Please log in again.']);
            exit;
        }

        // Use hash_equals to prevent timing attacks
        if (!hash_equals($_SESSION['login_otp'], $otp)) {
            $remaining = 5 - $attempts;
            echo json_encode([
                'success' => false,
                'message' => "Invalid OTP. {$remaining} attempt(s) remaining.",
            ]);
            exit;
        }

        // OTP valid — clear OTP state and complete login
        $userId = $_SESSION['login_user_id'];
        $username = $_SESSION['login_username'];
        $role = $_SESSION['login_role'];
        $fullname = $_SESSION['login_fullname'];

        unset(
            $_SESSION['login_otp'],
            $_SESSION['login_otp_time'],
            $_SESSION['login_user_id'],
            $_SESSION['login_username'],
            $_SESSION['login_role'],
            $_SESSION['login_fullname']
        );
        resetOtpAttempts('login_otp_attempts');

        try {
            $database = new Database();
            $db = $database->getConnection();

            // Re-verify user still exists and is active
            $stmt = $db->prepare("SELECT id, username, role, full_name FROM users WHERE id = ? AND username = ? AND is_active = 1");
            $stmt->execute([$userId, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['logged_in'] = true;

                createAuditLog($db, $user['id'], 'login', 'User logged in successfully with 2FA', getClientIP(), getClientUserAgent());

                echo json_encode([
                    'success' => true,
                    'message' => 'OTP verified successfully',
                    'redirect_url' => buildRedirectUrl($user['role'], $user['username']),
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'User account not found or inactive']);
            }

        } catch (PDOException $e) {
            error_log('[CyberShield] Verify OTP DB error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'A database error occurred. Please try again.']);
        }
        exit;
    }

    // ── RESEND OTP ──────────────────────────────────────────
    if ($_POST['action'] === 'resend_otp') {
        $mode = sanitizeInput($_POST['mode'] ?? '');

        try {
            if ($mode === 'signup') {
                if (empty($_SESSION['pending_signup'])) {
                    echo json_encode(['success' => false, 'message' => 'Session expired. Please sign up again.']);
                    exit;
                }
                $pending = $_SESSION['pending_signup'];
                $otpCode = generateOTP();
                $_SESSION['signup_otp'] = $otpCode;
                $_SESSION['signup_otp_time'] = time();
                unset($_SESSION['signup_otp_attempts']);

                $emailSent = sendOTPEmail($pending['email'], $pending['fullname'], $otpCode);

                if ($emailSent) {
                    echo json_encode(['success' => true, 'message' => 'New OTP sent to ' . maskEmail($pending['email'])]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to send email. Please try again.']);
                }

            } else {
                // For login — use server-side session data only (don't trust client-sent user_id/username)
                if (empty($_SESSION['login_user_id'])) {
                    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
                    exit;
                }

                $database = new Database();
                $db = $database->getConnection();
                $stmt = $db->prepare(
                    "SELECT id, username, email, full_name FROM users WHERE id = ? AND username = ? AND is_active = 1"
                );
                $stmt->execute([$_SESSION['login_user_id'], $_SESSION['login_username']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $otpCode = generateOTP();
                    $_SESSION['login_otp'] = $otpCode;
                    $_SESSION['login_otp_time'] = time();
                    unset($_SESSION['login_otp_attempts']);

                    $emailSent = sendOTPEmail($user['email'], $user['full_name'], $otpCode);

                    if ($emailSent) {
                        echo json_encode(['success' => true, 'message' => 'New OTP sent to ' . maskEmail($user['email'])]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to send email. Please try again.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                }
            }

        } catch (PDOException $e) {
            error_log('[CyberShield] Resend OTP DB error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
        } catch (Exception $e) {
            error_log('[CyberShield] Resend OTP error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
        }
        exit;
    }

    // ── REGISTER ────────────────────────────────────────────
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
            echo json_encode(['success' => false, 'message' => 'A valid email address is required']);
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

            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Email address already registered']);
                exit;
            }

            // Generate unique username from email local part
            $base_username = preg_replace('/[^a-z0-9_]/', '', strtolower(explode('@', $email)[0]));
            if (empty($base_username))
                $base_username = 'user';
            $username = $base_username;
            $counter = 1;
            while (true) {
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if (!$stmt->fetch())
                    break;
                $username = $base_username . $counter++;
            }

            $_SESSION['pending_signup'] = [
                'fullname' => $fullname,
                'email' => $email,
                'store' => $store,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'username' => $username,
            ];

            $otpCode = generateOTP();
            $_SESSION['signup_otp'] = $otpCode;
            $_SESSION['signup_otp_time'] = time();
            unset($_SESSION['signup_otp_attempts']);

            $emailSent = sendOTPEmail($email, $fullname, $otpCode);

            if ($emailSent) {
                $msg = 'OTP sent to ' . maskEmail($email) . '. Please check your inbox.';
            } else {
                $msg = 'Email delivery failed. Please try resending or contact support.';
            }

            echo json_encode([
                'success' => true,
                'require_otp' => true,
                'email' => maskEmail($email),   // masked — safe to expose
                'message' => $msg,
            ]);

        } catch (PDOException $e) {
            error_log('[CyberShield] Register DB error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
        }
        exit;
    }

    // ── VERIFY SIGNUP OTP ───────────────────────────────────
    if ($_POST['action'] === 'verify_signup_otp') {
        if (empty($_SESSION['pending_signup'])) {
            echo json_encode(['success' => false, 'message' => 'Session expired. Please sign up again.']);
            exit;
        }

        $otp = sanitizeInput($_POST['otp'] ?? '');
        if (strlen($otp) !== 6 || !ctype_digit($otp)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid 6-digit code']);
            exit;
        }

        if (!isset($_SESSION['signup_otp'], $_SESSION['signup_otp_time'])) {
            echo json_encode(['success' => false, 'message' => 'OTP session expired. Please sign up again.']);
            exit;
        }

        if (time() - $_SESSION['signup_otp_time'] > 300) {
            unset($_SESSION['signup_otp'], $_SESSION['signup_otp_time']);
            resetOtpAttempts('signup_otp_attempts');
            echo json_encode(['success' => false, 'message' => 'OTP expired. Please request a new one.']);
            exit;
        }

        // Brute-force guard
        $attempts = incrementOtpAttempts('signup_otp_attempts');
        if ($attempts > 5) {
            unset($_SESSION['signup_otp'], $_SESSION['signup_otp_time'], $_SESSION['pending_signup']);
            resetOtpAttempts('signup_otp_attempts');
            echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Please sign up again.']);
            exit;
        }

        if (!hash_equals($_SESSION['signup_otp'], $otp)) {
            $remaining = 5 - $attempts;
            echo json_encode([
                'success' => false,
                'message' => "Invalid OTP. {$remaining} attempt(s) remaining.",
            ]);
            exit;
        }

        // OTP valid — create user
        unset($_SESSION['signup_otp'], $_SESSION['signup_otp_time']);
        resetOtpAttempts('signup_otp_attempts');

        $pending = $_SESSION['pending_signup'];

        try {
            $database = new Database();
            $db = $database->getConnection();

            // Double-check email not taken (race condition guard)
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$pending['email']]);
            if ($stmt->fetch()) {
                unset($_SESSION['pending_signup']);
                echo json_encode(['success' => false, 'message' => 'Email address already registered']);
                exit;
            }

            $stmt = $db->prepare(
                "INSERT INTO users (username, password_hash, email, full_name, store_name, role)
                 VALUES (?, ?, ?, ?, ?, 'Seller')"
            );
            $stmt->execute([
                $pending['username'],
                $pending['password_hash'],
                $pending['email'],
                $pending['fullname'],
                $pending['store'],
            ]);
            $new_user_id = $db->lastInsertId();

            $_SESSION['user_id'] = $new_user_id;
            $_SESSION['username'] = $pending['username'];
            $_SESSION['role'] = 'Seller';
            $_SESSION['full_name'] = $pending['fullname'];
            $_SESSION['user_full_name'] = $pending['fullname'];
            $_SESSION['user_email'] = $pending['email'];
            $_SESSION['user_store_name'] = $pending['store'];
            $_SESSION['user_role'] = 'Seller';
            $_SESSION['logged_in'] = true;

            unset($_SESSION['pending_signup']);

            echo json_encode([
                'success' => true,
                'message' => 'Account verified and created! Redirecting…',
                'redirect_url' => buildRedirectUrl('Seller', $pending['username']),
            ]);

        } catch (PDOException $e) {
            error_log('[CyberShield] Signup verify DB error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
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
    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;700&family=Bebas+Neue&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --blue: #3b8bff;
            --purple: #b061ff;
            --green: #00ff94;
            --red: #ff3b5c;
            --bg: #030508;
            --border: rgba(255, 255, 255, 0.06);
            --text: #dde4f0;
            --muted: #5c6a84;
            --dim: #2a3a52;
            --input-bg: rgba(255, 255, 255, 0.03);
            --input-border: rgba(255, 255, 255, 0.08);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Space Grotesk', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-image:
                linear-gradient(rgba(59, 139, 255, 0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(59, 139, 255, 0.04) 1px, transparent 1px);
            background-size: 60px 60px;
            animation: gridMove 30s linear infinite;
        }

        @keyframes gridMove {
            to {
                background-position: 60px 60px;
            }
        }

        .orb1,
        .orb2,
        .orb3 {
            position: fixed;
            border-radius: 50%;
            filter: blur(140px);
            pointer-events: none;
            z-index: 0;
        }

        .orb1 {
            width: 700px;
            height: 700px;
            background: #3b8bff;
            top: -250px;
            left: -250px;
            opacity: .10;
            animation: orbf 14s ease-in-out infinite alternate;
        }

        .orb2 {
            width: 600px;
            height: 600px;
            background: #b061ff;
            bottom: -200px;
            right: -150px;
            opacity: .10;
            animation: orbf 16s ease-in-out infinite alternate-reverse;
        }

        .orb3 {
            width: 300px;
            height: 300px;
            background: #00ff94;
            top: 50%;
            left: 50%;
            opacity: .04;
            animation: orbf 10s ease-in-out infinite;
        }

        @keyframes orbf {
            from {
                transform: scale(1) translate(0, 0)
            }

            to {
                transform: scale(1.2) translate(30px, 20px)
            }
        }

        /* ═══ LEFT ═══ */
        .left {
            flex: 1;
            background: rgba(6, 9, 16, 0.97);
            border-right: 1px solid var(--border);
            padding: 48px 64px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            z-index: 1;
            overflow: hidden;
        }

        .left::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: radial-gradient(ellipse at top right, rgba(59, 139, 255, 0.07), transparent 70%);
            pointer-events: none;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .logo-icon {
            width: 46px;
            height: 46px;
            background: linear-gradient(135deg, #3b8bff, #b061ff);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            animation: logoGlow 4s ease-in-out infinite alternate;
        }

        @keyframes logoGlow {
            from {
                box-shadow: 0 0 0 1px rgba(59, 139, 255, 0.3), 0 0 24px rgba(59, 139, 255, 0.3)
            }

            to {
                box-shadow: 0 0 0 1px rgba(176, 97, 255, 0.5), 0 0 40px rgba(176, 97, 255, 0.4)
            }
        }

        .logo-name {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 30px;
            letter-spacing: 4px;
            background: linear-gradient(90deg, #fff 0%, #3b8bff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo-tag {
            margin-left: auto;
            font-family: 'JetBrains Mono', monospace;
            font-size: 9px;
            letter-spacing: 2px;
            color: #2a3a52;
            text-transform: uppercase;
        }

        .hero {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 40px 0 24px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(0, 255, 148, 0.07);
            border: 1px solid rgba(0, 255, 148, 0.18);
            color: var(--green);
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            padding: 6px 14px;
            border-radius: 4px;
            margin-bottom: 24px;
            width: fit-content;
            animation: fadeUp .6s ease both;
        }

        .eyebrow-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--green);
            animation: blink 2s step-end infinite;
        }

        @keyframes blink {
            50% {
                opacity: 0
            }
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(16px)
            }

            to {
                opacity: 1;
                transform: none
            }
        }

        .headline {
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(56px, 5.5vw, 84px);
            line-height: .9;
            letter-spacing: 1px;
            color: #fff;
            margin-bottom: 22px;
            animation: fadeUp .6s .1s ease both;
        }

        .headline .blue {
            color: var(--blue);
            text-shadow: 0 0 60px rgba(59, 139, 255, 0.4);
        }

        .headline .green {
            color: var(--green);
            text-shadow: 0 0 60px rgba(0, 255, 148, 0.35);
        }

        .desc {
            font-size: 13px;
            color: #5c6a84;
            line-height: 1.85;
            max-width: 440px;
            margin-bottom: 28px;
            animation: fadeUp .6s .2s ease both;
        }

        .desc strong {
            color: #8898b4;
            font-weight: 500;
        }

        .ticker-box {
            background: rgba(255, 59, 92, 0.05);
            border: 1px solid rgba(255, 59, 92, 0.12);
            border-left: 3px solid var(--red);
            border-radius: 8px;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            overflow: hidden;
            animation: fadeUp .6s .25s ease both;
            position: relative;
            margin-bottom: 24px;
        }

        .ticker-box::after {
            content: '';
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 60px;
            background: linear-gradient(to right, transparent, rgba(6, 9, 16, 0.95));
            pointer-events: none;
        }

        .ticker-label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 9px;
            letter-spacing: 2px;
            color: var(--red);
            white-space: nowrap;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .ticker-label::before {
            content: '';
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--red);
            animation: blink 1s step-end infinite;
        }

        .ticker-track {
            overflow: hidden;
            flex: 1;
        }

        .ticker-inner {
            display: flex;
            gap: 48px;
            white-space: nowrap;
            animation: scroll 24s linear infinite;
        }

        @keyframes scroll {
            from {
                transform: translateX(0)
            }

            to {
                transform: translateX(-50%)
            }
        }

        .ticker-item {
            font-size: 11px;
            color: #5c6a84;
        }

        .ticker-item b {
            color: #8898b4;
            font-weight: 600;
        }

        .features {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 24px;
            animation: fadeUp .6s .3s ease both;
        }

        .feature-pill {
            display: flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.07);
            padding: 5px 11px;
            border-radius: 20px;
            font-size: 11px;
            color: #4d5d7a;
            transition: all .2s;
        }

        .feature-pill:hover {
            border-color: rgba(59, 139, 255, 0.3);
            color: #8898b4;
        }

        .feature-pill .dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .stats {
            display: flex;
            gap: 0;
            animation: fadeUp .6s .35s ease both;
        }

        .stat {
            flex: 1;
            padding: 16px 20px;
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            position: relative;
            transition: background .2s;
        }

        .stat:first-child {
            border-left: 1px solid rgba(255, 255, 255, 0.05);
        }

        .stat:hover {
            background: rgba(59, 139, 255, 0.04);
        }

        .stat::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(255, 255, 255, 0.05);
        }

        .stat-n {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 34px;
            color: var(--blue);
            line-height: 1;
        }

        .stat-l {
            font-size: 9px;
            color: #3a4d66;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-top: 3px;
        }

        .footer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-txt {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            color: var(--dim);
        }

        .ph-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 9px;
            color: #2a3a52;
            border: 1px solid rgba(255, 255, 255, 0.04);
            border-radius: 4px;
            padding: 4px 10px;
        }

        /* ═══ RIGHT ═══ */
        .right {
            width: 500px;
            flex-shrink: 0;
            background: #060910;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 52px;
            position: relative;
            z-index: 1;
        }

        .right::before {
            content: '';
            position: absolute;
            left: 0;
            top: 10%;
            bottom: 10%;
            width: 1px;
            background: linear-gradient(to bottom, transparent, rgba(59, 139, 255, 0.15), rgba(176, 97, 255, 0.15), transparent);
        }

        .form-box {
            width: 100%;
        }

        .form-scanner {
            height: 2px;
            border-radius: 999px;
            background: linear-gradient(90deg, transparent, var(--blue), var(--purple), transparent);
            margin-bottom: 36px;
            animation: scanPulse 3s ease-in-out infinite;
        }

        @keyframes scanPulse {

            0%,
            100% {
                opacity: 0.25;
                transform: scaleX(0.5);
            }

            50% {
                opacity: 1;
                transform: scaleX(1);
            }
        }

        .form-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 9px;
            letter-spacing: 3px;
            color: var(--dim);
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        .form-eyebrow::before {
            content: '//';
            color: var(--blue);
            font-size: 12px;
        }

        .form-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 3rem;
            letter-spacing: 2px;
            line-height: 0.9;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #fff 30%, var(--blue) 100%);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .form-sub {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.7;
            margin-bottom: 28px;
        }

        .form-sub a {
            color: var(--blue);
            text-decoration: none;
            font-weight: 500;
            border-bottom: 1px solid rgba(59, 139, 255, 0.3);
            transition: border-color 0.2s;
        }

        .form-sub a:hover {
            border-color: var(--blue);
        }

        /* Fields */
        .field-group {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-bottom: 18px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field-label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 9px;
            letter-spacing: 2px;
            color: var(--dim);
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .field-label .required {
            color: var(--red);
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 15px;
            pointer-events: none;
            opacity: 0.5;
        }

        .field input {
            width: 100%;
            padding: 13px 14px 13px 42px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 9px;
            color: var(--text);
            font-family: 'Space Grotesk', sans-serif;
            font-size: 14px;
            transition: all 0.2s;
            outline: none;
            -webkit-text-fill-color: var(--text);
        }

        .field input::placeholder {
            color: #2a3a52;
        }

        .field input:focus {
            border-color: var(--blue);
            background: rgba(59, 139, 255, 0.04);
            box-shadow: 0 0 0 3px rgba(59, 139, 255, 0.1);
        }

        .pwd-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            opacity: 0.4;
            transition: opacity 0.2s;
            padding: 2px;
        }

        .pwd-toggle:hover {
            opacity: 0.8;
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 12px;
            color: var(--muted);
            user-select: none;
        }

        .checkbox-label input[type="checkbox"] {
            display: none;
        }

        .custom-check {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.03);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            font-size: 10px;
            flex-shrink: 0;
        }

        .checkbox-label input:checked+.custom-check {
            background: var(--blue);
            border-color: var(--blue);
            color: #fff;
        }

        .forgot-link {
            font-size: 10px;
            color: var(--muted);
            text-decoration: none;
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: 1px;
            transition: color 0.2s;
        }

        .forgot-link:hover {
            color: var(--blue);
        }

        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--blue) 0%, #5b6fff 100%);
            color: #fff;
            font-weight: 700;
            font-size: 15px;
            letter-spacing: 0.5px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-family: 'Space Grotesk', sans-serif;
            box-shadow: 0 4px 28px rgba(59, 139, 255, 0.3);
            transition: all .2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), transparent);
            opacity: 0;
            transition: opacity 0.2s;
        }

        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 40px rgba(59, 139, 255, 0.45);
        }

        .btn-submit:hover:not(:disabled)::before {
            opacity: 1;
        }

        .btn-submit:active {
            transform: none;
        }

        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .submit-arrow {
            transition: transform 0.2s;
        }

        .btn-submit:hover:not(:disabled) .submit-arrow {
            transform: translateX(4px);
        }

        .status-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 16px;
            padding: 10px 14px;
            background: rgba(0, 255, 148, 0.05);
            border: 1px solid rgba(0, 255, 148, 0.12);
            border-radius: 7px;
            font-size: 11px;
            color: #3a4d66;
            font-family: 'JetBrains Mono', monospace;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--green);
            animation: blink 2s step-end infinite;
            flex-shrink: 0;
        }

        .field.error input {
            border-color: rgba(255, 59, 92, 0.5);
            box-shadow: 0 0 0 3px rgba(255, 59, 92, 0.1);
        }

        .field-error {
            font-family: 'JetBrains Mono', monospace;
            font-size: 9px;
            color: var(--red);
            letter-spacing: 1px;
            display: none;
        }

        .field.error .field-error {
            display: block;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 12px;
            display: none;
            align-items: center;
            gap: 10px;
        }

        .alert.show {
            display: flex;
        }

        .alert.success {
            background: rgba(0, 255, 148, 0.07);
            border: 1px solid rgba(0, 255, 148, 0.2);
            color: var(--green);
        }

        .alert.error {
            background: rgba(255, 59, 92, 0.07);
            border: 1px solid rgba(255, 59, 92, 0.2);
            color: var(--red);
        }

        /* Auth Toggle */
        .auth-toggle {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
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
            background: rgba(59, 139, 255, 0.15);
            color: var(--blue);
            box-shadow: 0 0 0 1px rgba(59, 139, 255, 0.3);
        }

        .btn-signup-submit {
            background: linear-gradient(135deg, #00c97a 0%, #00a362 100%);
            box-shadow: 0 4px 28px rgba(0, 201, 122, 0.25);
        }

        .btn-signup-submit:hover:not(:disabled) {
            box-shadow: 0 8px 40px rgba(0, 201, 122, 0.4);
        }

        /* ═══ IMAGE CAPTCHA ═══ */
        .captcha-wrap {
            display: none;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
            animation: fadeUp .3s ease both;
        }

        .captcha-wrap.visible {
            display: flex;
        }

        .captcha-header {
            font-family: 'JetBrains Mono', monospace;
            font-size: 9px;
            letter-spacing: 2px;
            color: var(--dim);
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .captcha-header::before {
            content: '//';
            color: var(--blue);
            font-size: 11px;
        }

        .captcha-instruction {
            font-size: 13px;
            color: var(--text);
            font-weight: 500;
            margin-bottom: 8px;
            padding: 10px 14px;
            background: rgba(59, 139, 255, 0.08);
            border: 1px solid rgba(59, 139, 255, 0.2);
            border-radius: 8px;
        }

        .captcha-grid-container {
            position: relative;
            border: 2px solid rgba(59, 139, 255, 0.3);
            border-radius: 12px;
            overflow: hidden;
            background: rgba(0, 0, 0, 0.4);
        }

        .captcha-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows: repeat(3, 1fr);
            gap: 2px;
            background: rgba(59, 139, 255, 0.2);
        }

        .captcha-square {
            position: relative;
            aspect-ratio: 1;
            cursor: pointer;
            overflow: hidden;
            background: #0a0f1e;
            transition: all 0.2s;
            border: 2px solid transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
        }

        .captcha-square.selected {
            border-color: var(--green);
            box-shadow: inset 0 0 0 3px rgba(0, 255, 148, 0.3);
        }

        .captcha-square.selected::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 32px;
            height: 32px;
            background: var(--green);
            color: #000;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            animation: checkmarkPop 0.3s ease;
            z-index: 2;
        }

        @keyframes checkmarkPop {
            0% {
                transform: translate(-50%, -50%) scale(0);
            }

            50% {
                transform: translate(-50%, -50%) scale(1.2);
            }

            100% {
                transform: translate(-50%, -50%) scale(1);
            }
        }

        .captcha-square:hover:not(.selected) {
            border-color: rgba(59, 139, 255, 0.5);
        }

        .captcha-actions {
            display: flex;
            gap: 10px;
            margin-top: 8px;
        }

        .captcha-verify-btn {
            flex: 1;
            padding: 12px;
            background: linear-gradient(135deg, var(--blue), var(--purple));
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Space Grotesk', sans-serif;
        }

        .captcha-verify-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(59, 139, 255, 0.4);
        }

        .captcha-verify-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .captcha-refresh-btn-small {
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: var(--muted);
            cursor: pointer;
            transition: all 0.2s;
            font-size: 18px;
        }

        .captcha-refresh-btn-small:hover {
            border-color: var(--blue);
            color: var(--blue);
            background: rgba(59, 139, 255, 0.05);
        }

        .captcha-status-msg {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            letter-spacing: 1px;
            display: none;
            padding: 8px 12px;
            border-radius: 6px;
        }

        .captcha-status-msg.ok {
            display: block;
            color: var(--green);
            background: rgba(0, 255, 148, 0.06);
            border: 1px solid rgba(0, 255, 148, 0.15);
        }

        .captcha-status-msg.err {
            display: block;
            color: var(--red);
            background: rgba(255, 59, 92, 0.06);
            border: 1px solid rgba(255, 59, 92, 0.15);
        }

        /* OTP Modal */
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
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
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
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
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

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(59, 139, 255, 0.4);
            }

            50% {
                box-shadow: 0 0 0 20px rgba(59, 139, 255, 0);
            }
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

        @media (max-width: 900px) {
            body {
                flex-direction: column;
            }

            .left {
                padding: 32px;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .hero {
                padding: 24px 0 16px;
            }

            .right {
                width: 100%;
                padding: 40px 32px;
            }
        }

        @media (max-width: 480px) {
            .left {
                padding: 24px 20px;
            }

            .right {
                padding: 32px 20px;
            }

            .stats {
                flex-wrap: wrap;
            }

            .stat {
                min-width: 50%;
            }

            .modal-container {
                margin: 20px;
                padding: 24px;
            }

            .otp-digit {
                width: 45px;
                height: 54px;
                font-size: 24px;
            }

            .captcha-square {
                font-size: 32px;
            }
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
                <div class="stat">
                    <div class="stat-n">30</div>
                    <div class="stat-l">Questions</div>
                </div>
                <div class="stat">
                    <div class="stat-n">10</div>
                    <div class="stat-l">Categories</div>
                </div>
                <div class="stat">
                    <div class="stat-n">A–D</div>
                    <div class="stat-l">Risk Ranks</div>
                </div>
                <div class="stat">
                    <div class="stat-n">AI</div>
                    <div class="stat-l">Powered</div>
                </div>
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
            <div class="auth-toggle">
                <div class="auth-tab active" id="tab-signin" onclick="switchTab('signin')">Sign In</div>
                <div class="auth-tab" id="tab-signup" onclick="switchTab('signup')">Sign Up</div>
            </div>
            <div class="alert" id="alert"></div>

            <!-- ── SIGN IN FORM ── -->
            <div id="form-signin">
                <div class="form-title">SIGN IN<br>TO PORTAL</div>
                <p class="form-sub">No account yet? <a href="#" onclick="switchTab('signup'); return false;">Create one
                        here</a></p>
                <form id="signin-form" onsubmit="return handleSignIn(event)">
                    <div class="field-group">
                        <div class="field" id="si-field-user">
                            <div class="field-label">Username <span class="required">*</span></div>
                            <div class="input-wrap">
                                <span class="input-icon">👤</span>
                                <input type="text" id="si-username" name="username" placeholder="Enter your username"
                                    autocomplete="username">
                            </div>
                            <div class="field-error">Username is required.</div>
                        </div>
                        <div class="field" id="si-field-pass">
                            <div class="field-label">Password <span class="required">*</span></div>
                            <div class="input-wrap">
                                <span class="input-icon">🔑</span>
                                <input type="password" id="si-password" name="password"
                                    placeholder="Enter your password" autocomplete="current-password">
                                <button class="pwd-toggle" type="button"
                                    onclick="togglePwd('si-password', this)">👁</button>
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
                <p class="form-sub">Already have an account? <a href="#"
                        onclick="switchTab('signin'); return false;">Sign in here</a></p>
                <form id="signup-form" onsubmit="return handleSignUp(event)">
                    <div class="field-group">
                        <div class="field" id="su-field-fullname">
                            <div class="field-label">Full Name <span class="required">*</span></div>
                            <div class="input-wrap">
                                <span class="input-icon">🪪</span>
                                <input type="text" id="su-fullname" name="fullname" placeholder="Enter your full name"
                                    autocomplete="name">
                            </div>
                            <div class="field-error">Full name is required.</div>
                        </div>
                        <div class="field" id="su-field-email">
                            <div class="field-label">Email Address <span class="required">*</span></div>
                            <div class="input-wrap">
                                <span class="input-icon">✉️</span>
                                <input type="email" id="su-email" name="email" placeholder="you@example.com"
                                    autocomplete="email">
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
                                <input type="password" id="su-password" name="password" placeholder="Min. 8 characters"
                                    autocomplete="new-password">
                                <button class="pwd-toggle" type="button"
                                    onclick="togglePwd('su-password', this)">👁</button>
                            </div>
                            <div class="field-error">Password must be at least 8 characters.</div>
                        </div>
                        <div class="field" id="su-field-confirm">
                            <div class="field-label">Confirm Password <span class="required">*</span></div>
                            <div class="input-wrap">
                                <span class="input-icon">🔒</span>
                                <input type="password" id="su-confirm" name="confirm" placeholder="Repeat your password"
                                    autocomplete="new-password">
                                <button class="pwd-toggle" type="button"
                                    onclick="togglePwd('su-confirm', this)">👁</button>
                            </div>
                            <div class="field-error">Passwords do not match.</div>
                        </div>
                    </div>
                    <!-- Agree checkbox -->
                    <label class="checkbox-label" style="margin-bottom:20px;">
                        <input type="checkbox" id="su-agree" onchange="handleAgreeChange(this)">
                        <span class="custom-check"></span>
                        I agree to the <a href="#"
                            style="color:var(--blue);text-decoration:none;border-bottom:1px solid rgba(59,139,255,0.3);">Terms
                            &amp; Privacy Policy</a>
                    </label>

                    <!-- ── EMOJI CAPTCHA ── -->
                    <div class="captcha-wrap" id="captchaWrap">
                        <div class="captcha-header">Human Verification</div>
                        <div class="captcha-instruction" id="captchaInstruction">
                            Select all squares with <strong>🚗 cars</strong>
                        </div>
                        <div class="captcha-grid-container">
                            <div class="captcha-grid" id="captchaGrid"></div>
                        </div>
                        <div class="captcha-actions">
                            <button type="button" class="captcha-refresh-btn-small" onclick="generateCaptcha()"
                                title="New Challenge">↻</button>
                            <button type="button" class="captcha-verify-btn" id="captchaVerifyBtn"
                                onclick="verifyImageCaptcha()" disabled>
                                Verify Selection
                            </button>
                        </div>
                        <div class="captcha-status-msg" id="captchaMsg"></div>
                    </div>

                    <button type="submit" class="btn-submit btn-signup-submit" id="btnCreateAccount" disabled>
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

    <!-- ═══ OTP MODAL ═══ -->
    <div class="modal-overlay" id="otpModal">
        <div class="modal-container">
            <div class="modal-close" onclick="closeOTPModal()">✕</div>
            <div class="modal-content">
                <div class="modal-icon">🔐</div>
                <h2 class="modal-title">Two-Factor Authentication</h2>
                <p class="modal-subtitle" id="otpSubtitle">Enter the 6-digit code sent to <strong id="otpEmailHint">your
                        device</strong></p>
                <p class="modal-subtitle" id="otpSignupSubtitle" style="display:none">A verification code was sent to
                    <strong id="otpSignupEmail">your email</strong> to confirm your account</p>
                <div class="otp-input-group">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" id="otp1"
                        onkeyup="handleOTPInput(event,1)" onkeydown="handleOTPBackspace(event,1)">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" id="otp2"
                        onkeyup="handleOTPInput(event,2)" onkeydown="handleOTPBackspace(event,2)">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" id="otp3"
                        onkeyup="handleOTPInput(event,3)" onkeydown="handleOTPBackspace(event,3)">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" id="otp4"
                        onkeyup="handleOTPInput(event,4)" onkeydown="handleOTPBackspace(event,4)">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" id="otp5"
                        onkeyup="handleOTPInput(event,5)" onkeydown="handleOTPBackspace(event,5)">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" id="otp6"
                        onkeyup="handleOTPInput(event,6)" onkeydown="handleOTPBackspace(event,6)">
                </div>
                <div class="otp-timer" id="otpTimer">
                    Code expires in <span id="timerSeconds">05:00</span>
                </div>
                <div class="otp-info">
                    <span class="otp-info-icon">ℹ️</span>
                    <span>Enter each digit in the boxes above — type continuously</span>
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
        /* ══════════════════════════════════════════
           EMOJI CAPTCHA ENGINE
        ══════════════════════════════════════════ */
        let captchaValid = false;
        let selectedSquares = [];
        let correctSquares = [];
        let currentChallenge = null;

        const CAPTCHA_CHALLENGES = [
            { instruction: '🚗 cars', targetEmoji: '🚗', distractorEmojis: ['🚲', '🚶', '🏍️', '✈️', '🚛', '🚕', '🚙'] },
            { instruction: '🐶 dogs', targetEmoji: '🐶', distractorEmojis: ['🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼'] },
            { instruction: '🐱 cats', targetEmoji: '🐱', distractorEmojis: ['🐶', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼'] },
            { instruction: '🍎 apples', targetEmoji: '🍎', distractorEmojis: ['🍊', '🍌', '🍉', '🍇', '🍓', '🥝', '🍒'] },
            { instruction: '🔥 fire', targetEmoji: '🔥', distractorEmojis: ['💧', '❄️', '💨', '⚡', '💎', '⭐', '🌙'] },
            { instruction: '❤️ hearts', targetEmoji: '❤️', distractorEmojis: ['💙', '💚', '💛', '💜', '🧡', '🖤', '💔'] },
            { instruction: '☕ coffee', targetEmoji: '☕', distractorEmojis: ['🍵', '🥤', '🧃', '🍺', '🥂', '🍷', '🥛'] },
            { instruction: '📱 phones', targetEmoji: '📱', distractorEmojis: ['💻', '🖥️', '⌚', '📷', '🎮', '📺', '🔋'] },
            { instruction: '🌲 trees', targetEmoji: '🌲', distractorEmojis: ['🌳', '🌴', '🌵', '🌿', '🍃', '🍂', '🌸'] },
            { instruction: '🏠 houses', targetEmoji: '🏠', distractorEmojis: ['🏢', '🏪', '🏫', '🏥', '🏦', '🏨', '🏛️'] },
            { instruction: '🚲 bicycles', targetEmoji: '🚲', distractorEmojis: ['🚗', '🚕', '🚙', '🏍️', '✈️', '🚛', '🚶'] },
            { instruction: '⭐ stars', targetEmoji: '⭐', distractorEmojis: ['🌟', '✨', '🌙', '☀️', '⚡', '💫', '🌠'] },
            { instruction: '🐟 fish', targetEmoji: '🐟', distractorEmojis: ['🐠', '🐡', '🐙', '🦑', '🐬', '🐳', '🦀'] },
            { instruction: '⚽ sports balls', targetEmoji: '⚽', distractorEmojis: ['🏀', '🏈', '⚾', '🎾', '🏐', '🏓', '🎱'] },
            { instruction: '🎵 musical notes', targetEmoji: '🎵', distractorEmojis: ['🎶', '🎤', '🎧', '🎸', '🎹', '🥁', '🎺'] }
        ];

        function getRandomInt(min, max) {
            return Math.floor(Math.random() * (max - min + 1)) + min;
        }

        function generateCaptcha() {
            selectedSquares = []; captchaValid = false; correctSquares = [];
            const challengeIndex = Math.floor(Math.random() * CAPTCHA_CHALLENGES.length);
            currentChallenge = { ...CAPTCHA_CHALLENGES[challengeIndex] };
            const allIndices = [0, 1, 2, 3, 4, 5, 6, 7, 8];
            for (let i = allIndices.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [allIndices[i], allIndices[j]] = [allIndices[j], allIndices[i]];
            }
            const numCorrect = getRandomInt(3, 5);
            correctSquares = allIndices.slice(0, numCorrect).sort((a, b) => a - b);
            document.getElementById('captchaInstruction').innerHTML =
                `Select all squares with <strong>${currentChallenge.instruction}</strong>`;
            const grid = document.getElementById('captchaGrid');
            grid.innerHTML = '';
            for (let i = 0; i < 9; i++) {
                const square = document.createElement('div');
                square.className = 'captcha-square';
                square.dataset.index = i;
                square.onclick = () => toggleSquare(i);
                const emoji = correctSquares.includes(i)
                    ? currentChallenge.targetEmoji
                    : currentChallenge.distractorEmojis[Math.floor(Math.random() * currentChallenge.distractorEmojis.length)];
                square.textContent = emoji;
                square.style.cssText = 'font-size:48px;display:flex;align-items:center;justify-content:center;';
                grid.appendChild(square);
            }
            const msg = document.getElementById('captchaMsg');
            msg.className = 'captcha-status-msg'; msg.textContent = '';
            document.getElementById('captchaVerifyBtn').disabled = true;
            lockCreateBtn();
        }

        function toggleSquare(index) {
            const square = document.querySelector(`.captcha-square[data-index="${index}"]`);
            square.classList.toggle('selected');
            if (selectedSquares.includes(index)) {
                selectedSquares = selectedSquares.filter(i => i !== index);
            } else {
                selectedSquares.push(index);
            }
            document.getElementById('captchaVerifyBtn').disabled = selectedSquares.length === 0;
        }

        function verifyImageCaptcha() {
            const msg = document.getElementById('captchaMsg');
            const sortedSelected = [...selectedSquares].sort((a, b) => a - b);
            const sortedCorrect = [...correctSquares].sort((a, b) => a - b);
            const isCorrect = JSON.stringify(sortedSelected) === JSON.stringify(sortedCorrect);
            if (isCorrect) {
                msg.className = 'captcha-status-msg ok';
                msg.textContent = '✓ Verified — CAPTCHA passed!';
                captchaValid = true; unlockCreateBtn();
                document.querySelectorAll('.captcha-square').forEach(sq => sq.style.pointerEvents = 'none');
                document.getElementById('captchaVerifyBtn').disabled = true;
            } else {
                msg.className = 'captcha-status-msg err';
                msg.textContent = '✗ Incorrect selection. Try again or click ↻ for a new challenge.';
                captchaValid = false; lockCreateBtn();
                setTimeout(() => {
                    document.querySelectorAll('.captcha-square').forEach(sq => sq.classList.remove('selected'));
                    selectedSquares = [];
                    document.getElementById('captchaVerifyBtn').disabled = true;
                    setTimeout(() => {
                        if (msg.className === 'captcha-status-msg err') {
                            msg.className = 'captcha-status-msg'; msg.textContent = '';
                        }
                    }, 2000);
                }, 1500);
            }
        }

        function lockCreateBtn() { const b = document.getElementById('btnCreateAccount'); if (b) b.disabled = true; }
        function unlockCreateBtn() { const b = document.getElementById('btnCreateAccount'); if (b) b.disabled = false; }

        function handleAgreeChange(cb) {
            cb.parentElement.querySelector('.custom-check').textContent = cb.checked ? '✓' : '';
            const wrap = document.getElementById('captchaWrap');
            if (cb.checked) { wrap.classList.add('visible'); generateCaptcha(); }
            else { wrap.classList.remove('visible'); captchaValid = false; lockCreateBtn(); }
        }

        /* ══════════════════════════════════════════
           AUTH STATE
        ══════════════════════════════════════════ */
        let currentOTPMode = 'login';
        let currentSignupEmail = null;   // masked only
        let timerInterval = null;
        let timeLeft = 300;

        /* ══════════════════════════════════════════
           AUTH LOGIC
        ══════════════════════════════════════════ */
        function switchTab(mode) {
            const isSignup = mode === 'signup';
            document.getElementById('tab-signin').classList.toggle('active', !isSignup);
            document.getElementById('tab-signup').classList.toggle('active', isSignup);
            document.getElementById('form-signin').style.display = isSignup ? 'none' : 'block';
            document.getElementById('form-signup').style.display = isSignup ? 'block' : 'none';
            document.getElementById('alert').className = 'alert';
            if (isSignup) {
                const cb = document.getElementById('su-agree');
                if (cb.checked) generateCaptcha();
            }
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
            setTimeout(() => { if (el.className.includes('show')) el.className = 'alert'; }, 5000);
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
            if (!formData.get('username')) { document.getElementById('si-field-user').classList.add('error'); ok = false; }
            if (!formData.get('password')) { document.getElementById('si-field-pass').classList.add('error'); ok = false; }
            if (!ok) return false;
            formData.append('action', 'login');
            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        if (result.require_otp) {
                            // Server only returns a safe masked email hint — no user_id etc.
                            currentOTPMode = 'login';
                            document.getElementById('otpSubtitle').style.display = 'block';
                            document.getElementById('otpSignupSubtitle').style.display = 'none';
                            document.getElementById('otpEmailHint').textContent = result.email_hint || 'your registered email';
                            for (let i = 1; i <= 6; i++) document.getElementById(`otp${i}`).value = '';
                            document.getElementById('otpModal').classList.add('active');
                            setupOTPInput(); startOTPTimer();
                            showAlert('success', result.message);
                        } else {
                            showAlert('success', result.message);
                            setTimeout(() => window.location.href = result.redirect_url, 1800);
                        }
                    } else {
                        showAlert('error', result.message);
                    }
                })
                .catch(() => showAlert('error', 'Network error. Please check your connection and try again.'));
            return false;
        }

        function handleSignUp(event) {
            event.preventDefault();
            const form = document.getElementById('signup-form');
            const formData = new FormData(form);
            clearErrors('su-field-fullname', 'su-field-email', 'su-field-store', 'su-field-pass', 'su-field-confirm');
            document.getElementById('alert').className = 'alert';
            let ok = true;
            if (!formData.get('fullname')) { document.getElementById('su-field-fullname').classList.add('error'); ok = false; }
            if (!formData.get('email') || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.get('email'))) {
                document.getElementById('su-field-email').classList.add('error'); ok = false;
            }
            if (!formData.get('store')) { document.getElementById('su-field-store').classList.add('error'); ok = false; }
            if (formData.get('password').length < 8) { document.getElementById('su-field-pass').classList.add('error'); ok = false; }
            if (formData.get('password') !== formData.get('confirm')) { document.getElementById('su-field-confirm').classList.add('error'); ok = false; }
            if (!ok) return false;
            if (!document.getElementById('su-agree').checked) {
                showAlert('error', 'Please agree to the Terms & Privacy Policy.'); return false;
            }
            if (!captchaValid) {
                showAlert('error', 'Please complete the image CAPTCHA verification first.'); return false;
            }
            formData.append('action', 'register');
            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        if (result.require_otp) {
                            currentOTPMode = 'signup';
                            currentSignupEmail = result.email; // already masked server-side
                            document.getElementById('otpSubtitle').style.display = 'none';
                            document.getElementById('otpSignupSubtitle').style.display = 'block';
                            document.getElementById('otpSignupEmail').textContent = result.email;
                            for (let i = 1; i <= 6; i++) document.getElementById(`otp${i}`).value = '';
                            document.getElementById('otpModal').classList.add('active');
                            setupOTPInput(); startOTPTimer();
                            showAlert('success', result.message);
                        } else {
                            showAlert('success', result.message);
                            setTimeout(() => window.location.href = result.redirect_url, 1800);
                        }
                    } else {
                        showAlert('error', result.message);
                    }
                })
                .catch(() => showAlert('error', 'Network error. Please check your connection and try again.'));
            return false;
        }

        /* ══════════════════════════════════════════
           OTP MODAL
        ══════════════════════════════════════════ */
        function handleOTPInput(event, boxNumber) {
            const input = event.target;
            input.value = input.value.replace(/[^0-9]/g, '');
            if (input.value.length === 1 && boxNumber < 6) {
                document.getElementById(`otp${boxNumber + 1}`).focus();
            }
            if (boxNumber === 6) {
                let allFilled = true;
                for (let i = 1; i <= 6; i++) { if (!document.getElementById(`otp${i}`).value) { allFilled = false; break; } }
                if (allFilled) setTimeout(() => verifyOTP(), 500);
            }
        }

        function handleOTPBackspace(event, boxNumber) {
            if (event.key === 'Backspace' && !event.target.value && boxNumber > 1) {
                event.preventDefault();
                document.getElementById(`otp${boxNumber - 1}`).focus();
            }
        }

        function setupOTPInput() { document.getElementById('otp1').focus(); }

        function verifyOTP() {
            let otpCode = '';
            for (let i = 1; i <= 6; i++) otpCode += document.getElementById(`otp${i}`).value;
            if (otpCode.length !== 6) { showAlert('error', 'Please enter the complete 6-digit OTP'); return; }

            const verifyBtn = document.getElementById('verifyBtn');
            const originalText = verifyBtn.innerHTML;
            verifyBtn.innerHTML = 'Verifying...'; verifyBtn.disabled = true;

            const formData = new FormData();
            // Only send the OTP code — server uses session to identify user
            if (currentOTPMode === 'signup') {
                formData.append('action', 'verify_signup_otp');
                formData.append('otp', otpCode);
            } else {
                formData.append('action', 'verify_otp');
                formData.append('otp', otpCode);
            }

            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        closeOTPModal();
                        showAlert('success', result.message || 'Verified successfully! Redirecting…');
                        setTimeout(() => window.location.href = result.redirect_url, 1800);
                    } else {
                        showAlert('error', result.message || 'Verification failed. Please try again.');
                        // Clear OTP boxes on failure
                        for (let i = 1; i <= 6; i++) document.getElementById(`otp${i}`).value = '';
                        document.getElementById('otp1').focus();
                    }
                    verifyBtn.innerHTML = originalText; verifyBtn.disabled = false;
                })
                .catch(() => {
                    showAlert('error', 'Verification failed. Please try again.');
                    verifyBtn.innerHTML = originalText; verifyBtn.disabled = false;
                });
        }

        function resendOTP() {
            const resendBtn = document.getElementById('resendBtn');
            const originalText = resendBtn.innerHTML;
            resendBtn.innerHTML = 'Sending...'; resendBtn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'resend_otp');
            formData.append('mode', currentOTPMode);
            // Server uses session data — no need to pass user_id/email from client

            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        startOTPTimer();
                        for (let i = 1; i <= 6; i++) document.getElementById(`otp${i}`).value = '';
                        document.getElementById('otp1').focus();
                        document.getElementById('verifyBtn').disabled = false;
                        document.getElementById('otpTimer').classList.remove('expiring');
                        showAlert('success', result.message);
                    } else {
                        showAlert('error', result.message || 'Failed to resend OTP');
                    }
                    resendBtn.innerHTML = originalText; resendBtn.disabled = false;
                })
                .catch(() => {
                    showAlert('error', 'Network error. Please try again.');
                    resendBtn.innerHTML = originalText; resendBtn.disabled = false;
                });
        }

        function closeOTPModal() {
            document.getElementById('otpModal').classList.remove('active');
            if (timerInterval) clearInterval(timerInterval);
            document.getElementById('otpSubtitle').style.display = 'block';
            document.getElementById('otpSignupSubtitle').style.display = 'none';
            currentOTPMode = 'login';
        }

        function startOTPTimer() {
            if (timerInterval) clearInterval(timerInterval);
            timeLeft = 300; updateTimerDisplay();
            timerInterval = setInterval(() => {
                timeLeft--; updateTimerDisplay();
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
            const m = Math.floor(timeLeft / 60), s = timeLeft % 60;
            document.getElementById('timerSeconds').textContent = `${m}:${s.toString().padStart(2, '0')}`;
        }

        /* ══════════════════════════════════════════
           MISC LISTENERS
        ══════════════════════════════════════════ */
        document.getElementById('otpModal').addEventListener('click', function (e) {
            if (e.target === this) closeOTPModal();
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                const isSignup = document.getElementById('form-signup').style.display !== 'none';
                if (isSignup) document.getElementById('signup-form').dispatchEvent(new Event('submit'));
                else document.getElementById('signin-form').dispatchEvent(new Event('submit'));
            }
            if (e.key === 'Escape' && document.getElementById('otpModal').classList.contains('active')) closeOTPModal();
        });

        document.getElementById('remember').addEventListener('change', function () {
            this.parentElement.querySelector('.custom-check').textContent = this.checked ? '✓' : '';
        });
    </script>
</body>

</html>