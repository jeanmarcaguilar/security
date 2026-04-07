<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit();
}

$database = new Database();
$db = $database->getConnection();

$user_query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($user_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

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

// NEW: Fetch security audit trail (login history, profile changes, assessment completions)
$audit_query = "SELECT 
    a.id,
    a.action_type,
    a.action_description,
    a.ip_address,
    a.user_agent,
    a.created_at,
    CASE 
        WHEN a.action_type = 'login' THEN '🔐 Login'
        WHEN a.action_type = 'profile_update' THEN '✏️ Profile Update'
        WHEN a.action_type = 'password_change' THEN '🔑 Password Change'
        WHEN a.action_type = 'assessment_complete' THEN '📊 Assessment'
        WHEN a.action_type = 'data_clear' THEN '🗑️ Data Clear'
        ELSE '📌 Activity'
    END as action_display,
    CASE 
        WHEN a.action_type = 'login' THEN 'var(--teal)'
        WHEN a.action_type = 'profile_update' THEN 'var(--blue)'
        WHEN a.action_type = 'password_change' THEN 'var(--purple)'
        WHEN a.action_type = 'assessment_complete' THEN 'var(--green)'
        WHEN a.action_type = 'data_clear' THEN 'var(--red)'
        ELSE 'var(--muted2)'
    END as action_color
FROM audit_log a
WHERE a.user_id = :user_id
ORDER BY a.created_at DESC
LIMIT 15";

$audit_stmt = $db->prepare($audit_query);
$audit_stmt->bindParam(':user_id', $_SESSION['user_id']);
$audit_stmt->execute();
$audit_logs = $audit_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent assessment history for activity feed
$recent_assessments_query = "SELECT score, rank, created_at FROM assessments WHERE vendor_id = :user_id ORDER BY created_at DESC LIMIT 5";
$recent_stmt = $db->prepare($recent_assessments_query);
$recent_stmt->bindParam(':user_id', $_SESSION['user_id']);
$recent_stmt->execute();
$recent_assessments = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get account age and last login from audit log
$last_login_query = "SELECT created_at FROM audit_log WHERE user_id = :user_id AND action_type = 'login' ORDER BY created_at DESC LIMIT 1";
$last_login_stmt = $db->prepare($last_login_query);
$last_login_stmt->bindParam(':user_id', $_SESSION['user_id']);
$last_login_stmt->execute();
$last_login = $last_login_stmt->fetch(PDO::FETCH_ASSOC);

$account_created = $user['created_at'] ?? date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>My Profile — CyberShield</title>
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

    /* Sidebar */
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
      background: rgba(59, 139, 255, .12);
      color: var(--blue);
      border: 1px solid rgba(59, 139, 255, .2);
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

    /* Main Content */
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
      transition: var(--t);
      text-decoration: none
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

    /* Content */
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

    /* Cards */
    .card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 12px;
      box-shadow: var(--shadow);
      transition: border-color .18s;
      padding: 1.25rem 1.5rem;
      margin-bottom: .9rem
    }

    .card:hover {
      border-color: var(--border2)
    }

    .card-title {
      font-family: var(--mono);
      font-size: .62rem;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      color: var(--muted2);
      padding-bottom: .65rem;
      border-bottom: 1px solid var(--border);
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: .5rem
    }

    .card-title::before {
      content: '';
      width: 10px;
      height: 3px;
      background: var(--blue);
      border-radius: 2px;
      flex-shrink: 0
    }

    .card.danger {
      background: rgba(255, 59, 92, .04);
      border-color: rgba(255, 59, 92, .15)
    }

    .card.danger .card-title::before {
      background: var(--red)
    }

    .card.danger .card-title {
      color: var(--red)
    }

    /* Profile Layout */
    .profile-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: .9rem
    }

    @media(max-width:860px) {
      .profile-grid {
        grid-template-columns: 1fr
      }
    }

    .profile-avatar-wrap {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1.15rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid var(--border)
    }

    .profile-avatar {
      width: 64px;
      height: 64px;
      border-radius: 12px;
      background: linear-gradient(135deg, var(--blue), var(--purple));
      color: #fff;
      display: grid;
      place-items: center;
      font-family: var(--display);
      font-size: 1.6rem;
      font-weight: 700;
      flex-shrink: 0;
      box-shadow: 0 0 18px rgba(59, 139, 255, .3)
    }

    .profile-display-name {
      font-family: var(--display);
      font-size: 1rem;
      font-weight: 700;
      letter-spacing: .3px
    }

    .profile-display-email {
      font-family: var(--mono);
      font-size: .68rem;
      color: var(--muted2);
      margin-top: .2rem
    }

    /* Forms */
    .fg {
      margin-bottom: .85rem
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

    [data-theme=light] .fi {
      background: #f8fafc
    }

    /* Buttons */
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

    .btn-full {
      width: 100%;
      justify-content: center
    }

    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: .65rem;
      margin-bottom: 1rem
    }

    .stat-card {
      padding: 1rem;
      background: rgba(255, 255, 255, .025);
      border: 1px solid var(--border);
      border-radius: 10px;
      text-align: center;
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

    .stat-val {
      font-family: var(--display);
      font-size: 1.4rem;
      font-weight: 700;
      color: var(--accent, var(--blue))
    }

    .stat-lbl {
      font-family: var(--mono);
      font-size: .58rem;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      color: var(--muted2);
      margin-top: .25rem
    }

    /* Rank Badge */
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

    .rank-display {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: .5rem;
      margin-top: 1rem;
      padding: .65rem;
      background: rgba(255, 255, 255, .025);
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: .82rem;
      color: var(--muted2)
    }

    /* Audit Trail Styles */
    .audit-timeline {
      max-height: 400px;
      overflow-y: auto
    }

    .audit-item {
      display: flex;
      align-items: flex-start;
      gap: .75rem;
      padding: .7rem 0;
      border-bottom: 1px solid var(--border);
      transition: background .2s
    }

    .audit-item:last-child {
      border-bottom: none
    }

    .audit-item:hover {
      background: rgba(255, 255, 255, .02);
      margin: 0 -0.5rem;
      padding: .7rem .5rem
    }

    .audit-icon {
      width: 28px;
      height: 28px;
      border-radius: 7px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      font-size: .9rem
    }

    .audit-content {
      flex: 1;
      min-width: 0
    }

    .audit-title {
      font-size: .8rem;
      font-weight: 600;
      margin-bottom: .2rem
    }

    .audit-desc {
      font-size: .72rem;
      color: var(--muted2);
      word-break: break-word
    }

    .audit-meta {
      display: flex;
      gap: .75rem;
      margin-top: .3rem;
      font-size: .6rem;
      font-family: var(--mono);
      color: var(--muted)
    }

    .audit-time {
      display: flex;
      align-items: center;
      gap: .25rem
    }

    .info-chip {
      display: inline-flex;
      align-items: center;
      gap: .3rem;
      padding: .15rem .5rem;
      background: rgba(255, 255, 255, .05);
      border-radius: 12px;
      font-size: .6rem
    }

    .member-since {
      display: flex;
      align-items: center;
      gap: .5rem;
      margin-top: .5rem;
      padding-top: .5rem;
      border-top: 1px solid var(--border);
      font-size: .72rem;
      color: var(--muted2)
    }

    /* Form Error */
    .form-error {
      font-size: .75rem;
      color: var(--red);
      background: rgba(255, 59, 92, .08);
      border: 1px solid rgba(255, 59, 92, .2);
      border-radius: 7px;
      padding: .45rem .75rem;
      margin-bottom: .65rem
    }

    /* Modal Styles */
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
      width: min(90vw, 480px);
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

    .mbdy p {
      font-size: .82rem;
      color: var(--muted2);
      margin-bottom: .5rem
    }

    .otp-input-group {
      display: flex;
      gap: 0.5rem;
      justify-content: center;
      margin: 1.5rem 0
    }

    .otp-digit {
      width: 50px;
      height: 60px;
      text-align: center;
      font-size: 1.5rem;
      font-family: var(--mono);
      font-weight: 600;
      background: var(--bg2);
      border: 1px solid var(--border2);
      border-radius: 10px;
      color: var(--text);
      outline: none;
      transition: var(--t)
    }

    .otp-digit:focus {
      border-color: var(--blue);
      box-shadow: 0 0 0 2px rgba(59, 139, 255, .2)
    }

    .otp-digit::-webkit-outer-spin-button,
    .otp-digit::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0
    }

    .resend-timer {
      text-align: center;
      font-size: 0.75rem;
      color: var(--muted2);
      margin-top: 0.75rem
    }

    .resend-link {
      color: var(--blue);
      cursor: pointer;
      text-decoration: none;
      font-weight: 500
    }

    .resend-link:hover {
      text-decoration: underline
    }

    /* Toast */
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
  </style>
</head>

<body>
  <div class="bg-grid"></div>
  <div id="app">

    <!-- Sidebar -->
    <aside id="sidebar">
      <div class="sb-brand">
        <div class="shield"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white"
            stroke-width="2.2">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
          </svg></div>
        <div class="sb-brand-text">
          <h2>CyberShield</h2><span class="badge">Vendor Portal</span>
        </div>
        <button class="sb-toggle" onclick="toggleSidebar()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2.2">
            <polyline points="15 18 9 12 15 6" />
          </svg></button>
      </div>
      <div class="sb-section">
        <div class="sb-label">Main Menu</div>
        <a class="sb-item" href="index.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9">
              <rect x="3" y="3" width="7" height="7" rx="1.2" />
              <rect x="14" y="3" width="7" height="7" rx="1.2" />
              <rect x="3" y="14" width="7" height="7" rx="1.2" />
              <rect x="14" y="14" width="7" height="7" rx="1.2" />
            </svg></span><span class="sb-text">Dashboard</span></a>
        <a class="sb-item" href="assessment.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9">
              <path d="M9 11l3 3L22 4" />
              <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
            </svg></span><span class="sb-text">Take Assessment</span></a>
        <a class="sb-item" href="result.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9">
              <line x1="18" y1="20" x2="18" y2="10" />
              <line x1="12" y1="20" x2="12" y2="4" />
              <line x1="6" y1="20" x2="6" y2="14" />
            </svg></span><span class="sb-text">My Results</span></a>
        <a class="sb-item" href="review.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9">
              <path d="M8 6l4-4 4 4" />
              <path d="M12 2v13" />
              <path d="M20 21H4" />
              <path d="M17 12h3v9" />
              <path d="M4 12h3v9" />
            </svg></span><span class="sb-text">Review</span></a>
        <a class="sb-item" href="certificates.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9">
              <circle cx="12" cy="8" r="6" />
              <path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11" />
            </svg></span><span class="sb-text">Certificates</span></a>
        <div class="sb-divider"></div>
        <div class="sb-label">Account</div>
        <a class="sb-item active" href="profile.php"><span class="sb-icon"><svg width="15" height="15"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
              <circle cx="12" cy="7" r="4" />
            </svg></span><span class="sb-text">My Profile</span></a>
        <a class="sb-item" href="security-tips.php"><span class="sb-icon"><svg width="15" height="15"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg></span><span class="sb-text">Security Tips</span></a>
        <a class="sb-item" href="terms.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9">
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
            <p><?php echo htmlspecialchars($user['full_name']); ?></p>
            <span><?php echo htmlspecialchars($user['role'] ?? 'Vendor'); ?></span>
          </div>
        </div>
        <button class="btn-sb-logout" onclick="doLogout()">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <polyline points="16 17 21 12 16 7" />
            <line x1="21" y1="12" x2="9" y2="12" />
          </svg>
          <span>Sign Out</span>
        </button>
      </div>
    </aside>

    <!-- Main Content -->
    <div id="main">
      <div class="topbar">
        <div>
          <div class="tb-bc">
            <span class="tb-app">CyberShield</span>
            <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M6 4l4 4-4 4" />
            </svg>
            <span class="tb-title">My Profile</span>
          </div>
          <p class="tb-sub">Manage your account information and preferences</p>
        </div>
        <div class="tb-right">
          <div class="tb-search-wrap">
            <span class="tb-search-icon"><svg width="12" height="12" viewBox="0 0 20 20" fill="none">
                <circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.7" />
                <path d="M15 15l3 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
              </svg></span>
            <input type="text" class="tb-search" placeholder="Search assessments, tips…" autocomplete="off"
              id="global-search" onkeyup="filterAuditTrail()" />
          </div>
          <span class="tb-date" id="tb-date"></span>
          <div class="tb-divider"></div>
          <button class="tb-icon-btn" onclick="toggleTheme()" title="Toggle theme">
            <svg id="tmoon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
              stroke-width="1.8">
              <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 21 12.79z" />
            </svg>
            <svg id="tsun" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
              stroke-width="1.8" style="display:none">
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
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
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
                class="tb-admin-role"><?php echo htmlspecialchars($user['role'] ?? 'Vendor'); ?></span></div>
            <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"
              style="color:var(--muted);margin-left:.2rem">
              <path d="M4 6l4 4 4-4" />
            </svg>
          </a>
        </div>
      </div>

      <div class="content">
        <div class="sec-hdr">
          <h2>My Profile</h2>
          <p>Manage your account information and preferences.</p>
        </div>

        <div class="profile-grid">

          <!-- Left Column -->
          <div>
            <!-- Account Info -->
            <div class="card">
              <div class="card-title">Account Information</div>
              <div class="profile-avatar-wrap">
                <div class="profile-avatar" id="profile-avatar-big">
                  <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
                <div>
                  <div class="profile-display-name" id="profile-name-display">
                    <?php echo htmlspecialchars($user['full_name']); ?></div>
                  <div class="profile-display-email" id="profile-email-display">
                    <?php echo htmlspecialchars($user['email']); ?></div>
                </div>
              </div>
              <div class="fg">
                <label class="fl">Display Name</label>
                <input class="fi" type="text" id="profile-name" placeholder="Your full name"
                  value="<?php echo htmlspecialchars($user['full_name']); ?>">
              </div>
              <div class="fg">
                <label class="fl">Email Address</label>
                <input class="fi" type="email" id="profile-email" placeholder="your.email@example.com"
                  value="<?php echo htmlspecialchars($user['email']); ?>">
              </div>
              <div class="fg">
                <label class="fl">Store / Company Name</label>
                <input class="fi" type="text" id="profile-company" placeholder="Your store name"
                  value="<?php echo htmlspecialchars($user['store_name'] ?? ''); ?>">
              </div>
              <button class="btn btn-p btn-full" onclick="requestProfileOTP()">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                  <polyline points="17 21 17 13 7 13 7 21" />
                  <polyline points="7 3 7 8 15 8" />
                </svg>
                Save Changes
              </button>
            </div>

            <!-- Change Password -->
            <div class="card">
              <div class="card-title">Change Password</div>
              <div class="fg">
                <label class="fl">Current Password</label>
                <input class="fi" type="password" id="pw-current" placeholder="••••••••">
              </div>
              <div class="fg">
                <label class="fl">New Password</label>
                <input class="fi" type="password" id="pw-new" placeholder="••••••••">
              </div>
              <div class="fg">
                <label class="fl">Confirm New Password</label>
                <input class="fi" type="password" id="pw-confirm" placeholder="••••••••">
              </div>
              <div id="pw-change-error" class="form-error" style="display:none"></div>
              <button class="btn btn-p btn-full" onclick="requestPasswordOTP()">Update Password</button>
            </div>
          </div>

          <!-- Right Column -->
          <div>
            <!-- Statistics -->
            <div class="card">
              <div class="card-title">Your Statistics</div>
              <div class="stats-grid">
                <div class="stat-card" style="--accent:var(--blue)">
                  <div class="stat-val"><?php echo $stats['total_assessments'] ?? 0; ?></div>
                  <div class="stat-lbl">Assessments</div>
                </div>
                <div class="stat-card" style="--accent:var(--teal)">
                  <div class="stat-val"><?php echo round($stats['avg_score'] ?? 0, 1); ?>%</div>
                  <div class="stat-lbl">Avg Score</div>
                </div>
                <div class="stat-card" style="--accent:var(--green)">
                  <div class="stat-val"><?php echo $stats['best_score'] ?? 0; ?>%</div>
                  <div class="stat-lbl">Best Score</div>
                </div>
                <div class="stat-card" style="--accent:var(--purple)">
                  <div class="stat-val">
                    <?php echo $stats['latest_score'] ?? 'N/A'; ?><?php echo $stats['latest_score'] ? '%' : ''; ?></div>
                  <div class="stat-lbl">Latest Score</div>
                </div>
              </div>
              <?php if ($stats['latest_rank']): ?>
                <div class="rank-display">Current Risk Level:&nbsp;<span
                    class="rank r<?php echo $stats['latest_rank']; ?>"><?php echo $stats['latest_rank']; ?></span></div>
              <?php endif; ?>
              <div class="member-since"><span>📅 Member
                  since</span><strong><?php echo date('F j, Y', strtotime($account_created)); ?></strong></div>
              <?php if ($last_login): ?>
                <div class="member-since" style="margin-top:0.3rem;border-top:none;padding-top:0;"><span>🕒 Last
                    login</span><strong><?php echo date('M j, g:i A', strtotime($last_login['created_at'])); ?></strong>
                </div><?php endif; ?>
            </div>

            <!-- NEW: Security Audit Trail Container -->
            <div class="card">
              <div class="card-title">🔒 Security Audit Trail</div>
              <div class="audit-timeline" id="audit-timeline">
                <?php if (count($audit_logs) > 0): ?>
                  <?php foreach ($audit_logs as $log): ?>
                    <div class="audit-item"
                      data-search="<?php echo strtolower($log['action_description'] . ' ' . $log['action_display']); ?>">
                      <div class="audit-icon"
                        style="background:<?php echo $log['action_color']; ?>20; border:1px solid <?php echo $log['action_color']; ?>40">
                        <?php echo $log['action_display'][0]; ?></div>
                      <div class="audit-content">
                        <div class="audit-title" style="color:<?php echo $log['action_color']; ?>">
                          <?php echo htmlspecialchars($log['action_display']); ?></div>
                        <div class="audit-desc"><?php echo htmlspecialchars($log['action_description']); ?></div>
                        <div class="audit-meta">
                          <span class="audit-time">🕐 <?php echo date('M d, H:i', strtotime($log['created_at'])); ?></span>
                          <?php if ($log['ip_address']): ?>
                            <span class="info-chip">🌐 <?php echo htmlspecialchars($log['ip_address']); ?></span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div style="text-align:center;padding:2rem;color:var(--muted2);"><span>📭</span>
                    <p style="margin-top:.5rem">No audit records found yet.<br>Your activities will appear here.</p>
                  </div>
                <?php endif; ?>
              </div>
              <?php if (count($audit_logs) >= 15): ?>
                <div style="margin-top:.75rem;text-align:center"><span class="info-chip">Showing last 15 activities</span>
                </div>
              <?php endif; ?>
            </div>

            <!-- Danger Zone -->
            <div class="card danger">
              <div class="card-title">⚠ Danger Zone</div>
              <p style="font-size:.8rem;color:var(--muted2);margin-bottom:.85rem">Permanently delete all your assessment
                history and cached data. This action cannot be undone.</p>
              <button class="btn btn-d" onclick="clearAllData()">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="3 6 5 6 21 6" />
                  <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                  <path d="M10 11v6M14 11v6" />
                  <path d="M9 6V4h6v2" />
                </svg>
                Clear All Data
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- OTP Modal for Profile Changes -->
  <div id="otp-profile-modal" class="mo hidden" onclick="if(event.target===this)closeOTPModal('profile')">
    <div class="modal">
      <div class="mhdr">
        <h3>🔐 Verify with OTP</h3>
        <button class="mcl" onclick="closeOTPModal('profile')"><svg width="13" height="13" viewBox="0 0 24 24"
            fill="none" stroke="currentColor" stroke-width="2.2">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg></button>
      </div>
      <div class="mbdy">
        <p style="text-align:center;margin-bottom:0.5rem">A verification code has been sent to your email</p>
        <p style="text-align:center;font-size:0.75rem;color:var(--muted2);margin-bottom:1rem">
          <?php echo htmlspecialchars($user['email']); ?></p>
        <div class="otp-input-group" id="profile-otp-group">
          <input type="text" maxlength="1" class="otp-digit" id="profile-otp-1"
            onkeyup="moveToNext(this, 'profile-otp-2')" onkeydown="handleBackspace(event, 'profile-otp-1')">
          <input type="text" maxlength="1" class="otp-digit" id="profile-otp-2"
            onkeyup="moveToNext(this, 'profile-otp-3')" onkeydown="handleBackspace(event, 'profile-otp-1')">
          <input type="text" maxlength="1" class="otp-digit" id="profile-otp-3"
            onkeyup="moveToNext(this, 'profile-otp-4')" onkeydown="handleBackspace(event, 'profile-otp-2')">
          <input type="text" maxlength="1" class="otp-digit" id="profile-otp-4"
            onkeyup="moveToNext(this, 'profile-otp-5')" onkeydown="handleBackspace(event, 'profile-otp-3')">
          <input type="text" maxlength="1" class="otp-digit" id="profile-otp-5"
            onkeyup="moveToNext(this, 'profile-otp-6')" onkeydown="handleBackspace(event, 'profile-otp-4')">
          <input type="text" maxlength="1" class="otp-digit" id="profile-otp-6" onkeyup="verifyProfileOTP()"
            onkeydown="handleBackspace(event, 'profile-otp-5')">
        </div>
        <div id="profile-otp-error" class="form-error" style="display:none;text-align:center"></div>
        <div class="resend-timer" id="profile-resend-timer">Resend code in <span id="profile-countdown">60</span>
          seconds</div>
        <div style="display:flex;gap:0.75rem;justify-content:center;margin-top:1rem">
          <button class="btn btn-s" onclick="closeOTPModal('profile')">Cancel</button>
          <button class="btn btn-p" onclick="verifyProfileOTP()">Verify & Save</button>
        </div>
      </div>
    </div>
  </div>

  <!-- OTP Modal for Password Change -->
  <div id="otp-password-modal" class="mo hidden" onclick="if(event.target===this)closeOTPModal('password')">
    <div class="modal">
      <div class="mhdr">
        <h3>🔐 Verify with OTP</h3>
        <button class="mcl" onclick="closeOTPModal('password')"><svg width="13" height="13" viewBox="0 0 24 24"
            fill="none" stroke="currentColor" stroke-width="2.2">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg></button>
      </div>
      <div class="mbdy">
        <p style="text-align:center;margin-bottom:0.5rem">A verification code has been sent to your email</p>
        <p style="text-align:center;font-size:0.75rem;color:var(--muted2);margin-bottom:1rem">
          <?php echo htmlspecialchars($user['email']); ?></p>
        <div class="otp-input-group" id="password-otp-group">
          <input type="text" maxlength="1" class="otp-digit" id="password-otp-1"
            onkeyup="moveToNext(this, 'password-otp-2')" onkeydown="handleBackspace(event, 'password-otp-1')">
          <input type="text" maxlength="1" class="otp-digit" id="password-otp-2"
            onkeyup="moveToNext(this, 'password-otp-3')" onkeydown="handleBackspace(event, 'password-otp-1')">
          <input type="text" maxlength="1" class="otp-digit" id="password-otp-3"
            onkeyup="moveToNext(this, 'password-otp-4')" onkeydown="handleBackspace(event, 'password-otp-2')">
          <input type="text" maxlength="1" class="otp-digit" id="password-otp-4"
            onkeyup="moveToNext(this, 'password-otp-5')" onkeydown="handleBackspace(event, 'password-otp-3')">
          <input type="text" maxlength="1" class="otp-digit" id="password-otp-5"
            onkeyup="moveToNext(this, 'password-otp-6')" onkeydown="handleBackspace(event, 'password-otp-4')">
          <input type="text" maxlength="1" class="otp-digit" id="password-otp-6" onkeyup="verifyPasswordOTP()"
            onkeydown="handleBackspace(event, 'password-otp-5')">
        </div>
        <div id="password-otp-error" class="form-error" style="display:none;text-align:center"></div>
        <div class="resend-timer" id="password-resend-timer">Resend code in <span id="password-countdown">60</span>
          seconds</div>
        <div style="display:flex;gap:0.75rem;justify-content:center;margin-top:1rem">
          <button class="btn btn-s" onclick="closeOTPModal('password')">Cancel</button>
          <button class="btn btn-p" onclick="verifyPasswordOTP()">Verify & Update</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Generic Confirmation Modal (for logout, clear data) -->
  <div id="modal-overlay" class="mo hidden" onclick="if(event.target===this)closeModal()">
    <div class="modal">
      <div class="mhdr">
        <h3 id="modal-title">Confirm Action</h3><button class="mcl" onclick="closeModal()"><svg width="13" height="13"
            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg></button>
      </div>
      <div class="mbdy" id="modal-body">
        <p>Are you sure you want to clear all your data?</p>
        <p style="color:var(--red)">This action cannot be undone.</p>
        <div style="display:flex;gap:.5rem;margin-top:1rem"><button class="btn btn-d" onclick="confirmClearData()">Yes,
            Clear All</button><button class="btn btn-s" onclick="closeModal()">Cancel</button></div>
      </div>
    </div>
  </div>

  <div id="toast-c"></div>

  <script>
    // Global variables to store pending data
    let pendingProfileData = null;
    let pendingPasswordData = null;
    let profileResendTimer = null;
    let passwordResendTimer = null;
    let profileCountdown = 60;
    let passwordCountdown = 60;

    // ==================== Helper Functions ====================
    function isDark() { return document.documentElement.getAttribute('data-theme') === 'dark' }
    function toggleTheme() {
      const d = !isDark();
      document.documentElement.setAttribute('data-theme', d ? 'dark' : 'light');
      localStorage.setItem('cs_th', d ? 'dark' : 'light');
      const m = document.getElementById('tmoon'), s = document.getElementById('tsun');
      if (m) m.style.display = d ? '' : 'none';
      if (s) s.style.display = d ? 'none' : '';
    }
    function toggleNotif() { document.getElementById('np').classList.toggle('hidden'); }
    function clearNotifs() { document.getElementById('np-list').innerHTML = '<p class="np-empty">No notifications</p>'; document.getElementById('notif-dot').style.display = 'none'; document.getElementById('np').classList.add('hidden'); }
    function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); localStorage.setItem('cs_sb', document.getElementById('sidebar').classList.contains('collapsed') ? '1' : '0'); }
    function closeModal() { document.getElementById('modal-overlay').classList.add('hidden'); }
    function clearAllData() { document.getElementById('modal-overlay').classList.remove('hidden'); }
    function confirmClearData() { localStorage.clear(); closeModal(); showToast('All local data cleared', 'green'); setTimeout(() => window.location.reload(), 1000); }
    function showToast(msg, color = 'blue') { const cols = { blue: 'var(--blue)', green: 'var(--green)', red: 'var(--red)', yellow: 'var(--yellow)' }; const t = document.createElement('div'); t.className = 'toast'; t.innerHTML = `<span class="ti" style="background:${cols[color] || cols.blue}"></span><span>${msg}</span>`; document.getElementById('toast-c').appendChild(t); setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; setTimeout(() => t.remove(), 300); }, 2500); }
    function doLogout() { const modal = document.getElementById('modal-overlay'); const modalTitle = document.getElementById('modal-title'); const modalBody = document.getElementById('modal-body'); modalTitle.textContent = 'Confirm Logout'; modalBody.innerHTML = `<div style="text-align:center;padding:1rem;"><div style="font-size:3rem;margin-bottom:1rem;color:var(--red);">🚪</div><h3 style="margin-bottom:0.5rem;color:var(--text);">Are you sure you want to sign out?</h3><p style="color:var(--muted2);margin-bottom:1.5rem;">You will be redirected to the landing page.</p><div style="display:flex;gap:0.75rem;justify-content:center;"><button class="btn btn-s" onclick="closeModal()" style="padding:0.5rem 1.5rem;">Cancel</button><button class="btn btn-d" onclick="confirmLogout()" style="padding:0.5rem 1.5rem;">Sign Out</button></div></div>`; modal.classList.remove('hidden'); }
    function confirmLogout() { window.location.href = '../landingpage.php'; }
    function filterAuditTrail() { const search = document.getElementById('global-search').value.toLowerCase(); const items = document.querySelectorAll('.audit-item'); items.forEach(item => { const text = item.getAttribute('data-search') || ''; if (text.includes(search) || search === '') item.style.display = 'flex'; else item.style.display = 'none'; }); }

    // ==================== OTP Input Handlers ====================
    function moveToNext(current, nextId) {
      if (current.value.length === 1) {
        const next = document.getElementById(nextId);
        if (next) next.focus();
      }
    }

    function handleBackspace(event, prevId) {
      if (event.key === 'Backspace' && event.target.value === '') {
        const prev = document.getElementById(prevId);
        if (prev) prev.focus();
      }
    }

    function getOTPValue(prefix) {
      let otp = '';
      for (let i = 1; i <= 6; i++) {
        const digit = document.getElementById(`${prefix}-otp-${i}`).value;
        if (!digit) return null;
        otp += digit;
      }
      return otp;
    }

    function clearOTPInputs(prefix) {
      for (let i = 1; i <= 6; i++) {
        const input = document.getElementById(`${prefix}-otp-${i}`);
        if (input) input.value = '';
      }
      const firstInput = document.getElementById(`${prefix}-otp-1`);
      if (firstInput) firstInput.focus();
    }

    function closeOTPModal(type) {
      const modal = document.getElementById(`otp-${type}-modal`);
      if (modal) modal.classList.add('hidden');
      // Clear OTP inputs
      clearOTPInputs(type);
      // Clear error message
      const errorEl = document.getElementById(`${type}-otp-error`);
      if (errorEl) errorEl.style.display = 'none';
    }

    // ==================== Profile OTP Flow ====================
    async function requestProfileOTP() {
      const fullName = document.getElementById('profile-name').value.trim();
      const email = document.getElementById('profile-email').value.trim();
      const storeName = document.getElementById('profile-company').value.trim();

      if (!fullName) { showToast('Display name is required', 'red'); return; }
      if (!email) { showToast('Email address is required', 'red'); return; }
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) { showToast('Please enter a valid email address', 'red'); return; }

      // Store pending data
      pendingProfileData = { full_name: fullName, email: email, store_name: storeName };

      try {
        // Send OTP request to API
        const response = await fetch('../api/send_otp.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ type: 'profile' })
        });

        const result = await response.json();

        if (result.success) {
          showToast('OTP sent to your email', 'green');
          // Reset countdown
          profileCountdown = 60;
          startProfileResendTimer();
          // Clear previous OTP inputs
          clearOTPInputs('profile');
          // Show OTP modal
          document.getElementById('otp-profile-modal').classList.remove('hidden');
          // Clear any previous error
          document.getElementById('profile-otp-error').style.display = 'none';
        } else {
          showToast(result.error || 'Failed to send OTP', 'red');
        }
      } catch (e) {
        showToast('Error connecting to server', 'red');
      }
    }

    function startProfileResendTimer() {
      if (profileResendTimer) clearInterval(profileResendTimer);
      const timerSpan = document.getElementById('profile-countdown');
      const resendDiv = document.getElementById('profile-resend-timer');

      profileResendTimer = setInterval(() => {
        if (profileCountdown <= 1) {
          clearInterval(profileResendTimer);
          resendDiv.innerHTML = '<span class="resend-link" onclick="resendProfileOTP()">Resend Code</span>';
        } else {
          profileCountdown--;
          timerSpan.textContent = profileCountdown;
        }
      }, 1000);
    }

    async function resendProfileOTP() {
      try {
        // Send OTP request to API
        const response = await fetch('../api/send_otp.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ type: 'profile' })
        });

        const result = await response.json();

        if (result.success) {
          showToast('OTP resent to your email', 'green');
          profileCountdown = 60;
          startProfileResendTimer();
        } else {
          showToast(result.error || 'Failed to resend OTP', 'red');
        }
      } catch (e) {
        showToast('Error connecting to server', 'red');
      }
    }

    async function verifyProfileOTP() {
      const otp = getOTPValue('profile');
      if (!otp) {
        document.getElementById('profile-otp-error').textContent = 'Please enter the 6-digit code';
        document.getElementById('profile-otp-error').style.display = 'block';
        return;
      }

      const errorEl = document.getElementById('profile-otp-error');
      errorEl.style.display = 'none';

      try {
        // Verify OTP first
        const verifyRes = await fetch('../api/verify_otp.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ type: 'profile', otp: otp })
        });

        const verifyResult = await verifyRes.json();

        if (verifyResult.success) {
          // OTP is correct, now update profile
          const updateRes = await fetch('../api/update_profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(pendingProfileData)
          });
          const updateResult = await updateRes.json();

          if (updateResult.success) {
            // Update UI
            document.getElementById('profile-name-display').textContent = pendingProfileData.full_name;
            document.getElementById('profile-email-display').textContent = pendingProfileData.email;
            const sidebarName = document.querySelector('.sb-user-info p');
            if (sidebarName) sidebarName.textContent = pendingProfileData.full_name;
            const topbarName = document.querySelector('.tb-admin-name');
            if (topbarName) topbarName.textContent = pendingProfileData.full_name;
            const avatar = document.getElementById('profile-avatar-big');
            if (avatar) avatar.textContent = pendingProfileData.full_name.charAt(0).toUpperCase();

            closeOTPModal('profile');
            showToast('Profile updated successfully!', 'green');
            setTimeout(() => window.location.reload(), 1000);
          } else {
            errorEl.textContent = updateResult.error || 'Error updating profile';
            errorEl.style.display = 'block';
          }
        } else {
          errorEl.textContent = verifyResult.error || 'Invalid OTP';
          errorEl.style.display = 'block';
        }
      } catch (e) {
        errorEl.textContent = 'Error connecting to server';
        errorEl.style.display = 'block';
      }
    }

    // ==================== Password OTP Flow ====================
    async function requestPasswordOTP() {
      const current = document.getElementById('pw-current').value;
      const newPass = document.getElementById('pw-new').value;
      const confirm = document.getElementById('pw-confirm').value;
      const errorEl = document.getElementById('pw-change-error');
      errorEl.style.display = 'none';
      errorEl.textContent = '';

      if (!current) { errorEl.textContent = 'Current password is required'; errorEl.style.display = 'block'; return; }
      if (!newPass) { errorEl.textContent = 'New password is required'; errorEl.style.display = 'block'; return; }
      if (!confirm) { errorEl.textContent = 'Please confirm your new password'; errorEl.style.display = 'block'; return; }
      if (newPass !== confirm) { errorEl.textContent = 'Passwords do not match'; errorEl.style.display = 'block'; return; }
      if (newPass.length < 6) { errorEl.textContent = 'Password must be at least 6 characters'; errorEl.style.display = 'block'; return; }
      if (newPass === current) { errorEl.textContent = 'New password must be different from current password'; errorEl.style.display = 'block'; return; }

      // Store pending password data
      pendingPasswordData = { new_password: newPass, current_password: current };

      try {
        // Send OTP request to API
        const response = await fetch('../api/send_otp.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ type: 'password' })
        });

        const result = await response.json();

        if (result.success) {
          showToast('OTP sent to your email', 'green');
          passwordCountdown = 60;
          startPasswordResendTimer();
          clearOTPInputs('password');
          document.getElementById('otp-password-modal').classList.remove('hidden');
          document.getElementById('password-otp-error').style.display = 'none';
        } else {
          showToast(result.error || 'Failed to send OTP', 'red');
        }
      } catch (e) {
        showToast('Error connecting to server', 'red');
      }
    }

    function startPasswordResendTimer() {
      if (passwordResendTimer) clearInterval(passwordResendTimer);
      const timerSpan = document.getElementById('password-countdown');
      const resendDiv = document.getElementById('password-resend-timer');

      passwordResendTimer = setInterval(() => {
        if (passwordCountdown <= 1) {
          clearInterval(passwordResendTimer);
          resendDiv.innerHTML = '<span class="resend-link" onclick="resendPasswordOTP()">Resend Code</span>';
        } else {
          passwordCountdown--;
          timerSpan.textContent = passwordCountdown;
        }
      }, 1000);
    }

    async function resendPasswordOTP() {
      try {
        // Send OTP request to API
        const response = await fetch('../api/send_otp.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ type: 'password' })
        });

        const result = await response.json();

        if (result.success) {
          showToast('OTP resent to your email', 'green');
          passwordCountdown = 60;
          startPasswordResendTimer();
        } else {
          showToast(result.error || 'Failed to resend OTP', 'red');
        }
      } catch (e) {
        showToast('Error connecting to server', 'red');
      }
    }

    async function verifyPasswordOTP() {
      const otp = getOTPValue('password');
      if (!otp) {
        document.getElementById('password-otp-error').textContent = 'Please enter the 6-digit code';
        document.getElementById('password-otp-error').style.display = 'block';
        return;
      }

      const errorEl = document.getElementById('password-otp-error');
      errorEl.style.display = 'none';

      try {
        // Verify OTP first
        const verifyRes = await fetch('../api/verify_otp.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ type: 'password', otp: otp })
        });

        const verifyResult = await verifyRes.json();

        if (verifyResult.success) {
          // OTP is correct, now change password
          const changeRes = await fetch('../api/change_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              current_password: pendingPasswordData.current_password,
              new_password: pendingPasswordData.new_password
            })
          });
          const changeResult = await changeRes.json();

          if (changeResult.success) {
            closeOTPModal('password');
            document.getElementById('pw-current').value = '';
            document.getElementById('pw-new').value = '';
            document.getElementById('pw-confirm').value = '';
            showToast('Password changed successfully!', 'green');
            setTimeout(() => window.location.reload(), 1000);
          } else {
            errorEl.textContent = changeResult.error || 'Error changing password';
            errorEl.style.display = 'block';
          }
        } else {
          errorEl.textContent = verifyResult.error || 'Invalid OTP';
          errorEl.style.display = 'block';
        }
      } catch (e) {
        errorEl.textContent = 'Error connecting to server';
        errorEl.style.display = 'block';
      }
    }

    // ==================== Initialization ====================
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
      document.addEventListener('click', e => { const p = document.getElementById('np'); if (p && !p.classList.contains('hidden') && !p.contains(e.target) && !e.target.closest('.notif-wrap')) p.classList.add('hidden'); });
    });
  </script>
</body>

</html>