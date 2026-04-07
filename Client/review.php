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
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>Video Reviews — CyberShield</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0 }

    html, body { height: 100%; overflow: hidden }

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
      background-image: linear-gradient(rgba(59, 139, 255, .025) 1px, transparent 1px),
                        linear-gradient(90deg, rgba(59, 139, 255, .025) 1px, transparent 1px);
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

    #sidebar.collapsed { width: 58px; min-width: 58px }

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

    .sb-brand-text { flex: 1; overflow: hidden; white-space: nowrap }

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
      z-index: 100
    }

    .sb-toggle:hover {
      background: rgba(59, 139, 255, 0.2);
      border-color: var(--blue);
      color: var(--text);
      transform: scale(1.05)
    }

    #sidebar.collapsed .sb-toggle svg { transform: rotate(180deg) }

    .sb-section {
      flex: 1;
      overflow-y: auto;
      overflow-x: hidden;
      padding: .65rem 0
    }

    .sb-section::-webkit-scrollbar { width: 3px }
    .sb-section::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 2px }

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

    #sidebar.collapsed .sb-label { opacity: 0 }

    .sb-divider { height: 1px; background: var(--border); margin: .5rem .9rem }

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

    .sb-item:hover { background: rgba(59, 139, 255, .07); color: var(--text) }

    .sb-item.active { background: rgba(59, 139, 255, .1); color: var(--blue) }

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

    .sb-icon { display: flex; align-items: center; justify-content: center; width: 18px; flex-shrink: 0 }
    .sb-text { overflow: hidden }
    #sidebar.collapsed .sb-text { display: none }

    .sb-footer { border-top: 1px solid var(--border); padding: .75rem .9rem; flex-shrink: 0 }

    .sb-user { display: flex; align-items: center; gap: .65rem; overflow: hidden }

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

    .sb-user-info { overflow: hidden; white-space: nowrap }
    .sb-user-info p { font-size: .82rem; font-weight: 600 }
    .sb-user-info span { font-size: .68rem; color: var(--muted2) }
    #sidebar.collapsed .sb-user-info { display: none }

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

    .btn-sb-logout:hover { background: rgba(255, 59, 92, .15) }
    #sidebar.collapsed .btn-sb-logout span { display: none }

    #main { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0 }

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

    .tb-bc { display: flex; align-items: center; gap: .4rem }
    .tb-app { font-family: var(--mono); font-size: .68rem; color: var(--muted); letter-spacing: .5px }
    .tb-title { font-family: var(--display); font-size: 1.05rem; letter-spacing: 1px }
    .tb-sub { font-family: var(--mono); font-size: .63rem; letter-spacing: .5px; color: var(--muted); margin-top: 1px }
    .tb-right { display: flex; align-items: center; gap: .55rem }

    .tb-search-wrap { position: relative }

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

    .tb-search:focus { border-color: rgba(59, 139, 255, .4) }
    .tb-search::placeholder { color: var(--muted) }

    .tb-date { font-family: var(--mono); font-size: .65rem; color: var(--muted2); white-space: nowrap }
    .tb-divider { width: 1px; height: 20px; background: var(--border2); margin: 0 .2rem }

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

    .tb-icon-btn:hover { border-color: var(--blue); color: var(--text) }

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

    .tb-admin:hover { border-color: rgba(59, 139, 255, .28); background: rgba(59, 139, 255, .06) }

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

    .tb-admin-info { display: flex; flex-direction: column }
    .tb-admin-name { font-size: .78rem; font-weight: 600; line-height: 1.2 }
    .tb-admin-role { font-size: .6rem; color: var(--blue); letter-spacing: .5px; font-family: var(--mono) }

    .content { flex: 1; overflow-y: auto; padding: 1.5rem }
    .content::-webkit-scrollbar { width: 4px }
    .content::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 2px }

    .hero-section {
      background: linear-gradient(135deg, rgba(59,139,255,.08), rgba(123,114,240,.05));
      border-radius: 1.5rem;
      padding: 1.8rem 2rem;
      margin-bottom: 1.8rem;
      border: 1px solid var(--border);
    }

    .hero-title {
      font-family: var(--display);
      font-size: 2rem;
      font-weight: 800;
      background: linear-gradient(135deg, var(--blue), var(--purple));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      margin-bottom: .4rem;
      letter-spacing: .5px;
    }

    .stats-row {
      display: flex;
      gap: 1rem;
      margin-top: 1.2rem;
      flex-wrap: wrap;
    }

    .stat-badge {
      background: var(--card-bg);
      border-radius: .75rem;
      padding: .6rem 1rem;
      border: 1px solid var(--border);
      display: flex;
      align-items: center;
      gap: .6rem;
      font-size: .8rem;
      color: var(--muted2);
    }

    .filter-row { display: flex; gap: .65rem; flex-wrap: wrap; margin-bottom: 1.4rem; }

    .filter-pill {
      background: var(--bg2);
      border: 1px solid var(--border2);
      border-radius: 40px;
      padding: .42rem 1rem;
      font-size: .78rem;
      font-weight: 600;
      cursor: pointer;
      transition: var(--t);
      color: var(--muted2);
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      font-family: var(--font);
    }

    .filter-pill.active { background: var(--blue); color: white; border-color: var(--blue); box-shadow: 0 4px 12px rgba(59,139,255,0.3); }
    .filter-pill:hover { border-color: var(--blue); color: var(--blue); }

    .section-title {
      font-family: var(--display);
      font-size: 1.1rem;
      font-weight: 700;
      margin: 1.6rem 0 1rem;
      display: flex;
      align-items: center;
      gap: .6rem;
      border-left: 3px solid var(--blue);
      padding-left: .85rem;
      letter-spacing: .5px;
    }

    .video-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 1.4rem;
      margin-bottom: 1.8rem;
    }

    .video-card {
      background: var(--card-bg);
      border-radius: 1rem;
      border: 1px solid var(--border);
      overflow: hidden;
      transition: all .25s ease;
      cursor: pointer;
      box-shadow: var(--shadow);
    }

    .video-card:hover {
      transform: translateY(-5px);
      border-color: var(--blue);
      box-shadow: 0 16px 28px -10px rgba(59,139,255,.22);
    }

    .video-thumb {
      position: relative;
      background: #0c1222;
      height: 180px;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .video-thumb img.yt-thumb {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform .3s ease;
    }

    .video-card:hover .yt-thumb { transform: scale(1.05); }

    .thumb-fallback-icon {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(145deg, #0c1222, #03050b);
    }

    .play-overlay {
      position: absolute;
      width: 50px;
      height: 50px;
      background: rgba(0,0,0,0.65);
      backdrop-filter: blur(8px);
      border-radius: 50px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 1.2rem;
      border: 1px solid rgba(255,255,255,0.25);
      transition: .2s;
      z-index: 3;
    }

    .video-card:hover .play-overlay { background: var(--blue); transform: scale(1.08); }

    .video-info { padding: 1.1rem; }

    .video-info h3 { 
      font-size: 1rem; 
      font-weight: 700; 
      margin-bottom: .4rem;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .video-info p { 
      font-size: .78rem; 
      color: var(--muted2); 
      margin-bottom: .75rem; 
      line-height: 1.5;
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .tag {
      display: inline-block;
      padding: .18rem .55rem;
      border-radius: 30px;
      font-size: .62rem;
      font-weight: 700;
      margin-right: .4rem;
      font-family: var(--mono);
      letter-spacing: .5px;
    }

    .tag-threat { background: rgba(255,59,92,.15); color: var(--red); }
    .tag-risk   { background: rgba(245,183,49,.15); color: var(--yellow); }
    .tag-defense{ background: rgba(16,217,130,.15); color: var(--green); }

    .glossary-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
      gap: 1rem;
      margin-bottom: 2rem;
    }

    .tip-card {
      background: var(--card-bg);
      border-radius: .9rem;
      padding: 1rem 1.1rem;
      border: 1px solid var(--border);
      border-left: 3px solid var(--orange);
      transition: .2s;
      display: flex;
      flex-direction: column;
      gap: .4rem;
    }

    .tip-card:hover { transform: translateX(4px); border-left-color: var(--blue); }
    .tip-card strong { font-size: .9rem; }
    .tip-card p { font-size: .78rem; color: var(--muted2); line-height: 1.5; margin: 0; }

    #toast-c { position: fixed; bottom: 1.25rem; right: 1.25rem; display: flex; flex-direction: column; gap: .5rem; z-index: 300 }

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
      min-width: 240px;
    }

    @keyframes sl { from { opacity: 0; transform: translateX(20px) } to { opacity: 1; transform: none } }

    .ti { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

    @media (max-width: 768px) {
      .content { padding: 1rem; }
      .hero-title { font-size: 1.4rem; }
      .video-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>

<body>
  <div class="bg-grid"></div>
  <div id="app">

    <aside id="sidebar">
      <div class="sb-brand">
        <div class="shield">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white"
            stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
          </svg>
        </div>
        <div class="sb-brand-text">
          <h2>CyberShield</h2><span class="badge">Client Portal</span>
        </div>
        <button class="sb-toggle" onclick="toggleSidebar()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6" />
          </svg>
        </button>
      </div>

      <div class="sb-section">
        <div class="sb-label">Navigation</div>

        <a class="sb-item" href="index.php">
          <span class="sb-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="7" height="7" rx="1.2" />
              <rect x="14" y="3" width="7" height="7" rx="1.2" />
              <rect x="3" y="14" width="7" height="7" rx="1.2" />
              <rect x="14" y="14" width="7" height="7" rx="1.2" />
            </svg>
          </span><span class="sb-text">Dashboard</span>
        </a>

        <a class="sb-item" href="assessment.php">
          <span class="sb-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 11l3 3L22 4" />
              <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
            </svg>
          </span><span class="sb-text">Take Assessment</span>
        </a>

        <a class="sb-item" href="result.php">
          <span class="sb-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="20" x2="18" y2="10" />
              <line x1="12" y1="20" x2="12" y2="4" />
              <line x1="6" y1="20" x2="6" y2="14" />
            </svg>
          </span><span class="sb-text">Results</span>
        </a>

        <a class="sb-item active" href="review.php">
          <span class="sb-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <path d="M8 6l4-4 4 4" />
              <path d="M12 2v13" />
              <path d="M20 21H4" />
              <path d="M17 12h3v9" />
              <path d="M4 12h3v9" />
            </svg>
          </span><span class="sb-text">Review</span>
        </a>

        <a class="sb-item" href="certificates.php">
          <span class="sb-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="8" r="6" />
              <path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11" />
            </svg>
          </span><span class="sb-text">Certificates</span>
        </a>

        <div class="sb-divider"></div>
        <div class="sb-label">Account</div>

        <a class="sb-item" href="profile.php">
          <span class="sb-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
              <circle cx="12" cy="7" r="4" />
            </svg>
          </span><span class="sb-text">Profile</span>
        </a>

        <a class="sb-item" href="security-tips.php">
          <span class="sb-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
          </span><span class="sb-text">Security Tips</span>
        </a>

        <a class="sb-item" href="terms.php">
          <span class="sb-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <polyline points="14 2 14 8 20 8" />
              <line x1="16" y1="13" x2="8" y2="13" />
              <line x1="16" y1="17" x2="8" y2="17" />
            </svg>
          </span><span class="sb-text">Terms & Privacy</span>
        </a>
      </div>

      <div class="sb-footer">
        <div class="sb-user">
          <div class="sb-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
          <div class="sb-user-info">
            <p><?php echo htmlspecialchars($user['full_name']); ?></p>
            <span>Vendor Account</span>
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
            <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
              <path d="M6 4l4 4-4 4" />
            </svg>
            <span class="tb-title">Video Reviews</span>
          </div>
          <p class="tb-sub">Cybersecurity threat analysis & mastery sessions</p>
        </div>
        <div class="tb-right">
          <div class="tb-search-wrap">
            <span class="tb-search-icon">
              <svg width="12" height="12" viewBox="0 0 20 20" fill="none">
                <circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.7" />
                <path d="M15 15l3 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
              </svg>
            </span>
            <input type="text" class="tb-search" placeholder="Search videos…" id="searchInput" autocomplete="off" />
          </div>
          <span class="tb-date" id="tb-date"></span>
          <div class="tb-divider"></div>
          <button class="tb-icon-btn" onclick="toggleTheme()" title="Toggle theme">
            <svg id="tmoon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
            </svg>
            <svg id="tsun" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:none">
              <circle cx="12" cy="12" r="5" />
              <line x1="12" y1="1" x2="12" y2="3" />
              <line x1="12" y1="21" x2="12" y2="23" />
              <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
              <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
              <line x1="1" y1="12" x2="3" y2="12" />
              <line x1="21" y1="12" x2="23" y2="12" />
            </svg>
          </button>
          <div class="tb-divider"></div>
          <a class="tb-admin" href="#">
            <div class="tb-admin-av"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
            <div class="tb-admin-info">
              <span class="tb-admin-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
              <span class="tb-admin-role">Vendor</span>
            </div>
            <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" style="color:var(--muted);margin-left:.2rem">
              <path d="M4 6l4 4 4-4" />
            </svg>
          </a>
        </div>
      </div>

      <div class="content">

        <div class="hero-section">
          <h1 class="hero-title">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="url(#hg)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:.4rem">
              <defs><linearGradient id="hg" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#3B8BFF"/><stop offset="100%" stop-color="#7B72F0"/></linearGradient></defs>
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            Cybersecurity Video Review Hub
          </h1>
          <p style="font-size:.9rem;color:var(--muted2);margin-top:.35rem;line-height:1.6">
            Understand modern cyber threats, attack vectors, and risk mitigation strategies through curated expert video sessions.
          </p>
          <div class="stats-row">
            <div class="stat-badge">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
              <strong id="videoCountStat">21 expert sessions</strong>
            </div>
            <div class="stat-badge">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
              <strong>Threats · Risk · Defense</strong>
            </div>
            <div class="stat-badge">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--yellow)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"/><path d="M8.21 13.89L7 23l5-3 5 3-1.21-9.12"/></svg>
              <strong>CEU &amp; Awareness Credits</strong>
            </div>
          </div>
        </div>

        <div class="filter-row">
          <button class="filter-pill active" data-cat="all" onclick="filterVideos('all',this)">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/></svg>
            All Videos
          </button>
          <button class="filter-pill" data-cat="threat" onclick="filterVideos('threat',this)">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Threats &amp; Attacks
          </button>
          <button class="filter-pill" data-cat="risk" onclick="filterVideos('risk',this)">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            Risk Management
          </button>
          <button class="filter-pill" data-cat="defense" onclick="filterVideos('defense',this)">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Defense &amp; Best Practices
          </button>
        </div>

        <div class="section-title">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="15" rx="2"/><polyline points="17 2 12 7 7 2"/></svg>
          Cybersecurity Deep Dives
        </div>
        <div id="videoGrid" class="video-grid"></div>

        <div class="section-title">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"/><path d="M8.21 13.89L7 23l5-3 5 3-1.21-9.12"/></svg>
          Cyber Threat Glossary — Quick Review
        </div>
        <div class="glossary-grid">
          <div class="tip-card">
            <div style="display:flex;align-items:center;gap:.5rem">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
              <strong>Ransomware</strong>
            </div>
            <p>Malware that encrypts data and demands payment. Prevent with backups, email filtering &amp; endpoint protection.</p>
          </div>
          <div class="tip-card">
            <div style="display:flex;align-items:center;gap:.5rem">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              <strong>Phishing</strong>
            </div>
            <p>Deceptive messages designed to steal credentials. Defend with MFA, awareness training &amp; email filtering.</p>
          </div>
          <div class="tip-card">
            <div style="display:flex;align-items:center;gap:.5rem">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
              <strong>MITM Attack</strong>
            </div>
            <p>Eavesdropping on network communications. Enforce HTTPS, HSTS &amp; corporate VPN policies.</p>
          </div>
          <div class="tip-card">
            <div style="display:flex;align-items:center;gap:.5rem">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--purple)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
              <strong>SQL Injection</strong>
            </div>
            <p>Input validation failure exposing databases. Use parameterized queries, ORMs &amp; a WAF.</p>
          </div>
          <div class="tip-card">
            <div style="display:flex;align-items:center;gap:.5rem">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--teal)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
              <strong>IoT Botnets</strong>
            </div>
            <p>Compromised smart devices weaponized for DDoS. Change default credentials &amp; segment IoT networks.</p>
          </div>
          <div class="tip-card">
            <div style="display:flex;align-items:center;gap:.5rem">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--yellow)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <strong>Social Engineering</strong>
            </div>
            <p>Psychological manipulation of employees. Enforce identity verification &amp; a zero-trust mindset.</p>
          </div>
        </div>

      </div>
    </div>
  </div>

  <div id="toast-c"></div>

  <script>
    // -------- COMPLETE VIDEO LIBRARY WITH YOUTUBE IDs --------
    // All videos open directly on YouTube - guaranteed to work!
    const videoLibrary = [
      { id:1,  title:"Ransomware Attack Simulation & Defense",         category:"threat",  description:"How ransomware spreads, real-world case study, and proactive defense strategies including offline backups.",            videoId:"n8mbzU0X2nQ", icon:"shield-virus" },
      { id:2,  title:"Phishing Attacks Explained – IBM Technology",     category:"threat",  description:"Deepfake voice phishing, QR code scams, and advanced social engineering tactics to watch out for.",                   videoId:"XBkzBrXlle0", icon:"envelope-open-text" },
      { id:3,  title:"Zero-Day Exploits – Computerphile",              category:"threat",  description:"Understanding unknown vulnerabilities, responsible disclosure timelines, and effective patch management.",          videoId:"oHf1vD5_b5I", icon:"bomb" },
      { id:4,  title:"Cybersecurity Risk Assessment Framework",        category:"risk",    description:"NIST & ISO 27001 risk analysis, threat scoring methodologies, and business impact analysis.",                         videoId:"tYJjVtABn0o", icon:"chart-pie" },
      { id:5,  title:"Threat Hunting: Proactive Defense",             category:"defense", description:"How blue teams detect advanced persistent threats (APT) using SIEM, EDR, and behavioral analytics.",  videoId:"eCE7Z4-f1WY", icon:"eye" },
      { id:6,  title:"Cloud Security & Misconfiguration Risks",       category:"risk",    description:"Data breaches via S3 buckets, IAM misconfigurations, and the shared responsibility model explained.",                videoId:"MmUcj6EImpg", icon:"cloud" },
      { id:7,  title:"Social Engineering: Human Hacking – IBM",       category:"threat",  description:"Psychological tricks, pretexting, tailgating, and building insider-threat awareness programs.",                   videoId:"lc7scxvKQOo", icon:"user-secret" },
      { id:8,  title:"Incident Response Playbook – IBM Technology",   category:"defense", description:"Step-by-step containment, eradication, recovery, and post-mortem analysis for security incidents.",   videoId:"5c4-Mi9pwZo", icon:"toolbox" },
      { id:9,  title:"How DDoS Attacks Work – Computerphile",         category:"threat",  description:"Botnet anatomy, securing smart devices at scale, and effective network segmentation techniques.",                 videoId:"BcDZS7iYNsA", icon:"microchip" },
      { id:10, title:"SQL Injection – Computerphile",                  category:"defense", description:"SQLi, XSS, and developer best practices for input validation, output encoding, and prepared statements.",    videoId:"ciNHn38EyRc", icon:"code" },
      { id:11, title:"SolarWinds Hack Explained",                      category:"risk",    description:"Third-party risk management, software supply chain integrity, and vendor security assessment importance.",           videoId:"pPPiaGU12Og", icon:"link" },
      { id:12, title:"Zero Trust Security – IBM Technology",           category:"defense", description:"Beyond perimeter security — micro-segmentation, identity verification, and least-privilege access control.", videoId:"yn6CPQ9RioA", icon:"shield-halved" },
      { id:13, title:"How to Create a Strong Password – Google",      category:"defense", description:"Google's best practices for creating and managing strong, secure passwords.",                              videoId:"YQSDOBkONK4", icon:"shield-halved" },
      { id:14, title:"Password Managers – Computerphile",              category:"defense", description:"How password managers protect your accounts and simplify your digital life.",                             videoId:"w68BBPDAWr8", icon:"shield-halved" },
      { id:15, title:"Multi-Factor Authentication (MFA) Explained",   category:"defense", description:"Why MFA is essential and how to set it up on your accounts.",                                        videoId:"ZXFYT-BG2So", icon:"shield-halved" },
      { id:16, title:"What is Malware? – IBM Technology",             category:"threat",  description:"Protect devices from viruses, ransomware, and spyware — types and defenses explained.",                         videoId:"n8mbzU0X2nQ", icon:"microchip" },
      { id:17, title:"Hacking – The Art of Exploitation (TEDx)",      category:"threat",  description:"How hackers think and attack systems — a former hacker explains.",                                           videoId:"AuYNXgO_f3Y", icon:"user-secret" },
      { id:18, title:"Cybersecurity Fundamentals – CrashCourse",      category:"defense", description:"Comprehensive overview of device and system security basics.",                                       videoId:"bPVaOlJ6ln0", icon:"eye" },
      { id:19, title:"Data Privacy Explained – Computerphile",        category:"risk",    description:"Essential practices for handling and protecting sensitive personal data online.",                            videoId:"hhUb5iknVJs", icon:"chart-pie" },
      { id:20, title:"What is Phishing? – IBM Technology",            category:"threat",  description:"Identify phishing emails, text messages, and social media scams.",                                           videoId:"XBkzBrXlle0", icon:"envelope-open-text" },
      { id:21, title:"Wi-Fi Security – Protect Your Home Network",    category:"defense", description:"Secure your wireless network and prevent unauthorized access.",                                        videoId:"bPVaOlJ6ln0", icon:"shield-halved" },
    ];

    const catMeta = {
      threat:  { label:'⚠️ Threat',   cls:'tag-threat'  },
      risk:    { label:'📊 Risk',     cls:'tag-risk'    },
      defense: { label:'🛡️ Defense', cls:'tag-defense' },
    };

    let activeFilter = 'all';

    function esc(s) {
      if (!s) return '';
      return s.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }

    function renderVideos() {
      const grid = document.getElementById('videoGrid');
      if (!grid) return;
      const term = document.getElementById('searchInput')?.value.toLowerCase() || '';
      let list = videoLibrary.filter(v =>
        (activeFilter === 'all' || v.category === activeFilter) &&
        (!term || v.title.toLowerCase().includes(term) || v.description.toLowerCase().includes(term))
      );

      if (!list.length) {
        grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:3rem;background:var(--card-bg);border-radius:1.2rem;border:1px solid var(--border)">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="1.5" stroke-linecap="round" style="margin-bottom:.75rem"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
          <p style="color:var(--muted2)">No videos match your search. Try another keyword.</p>
        </div>`;
        return;
      }

      const iconSvgs = {
        'shield-virus':   `<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><circle cx="12" cy="11" r="3"/>`,
        'envelope-open-text': `<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>`,
        'bomb':           `<circle cx="11" cy="13" r="9"/><path d="M14.35 4.65L16 3l2 2-1.35 1.35"/><line x1="18" y1="3" x2="20" y2="5"/>`,
        'chart-pie':      `<path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>`,
        'eye':            `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`,
        'cloud':          `<path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/>`,
        'user-secret':    `<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>`,
        'toolbox':        `<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="12.01"/>`,
        'microchip':      `<rect x="9" y="9" width="6" height="6"/><path d="M9 1v3M15 1v3M9 20v3M15 20v3M1 9h3M1 15h3M20 9h3M20 15h3"/>`,
        'code':           `<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>`,
        'link':           `<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>`,
        'shield-halved':  `<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>`,
      };

      const colors = { threat: 'var(--red)', risk: 'var(--yellow)', defense: 'var(--teal)' };

      grid.innerHTML = list.map(v => {
        const cat = catMeta[v.category];
        const svgPaths = iconSvgs[v.icon] || iconSvgs['shield-halved'];
        const thumbSrc = `https://img.youtube.com/vi/${v.videoId}/mqdefault.jpg`;
        const watchUrl = `https://www.youtube.com/watch?v=${v.videoId}`;

        return `
        <div class="video-card" onclick="openVideo('${watchUrl}')">
          <div class="video-thumb">
            <img class="yt-thumb" src="${thumbSrc}" alt="${esc(v.title)}" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
            <div class="thumb-fallback-icon" style="display:none">
              <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="${colors[v.category]}" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" opacity=".45">${svgPaths}</svg>
            </div>
            <div class="play-overlay">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="white" stroke="none"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            </div>
          </div>
          <div class="video-info">
            <h3>${esc(v.title)}</h3>
            <p>${esc(v.description)}</p>
            <div class="video-card-footer">
              <span class="tag ${cat.cls}">${cat.label}</span>
              <a class="yt-link-btn" href="${watchUrl}" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation()">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M23 7s-.3-2-1.2-2.8c-1.1-1.2-2.4-1.2-3-1.3C16.1 2.7 12 2.7 12 2.7s-4.1 0-6.8.2c-.6.1-1.9.1-3 1.3C1.3 5 1 7 1 7S.7 9.1.7 11.2v2c0 2 .3 4.1.3 4.1s.3 2 1.2 2.8c1.1 1.2 2.6 1.1 3.3 1.2C7.5 21.5 12 21.5 12 21.5s4.1 0 6.8-.2c.6-.1 1.9-.1 3-1.3.9-.8 1.2-2.8 1.2-2.8s.3-2.1.3-4.1v-2C23.3 9.1 23 7 23 7zM9.7 15.5V8.2l8.1 3.7-8.1 3.6z"/></svg>
                Watch on YouTube
              </a>
            </div>
          </div>
        </div>`;
      }).join('');
    }

    function filterVideos(cat, btn) {
      activeFilter = cat;
      document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
      if (btn) btn.classList.add('active');
      renderVideos();
    }

    // Direct YouTube open - guaranteed to work every time!
    function openVideo(url) {
      window.open(url, '_blank');
      showToast('Opening video on YouTube...', 'red');
    }

    function showToast(msg, color = 'blue') {
      const cols = { blue: 'var(--blue)', green: 'var(--green)', red: 'var(--red)', yellow: 'var(--yellow)' };
      const t = document.createElement('div');
      t.className = 'toast';
      t.innerHTML = `<span class="ti" style="background:${cols[color]||cols.blue}"></span><span>${msg}</span>`;
      document.getElementById('toast-c').appendChild(t);
      setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; setTimeout(() => t.remove(), 300); }, 2500);
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
      document.getElementById('tmoon').style.display = d ? '' : 'none';
      document.getElementById('tsun').style.display  = d ? 'none' : '';
    }

    function doLogout() {
      if (confirm('Are you sure you want to sign out from CyberShield?')) {
        window.location.href = '../landingpage.php';
      }
    }

    document.addEventListener('DOMContentLoaded', () => {
      const th = localStorage.getItem('cs_th') || 'dark';
      document.documentElement.setAttribute('data-theme', th);
      document.getElementById('tmoon').style.display = th === 'dark' ? '' : 'none';
      document.getElementById('tsun').style.display  = th === 'dark' ? 'none' : '';

      if (localStorage.getItem('cs_sb') === '1') document.getElementById('sidebar').classList.add('collapsed');

      const d = document.getElementById('tb-date');
      if (d) d.textContent = new Date().toLocaleDateString('en-US', { weekday:'short', month:'short', day:'numeric', year:'numeric' });

      const cnt = document.getElementById('videoCountStat');
      if (cnt) cnt.textContent = videoLibrary.length + ' expert sessions';
      renderVideos();

      document.getElementById('searchInput').addEventListener('input', renderVideos);
    });
  </script>
</body>
</html>