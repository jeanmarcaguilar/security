<?php

session_start();

require_once '../includes/config.php';



if (!isset($_SESSION['user_id'])) {

    header('Location: ../login.php');

    exit();

}



$database = new Database();

$db       = $database->getConnection();



$stmt = $db->prepare("SELECT * FROM users WHERE id = :uid");

$stmt->bindParam(':uid', $_SESSION['user_id']);

$stmt->execute();

$user    = $stmt->fetch(PDO::FETCH_ASSOC);

$initial = strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 1));



// ── Cooldown / attempt-limit logic ───────────────────────────────────────────

$can_take            = true;

$next_available_date = null;

$waiting_message     = '';

$waiting_period_min  = 1;   // minutes (set higher for production)

$max_per_month       = 30;



$stmt = $db->prepare("SELECT assessment_date FROM assessments WHERE vendor_id = :uid ORDER BY created_at DESC LIMIT 1");

$stmt->bindParam(':uid', $_SESSION['user_id']);

$stmt->execute();

$last = $stmt->fetch(PDO::FETCH_ASSOC);



if ($last) {

    $lastDT   = new DateTime($last['assessment_date']);

    $now      = new DateTime();

    $minsDiff = ($now->getTimestamp() - $lastDT->getTimestamp()) / 60;

    if ($minsDiff < $waiting_period_min) {

        $can_take            = false;

        $next_available_date = clone $lastDT;

        $next_available_date->modify("+{$waiting_period_min} minutes");

        $minsLeft        = ceil($waiting_period_min - $minsDiff);

        $waiting_message = "Please wait {$minsLeft} more minute(s). Next available: " . $next_available_date->format('g:i A');

    }

}



if ($can_take) {

    $fom  = date('Y-m-01 00:00:00');

    $stmt = $db->prepare("SELECT COUNT(*) as c FROM assessments WHERE vendor_id = :uid AND created_at >= :fom");

    $stmt->bindParam(':uid', $_SESSION['user_id']);

    $stmt->bindParam(':fom', $fom);

    $stmt->execute();

    if ($stmt->fetch(PDO::FETCH_ASSOC)['c'] >= $max_per_month) {

        $can_take        = false;

        $waiting_message = "You've reached the {$max_per_month}-assessment monthly limit. Try again next month.";

    }

}



$assessment_token = null;

if ($can_take) {

    $assessment_token               = bin2hex(random_bytes(32));

    $_SESSION['assessment_token']   = $assessment_token;

    $_SESSION['assessment_start']   = time();

}

?>

<!DOCTYPE html>

<html lang="en" data-theme="dark">

<head>

<meta charset="UTF-8"/>

<meta name="viewport" content="width=device-width,initial-scale=1.0"/>

<title>Security Assessment — CyberShield</title>

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

.sb-toggle{width:28px;height:28px;background:rgba(59,139,255,0.1);border:1px solid var(--blue);border-radius:6px;cursor:pointer;color:var(--blue);display:grid;place-items:center;flex-shrink:0;transition:var(--t)}

.sb-toggle:hover{background:rgba(59,139,255,0.2);transform:scale(1.05)}

#sidebar.collapsed .sb-toggle svg{transform:rotate(180deg)}

.sb-section{flex:1;overflow-y:auto;overflow-x:hidden;padding:.65rem 0}

.sb-section::-webkit-scrollbar{width:3px}

.sb-section::-webkit-scrollbar-thumb{background:var(--border2);border-radius:2px}

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

.tb-admin:hover{border-color:rgba(59,139,255,.28);background:rgba(59,139,255,.06)}

.tb-admin-av{width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,var(--blue),var(--purple));color:#fff;display:grid;place-items:center;font-size:.7rem;font-weight:700;flex-shrink:0;font-family:var(--display)}

.tb-admin-info{display:flex;flex-direction:column}

.tb-admin-name{font-size:.78rem;font-weight:600;line-height:1.2}

.tb-admin-role{font-size:.6rem;color:var(--blue);letter-spacing:.5px;font-family:var(--mono)}

.content{flex:1;overflow-y:auto;padding:1.5rem}

.content::-webkit-scrollbar{width:4px}

.content::-webkit-scrollbar-thumb{background:var(--border2);border-radius:2px}

.sec-hdr{margin-bottom:1.25rem}

.sec-hdr h2{font-family:var(--display);font-size:1.25rem;font-weight:700;letter-spacing:.5px}

.sec-hdr p{font-size:.82rem;color:var(--muted2);margin-top:.2rem}

.card{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow);transition:border-color .18s}

.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.42rem .9rem;border-radius:8px;font-family:var(--font);font-size:.78rem;font-weight:600;cursor:pointer;transition:var(--t);border:none;text-decoration:none}

.btn-primary{background:var(--blue);color:#fff}

.btn-primary:hover{background:#2e7ae8}

.btn-secondary{background:rgba(255,255,255,.05);color:var(--muted2);border:1px solid var(--border2)}

.btn-secondary:hover{border-color:var(--blue);color:var(--text)}

.btn-success{background:var(--green);color:#fff}

.btn-success:hover{background:#0ec473}

.btn-sm{font-size:.72rem;padding:.32rem .7rem}



/* ── Progress bar ── */

.assess-header{margin-bottom:1.5rem}

.progress-meta{display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem}

.progress-meta-right{display:flex;align-items:center;gap:1rem}

.timer-wrap{display:flex;align-items:center;gap:.4rem;font-family:var(--mono);font-size:.78rem;color:var(--muted2)}

.timer-val{font-weight:600;color:var(--text)}

.timer-val.warning{color:var(--yellow)}

.timer-val.urgent{color:var(--red)}

.progress-pct{font-family:var(--mono);font-weight:600;color:var(--blue)}

.progress-bar-wrap{height:6px;background:var(--border2);border-radius:3px;margin-bottom:.5rem;overflow:hidden}

.progress-bar-fill{height:100%;background:linear-gradient(90deg,var(--blue),var(--purple));border-radius:3px;transition:width .3s ease}



/* ── Questions ── */

.questions-container{display:flex;flex-direction:column;gap:1.5rem;margin-bottom:2rem}

.question-item{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:1.5rem;transition:all .2s}

.question-item:hover{border-color:rgba(59,139,255,.3)}

.question-item.answered{border-color:rgba(16,217,130,.25)}

.q-header{display:flex;align-items:center;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap}

.q-number{font-family:var(--mono);font-size:.7rem;color:var(--muted2);background:rgba(59,139,255,.1);padding:.2rem .5rem;border-radius:4px}

.q-category{display:inline-block;padding:.2rem .6rem;border-radius:6px;font-family:var(--mono);font-size:.6rem;font-weight:600;letter-spacing:1px;text-transform:uppercase}

.q-diff{display:inline-block;padding:.15rem .45rem;border-radius:4px;font-family:var(--mono);font-size:.58rem;font-weight:700;letter-spacing:.5px;text-transform:uppercase}

.diff-easy{background:rgba(16,217,130,.12);color:var(--green)}

.diff-medium{background:rgba(245,183,49,.12);color:var(--yellow)}

.diff-hard{background:rgba(255,59,92,.12);color:var(--red)}

.category-password{background:rgba(59,139,255,.12);color:var(--blue)}

.category-phishing{background:rgba(255,140,66,.12);color:var(--orange)}

.category-device{background:rgba(16,217,130,.12);color:var(--green)}

.category-network{background:rgba(123,114,240,.12);color:var(--purple)}

.category-social_engineering{background:rgba(245,183,49,.12);color:var(--yellow)}

.category-data_handling{background:rgba(0,212,170,.12);color:var(--teal)}

.q-text{font-size:1rem;font-weight:500;line-height:1.5;margin-bottom:1rem;color:var(--text)}

.options-grid{display:grid;gap:.5rem;margin-top:.5rem}

.option-radio{display:flex;align-items:center;gap:.75rem;padding:.75rem;background:rgba(255,255,255,.02);border:1px solid var(--border);border-radius:8px;cursor:pointer;transition:all .2s}

.option-radio:hover{background:rgba(59,139,255,.05);border-color:rgba(59,139,255,.3)}

.option-radio.selected{background:rgba(59,139,255,.1);border-color:var(--blue)}

.option-radio input{width:16px;height:16px;cursor:pointer;accent-color:var(--blue)}

.option-radio label{flex:1;cursor:pointer;font-size:.85rem;line-height:1.4;color:var(--text)}

.explanation-box{margin-top:.75rem;padding:.75rem;background:rgba(59,139,255,.05);border-left:3px solid var(--blue);border-radius:6px;font-size:.8rem;line-height:1.5;color:var(--muted2);display:none}

.explanation-box.show{display:block}

.explanation-box strong{color:var(--blue);display:block;margin-bottom:.25rem;font-size:.7rem;font-family:var(--mono)}



/* ── Page indicator ── */

.page-indicator{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;padding:1rem;background:var(--card-bg);border:1px solid var(--border);border-radius:12px;flex-wrap:wrap;gap:.75rem}

.page-info{display:flex;flex-direction:column;gap:.35rem}

.page-text{font-family:var(--mono);font-size:.9rem;font-weight:600;color:var(--blue)}

.page-hint{font-size:.78rem;color:var(--muted2)}

.page-hint.final{color:var(--green);font-weight:600}

.next-footer{display:flex;justify-content:center;margin-top:2rem;padding:1rem;background:var(--card-bg);border:1px solid var(--border);border-radius:12px}

.nav-buttons{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--border)}



/* ── Schedule card (cooldown) ── */

.schedule-card{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:2.5rem;text-align:center;max-width:580px;margin:2rem auto}

.schedule-icon{font-size:3.5rem;margin-bottom:1rem}

.schedule-title{font-family:var(--display);font-size:1.4rem;color:var(--yellow);margin-bottom:.75rem}

.schedule-msg{font-size:.9rem;color:var(--muted2);margin-bottom:1.25rem;line-height:1.6}

.schedule-date{font-family:var(--mono);font-size:1rem;font-weight:700;color:var(--blue);margin:1rem 0;padding:.5rem .9rem;background:rgba(59,139,255,.1);border-radius:8px;display:inline-block}

.schedule-countdown{font-family:var(--mono);font-size:.88rem;color:var(--orange);margin:.75rem 0}



/* ── Loading / error ── */

.loading-screen{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1.5rem;padding:3rem;min-height:400px}

.loading-spinner{width:48px;height:48px;border:3px solid var(--border2);border-top-color:var(--blue);border-radius:50%;animation:spin .8s linear infinite}

@keyframes spin{to{transform:rotate(360deg)}}

.loading-text{font-family:var(--mono);font-size:.85rem;color:var(--muted2);text-align:center}

.error-screen{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1rem;padding:3rem;text-align:center}

.error-icon{font-size:2.5rem}

.error-title{font-family:var(--display);font-size:1.1rem;color:var(--red)}



/* ── Score preview bar ── */

.score-preview-bar{background:var(--card-bg);border:1px solid var(--border);border-radius:10px;padding:.7rem 1rem;margin-bottom:1rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap}

.sp-item{display:flex;align-items:center;gap:.4rem;font-family:var(--mono);font-size:.72rem;color:var(--muted2)}

.sp-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}



/* ── Modal ── */

.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;z-index:1000;padding:1rem}

.modal-overlay.hidden{display:none}

.modal{background:var(--bg3);border:1px solid var(--border);border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.5);width:100%;max-width:500px;max-height:90vh;overflow-y:auto;animation:su .2s ease}

@keyframes su{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}

.modal-header{display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid var(--border)}

.modal-header h3{font-family:var(--display);font-size:1rem;font-weight:700;margin:0}

.modal-close{background:none;border:none;font-size:1.25rem;color:var(--muted2);cursor:pointer;padding:.25rem;border-radius:4px;transition:var(--t)}

.modal-close:hover{color:var(--red)}

.modal-body{padding:1.5rem}

.rank-preview{text-align:center;margin-bottom:1.25rem}

.rank-letter{font-family:var(--display);font-size:3rem;font-weight:800;line-height:1}

.rank-score{font-size:1.4rem;margin:.3rem 0;font-weight:700}

.rank-label{font-size:.82rem;color:var(--muted2)}

.cat-grid{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-top:1rem}

.cat-item{background:var(--card-bg);border-radius:8px;padding:.6rem .8rem;text-align:center}

.cat-name{font-size:.6rem;text-transform:uppercase;color:var(--muted);font-family:var(--mono);letter-spacing:.5px}

.cat-val{font-family:var(--display);font-size:1.1rem;font-weight:700;margin-top:.2rem}



@media(max-width:768px){

  .content{padding:1rem}

  .question-item{padding:1rem}

  .questions-container{gap:1rem}

  .page-indicator{flex-direction:column}

  .cat-grid{grid-template-columns:1fr}

}

</style>

</head>

<body>

<div class="bg-grid"></div>

<div id="app">



<!-- ════════════════════ SIDEBAR ════════════════════ -->

<aside id="sidebar">

  <div class="sb-brand">

    <div class="shield"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>

    <div class="sb-brand-text"><h2>CyberShield</h2><span class="badge">Client Portal</span></div>

    <button class="sb-toggle" onclick="toggleSidebar()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg></button>

  </div>

  <div class="sb-section">

    <div class="sb-label">Navigation</div>

    <a class="sb-item" href="index.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="3" width="7" height="7" rx="1.2"/><rect x="14" y="3" width="7" height="7" rx="1.2"/><rect x="3" y="14" width="7" height="7" rx="1.2"/><rect x="14" y="14" width="7" height="7" rx="1.2"/></svg></span><span class="sb-text">Dashboard</span></a>

    <a class="sb-item active" href="assessment.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span><span class="sb-text">Take Assessment</span></a>

    <a class="sb-item" href="result.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span><span class="sb-text">Results</span></a>

    <a class="sb-item" href="leaderboard.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M8 6l4-4 4 4"/><path d="M12 2v13"/><path d="M20 21H4"/><path d="M17 12h3v9"/><path d="M4 12h3v9"/></svg></span><span class="sb-text">Leaderboard</span></a>

    <div class="sb-divider"></div>

    <div class="sb-label">Account</div>

    <a class="sb-item" href="profile.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span><span class="sb-text">Profile</span></a>

    <a class="sb-item" href="security-tips.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span><span class="sb-text">Security Tips</span></a>

    <a class="sb-item" href="terms.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span><span class="sb-text">Terms & Privacy</span></a>

  </div>

  <div class="sb-footer">

    <div class="sb-user">

      <div class="sb-avatar"><?php echo $initial; ?></div>

      <div class="sb-user-info"><p><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></p><span>Vendor Account</span></div>

    </div>

    <button class="btn-sb-logout" onclick="doLogout()">

      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>

      <span>Sign Out</span>

    </button>

  </div>

</aside>



<!-- ════════════════════ MAIN ════════════════════ -->

<div id="main">

  <div class="topbar">

    <div>

      <div class="tb-bc">

        <span class="tb-app">CyberShield</span>

        <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 4l4 4-4 4"/></svg>

        <span class="tb-title">Security Assessment</span>

      </div>

      <p class="tb-sub">100 questions · Ranked A–F · AI-powered risk analysis</p>

    </div>

    <div class="tb-right">

      <div class="tb-search-wrap">

        <span class="tb-search-icon"><svg width="12" height="12" viewBox="0 0 20 20" fill="none"><circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.7"/><path d="M15 15l3 3" stroke="currentColor" stroke-width="1.7"/></svg></span>

        <input type="text" class="tb-search" placeholder="Search questions…" id="search-q" autocomplete="off"/>

      </div>

      <span class="tb-date" id="tb-date"></span>

      <div class="tb-divider"></div>

      <button class="tb-icon-btn" onclick="toggleTheme()" title="Toggle theme">

        <svg id="tmoon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>

        <svg id="tsun" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="display:none"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>

      </button>

      <div class="tb-divider"></div>

      <div class="tb-admin">

        <div class="tb-admin-av"><?php echo $initial; ?></div>

        <div class="tb-admin-info"><span class="tb-admin-name"><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></span><span class="tb-admin-role">Vendor</span></div>

      </div>

    </div>

  </div>



  <div class="content">



    <?php if (!$can_take): ?>

    <!-- ── COOLDOWN CARD ── -->

    <div class="schedule-card">

      <div class="schedule-icon">⏰</div>

      <div class="schedule-title">Assessment Unavailable</div>

      <div class="schedule-msg"><?php echo htmlspecialchars($waiting_message); ?></div>

      <?php if ($next_available_date): ?>

      <div class="schedule-date">📅 Next: <?php echo $next_available_date->format('l, F j, Y · g:i A'); ?></div>

      <div id="countdown-timer" class="schedule-countdown"></div>

      <?php endif; ?>

      <div style="display:flex;gap:.75rem;justify-content:center;margin-top:1.25rem;flex-wrap:wrap">

        <a href="result.php" class="btn btn-primary">📊 View Results</a>

        <a href="security-tips.php" class="btn btn-secondary">🛡️ Security Tips</a>

      </div>

    </div>



    <?php else: ?>

    <!-- ── ASSESSMENT UI ── -->

    <div class="sec-hdr">

      <h2>Cybersecurity Assessment <span id="badge-fresh" style="display:none;font-size:.7rem;background:rgba(16,217,130,.15);color:var(--green);padding:.2rem .6rem;border-radius:20px;vertical-align:middle;font-family:var(--mono)">✨ Fresh Questions</span></h2>

      <p>100 randomized questions across 6 security domains · Ranks: A (90–100) · B (80–89) · C (70–79) · D (50–69) · F (&lt;50)</p>

    </div>



    <!-- Progress & Timer -->

    <div class="assess-header card" style="padding:1rem;margin-bottom:1.25rem" id="assess-header">

      <div class="progress-meta">

        <div class="timer-wrap">

          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>

          Time: <span class="timer-val" id="timer-display">00:00</span>

        </div>

        <div class="progress-meta-right">

          <span id="answered-count" style="font-family:var(--mono);font-size:.75rem;color:var(--muted2)">0 / 100 answered</span>

          <span class="progress-pct" id="progress-pct">0%</span>

        </div>

      </div>

      <div class="progress-bar-wrap"><div class="progress-bar-fill" id="progress-fill" style="width:0%"></div></div>

    </div>



    <div id="assessment-content">

      <div class="loading-screen">

        <div class="loading-spinner"></div>

        <div class="loading-text" id="loading-text">Loading your personalized 100-question assessment…</div>

      </div>

    </div>



    <div class="nav-buttons" id="nav-buttons" style="display:none">

      <div id="score-preview-txt" style="font-family:var(--mono);font-size:.82rem;color:var(--muted2)"></div>

      <div style="display:flex;gap:.75rem">

        <button class="btn btn-success" id="submit-btn" onclick="submitAssessment()">

          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="20 6 9 17 4 12"/></svg>

          Submit Assessment

        </button>

      </div>

    </div>

    <?php endif; ?>



  </div><!-- .content -->

</div><!-- #main -->

</div><!-- #app -->



<!-- ════════════════════ CONFIRM MODAL ════════════════════ -->

<div id="modal-overlay" class="modal-overlay hidden">

  <div class="modal">

    <div class="modal-header">

      <h3 id="modal-title">Confirm Submission</h3>

      <button class="modal-close" onclick="closeModal()">✕</button>

    </div>

    <div id="modal-body" class="modal-body"></div>

  </div>

</div>



<script>

// ════════════════════════════════════════════════════════════════════

// STATE

// ════════════════════════════════════════════════════════════════════

let questions        = [];

let userAnswers      = [];

let startTime        = null;

let timerInterval    = null;

let currentPage      = 1;

let assessmentSessionId = null;

let answerStartTimes = {}; // Track time per question to detect bias

let insightOpen      = [];

const PER_PAGE       = 10;

const TOKEN          = '<?php echo $assessment_token; ?>';

const USER_ID        = <?php echo (int)$_SESSION['user_id']; ?>;



// ════════════════════════════════════════════════════════════════════

// HELPER FUNCTIONS

// ════════════════════════════════════════════════════════════════════

function shuffle(arr) {

    const a = [...arr];

    for (let i = a.length - 1; i > 0; i--) {

        const j = Math.floor(Math.random() * (i + 1));

        [a[i], a[j]] = [a[j], a[i]];

    }

    return a;

}



function esc(s) {

    if (!s) return '';

    return s.replace(/[&<>"']/g, c => ({

        '&': '&amp;',

        '<': '&lt;',

        '>': '&gt;',

        '"': '&quot;',

        "'": '&#39;'

    }[c]));

}



// ════════════════════════════════════════════════════════════════════

// FETCH QUESTIONS FROM API (Database-backed with bias reduction)

// ════════════════════════════════════════════════════════════════════

async function initAssessment() {

    const container = document.getElementById('assessment-content');

    container.innerHTML = `

        <div class="loading-screen">

            <div class="loading-spinner"></div>

            <div class="loading-text">Loading your personalized 100-question assessment...</div>

            <div class="loading-text" style="font-size: 0.7rem; margin-top: 0.5rem;">✨ Questions are randomized per user to ensure fair assessment</div>

        </div>

    `;

    

    try {

        // Generate a random seed based on user ID and current date to ensure variety

        const seed = USER_ID + Math.floor(Date.now() / 86400000); // Changes daily

        

        // Fetch questions from API with bias reduction

        const response = await fetch(`api_questions.php?count=100&user_id=${USER_ID}&seed=${seed}`, {

            headers: {

                'X-Assessment-Token': TOKEN,

                'X-User-ID': USER_ID

            }

        });

        

        const data = await response.json();

        

        if (!data.success) {

            throw new Error(data.error || 'Failed to load questions');

        }

        

        questions = data.questions;

        assessmentSessionId = data.session_id;

        

        // Double-check option shuffling (client-side shuffle for extra randomness)

        questions = questions.map(q => ({

            ...q,

            options: shuffle([...q.options]) // Second shuffle to ensure no order bias

        }));

        

        // Final shuffle of question order

        questions = shuffle(questions);

        

        userAnswers = new Array(questions.length).fill(null);

        insightOpen = new Array(questions.length).fill(false);

        startTime = Date.now();

        

        // Initialize answer timing tracking

        questions.forEach((_, idx) => {

            answerStartTimes[idx] = null;

        });

        

        document.getElementById('badge-fresh').style.display = 'inline-block';

        startTimer();

        renderPage();

        

        // Log that assessment started (for analytics)

        await fetch('api_questions.php', {

            method: 'POST',

            headers: { 'Content-Type': 'application/json' },

            body: JSON.stringify({

                action: 'start_assessment',

                user_id: USER_ID,

                session_id: assessmentSessionId,

                question_ids: questions.map(q => q.id)

            })

        });

        

    } catch (err) {

        console.error('Failed to load questions:', err);

        container.innerHTML = `

            <div class="error-screen">

                <div class="error-icon">⚠️</div>

                <div class="error-title">Failed to Load Assessment</div>

                <p>${esc(err.message)}</p>

                <button class="btn btn-primary" onclick="location.reload()">Try Again</button>

            </div>

        `;

    }

}



// ════════════════════════════════════════════════════════════════════

// TIMER

// ════════════════════════════════════════════════════════════════════

function startTimer() {

    timerInterval = setInterval(() => {

        const elapsed = Math.floor((Date.now() - startTime) / 1000);

        const m = String(Math.floor(elapsed / 60)).padStart(2, '0');

        const s = String(elapsed % 60).padStart(2, '0');

        const el = document.getElementById('timer-display');

        if (el) el.textContent = `${m}:${s}`;

        

        // Warning when time is running low (30 min warning)

        if (elapsed > 2700 && elapsed < 2760) { // 45 minutes warning

            el.classList.add('warning');

        } else if (elapsed > 3540) { // 59 minutes - urgent

            el.classList.add('urgent');

        }

    }, 1000);

}



// ════════════════════════════════════════════════════════════════════

// RENDER PAGE

// ════════════════════════════════════════════════════════════════════

function renderPage() {

    const container = document.getElementById('assessment-content');

    const totalPages = Math.ceil(questions.length / PER_PAGE);

    const start = (currentPage - 1) * PER_PAGE;

    const end = Math.min(start + PER_PAGE, questions.length);

    const batch = questions.slice(start, end);

    const isLast = currentPage === totalPages;

    

    let html = `

        <div class="page-indicator">

            <div class="page-info">

                <span class="page-text">Questions ${start + 1}–${end} of ${questions.length}</span>

                <span class="page-hint ${isLast ? 'final' : ''}">${isLast ? '🎯 Last set — review and submit!' : 'Answer all questions then continue →'}</span>

            </div>

            ${!isLast ? `<button class="btn btn-primary" onclick="nextPage()">Next 10 Questions →</button>` : ''}

        </div>

        <div class="questions-container" id="q-container">`;

    

    batch.forEach((q, i) => {

        const idx = start + i;

        const answered = userAnswers[idx] !== null;

        const catLabel = q.category.toUpperCase();

        const categoryClass = q.category.replace('_', '-');

        

        html += `

            <div class="question-item ${answered ? 'answered' : ''}" id="qi-${idx}" data-question-id="${q.id}">

                <div class="q-header">

                    <span class="q-number">Q${idx + 1}</span>

                    <span class="q-category category-${q.category}">${catLabel}</span>

                    <span class="q-diff diff-${q.difficulty}">${q.difficulty}</span>

                </div>

                <div class="q-text">${esc(q.text)}</div>

                <div class="options-grid" id="opts-${idx}">`;

        

        q.options.forEach((opt, optIdx) => {

            const isSelected = userAnswers[idx] === opt;

            html += `

                <div class="option-radio ${isSelected ? 'selected' : ''}" 

                     onclick="selectAnswer(${idx}, '${esc(opt).replace(/'/g, "\\'")}', ${optIdx})">

                    <input type="radio" name="q${idx}" value="${esc(opt)}" ${isSelected ? 'checked' : ''}>

                    <label>${esc(opt)}</label>

                </div>`;

        });

        

        html += `

                </div>

                <div class="explanation-box ${insightOpen[idx] ? 'show' : ''}" id="exp-${idx}">

                    <strong>💡 SECURITY INSIGHT</strong>${esc(q.explanation)}

                </div>

            </div>`;

    });

    

    html += `</div>`;

    if (!isLast) {

        html += `<div class="next-footer"><button class="btn btn-primary" onclick="nextPage()">Continue → Next 10 Questions</button></div>`;

    }

    

    container.innerHTML = html;

    

    const nav = document.getElementById('nav-buttons');

    if (isLast) {

        nav.style.display = '';

        updateScorePreview();

    } else {

        nav.style.display = 'none';

    }

    

    updateHeader();

    

    // Search functionality

    const searchInput = document.getElementById('search-q');

    if (searchInput) {

        searchInput.oninput = e => {

            const term = e.target.value.toLowerCase();

            document.querySelectorAll('.question-item').forEach(el => {

                const text = el.querySelector('.q-text').textContent.toLowerCase();

                el.style.display = text.includes(term) ? '' : 'none';

            });

        };

    }

}



function nextPage() {

    const total = Math.ceil(questions.length / PER_PAGE);

    if (currentPage < total) {

        currentPage++;

        renderPage();

        document.getElementById('assessment-content').scrollIntoView({ behavior: 'smooth' });

    }

}



// ════════════════════════════════════════════════════════════════════

// SELECT ANSWER with timing tracking

// ════════════════════════════════════════════════════════════════════

function selectAnswer(idx, answer, optionPosition) {

    // Track time taken for this question

    if (answerStartTimes[idx] === null) {

        answerStartTimes[idx] = Date.now();

    }

    

    userAnswers[idx] = answer;

    

    // Store answer metadata for bias analysis

    if (!window.answerMetadata) window.answerMetadata = [];

    window.answerMetadata[idx] = {

        question_id: questions[idx].id,

        answer: answer,

        position: optionPosition,

        time_taken: Date.now() - (answerStartTimes[idx] || startTime)

    };

    

    // Show explanation

    const exp = document.getElementById(`exp-${idx}`);

    if (exp && insightOpen[idx]) exp.classList.add('show');

    

    // Mark question card

    const qi = document.getElementById(`qi-${idx}`);

    if (qi) qi.classList.add('answered');

    

    updateHeader();

    if (currentPage === Math.ceil(questions.length / PER_PAGE)) updateScorePreview();

}



// ════════════════════════════════════════════════════════════════════

// HEADER UPDATE

// ════════════════════════════════════════════════════════════════════

function updateHeader() {

    const answered = userAnswers.filter(a => a !== null).length;

    const pct = Math.round((answered / questions.length) * 100);

    const el = document.getElementById('answered-count');

    const pp = document.getElementById('progress-pct');

    const fill = document.getElementById('progress-fill');

    if (el) el.textContent = `${answered} / ${questions.length} answered`;

    if (pp) pp.textContent = `${pct}%`;

    if (fill) fill.style.width = `${pct}%`;

}



// ════════════════════════════════════════════════════════════════════

// SCORING

// ════════════════════════════════════════════════════════════════════

function calcScore() {

    const correct = questions.filter((q, i) => userAnswers[i] === q.correct).length;

    return Math.round((correct / questions.length) * 100);

}



function calcCatScores() {

    const cats = {};

    questions.forEach((q, i) => {

        const cat = q.category;

        if (!cats[cat]) cats[cat] = { total: 0, correct: 0 };

        cats[cat].total++;

        if (userAnswers[i] === q.correct) cats[cat].correct++;

    });

    const out = {};

    for (const [c, d] of Object.entries(cats)) {

        out[c] = Math.round((d.correct / d.total) * 100);

    }

    return out;

}



function getRank(score) {

    if (score >= 90) return { letter: 'A', label: 'Security Champion', color: 'var(--green)' };

    if (score >= 80) return { letter: 'B', label: 'Strong Awareness', color: 'var(--blue)' };

    if (score >= 70) return { letter: 'C', label: 'Solid Foundation', color: 'var(--teal)' };

    if (score >= 50) return { letter: 'D', label: 'Needs Improvement', color: 'var(--yellow)' };

    return { letter: 'F', label: 'Critical — Immediate Action', color: 'var(--red)' };

}



function updateScorePreview() {

    const answered = userAnswers.filter(a => a !== null).length;

    const el = document.getElementById('score-preview-txt');

    if (el) el.textContent = `${answered} of ${questions.length} answered`;

}



// ════════════════════════════════════════════════════════════════════

// SUBMIT FLOW with bias analytics

// ════════════════════════════════════════════════════════════════════

function submitAssessment() {

    const unanswered = userAnswers.filter(a => a === null).length;

    if (unanswered > 0) {

        const firstIdx = userAnswers.findIndex(a => a === null);

        const page = Math.floor(firstIdx / PER_PAGE) + 1;

        if (page !== currentPage) {

            currentPage = page;

            renderPage();

        }

        document.getElementById(`qi-${firstIdx}`)?.scrollIntoView({ behavior: 'smooth' });

        showModal('Incomplete Assessment', `

            <div style="text-align:center;padding:1rem">

                <div style="font-size:2.5rem;margin-bottom:.75rem">⚠️</div>

                <p style="margin-bottom:1rem">You have <strong>${unanswered}</strong> unanswered question(s).<br>Please answer all 100 questions before submitting.</p>

                <button class="btn btn-primary" onclick="closeModal()">Go Back</button>

            </div>`);

        return;

    }

    

    const score = calcScore();

    const rank = getRank(score);

    const catScores = calcCatScores();

    

    showModal('Confirm Submission', `

        <div class="rank-preview">

            <div class="rank-letter" style="color:${rank.color}">${rank.letter}</div>

            <div class="rank-score">${score}%</div>

            <div class="rank-label">${rank.label}</div>

        </div>

        <div class="cat-grid">

            ${Object.entries(catScores).map(([c, s]) => `

                <div class="cat-item">

                    <div class="cat-name">${c.replace('_', ' ')}</div>

                    <div class="cat-val" style="color:${s >= 70 ? 'var(--green)' : s >= 50 ? 'var(--yellow)' : 'var(--red)'}">${s}%</div>

                </div>`).join('')}

        </div>

        <div style="display:flex;gap:.75rem;margin-top:1.25rem;justify-content:center">

            <button class="btn btn-success" onclick="confirmSubmit()">

                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="20 6 9 17 4 12"/></svg>

                Confirm & Submit

            </button>

            <button class="btn btn-secondary" onclick="closeModal()">Review Answers</button>

        </div>`);

}



async function confirmSubmit() {

    closeModal();

    const score = calcScore();

    const rank = getRank(score);

    const catScores = calcCatScores();

    const timeSpent = Math.floor((Date.now() - startTime) / 1000);

    

    // Build per-question answers payload with timing data for bias analysis

    const answersPayload = questions.map((q, i) => ({

        question_id: q.id,

        question_text: q.text,

        user_answer: userAnswers[i] ?? '',

        correct_answer: q.correct,

        is_correct: userAnswers[i] === q.correct ? 1 : 0,

        category: q.category,

        position: window.answerMetadata && window.answerMetadata[i] ? window.answerMetadata[i].position : -1,

        time_taken: window.answerMetadata && window.answerMetadata[i] ? window.answerMetadata[i].time_taken : 0

    }));

    

    // Log answers for bias detection

    try {

        await fetch('api_questions.php', {

            method: 'POST',

            headers: { 'Content-Type': 'application/json' },

            body: JSON.stringify({

                action: 'log_answers',

                user_id: USER_ID,

                session_id: assessmentSessionId,

                answers: answersPayload

            })

        });

    } catch (err) {

        console.warn('Failed to log answers for analytics:', err);

    }

    

    // Cache to localStorage for result.php fallback

    localStorage.setItem('lastAssessment', JSON.stringify({

        score,

        rank: rank.letter,

        categoryScores: catScores,

        date: new Date().toISOString(),

        timeSpent,

        totalQuestions: questions.length,

        wrongCount: answersPayload.filter(a => !a.is_correct).length,

        answers: answersPayload,

        session_id: assessmentSessionId

    }));

    

    const btn = document.getElementById('submit-btn');

    btn.textContent = 'Saving…';

    btn.disabled = true;

    

    const fd = new FormData();

    fd.append('score', score);

    fd.append('rank', rank.letter);

    fd.append('password_score', catScores.password ?? 0);

    fd.append('phishing_score', catScores.phishing ?? 0);

    fd.append('device_score', catScores.device ?? 0);

    fd.append('network_score', catScores.network ?? 0);

    fd.append('social_engineering_score', catScores.social_engineering ?? 0);

    fd.append('data_handling_score', catScores.data_handling ?? 0);

    fd.append('time_spent', timeSpent);

    fd.append('questions_answered', questions.length);

    fd.append('total_questions', questions.length);

    fd.append('assessment_token', TOKEN);

    fd.append('answers_json', JSON.stringify(answersPayload));

    fd.append('session_id', assessmentSessionId);

    

    try {

        const res = await fetch('save_assessment.php', { method: 'POST', body: fd });

        const data = await res.json();

        

        if (data.success) {

            clearInterval(timerInterval);

            window.location.href = 'result.php';

        } else {

            alert('Save error: ' + (data.error || 'Unknown error'));

            btn.textContent = 'Submit Assessment';

            btn.disabled = false;

        }

    } catch (err) {

        alert('Network error. Please try again.\n' + err.message);

        btn.textContent = 'Submit Assessment';

        btn.disabled = false;

    }

}



// ════════════════════════════════════════════════════════════════════

// UI UTILITIES

// ════════════════════════════════════════════════════════════════════

function showModal(title, bodyHtml) {

    document.getElementById('modal-title').textContent = title;

    document.getElementById('modal-body').innerHTML = bodyHtml;

    document.getElementById('modal-overlay').classList.remove('hidden');

}



function closeModal() {

    document.getElementById('modal-overlay').classList.add('hidden');

}



function toggleSidebar() {

    document.getElementById('sidebar').classList.toggle('collapsed');

    localStorage.setItem('cs_sb', document.getElementById('sidebar').classList.contains('collapsed') ? '1' : '0');

}



function toggleTheme() {

    const dark = document.documentElement.getAttribute('data-theme') !== 'dark';

    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');

    localStorage.setItem('cs_th', dark ? 'dark' : 'light');

    document.getElementById('tmoon').style.display = dark ? '' : 'none';

    document.getElementById('tsun').style.display = dark ? 'none' : '';

}



function doLogout() {

    showModal('Confirm Logout', `

        <div style="text-align:center;padding:1rem">

            <div style="font-size:3rem;margin-bottom:1rem">🚪</div>

            <h3 style="margin-bottom:.5rem">Sign out of CyberShield?</h3>

            <p style="color:var(--muted2);margin-bottom:1.5rem">You will be redirected to the landing page.</p>

            <div style="display:flex;gap:.75rem;justify-content:center">

                <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>

                <button class="btn btn-primary" onclick="window.location.href='../landingpage.php'">Sign Out</button>

            </div>

        </div>`);

}



// ── Countdown timer for cooldown screen ──────────────────────────────

<?php if (!$can_take && $next_available_date): ?>

(function countdown() {

    const next = new Date('<?php echo $next_available_date->format('Y-m-d H:i:s'); ?>');

    function tick() {

        const diff = next - Date.now();

        const el = document.getElementById('countdown-timer');

        if (!el) return;

        if (diff <= 0) {

            el.innerHTML = '✅ Assessment available now — <a href="" style="color:var(--blue)">click to refresh</a>';

            return;

        }

        const m = Math.floor(diff / 60000), s = Math.floor((diff % 60000) / 1000);

        el.textContent = `⏱️ Available in ${m}m ${s}s`;

        setTimeout(tick, 1000);

    }

    tick();

})();

<?php endif; ?>



// ════════════════════════════════════════════════════════════════════

// BOOT

// ════════════════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', () => {

    const th = localStorage.getItem('cs_th') || 'dark';

    document.documentElement.setAttribute('data-theme', th);

    document.getElementById('tmoon').style.display = th === 'dark' ? '' : 'none';

    document.getElementById('tsun').style.display = th === 'dark' ? 'none' : '';

    if (localStorage.getItem('cs_sb') === '1') document.getElementById('sidebar').classList.add('collapsed');

    const d = document.getElementById('tb-date');

    if (d) d.textContent = new Date().toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });

    

    <?php if ($can_take): ?>

    initAssessment();

    <?php endif; ?>

});

</script>

</body>

</html> 