<?php
require_once '../config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Dashboard — CyberShield</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
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
.sb-brand-text .badge{font-family:var(--mono);font-size:.55rem;letter-spacing:1.5px;text-transform:uppercase;background:rgba(255,59,92,.12);color:var(--red);border:1px solid rgba(255,59,92,.2);border-radius:4px;padding:.08rem .38rem;display:inline-block;margin-top:.1rem}
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
.sb-avatar{width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,var(--red),var(--orange));color:#fff;display:grid;place-items:center;font-size:.75rem;font-weight:700;flex-shrink:0;font-family:var(--display)}
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
.tb-admin-av{width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,var(--red),var(--orange));color:#fff;display:grid;place-items:center;font-size:.7rem;font-weight:700;flex-shrink:0;font-family:var(--display)}
.tb-admin-info{display:flex;flex-direction:column}
.tb-admin-name{font-size:.78rem;font-weight:600;line-height:1.2}
.tb-admin-role{font-size:.6rem;color:var(--red);letter-spacing:.5px;font-family:var(--mono)}
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
.sec-hdr{margin-bottom:1.25rem}
.sec-hdr h2{font-family:var(--display);font-size:1.25rem;font-weight:700;letter-spacing:.5px}
.sec-hdr p{font-size:.82rem;color:var(--muted2);margin-top:.2rem}
.card{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow);transition:border-color .18s}
.card:hover{border-color:var(--border2)}
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
.pgn{display:flex;align-items:center;justify-content:flex-end;gap:.4rem;margin-top:1rem}
.pb{min-width:30px;height:30px;border-radius:6px;border:1px solid var(--border2);background:none;font-family:var(--mono);font-size:.72rem;color:var(--muted2);cursor:pointer;display:grid;place-items:center;transition:var(--t)}
.pb:hover,.pb.active{border-color:var(--blue);color:var(--blue);background:rgba(59,139,255,.07)}
.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.42rem .9rem;border-radius:8px;font-family:var(--font);font-size:.78rem;font-weight:600;cursor:pointer;transition:var(--t);border:none;text-decoration:none}
.btn-p{background:var(--blue);color:#fff}.btn-p:hover{background:#2e7ae8}
.btn-s{background:rgba(255,255,255,.05);color:var(--muted2);border:1px solid var(--border2)}.btn-s:hover{border-color:var(--blue);color:var(--text)}
.btn-d{background:rgba(255,59,92,.1);color:var(--red);border:1px solid rgba(255,59,92,.2)}.btn-d:hover{background:rgba(255,59,92,.2)}
.btn-sm{font-size:.72rem;padding:.32rem .7rem}
.sdot{width:7px;height:7px;border-radius:50%;display:inline-block;flex-shrink:0}
.sdot-g{background:var(--green);box-shadow:0 0 6px rgba(16,217,130,.5)}.sdot-y{background:var(--yellow)}.sdot-r{background:var(--red);box-shadow:0 0 6px rgba(255,59,92,.5)}
.mo{position:fixed;inset:0;background:rgba(0,0,0,.6);display:grid;place-items:center;z-index:200;backdrop-filter:blur(4px)}
.mo.hidden{display:none}
.modal{background:var(--bg3);border:1px solid var(--border2);border-radius:14px;width:min(90vw,560px);box-shadow:0 20px 60px rgba(0,0,0,.6);animation:su .2s ease}
@keyframes su{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
.mhdr{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid var(--border)}
.mhdr h3{font-family:var(--display);font-size:1rem;font-weight:700}
.mcl{width:28px;height:28px;border-radius:7px;border:1px solid var(--border2);background:none;color:var(--muted2);cursor:pointer;display:grid;place-items:center;transition:var(--t)}
.mcl:hover{border-color:var(--red);color:var(--red)}
.mbdy{padding:1.25rem}
.ts{position:relative;display:inline-block;width:38px;height:21px;flex-shrink:0}
.ts input{opacity:0;width:0;height:0}
.tsl{position:absolute;inset:0;cursor:pointer;background:rgba(255,255,255,.1);border-radius:21px;transition:var(--t)}
.tsl::before{content:'';position:absolute;height:15px;width:15px;left:3px;bottom:3px;background:var(--muted2);border-radius:50%;transition:var(--t)}
.ts input:checked+.tsl{background:var(--blue)}
.ts input:checked+.tsl::before{transform:translateX(17px);background:#fff}
#toast-c{position:fixed;bottom:1.25rem;right:1.25rem;display:flex;flex-direction:column;gap:.5rem;z-index:300}
.toast{background:var(--bg3);border:1px solid var(--border2);border-radius:9px;padding:.75rem 1rem;font-size:.82rem;box-shadow:var(--shadow);display:flex;align-items:center;gap:.6rem;animation:sl .2s ease;min-width:240px}
@keyframes sl{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:none}}
.ti{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.fi{background:rgba(255,255,255,.04);border:1px solid var(--border2);border-radius:8px;padding:.5rem .85rem;font-family:var(--font);font-size:.82rem;color:var(--text);outline:none;transition:var(--t);width:100%}
.fi:focus{border-color:var(--blue)}.fi[readonly],.fi:disabled{opacity:.6;cursor:not-allowed}
textarea.fi{resize:vertical}
[data-theme=light] .fi{background:#f8fafc}
.fl{font-family:var(--mono);font-size:.62rem;letter-spacing:1px;text-transform:uppercase;color:var(--muted);display:block;margin-bottom:.4rem}
.fg{margin-bottom:.85rem}
.pref-r{display:flex;align-items:center;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--border)}
.pref-r:last-child{border-bottom:none}
</style>
</head>
<body>
<div class="bg-grid"></div>
<div id="app">

  <aside id="sidebar">
    <div class="sb-brand">
      <div class="shield"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
      <div class="sb-brand-text"><h2>CyberShield</h2><span class="badge">Admin Panel</span></div>
      <button class="sb-toggle" onclick="toggleSidebar()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
    </div>
    <div class="sb-section">
      <div class="sb-label">Navigation</div>
      <a class="sb-item active" href="dashboard.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.2"/><rect x="14" y="3" width="7" height="7" rx="1.2"/><rect x="3" y="14" width="7" height="7" rx="1.2"/><rect x="14" y="14" width="7" height="7" rx="1.2"/></svg></span><span class="sb-text">Dashboard</span></a>
<a class="sb-item" href="reports.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span><span class="sb-text">Reports</span></a>
<a class="sb-item" href="users.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><span class="sb-text">Users</span></a>
<a class="sb-item" href="heatmap.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span><span class="sb-text">Risk Heatmap</span></a>
<a class="sb-item" href="activity.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span><span class="sb-text">Activity Log</span></a>
<a class="sb-item" href="settings.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M6 20v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/></svg></span><span class="sb-text">Settings</span></a>
      <div class="sb-divider"></div>
      <div class="sb-label">Tools</div>
      <a class="sb-item" href="compare.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="8" x2="6" y2="8"/><line x1="21" y1="16" x2="3" y2="16"/></svg></span><span class="sb-text">Compare</span></a>
<a class="sb-item" href="forecast.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></span><span class="sb-text">Forecast</span></a>
<a class="sb-item" href="compliance.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span><span class="sb-text">Compliance</span></a>
<a class="sb-item" href="email.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span><span class="sb-text">Email Report</span></a>
      <div class="sb-divider"></div>
      <div class="sb-label">Quick Actions</div>
      <a class="sb-item" onclick="showToast('CSV exported','green')"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></span><span class="sb-text">Export CSV</span></a>
      <a class="sb-item" onclick="showToast('PDF exported','green')"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span><span class="sb-text">Export PDF</span></a>
      <a class="sb-item" onclick="showToast('Data refreshed','blue')"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg></span><span class="sb-text">Refresh Data</span></a>
    </div>
    <div class="sb-footer">
      <div class="sb-user">
        <div class="sb-avatar">A</div>
        <div class="sb-user-info"><p>Admin User</p><span>admin@cybershield.io</span></div>
      </div>
      <button class="btn-sb-logout" onclick="showToast('Signed out','red')">
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
          <span class="tb-title">Dashboard Overview</span>
        </div>
        <p class="tb-sub">Vendor cybersecurity risk summary</p>
      </div>
      <div class="tb-right">
        <div class="tb-search-wrap">
          <span class="tb-search-icon"><svg width="12" height="12" viewBox="0 0 20 20" fill="none"><circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.7"/><path d="M15 15l3 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span>
          <input type="text" class="tb-search" placeholder="Search vendors, scores…" autocomplete="off"/>
        </div>
        <span class="tb-date" id="tb-date"></span>
        <div class="tb-divider"></div>
        <button class="tb-icon-btn" onclick="toggleTheme()" title="Toggle theme">
          <svg id="tmoon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
          <svg id="tsun" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:none"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>
        </button>
        <div class="notif-wrap">
          <button class="tb-icon-btn" onclick="toggleNotif()" title="Alerts" style="position:relative">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="notif-dot" id="notif-dot"></span>
          </button>
          <div class="np hidden" id="np">
            <div class="np-hdr"><span>Alerts</span><button onclick="clearNotifs()">Clear all</button></div>
            <div id="np-list">
              <div class="np-item"><span class="np-dot"></span><span>Apex Corp dropped to rank D</span></div>
              <div class="np-item"><span class="np-dot"></span><span>3 vendors need compliance review</span></div>
            </div>
          </div>
        </div>
        <div class="tb-divider"></div>
        <a class="tb-admin" href="settings.php">
          <div class="tb-admin-av">A</div>
          <div class="tb-admin-info"><span class="tb-admin-name">Admin</span><span class="tb-admin-role">Admin</span></div>
          <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" style="color:var(--muted);margin-left:.2rem"><path d="M4 6l4 4 4-4"/></svg>
        </a>
      </div>
    </div>
    <div class="content">

<div class="sec-hdr"><h2>Dashboard Overview</h2><p>Vendor cybersecurity risk summary across all assessments.</p></div>
<div class="stats-row" id="stats-row">
  <div class="card stat-card" style="--accent:var(--blue)"><div class="si" style="background:rgba(59,139,255,.12);color:var(--blue)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></div><div class="slabel">Total Vendors</div><div class="sval" id="sv-total">—</div><div class="ssub">Registered clients</div></div>
  <div class="card stat-card" style="--accent:var(--teal)"><div class="si" style="background:rgba(0,212,170,.12);color:var(--teal)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div><div class="slabel">Average Score</div><div class="sval" id="sv-avg">—</div><div class="ssub">Platform-wide avg</div></div>
  <div class="card stat-card" style="--accent:var(--green)"><div class="si" style="background:rgba(0,255,148,.12);color:var(--green)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div><div class="slabel">Low Risk (A)</div><div class="sval" id="sv-a" style="color:var(--green)">—</div><div class="ssub">Rank A vendors</div></div>
  <div class="card stat-card" style="--accent:var(--yellow)"><div class="si" style="background:rgba(255,214,10,.1);color:var(--yellow)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><div class="slabel">Moderate (B)</div><div class="sval" id="sv-b" style="color:var(--yellow)">—</div><div class="ssub">Rank B vendors</div></div>
  <div class="card stat-card" style="--accent:var(--orange)"><div class="si" style="background:rgba(255,140,66,.12);color:var(--orange)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div><div class="slabel">High Risk (C)</div><div class="sval" id="sv-c" style="color:var(--orange)">—</div><div class="ssub">Rank C vendors</div></div>
  <div class="card stat-card" style="--accent:var(--red)"><div class="si" style="background:rgba(255,59,92,.12);color:var(--red)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div><div class="slabel">Critical (D)</div><div class="sval" id="sv-d" style="color:var(--red)">—</div><div class="ssub">Rank D vendors</div></div>
</div>
<div class="charts-grid" style="grid-template-columns:1fr 1fr 2fr">
  <div class="card chart-card"><h3>Risk Level Count</h3><div class="cw sm"><canvas id="bar-chart"></canvas></div></div>
  <div class="card chart-card"><h3>Risk Distribution</h3><div class="cw sm"><canvas id="pie-chart"></canvas></div></div>
  <div class="card chart-card"><h3>Score Trend Over Time</h3><div class="cw sm"><canvas id="line-chart"></canvas></div></div>
</div>
<div class="sec-hdr"><h2>Advanced Analytics</h2><p>Aggregated insights across all vendors.</p></div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.9rem;margin-bottom:1.25rem">
  <div class="card analytic-card" style="padding:1rem 1.15rem"><div class="analytic-label" style="font-family:var(--mono);font-size:.58rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted)">Highest Score</div><div style="font-family:var(--display);font-size:1.5rem;font-weight:700;color:var(--green)" id="an-hi">—</div><div style="font-size:.72rem;color:var(--muted2);margin-top:.2rem" id="an-hi-v">—</div></div>
  <div class="card analytic-card" style="padding:1rem 1.15rem"><div class="analytic-label" style="font-family:var(--mono);font-size:.58rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted)">Lowest Score</div><div style="font-family:var(--display);font-size:1.5rem;font-weight:700;color:var(--red)" id="an-lo">—</div><div style="font-size:.72rem;color:var(--muted2);margin-top:.2rem" id="an-lo-v">—</div></div>
  <div class="card analytic-card" style="padding:1rem 1.15rem"><div class="analytic-label" style="font-family:var(--mono);font-size:.58rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted)">Total Assessments</div><div style="font-family:var(--display);font-size:1.5rem;font-weight:700" id="an-tot">—</div><div style="font-size:.72rem;color:var(--muted2);margin-top:.2rem">All time</div></div>
  <div class="card analytic-card" style="padding:1rem 1.15rem"><div class="analytic-label" style="font-family:var(--mono);font-size:.58rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted)">Avg Trend</div><div style="font-family:var(--display);font-size:1.5rem;font-weight:700;color:var(--teal)">+2.4%</div><div style="font-size:.72rem;color:var(--muted2);margin-top:.2rem">Improving overall</div></div>
</div>
<div class="card tbl-card">
  <div class="tbl-bar">
    <h3>Recent Assessments</h3>
    <div class="frow">
      <select class="fsel" id="rank-filter" onchange="renderTbl()"><option value="">All Ranks</option><option value="A">A — Low</option><option value="B">B — Moderate</option><option value="C">C — High</option><option value="D">D — Critical</option></select>
      <button class="btn btn-s btn-sm" onclick="document.getElementById('rank-filter').value='';renderTbl()">Clear</button>
      <button class="btn btn-p btn-sm" onclick="showToast('Exported','green')">⬇ Export</button>
    </div>
  </div>
  <div class="tw"><table><thead><tr><th></th><th>Vendor</th><th>Score</th><th>Rank</th><th>Category</th><th>Date</th><th></th></tr></thead><tbody id="tbl-body"></tbody></table></div>
  <div class="pgn" id="pgn"></div>
</div>

    </div>
  </div>
</div>

<div id="modal-overlay" class="mo hidden" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <div class="mhdr"><h3 id="modal-title">Detail</h3><button class="mcl" onclick="closeModal()"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
    <div class="mbdy" id="modal-body"></div>
  </div>
</div>
<div id="toast-c"></div>
<script>
const MOCK = {
  vendors:[
    {id:1,name:'TechNova Solutions'},{id:2,name:'CloudSafe Inc'},
    {id:3,name:'Apex Corp'},{id:4,name:'DataGuard LLC'},
    {id:5,name:'NetShield Pro'},{id:6,name:'Vertex Systems'},
    {id:7,name:'IronCore Security'},{id:8,name:'BlueSky Tech'},
    {id:9,name:'CipherNet'},{id:10,name:'Quantum Sec'},
    {id:11,name:'SafeNet LLC'},{id:12,name:'TrustArc Inc'}
  ],
  cats:['Access Control','Network Security','Data Encryption','Compliance','Incident Response','Physical Security']
};
MOCK.assessments = Array.from({length:60},(_,i)=>{
  const s=Math.round(Math.random()*78+20);
  const r=s>=80?'A':s>=60?'B':s>=40?'C':'D';
  const v=MOCK.vendors[i%MOCK.vendors.length];
  const d=new Date(2024,Math.floor(Math.random()*14),Math.floor(Math.random()*28)+1);
  return{id:i+1,vid:v.id,vname:v.name,score:s,rank:r,cat:MOCK.cats[i%6],date:d.toISOString().split('T')[0]};
});
MOCK.activity=[
  {type:'export',msg:'Admin exported CSV report',time:'2 min ago'},
  {type:'alert',msg:'Apex Corp dropped to Rank D',time:'15 min ago'},
  {type:'refresh',msg:'Data refreshed manually',time:'32 min ago'},
  {type:'flag',msg:'NetShield Pro flagged for review',time:'1 hr ago'},
  {type:'profile',msg:'Admin profile updated',time:'3 hrs ago'},
  {type:'export',msg:'PDF report downloaded',time:'5 hrs ago'},
  {type:'alert',msg:'Quantum Sec score dropped 12%',time:'8 hrs ago'},
];

function sc(s){return s>=80?'var(--green)':s>=60?'var(--yellow)':s>=40?'var(--orange)':'var(--red)'}
function isDark(){return document.documentElement.getAttribute('data-theme')==='dark'}
function ax(){return isDark()?{tick:'#8898b4',grid:'rgba(59,139,255,.04)',tt:'#0d1421',ttB:'rgba(255,255,255,.1)',tc:'#dde4f0',bc:'#8898b4'}:{tick:'#64748b',grid:'rgba(0,0,0,.06)',tt:'#fff',ttB:'rgba(0,0,0,.1)',tc:'#0f172a',bc:'#475569'}}
const CC={A:{s:'#10D982',b:'rgba(16,217,130,.55)'},B:{s:'#F5B731',b:'rgba(245,183,49,.55)'},C:{s:'#FF7A45',b:'rgba(255,122,69,.55)'},D:{s:'#FF4D6A',b:'rgba(255,77,106,.55)'}};
function riskCounts(){
  const lat={};
  MOCK.assessments.forEach(a=>{if(!lat[a.vid]||a.date>lat[a.vid].date)lat[a.vid]=a;});
  const c={A:0,B:0,C:0,D:0};
  Object.values(lat).forEach(a=>c[a.rank]++);
  return c;
}
function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('collapsed');
  localStorage.setItem('cs_sb',document.getElementById('sidebar').classList.contains('collapsed')?'1':'0');
}
function toggleTheme(){
  const d=!isDark();
  document.documentElement.setAttribute('data-theme',d?'dark':'light');
  localStorage.setItem('cs_th',d?'dark':'light');
  const m=document.getElementById('tmoon'),s=document.getElementById('tsun');
  if(m)m.style.display=d?'':'none';
  if(s)s.style.display=d?'none':'';
  if(typeof onThemeChange==='function')onThemeChange();
}
function toggleNotif(){
  const p=document.getElementById('np');
  if(p)p.classList.toggle('hidden');
}
function clearNotifs(){
  const l=document.getElementById('np-list');
  if(l)l.innerHTML='<p class="np-empty">No alerts</p>';
  const d=document.getElementById('notif-dot');
  if(d)d.style.display='none';
  const p=document.getElementById('np');
  if(p)p.classList.add('hidden');
}
function showToast(msg,color='blue'){
  const cols={blue:'var(--blue)',green:'var(--green)',red:'var(--red)',yellow:'var(--yellow)'};
  const t=document.createElement('div');t.className='toast';
  t.innerHTML=`<span class="ti" style="background:${cols[color]||cols.blue}"></span><span>${msg}</span>`;
  document.getElementById('toast-c').appendChild(t);
  setTimeout(()=>{t.style.opacity='0';t.style.transition='opacity .3s';setTimeout(()=>t.remove(),300);},2500);
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
  if(typeof pageInit==='function')pageInit();
});

let pg=1,PS=8,charts={};
function pageInit(){
  const c=riskCounts(),tot=MOCK.vendors.length;
  const avg=Math.round(MOCK.assessments.reduce((a,b)=>a+b.score,0)/MOCK.assessments.length);
  document.getElementById('sv-total').textContent=tot;
  document.getElementById('sv-avg').textContent=avg+'%';
  document.getElementById('sv-a').textContent=c.A;
  document.getElementById('sv-b').textContent=c.B;
  document.getElementById('sv-c').textContent=c.C;
  document.getElementById('sv-d').textContent=c.D;
  const hi=MOCK.assessments.reduce((a,b)=>b.score>a.score?b:a);
  const lo=MOCK.assessments.reduce((a,b)=>b.score<a.score?b:a);
  document.getElementById('an-hi').textContent=hi.score+'%';
  document.getElementById('an-hi-v').textContent=hi.vname;
  document.getElementById('an-lo').textContent=lo.score+'%';
  document.getElementById('an-lo-v').textContent=lo.vname;
  document.getElementById('an-tot').textContent=MOCK.assessments.length;
  renderTbl(); renderCharts();
}
function renderTbl(){
  const f=document.getElementById('rank-filter').value;
  let d=MOCK.assessments;
  if(f)d=d.filter(a=>a.rank===f);
  const tp=Math.ceil(d.length/PS);
  if(pg>tp)pg=1;
  const sl=d.slice((pg-1)*PS,pg*PS);
  document.getElementById('tbl-body').innerHTML=sl.map(a=>`
    <tr onclick="openModal(${a.id})" style="cursor:pointer">
      <td><input type="checkbox" onclick="event.stopPropagation()"></td>
      <td style="font-weight:600">${a.vname}</td>
      <td><div class="sbw"><div class="sbb"><div class="sbf" style="width:${a.score}%;background:${sc(a.score)}"></div></div><span class="sbn">${a.score}%</span></div></td>
      <td><span class="rank r${a.rank}">${a.rank}</span></td>
      <td style="color:var(--muted2);font-size:.78rem">${a.cat}</td>
      <td style="color:var(--muted2);font-family:var(--mono);font-size:.72rem">${a.date}</td>
      <td><button class="btn btn-s btn-sm" onclick="event.stopPropagation();openModal(${a.id})">View</button></td>
    </tr>`).join('');
  let ph='';for(let i=1;i<=tp;i++)ph+=`<button class="pb ${i===pg?'active':''}" onclick="pg=${i};renderTbl()">${i}</button>`;
  document.getElementById('pgn').innerHTML=ph;
}
function openModal(id){
  const a=MOCK.assessments.find(x=>x.id===id);
  if(!a)return;
  document.getElementById('modal-title').textContent=a.vname;
  document.getElementById('modal-body').innerHTML=`
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.85rem;margin-bottom:1rem">
      <div style="padding:.75rem;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:8px"><div style="font-family:var(--mono);font-size:.58rem;letter-spacing:1px;text-transform:uppercase;color:var(--muted)">Score</div><div style="font-family:var(--display);font-size:1.5rem;font-weight:700;color:${sc(a.score)}">${a.score}%</div></div>
      <div style="padding:.75rem;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:8px"><div style="font-family:var(--mono);font-size:.58rem;letter-spacing:1px;text-transform:uppercase;color:var(--muted)">Rank</div><div style="margin-top:.4rem"><span class="rank r${a.rank}">${a.rank}</span></div></div>
    </div>
    <div style="padding:.85rem;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:8px;font-size:.8rem;color:var(--muted2)">Category: <b style="color:var(--text)">${a.cat}</b> &nbsp;|&nbsp; Date: <b style="color:var(--text)">${a.date}</b>${a.rank==='D'?'<br><br><span style="color:var(--red);font-weight:600">⚠ Critical — immediate action required.</span>':''}</div>`;
  document.getElementById('modal-overlay').classList.remove('hidden');
}
function renderCharts(){
  const c=riskCounts(),a=ax();
  if(charts.bar)charts.bar.destroy();
  if(charts.pie)charts.pie.destroy();
  if(charts.line)charts.line.destroy();
  const bCtx=document.getElementById('bar-chart');
  if(bCtx)charts.bar=new Chart(bCtx,{type:'bar',data:{labels:['A','B','C','D'],datasets:[{data:[c.A,c.B,c.C,c.D],backgroundColor:[CC.A.b,CC.B.b,CC.C.b,CC.D.b],borderColor:[CC.A.s,CC.B.s,CC.C.s,CC.D.s],borderWidth:2,borderRadius:5,borderSkipped:false}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{backgroundColor:a.tt,borderColor:a.ttB,borderWidth:1,titleColor:a.tc,bodyColor:a.bc,padding:10}},scales:{y:{beginAtZero:true,ticks:{stepSize:1,color:a.tick,font:{size:10}},grid:{color:a.grid}},x:{ticks:{color:a.tick,font:{size:10}},grid:{display:false}}}}});
  const pCtx=document.getElementById('pie-chart');
  if(pCtx)charts.pie=new Chart(pCtx,{type:'doughnut',data:{labels:['A','B','C','D'],datasets:[{data:[c.A,c.B,c.C,c.D],backgroundColor:[CC.A.s,CC.B.s,CC.C.s,CC.D.s],borderWidth:2,borderColor:isDark()?'#030508':'#fff',hoverOffset:5}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{font:{size:9},padding:10,color:a.tick}},tooltip:{backgroundColor:a.tt,borderColor:a.ttB,borderWidth:1,titleColor:a.tc,bodyColor:a.bc}}}});
  const byM={};MOCK.assessments.forEach(x=>{const k=x.date.slice(0,7);if(!byM[k])byM[k]=[];byM[k].push(x.score);});
  const keys=Object.keys(byM).sort().slice(-8);
  const vals=keys.map(k=>Math.round(byM[k].reduce((a,b)=>a+b,0)/byM[k].length));
  const lCtx=document.getElementById('line-chart');
  if(lCtx)charts.line=new Chart(lCtx,{type:'line',data:{labels:keys.map(k=>{const[y,m]=k.split('-');return new Date(y,m-1).toLocaleDateString('en-US',{month:'short',year:'2-digit'});}),datasets:[{label:'Avg Score',data:vals,borderColor:'#7B72F0',backgroundColor:isDark()?'rgba(91,79,232,.1)':'rgba(91,79,232,.12)',fill:true,tension:.4,pointBackgroundColor:'#7B72F0',pointBorderColor:isDark()?'#030508':'#fff',pointBorderWidth:2,pointRadius:4,pointHoverRadius:6}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{backgroundColor:a.tt,borderColor:a.ttB,borderWidth:1,titleColor:a.tc,bodyColor:a.bc,padding:10}},scales:{y:{min:0,max:100,ticks:{color:a.tick,font:{size:10},callback:v=>v+'%'},grid:{color:a.grid}},x:{ticks:{color:a.tick,font:{size:10}},grid:{display:false}}}}});
}
function onThemeChange(){renderCharts();}
</script>
</body>
</html>