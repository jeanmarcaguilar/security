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

// Assessment questions database
$questions = [
    ['id' => 1, 'category' => 'password', 'text' => 'Do you use a password manager to store and generate strong passwords?', 'options' => ['Yes, always' => 100, 'Sometimes' => 50, 'No, I remember them' => 25, 'I use the same password everywhere' => 0]],
    ['id' => 2, 'category' => 'password', 'text' => 'How often do you change your passwords?', 'options' => ['Every 30 days' => 100, 'Every 90 days' => 75, 'Every 6 months' => 50, 'Only when forced' => 25, 'Never' => 0]],
    ['id' => 3, 'category' => 'password', 'text' => 'Do you use multi-factor authentication (MFA) on your important accounts?', 'options' => ['Yes, on all accounts' => 100, 'On most accounts' => 75, 'On a few accounts' => 50, 'No' => 0]],
    ['id' => 4, 'category' => 'phishing', 'text' => 'How do you verify suspicious emails asking for credentials?', 'options' => ['Contact sender through known channel' => 100, 'Check email headers' => 75, 'Look for spelling errors' => 50, 'Click links to verify' => 25, 'I always trust emails' => 0]],
    ['id' => 5, 'category' => 'phishing', 'text' => 'Have you completed security awareness training in the past year?', 'options' => ['Yes, with certification' => 100, 'Yes, online course' => 75, 'Only watched videos' => 50, 'No training' => 0]],
    ['id' => 6, 'category' => 'phishing', 'text' => 'What do you do when you receive an unexpected attachment?', 'options' => ['Verify with sender before opening' => 100, 'Scan with antivirus' => 75, 'Open if it looks legitimate' => 25, 'Always open' => 0]],
    ['id' => 7, 'category' => 'device', 'text' => 'Is your device protected with antivirus/anti-malware software?', 'options' => ['Yes, always updated' => 100, 'Yes, but not always updated' => 50, 'No antivirus' => 0]],
    ['id' => 8, 'category' => 'device', 'text' => 'Do you lock your device when away from it?', 'options' => ['Always immediately' => 100, 'Sometimes' => 50, 'Never' => 0]],
    ['id' => 9, 'category' => 'device', 'text' => 'How often do you update your operating system and applications?', 'options' => ['Automatically updated' => 100, 'Weekly manual checks' => 75, 'Monthly' => 50, 'When reminded' => 25, 'Never' => 0]],
    ['id' => 10, 'category' => 'network', 'text' => 'Do you use a VPN when connecting to public Wi-Fi?', 'options' => ['Always' => 100, 'Sometimes' => 50, 'Never' => 0]],
    ['id' => 11, 'category' => 'network', 'text' => 'Is your home Wi-Fi secured with WPA2/WPA3 encryption?', 'options' => ['Yes, with strong password' => 100, 'Yes, default password' => 50, 'No encryption' => 0, 'Not sure' => 25]],
    ['id' => 12, 'category' => 'network', 'text' => 'Do you have a firewall enabled on your network/devices?', 'options' => ['Yes, hardware and software' => 100, 'Software only' => 75, 'Hardware only' => 50, 'No firewall' => 0]]
];

$userInitial = strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 1));
$totalQuestions = count($questions);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Assessment — CyberShield</title>
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

/* Assessment-specific styles */
.assess-header{margin-bottom:1.5rem}
.assess-topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem}
.assess-title{font-family:var(--display);font-size:1.25rem;font-weight:700;letter-spacing:.5px}
.progress-meta{display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem}
.progress-meta-right{display:flex;align-items:center;gap:1rem}
.timer-wrap{display:flex;align-items:center;gap:.35rem}
.timer-val{font-family:var(--mono);font-size:.9rem;font-weight:600;color:var(--text)}
.timer-val.urgent{color:var(--red)}.timer-val.warning{color:var(--yellow)}
.progress-pct{font-family:var(--mono);font-size:.7rem;color:var(--muted2)}
.progress-bar-wrap{height:6px;background:var(--border);border-radius:3px;margin-bottom:.75rem;overflow:hidden}
.progress-bar-fill{height:100%;background:linear-gradient(90deg,var(--blue),var(--purple));border-radius:3px;transition:width .3s ease}
.timer-bar-wrap{height:3px;background:var(--border);border-radius:2px;overflow:hidden}
.timer-bar-fill{height:100%;background:var(--green);border-radius:2px;transition:width 1s linear}
.timer-bar-fill.urgent{background:var(--red)}.timer-bar-fill.warning{background:var(--yellow)}
.q-card{padding:2rem}
.q-num{font-family:var(--mono);font-size:.65rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted2);margin-bottom:.5rem}
.q-category{display:flex;align-items:center;gap:.5rem;font-family:var(--mono);font-size:.6rem;letter-spacing:1px;text-transform:uppercase;color:var(--blue);margin-bottom:1rem}
.q-text{font-size:1.1rem;line-height:1.6;color:var(--text);margin-bottom:1.5rem}
.options-list{display:flex;flex-direction:column;gap:.75rem}
.option-btn{display:flex;align-items:flex-start;gap:.75rem;padding:1rem;border:1px solid var(--border);border-radius:8px;background:var(--bg2);color:var(--text);cursor:pointer;transition:var(--t);text-align:left}
.option-btn:hover{border-color:var(--blue);background:rgba(59,139,255,.07)}
.option-btn.correct{border-color:rgba(59,139,255,.35);background:rgba(59,139,255,.07)}
.option-letter{width:28px;height:28px;border-radius:6px;background:var(--bg3);border:1px solid var(--border);display:grid;place-items:center;font-family:var(--mono);font-size:.7rem;font-weight:600;color:var(--muted2);flex-shrink:0}
.option-btn.correct .option-letter{background:rgba(59,139,255,.14);border-color:rgba(59,139,255,.3);color:var(--blue)}
.q-nav-hint{margin-top:1rem;text-align:center}
.rank-pill{display:inline-block;padding:.2rem .5rem;border-radius:4px;font-family:var(--mono);font-size:.65rem;font-weight:600}
.rank-pill.A{background:rgba(16,217,130,.15);color:var(--green)}
.rank-pill.B{background:rgba(245,183,49,.15);color:var(--yellow)}
.rank-pill.C{background:rgba(255,140,66,.15);color:var(--orange)}
.rank-pill.D{background:rgba(255,59,92,.15);color:var(--red)}
.tip-card{margin-top:1.5rem}
.tip-card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem}
.tip-card-label{font-family:var(--mono);font-size:.65rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted2)}
.fp-overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);display:grid;place-items:center;z-index:200;backdrop-filter:blur(4px)}
.fp-overlay.hidden{display:none}
.fp-close-btn{position:absolute;top:1rem;right:1rem;width:28px;height:28px;border-radius:7px;border:1px solid var(--border2);background:none;color:var(--muted2);cursor:pointer;display:grid;place-items:center;transition:var(--t)}
.fp-close-btn:hover{border-color:var(--red);color:var(--red)}
.fp-step-icon{width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,var(--blue),var(--purple));display:grid;place-items:center;margin:0 auto 1rem;color:#fff}
.fp-step h3{font-family:var(--display);font-size:1.1rem;font-weight:700;text-align:center;margin-bottom:.5rem}
.fp-step p{text-align:center;color:var(--muted2);margin-bottom:1.5rem}
</style>
</head>
<body>
<div class="bg-grid"></div>
<div id="app">

  <aside id="sidebar">
    <div class="sb-brand">
      <div class="shield"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
      <div class="sb-brand-text"><h2>CyberShield</h2><span class="badge">Client Portal</span></div>
      <button class="sb-toggle" onclick="toggleSidebar()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
    </div>
    <div class="sb-section">
      <div class="sb-label">Navigation</div>
      <a class="sb-item" href="index.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.2"/><rect x="14" y="3" width="7" height="7" rx="1.2"/><rect x="3" y="14" width="7" height="7" rx="1.2"/><rect x="14" y="14" width="7" height="7" rx="1.2"/></svg></span><span class="sb-text">Dashboard</span></a>
      <a class="sb-item active" href="assessment.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span><span class="sb-text">Assessment</span></a>
      <a class="sb-item" href="result.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span><span class="sb-text">Results</span></a>
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
        <div class="sb-avatar" id="sb-avatar"><?php echo $userInitial; ?></div>
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
          <span class="tb-title">Cyber Hygiene Assessment</span>
        </div>
        <p class="tb-sub">Vendor cybersecurity assessment questionnaire</p>
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
          <div class="tb-admin-av" id="tb-avatar"><?php echo $userInitial; ?></div>
          <div class="tb-admin-info"><span class="tb-admin-name" id="tb-name"><?php echo htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'User'); ?></span><span class="tb-admin-role">Client</span></div>
          <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" style="color:var(--muted);margin-left:.2rem"><path d="M4 6l4 4 4-4"/></svg>
        </a>
      </div>
    </div>

    <div class="content">

        <div class="assess-header">
          <div class="assess-topbar">
            <h2 class="assess-title">Cyber Hygiene Assessment</h2>
            <button class="btn btn-outline btn-sm" onclick="confirmQuit()">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              Quit
            </button>
          </div>
          <div class="progress-meta">
            <span id="progress-label">Question 1 of <?php echo $totalQuestions; ?></span>
            <div class="progress-meta-right">
              <div class="timer-wrap" id="timer-wrap">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span class="timer-val" id="timer-val">30</span>
              </div>
              <span class="progress-pct" id="progress-pct">0%</span>
            </div>
          </div>
          <div class="progress-bar-wrap"><div class="progress-bar-fill" id="progress-fill" style="width:0%"></div></div>
          <div class="timer-bar-wrap"><div class="timer-bar-fill" id="timer-bar-fill"></div></div>
        </div>

        <div class="card q-card" id="q-card"></div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1.5rem;">
          <button class="btn btn-outline btn-sm" id="prev-btn" onclick="prevQuestion()" disabled>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Previous
          </button>
          <button class="btn btn-primary btn-sm" id="next-btn" onclick="nextQuestion()">
            Next
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </button>
          <button class="btn btn-primary btn-sm" id="submit-btn" onclick="submitAssessment()" style="display:none;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            Submit Assessment
          </button>
        </div>

        <div id="score-preview" class="card tip-card" style="display:none;max-width:710px;margin:1.5rem auto 0;">
          <div class="tip-card-header">
            <span class="tip-card-label">Score Preview</span>
          </div>
          <div style="display:flex;align-items:center;gap:1.25rem;margin-top:.25rem;">
            <div style="font-family:var(--display);font-size:2.5rem;letter-spacing:1px;color:var(--blue);" id="preview-score">0%</div>
            <div id="preview-rank" style="font-size:.84rem;color:var(--muted2);"></div>
          </div>
        </div>

    </div>
  </div>

<div id="modal-overlay" class="mo hidden" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <div class="mhdr"><h3 id="modal-title">Confirm Submission</h3><button class="mcl" onclick="closeModal()"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
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

// Assessment-specific JavaScript
const questions = <?php echo json_encode($questions); ?>;
let userAnswers = new Array(questions.length).fill(null);
let currentQuestion = 0;
let timerInterval = null;
let timeLeft = 30;
const timerEnabled = true;

const catLabels = { password: 'Password', phishing: 'Phishing', device: 'Device', network: 'Network' };

function renderQuestion(index) {
    const q = questions[index];
    const selectedValue = userAnswers[index];
    const letters = ['A', 'B', 'C', 'D', 'E'];

    let optionsHtml = '';
    let i = 0;
    for (const [text, score] of Object.entries(q.options)) {
        const isSelected = selectedValue === score;
        optionsHtml += `
            <button class="option-btn ${isSelected ? 'correct' : ''}"
                    onclick="selectAnswer(${index}, ${score})">
                <span class="option-letter">${letters[i] || (i+1)}</span>
                ${escapeHtml(text)}
            </button>
        `;
        i++;
    }

    const html = `
        <div class="q-num">Question ${index + 1} of ${questions.length}</div>
        <div class="q-category">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            ${catLabels[q.category] || q.category}
        </div>
        <div class="q-text">${escapeHtml(q.text)}</div>
        <div class="options-list">${optionsHtml}</div>
        <div class="q-nav-hint">
            ${selectedValue !== null ? '✓ Answer recorded — click Next to continue' : 'Select an answer to proceed'}
        </div>
    `;

    document.getElementById('q-card').innerHTML = html;

    const pct = Math.round(((index + 1) / questions.length) * 100);
    document.getElementById('progress-label').textContent = `Question ${index + 1} of ${questions.length}`;
    document.getElementById('progress-pct').textContent = `${pct}%`;
    document.getElementById('progress-fill').style.width = `${pct}%`;

    document.getElementById('prev-btn').disabled = index === 0;
    if (index === questions.length - 1) {
        document.getElementById('next-btn').style.display = 'none';
        document.getElementById('submit-btn').style.display = 'inline-flex';
    } else {
        document.getElementById('next-btn').style.display = 'inline-flex';
        document.getElementById('submit-btn').style.display = 'none';
    }

    resetTimer();
    if (timerEnabled && selectedValue === null) startTimer();
}

function selectAnswer(questionIndex, score) {
    userAnswers[questionIndex] = score;
    renderQuestion(currentQuestion);
    updateScorePreview();
}

function nextQuestion() {
    if (currentQuestion < questions.length - 1) {
        currentQuestion++;
        renderQuestion(currentQuestion);
    }
}

function prevQuestion() {
    if (currentQuestion > 0) {
        currentQuestion--;
        renderQuestion(currentQuestion);
    }
}

function resetTimer() {
    if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
    timeLeft = 30;
    updateTimerDisplay();
}

function startTimer() {
    if (timerInterval) clearInterval(timerInterval);
    timerInterval = setInterval(() => {
        if (timeLeft <= 1) {
            clearInterval(timerInterval); timerInterval = null;
            if (userAnswers[currentQuestion] === null) {
                const firstScore = Object.values(questions[currentQuestion].options)[0];
                selectAnswer(currentQuestion, firstScore);
            }
        } else {
            timeLeft--;
            updateTimerDisplay();
        }
    }, 1000);
}

function updateTimerDisplay() {
    const el = document.getElementById('timer-val');
    const bar = document.getElementById('timer-bar-fill');
    el.textContent = timeLeft;
    const pct = (timeLeft / 30) * 100;
    bar.style.width = pct + '%';

    el.className = 'timer-val';
    bar.className = 'timer-bar-fill';
    if (timeLeft <= 5) {
        el.classList.add('urgent');
        bar.classList.add('urgent');
    } else if (timeLeft <= 10) {
        el.classList.add('warning');
        bar.classList.add('warning');
    }
}

function calculateScore() {
    const answered = userAnswers.filter(a => a !== null);
    if (!answered.length) return 0;
    return Math.round(answered.reduce((s, v) => s + v, 0) / answered.length);
}

function calculateCategoryScores() {
    const cats = { password: { t: 0, c: 0 }, phishing: { t: 0, c: 0 }, device: { t: 0, c: 0 }, network: { t: 0, c: 0 } };
    questions.forEach((q, i) => {
        if (userAnswers[i] !== null && cats[q.category]) {
            cats[q.category].t += userAnswers[i];
            cats[q.category].c++;
        }
    });
    return {
        password: cats.password.c ? Math.round(cats.password.t / cats.password.c) : 0,
        phishing: cats.phishing.c ? Math.round(cats.phishing.t / cats.phishing.c) : 0,
        device:   cats.device.c   ? Math.round(cats.device.t   / cats.device.c)   : 0,
        network:  cats.network.c  ? Math.round(cats.network.t  / cats.network.c)  : 0,
    };
}

function getRank(score) {
    if (score >= 80) return { letter: 'A', text: 'Low Risk — Excellent security practices', color: 'var(--green)' };
    if (score >= 60) return { letter: 'B', text: 'Moderate Risk — Good foundation, room for improvement', color: 'var(--yellow)' };
    if (score >= 40) return { letter: 'C', text: 'High Risk — Significant improvements needed', color: 'var(--orange)' };
    return { letter: 'D', text: 'Critical Risk — Immediate action required', color: 'var(--red)' };
}

function updateScorePreview() {
    const score = calculateScore();
    const rank = getRank(score);
    const preview = document.getElementById('score-preview');
    if (userAnswers.some(a => a !== null)) {
        preview.style.display = 'block';
        document.getElementById('preview-score').textContent = score + '%';
        document.getElementById('preview-score').style.color = rank.color;
        document.getElementById('preview-rank').innerHTML =
            `<span class="rank-pill ${rank.letter}" style="margin-right:.5rem;">${rank.letter}</span>${rank.text}`;
    }
}

function submitAssessment() {
    const unanswered = userAnswers.filter(a => a === null).length;
    if (unanswered > 0) {
        const first = userAnswers.findIndex(a => a === null);
        if (first !== -1) { currentQuestion = first; renderQuestion(currentQuestion); }
        showToast(`⚠ ${unanswered} question(s) still unanswered`, 'warning');
        return;
    }
    document.getElementById('modal-title').textContent = 'Confirm Submission';
    document.getElementById('modal-body').innerHTML = `
        <p>Are you sure you want to submit this assessment? You cannot change your answers after submission.</p>
        <div style="display:flex;gap:.75rem;margin-top:1.25rem;">
            <button class="btn btn-primary" onclick="confirmSubmit()">Yes, Submit</button>
            <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
        </div>
    `;
    document.getElementById('modal-overlay').classList.remove('hidden');
}

async function confirmSubmit() {
    closeModal();
    const score = calculateScore();
    const rank = getRank(score);
    const categoryScores = calculateCategoryScores();

    const assessmentData = {
        vendor_id: 0,
        score,
        rank: rank.letter,
        password_score: categoryScores.password,
        phishing_score: categoryScores.phishing,
        device_score:   categoryScores.device,
        network_score:  categoryScores.network,
        assessment_notes: `Completed on ${new Date().toLocaleString()}`
    };

    try {
        const response = await fetch('api/save_assessment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(assessmentData)
        });
        const result = await response.json();

        if (result.success) {
            localStorage.setItem('lastAssessment', JSON.stringify({
                score, rank: rank.letter, categoryScores,
                answers: userAnswers, date: new Date().toISOString()
            }));
            window.location.href = 'results.php';
        } else {
            showToast('Error saving assessment: ' + result.message, 'error');
        }
    } catch (err) {
        console.error(err);
        showToast('Connection error. Please try again.', 'error');
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function confirmQuit() {
    document.getElementById('modal-title').textContent = 'Quit Assessment?';
    document.getElementById('modal-body').innerHTML = `
        <p>Your progress will be lost if you leave now. Are you sure you want to quit?</p>
        <div style="display:flex;gap:.75rem;margin-top:1.25rem;">
            <a href="index.php" class="btn btn-outline" style="justify-content:center;">Yes, Quit</a>
            <button class="btn btn-primary" onclick="closeModal()">Keep Going</button>
        </div>
    `;
    document.getElementById('modal-overlay').classList.remove('hidden');
}

// Boot
document.addEventListener('DOMContentLoaded', () => {
    renderQuestion(0);
});
</script>
</body>
</html>