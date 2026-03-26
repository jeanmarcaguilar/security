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

// Get latest assessment for this user's vendor
$assessment_query = "SELECT va.*, v.name as vendor_name 
    FROM vendor_assessments va 
    JOIN vendors v ON va.vendor_id = v.id 
    WHERE v.email = :email 
    ORDER BY va.created_at DESC LIMIT 1";
$stmt = $db->prepare($assessment_query);
$stmt->bindParam(':email', $user['email']);
$stmt->execute();
$latest_assessment = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all assessments for history
$history_query = "SELECT va.*, v.name as vendor_name 
    FROM vendor_assessments va 
    JOIN vendors v ON va.vendor_id = v.id 
    WHERE v.email = :email 
    ORDER BY va.created_at DESC";
$stmt = $db->prepare($history_query);
$stmt->bindParam(':email', $user['email']);
$stmt->execute();
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Results — CyberShield</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
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
#sidebar{width:228px;min-width:228px;background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;transition:width .18s,min-width .18s;overflow:hidden;z-index:10;flex-shrink:0}
#sidebar.collapsed{width:58px;min-width:58px}
.sb-brand{display:flex;align-items:center;gap:.75rem;padding:1rem .9rem .9rem;border-bottom:1px solid var(--border);flex-shrink:0}
.shield{width:34px;height:34px;background:linear-gradient(135deg,var(--blue),var(--purple));border-radius:9px;display:grid;place-items:center;flex-shrink:0;box-shadow:0 0 16px rgba(59,139,255,.3)}
.sb-brand-text{flex:1;overflow:hidden;white-space:nowrap}
.sb-brand-text h2{font-family:var(--display);font-size:.95rem;font-weight:700;letter-spacing:1px}
.sb-brand-text .badge{font-family:var(--mono);font-size:.55rem;letter-spacing:1.5px;text-transform:uppercase;background:rgba(16,217,130,.12);color:var(--green);border:1px solid rgba(16,217,130,.2);border-radius:4px;padding:.08rem .38rem;display:inline-block;margin-top:.1rem}
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
#main{flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0}
.topbar{height:54px;min-height:54px;display:flex;align-items:center;justify-content:space-between;padding:0 1.5rem;background:var(--bg2);border-bottom:1px solid var(--border);gap:1rem;flex-shrink:0}
.tb-bc{display:flex;align-items:center;gap:.4rem}
.tb-app{font-family:var(--mono);font-size:.68rem;color:var(--muted);letter-spacing:.5px}
.tb-title{font-family:var(--display);font-size:1.05rem;letter-spacing:1px}
.tb-sub{font-family:var(--mono);font-size:.63rem;letter-spacing:.5px;color:var(--muted);margin-top:1px}
.tb-right{display:flex;align-items:center;gap:.55rem}
.tb-search-wrap{position:relative}
.tb-search-icon{position:absolute;left:.65rem;top:50%;transform:translateY(-50%);color:var(--muted2);pointer-events:none}
.tb-search{background:rgba(255,255,255,.04);border:1px solid var(--border2);border-radius:8px;padding:.38rem .8rem .38rem 2rem;font-family:var(--font);font-size:.78rem;color:var(--text);outline:none;width:200px;transition:var(--t)}
.tb-search:focus{border-color:rgba(59,139,255,.4)}
.tb-search::placeholder{color:var(--muted)}
.tb-date{font-family:var(--mono);font-size:.65rem;color:var(--muted2);white-space:nowrap}
.tb-divider{width:1px;height:20px;background:var(--border2);margin:0 .2rem}
.tb-icon-btn{width:32px;height:32px;border-radius:7px;border:1px solid var(--border2);background:rgba(255,255,255,.04);cursor:pointer;display:grid;place-items:center;color:var(--muted2);transition:var(--t);flex-shrink:0}
.tb-icon-btn:hover{border-color:var(--blue);color:var(--text)}
.tb-admin{display:flex;align-items:center;gap:.55rem;background:rgba(255,255,255,.04);border:1px solid var(--border2);border-radius:9px;padding:.28rem .65rem .28rem .28rem;cursor:pointer;transition:var(--t)}
.tb-admin:hover{border-color:rgba(255,59,92,.28);background:rgba(255,59,92,.06)}
.tb-admin-av{width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,var(--blue),var(--purple));color:#fff;display:grid;place-items:center;font-size:.7rem;font-weight:700;flex-shrink:0;font-family:var(--display)}
.tb-admin-info{display:flex;flex-direction:column}
.tb-admin-name{font-size:.78rem;font-weight:600;line-height:1.2}
.tb-admin-role{font-size:.6rem;color:var(--blue);letter-spacing:.5px;font-family:var(--mono)}
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
.content{flex:1;overflow-y:auto;padding:1.5rem}
.content::-webkit-scrollbar{width:4px}.content::-webkit-scrollbar-thumb{background:var(--border2);border-radius:2px}
.card{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow);transition:border-color .18s}
.card:hover{border-color:var(--border2)}
.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.42rem .9rem;border-radius:8px;font-family:var(--font);font-size:.78rem;font-weight:600;cursor:pointer;transition:var(--t);border:none;text-decoration:none}
.btn-p{background:var(--blue);color:#fff}.btn-p:hover{background:#2e7ae8}
.btn-s{background:rgba(255,255,255,.05);color:var(--muted2);border:1px solid var(--border2)}.btn-s:hover{border-color:var(--blue);color:var(--text)}
.btn-d{background:rgba(255,59,92,.1);color:var(--red);border:1px solid rgba(255,59,92,.2)}.btn-d:hover{background:rgba(255,59,92,.2)}
.btn-sm{font-size:.72rem;padding:.32rem .7rem}
.mo{position:fixed;inset:0;background:rgba(0,0,0,.6);display:grid;place-items:center;z-index:200;backdrop-filter:blur(4px)}
.mo.hidden{display:none}
.modal{background:var(--bg3);border:1px solid var(--border2);border-radius:14px;width:min(90vw,560px);box-shadow:0 20px 60px rgba(0,0,0,.6);animation:su .2s ease}
@keyframes su{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
.mhdr{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid var(--border)}
.mhdr h3{font-family:var(--display);font-size:1rem;font-weight:700}
.mcl{width:28px;height:28px;border-radius:7px;border:1px solid var(--border2);background:none;color:var(--muted2);cursor:pointer;display:grid;place-items:center;transition:var(--t)}
.mcl:hover{border-color:var(--red);color:var(--red)}
.mbdy{padding:1.25rem}
#toast-c{position:fixed;bottom:1.25rem;right:1.25rem;display:flex;flex-direction:column;gap:.5rem;z-index:300}
.toast{background:var(--bg3);border:1px solid var(--border2);border-radius:9px;padding:.75rem 1rem;font-size:.82rem;box-shadow:var(--shadow);display:flex;align-items:center;gap:.6rem;animation:sl .2s ease;min-width:240px}
@keyframes sl{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:none}}
.ti{width:8px;height:8px;border-radius:50%;flex-shrink:0}

/* Results-specific styles */
.result-hero{background:linear-gradient(135deg,var(--card-bg) 0%,var(--bg2) 100%);border-radius:24px;padding:2rem;text-align:center;margin-bottom:2rem;border:1px solid var(--border)}
.score-circle{width:180px;height:180px;border-radius:50%;margin:0 auto 1rem;display:flex;align-items:center;justify-content:center;background:conic-gradient(var(--blue) 0deg,var(--bg2) 0deg);position:relative}
.score-inner{width:140px;height:140px;border-radius:50%;background:var(--card-bg);display:flex;flex-direction:column;align-items:center;justify-content:center}
.score-value{font-size:3rem;font-weight:700;color:var(--blue)}
.rank-badge-large{font-size:2rem;font-weight:700;padding:.25rem 1rem;border-radius:50px;display:inline-block;margin-top:1rem}
.rank-a{background:rgba(16,217,130,.15);color:var(--green)}
.rank-b{background:rgba(245,183,49,.15);color:var(--yellow)}
.rank-c{background:rgba(255,140,66,.15);color:var(--orange)}
.rank-d{background:rgba(255,59,92,.15);color:var(--red)}
.category-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-top:1rem}
.category-card{background:var(--bg2);border-radius:12px;padding:1rem;text-align:center}
.category-score{font-size:1.5rem;font-weight:700;margin-top:.5rem}
.recommendation-item{padding:.75rem;border-left:3px solid var(--blue);margin-bottom:.75rem;background:var(--bg2);border-radius:8px}
.badge-earned{background:linear-gradient(135deg,#f59e0b,#ef4444);color:white;padding:.5rem 1rem;border-radius:50px;display:inline-flex;align-items:center;gap:.5rem}
.video-card{background:var(--bg2);border-radius:12px;padding:1rem;cursor:pointer;transition:var(--t)}
.video-card:hover{transform:translateY(-4px)}
.video-thumb{width:100%;height:120px;background:var(--card-bg);border-radius:8px;display:flex;align-items:center;justify-content:center;margin-bottom:.75rem}
.chart-card{margin-top:1.5rem}
.chart-card h3{margin-bottom:1rem}
.chart-wrap{position:relative;height:300px}

/* Admin dashboard styles */
.sec-hdr{margin-bottom:1.25rem}
.sec-hdr h2{font-family:var(--display);font-size:1.25rem;font-weight:700;letter-spacing:.5px}
.sec-hdr p{font-size:.82rem;color:var(--muted2);margin-top:.2rem}
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.9rem;margin-bottom:1.25rem}
.stat-card{padding:1.15rem 1.25rem;position:relative;overflow:hidden}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:var(--accent,var(--blue));opacity:.7}
.si{width:32px;height:32px;border-radius:8px;display:grid;place-items:center;margin-bottom:.65rem}
.slabel{font-family:var(--mono);font-size:.6rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted2);margin-bottom:.3rem}
.sval{font-family:var(--display);font-size:1.9rem;font-weight:700;line-height:1}
.ssub{font-size:.7rem;color:var(--muted);margin-top:.3rem}
.charts-grid{display:grid;gap:.9rem;margin-bottom:1.25rem}
.chart-card{padding:1.15rem 1.25rem}
.chart-card h3{font-family:var(--mono);font-size:.65rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted2);margin-bottom:.85rem;display:flex;align-items:center;gap:.5rem}
.chart-card h3::before{content:'';width:10px;height:3px;background:var(--blue);border-radius:2px;flex-shrink:0}
.cw{width:100%}.cw.sm{height:160px}.cw.md{height:190px}.cw.lg{height:240px}
.tbl-card{padding:1.25rem 1.5rem}
.tbl-bar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.65rem;margin-bottom:1rem}
.tbl-bar h3{font-family:var(--display);font-size:1rem;font-weight:700}
.frow{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
.fsel{background:rgba(255,255,255,.04);border:1px solid var(--border2);border-radius:7px;padding:.38rem .75rem;font-family:var(--font);font-size:.78rem;color:var(--text);cursor:pointer;outline:none;transition:var(--t)}
.fsel:focus{border-color:var(--blue)}
[data-theme=light] .fsel{background:#fff}
.tw{overflow-x:auto}
table{width:100%;border-collapse:collapse}
thead th{text-align:left;padding:.55rem .75rem;font-family:var(--mono);font-size:.6rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted2);border-bottom:1px solid var(--border);white-space:nowrap}
tbody tr{border-bottom:1px solid var(--border);transition:background .18s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:rgba(59,139,255,.04)}
tbody td{padding:.65rem .75rem;font-size:.82rem}
.rank{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:5px;font-family:var(--mono);font-size:.7rem;font-weight:700}
.rA{background:rgba(16,217,130,.15);color:var(--green)}.rB{background:rgba(245,183,49,.15);color:var(--yellow)}
.rC{background:rgba(255,140,66,.15);color:var(--orange)}.rD{background:rgba(255,59,92,.15);color:var(--red)}
.sbw{display:flex;align-items:center;gap:.6rem}
.sbb{flex:1;height:4px;background:var(--border2);border-radius:2px}
.sbf{height:100%;border-radius:2px}
.sbn{font-family:var(--mono);font-size:.72rem;color:var(--muted2);min-width:32px;text-align:right}
.analytic-label{font-family:var(--mono);font-size:.58rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted)}
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div id="app">

  <!-- SIDEBAR -->
  <aside id="sidebar">
    <div class="sb-brand">
      <div class="shield"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
      <div class="sb-brand-text"><h2>CyberShield</h2><span class="badge">Client Portal</span></div>
      <button class="sb-toggle" onclick="toggleSidebar()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
    </div>
    <div class="sb-section">
      <div class="sb-label">Navigation</div>
      <a class="sb-item" href="index.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.2"/><rect x="14" y="3" width="7" height="7" rx="1.2"/><rect x="3" y="14" width="7" height="7" rx="1.2"/><rect x="14" y="14" width="7" height="7" rx="1.2"/></svg></span><span class="sb-text">Dashboard</span></a>
      <a class="sb-item" href="assessment.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span><span class="sb-text">Assessment</span></a>
      <a class="sb-item active" href="result.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span><span class="sb-text">Results</span></a>
      <a class="sb-item" href="leaderboard.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6l4-4 4 4"/><path d="M12 2v13"/><path d="M20 21H4"/><path d="M17 12h3v9"/><path d="M4 12h3v9"/></svg></span><span class="sb-text">Leaderboard</span></a>
      <div class="sb-divider"></div>
      <div class="sb-label">Seller Hub</div>
      <a class="sb-item" href="seller-store.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1 0 8 0"/></svg></span><span class="sb-text">My Store</span></a>
      <a class="sb-item" href="seller-analytics.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><polyline points="2 20 22 20"/></svg></span><span class="sb-text">Analytics</span></a>
      <div class="sb-divider"></div>
      <div class="sb-label">Account</div>
      <a class="sb-item" href="profile.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span><span class="sb-text">Profile</span></a>
      <a class="sb-item" href="security-tips.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span><span class="sb-text">Security Tips</span></a>
      <a class="sb-item" href="terms.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span><span class="sb-text">Terms & Privacy</span></a>
    </div>
    <div class="sb-footer">
      <div class="sb-user">
        <div class="sb-avatar" id="sb-avatar"><?php echo strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 1)); ?></div>
        <div class="sb-user-info"><p id="sb-name"><?php echo htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'User'); ?></p><span id="sb-email"><?php echo htmlspecialchars($user['email'] ?? 'user@example.com'); ?></span></div>
      </div>
      <button class="btn-sb-logout" onclick="doLogout()">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span>Sign Out</span>
      </button>
    </div>
  </aside>
  <div id="main">

    <div class="topbar">
      <div>
        <div class="tb-bc">
          <span class="tb-app">CyberShield</span>
          <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 4l4 4-4 4"/></svg>
          <span class="tb-title">Assessment Results</span>
        </div>
        <p class="tb-sub">Your cybersecurity assessment results and recommendations</p>
      </div>
      <div class="tb-right">
        <div class="tb-search-wrap">
          <span class="tb-search-icon"><svg width="12" height="12" viewBox="0 0 20 20" fill="none"><circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.7"/><path d="M15 15l3 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span>
          <input type="text" class="tb-search" placeholder="Search…" autocomplete="off"/>
        </div>
        <span class="tb-date" id="tb-date"></span>
        <div class="tb-divider"></div>
        <button class="tb-icon-btn" onclick="toggleTheme()" title="Toggle theme">
          <svg id="tmoon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 21 12.79z"/></svg>
          <svg id="tsun" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:none"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>
        </button>
        <div class="notif-wrap">
          <button class="tb-icon-btn" onclick="toggleNotif()" title="Alerts" style="position:relative">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="notif-dot" id="notif-dot"></span>
          </button>
          <div class="np hidden" id="np">
            <div class="np-hdr"><span>Alerts</span><button onclick="clearNotifs()">Clear all</button></div>
            <div id="np-list"><p class="np-empty">No alerts</p></div>
          </div>
        </div>
        <div class="tb-divider"></div>
        <a class="tb-admin" href="profile.php">
          <div class="tb-admin-av" id="tb-avatar"><?php echo strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 1)); ?></div>
          <div class="tb-admin-info"><span class="tb-admin-name" id="tb-name"><?php echo htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'User'); ?></span><span class="tb-admin-role">Client</span></div>
          <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" style="color:var(--muted);margin-left:.2rem"><path d="M4 6l4 4 4-4"/></svg>
        </a>
      </div>
    </div>

    <div class="content">
      <div class="sec-hdr"><h2>Assessment Results</h2><p>Your cybersecurity assessment results and detailed analytics.</p></div>
      <div class="stats-row" id="stats-row">
        <div class="card stat-card" style="--accent:var(--blue)"><div class="si" style="background:rgba(59,139,255,.12);color:var(--blue)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div><div class="slabel">Latest Score</div><div class="sval" id="sv-score"><?php echo $latest_assessment ? $latest_assessment['score'] . '%' : '—'; ?></div><div class="ssub">Overall assessment</div></div>
        <div class="card stat-card" style="--accent:var(--teal)"><div class="si" style="background:rgba(0,212,170,.12);color:var(--teal)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div><div class="slabel">Risk Rank</div><div class="sval" id="sv-rank"><?php echo $latest_assessment ? $latest_assessment['rank'] : '—'; ?></div><div class="ssub">Security level</div></div>
        <div class="card stat-card" style="--accent:var(--green)"><div class="si" style="background:rgba(16,217,130,.12);color:var(--green)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div><div class="slabel">Assessments</div><div class="sval" id="sv-count"><?php echo count($history); ?></div><div class="ssub">Total completed</div></div>
        <div class="card stat-card" style="--accent:var(--yellow)"><div class="si" style="background:rgba(245,183,49,.12);color:var(--yellow)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><div class="slabel">Trend</div><div class="sval" id="sv-trend"><?php echo count($history) > 1 ? ($history[0]['score'] > $history[1]['score'] ? '↑' : '↓') : '—'; ?></div><div class="ssub">vs last assessment</div></div>
      </div>

      <?php if ($latest_assessment): ?>
      <div class="charts-grid" style="grid-template-columns:1fr 1fr 2fr">
        <div class="card chart-card"><h3>Category Performance</h3><div class="cw sm"><canvas id="category-chart"></canvas></div></div>
        <div class="card chart-card"><h3>Score Distribution</h3><div class="cw sm"><canvas id="score-pie-chart"></canvas></div></div>
        <div class="card chart-card"><h3>Score Trend Over Time</h3><div class="cw sm"><canvas id="trend-chart"></canvas></div></div>
      </div>

      <div class="sec-hdr"><h2>Detailed Analysis</h2><p>Comprehensive breakdown of your security assessment.</p></div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.9rem;margin-bottom:1.25rem">
        <div class="card analytic-card" style="padding:1rem 1.15rem"><div class="analytic-label" style="font-family:var(--mono);font-size:.58rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted)">Password Security</div><div style="font-family:var(--display);font-size:1.5rem;font-weight:700;color:<?php echo $latest_assessment['password_score'] >= 80 ? 'var(--green)' : ($latest_assessment['password_score'] >= 60 ? 'var(--yellow)' : 'var(--red)'); ?>" id="an-password"><?php echo $latest_assessment['password_score']; ?>%</div><div style="font-size:.72rem;color:var(--muted2);margin-top:.2rem">Account protection</div></div>
        <div class="card analytic-card" style="padding:1rem 1.15rem"><div class="analytic-label" style="font-family:var(--mono);font-size:.58rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted)">Phishing Awareness</div><div style="font-family:var(--display);font-size:1.5rem;font-weight:700;color:<?php echo $latest_assessment['phishing_score'] >= 80 ? 'var(--green)' : ($latest_assessment['phishing_score'] >= 60 ? 'var(--yellow)' : 'var(--red)'); ?>" id="an-phishing"><?php echo $latest_assessment['phishing_score']; ?>%</div><div style="font-size:.72rem;color:var(--muted2);margin-top:.2rem">Email security</div></div>
        <div class="card analytic-card" style="padding:1rem 1.15rem"><div class="analytic-label" style="font-family:var(--mono);font-size:.58rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted)">Device Security</div><div style="font-family:var(--display);font-size:1.5rem;font-weight:700;color:<?php echo $latest_assessment['device_score'] >= 80 ? 'var(--green)' : ($latest_assessment['device_score'] >= 60 ? 'var(--yellow)' : 'var(--red)'); ?>" id="an-device"><?php echo $latest_assessment['device_score']; ?>%</div><div style="font-size:.72rem;color:var(--muted2);margin-top:.2rem">Device protection</div></div>
        <div class="card analytic-card" style="padding:1rem 1.15rem"><div class="analytic-label" style="font-family:var(--mono);font-size:.58rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted)">Network Security</div><div style="font-family:var(--display);font-size:1.5rem;font-weight:700;color:<?php echo $latest_assessment['network_score'] >= 80 ? 'var(--green)' : ($latest_assessment['network_score'] >= 60 ? 'var(--yellow)' : 'var(--red)'); ?>" id="an-network"><?php echo $latest_assessment['network_score']; ?>%</div><div style="font-size:.72rem;color:var(--muted2);margin-top:.2rem">Network protection</div></div>
      </div>

      <div class="card tbl-card">
        <div class="tbl-bar">
          <h3>Assessment History</h3>
          <div class="frow">
            <button class="btn btn-p btn-sm" onclick="exportResults()">⬇ Export</button>
          </div>
        </div>
        <div class="tw"><table><thead><tr><th>Date</th><th>Score</th><th>Rank</th><th>Password</th><th>Phishing</th><th>Device</th><th>Network</th></tr></thead><tbody id="history-tbl-body"></tbody></table></div>
      </div>

      <div class="card">
        <div class="tbl-bar">
          <h3>Personalized Recommendations</h3>
        </div>
        <div id="recommendations">
          <?php
          $recommendations = [];
          if ($latest_assessment['password_score'] < 60) {
            $recommendations[] = "🔐 Use a password manager to generate and store strong, unique passwords for all accounts.";
            $recommendations[] = "🔐 Enable multi-factor authentication (MFA) on all critical accounts.";
          }
          if ($latest_assessment['phishing_score'] < 60) {
            $recommendations[] = "🎣 Complete security awareness training to identify phishing attempts.";
            $recommendations[] = "🎣 Always verify suspicious emails by contacting the sender through a known channel.";
          }
          if ($latest_assessment['device_score'] < 60) {
            $recommendations[] = "💻 Install and regularly update antivirus/anti-malware software.";
            $recommendations[] = "💻 Enable automatic updates for your operating system and applications.";
          }
          if ($latest_assessment['network_score'] < 60) {
            $recommendations[] = "🌐 Use a VPN when connecting to public Wi-Fi networks.";
            $recommendations[] = "🌐 Secure your home Wi-Fi with WPA3 encryption and a strong password.";
          }
          if (empty($recommendations)) {
            $recommendations[] = "✅ Great job! Maintain your security practices and stay vigilant.";
          }
          
          foreach ($recommendations as $rec) {
            echo "<div class='recommendation-item'>$rec</div>";
          }
          ?>
        </div>
      </div>

      <?php else: ?>
      <div class="card" style="text-align: center; padding: 3rem;">
        <div style="font-size: 3rem; margin-bottom: 1rem;">📊</div>
        <h3>No Assessment Results Yet</h3>
        <p>You haven't completed any assessments. Start your first assessment now!</p>
        <button class="btn btn-primary" onclick="startNewAssessment()" style="margin-top: 1rem;">Start Assessment</button>
      </div>
      <?php endif; ?>

=======
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Assessment Results - CyberShield</title>
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
    window.location.href = 'index.php';
}

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
<style>
    .result-hero {
        background: linear-gradient(135deg, var(--card-bg) 0%, var(--navy-3) 100%);
        border-radius: 24px;
        padding: 2rem;
        text-align: center;
        margin-bottom: 2rem;
        border: 1px solid var(--border-2);
    }
    .score-circle {
        width: 180px;
        height: 180px;
        border-radius: 50%;
        margin: 0 auto 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        background: conic-gradient(var(--primary) 0deg, var(--navy-3) 0deg);
        position: relative;
    }
    .score-inner {
        width: 140px;
        height: 140px;
        border-radius: 50%;
        background: var(--card-bg);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    .score-value {
        font-size: 3rem;
        font-weight: 700;
        color: var(--primary);
    }
    .rank-badge-large {
        font-size: 2rem;
        font-weight: 700;
        padding: 0.25rem 1rem;
        border-radius: 50px;
        display: inline-block;
        margin-top: 1rem;
    }
    .category-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }
    .category-card {
        background: var(--navy-3);
        border-radius: 12px;
        padding: 1rem;
        text-align: center;
    }
    .category-score {
        font-size: 1.5rem;
        font-weight: 700;
        margin-top: 0.5rem;
    }
    .recommendation-item {
        padding: 0.75rem;
        border-left: 3px solid var(--primary);
        margin-bottom: 0.75rem;
        background: var(--navy-3);
        border-radius: 8px;
    }
    .badge-earned {
        background: linear-gradient(135deg, #f59e0b, #ef4444);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 50px;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    .video-card {
        background: var(--navy-3);
        border-radius: 12px;
        padding: 1rem;
        cursor: pointer;
        transition: transform 0.2s;
    }
    .video-card:hover {
        transform: translateY(-4px);
    }
    .video-thumb {
        width: 100%;
        height: 120px;
        background: var(--card-bg);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 0.75rem;
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
>>>>>>> ffb18a752ab124402d4952ea0483fe767210a8d9
    </div>

    <nav class="sidebar-nav">
      <p class="sidebar-section-label">Main Menu</p>
      <a class="sidebar-item" id="nav-dashboard" href="index.php">
        <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.2"/><rect x="14" y="3" width="7" height="7" rx="1.2"/><rect x="3" y="14" width="7" height="7" rx="1.2"/><rect x="14" y="14" width="7" height="7" rx="1.2"/></svg></span>
        <span class="sidebar-label">Dashboard</span>
        <span class="sidebar-tooltip">Dashboard</span>
      </a>
      <a class="sidebar-item" id="nav-assessment" href="assessment.php">
        <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
        <span class="sidebar-label">Take Assessment</span>
        <span class="sidebar-tooltip">Assessment</span>
      </a>
      <a class="sidebar-item active" id="nav-results" href="result.php">
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
          <span id="topbar-page-title">Assessment Results</span>
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

    <!-- RESULTS PAGE CONTENT -->
    <div id="page-results" class="page">
      <div class="page-inner fade-in">
        <div class="page-header">
          <div>
            <h2 class="page-title">Assessment Results</h2>
            <p class="page-subtitle">Your cybersecurity hygiene evaluation</p>
          </div>
          <div>
            <button class="btn btn-secondary" onclick="exportResults()">📄 Export Report</button>
            <button class="btn btn-primary" onclick="startNewAssessment()">Start New Assessment</button>
          </div>
        </div>
        
        <?php if ($latest_assessment): ?>
        <!-- Hero Section -->
        <div class="result-hero">
            <div class="score-circle" id="score-circle">
                <div class="score-inner">
                    <div class="score-value"><?php echo $latest_assessment['score']; ?>%</div>
                    <div style="font-size: 0.8rem;">Overall Score</div>
                </div>
            </div>
            <div class="rank-badge-large rank-<?php echo strtolower($latest_assessment['rank']); ?>">
                Rank <?php echo $latest_assessment['rank']; ?>
            </div>
            <p style="margin-top: 1rem;">
                <?php
                if ($latest_assessment['rank'] == 'A') echo "Excellent! Your security practices are outstanding.";
                elseif ($latest_assessment['rank'] == 'B') echo "Good work! You have a solid foundation with room for improvement.";
                elseif ($latest_assessment['rank'] == 'C') echo "Significant improvements needed. Review recommendations below.";
                else echo "Critical risk detected. Immediate action required!";
                ?>
            </p>
            <div style="margin-top: 1rem;">
                <span class="badge-earned">
                    <?php
                    $badges = [];
                    if ($latest_assessment['score'] >= 80) $badges[] = "🏆 Security Champion";
                    if ($latest_assessment['password_score'] >= 80) $badges[] = "🔒 Password Pro";
                    if ($latest_assessment['phishing_score'] >= 80) $badges[] = "🎣 Phishing Aware";
                    if ($latest_assessment['device_score'] >= 80) $badges[] = "💻 Device Guardian";
                    if ($latest_assessment['network_score'] >= 80) $badges[] = "🌐 Network Defender";
                    echo implode(' ', array_slice($badges, 0, 3));
                    ?>
                </span>
            </div>
        </div>
        
        <!-- Category Scores -->
        <div class="card">
            <h3>Category Performance</h3>
            <div class="category-grid">
                <div class="category-card">
                    <div>🔐 Password Security</div>
                    <div class="category-score"><?php echo $latest_assessment['password_score']; ?>%</div>
                </div>
                <div class="category-card">
                    <div>🎣 Phishing Awareness</div>
                    <div class="category-score"><?php echo $latest_assessment['phishing_score']; ?>%</div>
                </div>
                <div class="category-card">
                    <div>💻 Device Security</div>
                    <div class="category-score"><?php echo $latest_assessment['device_score']; ?>%</div>
                </div>
                <div class="category-card">
                    <div>🌐 Network Security</div>
                    <div class="category-score"><?php echo $latest_assessment['network_score']; ?>%</div>
                </div>
            </div>
        </div>
        
        <!-- Radar Chart -->
        <div class="card chart-card">
            <h3>Security Posture Radar</h3>
            <div class="chart-wrap" style="height: 300px;">
                <canvas id="radar-chart"></canvas>
            </div>
        </div>
        
        <!-- Recommendations -->
        <div class="card">
            <h3>Personalized Recommendations</h3>
            <div id="recommendations">
                <?php
                $recommendations = [];
                if ($latest_assessment['password_score'] < 60) {
                    $recommendations[] = "🔐 Use a password manager to generate and store strong, unique passwords for all accounts.";
                    $recommendations[] = "🔐 Enable multi-factor authentication (MFA) on all critical accounts.";
                }
                if ($latest_assessment['phishing_score'] < 60) {
                    $recommendations[] = "🎣 Complete security awareness training to identify phishing attempts.";
                    $recommendations[] = "🎣 Always verify suspicious emails by contacting the sender through a known channel.";
                }
                if ($latest_assessment['device_score'] < 60) {
                    $recommendations[] = "💻 Install and regularly update antivirus/anti-malware software.";
                    $recommendations[] = "💻 Enable automatic updates for your operating system and applications.";
                }
                if ($latest_assessment['network_score'] < 60) {
                    $recommendations[] = "🌐 Use a VPN when connecting to public Wi-Fi networks.";
                    $recommendations[] = "🌐 Secure your home Wi-Fi with WPA3 encryption and a strong password.";
                }
                if (empty($recommendations)) {
                    $recommendations[] = "✅ Great job! Maintain your security practices and stay vigilant.";
                }
                
                foreach ($recommendations as $rec) {
                    echo "<div class='recommendation-item'>$rec</div>";
                }
                ?>
            </div>
        </div>
        
        <!-- Progress Chart -->
        <?php if (count($history) > 1): ?>
        <div class="card chart-card">
            <h3>Progress Over Time</h3>
            <div class="chart-wrap" style="height: 300px;">
                <canvas id="progress-chart"></canvas>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Learning Resources -->
        <div class="card">
            <h3>Learning Resources</h3>
            <div class="video-grid" id="video-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 1rem;">
                <?php
                $videos = [
                    ['title' => 'Password Security Best Practices', 'url' => 'https://www.youtube.com/embed/example1', 'category' => 'password'],
                    ['title' => 'How to Spot Phishing Emails', 'url' => 'https://www.youtube.com/embed/example2', 'category' => 'phishing'],
                    ['title' => 'Device Security Essentials', 'url' => 'https://www.youtube.com/embed/example3', 'category' => 'device'],
                    ['title' => 'Network Security Fundamentals', 'url' => 'https://www.youtube.com/embed/example4', 'category' => 'network'],
                ];
                
                $weakCategories = [];
                if ($latest_assessment['password_score'] < 70) $weakCategories[] = 'password';
                if ($latest_assessment['phishing_score'] < 70) $weakCategories[] = 'phishing';
                if ($latest_assessment['device_score'] < 70) $weakCategories[] = 'device';
                if ($latest_assessment['network_score'] < 70) $weakCategories[] = 'network';
                
                foreach ($videos as $video) {
                    if (empty($weakCategories) || in_array($video['category'], $weakCategories)) {
                        echo "<div class='video-card' onclick=\"window.open('{$video['url']}', '_blank')\">
                            <div class='video-thumb'>📹</div>
                            <div><strong>{$video['title']}</strong></div>
                            <div style='font-size: 0.7rem; color: var(--text-3); margin-top: 0.5rem;'>Click to watch</div>
                        </div>";
                    }
                }
                ?>
            </div>
        </div>
        
        <?php else: ?>
        <div class="card" style="text-align: center; padding: 3rem;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">📊</div>
            <h3>No Assessment Results Yet</h3>
            <p>You haven't completed any assessments. Start your first assessment now!</p>
            <button class="btn btn-primary" onclick="startNewAssessment()" style="margin-top: 1rem;">Start Assessment</button>
        </div>
        <?php endif; ?>
      </div><!-- end page-inner -->
    </div><!-- end page-results -->

  </div><!-- end main-content -->
</div><!-- end app -->

<!-- LOGIN SCREEN -->
<div id="login-screen" class="login-overlay">
  <div class="login-card">
    <div class="login-header">
      <div class="shield-icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <h1>CyberShield</h1>
      <p>Security Hygiene Assessment Platform</p>
    </div>

    <form id="login-form" onsubmit="attemptLogin(event)">
      <div class="form-group">
        <label for="login-username">Username or Email</label>
        <input type="text" id="login-username" placeholder="Enter your username" required autocomplete="username">
      </div>
      <div class="form-group">
        <label for="login-password">Password</label>
        <input type="password" id="login-password" placeholder="Enter your password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary btn-full" id="login-btn">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        Sign In
      </button>
    </form>

    <div id="login-error" class="form-error" style="display:none;"></div>

    <div class="login-footer">
      <button class="btn-ghost" onclick="showForgotPassword()">Forgot password?</button>
    </div>

    <div class="demo-accounts">
      <p>Demo Accounts:</p>
      <div class="demo-btn-group">
        <button type="button" class="btn-demo" onclick="fillDemo('vendor1', 'password123')">
          <span>🏪</span> Vendor Account
        </button>
        <button type="button" class="btn-demo" onclick="fillDemo('admin', 'admin123')">
          <span>👤</span> Admin Account
        </button>
      </div>
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
async function attemptLogin(event) {
    if (event) event.preventDefault();
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

        hideLoginScreen();
        if (typeof bootApp === 'function') bootApp();

        // Initialize result-specific functionality
        initResultPage();
    } else {
        showLoginScreen();
    }
})();

// Result page specific functions
const latestAssessment = <?php echo json_encode($latest_assessment); ?>;
const history = <?php echo json_encode($history); ?>;

function initResultPage() {
    drawScoreCircle();
    initRadarChart();
    initProgressChart();
}

function initRadarChart() {
    if (!latestAssessment) return;
    
<<<<<<< HEAD
    <div id="modal-overlay" class="mo hidden" onclick="if(event.target===this)closeModal()">
      <div class="modal">
        <div class="mhdr"><h3 id="modal-title">Confirm Submission</h3><button class="mcl" onclick="closeModal()"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
        <div class="mbdy" id="modal-body"></div>
      </div>
    </div>
    <div id="toast-c"></div>
    <script>
      function isDark(){return document.documentElement.getAttribute('data-theme')==='dark'}
      function toggleSidebar(){document.getElementById('sidebar').classList.toggle('collapsed');localStorage.setItem('cs_sb',document.getElementById('sidebar').classList.contains('collapsed')?'1':'0');}
      function toggleTheme(){const d=!isDark();document.documentElement.setAttribute('data-theme',d?'dark':'light');localStorage.setItem('cs_th',d?'dark':'light');const m=document.getElementById('tmoon'),s=document.getElementById('tsun');if(m)m.style.display=d?'':'none';if(s)s.style.display=d?'none':'';}
      function toggleNotif(){const p=document.getElementById('np');if(p)p.classList.toggle('hidden');}
      function clearNotifs(){const l=document.getElementById('np-list');if(l)l.innerHTML='<p class="np-empty">No alerts</p>';const d=document.getElementById('notif-dot');if(d)d.style.display='none';const p=document.getElementById('np');if(p)p.classList.add('hidden');}
      function showToast(msg,color='blue'){const cols={blue:'var(--blue)',green:'var(--green)',red:'var(--red)',yellow:'var(--yellow)'};const t=document.createElement('div');t.className='toast';t.innerHTML=`<span class="ti" style="background:${cols[color]||cols.blue}"></span><span>${msg}</span>`;document.getElementById('toast-c').appendChild(t);setTimeout(()=>{t.style.opacity='0';t.style.transition='opacity .3s';setTimeout(()=>t.remove(),300);},2500);}
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
      function closeModal(){document.getElementById('modal-overlay').classList.add('hidden')}
      document.addEventListener('DOMContentLoaded',()=>{
        const th=localStorage.getItem('cs_th')||'dark';
        document.documentElement.setAttribute('data-theme',th);
        const m=document.getElementById('tmoon'),s=document.getElementById('tsun');
        if(m)m.style.display=th==='dark'?'':'none';
        if(s)s.style.display=th==='dark'?'none':'';
        const sb=localStorage.getItem('cs_sb');
        if(sb==='1')document.getElementById('sidebar').classList.add('collapsed');
        const d=document.getElementById('tb-date');
        if(d)d.textContent=new Date().toLocaleDateString('en-US',{weekday:'short',month:'short',day:'numeric',year:'numeric'});
      });
      
      // Results-specific JavaScript
      const latestAssessment = <?php echo json_encode($latest_assessment); ?>;
      const history = <?php echo json_encode($history); ?>;
      
      function renderHistoryTable() {
          const tbody = document.getElementById('history-tbl-body');
          if (!tbody) return;
          
          tbody.innerHTML = history.map(h => `
            <tr>
              <td style="color:var(--muted2);font-family:var(--mono);font-size:.72rem">${new Date(h.created_at).toLocaleDateString()}</td>
              <td><div class="sbw"><div class="sbb"><div class="sbf" style="width:${h.score}%;background:${h.score >= 80 ? 'var(--green)' : (h.score >= 60 ? 'var(--yellow)' : (h.score >= 40 ? 'var(--orange)' : 'var(--red)'))}"></div></div><span class="sbn">${h.score}%</span></div></td>
              <td><span class="rank r${h.rank}">${h.rank}</span></td>
              <td><div class="sbw"><div class="sbb"><div class="sbf" style="width:${h.password_score}%;background:${h.password_score >= 80 ? 'var(--green)' : (h.password_score >= 60 ? 'var(--yellow)' : 'var(--red)')}"></div></div><span class="sbn">${h.password_score}%</span></div></td>
              <td><div class="sbw"><div class="sbb"><div class="sbf" style="width:${h.phishing_score}%;background:${h.phishing_score >= 80 ? 'var(--green)' : (h.phishing_score >= 60 ? 'var(--yellow)' : 'var(--red)')}"></div></div><span class="sbn">${h.phishing_score}%</span></div></td>
              <td><div class="sbw"><div class="sbb"><div class="sbf" style="width:${h.device_score}%;background:${h.device_score >= 80 ? 'var(--green)' : (h.device_score >= 60 ? 'var(--yellow)' : 'var(--red)')}"></div></div><span class="sbn">${h.device_score}%</span></div></td>
              <td><div class="sbw"><div class="sbb"><div class="sbf" style="width:${h.network_score}%;background:${h.network_score >= 80 ? 'var(--green)' : (h.network_score >= 60 ? 'var(--yellow)' : 'var(--red)')}"></div></div><span class="sbn">${h.network_score}%</span></div></td>
            </tr>
          `).join('');
      }
      
      function renderCharts() {
          if (!latestAssessment) return;
          
          const a = ax();
          
          // Category Performance Chart
          if (charts.category) charts.category.destroy();
          const categoryCtx = document.getElementById('category-chart');
          if (categoryCtx) {
              charts.category = new Chart(categoryCtx, {
                  type: 'bar',
                  data: {
                      labels: ['Password', 'Phishing', 'Device', 'Network'],
                      datasets: [{
                          data: [latestAssessment.password_score, latestAssessment.phishing_score, latestAssessment.device_score, latestAssessment.network_score],
                          backgroundColor: ['rgba(59,139,255,.55)', 'rgba(123,114,240,.55)', 'rgba(16,217,130,.55)', 'rgba(245,183,49,.55)'],
                          borderColor: ['#3B8BFF', '#7B72F0', '#10D982', '#F5B731'],
                          borderWidth: 2,
                          borderRadius: 5,
                          borderSkipped: false
                      }]
                  },
                  options: {
                      responsive: true,
                      maintainAspectRatio: false,
                      plugins: {
                          legend: { display: false },
                          tooltip: {
                              backgroundColor: a.tt,
                              borderColor: a.ttB,
                              borderWidth: 1,
                              titleColor: a.tc,
                              bodyColor: a.bc,
                              padding: 10,
                              callbacks: {
                                  label: (context) => `${context.parsed.y}%`
                              }
                          }
                      },
                      scales: {
                          y: {
                              beginAtZero: true,
                              max: 100,
                              ticks: { stepSize: 20, color: a.tick, font: { size: 10 }, callback: v => v + '%' },
                              grid: { color: a.grid }
                          },
                          x: {
                              ticks: { color: a.tick, font: { size: 10 } },
                              grid: { display: false }
                          }
                      }
                  }
              });
          }
          
          // Score Distribution Pie Chart
          if (charts.scorePie) charts.scorePie.destroy();
          const scorePieCtx = document.getElementById('score-pie-chart');
          if (scorePieCtx) {
              const scoreRanges = {
                  'Excellent (80-100)': 0,
                  'Good (60-79)': 0,
                  'Fair (40-59)': 0,
                  'Poor (0-39)': 0
              };
              
              history.forEach(h => {
                  if (h.score >= 80) scoreRanges['Excellent (80-100)']++;
                  else if (h.score >= 60) scoreRanges['Good (60-79)']++;
                  else if (h.score >= 40) scoreRanges['Fair (40-59)']++;
                  else scoreRanges['Poor (0-39)']++;
              });
              
              charts.scorePie = new Chart(scorePieCtx, {
                  type: 'doughnut',
                  data: {
                      labels: Object.keys(scoreRanges),
                      datasets: [{
                          data: Object.values(scoreRanges),
                          backgroundColor: ['#10D982', '#F5B731', '#FF7A45', '#FF4D6A'],
                          borderWidth: 2,
                          borderColor: isDark() ? '#030508' : '#fff',
                          hoverOffset: 5
                      }]
                  },
                  options: {
                      responsive: true,
                      maintainAspectRatio: false,
                      plugins: {
                          legend: {
                              position: 'bottom',
                              labels: { font: { size: 9 }, padding: 10, color: a.tick }
                          },
                          tooltip: {
                              backgroundColor: a.tt,
                              borderColor: a.ttB,
                              borderWidth: 1,
                              titleColor: a.tc,
                              bodyColor: a.bc
                          }
                      }
                  }
              });
          }
          
          // Trend Chart
          if (charts.trend) charts.trend.destroy();
          const trendCtx = document.getElementById('trend-chart');
          if (trendCtx && history.length > 1) {
              const dates = history.slice().reverse().map(h => new Date(h.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
              const scores = history.slice().reverse().map(h => h.score);
              
              charts.trend = new Chart(trendCtx, {
                  type: 'line',
                  data: {
                      labels: dates,
                      datasets: [{
                          label: 'Assessment Score',
                          data: scores,
                          borderColor: '#7B72F0',
                          backgroundColor: isDark() ? 'rgba(91,79,232,.1)' : 'rgba(91,79,232,.12)',
                          fill: true,
                          tension: 0.4,
                          pointBackgroundColor: '#7B72F0',
                          pointBorderColor: isDark() ? '#030508' : '#fff',
                          pointBorderWidth: 2,
                          pointRadius: 4,
                          pointHoverRadius: 6
                      }]
                  },
                  options: {
                      responsive: true,
                      maintainAspectRatio: false,
                      plugins: {
                          legend: { display: false },
                          tooltip: {
                              backgroundColor: a.tt,
                              borderColor: a.ttB,
                              borderWidth: 1,
                              titleColor: a.tc,
                              bodyColor: a.bc,
                              padding: 10,
                              callbacks: {
                                  label: (context) => `Score: ${context.parsed.y}%`
                              }
                          }
                      },
                      scales: {
                          y: {
                              min: 0,
                              max: 100,
                              ticks: { color: a.tick, font: { size: 10 }, callback: v => v + '%' },
                              grid: { color: a.grid }
                          },
                          x: {
                              ticks: { color: a.tick, font: { size: 10 } },
                              grid: { display: false }
                          }
                      }
                  }
              });
          }
      }
      
      function onThemeChange() {
          renderCharts();
      }
      
      function exportResults() {
          if (!latestAssessment) {
              alert('No assessment data to export.');
              return;
          }
          
          const report = `CyberShield Assessment Report
=======
    const ctx = document.getElementById('radar-chart').getContext('2d');
    new Chart(ctx, {
        type: 'radar',
        data: {
            labels: ['Password Security', 'Phishing Awareness', 'Device Security', 'Network Security'],
            datasets: [{
                label: 'Your Score',
                data: [
                    latestAssessment.password_score,
                    latestAssessment.phishing_score,
                    latestAssessment.device_score,
                    latestAssessment.network_score
                ],
                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                borderColor: '#3b82f6',
                borderWidth: 2,
                pointBackgroundColor: '#3b82f6'
            }, {
                label: 'Benchmark (Industry Avg)',
                data: [65, 60, 70, 55],
                backgroundColor: 'rgba(139, 92, 246, 0.2)',
                borderColor: '#8b5cf6',
                borderWidth: 2,
                pointBackgroundColor: '#8b5cf6'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { stepSize: 20 }
                }
            }
        }
    });
}

function initProgressChart() {
    if (history.length < 2) return;
    
    const ctx = document.getElementById('progress-chart').getContext('2d');
    const dates = history.map(h => new Date(h.created_at).toLocaleDateString()).reverse();
    const scores = history.map(h => h.score).reverse();
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Assessment Score',
                data: scores,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: (context) => `Score: ${context.raw}%`
                    }
                }
            }
        }
    });
}

function exportResults() {
    if (!latestAssessment) {
        alert('No assessment data to export.');
        return;
    }
    
    const report = `CyberShield Assessment Report
>>>>>>> ffb18a752ab124402d4952ea0483fe767210a8d9
Generated: ${new Date().toLocaleString()}
Vendor: ${latestAssessment.vendor_name}
Overall Score: ${latestAssessment.score}%
Risk Rank: ${latestAssessment.rank}

Category Breakdown:
- Password Security: ${latestAssessment.password_score}%
- Phishing Awareness: ${latestAssessment.phishing_score}%
- Device Security: ${latestAssessment.device_score}%
- Network Security: ${latestAssessment.network_score}%

Recommendations:
${document.querySelector('#recommendations').innerText}

Assessment Date: ${new Date(latestAssessment.created_at).toLocaleString()}`;
<<<<<<< HEAD
          
          const blob = new Blob([report], { type: 'text/plain' });
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `cybershield_report_${new Date().toISOString()}.txt`;
          a.click();
          URL.revokeObjectURL(url);
      }
      
      function startNewAssessment() {
          window.location.href = 'assessment.php';
      }
      
      // Initialize results functionality
      document.addEventListener('DOMContentLoaded', () => {
          renderHistoryTable();
          renderCharts();
      });
    </script>
</body>
</html>
=======
    
    const blob = new Blob([report], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `cybershield_report_${new Date().toISOString()}.txt`;
    a.click();
    URL.revokeObjectURL(url);
}

function startNewAssessment() {
    window.location.href = 'assessment.php';
}

// Draw score circle
function drawScoreCircle() {
    if (!latestAssessment) return;
    
    const circle = document.getElementById('score-circle');
    const score = latestAssessment.score;
    const angle = (score / 100) * 360;
    circle.style.background = `conic-gradient(var(--primary) 0deg ${angle}deg, var(--navy-3) ${angle}deg)`;
}
</script>
>>>>>>> ffb18a752ab124402d4952ea0483fe767210a8d9
