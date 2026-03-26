
<?php
require_once '../config.php';
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

// Get current page from URL parameter
$page = $_GET['page'] ?? 'dashboard';

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

// Security tips data
$tips = [
    ['id' => 1, 'category' => 'password', 'title' => 'Use Strong, Unique Passwords', 'content' => 'Create passwords that are at least 12 characters long, combining uppercase, lowercase, numbers, and symbols. Never reuse passwords across different accounts.', 'icon' => '🔐'],
    ['id' => 2, 'category' => 'password', 'title' => 'Enable Multi-Factor Authentication', 'content' => 'MFA adds an extra layer of security. Even if your password is compromised, attackers cannot access your account without second factor.', 'icon' => '📱'],
    ['id' => 3, 'category' => 'password', 'title' => 'Use a Password Manager', 'content' => 'Password managers generate and store strong, unique passwords for all your accounts. You only need to remember one master password.', 'icon' => '🔑'],
    ['id' => 4, 'category' => 'phishing', 'title' => 'Verify Email Senders', 'content' => 'Always check sender\'s email address carefully. Phishing emails often use addresses that look legitimate but have slight misspellings.', 'icon' => '✉️'],
    ['id' => 5, 'category' => 'phishing', 'title' => 'Hover Before Clicking', 'content' => 'Hover over links to see the actual URL before clicking. If it looks suspicious, don\'t click.', 'icon' => '🖱️'],
    ['id' => 6, 'category' => 'phishing', 'title' => 'Watch for Urgent Language', 'content' => 'Phishing emails often create a sense of urgency to make you act without thinking. Be skeptical of threats or immediate action requests.', 'icon' => '⚠️'],
    ['id' => 7, 'category' => 'device', 'title' => 'Keep Software Updated', 'content' => 'Regularly update your operating system, browsers, and applications. Updates often include critical security patches.', 'icon' => '🔄'],
    ['id' => 8, 'category' => 'device', 'title' => 'Install Antivirus Software', 'content' => 'Use reputable antivirus software and keep it updated. Run regular scans to detect and remove malware.', 'icon' => '🛡️'],
    ['id' => 9, 'category' => 'device', 'title' => 'Lock Your Devices', 'content' => 'Always lock your computer and mobile devices when stepping away. Use strong PINs or biometric authentication.', 'icon' => '🔒'],
    ['id' => 10, 'category' => 'network', 'title' => 'Use VPN on Public Wi-Fi', 'content' => 'Public Wi-Fi networks are often unsecured. A VPN encrypts your internet traffic, protecting your data from eavesdroppers.', 'icon' => '🌐'],
    ['id' => 11, 'category' => 'network', 'title' => 'Secure Your Home Wi-Fi', 'content' => 'Use WPA3 encryption, change default router password, and disable WPS. Create a separate guest network for visitors.', 'icon' => '🏠'],
    ['id' => 12, 'category' => 'network', 'title' => 'Enable Firewall', 'content' => 'Firewalls monitor incoming and outgoing network traffic. Keep your firewall enabled on all devices and networks.', 'icon' => '🔥'],
];

// Get user's assessment statistics
$stats_query = "SELECT 
    COUNT(*) as total_assessments,
    AVG(score) as avg_score,
    MAX(score) as best_score,
    MIN(score) as worst_score,
    (SELECT score FROM vendor_assessments WHERE assessed_by = :user_id ORDER BY created_at DESC LIMIT 1) as latest_score,
    (SELECT rank FROM vendor_assessments WHERE assessed_by = :user_id ORDER BY created_at DESC LIMIT 1) as latest_rank
    FROM vendor_assessments 
    WHERE assessed_by = :user_id";
$stmt = $db->prepare($stats_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

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

// Get products for this seller
$products_query = "SELECT * FROM products WHERE user_id = :user_id ORDER BY created_at DESC";
$stmt = $db->prepare($products_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get badge achievements based on scores
$badges = [];
if ($stats['best_score'] >= 90) $badges[] = ['name' => 'Security Elite', 'icon' => '🏆', 'color' => '#f5c518'];
if ($stats['best_score'] >= 80) $badges[] = ['name' => 'Security Champion', 'icon' => '🛡️', 'color' => '#00d97e'];
if ($stats['total_assessments'] >= 5) $badges[] = ['name' => 'Consistent Learner', 'icon' => '📚', 'color' => '#4090ff'];
if ($stats['latest_rank'] === 'A') $badges[] = ['name' => 'Low Risk Hero', 'icon' => '✅', 'color' => '#00d97e'];
if ($stats['latest_rank'] === 'B') $badges[] = ['name' => 'On The Right Track', 'icon' => '📈', 'color' => '#4090ff'];
if ($stats['total_assessments'] >= 10) $badges[] = ['name' => 'Dedicated Defender', 'icon' => '🔒', 'color' => '#a855f7'];

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

// Get all assessments history for results page
$history_query2 = "SELECT va.*, v.name as vendor_name 
    FROM vendor_assessments va 
    JOIN vendors v ON va.vendor_id = v.id 
    WHERE v.email = :email 
    ORDER BY va.created_at DESC";
$stmt = $db->prepare($history_query2);
$stmt->bindParam(':email', $user['email']);
$stmt->execute();
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Store/analytics stats
$totalProducts = count($products);
$activeProducts = count(array_filter($products, fn($p) => $p['status'] === 'active'));
$outOfStock = count(array_filter($products, fn($p) => ($p['stock'] ?? 0) == 0));
$totalValue = array_sum(array_column($products, 'price'));

?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Dashboard — CyberShield</title>
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
.tb-admin:hover{border-color:rgba(59,139,255,.28);background:rgba(59,139,255,.06)}
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
      <div class="sb-brand-text"><h2>CyberShield</h2><span class="badge">Client Portal</span></div>
      <button class="sb-toggle" onclick="toggleSidebar()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
    </div>
<div class="sb-section">
      <div class="sb-label">Navigation</div>
      <a class="sb-item active" href="index.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.2"/><rect x="14" y="3" width="7" height="7" rx="1.2"/><rect x="3" y="14" width="7" height="7" rx="1.2"/><rect x="14" y="14" width="7" height="7" rx="1.2"/></svg></span><span class="sb-text">Dashboard</span></a>
      <a class="sb-item" href="assessment.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span><span class="sb-text">Assessment</span></a>
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
        <div class="sb-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
        <div class="sb-user-info"><p><?php echo htmlspecialchars($user['full_name']); ?></p><span>Vendor Account</span></div>
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
          <span class="tb-title">Dashboard Overview</span>
        </div>
        <p class="tb-sub">Your cybersecurity posture summary</p>
      </div>
      <div class="tb-right">
        <div class="tb-search-wrap">
          <span class="tb-search-icon"><svg width="12" height="12" viewBox="0 0 20 20" fill="none"><circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.7"/><path d="M15 15l3 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span>
          <input type="text" class="tb-search" placeholder="Search assessments, tips…" autocomplete="off"/>
        </div>
        <span class="tb-date" id="tb-date"></span>
        <div class="tb-divider"></div>
        <button class="tb-icon-btn" onclick="toggleTheme()" title="Toggle theme">
          <svg id="tmoon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
          <svg id="tsun" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:none"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>
        </button>
        <div class="notif-wrap">
          <button class="tb-icon-btn" onclick="toggleNotif()" title="Notifications" style="position:relative">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="notif-dot" id="notif-dot"></span>
          </button>
<<<<<<< HEAD
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
          <div class="tb-admin-info"><span class="tb-admin-name"><?php echo htmlspecialchars($user['full_name']); ?></span><span class="tb-admin-role">Vendor</span></div>
          <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" style="color:var(--muted);margin-left:.2rem"><path d="M4 6l4 4 4-4"/></svg>
        </a>
      </div>
    </div>
    <div class="content">

<div class="sec-hdr"><h2>Dashboard Overview</h2><p>Your cybersecurity posture summary and recent activity.</p></div>
<div class="stats-row" id="stats-row">
  <div class="card stat-card" style="--accent:var(--blue)"><div class="si" style="background:rgba(59,139,255,.12);color:var(--blue)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div><div class="slabel">Latest Score</div><div class="sval" id="sv-score"><?php echo $stats['latest_score'] ?? '--'; ?></div><div class="ssub"><?php echo $stats['latest_rank'] ? 'Rank ' . $stats['latest_rank'] : 'No assessments yet'; ?></div></div>
  <div class="card stat-card" style="--accent:var(--teal)"><div class="si" style="background:rgba(0,212,170,.12);color:var(--teal)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div><div class="slabel">Risk Rank</div><div class="sval" id="sv-rank"><?php echo $stats['latest_rank'] ?? '--'; ?></div><div class="ssub"><?php 
              if ($stats['latest_rank'] === 'A') echo 'Low Risk';
              elseif ($stats['latest_rank'] === 'B') echo 'Moderate Risk';
              elseif ($stats['latest_rank'] === 'C') echo 'High Risk';
              elseif ($stats['latest_rank'] === 'D') echo 'Critical Risk';
              else echo '--';
            ?></div></div>
  <div class="card stat-card" style="--accent:var(--green)"><div class="si" style="background:rgba(0,255,148,.12);color:var(--green)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div><div class="slabel">Assessments</div><div class="sval" id="sv-total"><?php echo $stats['total_assessments']; ?></div><div class="ssub">Total completed</div></div>
  <div class="card stat-card" style="--accent:var(--yellow)"><div class="si" style="background:rgba(255,214,10,.1);color:var(--yellow)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><div class="slabel">Trend</div><div class="sval" id="sv-trend"><?php 
              if ($stats['total_assessments'] > 1) echo '↑ Improving';
              elseif ($stats['total_assessments'] == 1) echo 'First';
              else echo '--';
            ?></div><div class="ssub"><?php 
              if ($stats['total_assessments'] > 1) echo 'Keep it up!';
              elseif ($stats['total_assessments'] == 1) echo 'Great start!';
              else echo '--';
            ?></div></div>
</div>

<div class="sec-hdr"><h2>Security Tips</h2><p>Daily recommendations to improve your cybersecurity posture.</p></div>
<div class="card" style="padding:1.25rem;margin-bottom:1.25rem">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
    <h3 style="font-family:var(--mono);font-size:.65rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted2)">Tip of the Day</h3>
    <button class="btn btn-s btn-sm" onclick="refreshTip()">Refresh</button>
  </div>
  <p id="tip-text" style="font-size:.9rem;color:var(--text);line-height:1.5">Loading tip...</p>
</div>

<div class="sec-hdr"><h2>Badges & Achievements</h2><p>Your cybersecurity milestones and accomplishments.</p></div>
<div class="card" style="padding:1.25rem;margin-bottom:1.25rem">
  <div id="dash-badges" style="display:flex;gap:.75rem;flex-wrap:wrap">
    <?php if (empty($badges)): ?>
      <p style="color:var(--muted2);font-size:.85rem">Complete assessments to earn badges</p>
    <?php else: ?>
      <?php foreach ($badges as $badge): ?>
        <div style="display:flex;align-items:center;gap:.5rem;background:<?php echo $badge['color']; ?>20;padding:.5rem .85rem;border-radius:8px;border:1px solid <?php echo $badge['color']; ?>30">
          <span style="font-size:1.2rem"><?php echo $badge['icon']; ?></span>
          <span style="font-size:.78rem;font-weight:600;color:<?php echo $badge['color']; ?>"><?php echo $badge['name']; ?></span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="sec-hdr"><h2>Quick Actions</h2><p>Common tasks and shortcuts for your security workflow.</p></div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.9rem;margin-bottom:1.25rem">
  <div class="card" style="padding:1.15rem;cursor:pointer;transition:var(--t)" onclick="startAssessment()">
    <div style="display:flex;align-items:center;gap:.75rem">
      <div style="width:40px;height:40px;background:rgba(59,139,255,.12);color:var(--blue);border-radius:8px;display:grid;place-items:center">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      </div>
      <div>
        <h4 style="font-size:.9rem;font-weight:600;margin-bottom:.2rem">New Assessment</h4>
        <p style="font-size:.75rem;color:var(--muted2)">Take a fresh 12-question quiz</p>
      </div>
    </div>
  </div>
  <div class="card" style="padding:1.15rem;cursor:pointer;transition:var(--t)" onclick="showPage('results')">
    <div style="display:flex;align-items:center;gap:.75rem">
      <div style="width:40px;height:40px;background:rgba(16,217,130,.12);color:var(--green);border-radius:8px;display:grid;place-items:center">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      </div>
      <div>
        <h4 style="font-size:.9rem;font-weight:600;margin-bottom:.2rem">View Results</h4>
        <p style="font-size:.75rem;color:var(--muted2)">Charts & recommendations</p>
      </div>
    </div>
  </div>
  <div class="card" style="padding:1.15rem;cursor:pointer;transition:var(--t)" onclick="showPage('leaderboard')">
    <div style="display:flex;align-items:center;gap:.75rem">
      <div style="width:40px;height:40px;background:rgba(123,114,240,.12);color:var(--purple);border-radius:8px;display:grid;place-items:center">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6l4-4 4 4"/><path d="M12 2v13"/><path d="M20 21H4"/><path d="M17 12h3v9"/><path d="M4 12h3v9"/></svg>
      </div>
      <div>
        <h4 style="font-size:.9rem;font-weight:600;margin-bottom:.2rem">Leaderboard</h4>
        <p style="font-size:.75rem;color:var(--muted2)">Compare with other vendors</p>
      </div>
    </div>
  </div>
  <div class="card" style="padding:1.15rem;cursor:pointer;transition:var(--t)" onclick="showPage('tips')">
    <div style="display:flex;align-items:center;gap:.75rem">
      <div style="width:40px;height:40px;background:rgba(245,183,49,.12);color:var(--yellow);border-radius:8px;display:grid;place-items:center">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      </div>
      <div>
        <h4 style="font-size:.9rem;font-weight:600;margin-bottom:.2rem">Security Tips</h4>
        <p style="font-size:.75rem;color:var(--muted2)">Guides to improve your score</p>
      </div>
    </div>
  </div>
</div>
=======
          <div class="notif-panel hidden" id="notif-panel">
            <div class="notif-header"><span>Notifications</span><button onclick="clearNotifs()">Clear all</button></div>
            <div id="notif-list"><p class="notif-empty">No notifications</p></div>
          </div>
        </div>
        <div class="topbar-user" onclick="showPage('profile')" title="My Profile">
          <div class="topbar-avatar" id="nav-avatar">D</div>
          <div class="topbar-user-info">
            <span class="topbar-user-name" id="nav-name">Demo User</span>
            <span class="topbar-user-role">Vendor</span>
          </div>
          <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6l4 4 4-4"/></svg>
        </div>
      </div>
    </header>

    <!-- DASHBOARD -->
    <div id="page-dashboard" class="page hidden">
      <div class="page-inner fade-in">
        <div class="page-header">
          <div>
            <h2 class="page-title">Good day, <span id="dash-greeting">User</span></h2>
            <p class="page-subtitle">Here's your cybersecurity hygiene overview for today.</p>
          </div>
          <button class="btn btn-primary" onclick="startAssessment()">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            Start Assessment
          </button>
        </div>
        <div id="welcome-banner" class="welcome-banner hidden">
          <div class="welcome-inner">
            <div class="welcome-icon-wrap"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--blue)"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
            <h3>Welcome to CyberShield</h3>
            <p>You haven't taken an assessment yet. Start your first quiz to discover your cybersecurity posture — it only takes a few minutes.</p>
            <button class="btn btn-primary btn-lg" onclick="startAssessment()">Start My First Assessment</button>
          </div>
        </div>
        <div class="stats-grid" id="stats-grid">
          <div class="card stat-card"><div class="stat-label">Latest Score</div><div class="stat-val mono" id="stat-score">—</div><div class="stat-sub" id="stat-rank-text">No assessments yet</div></div>
          <div class="card stat-card"><div class="stat-label">Risk Rank</div><div class="stat-val" id="stat-rank">—</div><div class="stat-sub" id="stat-rank-sub">—</div></div>
          <div class="card stat-card"><div class="stat-label">Assessments Done</div><div class="stat-val mono" id="stat-count">0</div><div class="stat-sub">Total sessions</div></div>
          <div class="card stat-card"><div class="stat-label">Trend</div><div class="stat-val" id="stat-trend">—</div><div class="stat-sub" id="stat-trend-sub">—</div></div>
        </div>
        <div class="card tip-card" id="tip-of-day">
          <div class="tip-card-header">
            <span class="tip-card-label">Tip of the Day</span>
            <button class="btn-ghost-sm" onclick="refreshTip()">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
              Refresh
            </button>
          </div>
          <p class="tip-card-text" id="tip-text">Loading tip…</p>
        </div>
        <div class="card section-card">
          <div class="section-title">Badges &amp; Achievements</div>
          <div id="dash-badges" class="badges-row"></div>
        </div>
        <div class="quick-actions">
          <div class="action-card" onclick="startAssessment()">
            <div class="action-icon blue"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
            <div class="action-text"><h4>New Assessment</h4><p>Take a fresh 12-question quiz</p></div>
            <svg class="action-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </div>
          <div class="action-card" onclick="showPage('results')">
            <div class="action-icon green"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
            <div class="action-text"><h4>View Results</h4><p>Charts &amp; recommendations</p></div>
            <svg class="action-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </div>
          <div class="action-card" onclick="showPage('leaderboard')">
            <div class="action-icon purple"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6l4-4 4 4"/><path d="M12 2v13"/><path d="M20 21H4"/><path d="M17 12h3v9"/><path d="M4 12h3v9"/></svg></div>
            <div class="action-text"><h4>Leaderboard</h4><p>Compare with other vendors</p></div>
            <svg class="action-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </div>
          <div class="action-card" onclick="showPage('tips')">
            <div class="action-icon orange"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
            <div class="action-text"><h4>Security Tips</h4><p>Guides to improve your score</p></div>
            <svg class="action-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </div>
        </div>
        <div class="card chart-card"><div class="chart-card-header">Risk Score Trend</div><div class="chart-wrap"><canvas id="trend-chart"></canvas></div></div>
        <div class="card section-card"><div class="section-title">Assessment History</div><div id="history-container"><p class="empty-state">No assessments taken yet. Start one now!</p></div></div>
      </div>
    </div>









  </div><!-- end main-content -->
</div><!-- end app -->

>>>>>>> ffb18a752ab124402d4952ea0483fe767210a8d9

<div class="sec-hdr"><h2>Assessment History</h2><p>Your past security assessments and performance trends.</p></div>
<div class="card tbl-card">
  <div class="tbl-bar">
    <h3>Recent Assessments</h3>
    <div class="frow">
      <button class="btn btn-p btn-sm" onclick="startAssessment()">New Assessment</button>
    </div>
  </div>
  <div class="tw">
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Score</th>
          <th>Rank</th>
          <th>Duration</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($history)): ?>
          <tr>
            <td colspan="5" style="text-align:center;color:var(--muted2);padding:2rem">No assessments taken yet. Start one now!</td>
          </tr>
        <?php else: ?>
          <?php foreach ($history as $assessment): ?>
            <tr>
              <td><?php echo date('M j, Y', strtotime($assessment['created_at'])); ?></td>
              <td>
                <div class="sbw">
                  <div class="sbb">
                    <div class="sbf" style="width:<?php echo $assessment['score']; ?>%;background:<?php 
                      echo $assessment['score'] >= 80 ? 'var(--green)' : 
                           ($assessment['score'] >= 60 ? 'var(--yellow)' : 
                           ($assessment['score'] >= 40 ? 'var(--orange)' : 'var(--red)')); ?>"></div>
                  </div>
                  <span class="sbn"><?php echo $assessment['score']; ?>%</span>
                </div>
              </td>
              <td><span class="rank r<?php echo $assessment['rank']; ?>"><?php echo $assessment['rank']; ?></span></td>
              <td><?php echo $assessment['duration'] ?? 'N/A'; ?></td>
              <td><button class="btn btn-s btn-sm" onclick="viewAssessment(<?php echo $assessment['id']; ?>)">View</button></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
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
// Client-side functionality
function isDark(){return document.documentElement.getAttribute('data-theme')==='dark'}
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
  if(l)l.innerHTML='<p class="np-empty">No notifications</p>';
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

function startAssessment(){
  showToast('Starting assessment...', 'blue');
  // Redirect to assessment page or show assessment modal
  setTimeout(() => {
    window.location.href = '?page=assessment';
  }, 1000);
}

function showPage(page){
  showToast(`Navigating to ${page}...`, 'blue');
  setTimeout(() => {
    window.location.href = `?page=${page}`;
  }, 500);
}

function viewAssessment(id){
  showToast('Loading assessment details...', 'blue');
  // Show assessment details in modal
}

function refreshTip(){
  const tips = [
    'Use a password manager to generate and store strong, unique passwords for all your accounts.',
    'Enable multi-factor authentication (MFA) on all accounts that support it.',
    'Regularly update your software and operating system to protect against security vulnerabilities.',
    'Be cautious of phishing emails - verify sender identity before clicking links or downloading attachments.',
    'Use a VPN when connecting to public Wi-Fi networks to encrypt your internet traffic.',
    'Back up your important data regularly to protect against ransomware and data loss.'
  ];
  const randomTip = tips[Math.floor(Math.random() * tips.length)];
  document.getElementById('tip-text').textContent = randomTip;
  showToast('Tip refreshed!', 'green');
}

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
  
  // Initialize tip of the day
  refreshTip();
  
  if(typeof pageInit==='function')pageInit();
});
</script>
</body>
</html>
              </body>
</html
