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

// Get all assessments for certificate eligibility (score >= 70 qualifies)
$cert_query = "SELECT a.*, u.store_name as vendor_name
    FROM assessments a
    JOIN users u ON a.vendor_id = u.id
    WHERE u.id = :user_id
    ORDER BY a.created_at DESC";
$stmt = $db->prepare($cert_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$all_assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter assessments that qualify for a certificate (score >= 70)
$eligible = array_filter($all_assessments, fn($a) => (float)($a['score'] ?? 0) >= 70);

// Get best assessment
$best_assessment = null;
if (!empty($all_assessments)) {
  usort($all_assessments, fn($a, $b) => $b['score'] <=> $a['score']);
  $best_assessment = $all_assessments[0];
}

// Count stats
$total_certs = count($eligible);

// Get sent certificates for this user
$sent_certificates = [];
try {
    $stmt = $db->prepare("
        SELECT sc.*, u.full_name, u.email, u.store_name
        FROM sent_certificates sc
        LEFT JOIN users u ON sc.user_id = u.id
        WHERE sc.recipient_email = :user_email OR sc.user_id = :user_id
        ORDER BY sc.sent_at DESC
    ");
    $stmt->bindParam(':user_email', $user['email']);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $sent_certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching sent certificates: " . $e->getMessage());
    $sent_certificates = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>Certificates — CyberShield</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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
    body { font-family: var(--font); background: var(--bg); color: var(--text); transition: background .18s, color .18s }

    .bg-grid {
      position: fixed; inset: 0; pointer-events: none; z-index: 0;
      background-image: linear-gradient(rgba(59,139,255,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(59,139,255,.025) 1px,transparent 1px);
      background-size: 40px 40px
    }

    #app { display: flex; height: 100vh; position: relative; z-index: 1 }

    /* ── Sidebar ── */
    #sidebar {
      width: 228px; min-width: 228px; background: var(--bg2); border-right: 1px solid var(--border);
      display: flex; flex-direction: column; transition: width .18s,min-width .18s;
      overflow: hidden; z-index: 10; flex-shrink: 0
    }
    #sidebar.collapsed { width: 58px; min-width: 58px }
    .sb-brand { display: flex; align-items: center; gap: .75rem; padding: 1rem .9rem .9rem; border-bottom: 1px solid var(--border); flex-shrink: 0 }
    .shield { width: 34px; height: 34px; background: linear-gradient(135deg,var(--blue),var(--purple)); border-radius: 9px; display: grid; place-items: center; flex-shrink: 0; box-shadow: 0 0 16px rgba(59,139,255,.3) }
    .sb-brand-text { flex: 1; overflow: hidden; white-space: nowrap }
    .sb-brand-text h2 { font-family: var(--display); font-size: .95rem; font-weight: 700; letter-spacing: 1px }
    .sb-brand-text .badge { font-family: var(--mono); font-size: .55rem; letter-spacing: 1.5px; text-transform: uppercase; background: rgba(16,217,130,.12); color: var(--green); border: 1px solid rgba(16,217,130,.2); border-radius: 4px; padding: .08rem .38rem; display: inline-block; margin-top: .1rem }
    .sb-toggle { width: 28px; height: 28px; background: rgba(59,139,255,0.1); border: 1px solid var(--blue); border-radius: 6px; cursor: pointer; color: var(--blue); display: grid; place-items: center; flex-shrink: 0; transition: var(--t); z-index: 100 }
    .sb-toggle:hover { background: rgba(59,139,255,0.2); transform: scale(1.05) }
    #sidebar.collapsed .sb-toggle svg { transform: rotate(180deg) }
    .sb-section { flex: 1; overflow-y: auto; overflow-x: hidden; padding: .65rem 0 }
    .sb-section::-webkit-scrollbar { width: 3px }
    .sb-section::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 2px }
    .sb-label { font-family: var(--mono); font-size: .55rem; letter-spacing: 2px; text-transform: uppercase; color: var(--muted); padding: .5rem .9rem .25rem; white-space: nowrap; overflow: hidden }
    #sidebar.collapsed .sb-label { opacity: 0 }
    .sb-divider { height: 1px; background: var(--border); margin: .5rem .9rem }
    .sb-item { display: flex; align-items: center; gap: .65rem; padding: .52rem .9rem; cursor: pointer; color: var(--muted2); font-size: .82rem; font-weight: 500; text-decoration: none; transition: var(--t); white-space: nowrap; overflow: hidden; position: relative }
    .sb-item:hover { background: rgba(59,139,255,.07); color: var(--text) }
    .sb-item.active { background: rgba(245,183,49,.1); color: var(--yellow) }
    .sb-item.active::before { content: ''; position: absolute; left: 0; top: 20%; bottom: 20%; width: 3px; background: var(--yellow); border-radius: 0 3px 3px 0 }
    .sb-icon { display: flex; align-items: center; justify-content: center; width: 18px; flex-shrink: 0 }
    .sb-text { overflow: hidden }
    #sidebar.collapsed .sb-text { display: none }
    .sb-footer { border-top: 1px solid var(--border); padding: .75rem .9rem; flex-shrink: 0 }
    .sb-user { display: flex; align-items: center; gap: .65rem; overflow: hidden }
    .sb-avatar { width: 30px; height: 30px; border-radius: 8px; background: linear-gradient(135deg,var(--blue),var(--purple)); color: #fff; display: grid; place-items: center; font-size: .75rem; font-weight: 700; flex-shrink: 0; font-family: var(--display) }
    .sb-user-info { overflow: hidden; white-space: nowrap }
    .sb-user-info p { font-size: .82rem; font-weight: 600 }
    .sb-user-info span { font-size: .68rem; color: var(--muted2) }
    #sidebar.collapsed .sb-user-info { display: none }
    .btn-sb-logout { display: flex; align-items: center; gap: .35rem; margin-top: .65rem; width: 100%; background: rgba(255,59,92,.08); border: 1px solid rgba(255,59,92,.18); color: var(--red); font-family: var(--font); font-size: .75rem; font-weight: 600; border-radius: 7px; padding: .42rem .8rem; cursor: pointer; transition: var(--t) }
    .btn-sb-logout:hover { background: rgba(255,59,92,.15) }
    #sidebar.collapsed .btn-sb-logout span { display: none }

    /* ── Main ── */
    #main { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0 }

    /* ── Topbar ── */
    .topbar { height: 54px; min-height: 54px; display: flex; align-items: center; justify-content: space-between; padding: 0 1.5rem; border-bottom: 1px solid var(--border); background: var(--bg2); flex-shrink: 0 }
    .tb-bc { display: flex; align-items: center; gap: .4rem; font-size: .78rem; color: var(--muted2) }
    .tb-app { color: var(--muted2) }
    .tb-title { color: var(--text); font-weight: 600 }
    .tb-sub { font-size: .72rem; color: var(--muted); margin-top: .12rem }
    .tb-right { display: flex; align-items: center; gap: .65rem }
    .tb-date { font-family: var(--mono); font-size: .7rem; color: var(--muted2) }
    .tb-divider { width: 1px; height: 20px; background: var(--border2) }
    .tb-icon-btn { width: 30px; height: 30px; border-radius: 8px; border: 1px solid var(--border2); background: none; color: var(--muted2); cursor: pointer; display: grid; place-items: center; transition: var(--t) }
    .tb-icon-btn:hover { border-color: var(--blue); color: var(--blue) }
    .tb-admin { display: flex; align-items: center; gap: .5rem; text-decoration: none; padding: .35rem .65rem; border-radius: 8px; border: 1px solid var(--border2); transition: var(--t) }
    .tb-admin:hover { border-color: var(--blue) }
    .tb-admin-av { width: 24px; height: 24px; border-radius: 6px; background: linear-gradient(135deg,var(--blue),var(--purple)); color: #fff; display: grid; place-items: center; font-size: .68rem; font-weight: 700; font-family: var(--display) }
    .tb-admin-info { display: flex; flex-direction: column }
    .tb-admin-name { font-size: .75rem; font-weight: 600; color: var(--text) }
    .tb-admin-role { font-size: .62rem; color: var(--muted2) }

    /* ── Content ── */
    .content { flex: 1; overflow-y: auto; padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: .75rem }
    .content::-webkit-scrollbar { width: 5px }
    .content::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 3px }

    .sec-hdr { margin-bottom: .15rem }
    .sec-hdr h2 { font-family: var(--display); font-size: 1.05rem; font-weight: 700 }
    .sec-hdr p { font-size: .78rem; color: var(--muted2); margin-top: .2rem }

    .card { background: var(--card-bg); border: 1px solid var(--border2); border-radius: 12px; padding: 1.1rem 1.25rem }

    /* ── Stats row ── */
    .stats-row { display: grid; grid-template-columns: repeat(3,1fr); gap: .75rem }
    .stat-card { border-top: 2px solid var(--accent,var(--blue)); padding: .85rem 1rem }
    .si { width: 30px; height: 30px; border-radius: 8px; display: grid; place-items: center; margin-bottom: .55rem }
    .slabel { font-family: var(--mono); font-size: .6rem; letter-spacing: 1px; text-transform: uppercase; color: var(--muted2); margin-bottom: .2rem }
    .sval { font-family: var(--display); font-size: 1.7rem; font-weight: 700; line-height: 1 }
    .ssub { font-size: .7rem; color: var(--muted2); margin-top: .25rem }

    /* ── Buttons ── */
    .btn { border: none; border-radius: 8px; cursor: pointer; font-family: var(--font); font-weight: 600; transition: var(--t); display: inline-flex; align-items: center; gap: .4rem; text-decoration: none }
    .btn-p { background: var(--blue); color: #fff; padding: .52rem 1rem; font-size: .8rem }
    .btn-p:hover { opacity: .88 }
    .btn-s { background: transparent; border: 1px solid var(--border2); color: var(--muted2); padding: .42rem .8rem; font-size: .75rem }
    .btn-s:hover { border-color: var(--blue); color: var(--blue) }
    .btn-sm { padding: .38rem .75rem; font-size: .75rem }
    .btn-dl { background: linear-gradient(135deg,var(--yellow),var(--orange)); color: #0a0a0a; padding: .42rem .85rem; font-size: .75rem; font-weight: 700; border-radius: 8px }
    .btn-dl:hover { opacity: .9; transform: translateY(-1px) }

    /* ── Certificate grid ── */
    .cert-grid { display: grid; grid-template-columns: repeat(auto-fill,minmax(320px,1fr)); gap: 1rem }

    /* ── Certificate card ── */
    .cert-card {
      background: var(--card-bg); border: 1px solid var(--border2); border-radius: 14px;
      overflow: hidden; transition: var(--t); position: relative
    }
    .cert-card:hover { border-color: var(--yellow); transform: translateY(-2px); box-shadow: 0 8px 30px rgba(245,183,49,.1) }
    .cert-card-header {
      background: linear-gradient(135deg,rgba(245,183,49,.15),rgba(255,140,66,.1));
      border-bottom: 1px solid rgba(245,183,49,.15);
      padding: 1.1rem 1.25rem;
      display: flex; align-items: center; gap: .85rem
    }
    .cert-medal { width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg,var(--yellow),var(--orange)); display: grid; place-items: center; flex-shrink: 0; box-shadow: 0 0 14px rgba(245,183,49,.4) }
    .cert-title-group { flex: 1; min-width: 0 }
    .cert-title { font-family: var(--display); font-size: .92rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis }
    .cert-subtitle { font-size: .7rem; color: var(--muted2); margin-top: .15rem }
    .cert-rank-badge { font-family: var(--mono); font-size: .7rem; font-weight: 700; padding: .22rem .55rem; border-radius: 6px; flex-shrink: 0 }
    .cert-rank-A { background: rgba(16,217,130,.15); color: var(--green); border: 1px solid rgba(16,217,130,.3) }
    .cert-rank-B { background: rgba(59,139,255,.15); color: var(--blue); border: 1px solid rgba(59,139,255,.3) }
    .cert-rank-C { background: rgba(245,183,49,.15); color: var(--yellow); border: 1px solid rgba(245,183,49,.3) }
    .cert-rank-D { background: rgba(255,59,92,.15); color: var(--red); border: 1px solid rgba(255,59,92,.3) }

    .cert-body { padding: 1rem 1.25rem }
    .cert-row { display: flex; align-items: center; justify-content: space-between; padding: .38rem 0; border-bottom: 1px solid var(--border) }
    .cert-row:last-child { border-bottom: none }
    .cert-row-label { font-size: .72rem; color: var(--muted2); font-family: var(--mono); text-transform: uppercase; letter-spacing: .5px }
    .cert-row-val { font-size: .8rem; font-weight: 600; color: var(--text) }

    .score-pill { display: inline-flex; align-items: center; gap: .35rem; font-size: .8rem; font-weight: 700; padding: .18rem .6rem; border-radius: 20px }
    .score-A { background: rgba(16,217,130,.15); color: var(--green) }
    .score-B { background: rgba(59,139,255,.15); color: var(--blue) }
    .score-C { background: rgba(245,183,49,.15); color: var(--yellow) }
    .score-D { background: rgba(255,59,92,.15); color: var(--red) }

    .cert-footer { padding: .85rem 1.25rem; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: .6rem }

    /* ── Certificate preview modal ── */
    .mo { position: fixed; inset: 0; background: rgba(0,0,0,.7); display: grid; place-items: center; z-index: 200; backdrop-filter: blur(6px) }
    .mo.hidden { display: none }
    .modal { background: var(--bg3); border: 1px solid var(--border2); border-radius: 14px; width: min(94vw,760px); max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,.6); animation: su .2s ease }
    @keyframes su { from { opacity:0;transform:translateY(20px) } to { opacity:1;transform:none } }
    .mhdr { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border) }
    .mhdr h3 { font-family: var(--display); font-size: 1rem; font-weight: 700 }
    .mcl { width: 28px; height: 28px; border-radius: 7px; border: 1px solid var(--border2); background: none; color: var(--muted2); cursor: pointer; display: grid; place-items: center; transition: var(--t) }
    .mcl:hover { border-color: var(--red); color: var(--red) }
    .mbdy { padding: 1.5rem }

    /* ── Certificate print template ── */
    #cert-print-template {
      width: 700px; padding: 48px 56px; background: #fff; color: #0a0a0a;
      border: 3px solid #F5B731; border-radius: 16px; position: relative; font-family: 'Inter', sans-serif;
      box-shadow: inset 0 0 0 6px rgba(245,183,49,.15)
    }
    #cert-print-template .cert-watermark {
      position: absolute; inset: 0; display: grid; place-items: center; opacity: .04; pointer-events: none
    }
    #cert-print-template .cert-logo-area { display: flex; align-items: center; gap: 10px; margin-bottom: 28px }
    #cert-print-template .cert-shield { width: 42px; height: 42px; background: linear-gradient(135deg,#3B8BFF,#7B72F0); border-radius: 11px; display: grid; place-items: center }
    #cert-print-template .cert-brand { font-family: 'Syne',sans-serif; font-size: 1.2rem; font-weight: 800; letter-spacing: 2px; color: #0a0a0a }
    #cert-print-template .cert-headline { font-family: 'Syne',sans-serif; font-size: 2rem; font-weight: 800; color: #0a0a0a; margin-bottom: 4px; line-height: 1.1 }
    #cert-print-template .cert-subline { font-size: .85rem; color: #475569; margin-bottom: 28px }
    #cert-print-template .cert-awardee { font-family: 'Syne',sans-serif; font-size: 1.55rem; font-weight: 700; color: #1a1a2e; border-bottom: 2px solid #F5B731; display: inline-block; padding-bottom: 4px; margin-bottom: 8px }
    #cert-print-template .cert-for { font-size: .82rem; color: #64748b; margin-bottom: 24px }
    #cert-print-template .cert-details-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 28px }
    #cert-print-template .cert-detail-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px }
    #cert-print-template .cert-detail-label { font-size: .6rem; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 3px; font-family: 'JetBrains Mono',monospace }
    #cert-print-template .cert-detail-val { font-size: .95rem; font-weight: 700; color: #0a0a0a }
    #cert-print-template .cert-seal { display: flex; align-items: center; gap: 12px; padding-top: 20px; border-top: 1px solid #e2e8f0 }
    #cert-print-template .cert-seal-circle { width: 56px; height: 56px; border-radius: 50%; background: linear-gradient(135deg,#F5B731,#FF8C42); display: grid; place-items: center; flex-shrink: 0; box-shadow: 0 0 0 4px rgba(245,183,49,.2) }
    #cert-print-template .cert-seal-text { font-size: .75rem; color: #64748b }
    #cert-print-template .cert-seal-text strong { display: block; font-size: .85rem; color: #0a0a0a }
    #cert-print-template .cert-id { font-family: 'JetBrains Mono',monospace; font-size: .6rem; color: #94a3b8; margin-top: 4px }

    /* ── Empty state ── */
    .empty-state { text-align: center; padding: 3.5rem 1.5rem; color: var(--muted2) }
    .empty-icon { width: 60px; height: 60px; border-radius: 50%; background: rgba(245,183,49,.1); display: grid; place-items: center; margin: 0 auto 1rem; color: var(--yellow) }
    .empty-state h3 { font-family: var(--display); font-size: 1rem; color: var(--text); margin-bottom: .35rem }
    .empty-state p { font-size: .8rem; max-width: 320px; margin: 0 auto .85rem }

    /* ── Toast ── */
    #toast-c { position: fixed; bottom: 1.25rem; right: 1.25rem; display: flex; flex-direction: column; gap: .5rem; z-index: 300 }
    .toast { background: var(--bg3); border: 1px solid var(--border2); border-radius: 9px; padding: .75rem 1rem; font-size: .82rem; box-shadow: var(--shadow); display: flex; align-items: center; gap: .6rem; animation: sl .2s ease; min-width: 220px }
    @keyframes sl { from { opacity:0;transform:translateX(20px) } to { opacity:1;transform:none } }
    .ti { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0 }
  </style>
</head>

<body>
  <div class="bg-grid"></div>
  <div id="app">

    <!-- ─── Sidebar ─── -->
    <aside id="sidebar">
      <div class="sb-brand">
        <div class="shield"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
        <div class="sb-brand-text">
          <h2>CyberShield</h2><span class="badge">Client Portal</span>
        </div>
        <button class="sb-toggle" onclick="toggleSidebar()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
      </div>
      <div class="sb-section">
        <div class="sb-label">Navigation</div>
        <a class="sb-item" href="index.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.2"/><rect x="14" y="3" width="7" height="7" rx="1.2"/><rect x="3" y="14" width="7" height="7" rx="1.2"/><rect x="14" y="14" width="7" height="7" rx="1.2"/></svg></span><span class="sb-text">Dashboard</span></a>
        <a class="sb-item" href="assessment.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span><span class="sb-text">Take Assessment</span></a>
        <a class="sb-item" href="result.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span><span class="sb-text">Results</span></a>
        <a class="sb-item" href="review.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6l4-4 4 4"/><path d="M12 2v13"/><path d="M20 21H4"/><path d="M17 12h3v9"/><path d="M4 12h3v9"/></svg></span><span class="sb-text">Review</span></a>
        <a class="sb-item active" href="certificates.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg></span><span class="sb-text">Certificates</span></a>
        <div class="sb-divider"></div>
        <div class="sb-label">Account</div>
        <a class="sb-item" href="profile.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span><span class="sb-text">Profile</span></a>
        <a class="sb-item" href="security-tips.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span><span class="sb-text">Security Tips</span></a>
        <a class="sb-item" href="terms.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span><span class="sb-text">Terms & Privacy</span></a>
      </div>
      <div class="sb-footer">
        <div class="sb-user">
          <div class="sb-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
          <div class="sb-user-info">
            <p><?php echo htmlspecialchars($user['full_name']); ?></p><span>Vendor Account</span>
          </div>
        </div>
        <button class="btn-sb-logout" onclick="doLogout()">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          <span>Sign Out</span>
        </button>
      </div>
    </aside>

    <!-- ─── Main ─── -->
    <div id="main">
      <div class="topbar">
        <div>
          <div class="tb-bc">
            <span class="tb-app">CyberShield</span>
            <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 4l4 4-4 4"/></svg>
            <span class="tb-title">Certificates</span>
          </div>
          <p class="tb-sub">Your earned cybersecurity achievement certificates</p>
        </div>
        <div class="tb-right">
          <span class="tb-date" id="tb-date"></span>
          <div class="tb-divider"></div>
          <button class="tb-icon-btn" onclick="toggleTheme()" title="Toggle theme">
            <svg id="tmoon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            <svg id="tsun" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:none"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>
          </button>
          <div class="tb-divider"></div>
          <a class="tb-admin" href="#">
            <div class="tb-admin-av"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
            <div class="tb-admin-info">
              <span class="tb-admin-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
              <span class="tb-admin-role">Vendor</span>
            </div>
          </a>
        </div>
      </div>

      <div class="content">

        <!-- Page header -->
        <div class="sec-hdr">
          <h2>Your Certificates</h2>
          <p>Certificates are awarded for assessments with a score of 70% or above.</p>
        </div>

        <!-- Stats row -->
        <div class="stats-row">
          <div class="card stat-card" style="--accent:var(--yellow)">
            <div class="si" style="background:rgba(245,183,49,.12);color:var(--yellow)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
            </div>
            <div class="slabel">Certificates Earned</div>
            <div class="sval"><?php echo $total_certs; ?></div>
            <div class="ssub">Score ≥ 70% qualifies</div>
          </div>
          <div class="card stat-card" style="--accent:var(--green)">
            <div class="si" style="background:rgba(16,217,130,.12);color:var(--green)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <div class="slabel">Best Score</div>
            <div class="sval"><?php echo $best_assessment ? $best_assessment['score'] . '%' : '--'; ?></div>
            <div class="ssub">Rank <?php echo $best_assessment['rank'] ?? '--'; ?></div>
          </div>
          <div class="card stat-card" style="--accent:var(--blue)">
            <div class="si" style="background:rgba(59,139,255,.12);color:var(--blue)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div class="slabel">Total Assessments</div>
            <div class="sval"><?php echo count($all_assessments); ?></div>
            <div class="ssub">All time</div>
          </div>
        </div>

        <!-- Certificate cards -->
        <?php if (!empty($eligible)): ?>
          <div class="cert-grid">
            <?php foreach ($eligible as $a): 
              $rank = $a['rank'] ?? 'C';
              $score = (float)($a['score'] ?? 0);
              $rankLabel = ['A'=>'Excellent','B'=>'Good','C'=>'Satisfactory','D'=>'Needs Work'][$rank] ?? 'Certified';
              $certId = 'CS-' . strtoupper(substr(md5($a['id'] . $a['vendor_id']), 0, 8));
              $dateStr = date('F j, Y', strtotime($a['created_at']));
            ?>
            <div class="cert-card">
              <div class="cert-card-header">
                <div class="cert-medal">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
                </div>
                <div class="cert-title-group">
                  <div class="cert-title">Cybersecurity Certificate</div>
                  <div class="cert-subtitle"><?php echo $dateStr; ?></div>
                </div>
                <span class="cert-rank-badge cert-rank-<?php echo $rank; ?>"><?php echo $rank; ?></span>
              </div>
              <div class="cert-body">
                <div class="cert-row">
                  <span class="cert-row-label">Recipient</span>
                  <span class="cert-row-val"><?php echo htmlspecialchars($user['full_name']); ?></span>
                </div>
                <div class="cert-row">
                  <span class="cert-row-label">Store / Vendor</span>
                  <span class="cert-row-val"><?php echo htmlspecialchars($a['vendor_name'] ?? $user['store_name'] ?? '--'); ?></span>
                </div>
                <div class="cert-row">
                  <span class="cert-row-label">Overall Score</span>
                  <span class="score-pill score-<?php echo $rank; ?>"><?php echo $score; ?>%</span>
                </div>
                <div class="cert-row">
                  <span class="cert-row-label">Performance</span>
                  <span class="cert-row-val"><?php echo $rankLabel; ?></span>
                </div>
                <div class="cert-row">
                  <span class="cert-row-label">Certificate ID</span>
                  <span class="cert-row-val" style="font-family:var(--mono);font-size:.7rem;color:var(--muted2)"><?php echo $certId; ?></span>
                </div>
              </div>
              <div class="cert-footer">
                <button class="btn btn-s btn-sm" onclick="viewCert(<?php echo htmlspecialchars(json_encode([
                  'name' => $user['full_name'],
                  'store' => $a['vendor_name'] ?? $user['store_name'] ?? '',
                  'score' => $score,
                  'rank' => $rank,
                  'rankLabel' => $rankLabel,
                  'date' => $dateStr,
                  'certId' => $certId,
                  'password' => $a['password_score'] ?? 0,
                  'phishing' => $a['phishing_score'] ?? 0,
                  'device' => $a['device_score'] ?? 0,
                  'network' => $a['network_score'] ?? 0,
                ])); ?>)">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  Preview
                </button>
                <button class="btn btn-dl btn-sm" onclick="downloadCert(<?php echo htmlspecialchars(json_encode([
                  'name' => $user['full_name'],
                  'store' => $a['vendor_name'] ?? $user['store_name'] ?? '',
                  'score' => $score,
                  'rank' => $rank,
                  'rankLabel' => $rankLabel,
                  'date' => $dateStr,
                  'certId' => $certId,
                  'password' => $a['password_score'] ?? 0,
                  'phishing' => $a['phishing_score'] ?? 0,
                  'device' => $a['device_score'] ?? 0,
                  'network' => $a['network_score'] ?? 0,
                ])); ?>)">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                  Download PDF
                </button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- Sent Certificates Section -->
        <?php if (!empty($sent_certificates)): ?>
          <div class="sec-hdr">
            <h3>Sent Certificates</h3>
            <p>Certificates that have been sent to your email address.</p>
          </div>
          <div class="cert-grid">
            <?php foreach ($sent_certificates as $cert): 
              $rank = $cert['rank'] ?? 'C';
              $score = (int)($cert['score'] ?? 0);
              $rankLabel = ['A'=>'Excellent','B'=>'Good','C'=>'Satisfactory','D'=>'Needs Work'][$rank] ?? 'Certified';
              $dateStr = date('F j, Y', strtotime($cert['sent_at']));
            ?>
            <div class="cert-card">
              <div class="cert-card-header">
                <div class="cert-medal">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
                </div>
                <div class="cert-title-group">
                  <div class="cert-title"><?php echo htmlspecialchars($cert['cert_type'] ?? 'Certificate'); ?></div>
                  <div class="cert-subtitle"><?php echo $dateStr; ?></div>
                </div>
                <span class="cert-rank-badge cert-rank-<?php echo $rank; ?>"><?php echo $rank; ?></span>
              </div>
              <div class="cert-body">
                <div class="cert-row">
                  <span class="cert-row-label">Recipient</span>
                  <span class="cert-row-val"><?php echo htmlspecialchars($cert['recipient_name'] ?? $user['full_name']); ?></span>
                </div>
                <div class="cert-row">
                  <span class="cert-row-label">Email</span>
                  <span class="cert-row-val"><?php echo htmlspecialchars($cert['recipient_email']); ?></span>
                </div>
                <div class="cert-row">
                  <span class="cert-row-label">Store / Vendor</span>
                  <span class="cert-row-val"><?php echo htmlspecialchars($cert['store_name'] ?? $user['store_name'] ?? '--'); ?></span>
                </div>
                <div class="cert-row">
                  <span class="cert-row-label">Overall Score</span>
                  <span class="score-pill score-<?php echo $rank; ?>"><?php echo $score; ?>%</span>
                </div>
                <div class="cert-row">
                  <span class="cert-row-label">Performance</span>
                  <span class="cert-row-val"><?php echo $rankLabel; ?></span>
                </div>
                <div class="cert-row">
                  <span class="cert-row-label">Certificate ID</span>
                  <span class="cert-row-val" style="font-family:var(--mono);font-size:.7rem;color:var(--muted2)"><?php echo htmlspecialchars($cert['cert_id']); ?></span>
                </div>
                <div class="cert-row">
                  <span class="cert-row-label">Sent By</span>
                  <span class="cert-row-val"><?php echo htmlspecialchars($cert['full_name'] ?? 'Admin'); ?></span>
                </div>
              </div>
              <div class="cert-footer">
                <div style="font-size:.7rem;color:var(--muted2);font-family:var(--mono)">
                  Sent: <?php echo date('M d, Y H:i', strtotime($cert['sent_at'])); ?>
                </div>
                <span class="status-badge status-sent">✓ Sent</span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>

  <!-- ─── Preview Modal ─── -->
  <div id="modal-overlay" class="mo hidden" onclick="if(event.target===this)closeModal()">
    <div class="modal">
      <div class="mhdr">
        <h3>Certificate Preview</h3>
        <button class="mcl" onclick="closeModal()">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div class="mbdy">
        <div id="cert-preview-area"></div>
        <div style="display:flex;justify-content:flex-end;gap:.6rem;margin-top:1.25rem">
          <button class="btn btn-s btn-sm" onclick="closeModal()">Close</button>
          <button class="btn btn-dl btn-sm" id="modal-dl-btn">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Download PDF
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Hidden PDF template -->
  <div id="cert-print-template" style="position:fixed;left:-9999px;top:0;visibility:hidden"></div>

  <div id="toast-c"></div>

  <script>
    /* ── Utilities ── */
    function toggleSidebar() {
      document.getElementById('sidebar').classList.toggle('collapsed');
      localStorage.setItem('cs_sb', document.getElementById('sidebar').classList.contains('collapsed') ? '1' : '0');
    }
    function toggleTheme() {
      const d = document.documentElement.getAttribute('data-theme') !== 'dark';
      document.documentElement.setAttribute('data-theme', d ? 'dark' : 'light');
      localStorage.setItem('cs_th', d ? 'dark' : 'light');
      document.getElementById('tmoon').style.display = d ? '' : 'none';
      document.getElementById('tsun').style.display  = d ? 'none' : '';
    }
    function showToast(msg, color = 'blue') {
      const cols = { blue:'var(--blue)', green:'var(--green)', red:'var(--red)', yellow:'var(--yellow)' };
      const t = document.createElement('div'); t.className = 'toast';
      t.innerHTML = `<span class="ti" style="background:${cols[color]||cols.blue}"></span><span>${msg}</span>`;
      document.getElementById('toast-c').appendChild(t);
      setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 2500);
    }
    function closeModal() { document.getElementById('modal-overlay').classList.add('hidden'); }

    /* ── Restore preferences ── */
    (function() {
      const th = localStorage.getItem('cs_th') || 'dark';
      document.documentElement.setAttribute('data-theme', th);
      document.getElementById('tmoon').style.display = th==='dark' ? '' : 'none';
      document.getElementById('tsun').style.display  = th==='dark' ? 'none' : '';
      if (localStorage.getItem('cs_sb')==='1') document.getElementById('sidebar').classList.add('collapsed');
      const d = new Date();
      document.getElementById('tb-date').textContent = d.toLocaleDateString('en-US',{weekday:'short',month:'short',day:'numeric'});
    })();

    /* ── Build certificate HTML ── */
    function buildCertHTML(data) {
      const scoreColor = data.score >= 80 ? '#10D982' : data.score >= 70 ? '#3B8BFF' : '#F5B731';
      return `
        <div style="width:660px;padding:44px 52px;background:#fff;color:#0a0a0a;border:3px solid #F5B731;border-radius:14px;position:relative;font-family:'Inter',sans-serif;box-shadow:inset 0 0 0 6px rgba(245,183,49,.1)">
          <!-- Watermark -->
          <div style="position:absolute;inset:0;display:grid;place-items:center;opacity:.04;pointer-events:none">
            <svg width="260" height="260" viewBox="0 0 24 24" fill="#F5B731"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          </div>
          <!-- Logo -->
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:26px">
            <div style="width:40px;height:40px;background:linear-gradient(135deg,#3B8BFF,#7B72F0);border-radius:10px;display:grid;place-items:center">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <div>
              <div style="font-family:'Syne',sans-serif;font-size:1.15rem;font-weight:800;letter-spacing:2px">CYBERSHIELD</div>
              <div style="font-size:.62rem;color:#64748b;letter-spacing:1px;text-transform:uppercase">Cybersecurity Assessment Platform</div>
            </div>
            <div style="margin-left:auto;text-align:right">
              <div style="font-size:.6rem;color:#94a3b8;font-family:'JetBrains Mono',monospace;letter-spacing:.5px">CERTIFICATE ID</div>
              <div style="font-size:.7rem;font-weight:700;color:#0a0a0a;font-family:'JetBrains Mono',monospace">${data.certId}</div>
            </div>
          </div>
          <!-- Headline -->
          <div style="font-family:'Syne',sans-serif;font-size:1.9rem;font-weight:800;color:#0a0a0a;line-height:1.1;margin-bottom:4px">Certificate of Achievement</div>
          <div style="font-size:.82rem;color:#64748b;margin-bottom:22px">This certifies that the following individual has successfully demonstrated cybersecurity proficiency.</div>
          <!-- Awardee -->
          <div style="margin-bottom:6px;font-size:.68rem;color:#94a3b8;text-transform:uppercase;letter-spacing:1px">Awarded to</div>
          <div style="font-family:'Syne',sans-serif;font-size:1.7rem;font-weight:800;color:#1a1a2e;border-bottom:2px solid #F5B731;display:inline-block;padding-bottom:4px;margin-bottom:6px">${data.name}</div>
          <div style="font-size:.78rem;color:#64748b;margin-bottom:22px">${data.store}</div>
          <!-- Detail grid -->
          <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px">
            ${[
              ['Overall Score', data.score + '%', scoreColor],
              ['Performance', data.rankLabel, '#F5B731'],
              ['Date Issued', data.date, '#3B8BFF'],
              ['Rank', data.rank, scoreColor],
            ].map(([label, val, col]) => `
              <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px">
                <div style="font-size:.58rem;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;margin-bottom:3px;font-family:'JetBrains Mono',monospace">${label}</div>
                <div style="font-size:.95rem;font-weight:700;color:${col}">${val}</div>
              </div>`).join('')}
          </div>
          <!-- Score breakdown -->
          <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;margin-bottom:22px">
            <div style="font-size:.65rem;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;margin-bottom:10px;font-family:'JetBrains Mono',monospace">Score Breakdown</div>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px">
              ${[
                ['Password', data.password],
                ['Phishing', data.phishing],
                ['Device', data.device],
                ['Network', data.network],
              ].map(([label, val]) => {
                const pct = Math.min(100, Number(val));
                const c = pct >= 80 ? '#10D982' : pct >= 60 ? '#3B8BFF' : pct >= 40 ? '#F5B731' : '#FF3B5C';
                return `<div>
                  <div style="font-size:.62rem;color:#64748b;margin-bottom:4px">${label}</div>
                  <div style="height:5px;background:#e2e8f0;border-radius:3px;overflow:hidden">
                    <div style="height:100%;width:${pct}%;background:${c};border-radius:3px"></div>
                  </div>
                  <div style="font-size:.65rem;font-weight:700;color:${c};margin-top:2px">${pct}%</div>
                </div>`;
              }).join('')}
            </div>
          </div>
          <!-- Seal / footer -->
          <div style="display:flex;align-items:center;gap:14px;padding-top:18px;border-top:1px solid #e2e8f0">
            <div style="width:54px;height:54px;border-radius:50%;background:linear-gradient(135deg,#F5B731,#FF8C42);display:grid;place-items:center;flex-shrink:0;box-shadow:0 0 0 4px rgba(245,183,49,.2)">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </div>
            <div>
              <div style="font-size:.8rem;font-weight:700;color:#0a0a0a">CyberShield Certified</div>
              <div style="font-size:.7rem;color:#64748b">Issued by the CyberShield Assessment Platform</div>
              <div style="font-family:'JetBrains Mono',monospace;font-size:.58rem;color:#94a3b8;margin-top:2px">Verify at cybershield.app/verify/${data.certId}</div>
            </div>
            <div style="margin-left:auto;text-align:right">
              <div style="width:70px;border-top:1.5px solid #0a0a0a;padding-top:4px;font-size:.6rem;color:#64748b;text-align:center">Authorized Seal</div>
            </div>
          </div>
        </div>
      `;
    }

    let currentCertData = null;

    function viewCert(data) {
      currentCertData = data;
      document.getElementById('cert-preview-area').innerHTML = buildCertHTML(data);
      document.getElementById('modal-dl-btn').onclick = () => downloadCert(data);
      document.getElementById('modal-overlay').classList.remove('hidden');
    }

    async function downloadCert(data) {
      showToast('Generating PDF…', 'yellow');
      const tpl = document.getElementById('cert-print-template');
      tpl.style.visibility = 'visible';
      tpl.innerHTML = buildCertHTML(data);

      try {
        const canvas = await html2canvas(tpl, { scale: 2, useCORS: true, backgroundColor: '#fff' });
        const imgData = canvas.toDataURL('image/png');
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({ orientation: 'landscape', unit: 'px', format: [canvas.width / 2, canvas.height / 2] });
        pdf.addImage(imgData, 'PNG', 0, 0, canvas.width / 2, canvas.height / 2);
        pdf.save(`CyberShield-Certificate-${data.certId}.pdf`);
        showToast('Certificate downloaded!', 'green');
      } catch (e) {
        showToast('Download failed. Try again.', 'red');
        console.error(e);
      } finally {
        tpl.style.visibility = 'hidden';
        tpl.innerHTML = '';
      }
    }

    function doLogout() {
      if (confirm('Sign out of CyberShield?')) window.location.href = '../logout.php';
    }
  </script>
</body>
</html>