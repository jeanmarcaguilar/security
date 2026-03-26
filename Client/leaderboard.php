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

// Get leaderboard data
$leaderboard_query = "SELECT v.name as vendor_name, v.store_name, 
    va.score, va.rank, va.password_score, va.phishing_score, 
    va.device_score, va.network_score, va.created_at
    FROM vendor_assessments va
    JOIN vendors v ON va.vendor_id = v.id
    WHERE va.id IN (SELECT MAX(id) FROM vendor_assessments GROUP BY vendor_id)
    ORDER BY va.score DESC";
$stmt = $db->prepare($leaderboard_query);
$stmt->execute();
$leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Leaderboard — CyberShield</title>
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

/* Leaderboard-specific styles */
.leaderboard-hero{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:2rem;
  padding:2.5rem;
  background:linear-gradient(135deg,var(--card-bg) 0%,var(--bg2) 100%);
  border-radius:24px;
  border:1px solid var(--border);
  margin-bottom:3rem;
  position:relative;
  overflow:hidden;
}

.leaderboard-hero::before{
  content:'';
  position:absolute;
  top:0;
  left:0;
  right:0;
  bottom:0;
  background:radial-gradient(circle at 20% 80%,rgba(59,139,255,.1) 0%,transparent 50%),radial-gradient(circle at 80% 20%,rgba(123,114,240,.1) 0%,transparent 50%);
  pointer-events:none;
}

.hero-content{
  flex:1;
  z-index:1;
}

.hero-stats{
  display:flex;
  gap:2rem;
  margin-top:1.5rem;
}

.hero-stat{
  display:flex;
  flex-direction:column;
  align-items:center;
  padding:1rem 1.5rem;
  background:rgba(255,255,255,.05);
  border-radius:12px;
  border:1px solid rgba(255,255,255,.1);
  backdrop-filter:blur(10px);
}

.hero-number{
  font-size:2rem;
  font-weight:800;
  color:var(--blue);
  line-height:1;
}

.hero-label{
  font-size:.85rem;
  color:var(--muted);
  margin-top:.25rem;
  text-transform:uppercase;
  letter-spacing:.5px;
}

.hero-trophy{
  position:relative;
  z-index:1;
}

.trophy-container{
  position:relative;
  width:120px;
  height:120px;
  display:flex;
  align-items:center;
  justify-content:center;
}

.trophy-icon{
  font-size:4rem;
  z-index:2;
  position:relative;
  animation:float 3s ease-in-out infinite;
}

.trophy-glow{
  position:absolute;
  top:50%;
  left:50%;
  transform:translate(-50%,-50%);
  width:100px;
  height:100px;
  background:radial-gradient(circle,rgba(255,215,0,.3) 0%,transparent 70%);
  border-radius:50%;
  animation:pulse 2s ease-in-out infinite;
}

@keyframes float{
  0%,100%{transform:translateY(0px);}
  50%{transform:translateY(-10px);}
}

@keyframes pulse{
  0%,100%{opacity:.5;transform:translate(-50%,-50%) scale(1);}
  50%{opacity:1;transform:translate(-50%,-50%) scale(1.1);}
}

.stats-grid.enhanced{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
  gap:2rem;
  margin-bottom:3rem;
}

.stat-card.enhanced{
  background:var(--card-bg);
  border:1px solid var(--border);
  border-radius:16px;
  padding:1.5rem;
  display:flex;
  align-items:center;
  gap:1rem;
  position:relative;
  overflow:hidden;
  transition:all .3s ease;
}

.stat-card.enhanced:hover{
  transform:translateY(-4px);
  box-shadow:0 12px 32px rgba(0,0,0,.15);
  border-color:var(--blue);
}

.stat-card.enhanced::before{
  content:'';
  position:absolute;
  top:0;
  left:0;
  right:0;
  height:3px;
  background:linear-gradient(90deg,var(--blue),var(--purple));
}

.stat-icon{
  font-size:2rem;
  width:60px;
  height:60px;
  display:flex;
  align-items:center;
  justify-content:center;
  background:rgba(59,139,255,.1);
  border-radius:12px;
  flex-shrink:0;
}

.stat-content{
  flex:1;
}

.stat-number{
  font-size:1.8rem;
  font-weight:700;
  color:var(--text);
  line-height:1;
}

.stat-label{
  font-size:.78rem;
  color:var(--muted);
  margin-top:.25rem;
  text-transform:uppercase;
  letter-spacing:.5px;
}

.filter-header{
  display:flex;
  align-items:center;
  justify-content:space-between;
  margin-bottom:2rem;
}

.filter-title{
  font-family:var(--display);
  font-size:1.2rem;
  font-weight:700;
  letter-spacing:1px;
}

.filter-actions{
  display:flex;
  gap:0.75rem;
}

.export-btn{
  display:flex;
  align-items:center;
  gap:.5rem;
  padding:.75rem 1.25rem;
  background:var(--blue);
  color:white;
  border:none;
  border-radius:10px;
  font-weight:600;
  cursor:pointer;
  transition:all .3s ease;
  font-size:.9rem;
}

.export-btn:hover{
  background:var(--purple);
  transform:translateY(-2px);
}

.filter-buttons.enhanced{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
  gap:1.5rem;
  margin-bottom:3rem;
}

.filter-btn{
  display:flex;
  align-items:center;
  gap:.75rem;
  padding:1rem 1.25rem;
  background:rgba(59,139,255,.05);
  border:2px solid var(--border);
  border-radius:12px;
  cursor:pointer;
  transition:all .3s ease;
  font-weight:600;
  color:var(--text);
  position:relative;
  overflow:hidden;
}

.filter-btn:hover{
  background:rgba(59,139,255,.1);
  border-color:var(--blue);
  transform:translateY(-2px);
}

.filter-btn.active{
  background:linear-gradient(135deg,var(--blue),var(--purple));
  color:white;
  border-color:transparent;
  box-shadow:0 8px 24px rgba(59,139,255,.3);
}

.filter-icon{
  font-size:1.2rem;
}

.filter-text{
  flex:1;
  text-align:left;
}

.filter-count{
  background:rgba(255,255,255,.2);
  padding:.25rem .5rem;
  border-radius:20px;
  font-size:.8rem;
  font-weight:700;
  min-width:24px;
  text-align:center;
}

.filter-btn:not(.active) .filter-count{
  background:var(--border);
  color:var(--muted);
}

.leaderboard-table{
  width:100%;
  border-collapse:separate;
  border-spacing:0;
  background:var(--card-bg);
  border-radius:16px;
  overflow:hidden;
  border:1px solid var(--border);
}

.leaderboard-table thead{
  background:linear-gradient(135deg,rgba(59,139,255,.1),rgba(123,114,240,.1));
}

.leaderboard-table th{
  padding:1.25rem 1rem;
  text-align:left;
  font-weight:700;
  color:var(--text);
  font-size:.9rem;
  text-transform:uppercase;
  letter-spacing:.5px;
}

.leaderboard-table td{
  padding:1rem;
  border-bottom:1px solid var(--border);
  font-size:.88rem;
}

.leaderboard-table tbody tr:hover{
  background:rgba(59,139,255,.05);
}

.rank-badge{
  display:inline-block;
  padding:.25rem .5rem;
  border-radius:6px;
  font-family:var(--mono);
  font-size:.7rem;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:1px;
}

.rank-a{background:rgba(16,217,130,.15);color:var(--green)}
.rank-b{background:rgba(245,183,49,.15);color:var(--yellow)}
.rank-c{background:rgba(255,140,66,.15);color:var(--orange)}
.rank-d{background:rgba(255,59,92,.15);color:var(--red)}

.vendor-name{
  font-weight:600;
  color:var(--text);
}

.store-name{
  font-size:.78rem;
  color:var(--muted2);
  margin-top:.25rem;
}

.score-display{
  display:flex;
  align-items:center;
  gap:.5rem;
}

.score-bar{
  width:100px;
  height:8px;
  background:var(--border);
  border-radius:4px;
  overflow:hidden;
}

.score-fill{
  height:100%;
  border-radius:4px;
  transition:width .3s ease;
}

.score-text{
  font-family:var(--mono);
  font-size:.85rem;
  font-weight:600;
  min-width:45px;
  text-align:right;
}

.category-scores{
  display:flex;
  gap:.5rem;
  font-size:.75rem;
  color:var(--muted2);
}

.category-score{
  display:flex;
  align-items:center;
  gap:.25rem;
}

.category-icon{
  font-size:.8rem;
}

.date{
  font-family:var(--mono);
  font-size:.7rem;
  color:var(--muted2);
}

@media (max-width:768px){
  .leaderboard-hero{
    flex-direction:column;
    text-align:center;
    gap:1.5rem;
  }
  .hero-stats{
    justify-content:center;
    gap:1rem;
  }
  .hero-stat{
    padding:.75rem 1rem;
  }
  .stats-grid.enhanced{
    grid-template-columns:1fr;
  }
  .filter-buttons.enhanced{
    grid-template-columns:1fr;
  }
  .filter-header{
    flex-direction:column;
    gap:1rem;
    align-items:stretch;
  }
  .trophy-container{
    width:80px;
    height:80px;
  }
  .trophy-icon{
    font-size:3rem;
}
</style>
</head>
<body>
<div class="bg-grid"></div>
<div id="app">

  <!-- SIDEBAR -->
  <div id="sidebar">
    <div class="sb-brand">
      <div class="shield">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <div class="sb-brand-text">
        <h2>CyberShield</h2>
        <span class="badge">Client Portal</span>
      </div>
      <button class="sb-toggle" onclick="toggleSidebar()">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      </button>
    </div>
    <div class="sb-section">
      <div class="sb-label">Navigation</div>
      <a class="sb-item" href="index.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.2"/><rect x="14" y="3" width="7" height="7" rx="1.2"/><rect x="3" y="14" width="7" height="7" rx="1.2"/><rect x="14" y="14" width="7" height="7" rx="1.2"/></svg></span><span class="sb-text">Dashboard</span></a>
      <a class="sb-item" href="assessment.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span><span class="sb-text">Assessment</span></a>
      <a class="sb-item" href="result.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span><span class="sb-text">Results</span></a>
      <a class="sb-item active" href="leaderboard.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6l4-4 4 4"/><path d="M12 2v13"/><path d="M20 21H4"/><path d="M17 12h3v9"/><path d="M4 12h3v9"/></svg></span><span class="sb-text">Leaderboard</span></a>
      <div class="sb-divider"></div>
      <div class="sb-label">Seller Hub</div>
      <a class="sb-item" href="seller-store.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1 0 8 0"/></svg></span><span class="sb-text">My Store</span></a>
      <a class="sb-item" href="seller-analytics.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><polyline points="2 20 22 20"/></svg></span><span class="sb-text">Analytics</span></a>
      <div class="sb-divider"></div>
      <div class="sb-label">Account</div>
      <a class="sb-item" href="profile.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span><span class="sb-text">My Profile</span></a>
      <a class="sb-item" href="security-tips.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span><span class="sb-text">Security Tips</span></a>
      <a class="sb-item active" href="terms.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span><span class="sb-text">Terms & Privacy</span></a>
    </div>
    <div class="sb-footer">
      <div class="sb-user">
        <div class="sb-avatar"><?php echo strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 1)); ?></div>
        <div class="sb-user-info">
          <p><?php echo htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'User'); ?></p>
          <span>Vendor Account</span>
        </div>
      </div>
      <button class="btn-sb-logout" onclick="doLogout()">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y1="12"/></svg>
        <span>Sign Out</span>
      </button>
    </div>
  </div>

  <!-- MAIN -->
  <div id="main">
    <!-- TOPBAR -->
    <div class="topbar">
      <div class="tb-bc">
        <div>
          <div class="tb-title">Leaderboard</div>
          <div class="tb-sub">Security Rankings & Performance</div>
        </div>
      </div>
      <div class="tb-right">
        <div class="tb-search-wrap">
          <svg class="tb-search-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" class="tb-search" placeholder="Search vendors..." id="search-input" oninput="searchVendors(this.value)"/>
        </div>
        <div class="tb-divider"></div>
        <div class="tb-date" id="tb-date"></div>
        <div class="tb-divider"></div>
        <button class="tb-icon-btn" id="tmoon" onclick="toggleTheme()" title="Toggle theme">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <button class="tb-icon-btn" id="tsun" onclick="toggleTheme()" title="Toggle theme" style="display:none">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>
        </button>
        <div class="tb-divider"></div>
        <div class="notif-wrap">
          <button class="tb-icon-btn" onclick="toggleNotif()" title="Notifications">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="notif-dot" id="notif-dot" style="display:none"></span>
          </button>
          <div class="np hidden" id="np">
            <div class="np-hdr"><span>Notifications</span><button onclick="clearNotifs()">Clear all</button></div>
            <div id="np-list"><p class="np-empty">No alerts</p></div>
          </div>
        </div>
        <div class="tb-divider"></div>
        <div class="tb-admin" onclick="window.location.href='profile.php'">
          <div class="tb-admin-av"><?php echo strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 1)); ?></div>
          <div class="tb-admin-info">
            <div class="tb-admin-name"><?php echo htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'User'); ?></div>
            <div class="tb-admin-role">Vendor</div>
          </div>
          <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6l4 4-4 4"/></svg>
        </div>
      </div>
    </div>

    <!-- CONTENT -->
    <div class="content">
      <div class="page-inner fade-in">
        
        <!-- Hero Section -->
        <div class="leaderboard-header">
          <div class="leaderboard-hero">
            <div class="hero-content">
              <h1 style="margin-bottom: 0.5rem; font-size: 2.5rem; font-weight: 800; background: linear-gradient(135deg, #3B8BFF, #7B72F0); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">🏆 Security Champions</h1>
              <p style="font-size: 1.1rem; opacity: 0.9;">Top vendors with exceptional cybersecurity practices</p>
              <div class="hero-stats">
                <div class="hero-stat">
                  <span class="hero-number"><?php echo count($leaderboard); ?></span>
                  <span class="hero-label">Vendors</span>
                </div>
                <div class="hero-stat">
                  <span class="hero-number">
                    <?php
                    $avgScore = array_sum(array_column($leaderboard, 'score')) / max(1, count($leaderboard));
                    echo round($avgScore, 0);
                    ?>%
                  </span>
                  <span class="hero-label">Avg Score</span>
                </div>
                <div class="hero-stat">
                  <span class="hero-number">
                    <?php
                    $aCount = count(array_filter($leaderboard, fn($v) => $v['rank'] === 'A'));
                    echo $aCount;
                    ?>
                  </span>
                  <span class="hero-label">Low Risk</span>
                </div>
              </div>
            </div>
            <div class="hero-trophy">
              <div class="trophy-container">
                <div class="trophy-icon">🏆</div>
                <div class="trophy-glow"></div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Enhanced Statistics -->
        <div class="stats-grid enhanced">
          <div class="stat-card enhanced">
            <div class="stat-icon">👥</div>
            <div class="stat-content">
              <div class="stat-value"><?php echo count($leaderboard); ?></div>
              <div class="stat-label">Total Vendors</div>
            </div>
            <div class="stat-trend">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10D982" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            </div>
          </div>
          <div class="stat-card enhanced">
            <div class="stat-icon">📊</div>
            <div class="stat-content">
              <div class="stat-value">
                <?php
                $avgScore = array_sum(array_column($leaderboard, 'score')) / max(1, count($leaderboard));
                echo round($avgScore, 1) . '%';
                ?>
              </div>
              <div class="stat-label">Average Score</div>
            </div>
            <div class="stat-trend">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10D982" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            </div>
          </div>
          <div class="stat-card enhanced">
            <div class="stat-icon">⭐</div>
            <div class="stat-content">
              <div class="stat-value">
                <?php
                $topScore = !empty($leaderboard) ? $leaderboard[0]['score'] : 0;
                echo $topScore . '%';
                ?>
              </div>
              <div class="stat-label">Top Score</div>
            </div>
            <div class="stat-trend">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#F5B731" stroke-width="2"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg>
            </div>
          </div>
          <div class="stat-card enhanced">
            <div class="stat-icon">🛡️</div>
            <div class="stat-content">
              <div class="stat-value">
                <?php
                $aCount = count(array_filter($leaderboard, fn($v) => $v['rank'] === 'A'));
                echo $aCount;
                ?>
              </div>
              <div class="stat-label">Low Risk (A)</div>
            </div>
            <div class="stat-trend">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10D982" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            </div>
          </div>
        </div>
        
        <!-- Enhanced Filter Buttons -->
        <div class="filter-section">
          <div class="filter-header">
            <h3>Filter by Risk Level</h3>
            <button class="export-btn" onclick="exportLeaderboard()">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              Export CSV
            </button>
          </div>
          <div class="filter-buttons enhanced">
            <button class="filter-btn active" onclick="filterLeaderboard('all', this)">
              <span class="filter-icon">🌐</span>
              <span class="filter-text">All Vendors</span>
              <span class="filter-count"><?php echo count($leaderboard); ?></span>
            </button>
            <button class="filter-btn" onclick="filterLeaderboard('A', this)">
              <span class="filter-icon">🏆</span>
              <span class="filter-text">Low Risk</span>
              <span class="filter-count"><?php echo count(array_filter($leaderboard, fn($v) => $v['rank'] === 'A')); ?></span>
            </button>
            <button class="filter-btn" onclick="filterLeaderboard('B', this)">
              <span class="filter-icon">⭐</span>
              <span class="filter-text">Moderate</span>
              <span class="filter-count"><?php echo count(array_filter($leaderboard, fn($v) => $v['rank'] === 'B')); ?></span>
            </button>
            <button class="filter-btn" onclick="filterLeaderboard('C', this)">
              <span class="filter-icon">⚠️</span>
              <span class="filter-text">High Risk</span>
              <span class="filter-count"><?php echo count(array_filter($leaderboard, fn($v) => $v['rank'] === 'C')); ?></span>
            </button>
          </div>
        </div>
                    
        <div style="overflow-x: auto;">
          <table class="leaderboard-table" id="leaderboard-table">
            <thead>
              <tr>
                <th>Rank</th>
                <th>Vendor</th>
                <th>Overall Score</th>
                <th>Risk Level</th>
                <th>Password</th>
                <th>Phishing</th>
                <th>Device</th>
                <th>Network</th>
                <th>Last Assessment</th>
              </tr>
            </thead>
            <tbody id="leaderboard-body">
              <?php foreach ($leaderboard as $index => $vendor): ?>
              <tr data-rank="<?php echo $vendor['rank']; ?>" 
                  data-score="<?php echo $vendor['score']; ?>"
                  <?php if (isset($vendor['store_name']) && $vendor['store_name'] === $user['store_name']): ?>class="current-user-row"<?php endif; ?>>
                  <td>
                    <?php if ($index === 0): ?>
                        <span class="medal">🥇</span> 1st
                    <?php elseif ($index === 1): ?>
                        <span class="medal">🥈</span> 2nd
                    <?php elseif ($index === 2): ?>
                        <span class="medal">🥉</span> 3rd
                    <?php else: ?>
                        <?php echo $index + 1; ?>th
                    <?php endif; ?>
                  </td>
                  <td><strong><?php echo htmlspecialchars($vendor['vendor_name']); ?></strong></td>
                  <td><span style="font-size: 1.2rem; font-weight: 700;"><?php echo $vendor['score']; ?>%</span></td>
                  <td><span class="rank-badge rank-<?php echo strtolower($vendor['rank']); ?>"><?php echo $vendor['rank']; ?></span></td>
                  <td><?php echo $vendor['password_score']; ?>%</td>
                  <td><?php echo $vendor['phishing_score']; ?>%</td>
                  <td><?php echo $vendor['device_score']; ?>%</td>
                  <td><?php echo $vendor['network_score']; ?>%</td>
                  <td><?php echo date('M j, Y', strtotime($vendor['created_at'])); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div><!-- end main-content -->
</div><!-- end app -->

<script>
let currentFilter = 'all';

function filterLeaderboard(rank, btn) {
    currentFilter = rank;
    
    // Update active button
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    
    const rows = document.querySelectorAll('#leaderboard-body tr');
    rows.forEach(row => {
        const rowRank = row.dataset.rank;
        if (rank === 'all' || rowRank === rank) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function exportLeaderboard() {
    let csv = "Rank,Vendor,Score,Rank,Password,Phishing,Device,Network,Last Assessment\n";
    const rows = document.querySelectorAll('#leaderboard-body tr');
    let visibleRank = 1;
    
    rows.forEach((row, index) => {
        if (row.style.display !== 'none') {
            const cells = row.querySelectorAll('td');
            csv += `${visibleRank},"${cells[1]?.innerText.replace(/"/g, '""') || ''}",${cells[2]?.innerText || ''},${cells[3]?.innerText || ''},${cells[4]?.innerText || ''},${cells[5]?.innerText || ''},${cells[6]?.innerText || ''},${cells[7]?.innerText || ''},${cells[8]?.innerText || ''}\n`;
            visibleRank++;
        }
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `leaderboard_${new Date().toISOString()}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

function toggleTheme() {
    const html = document.documentElement;
    const current = html.getAttribute('data-theme');
    html.setAttribute('data-theme', current === 'dark' ? 'light' : 'dark');
}

// Admin dashboard JavaScript functions
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
</script>

<div id="modal-overlay" class="mo hidden" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <div class="mhdr"><h3 id="modal-title">Confirm Action</h3><button class="mcl" onclick="closeModal()"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
    <div class="mbdy" id="modal-body"></div>
  </div>
</div>
<div id="toast-c"></div>
</body>
</html>