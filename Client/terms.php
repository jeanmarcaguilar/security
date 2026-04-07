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
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Terms & Privacy — CyberShield</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Syne:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap');
:root{--font:'Inter',sans-serif;--display:'Syne',sans-serif;--mono:'JetBrains Mono',monospace;--blue:#3B8BFF;--purple:#7B72F0;--teal:#00D4AA;--green:#10D982;--yellow:#F5B731;--orange:#FF8C42;--red:#FF3B5C;--t:.18s ease}
[data-theme=dark]{--bg:#030508;--bg2:#080d16;--bg3:#0d1421;--border:rgba(59,139,255,.08);--border2:rgba(255,255,255,.07);--text:#dde4f0;--muted:#4a6080;--muted2:#8898b4;--card-bg:#0a1020;--shadow:0 4px 24px rgba(0,0,0,.5)}
[data-theme=light]{--bg:#f0f4f8;--bg2:#e8eef5;--bg3:#fff;--border:rgba(59,139,255,.12);--border2:rgba(0,0,0,.1);--text:#0f172a;--muted:#94a3b8;--muted2:#475569;--card-bg:#fff;--shadow:0 4px 24px rgba(0,0,0,.1)}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;overflow:hidden}
body{font-family:var(--font);background:var(--bg);color:var(--text);transition:background .18s,color .18s}
.bg-grid{position:fixed;inset:0;pointer-events:none;z-index:0;background-image:linear-gradient(rgba(59,139,255,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(59,139,255,.025) 1px,transparent 1px);background-size:40px 40px}
#app{display:flex;height:100vh;position:relative;z-index:1}

/* ── Sidebar ── */
#sidebar{width:228px;min-width:228px;background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;transition:width .18s,min-width .18s;overflow:hidden;z-index:10;flex-shrink:0}
#sidebar.collapsed{width:58px;min-width:58px}
.sb-brand{display:flex;align-items:center;gap:.75rem;padding:1rem .9rem .9rem;border-bottom:1px solid var(--border);flex-shrink:0}
.shield{width:34px;height:34px;background:linear-gradient(135deg,var(--blue),var(--purple));border-radius:9px;display:grid;place-items:center;flex-shrink:0;box-shadow:0 0 16px rgba(59,139,255,.3)}
.sb-brand-text{flex:1;overflow:hidden;white-space:nowrap}
.sb-brand-text h2{font-family:var(--display);font-size:.95rem;font-weight:700;letter-spacing:1px}
.sb-brand-text .badge{font-family:var(--mono);font-size:.55rem;letter-spacing:1.5px;text-transform:uppercase;background:rgba(59,139,255,.12);color:var(--blue);border:1px solid rgba(59,139,255,.2);border-radius:4px;padding:.08rem .38rem;display:inline-block;margin-top:.1rem}
.sb-toggle{width:22px;height:22px;background:none;border:1px solid var(--border2);border-radius:5px;cursor:pointer;color:var(--muted2);display:grid;place-items:center;flex-shrink:0;transition:var(--t)}
.sb-toggle:hover{border-color:var(--blue);color:var(--text)}
#sidebar.collapsed .sb-toggle svg{transform:rotate(180deg)}
.sb-section{flex:1;overflow-y:auto;overflow-x:hidden;padding:.65rem 0}
.sb-section::-webkit-scrollbar{width:3px}.sb-section::-webkit-scrollbar-thumb{background:var(--border2);border-radius:2px}
.sb-label{font-family:var(--mono);font-size:.55rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);padding:.5rem .9rem .25rem;white-space:nowrap;overflow:hidden}
#sidebar.collapsed .sb-label{opacity:0}
.sb-divider{height:1px;background:var(--border);margin:.5rem .9rem}
.sb-item{display:flex;align-items:center;gap:.65rem;padding:.52rem .9rem;cursor:pointer;color:var(--muted2);font-size:.82rem;font-weight:500;text-decoration:none;transition:var(--t);white-space:nowrap;overflow:hidden;position:relative}
.sb-item:hover{background:rgba(59,139,255,.07);color:var(--text)}
.sb-item.active{background:rgba(59,139,255,.1);color:var(--blue)}
.sb-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--blue);border-radius:0 3px 3px 0}
.sb-icon{display:flex;align-items:center;justify-content:center;width:18px;flex-shrink:0}
.sb-text{overflow:hidden}
#sidebar.collapsed .sb-text{display:none}
.sb-footer{border-top:1px solid var(--border);padding:.75rem .9rem;flex-shrink:0}
.sb-user{display:flex;align-items:center;gap:.65rem;overflow:hidden}
.sb-avatar{width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,var(--blue),var(--purple));color:#fff;display:grid;place-items:center;font-size:.75rem;font-weight:700;flex-shrink:0;font-family:var(--display)}
.sb-user-info{overflow:hidden;white-space:nowrap}
.sb-user-info p{font-size:.82rem;font-weight:600}
.sb-user-info span{font-size:.68rem;color:var(--muted2)}
#sidebar.collapsed .sb-user-info{display:none}
.btn-sb-logout{display:flex;align-items:center;gap:.35rem;margin-top:.65rem;width:100%;background:rgba(255,59,92,.08);border:1px solid rgba(255,59,92,.18);color:var(--red);font-family:var(--font);font-size:.75rem;font-weight:600;border-radius:7px;padding:.42rem .8rem;cursor:pointer;transition:var(--t)}
.btn-sb-logout:hover{background:rgba(255,59,92,.15)}
#sidebar.collapsed .btn-sb-logout span{display:none}

/* ── Main ── */
#main{flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0}
.topbar{height:54px;min-height:54px;display:flex;align-items:center;justify-content:space-between;padding:0 1.5rem;background:var(--bg2);border-bottom:1px solid var(--border);gap:1rem;flex-shrink:0}
.tb-bc{display:flex;align-items:center;gap:.4rem}
.tb-app{font-family:var(--mono);font-size:.68rem;color:var(--muted);letter-spacing:.5px}
.tb-title{font-family:var(--display);font-size:1.05rem;letter-spacing:1px}
.tb-sub{font-family:var(--mono);font-size:.63rem;letter-spacing:.5px;color:var(--muted);margin-top:1px}
.tb-right{display:flex;align-items:center;gap:.55rem}
.tb-date{font-family:var(--mono);font-size:.65rem;color:var(--muted2);white-space:nowrap}
.tb-divider{width:1px;height:20px;background:var(--border2);margin:0 .2rem}
.tb-icon-btn{width:32px;height:32px;border-radius:7px;border:1px solid var(--border2);background:rgba(255,255,255,.04);cursor:pointer;display:grid;place-items:center;color:var(--muted2);transition:var(--t);flex-shrink:0}
.tb-icon-btn:hover{border-color:var(--blue);color:var(--text)}
.tb-admin{display:flex;align-items:center;gap:.55rem;background:rgba(255,255,255,.04);border:1px solid var(--border2);border-radius:9px;padding:.28rem .65rem .28rem .28rem;cursor:pointer;transition:var(--t);text-decoration:none}
.tb-admin:hover{border-color:rgba(59,139,255,.28);background:rgba(59,139,255,.06)}
.tb-admin-av{width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,var(--blue),var(--purple));color:#fff;display:grid;place-items:center;font-size:.7rem;font-weight:700;flex-shrink:0;font-family:var(--display)}
.tb-admin-info{display:flex;flex-direction:column}
.tb-admin-name{font-size:.78rem;font-weight:600;line-height:1.2}
.tb-admin-role{font-size:.6rem;color:var(--blue);letter-spacing:.5px;font-family:var(--mono)}
.tb-search-wrap{position:relative}
.tb-search-icon{position:absolute;left:.65rem;top:50%;transform:translateY(-50%);color:var(--muted2);pointer-events:none}
.tb-search{background:rgba(255,255,255,.04);border:1px solid var(--border2);border-radius:8px;padding:.38rem .8rem .38rem 2rem;font-family:var(--font);font-size:.78rem;color:var(--text);outline:none;width:200px;transition:var(--t)}
.tb-search:focus{border-color:rgba(59,139,255,.4)}
.tb-search::placeholder{color:var(--muted)}
.notif-wrap{position:relative}
.notif-dot{position:absolute;top:5px;right:5px;width:7px;height:7px;border-radius:50%;background:var(--red);border:1.5px solid var(--bg2)}
.np{position:absolute;right:0;top:calc(100% + 8px);width:280px;background:var(--bg3);border:1px solid var(--border2);border-radius:10px;box-shadow:var(--shadow);z-index:100}
.np.hidden{display:none}
.np-hdr{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;border-bottom:1px solid var(--border);font-size:.82rem;font-weight:600}
.np-hdr button{font-size:.72rem;color:var(--muted2);background:none;border:none;cursor:pointer}
.np-empty{font-size:.8rem;color:var(--muted2);padding:1rem;text-align:center}
.np-item{display:flex;gap:.6rem;padding:.7rem 1rem;border-bottom:1px solid var(--border);font-size:.78rem}
.np-item:last-child{border-bottom:none}
.np-dot{width:8px;height:8px;border-radius:50%;background:var(--red);flex-shrink:0;margin-top:4px}

/* ── Content ── */
.content{flex:1;overflow-y:auto;padding:1.5rem}
.content::-webkit-scrollbar{width:4px}.content::-webkit-scrollbar-thumb{background:var(--border2);border-radius:2px}
.sec-hdr{margin-bottom:1.25rem}
.sec-hdr h2{font-family:var(--display);font-size:1.25rem;font-weight:700;letter-spacing:.5px}
.sec-hdr p{font-size:.82rem;color:var(--muted2);margin-top:.2rem}

/* ── Terms Layout ── */
.terms-wrap{max-width:860px;margin:0 auto}

/* ── Hero ── */
.terms-hero{background:linear-gradient(135deg,rgba(59,139,255,.12),rgba(123,114,240,.12));border:1px solid rgba(59,139,255,.12);border-radius:14px;padding:1.5rem 1.75rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:1.25rem}
.terms-hero-icon{width:52px;height:52px;border-radius:12px;background:linear-gradient(135deg,var(--blue),var(--purple));display:grid;place-items:center;font-size:1.5rem;flex-shrink:0;box-shadow:0 0 20px rgba(59,139,255,.3)}
.terms-hero h2{font-family:var(--display);font-size:1.1rem;font-weight:700;letter-spacing:.3px}
.terms-hero p{font-size:.78rem;color:var(--muted2);margin-top:.25rem;font-family:var(--mono);letter-spacing:.5px}

/* ── Section Card ── */
.terms-card{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:1.25rem 1.5rem;margin-bottom:.9rem;transition:border-color .18s}
.terms-card:hover{border-color:var(--border2)}
.terms-card-title{display:flex;align-items:center;gap:.6rem;font-family:var(--display);font-size:.95rem;font-weight:700;margin-bottom:.85rem;padding-bottom:.65rem;border-bottom:1px solid var(--border)}
.terms-card-title::before{content:'';width:10px;height:3px;background:var(--blue);border-radius:2px;flex-shrink:0}
.terms-card p{font-size:.82rem;color:var(--muted2);line-height:1.7;margin-bottom:.65rem}
.terms-card p:last-child{margin-bottom:0}
.terms-card ul{margin-left:1.25rem;margin-bottom:.65rem}
.terms-card ul:last-child{margin-bottom:0}
.terms-card li{font-size:.82rem;color:var(--muted2);line-height:1.65;margin-bottom:.3rem}
.terms-card strong{color:var(--text);font-weight:600}
.terms-card a{color:var(--blue);text-decoration:none}
.terms-card a:hover{text-decoration:underline}

/* ── Acceptance Bar ── */
.acceptance-bar{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:1.25rem 1.5rem;text-align:center;margin-bottom:.9rem}
.acceptance-bar p{font-size:.8rem;color:var(--muted2);margin-bottom:1rem;line-height:1.6}

/* ── Buttons ── */
.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.42rem .9rem;border-radius:8px;font-family:var(--font);font-size:.78rem;font-weight:600;cursor:pointer;transition:var(--t);border:none;text-decoration:none}
.btn-p{background:var(--blue);color:#fff}.btn-p:hover{background:#2e7ae8}
.btn-s{background:rgba(255,255,255,.05);color:var(--muted2);border:1px solid var(--border2)}.btn-s:hover{border-color:var(--blue);color:var(--text)}

/* ── Modal ── */
.mo{position:fixed;inset:0;background:rgba(0,0,0,.6);display:grid;place-items:center;z-index:200;backdrop-filter:blur(4px)}
.mo.hidden{display:none}
.modal{background:var(--bg3);border:1px solid var(--border2);border-radius:14px;width:min(90vw,480px);box-shadow:0 20px 60px rgba(0,0,0,.6);animation:su .2s ease}
@keyframes su{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
.mhdr{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid var(--border)}
.mhdr h3{font-family:var(--display);font-size:1rem;font-weight:700}
.mcl{width:28px;height:28px;border-radius:7px;border:1px solid var(--border2);background:none;color:var(--muted2);cursor:pointer;display:grid;place-items:center;transition:var(--t)}
.mcl:hover{border-color:var(--red);color:var(--red)}
.mbdy{padding:1.25rem}

/* ── Toast ── */
#toast-c{position:fixed;bottom:1.25rem;right:1.25rem;display:flex;flex-direction:column;gap:.5rem;z-index:300}
.toast{background:var(--bg3);border:1px solid var(--border2);border-radius:9px;padding:.75rem 1rem;font-size:.82rem;box-shadow:var(--shadow);display:flex;align-items:center;gap:.6rem;animation:sl .2s ease;min-width:240px}
@keyframes sl{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:none}}
.ti{width:8px;height:8px;border-radius:50%;flex-shrink:0}

@media print{#sidebar,#toast-c,.topbar,.acceptance-bar{display:none!important}.content{padding:0}.terms-card{border:1px solid #ccc;break-inside:avoid}}
</style>
</head>
<body>
<div class="bg-grid"></div>
<div id="app">

  <!-- ── Sidebar ── -->
  <aside id="sidebar">
    <div class="sb-brand">
      <div class="shield"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
      <div class="sb-brand-text"><h2>CyberShield</h2><span class="badge">Vendor Portal</span></div>
      <button class="sb-toggle" onclick="toggleSidebar()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
    </div>
    <div class="sb-section">
      <div class="sb-label">Main Menu</div>
      <a class="sb-item" href="index.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.2"/><rect x="14" y="3" width="7" height="7" rx="1.2"/><rect x="3" y="14" width="7" height="7" rx="1.2"/><rect x="14" y="14" width="7" height="7" rx="1.2"/></svg></span><span class="sb-text">Dashboard</span></a>
      <a class="sb-item" href="assessment.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span><span class="sb-text">Take Assessment</span></a>
      <a class="sb-item" href="result.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span><span class="sb-text">My Results</span></a>
      <a class="sb-item" href="review.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6l4-4 4 4"/><path d="M12 2v13"/><path d="M20 21H4"/><path d="M17 12h3v9"/><path d="M4 12h3v9"/></svg></span><span class="sb-text">Review</span></a>
      <a class="sb-item" href="certificates.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg></span><span class="sb-text">Certificates</span></a>
      <div class="sb-divider"></div>
      <div class="sb-label">Account</div>
      <a class="sb-item" href="profile.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span><span class="sb-text">My Profile</span></a>
      <a class="sb-item" href="security-tips.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span><span class="sb-text">Security Tips</span></a>
      <a class="sb-item active" href="terms.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span><span class="sb-text">Terms & Privacy</span></a>
      
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
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span>Sign Out</span>
      </button>
    </div>
  </aside>

  <!-- ── Main ── -->
  <div id="main">
    <div class="topbar">
      <div>
        <div class="tb-bc">
          <span class="tb-app">CyberShield</span>
          <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 4l4 4-4 4"/></svg>
          <span class="tb-title">Terms & Privacy</span>
        </div>
        <p class="tb-sub">Your rights and our commitments</p>
      </div>
      <div class="tb-right">
        <div class="tb-search-wrap">
          <span class="tb-search-icon"><svg width="12" height="12" viewBox="0 0 20 20" fill="none">
                <circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.7"/>
                <path d="M15 15l3 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
              </svg></span>
          <input type="text" class="tb-search" placeholder="Search assessments, tips…" autocomplete="off"/>
        </div>
        <span class="tb-date" id="tb-date"></span>
        <div class="tb-divider"></div>
        <button class="tb-icon-btn" onclick="toggleTheme()" title="Toggle theme">
            <svg id="tmoon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 21 12.79z"/>
            </svg>
            <svg id="tsun" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:none">
              <circle cx="12" cy="12" r="5"/>
              <line x1="12" y1="1" x2="12" y2="3"/>
              <line x1="12" y1="21" x2="12" y2="23"/>
              <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
              <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
              <line x1="1" y1="12" x2="3" y2="12"/>
              <line x1="21" y1="12" x2="23" y2="12"/>
            </svg>
          </button>
          <div class="notif-wrap">
            <button class="tb-icon-btn" onclick="toggleNotif()" title="Notifications" style="position:relative">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
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
            <div class="tb-admin-info"><span class="tb-admin-name"><?php echo htmlspecialchars($user['full_name']); ?></span><span class="tb-admin-role"><?php echo htmlspecialchars($user['role'] ?? 'Vendor'); ?></span></div>
            <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" style="color:var(--muted);margin-left:.2rem">
              <path d="M4 6l4 4 4-4"/>
            </svg>
          </a>
        </div>
      </div>

    <div class="content">
      <div class="terms-wrap">

        <!-- Hero -->
        <div class="terms-hero">
          <div class="terms-hero-icon">📜</div>
          <div>
            <h2>Terms of Service & Privacy Policy</h2>
            <p>Last Updated: March 15, 2025</p>
          </div>
        </div>

        <!-- 1. Introduction -->
        <div class="terms-card">
          <div class="terms-card-title">1. Introduction</div>
          <p>Welcome to CyberShield, a vendor cybersecurity hygiene assessment platform. By accessing or using our platform, you agree to be bound by these Terms of Service and our Privacy Policy. If you do not agree to these terms, please do not use our services.</p>
          <p>CyberShield helps organizations evaluate their cybersecurity awareness and practices through standardized assessments, provides actionable insights, and tracks progress over time.</p>
        </div>

        <!-- 2. Data Collection -->
        <div class="terms-card">
          <div class="terms-card-title">2. Data Collection and Usage</div>
          <p>We collect and process the following types of data:</p>
          <ul>
            <li><strong>Account Information:</strong> Name, email address, organization name, and role</li>
            <li><strong>Assessment Data:</strong> Responses to security questionnaires, scores, and rankings</li>
            <li><strong>Activity Logs:</strong> User actions within the platform (logins, assessments, exports)</li>
            <li><strong>Technical Data:</strong> IP addresses, browser information, and device identifiers</li>
          </ul>
          <p>This data is used to:</p>
          <ul>
            <li>Calculate and display your security scores</li>
            <li>Generate personalized recommendations</li>
            <li>Track your progress over time</li>
            <li>Provide leaderboard rankings (with your consent)</li>
            <li>Improve our services and user experience</li>
          </ul>
        </div>

        <!-- 3. Data Storage -->
        <div class="terms-card">
          <div class="terms-card-title">3. Data Storage and Security</div>
          <p>All data is stored in a secure MySQL database hosted on our servers. We implement industry-standard security measures including:</p>
          <ul>
            <li>Password hashing using bcrypt</li>
            <li>Session management with automatic expiration</li>
            <li>Encrypted data transmission (HTTPS)</li>
            <li>Regular security audits and updates</li>
          </ul>
          <p>Your data is never sold to third parties. We only share data when required by law or with your explicit consent.</p>
        </div>

        <!-- 4. Your Rights -->
        <div class="terms-card">
          <div class="terms-card-title">4. Your Rights</div>
          <p>You have the following rights regarding your data:</p>
          <ul>
            <li><strong>Right to Access:</strong> View all data we hold about you</li>
            <li><strong>Right to Rectification:</strong> Correct inaccurate or incomplete data</li>
            <li><strong>Right to Erasure:</strong> Request deletion of your account and associated data</li>
            <li><strong>Right to Data Portability:</strong> Export your data in a machine-readable format</li>
            <li><strong>Right to Object:</strong> Opt-out of certain data processing activities</li>
          </ul>
          <p>To exercise these rights, contact your system administrator or email <a href="mailto:privacy@cybershield.ph">privacy@cybershield.ph</a>.</p>
        </div>

        <!-- 5. Acceptable Use -->
        <div class="terms-card">
          <div class="terms-card-title">5. Acceptable Use Policy</div>
          <p>You agree not to:</p>
          <ul>
            <li>Attempt to gain unauthorized access to other accounts</li>
            <li>Upload malicious code or attempt to compromise the platform</li>
            <li>Share assessment questions or answers outside the platform</li>
            <li>Manipulate assessment results or leaderboard rankings</li>
            <li>Use the platform for illegal activities</li>
          </ul>
          <p>Violation may result in immediate account termination and legal action.</p>
        </div>

        <!-- 6. Disclaimer -->
        <div class="terms-card">
          <div class="terms-card-title">6. Disclaimer of Warranties</div>
          <p>The platform is provided "as is" without warranties of any kind. While we strive for accuracy, we do not guarantee that:</p>
          <ul>
            <li>The platform will be uninterrupted or error-free</li>
            <li>Assessment results guarantee complete security</li>
            <li>Recommendations will prevent all security incidents</li>
          </ul>
          <p>CyberShield is a tool to help improve security awareness, not a guarantee of security.</p>
        </div>

        <!-- 7. Liability -->
        <div class="terms-card">
          <div class="terms-card-title">7. Limitation of Liability</div>
          <p>To the maximum extent permitted by law, CyberShield shall not be liable for any indirect, incidental, special, consequential, or punitive damages resulting from your use of or inability to use the platform.</p>
          <p>Our total liability shall not exceed the amount paid by you (if any) for using the platform.</p>
        </div>

        <!-- 8. Changes -->
        <div class="terms-card">
          <div class="terms-card-title">8. Changes to Terms</div>
          <p>We may update these terms periodically. Significant changes will be notified via email or platform notification. Continued use of the platform after changes constitutes acceptance of the new terms.</p>
          <p>This version was last updated on March 15, 2025.</p>
        </div>

        <!-- 9. Contact -->
        <div class="terms-card">
          <div class="terms-card-title">9. Contact Information</div>
          <p>For questions about these terms or privacy practices, contact:</p>
          <p>
            <strong>Email:</strong> <a href="mailto:legal@cybershield.ph">legal@cybershield.ph</a><br>
            <strong>Address:</strong> CyberShield Security Inc., 123 Security Street, Tech City, 12345<br>
            <strong>Phone:</strong> +1 (555) 123-4567
          </p>
        </div>

        <!-- Acceptance -->
        <div class="acceptance-bar">
          <p>By using CyberShield, you acknowledge that you have read, understood, and agree to these Terms of Service and Privacy Policy.</p>
          <button class="btn btn-p" onclick="window.location.href='dashboard.php'">
            I Understand & Continue
            <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 4l4 4-4 4"/></svg>
          </button>
        </div>

      </div><!-- /.terms-wrap -->
    </div><!-- /.content -->
  </div><!-- /#main -->
</div><!-- /#app -->

<!-- ── Modal ── -->
<div id="modal-overlay" class="mo hidden" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <div class="mhdr">
      <h3>Confirm Action</h3>
      <button class="mcl" onclick="closeModal()"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="mbdy" id="modal-body"></div>
  </div>
</div>

<div id="toast-c"></div>

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('collapsed');
  localStorage.setItem('cs_sb', document.getElementById('sidebar').classList.contains('collapsed') ? '1' : '0');
}
function isDark(){return document.documentElement.getAttribute('data-theme')==='dark'}

/* ── Theme ── */
function toggleTheme(){
  const d=!isDark();
  document.documentElement.setAttribute('data-theme',d?'dark':'light');
  localStorage.setItem('cs_th',d?'dark':'light');
  const m=document.getElementById('tmoon'),s=document.getElementById('tsun');
  if(m)m.style.display=d?'':'none';
  if(s)s.style.display=d?'none':'';
  if (typeof onThemeChange === 'function') onThemeChange();
}

/* ── Notifications ── */
function toggleNotif(){
  const p=document.getElementById('np');
  if(p)p.classList.toggle('hidden');
}
function clearNotifs(){
  const l=document.getElementById('np-list');
  if(l)l.innerHTML='<p class="np-empty">No notifications</p>';
  const d=document.getElementById('notif-dot');
  if(d)d.style.display='none';
  const p=document.getElementById('np');
  if(p)p.classList.add('hidden');
}

/* ── Modal ── */
function closeModal(){document.getElementById('modal-overlay').classList.add('hidden')}
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
document.addEventListener('DOMContentLoaded', ()=>{
  const th=localStorage.getItem('cs_th')||'dark';
  document.documentElement.setAttribute('data-theme',th);
  const m=document.getElementById('tmoon'),s=document.getElementById('tsun');
  if(m)m.style.display=th==='dark'?'':'none';
  if(s)s.style.display=th==='dark'?'none':'';
  const sb=localStorage.getItem('cs_sb');
  if(sb==='1')document.getElementById('sidebar').classList.add('collapsed');
  const d=document.getElementById('tb-date');
  if(d)d.textContent=new Date().toLocaleDateString('en-US',{weekday:'short',month:'short',day:'numeric',year:'numeric'});

  // Close notif on outside click
  document.addEventListener('click', e => {
    const p=document.getElementById('np');
    const b=document.getElementById('notif-btn');
    if(p && !p.classList.contains('hidden') && !p.contains(e.target) && b && !b.contains(e.target))
      p.classList.add('hidden');
  });
});
</script>
</body>
</html>