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

$products_query = "SELECT * FROM products WHERE user_id = :user_id";
$stmt = $db->prepare($products_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalValue    = array_sum(array_map(fn($p) => $p['price'] * $p['stock'], $products));
$totalStock    = array_sum(array_column($products, 'stock'));
$activeCount   = count(array_filter($products, fn($p) => $p['status'] === 'active'));
$lowStockCount = count(array_filter($products, fn($p) => $p['stock'] < 10));
$avgPrice      = count($products) > 0 ? array_sum(array_column($products, 'price')) / count($products) : 0;

$categories = [];
foreach ($products as $p) {
    $cat = $p['category'] ?? 'Other';
    if (!isset($categories[$cat])) $categories[$cat] = ['count' => 0, 'value' => 0];
    $categories[$cat]['count']++;
    $categories[$cat]['value'] += $p['price'] * $p['stock'];
}
arsort($categories);
$topCategory = array_key_first($categories) ?? 'N/A';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Analytics — CyberShield</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<link rel="stylesheet" href="style.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
@import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&display=swap');
@import url('https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&display=swap');

    :root {
      --font: 'Inter', sans-serif;
      --display: 'Syne', sans-serif;
      --mono: 'JetBrains Mono', monospace;
      --blue:#3B8BFF;
      --purple:#7B72F0;
      --teal:#00D4AA;
      --green:#10D982;
      --yellow:#F5B731;
      --orange:#FF8C42;
      --red:#FF3B5C;
      --t:.18s ease
    }
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
.np-hdr{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;border-bottom:1px solid var(--border)}
.np-hdr button{font-size:.72rem;color:var(--muted2);background:none;border:none;cursor:pointer}
.np-empty{font-size:.8rem;color:var(--muted2);padding:1rem;text-align:center}
.content{flex:1;overflow-y:auto;padding:1.5rem}
.content::-webkit-scrollbar{width:4px}.content::-webkit-scrollbar-thumb{background:var(--border2);border-radius:2px}
.mo{position:fixed;inset:0;background:rgba(0,0,0,.65);display:grid;place-items:center;z-index:200;backdrop-filter:blur(6px);opacity:0;pointer-events:none;transition:opacity .25s ease}
.mo.active{opacity:1;pointer-events:all}
.mo .modal{background:var(--bg3);border:1px solid var(--border2);border-radius:16px;width:min(90vw,560px);box-shadow:0 24px 80px rgba(0,0,0,.7);transform:translateY(24px) scale(.97);opacity:0;transition:transform .28s cubic-bezier(.34,1.46,.64,1),opacity .22s ease}
.mo.active .modal{transform:none;opacity:1}
.mhdr{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid var(--border)}
.mhdr h3{font-family:var(--display);font-size:1rem;font-weight:700}
.mcl{width:28px;height:28px;border-radius:7px;border:1px solid var(--border2);background:none;color:var(--muted2);cursor:pointer;display:grid;place-items:center;transition:var(--t)}
.mcl:hover{border-color:var(--red);color:var(--red)}
.mbdy{padding:1.25rem}
#toast-c{position:fixed;bottom:1.25rem;right:1.25rem;display:flex;flex-direction:column;gap:.5rem;z-index:300}
.toast{background:var(--bg3);border:1px solid var(--border2);border-radius:9px;padding:.75rem 1rem;font-size:.82rem;box-shadow:var(--shadow);display:flex;align-items:center;gap:.6rem;animation:sl .2s ease;min-width:240px}
@keyframes sl{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:none}}
.ti{width:8px;height:8px;border-radius:50%;flex-shrink:0}

/* ── Analytics layout ── */
.analytics-hero{display:flex;align-items:center;padding:2.5rem;background:linear-gradient(135deg,var(--card-bg) 0%,var(--bg2) 100%);border-radius:24px;border:1px solid var(--border);margin-bottom:2rem;position:relative;overflow:hidden;width:100%}
.analytics-hero::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 15% 80%,rgba(59,139,255,.07) 0%,transparent 50%),radial-gradient(circle at 85% 20%,rgba(123,114,240,.07) 0%,transparent 50%);pointer-events:none}
.hero-content{flex:1;z-index:1}
.hero-title{font-family:var(--display);font-size:2.2rem;font-weight:800;background:linear-gradient(135deg,var(--blue),var(--purple));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:.4rem}
.hero-sub{font-size:.95rem;color:var(--muted2);margin-bottom:1.5rem}
.hero-stats{display:flex;gap:1rem;flex-wrap:wrap}
.hero-stat{display:flex;flex-direction:column;align-items:center;padding:.9rem 1.3rem;background:rgba(255,255,255,.04);border-radius:12px;border:1px solid var(--border2);min-width:100px}
.hero-number{font-family:var(--display);font-size:1.7rem;font-weight:800;color:var(--blue);line-height:1}
.hero-label{font-family:var(--mono);font-size:.6rem;letter-spacing:1.2px;text-transform:uppercase;color:var(--muted2);margin-top:.3rem}

.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.2rem;margin-bottom:2rem}
.kpi-card{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:1.4rem 1.5rem;position:relative;overflow:hidden;transition:all .25s ease}
.kpi-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--accent,var(--blue))}
.kpi-card:hover{transform:translateY(-3px);box-shadow:0 12px 32px rgba(0,0,0,.25);border-color:rgba(59,139,255,.2)}
.kpi-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:.8rem}
.kpi-icon{width:38px;height:38px;border-radius:10px;background:rgba(59,139,255,.08);display:grid;place-items:center}
.kpi-badge{font-family:var(--mono);font-size:.65rem;font-weight:600;padding:.18rem .5rem;border-radius:6px}
.badge-up{background:rgba(16,217,130,.12);color:var(--green)}
.badge-down{background:rgba(255,59,92,.12);color:var(--red)}
.badge-neutral{background:rgba(245,183,49,.1);color:var(--yellow)}
.kpi-value{font-family:var(--display);font-size:1.85rem;font-weight:800;line-height:1;margin-bottom:.22rem}
.kpi-label{font-family:var(--mono);font-size:.63rem;letter-spacing:1px;text-transform:uppercase;color:var(--muted2)}

.charts-grid{display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;margin-bottom:1.5rem}
@media(max-width:960px){.charts-grid{grid-template-columns:1fr}}
.chart-card{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:1.4rem;transition:border-color .18s}
.chart-card:hover{border-color:var(--border2)}
.chart-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem}
.chart-hdr h3{font-family:var(--display);font-size:.95rem;font-weight:700;letter-spacing:.4px}
.period-btns{display:flex;gap:.35rem}
.period-btn{font-family:var(--mono);font-size:.63rem;letter-spacing:.5px;padding:.25rem .6rem;border-radius:6px;border:1px solid var(--border2);background:rgba(255,255,255,.03);color:var(--muted2);cursor:pointer;transition:var(--t)}
.period-btn:hover{border-color:var(--blue);color:var(--text)}
.period-btn.active{background:var(--blue);color:#fff;border-color:var(--blue)}
.chart-wrap{position:relative;height:250px}

.bottom-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem}
@media(max-width:860px){.bottom-grid{grid-template-columns:1fr}}

.top-product-row{display:flex;align-items:center;gap:.85rem;padding:.7rem 0;border-bottom:1px solid var(--border)}
.top-product-row:last-child{border-bottom:none}
.top-rank{font-family:var(--mono);font-size:.7rem;color:var(--muted);width:18px;flex-shrink:0;text-align:center}
.top-info{flex:1;min-width:0}
.top-name{font-size:.82rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.top-meta{font-size:.7rem;color:var(--muted2)}
.top-val{font-family:var(--mono);font-size:.8rem;font-weight:600;color:var(--blue);text-align:right;flex-shrink:0}

.inv-alert{display:flex;align-items:flex-start;gap:.7rem;padding:.9rem 1rem;border-radius:10px;margin-bottom:.65rem}
.inv-alert:last-child{margin-bottom:0}
.inv-alert.warn{background:rgba(245,183,49,.05);border:1px solid rgba(245,183,49,.18)}
.inv-alert.danger{background:rgba(255,59,92,.05);border:1px solid rgba(255,59,92,.18)}
.inv-alert.ok{background:rgba(16,217,130,.05);border:1px solid rgba(16,217,130,.18)}
.inv-icon{width:30px;height:30px;border-radius:8px;display:grid;place-items:center;flex-shrink:0}
.warn .inv-icon{background:rgba(245,183,49,.1)}
.danger .inv-icon{background:rgba(255,59,92,.1)}
.ok .inv-icon{background:rgba(16,217,130,.1)}
.inv-title{font-size:.8rem;font-weight:600;margin-bottom:.18rem}
.inv-body{font-size:.73rem;color:var(--muted2);line-height:1.5}

@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
.fade-in{animation:fadeUp .3s ease both}
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
      <a class="sb-item" href="leaderboard.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6l4-4 4 4"/><path d="M12 2v13"/><path d="M20 21H4"/><path d="M17 12h3v9"/><path d="M4 12h3v9"/></svg></span><span class="sb-text">Leaderboard</span></a>
      <div class="sb-divider"></div>
      <div class="sb-label">Seller Hub</div>
      <a class="sb-item" href="seller-store.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1 0 8"/></svg></span><span class="sb-text">My Store</span></a>
      <a class="sb-item active" href="seller-analytics.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><polyline points="2 20 22 20"/></svg></span><span class="sb-text">Analytics</span></a>
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
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span>Sign Out</span>
      </button>
    </div>
  </div>

  <!-- MAIN -->
  <div id="main">
    <div class="topbar">
      <div class="tb-bc">
        <div>
          <div class="tb-title">Analytics</div>
          <div class="tb-sub">Store Performance &amp; Insights</div>
        </div>
      </div>
      <div class="tb-right">
        <div class="tb-search-wrap">
          <svg class="tb-search-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" class="tb-search" placeholder="Search analytics..."/>
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
          <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6l4 4 4-4"/></svg>
        </div>
      </div>
    </div>

    <!-- CONTENT -->
    <div class="content">
      <div class="page-inner fade-in">

        <!-- Hero -->
        <div class="analytics-hero">
          <div class="hero-content">
            <div class="hero-title">Store Analytics</div>
            <div class="hero-sub">Track your store performance and inventory insights</div>
            <div class="hero-stats">
              <div class="hero-stat">
                <span class="hero-number"><?php echo count($products); ?></span>
                <span class="hero-label">Products</span>
              </div>
              <div class="hero-stat">
                <span class="hero-number">&#8369;<?php echo number_format($totalValue, 0); ?></span>
                <span class="hero-label">Total Value</span>
              </div>
              <div class="hero-stat">
                <span class="hero-number"><?php echo number_format($totalStock, 0); ?></span>
                <span class="hero-label">Total Stock</span>
              </div>
              <div class="hero-stat">
                <span class="hero-number">&#8369;<?php echo number_format($avgPrice, 0); ?></span>
                <span class="hero-label">Avg Price</span>
              </div>
            </div>
          </div>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid">
          <div class="kpi-card" style="--accent:var(--blue)">
            <div class="kpi-top">
              <div class="kpi-icon">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
              </div>
              <span class="kpi-badge badge-up">live</span>
            </div>
            <div class="kpi-value">&#8369;<?php echo number_format($totalValue, 0); ?></div>
            <div class="kpi-label">Inventory Value</div>
          </div>
          <div class="kpi-card" style="--accent:var(--green)">
            <div class="kpi-top">
              <div class="kpi-icon" style="background:rgba(16,217,130,.08)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
              </div>
              <span class="kpi-badge badge-up"><?php echo $activeCount; ?> active</span>
            </div>
            <div class="kpi-value"><?php echo count($products); ?></div>
            <div class="kpi-label">Total Products</div>
          </div>
          <div class="kpi-card" style="--accent:var(--purple)">
            <div class="kpi-top">
              <div class="kpi-icon" style="background:rgba(123,114,240,.08)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--purple)" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="20" x2="12" y2="4"/><line x1="18" y1="20" x2="18" y2="10"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
              </div>
              <span class="kpi-badge badge-neutral">units</span>
            </div>
            <div class="kpi-value"><?php echo number_format($totalStock, 0); ?></div>
            <div class="kpi-label">Total Stock</div>
          </div>
          <div class="kpi-card" style="--accent:var(--<?php echo $lowStockCount > 0 ? 'red' : 'green'; ?>)">
            <div class="kpi-top">
              <div class="kpi-icon" style="background:rgba(<?php echo $lowStockCount > 0 ? '255,59,92' : '16,217,130'; ?>,.08)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--<?php echo $lowStockCount > 0 ? 'red' : 'green'; ?>)" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              </div>
              <span class="kpi-badge <?php echo $lowStockCount > 0 ? 'badge-down' : 'badge-up'; ?>"><?php echo $lowStockCount > 0 ? 'restock' : 'all ok'; ?></span>
            </div>
            <div class="kpi-value"><?php echo $lowStockCount; ?></div>
            <div class="kpi-label">Low Stock Alerts</div>
          </div>
        </div>

        <!-- Charts Row -->
        <div class="charts-grid">
          <div class="chart-card">
            <div class="chart-hdr">
              <h3>Revenue Overview</h3>
              <div class="period-btns">
                <button class="period-btn active" onclick="updatePeriod(30,this)">30D</button>
                <button class="period-btn" onclick="updatePeriod(7,this)">7D</button>
                <button class="period-btn" onclick="updatePeriod(90,this)">90D</button>
              </div>
            </div>
            <div class="chart-wrap"><canvas id="revenue-chart"></canvas></div>
          </div>
          <div class="chart-card">
            <div class="chart-hdr"><h3>By Category</h3></div>
            <div class="chart-wrap"><canvas id="category-chart"></canvas></div>
          </div>
        </div>

        <!-- Bottom Row -->
        <div class="bottom-grid">
          <div class="chart-card">
            <div class="chart-hdr"><h3>Top Products by Value</h3></div>
            <div id="top-products-list"></div>
          </div>
          <div class="chart-card">
            <div class="chart-hdr"><h3>Inventory Status</h3></div>
            <div id="inventory-status"></div>
          </div>
        </div>

        <!-- Stock Chart -->
        <div class="chart-card" style="margin-bottom:2rem">
          <div class="chart-hdr"><h3>Stock Levels by Product</h3></div>
          <div class="chart-wrap" style="height:<?php echo max(180, count($products)*38); ?>px"><canvas id="stock-chart"></canvas></div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- Modal overlay -->
<div id="modal-overlay" class="mo" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <div class="mhdr"><h3 id="modal-title">Confirm Action</h3><button class="mcl" onclick="closeModal()"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
    <div class="mbdy" id="modal-body"></div>
  </div>
</div>
<div id="toast-c"></div>

<script>
const products = <?php echo json_encode($products); ?>;
let revenueChart, categoryChart, stockChart;

function isDark(){return document.documentElement.getAttribute('data-theme')==='dark'}

function ctColors(){
    const dk=isDark();
    return{grid:dk?'rgba(255,255,255,.045)':'rgba(0,0,0,.06)',tick:dk?'#8898b4':'#475569'};
}

function buildRevenueChart(days){
    const ctx=document.getElementById('revenue-chart').getContext('2d');
    if(revenueChart)revenueChart.destroy();
    const now=new Date(), labels=[], data=[];
    for(let i=days;i>=0;i--){
        const d=new Date(now);d.setDate(d.getDate()-i);
        labels.push(d.toLocaleDateString('en-US',{month:'short',day:'numeric'}));
        data.push(Math.floor(Math.random()*5500)+800);
    }
    const c=ctColors();
    revenueChart=new Chart(ctx,{
        type:'line',
        data:{labels,datasets:[{label:'Revenue (&#8369;)',data,borderColor:'#3B8BFF',backgroundColor:'rgba(59,139,255,.07)',fill:true,tension:.42,pointRadius:days<=7?4:1,pointHoverRadius:6,borderWidth:2}]},
        options:{responsive:true,maintainAspectRatio:false,
            plugins:{legend:{display:false},tooltip:{backgroundColor:isDark()?'#0d1421':'#fff',titleColor:'#dde4f0',bodyColor:'#8898b4',borderColor:'rgba(59,139,255,.2)',borderWidth:1}},
            scales:{x:{grid:{color:c.grid},ticks:{color:c.tick,maxTicksLimit:8,font:{family:"'JetBrains Mono'",size:10}}},y:{grid:{color:c.grid},ticks:{color:c.tick,callback:v=>'&#8369;'+v.toLocaleString(),font:{family:"'JetBrains Mono'",size:10}}}}}
    });
}

function buildCategoryChart(){
    const ctx=document.getElementById('category-chart').getContext('2d');
    if(categoryChart)categoryChart.destroy();
    const cats={};
    products.forEach(p=>{const c=p.category||'Other';cats[c]=(cats[c]||0)+p.price*p.stock;});
    if(!Object.keys(cats).length)return;
    const c=ctColors();
    categoryChart=new Chart(ctx,{
        type:'doughnut',
        data:{labels:Object.keys(cats),datasets:[{data:Object.values(cats),backgroundColor:['#3B8BFF','#7B72F0','#00D4AA','#F5B731','#FF8C42','#FF3B5C'],borderWidth:0,hoverOffset:5}]},
        options:{responsive:true,maintainAspectRatio:false,cutout:'65%',
            plugins:{legend:{position:'bottom',labels:{color:c.tick,font:{size:11},boxWidth:10,padding:10}}}}
    });
}

function buildStockChart(){
    const ctx=document.getElementById('stock-chart').getContext('2d');
    if(stockChart)stockChart.destroy();
    if(!products.length)return;
    const c=ctColors();
    stockChart=new Chart(ctx,{
        type:'bar',
        data:{labels:products.map(p=>p.name.length>18?p.name.substring(0,18)+'…':p.name),
              datasets:[{label:'Stock',data:products.map(p=>p.stock),backgroundColor:products.map(p=>p.stock<10?'rgba(255,59,92,.65)':'rgba(59,139,255,.65)'),borderRadius:5,borderSkipped:false}]},
        options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',
            plugins:{legend:{display:false}},
            scales:{x:{grid:{color:c.grid},ticks:{color:c.tick,font:{family:"'JetBrains Mono'",size:10}}},y:{grid:{display:false},ticks:{color:c.tick,font:{size:11}}}}}
    });
}

function buildTopProducts(){
    const sorted=[...products].sort((a,b)=>(b.price*b.stock)-(a.price*a.stock)).slice(0,5);
    const el=document.getElementById('top-products-list');
    if(!sorted.length){el.innerHTML='<p style="color:var(--muted2);font-size:.82rem;padding:.5rem 0">No products yet.</p>';return;}
    el.innerHTML=sorted.map((p,i)=>`
        <div class="top-product-row">
            <span class="top-rank">${i+1}</span>
            <div class="top-info">
                <div class="top-name">${esc(p.name)}</div>
                <div class="top-meta">${p.stock} units &middot; ${esc(p.category||'Other')}</div>
            </div>
            <div class="top-val">&#8369;${(p.price*p.stock).toLocaleString()}</div>
        </div>`).join('');
}

function buildInventoryStatus(){
    const low=products.filter(p=>p.stock>0&&p.stock<10);
    const out=products.filter(p=>p.stock===0);
    const el=document.getElementById('inventory-status');
    let html='';
    if(out.length) html+=`<div class="inv-alert danger"><div class="inv-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div><div><div class="inv-title" style="color:var(--red)">Out of Stock</div><div class="inv-body">${out.map(p=>esc(p.name)).join(', ')}</div></div></div>`;
    if(low.length)  html+=`<div class="inv-alert warn"><div class="inv-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--yellow)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div><div><div class="inv-title" style="color:var(--yellow)">Low Stock</div><div class="inv-body">${low.map(p=>`${esc(p.name)}: ${p.stock} left`).join('<br>')}</div></div></div>`;
    if(!low.length&&!out.length) html+=`<div class="inv-alert ok"><div class="inv-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div><div><div class="inv-title" style="color:var(--green)">All Good</div><div class="inv-body">All products have sufficient stock.</div></div></div>`;
    el.innerHTML=html;
}

function updatePeriod(days,btn){
    document.querySelectorAll('.period-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    buildRevenueChart(days);
}

function esc(s){if(!s)return'';return s.replace(/[&<>]/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[m]));}

function toggleSidebar(){document.getElementById('sidebar').classList.toggle('collapsed');localStorage.setItem('cs_sb',document.getElementById('sidebar').classList.contains('collapsed')?'1':'0');}
function toggleTheme(){const d=!isDark();document.documentElement.setAttribute('data-theme',d?'dark':'light');localStorage.setItem('cs_th',d?'dark':'light');const m=document.getElementById('tmoon'),s=document.getElementById('tsun');if(m)m.style.display=d?'':'none';if(s)s.style.display=d?'none':'';setTimeout(()=>{buildRevenueChart(30);buildCategoryChart();buildStockChart();},50);}
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
function closeModal(){document.getElementById('modal-overlay').classList.remove('active');}

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

    buildRevenueChart(30);
    buildCategoryChart();
    buildStockChart();
    buildTopProducts();
    buildInventoryStatus();

    document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal();});
});
</script>
</body>
</html>