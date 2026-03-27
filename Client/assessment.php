<?php
session_start();
require_once '../config.php';

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

$questions = [
    ['id'=>1,'category'=>'password','text'=>'Do you use a password manager to store and generate strong passwords?','options'=>['Yes, always'=>100,'Sometimes'=>50,'No, I remember them'=>25,'I use the same password everywhere'=>0]],
    ['id'=>2,'category'=>'password','text'=>'How often do you change your passwords?','options'=>['Every 30 days'=>100,'Every 90 days'=>75,'Every 6 months'=>50,'Only when forced'=>25,'Never'=>0]],
    ['id'=>3,'category'=>'password','text'=>'Do you use multi-factor authentication (MFA) on your important accounts?','options'=>['Yes, on all accounts'=>100,'On most accounts'=>75,'On a few accounts'=>50,'No'=>0]],
    ['id'=>4,'category'=>'phishing','text'=>'How do you verify suspicious emails asking for credentials?','options'=>['Contact sender through known channel'=>100,'Check email headers'=>75,'Look for spelling errors'=>50,'Click links to verify'=>25,'I always trust emails'=>0]],
    ['id'=>5,'category'=>'phishing','text'=>'Have you completed security awareness training in the past year?','options'=>['Yes, with certification'=>100,'Yes, online course'=>75,'Only watched videos'=>50,'No training'=>0]],
    ['id'=>6,'category'=>'phishing','text'=>'What do you do when you receive an unexpected attachment?','options'=>['Verify with sender before opening'=>100,'Scan with antivirus'=>75,'Open if it looks legitimate'=>25,'Always open'=>0]],
    ['id'=>7,'category'=>'device','text'=>'Is your device protected with antivirus/anti-malware software?','options'=>['Yes, always updated'=>100,'Yes, but not always updated'=>50,'No antivirus'=>0]],
    ['id'=>8,'category'=>'device','text'=>'Do you lock your device when away from it?','options'=>['Always immediately'=>100,'Sometimes'=>50,'Never'=>0]],
    ['id'=>9,'category'=>'device','text'=>'How often do you update your operating system and applications?','options'=>['Automatically updated'=>100,'Weekly manual checks'=>75,'Monthly'=>50,'When reminded'=>25,'Never'=>0]],
    ['id'=>10,'category'=>'network','text'=>'Do you use a VPN when connecting to public Wi-Fi?','options'=>['Always'=>100,'Sometimes'=>50,'Never'=>0]],
    ['id'=>11,'category'=>'network','text'=>'Is your home Wi-Fi secured with WPA2/WPA3 encryption?','options'=>['Yes, with strong password'=>100,'Yes, default password'=>50,'No encryption'=>0,'Not sure'=>25]],
    ['id'=>12,'category'=>'network','text'=>'Do you have a firewall enabled on your network/devices?','options'=>['Yes, hardware and software'=>100,'Software only'=>75,'Hardware only'=>50,'No firewall'=>0]],
];

$initial = strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 1));
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Security Assessment — CyberShield</title>
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

    .sidebar-logo {
      display: flex;
      align-items: center;
      gap: .75rem;
      padding: 1rem .9rem .9rem;
      border-bottom: 1px solid var(--border);
      flex-shrink: 0
    }

    .shield-icon {
      width: 34px;
      height: 34px;
      background: linear-gradient(135deg, var(--blue), var(--purple));
      border-radius: 9px;
      display: grid;
      place-items: center;
      flex-shrink: 0;
      box-shadow: 0 0 16px rgba(59, 139, 255, .3)
    }

    .sidebar-brand {
      flex: 1;
      overflow: hidden;
      white-space: nowrap;
      font-family: var(--display);
      font-size: .95rem;
      font-weight: 700;
      letter-spacing: 1px
    }

    .sidebar-nav {
      flex: 1;
      overflow-y: auto;
      overflow-x: hidden;
      padding: .65rem 0
    }

    .sidebar-nav::-webkit-scrollbar {
      width: 3px
    }

    .sidebar-nav::-webkit-scrollbar-thumb {
      background: var(--border2);
      border-radius: 2px
    }

    .sidebar-section-label {
      font-family: var(--mono);
      font-size: .55rem;
      letter-spacing: 2px;
      text-transform: uppercase;
      color: var(--muted);
      padding: .5rem .9rem .25rem;
      white-space: nowrap;
      overflow: hidden
    }

    #sidebar.collapsed .sidebar-section-label {
      opacity: 0
    }

    .sidebar-item {
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

    .sidebar-item:hover {
      background: rgba(59, 139, 255, .07);
      color: var(--text)
    }

    .sidebar-item.active {
      background: rgba(59, 139, 255, .1);
      color: var(--blue)
    }

    .sidebar-item.active::before {
      content: '';
      position: absolute;
      left: 0;
      top: 20%;
      bottom: 20%;
      width: 3px;
      background: var(--blue);
      border-radius: 0 3px 3px 0
    }

    .sidebar-icon {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 18px;
      flex-shrink: 0
    }

    .sidebar-label {
      overflow: hidden
    }

    #sidebar.collapsed .sidebar-label {
      display: none
    }

    .sidebar-tooltip {
      position: absolute;
      left: 100%;
      top: 50%;
      transform: translateY(-50%);
      margin-left: .5rem;
      background: var(--bg3);
      border: 1px solid var(--border2);
      border-radius: 6px;
      padding: .4rem .6rem;
      font-size: .75rem;
      white-space: nowrap;
      opacity: 0;
      pointer-events: none;
      transition: opacity .18s;
      z-index: 100
    }

    .sidebar-item:hover .sidebar-tooltip {
      opacity: 1
    }

    #sidebar.collapsed .sidebar-tooltip {
      opacity: 1
    }

    .sidebar-bottom {
      border-top: 1px solid var(--border);
      padding: .75rem .9rem;
      flex-shrink: 0
    }

    .sidebar-user-card {
      display: flex;
      align-items: center;
      gap: .65rem;
      overflow: hidden;
      cursor: pointer;
      padding: .5rem;
      border-radius: 8px;
      transition: var(--t)
    }

    .sidebar-user-card:hover {
      background: rgba(255, 255, 255, .03)
    }

    .sidebar-avatar {
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

    .sidebar-user-info {
      overflow: hidden;
      white-space: nowrap
    }

    .sidebar-user-name {
      font-size: .82rem;
      font-weight: 600
    }

    .sidebar-user-role {
      font-size: .68rem;
      color: var(--muted2)
    }

    #sidebar.collapsed .sidebar-user-info {
      display: none
    }

    .sidebar-signout-btn {
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

    .sidebar-signout-btn:hover {
      background: rgba(255, 59, 92, .15)
    }

    #sidebar.collapsed .sidebar-signout-btn span {
      display: none
    }

    .sidebar-toggle {
      position: absolute;
      top: 1rem;
      right: -12px;
      width: 24px;
      height: 24px;
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: 6px;
      cursor: pointer;
      display: grid;
      place-items: center;
      color: var(--muted2);
      transition: var(--t);
      z-index: 11
    }

    .sidebar-toggle:hover {
      border-color: var(--blue);
      color: var(--text)
    }

    .mobile-menu-btn {
      display: none;
      position: fixed;
      top: 1rem;
      left: 1rem;
      z-index: 20;
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: .5rem;
      color: var(--text);
      font-size: .75rem;
      font-weight: 600;
      cursor: pointer;
      gap: .5rem;
      align-items: center;
      transition: var(--t)
    }

    #main-content {
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

    .topbar-left {
      display: flex;
      flex-direction: column;
      gap: .2rem
    }

    .topbar-breadcrumb {
      display: flex;
      align-items: center;
      gap: .4rem
    }

    .topbar-app-name {
      font-family: var(--mono);
      font-size: .68rem;
      color: var(--muted);
      letter-spacing: .5px
    }

    #topbar-page-title {
      font-family: var(--display);
      font-size: 1.05rem;
      letter-spacing: 1px
    }

    .topbar-right {
      display: flex;
      align-items: center;
      gap: .55rem
    }

    .topbar-search {
      position: relative;
      display: flex;
      align-items: center;
      background: rgba(255, 255, 255, .04);
      border: 1px solid var(--border2);
      border-radius: 8px;
      padding: .38rem .8rem .38rem 2rem;
      font-size: .78rem;
      color: var(--text);
      outline: none;
      width: 200px;
      transition: var(--t)
    }

    .topbar-search:focus-within {
      border-color: rgba(59, 139, 255, .4)
    }

    .topbar-search svg {
      position: absolute;
      left: .65rem;
      color: var(--muted2);
      pointer-events: none
    }

    .topbar-search input {
      background: none;
      border: none;
      outline: none;
      color: var(--text);
      font-family: var(--font);
      font-size: .78rem;
      width: 100%
    }

    .topbar-search input::placeholder {
      color: var(--muted)
    }

    .topbar-divider {
      width: 1px;
      height: 20px;
      background: var(--border2);
      margin: 0 .2rem
    }

    .topbar-ctrl-btn {
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

    .topbar-ctrl-btn:hover {
      border-color: var(--blue);
      color: var(--text)
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

    .notif-panel {
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

    .notif-panel.hidden {
      display: none
    }

    .notif-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: .75rem 1rem;
      border-bottom: 1px solid var(--border);
      font-size: .82rem;
      font-weight: 600
    }

    .notif-header button {
      font-size: .72rem;
      color: var(--muted2);
      background: none;
      border: none;
      cursor: pointer
    }

    .notif-empty {
      font-size: .8rem;
      color: var(--muted2);
      padding: 1rem;
      text-align: center
    }

    .topbar-user {
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

    .topbar-user:hover {
      border-color: rgba(255, 59, 92, .28);
      background: rgba(255, 59, 92, .06)
    }

    .topbar-avatar {
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

    .topbar-user-info {
      display: flex;
      flex-direction: column
    }

    .topbar-user-name {
      font-size: .78rem;
      font-weight: 600;
      line-height: 1.2
    }

    .topbar-user-role {
      font-size: .6rem;
      color: var(--red);
      letter-spacing: .5px;
      font-family: var(--mono)
    }

    .page {
      flex: 1;
      overflow-y: auto;
      padding: 1.5rem
    }

    .page::-webkit-scrollbar {
      width: 4px
    }

    .page::-webkit-scrollbar-thumb {
      background: var(--border2);
      border-radius: 2px
    }

    .page-inner {
      max-width: 900px;
      margin: 0 auto
    }

    .page-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
      gap: 1rem
    }

    .page-title {
      font-family: var(--display);
      font-size: 1.5rem;
      font-weight: 700;
      letter-spacing: .5px;
      color: var(--text)
    }

    .page-subtitle {
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

    .btn-primary {
      background: var(--blue);
      color: #fff
    }

    .btn-primary:hover {
      background: #2e7ae8
    }

    .btn-secondary {
      background: rgba(255, 255, 255, .05);
      color: var(--muted2);
      border: 1px solid var(--border2)
    }

    .btn-secondary:hover {
      border-color: var(--blue);
      color: var(--text)
    }

    .btn-success {
      background: var(--green);
      color: #fff
    }

    .btn-success:hover {
      background: #0ec473
    }

    .btn-outline {
      background: transparent;
      color: var(--muted2);
      border: 1px solid var(--border2)
    }

    .btn-outline:hover {
      border-color: var(--blue);
      color: var(--text)
    }

    .btn-sm {
      font-size: .72rem;
      padding: .32rem .7rem
    }

    .assess-header {
      margin-bottom: 1.5rem
    }

    .progress-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: .75rem
    }

    .progress-meta-right {
      display: flex;
      align-items: center;
      gap: 1rem
    }

    .timer-wrap {
      display: flex;
      align-items: center;
      gap: .4rem;
      font-family: var(--mono);
      font-size: .78rem;
      color: var(--muted2)
    }

    .timer-val {
      font-weight: 600;
      color: var(--text)
    }

    .timer-val.warning {
      color: var(--yellow)
    }

    .timer-val.urgent {
      color: var(--red)
    }

    .progress-pct {
      font-family: var(--mono);
      font-weight: 600;
      color: var(--blue)
    }

    .progress-bar-wrap {
      height: 6px;
      background: var(--border2);
      border-radius: 3px;
      margin-bottom: .5rem;
      overflow: hidden
    }

    .progress-bar-fill {
      height: 100%;
      background: linear-gradient(90deg, var(--blue), var(--purple));
      border-radius: 3px;
      transition: width .3s ease
    }

    .timer-bar-wrap {
      height: 3px;
      background: var(--border2);
      border-radius: 2px;
      overflow: hidden
    }

    .timer-bar-fill {
      height: 100%;
      background: var(--teal);
      border-radius: 2px;
      transition: width 1s linear
    }

    .timer-bar-fill.warning {
      background: var(--yellow)
    }

    .timer-bar-fill.urgent {
      background: var(--red)
    }

    .q-card {
      padding: 2rem;
      margin-bottom: 1.5rem
    }

    .q-num {
      font-family: var(--mono);
      font-size: .7rem;
      color: var(--muted2);
      margin-bottom: .5rem
    }

    .q-category {
      display: inline-block;
      padding: .3rem .6rem;
      border-radius: 6px;
      font-family: var(--mono);
      font-size: .6rem;
      font-weight: 600;
      letter-spacing: 1px;
      text-transform: uppercase;
      margin-bottom: 1rem
    }

    .category-password {
      background: rgba(59, 139, 255, .12);
      color: var(--blue)
    }

    .category-phishing {
      background: rgba(255, 140, 66, .12);
      color: var(--orange)
    }

    .category-device {
      background: rgba(16, 217, 130, .12);
      color: var(--green)
    }

    .category-network {
      background: rgba(123, 114, 240, .12);
      color: var(--purple)
    }

    .q-text {
      font-size: 1.1rem;
      font-weight: 600;
      line-height: 1.5;
      margin-bottom: 1.5rem;
      color: var(--text)
    }

    .options-list {
      display: grid;
      gap: .75rem
    }

    .option-btn {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1rem;
      background: rgba(255, 255, 255, .03);
      border: 1px solid var(--border);
      border-radius: 10px;
      cursor: pointer;
      transition: var(--t)
    }

    .option-btn:hover {
      background: rgba(255, 255, 255, .06);
      border-color: var(--border2)
    }

    .option-btn.correct {
      background: rgba(16, 217, 130, .08);
      border-color: var(--green)
    }

    .option-letter {
      width: 40px;
      height: 40px;
      border-radius: 8px;
      background: var(--bg2);
      border: 1px solid var(--border);
      display: grid;
      place-items: center;
      font-family: var(--mono);
      font-weight: 600;
      font-size: .8rem;
      flex-shrink: 0
    }

    .option-btn.correct .option-letter {
      background: var(--green);
      color: #fff;
      border-color: var(--green)
    }

    .option-text {
      flex: 1;
      font-size: .95rem;
      line-height: 1.4;
      color: var(--text)
    }

    .nav-buttons {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem
    }

    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .6);
      display: grid;
      place-items: center;
      z-index: 200;
      backdrop-filter: blur(4px)
    }

    .modal-overlay.hidden {
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

    .modal-sm {
      width: min(90vw, 400px)
    }

    .modal-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1rem 1.25rem;
      border-bottom: 1px solid var(--border)
    }

    .modal-header h3 {
      font-family: var(--display);
      font-size: 1rem;
      font-weight: 700
    }

    .modal-close {
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

    .modal-close:hover {
      border-color: var(--red);
      color: var(--red)
    }

    .modal-body {
      padding: 1.25rem
    }

    .rank-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 20px;
      height: 20px;
      border-radius: 4px;
      font-family: var(--mono);
      font-size: .65rem;
      font-weight: 700;
      margin-left: .5rem
    }

    .rank-a {
      background: rgba(16, 217, 130, .15);
      color: var(--green)
    }

    .rank-b {
      background: rgba(245, 183, 49, .15);
      color: var(--yellow)
    }

    .rank-c {
      background: rgba(255, 140, 66, .15);
      color: var(--orange)
    }

    .rank-d {
      background: rgba(255, 59, 92, .15);
      color: var(--red)
    }

    .fade-in {
      animation: fadeIn .3s ease
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px) }
      to { opacity: 1; transform: none }
    }

    @media (max-width: 768px) {
      .mobile-menu-btn {
        display: flex
      }

      #sidebar {
        position: fixed;
        left: -228px;
        top: 0;
        height: 100vh;
        z-index: 15;
        transition: left .18s
      }

      #sidebar.mobile-open {
        left: 0
      }

      .sidebar-toggle {
        display: none
      }

      .topbar {
        padding-left: 4rem
      }

      .page-header {
        flex-direction: column;
        align-items: flex-start
      }

      .nav-buttons {
        flex-direction: column;
        align-items: stretch
      }

      .progress-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: .5rem
      }
    }
  </style>
</head>
<body>
<div class="bg-grid"></div>
<div id="app">

  <!-- ═══ SIDEBAR ═══ -->
  <aside id="sidebar" class="sidebar">
    <div class="sidebar-logo">
      <div class="shield-icon">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <span class="sidebar-brand">CyberShield</span>
    </div>
    <nav class="sidebar-nav">
      <p class="sidebar-section-label">Main Menu</p>
      <a class="sidebar-item" href="index.php">
        <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.2"/><rect x="14" y="3" width="7" height="7" rx="1.2"/><rect x="3" y="14" width="7" height="7" rx="1.2"/><rect x="14" y="14" width="7" height="7" rx="1.2"/></svg></span>
        <span class="sidebar-label">Dashboard</span>
        <span class="sidebar-tooltip">Dashboard</span>
      </a>
      <a class="sidebar-item active" href="assessment.php">
        <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
        <span class="sidebar-label">Take Assessment</span>
        <span class="sidebar-tooltip">Assessment</span>
      </a>
      <a class="sidebar-item" href="result.php">
        <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>
        <span class="sidebar-label">My Results</span>
        <span class="sidebar-tooltip">Results</span>
      </a>
      <a class="sidebar-item" href="leaderboard.php">
        <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6l4-4 4 4"/><path d="M12 2v13"/><path d="M20 21H4"/><path d="M17 12h3v9"/><path d="M4 12h3v9"/></svg></span>
        <span class="sidebar-label">Leaderboard</span>
        <span class="sidebar-tooltip">Leaderboard</span>
      </a>
      <p class="sidebar-section-label" style="margin-top:1.25rem;">Seller Hub</p>
      <a class="sidebar-item" href="seller-store.php">
        <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg></span>
        <span class="sidebar-label">My Store</span>
        <span class="sidebar-tooltip">My Store</span>
      </a>
      <a class="sidebar-item" href="seller-analytics.php">
        <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><polyline points="2 20 22 20"/></svg></span>
        <span class="sidebar-label">Analytics</span>
        <span class="sidebar-tooltip">Analytics</span>
      </a>
      <p class="sidebar-section-label" style="margin-top:1.25rem;">Account</p>
      <a class="sidebar-item" href="profile.php">
        <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
        <span class="sidebar-label">My Profile</span>
        <span class="sidebar-tooltip">Profile</span>
      </a>
      <a class="sidebar-item" href="security-tips.php">
        <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span>
        <span class="sidebar-label">Security Tips</span>
        <span class="sidebar-tooltip">Tips</span>
      </a>
      <a class="sidebar-item" href="terms.php">
        <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>
        <span class="sidebar-label">Terms &amp; Privacy</span>
        <span class="sidebar-tooltip">Terms</span>
      </a>
    </nav>
    <div class="sidebar-bottom">
      <div class="sidebar-user-card" onclick="window.location.href='profile.php'" title="View profile">
        <div class="sidebar-avatar"><?php echo $initial; ?></div>
        <div class="sidebar-user-info">
          <div class="sidebar-user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
          <div class="sidebar-user-role">Vendor Account</div>
        </div>
        <svg class="sidebar-chevron" width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </div>
      <button class="sidebar-signout-btn" onclick="window.location.href='../logout.php'">
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

  <!-- ═══ MAIN CONTENT ═══ -->
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
          <input type="text" placeholder="Search…" autocomplete="off"/>
        </div>
        <div class="topbar-divider"></div>
        <button class="topbar-ctrl-btn" id="a11y-btn" title="Toggle large text">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="2"/><path d="M9 11h6M12 11v9"/><path d="M7.5 16h2M14.5 16h2"/></svg>
        </button>
        <button class="topbar-ctrl-btn" id="theme-toggle" onclick="toggleTheme()" title="Toggle theme">
          <svg id="theme-icon-moon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
          <svg id="theme-icon-sun" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:none"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>
        </button>
        <div class="topbar-divider"></div>
        <div class="notif-wrap">
          <button class="topbar-ctrl-btn" id="notif-btn" onclick="toggleNotifPanel()" title="Notifications">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="notif-dot hidden" id="notif-dot"></span>
          </button>
          <div class="notif-panel hidden" id="notif-panel">
            <div class="notif-header"><span>Notifications</span><button onclick="document.getElementById('notif-panel').classList.add('hidden')">Clear all</button></div>
            <div id="notif-list"><p class="notif-empty">No notifications</p></div>
          </div>
        </div>
        <div class="topbar-user" onclick="window.location.href='profile.php'" title="My Profile">
          <div class="topbar-avatar"><?php echo $initial; ?></div>
          <div class="topbar-user-info">
            <span class="topbar-user-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
            <span class="topbar-user-role">Vendor</span>
          </div>
          <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6l4 4 4-4"/></svg>
        </div>
      </div>
    </header>

    <!-- ═══ ASSESSMENT CONTENT ═══ -->
    <div class="page">
      <div class="page-inner fade-in">

        <!-- Page header -->
        <div class="page-header">
          <div>
            <h2 class="page-title">Security Assessment</h2>
            <p class="page-subtitle">Evaluate your cybersecurity hygiene across key domains</p>
          </div>
          <a href="index.php" class="btn btn-outline btn-sm">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            Quit
          </a>
        </div>

        <!-- Progress bar -->
        <div class="assess-header">
          <div class="progress-meta">
            <span id="progress-label">Question 1 of <?php echo count($questions); ?></span>
            <div class="progress-meta-right">
              <div class="timer-wrap">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span class="timer-val" id="timer-val">30</span>
              </div>
              <span class="progress-pct" id="progress-pct">0%</span>
            </div>
          </div>
          <div class="progress-bar-wrap">
            <div class="progress-bar-fill" id="progress-fill" style="width:0%"></div>
          </div>
          <div class="timer-bar-wrap">
            <div class="timer-bar-fill" id="timer-bar-fill" style="width:100%"></div>
          </div>
        </div>

        <!-- Question card -->
        <div class="card q-card" id="q-card"></div>

        <!-- Nav buttons -->
        <div class="nav-buttons">
          <button class="btn btn-secondary" id="prev-btn" onclick="prevQuestion()" disabled>← Previous</button>
          <div id="score-preview-inline" style="display:none;font-family:var(--mono);font-size:.82rem;color:var(--text2);align-self:center;"></div>
          <div style="display:flex;gap:.5rem;">
            <button class="btn btn-primary" id="next-btn" onclick="nextQuestion()">Next →</button>
            <button class="btn btn-success" id="submit-btn" onclick="submitAssessment()" style="display:none;">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              Submit Assessment
            </button>
          </div>
        </div>

      </div>
    </div>
  </div><!-- end main-content -->
</div><!-- end app -->

<!-- ═══ CONFIRM SUBMIT MODAL ═══ -->
<div id="modal-overlay" class="modal-overlay hidden" onclick="closeModal(event)">
  <div class="modal modal-sm" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h3>Confirm Submission</h3>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div id="modal-body">
      <p style="margin-bottom:.5rem;">Are you sure you want to submit this assessment?</p>
      <p style="font-size:.83rem;color:var(--muted);">You cannot change your answers after submission.</p>
      <div id="modal-score-preview" style="margin:1rem 0;padding:.85rem 1rem;background:var(--card-bg);border-radius:9px;border:1px solid var(--border2);"></div>
      <div style="display:flex;gap:.5rem;margin-top:1rem;">
        <button class="btn btn-primary" onclick="confirmSubmit()">Yes, Submit</button>
        <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
      </div>
    </div>
  </div>
</div>

<script>
const questions    = <?php echo json_encode($questions); ?>;
let userAnswers    = new Array(questions.length).fill(null);
let currentQ       = 0;
let timerInterval  = null;
let timeLeft       = 30;

/* ── Sidebar ── */
function toggleSidebar() {
  const sb = document.getElementById('sidebar');
  const mc = document.getElementById('main-content');
  const icon = document.getElementById('sidebar-toggle-icon');
  const tog  = document.getElementById('sidebar-toggle');
  sb.classList.toggle('collapsed');
  mc.classList.toggle('sidebar-collapsed');
  const col = sb.classList.contains('collapsed');
  icon.innerHTML = col
    ? '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>'
    : '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>';
  tog.style.left = col ? '66px' : 'var(--sidebar-w)';
}
function toggleMobileSidebar() {
  document.getElementById('sidebar').classList.toggle('mobile-open');
}

/* ── Theme ── */
function toggleTheme() {
  const html = document.documentElement;
  const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  localStorage.setItem('cs-theme', next);
  document.getElementById('theme-icon-moon').style.display = next === 'dark'  ? '' : 'none';
  document.getElementById('theme-icon-sun').style.display  = next === 'light' ? '' : 'none';
}

/* ── Notifications ── */
function toggleNotifPanel() {
  document.getElementById('notif-panel').classList.toggle('hidden');
}

/* ── Modal ── */
function closeModal(event) {
  if (event && event.target !== event.currentTarget) return;
  document.getElementById('modal-overlay').classList.add('hidden');
}

/* ── Timer ── */
function startTimer() {
  clearInterval(timerInterval);
  timeLeft = 30;
  updateTimerDisplay();
  updateTimerBar(100);
  timerInterval = setInterval(() => {
    timeLeft--;
    updateTimerDisplay();
    updateTimerBar((timeLeft / 30) * 100);
    if (timeLeft <= 0) {
      clearInterval(timerInterval);
      // Auto-advance on timeout
      if (currentQ < questions.length - 1) {
        currentQ++;
        renderQuestion(currentQ);
      }
    }
  }, 1000);
}
function updateTimerDisplay() {
  const el  = document.getElementById('timer-val');
  const min = Math.floor(timeLeft / 60);
  const sec = timeLeft % 60;
  el.textContent = `${String(min).padStart(2,'0')}:${String(sec).padStart(2,'0')}`;
  el.className = 'timer-val' + (timeLeft <= 10 ? (timeLeft <= 5 ? ' urgent' : ' warning') : '');
}
function updateTimerBar(pct) {
  const bar = document.getElementById('timer-bar-fill');
  bar.style.width = Math.max(0, pct) + '%';
  bar.className = 'timer-bar-fill' + (pct <= 33 ? (pct <= 16 ? ' urgent' : ' warning') : '');
}

/* ── Render question ── */
function renderQuestion(idx) {
  const q   = questions[idx];
  const sel = userAnswers[idx];

  const catLabels = { password:'PASSWORD', phishing:'PHISHING', device:'DEVICE', network:'NETWORK' };

  const optionsHtml = Object.entries(q.options).map(([text, score]) => `
    <div class="option-btn ${sel === score ? 'correct' : ''}" onclick="selectAnswer(${idx}, ${score})">
      <div class="option-letter">${score}%</div>
      <div class="option-text">${escHtml(text)}</div>
    </div>`).join('');

  document.getElementById('q-card').innerHTML = `
    <div class="q-num">Question ${idx + 1} of ${questions.length}</div>
    <div class="q-category category-${q.category}">${catLabels[q.category] || q.category.toUpperCase()}</div>
    <div class="q-text">${escHtml(q.text)}</div>
    <div class="options-list">${optionsHtml}</div>`;

  // Progress
  const pct = Math.round(((idx + 1) / questions.length) * 100);
  document.getElementById('progress-label').textContent = `Question ${idx + 1} of ${questions.length}`;
  document.getElementById('progress-fill').style.width  = pct + '%';
  document.getElementById('progress-pct').textContent   = pct + '%';

  // Buttons
  document.getElementById('prev-btn').disabled = idx === 0;
  const isLast = idx === questions.length - 1;
  document.getElementById('next-btn').style.display   = isLast ? 'none' : '';
  document.getElementById('submit-btn').style.display = isLast ? '' : 'none';

  // Score preview
  updateScorePreview();

  // Timer — only start if not already answered
  if (sel === null) startTimer();
  else { clearInterval(timerInterval); updateTimerDisplay(); updateTimerBar(100); }
}

function selectAnswer(idx, score) {
  userAnswers[idx] = score;
  clearInterval(timerInterval);
  renderQuestion(idx);
  // Auto-advance after brief pause
  if (idx < questions.length - 1) {
    setTimeout(() => { currentQ++; renderQuestion(currentQ); }, 350);
  }
}

function nextQuestion() {
  if (currentQ < questions.length - 1) { currentQ++; renderQuestion(currentQ); }
}
function prevQuestion() {
  if (currentQ > 0) { currentQ--; renderQuestion(currentQ); }
}

/* ── Scoring ── */
function calcScore() {
  const answered = userAnswers.filter(a => a !== null);
  if (!answered.length) return 0;
  return Math.round(answered.reduce((s, a) => s + a, 0) / answered.length);
}
function calcCatScores() {
  const cats = { password:{t:0,c:0}, phishing:{t:0,c:0}, device:{t:0,c:0}, network:{t:0,c:0} };
  questions.forEach((q, i) => {
    if (userAnswers[i] !== null) { cats[q.category].t += userAnswers[i]; cats[q.category].c++; }
  });
  const r = {};
  for (const k in cats) r[k] = cats[k].c ? Math.round(cats[k].t / cats[k].c) : 0;
  return r;
}
function getRank(score) {
  if (score >= 80) return { letter:'A', text:'Low Risk — Excellent security practices',          color:'var(--green)' };
  if (score >= 60) return { letter:'B', text:'Moderate Risk — Good foundation, room to improve', color:'var(--blue)' };
  if (score >= 40) return { letter:'C', text:'High Risk — Significant improvements needed',       color:'var(--orange)' };
  return              { letter:'D', text:'Critical Risk — Immediate action required',            color:'var(--red)' };
}

function updateScorePreview() {
  const answered = userAnswers.filter(a => a !== null).length;
  const el = document.getElementById('score-preview-inline');
  if (!answered) { el.style.display = 'none'; return; }
  const score = calcScore();
  const rank  = getRank(score);
  el.style.display = '';
  el.innerHTML = `Score so far: <strong style="color:${rank.color}">${score}%</strong> &nbsp;<span class="rank-badge rank-${rank.letter.toLowerCase()}">${rank.letter}</span>`;
}

/* ── Submit ── */
function submitAssessment() {
  const unanswered = userAnswers.filter(a => a === null).length;
  if (unanswered > 0) {
    const first = userAnswers.findIndex(a => a === null);
    currentQ = first;
    renderQuestion(currentQ);
    // Flash the card
    const card = document.getElementById('q-card');
    card.style.outline = '2px solid var(--red)';
    setTimeout(() => card.style.outline = '', 1200);
    return;
  }
  const score  = calcScore();
  const rank   = getRank(score);
  const cats   = calcCatScores();
  document.getElementById('modal-score-preview').innerHTML = `
    <div style="display:flex;align-items:center;gap:1rem;">
      <div style="font-family:var(--display);font-size:2.5rem;color:${rank.color};line-height:1;">${rank.letter}</div>
      <div>
        <div style="font-size:1.3rem;font-weight:700;font-family:var(--mono);">${score}%</div>
        <div style="font-size:.78rem;color:var(--text2);">${rank.text}</div>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-top:.85rem;">
      ${['password','phishing','device','network'].map(c => `
        <div style="background:var(--card);padding:.5rem .75rem;border-radius:7px;border:1px solid var(--border);">
          <div style="font-family:var(--mono);font-size:.6rem;letter-spacing:1px;color:var(--muted);text-transform:uppercase;">${c}</div>
          <div style="font-family:var(--mono);font-size:1rem;font-weight:700;">${cats[c]}%</div>
        </div>`).join('')}
    </div>`;
  document.getElementById('modal-overlay').classList.remove('hidden');
}

async function confirmSubmit() {
  closeModal();
  const score = calcScore();
  const rank  = getRank(score);
  const cats  = calcCatScores();

  const payload = {
    vendor_id: 0,
    score,
    rank: rank.letter,
    password_score: cats.password,
    phishing_score: cats.phishing,
    device_score:   cats.device,
    network_score:  cats.network,
    assessment_notes: `Completed on ${new Date().toLocaleString()}`
  };

  // Show saving state
  document.getElementById('submit-btn').textContent = 'Saving…';
  document.getElementById('submit-btn').disabled = true;

  try {
    const res  = await fetch('index.php?action=save_assessment', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      credentials: 'same-origin'
    });
    const data = await res.json();
    if (data.success) {
      localStorage.setItem('lastAssessment', JSON.stringify({ score, rank: rank.letter, categoryScores: cats, date: new Date().toISOString() }));
      window.location.href = 'result.php';
    } else {
      alert('Error saving: ' + (data.error || 'Unknown error'));
      document.getElementById('submit-btn').textContent = 'Submit Assessment';
      document.getElementById('submit-btn').disabled = false;
    }
  } catch(e) {
    alert('Connection error. Please try again.');
    document.getElementById('submit-btn').textContent = 'Submit Assessment';
    document.getElementById('submit-btn').disabled = false;
  }
}

function escHtml(str) {
  return (str || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

/* ── Init ── */
document.addEventListener('DOMContentLoaded', () => {
  // Restore theme
  const t = localStorage.getItem('cs-theme') || 'dark';
  document.documentElement.setAttribute('data-theme', t);
  document.getElementById('theme-icon-moon').style.display = t === 'dark'  ? '' : 'none';
  document.getElementById('theme-icon-sun').style.display  = t === 'light' ? '' : 'none';

  // Close notif on outside click
  document.addEventListener('click', e => {
    const p = document.getElementById('notif-panel');
    const b = document.getElementById('notif-btn');
    if (p && !p.classList.contains('hidden') && !p.contains(e.target) && b && !b.contains(e.target))
      p.classList.add('hidden');
  });

  // Start assessment
  renderQuestion(0);
});
</script>
</body>
</html>