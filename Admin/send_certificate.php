<?php
require_once '../includes/config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header('Location: ../index.html');
  exit();
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get current user data - prioritize session variables set by profile update
if (isset($_SESSION['user_full_name'])) {
    $user = [
        'id' => $_SESSION['user_id'],
        'full_name' => $_SESSION['user_full_name'],
        'email' => $_SESSION['user_email'],
        'store_name' => $_SESSION['user_store_name'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'Admin'
    ];
} else {
    $user_query = "SELECT * FROM users WHERE id = :user_id";
    $stmt = $db->prepare($user_query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['user_full_name'] = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_store_name'] = $user['store_name'];
        $_SESSION['user_role'] = $user['role'];
    }
}

if (!$user) {
  header('Location: ../index.html');
  exit();
}


// Fetch users and assessment datacert
$users = [];
$assessments = [];
try {
    $stmt = $db->prepare("SELECT id, username, email, full_name, store_name, role, is_active, last_assessment_score, last_assessment_date, total_assessments, created_at FROM users ORDER BY created_at DESC");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("
        SELECT a.*, u.full_name, u.email, u.store_name
        FROM assessments a
        JOIN users u ON a.vendor_id = u.id
        ORDER BY a.assessment_date DESC
    ");
    $stmt->execute();
    $allAssessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allAssessments as $assessment) {
        $assessments[] = [
            'id'    => $assessment['id'],
            'vid'   => $assessment['vendor_id'],
            'vname' => $assessment['full_name'] ?: $assessment['store_name'],
            'email' => $assessment['email'],
            'score' => $assessment['score'],
            'rank'  => ($assessment['score'] >= 80) ? 'A' : (($assessment['score'] >= 60) ? 'B' : (($assessment['score'] >= 40) ? 'C' : 'D')),
            'date'  => date('Y-m-d', strtotime($assessment['assessment_date'])),
        ];
    }

    // If no real data, build sample from users
    if (empty($assessments) && !empty($users)) {
        foreach ($users as $u) {
            if ($u['last_assessment_score'] !== null) {
                $score = $u['last_assessment_score'];
                $rank  = ($score >= 80) ? 'A' : (($score >= 60) ? 'B' : (($score >= 40) ? 'C' : 'D'));
                $assessments[] = [
                    'id'    => $u['id'],
                    'vid'   => $u['id'],
                    'vname' => $u['full_name'] ?: $u['store_name'],
                    'email' => $u['email'],
                    'score' => $score,
                    'rank'  => $rank,
                    'date'  => $u['last_assessment_date'] ?? date('Y-m-d'),
                ];
            }
        }
    }

} catch(PDOException $exception) {
    error_log("Error fetching certificate data: " . $exception->getMessage());
    $users = [];
    $assessments = [];
}

// Fetch sent certificate log from database (persistent storage)
$sentCertificates = [];
try {
    $stmt = $db->prepare("
        SELECT sc.*, u.full_name, u.email, u.store_name
        FROM sent_certificates sc
        LEFT JOIN users u ON sc.user_id = u.id
        ORDER BY sc.sent_at DESC
        LIMIT 100
    ");
    $stmt->execute();
    $sentCertificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data for display
    $formattedCertificates = [];
    foreach ($sentCertificates as $cert) {
        $formattedCertificates[] = [
            'id' => $cert['id'],
            'user_id' => $cert['user_id'],
            'full_name' => $cert['full_name'] ?? $cert['recipient_name'],
            'email' => $cert['recipient_email'],
            'cert_type' => $cert['cert_type'],
            'score' => $cert['score'],
            'rank' => $cert['rank'],
            'cert_id' => $cert['cert_id'],
            'sent_at' => date('M d, Y H:i', strtotime($cert['sent_at'])),
            'status' => $cert['status']
        ];
    }
    $sentCertificates = $formattedCertificates;
} catch(PDOException $e) {
    error_log("Certificate log error: " . $e->getMessage());
    $sentCertificates = [];
}

// Get statistics for dashboard
$stats = [
    'eligible' => 0,
    'total_assessments' => 0,
    'sent_count' => 0
];

try {
    // Count eligible users (rank A)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT u.id) as count 
        FROM users u
        INNER JOIN assessments a ON u.id = a.vendor_id
        WHERE a.score >= 80
    ");
    $stmt->execute();
    $stats['eligible'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Count total assessments
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM assessments");
    $stmt->execute();
    $stats['total_assessments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Count sent certificates
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM sent_certificates");
    $stmt->execute();
    $stats['sent_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
} catch(PDOException $e) {
    error_log("Stats error: " . $e->getMessage());
}

$usersJson        = json_encode($users);
$assessmentsJson  = json_encode($assessments);
$sentCertsJson    = json_encode($sentCertificates);
$statsJson        = json_encode($stats);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>Send Certificate — CyberShield</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Syne:wght@600;700;800&family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@700;800&family=Cinzel:wght@400;600&family=Cormorant+Garamond:wght@600;700&family=Merriweather:wght@400;700&family=Space+Mono:wght@700&family=Bebas+Neue:wght@700&display=swap');

    :root {
      --font:'Inter',sans-serif;--display:'Syne',sans-serif;--mono:'JetBrains Mono',monospace;
      --blue:#3B8BFF;--purple:#7B72F0;--teal:#00D4AA;--green:#10D982;--yellow:#F5B731;--orange:#FF8C42;--red:#FF3B5C;--t:.18s ease
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
    .tb-app{font-family:var(--mono);font-size:.68rem;color:var(--muted);letter-spacing:.5px}
    .tb-title{font-family:var(--display);font-size:1.05rem;letter-spacing:1px}
    .tb-sub{font-family:var(--mono);font-size:.63rem;letter-spacing:.5px;color:var(--muted);margin-top:1px}
    .tb-right{display:flex;align-items:center;gap:.55rem}
    .tb-date{font-family:var(--mono);font-size:.65rem;color:var(--muted2);white-space:nowrap}
    .tb-divider{width:1px;height:20px;background:var(--border2);margin:0 .2rem}
    .tb-icon-btn{width:32px;height:32px;border-radius:7px;border:1px solid var(--border2);background:rgba(255,255,255,.04);cursor:pointer;display:grid;place-items:center;color:var(--muted2);transition:var(--t);flex-shrink:0}
    .tb-icon-btn:hover{border-color:var(--blue);color:var(--text)}
    .tb-admin{display:flex;align-items:center;gap:.55rem;background:rgba(255,255,255,.04);border:1px solid var(--border2);border-radius:9px;padding:.28rem .65rem .28rem .28rem;cursor:pointer;transition:var(--t);text-decoration:none;color:inherit}
    .tb-admin:hover{border-color:rgba(255,59,92,.28);background:rgba(255,59,92,.06)}
    .tb-admin-av{width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,var(--red),var(--orange));color:#fff;display:grid;place-items:center;font-size:.7rem;font-weight:700;flex-shrink:0;font-family:var(--display)}
    .tb-admin-info{display:flex;flex-direction:column}
    .tb-admin-name{font-size:.78rem;font-weight:600;line-height:1.2}
    .tb-admin-role{font-size:.6rem;color:var(--red);letter-spacing:.5px;font-family:var(--mono)}
    .content{flex:1;overflow-y:auto;padding:1.5rem}
    .content::-webkit-scrollbar{width:4px}.content::-webkit-scrollbar-thumb{background:var(--border2);border-radius:2px}
    .sec-hdr{margin-bottom:1.25rem}
    .sec-hdr h2{font-family:var(--display);font-size:1.25rem;font-weight:700;letter-spacing:.5px}
    .sec-hdr p{font-size:.82rem;color:var(--muted2);margin-top:.2rem}
    .card{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow);transition:border-color .18s}
    .card:hover{border-color:var(--border2)}
    .btn{display:inline-flex;align-items:center;gap:.4rem;padding:.42rem .9rem;border-radius:8px;font-family:var(--font);font-size:.78rem;font-weight:600;cursor:pointer;transition:var(--t);border:none;text-decoration:none}
    .btn-p{background:var(--blue);color:#fff}.btn-p:hover{background:#2e7ae8}
    .btn-g{background:var(--green);color:#0a1020}.btn-g:hover{background:#0dc070}
    .btn-s{background:rgba(255,255,255,.05);color:var(--muted2);border:1px solid var(--border2)}.btn-s:hover{border-color:var(--blue);color:var(--text)}
    .btn-d{background:rgba(255,59,92,.1);color:var(--red);border:1px solid rgba(255,59,92,.2)}.btn-d:hover{background:rgba(255,59,92,.2)}
    .btn-sm{font-size:.72rem;padding:.32rem .7rem}
    .rank{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:5px;font-family:var(--mono);font-size:.7rem;font-weight:700}
    .rA{background:rgba(16,217,130,.15);color:var(--green)}.rB{background:rgba(245,183,49,.15);color:var(--yellow)}.rC{background:rgba(255,140,66,.15);color:var(--orange)}.rD{background:rgba(255,59,92,.15);color:var(--red)}
    .tw{overflow-x:auto}
    table{width:100%;border-collapse:collapse}
    thead th{text-align:left;padding:.55rem .75rem;font-family:var(--mono);font-size:.6rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted2);border-bottom:1px solid var(--border);white-space:nowrap}
    tbody tr{border-bottom:1px solid var(--border);transition:background .18s}
    tbody tr:last-child{border-bottom:none}
    tbody tr:hover{background:rgba(59,139,255,.04)}
    tbody td{padding:.65rem .75rem;font-size:.82rem}
    .fsel{background:rgba(255,255,255,.04);border:1px solid var(--border2);border-radius:7px;padding:.38rem .75rem;font-family:var(--font);font-size:.78rem;color:var(--text);cursor:pointer;outline:none;transition:var(--t)}
    .fsel:focus{border-color:var(--blue)}
    [data-theme=light] .fsel{background:#fff}
    .pgn{display:flex;align-items:center;justify-content:flex-end;gap:.4rem;margin-top:1rem}
    .pb{min-width:30px;height:30px;border-radius:6px;border:1px solid var(--border2);background:none;font-family:var(--mono);font-size:.72rem;color:var(--muted2);cursor:pointer;display:grid;place-items:center;transition:var(--t)}
    .pb:hover,.pb.active{border-color:var(--blue);color:var(--blue);background:rgba(59,139,255,.07)}
    .sbw{display:flex;align-items:center;gap:.6rem}.sbb{flex:1;height:4px;background:var(--border2);border-radius:2px}.sbf{height:100%;border-radius:2px}.sbn{font-family:var(--mono);font-size:.72rem;color:var(--muted2);min-width:32px;text-align:right}
    .mo{position:fixed;inset:0;background:rgba(0,0,0,.6);display:grid;place-items:center;z-index:200;backdrop-filter:blur(4px)}
    .mo.hidden{display:none}
    .modal{background:var(--bg3);border:1px solid var(--border2);border-radius:14px;width:min(95vw,900px);max-width:900px;box-shadow:0 20px 60px rgba(0,0,0,.6);animation:su .2s ease}
    @keyframes su{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
    .mhdr{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid var(--border)}
    .mhdr h3{font-family:var(--display);font-size:1rem;font-weight:700}
    .mcl{width:28px;height:28px;border-radius:7px;border:1px solid var(--border2);background:none;color:var(--muted2);cursor:pointer;display:grid;place-items:center;transition:var(--t)}
    .mcl:hover{border-color:var(--red);color:var(--red)}
    .mbdy{padding:1.25rem;max-height:80vh;overflow-y:auto}
    .fi{background:rgba(255,255,255,.04);border:1px solid var(--border2);border-radius:8px;padding:.5rem .85rem;font-family:var(--font);font-size:.82rem;color:var(--text);outline:none;transition:var(--t);width:100%}
    .fi:focus{border-color:var(--blue)}
    textarea.fi{resize:vertical}
    [data-theme=light] .fi{background:#f8fafc}
    .fl{font-family:var(--mono);font-size:.62rem;letter-spacing:1px;text-transform:uppercase;color:var(--muted);display:block;margin-bottom:.4rem}
    .fg{margin-bottom:.85rem}
    #toast-c{position:fixed;bottom:1.25rem;right:1.25rem;display:flex;flex-direction:column;gap:.5rem;z-index:300}
    .toast{background:var(--bg3);border:1px solid var(--border2);border-radius:9px;padding:.75rem 1rem;font-size:.82rem;box-shadow:var(--shadow);display:flex;align-items:center;gap:.6rem;animation:sl .2s ease;min-width:240px}
    @keyframes sl{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:none}}
    .ti{width:8px;height:8px;border-radius:50%;flex-shrink:0}
    .cert-preview{
      background:linear-gradient(135deg,rgba(59,139,255,.03),rgba(123,114,240,.03));
      border:2px solid transparent;
      border-radius:16px;
      padding:3rem;
      text-align:center;
      position:relative;
      overflow:hidden;
      box-shadow:0 8px 32px rgba(0,0,0,.15);
      min-height:500px;
      display:flex;
      flex-direction:column;
      justify-content:center;
      align-items:center;
    }
    .cert-preview::before{
      content:'';
      position:absolute;
      inset:0;
      border-radius:16px;
      padding:2px;
      background:linear-gradient(135deg,var(--blue),var(--purple),var(--teal));
      -webkit-mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0);
      mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0);
      -webkit-mask-composite:xor;
      mask-composite:exclude;
      opacity:.6;
    }
    .cert-preview::after{
      content:'';
      position:absolute;
      top:-50%;left:-50%;width:200%;height:200%;
      background:conic-gradient(from 0deg,transparent,rgba(59,139,255,.1),transparent,rgba(123,114,240,.1),transparent);
      animation:rotate 20s linear infinite;
      pointer-events:none;
    }
    @keyframes rotate{100%{transform:rotate(360deg)}}
    .cert-seal{
      width:60px;height:60px;
      background:linear-gradient(135deg,var(--blue),var(--purple));
      border-radius:50%;
      display:grid;place-items:center;
      margin:0 auto 1.5rem;
      position:relative;z-index:1;
      box-shadow:0 4px 16px rgba(59,139,255,.3),0 0 0 2px rgba(255,255,255,.05);
    }
    .cert-title{
      font-family:'Playfair Display', 'Georgia', serif;
      font-size:1.8rem;
      font-weight:800;
      background:linear-gradient(135deg,var(--blue),var(--purple),var(--teal));
      -webkit-background-clip:text;
      -webkit-text-fill-color:transparent;
      background-clip:text;
      margin-bottom:.5rem;
      margin-top:0;
      position:relative;z-index:1;
      letter-spacing:1px;
      text-shadow:0 2px 4px rgba(0,0,0,.1);
    }
    .cert-subtitle{
      font-family:'Cinzel', 'Georgia', serif;
      font-size:.9rem;
      letter-spacing:3px;
      text-transform:uppercase;
      color:var(--muted2);
      margin-bottom:1.5rem;
      position:relative;z-index:1;
      font-weight:600;
    }
    .cert-name{
      font-family:'Cormorant Garamond', 'Georgia', serif;
      font-size:1.8rem;
      font-weight:700;
      color:var(--text);
      margin:1.5rem 0 .5rem;
      position:relative;z-index:1;
      display:inline-block;
      padding:.5rem 1.5rem;
      border:2px solid var(--blue);
      border-radius:8px;
      background:linear-gradient(135deg,rgba(59,139,255,.08),rgba(123,114,240,.08));
      box-shadow:0 4px 12px rgba(59,139,255,.2);
      transition:all .3s ease;
    }
    .cert-name:hover{
      transform:translateY(-2px);
      box-shadow:0 6px 20px rgba(59,139,255,.3);
    }
    .cert-body{
      font-family:'Merriweather', 'Georgia', serif;
      font-size:1rem;
      color:var(--muted2);
      margin:1.5rem auto;
      line-height:1.8;
      max-width:500px;
      position:relative;z-index:1;
      font-weight:400;
    }
    .cert-score-badge{
      display:inline-flex;
      align-items:center;
      gap:.8rem;
      background:linear-gradient(135deg,rgba(59,139,255,.12),rgba(123,114,240,.12));
      border:2px solid rgba(59,139,255,.25);
      border-radius:25px;
      padding:.6rem 1.2rem;
      margin-top:1.5rem;
      font-family:'Space Mono', monospace;
      font-size:.9rem;
      font-weight:700;
      position:relative;z-index:1;
      box-shadow:0 4px 12px rgba(59,139,255,.15);
    }
    .cert-grade-display{
      position:static;
      width:70px;height:70px;
      background:linear-gradient(135deg,var(--blue),var(--purple));
      border-radius:50%;
      display:flex;align-items:center;justify-content:center;
      font-family:'Bebas Neue', sans-serif;
      font-size:2rem;
      font-weight:800;
      color:#fff;
      box-shadow:0 6px 20px rgba(59,139,255,.4);
      margin:0 auto 1.5rem;
      position:relative;z-index:1;
    }
    .cert-grade-display::before{
      content:'';
      position:absolute;
      inset:-4px;
      border-radius:50%;
      background:linear-gradient(135deg,var(--blue),var(--purple));
      opacity:.3;
      z-index:-1;
    }
    .cert-date-display{
      position:static;
      font-family:'Cinzel', serif;
      font-size:.8rem;
      color:var(--muted);
      text-align:center;
      position:relative;z-index:1;
      margin:0 auto 1rem;
      background:rgba(255,255,255,.05);
      padding:.4rem .8rem;
      border-radius:6px;
      border:1px solid rgba(255,255,255,.1);
      display:inline-block;
    }
    .tab-bar{display:flex;gap:.5rem;margin-bottom:1.25rem;border-bottom:1px solid var(--border);padding-bottom:0}
    .tab-btn{padding:.55rem 1.1rem;font-family:var(--mono);font-size:.72rem;letter-spacing:.5px;text-transform:uppercase;color:var(--muted2);background:none;border:none;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;transition:var(--t)}
    .tab-btn:hover{color:var(--text)}
    .tab-btn.active{color:var(--blue);border-bottom-color:var(--blue)}
    .tab-panel{display:none}.tab-panel.active{display:block}
    .status-badge{display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .55rem;border-radius:15px;font-size:.7rem;font-weight:600}
    .status-sent{background:rgba(16,217,130,.12);color:var(--green);border:1px solid rgba(16,217,130,.25)}
    .status-pending{background:rgba(245,183,49,.12);color:var(--yellow);border:1px solid rgba(245,183,49,.25)}
    .tbl-bar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.65rem;margin-bottom:1rem}
    .tbl-bar h3{font-family:var(--display);font-size:1rem;font-weight:700}
    .frow{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
    .tbl-card{padding:1.25rem 1.5rem}
    .cert-type-tag{display:inline-flex;align-items:center;gap:.3rem;font-family:var(--mono);font-size:.65rem;letter-spacing:.5px;padding:.18rem .5rem;border-radius:10px;background:rgba(123,114,240,.12);color:var(--purple);border:1px solid rgba(123,114,240,.2)}
  </style>
</head>
<body>
<div class="bg-grid"></div>
<div id="app">

  <!-- ===== SIDEBAR ===== -->
  <aside id="sidebar">
    <div class="sb-brand">
      <div class="shield"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
      <div class="sb-brand-text"><h2>CyberShield</h2><span class="badge">Admin Panel</span></div>
      <button class="sb-toggle" onclick="toggleSidebar()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
    </div>
    <div class="sb-section">
      <div class="sb-label">Navigation</div>
      <a class="sb-item" href="dashboard.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.2"/><rect x="14" y="3" width="7" height="7" rx="1.2"/><rect x="3" y="14" width="7" height="7" rx="1.2"/><rect x="14" y="14" width="7" height="7" rx="1.2"/></svg></span><span class="sb-text">Dashboard</span></a>
      <a class="sb-item" href="reports.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span><span class="sb-text">Reports</span></a>
      <a class="sb-item" href="users.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><span class="sb-text">Users</span></a>
      <a class="sb-item" href="heatmap.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span><span class="sb-text">Risk Heatmap</span></a>
      <a class="sb-item" href="activity.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span><span class="sb-text">Activity Log</span></a>
      <!-- ===== CERTIFICATES BUTTON (NEW) ===== -->
      <a class="sb-item active" href="send_certificate.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 15l2 2 4-4"/></svg></span><span class="sb-text">Certificates</span></a>
      <a class="sb-item" href="settings.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M6 20v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/></svg></span><span class="sb-text">Settings</span></a>
      <div class="sb-divider"></div>
      <div class="sb-label">Tools</div>
      <a class="sb-item" href="compare.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="8" x2="6" y2="8"/><line x1="21" y1="16" x2="3" y2="16"/></svg></span><span class="sb-text">Compare</span></a>
      <a class="sb-item" href="email.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v16a2 2 0 0 0 2 2H4c-1.1 0-2-.9-2-2V8z"/><polyline points="22,6 12,13 2,6"/></svg></span><span class="sb-text">Email Report</span></a>
      <div class="sb-divider"></div>
      <div class="sb-label">Quick Actions</div>
      <a class="sb-item" onclick="exportCSV()"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></span><span class="sb-text">Export CSV</span></a>
      <a class="sb-item" onclick="refreshData()"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg></span><span class="sb-text">Refresh Data</span></a>
    </div>
    <div class="sb-footer">
      <div class="sb-user">
        <div class="sb-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
        <div class="sb-user-info">
          <p><?php echo htmlspecialchars($user['full_name']); ?></p>
          <span><?php echo htmlspecialchars($user['email']); ?></span>
        </div>
      </div>
      <button class="btn-sb-logout" onclick="doLogout()">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span>Sign Out</span>
      </button>
    </div>
  </aside>

  <!-- ===== MAIN ===== -->
  <div id="main">
    <div class="topbar">
      <div>
        <div class="tb-bc">
          <span class="tb-app">CyberShield</span>
          <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 4l4 4-4 4"/></svg>
          <span class="tb-title">Certificates</span>
        </div>
        <p class="tb-sub">Send compliance certificates to users</p>
      </div>
      <div class="tb-right">
        <span class="tb-date" id="tb-date"></span>
        <div class="tb-divider"></div>
        <button class="tb-icon-btn" onclick="toggleTheme()" title="Toggle theme">
          <svg id="tmoon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
          <svg id="tsun" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:none"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>
        </button>
        <div class="tb-divider"></div>
        <a class="tb-admin" href="settings.php">
          <div class="tb-admin-av"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
          <div class="tb-admin-info">
            <span class="tb-admin-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
            <span class="tb-admin-role"><?php echo htmlspecialchars($user['role'] ?? 'Admin'); ?></span>
          </div>
          <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" style="color:var(--muted);margin-left:.2rem"><path d="M4 6l4 4 4-4"/></svg>
        </a>
      </div>
    </div>

    <div class="content">
      <div class="sec-hdr">
        <h2>Certificate Management</h2>
        <p>Issue and send cybersecurity compliance certificates to users based on their assessment results.</p>
      </div>

      <!-- Tab Bar -->
      <div class="tab-bar">
        <button class="tab-btn active" onclick="switchTab('send')">Send Certificate</button>
        <button class="tab-btn" onclick="switchTab('log')">Sent Log</button>
      </div>

      <!-- ===== TAB: SEND CERTIFICATE ===== -->
      <div class="tab-panel active" id="tab-send">

        <!-- Stats row -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.9rem;margin-bottom:1.25rem">
          <div class="card" style="padding:1.1rem 1.2rem;--accent:var(--purple);position:relative;overflow:hidden">
            <div style="position:absolute;top:0;left:0;right:0;height:2px;background:var(--purple);opacity:.7"></div>
            <div style="font-family:var(--mono);font-size:.58rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted2);margin-bottom:.3rem">Eligible Users</div>
            <div style="font-family:var(--display);font-size:1.9rem;font-weight:700;color:var(--purple)" id="stat-eligible"><?php echo $stats['eligible']; ?></div>
            <div style="font-size:.7rem;color:var(--muted);margin-top:.2rem">Score ≥ 80 (Rank A)</div>
          </div>
          <div class="card" style="padding:1.1rem 1.2rem;--accent:var(--teal);position:relative;overflow:hidden">
            <div style="position:absolute;top:0;left:0;right:0;height:2px;background:var(--teal);opacity:.7"></div>
            <div style="font-family:var(--mono);font-size:.58rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted2);margin-bottom:.3rem">Total Assessments</div>
            <div style="font-family:var(--display);font-size:1.9rem;font-weight:700;color:var(--teal)" id="stat-total"><?php echo $stats['total_assessments']; ?></div>
            <div style="font-size:.7rem;color:var(--muted);margin-top:.2rem">All records</div>
          </div>
          <div class="card" style="padding:1.1rem 1.2rem;--accent:var(--green);position:relative;overflow:hidden">
            <div style="position:absolute;top:0;left:0;right:0;height:2px;background:var(--green);opacity:.7"></div>
            <div style="font-family:var(--mono);font-size:.58rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted2);margin-bottom:.3rem">Certificates Sent</div>
            <div style="font-family:var(--display);font-size:1.9rem;font-weight:700;color:var(--green)" id="stat-sent"><?php echo $stats['sent_count']; ?></div>
            <div style="font-size:.7rem;color:var(--muted);margin-top:.2rem">Total issued</div>
          </div>
        </div>

        <!-- Assessment table with Send button -->
        <div class="card tbl-card">
          <div class="tbl-bar">
            <h3>User Assessments</h3>
            <div class="frow">
              <select class="fsel" id="cert-rank-filter" onchange="renderCertTbl()">
                <option value="">All Ranks</option>
                <option value="A">A — Eligible</option>
                <option value="B">B — Moderate</option>
                <option value="C">C — High Risk</option>
                <option value="D">D — Critical</option>
              </select>
              <button class="btn btn-s btn-sm" onclick="document.getElementById('cert-rank-filter').value='';renderCertTbl()">Clear</button>
              <button class="btn btn-g btn-sm" onclick="sendBulkCertificates()">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg>
                Send All Eligible
              </button>
            </div>
          </div>
          <div class="tw">
            <table>
              <thead>
                <tr>
                  <th><input type="checkbox" id="select-all" onchange="toggleSelectAll(this)"></th>
                  <th>User</th>
                  <th>Email</th>
                  <th>Score</th>
                  <th>Rank</th>
                  <th>Date</th>
                  <th>Eligible</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="cert-tbl-body"></tbody>
            </table>
          </div>
          <div class="pgn" id="cert-pgn"></div>
        </div>
      </div>

      <!-- ===== TAB: SENT LOG ===== -->
      <div class="tab-panel" id="tab-log">
        <div class="card tbl-card">
          <div class="tbl-bar">
            <h3>Certificate Sent Log</h3>
            <div class="frow">
              <button class="btn btn-s btn-sm" onclick="exportLogCSV()">⬇ Export Log</button>
            </div>
          </div>
          <div class="tw">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>User</th>
                  <th>Email</th>
                  <th>Type</th>
                  <th>Score</th>
                  <th>Sent At</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="log-tbl-body"></tbody>
            </table>
          </div>
          <div id="log-empty" style="text-align:center;padding:2.5rem;color:var(--muted2);font-size:.85rem;display:none">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto .75rem;display:block;color:var(--muted)"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 15l2 2 4-4"/></svg>
            No certificates have been sent yet.
          </div>
        </div>
      </div>

    </div><!-- /.content -->
  </div><!-- /#main -->
</div><!-- /#app -->

<!-- ===== SEND CERTIFICATE MODAL ===== -->
<div id="modal-overlay" class="mo hidden" onclick="if(event.target===this)closeModal()">
  <div class="modal" style="max-width:900px;">
    <div class="mhdr">
      <h3 id="modal-title">Send Certificate</h3>
      <button class="mcl" onclick="closeModal()"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="mbdy" id="modal-body"></div>
  </div>
</div>

<div id="toast-c"></div>

<script>
  const DB_USERS       = <?php echo $usersJson; ?>;
  const DB_ASSESSMENTS = <?php echo $assessmentsJson; ?>;
  const DB_SENT_CERTS  = <?php echo $sentCertsJson; ?>;
  const DB_STATS       = <?php echo $statsJson; ?>;

  // In-memory copy of sent certificates (will be synced with DB)
  let sessionSentLog = [...DB_SENT_CERTS];

  function sc(s){ return s>=80?'var(--green)':s>=60?'var(--yellow)':s>=40?'var(--orange)':'var(--red)' }
  function isDark(){ return document.documentElement.getAttribute('data-theme')==='dark' }
  function toggleSidebar(){ document.getElementById('sidebar').classList.toggle('collapsed'); localStorage.setItem('cs_sb',document.getElementById('sidebar').classList.contains('collapsed')?'1':'0'); }
  function toggleTheme(){
    const d=!isDark();
    document.documentElement.setAttribute('data-theme',d?'dark':'light');
    localStorage.setItem('cs_th',d?'dark':'light');
    const m=document.getElementById('tmoon'),s=document.getElementById('tsun');
    if(m) m.style.display=d?'':'none';
    if(s) s.style.display=d?'none':'';
  }
  function showToast(msg,color='blue'){
    const cols={blue:'var(--blue)',green:'var(--green)',red:'var(--red)',yellow:'var(--yellow)',purple:'var(--purple)'};
    const t=document.createElement('div'); t.className='toast';
    t.innerHTML=`<span class="ti" style="background:${cols[color]||cols.blue}"></span><span>${msg}</span>`;
    document.getElementById('toast-c').appendChild(t);
    setTimeout(()=>{ t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); },2800);
  }
  function doLogout(){ if(confirm('Are you sure you want to sign out?')) window.location.href='logout.php'; }
  function closeModal(){ document.getElementById('modal-overlay').classList.add('hidden'); }
  function switchTab(tab){
    document.querySelectorAll('.tab-btn').forEach((b,i)=>b.classList.toggle('active',['send','log'][i]===tab));
    document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
    document.getElementById('tab-'+tab).classList.add('active');
    if(tab==='log') renderLogTbl();
  }
  function toggleSelectAll(cb){
    document.querySelectorAll('.row-check').forEach(c=>c.checked=cb.checked);
  }
  function refreshData(){
    window.location.reload();
  }
  function exportCSV(){
    let csv = "User,Email,Score,Rank,Date\n";
    DB_ASSESSMENTS.forEach(a => {
      csv += `"${a.vname}","${a.email || ''}",${a.score},${a.rank},${a.date}\n`;
    });
    const blob = new Blob([csv], {type: 'text/csv'});
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `certificates_${new Date().toISOString().slice(0,19)}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
    showToast('CSV exported successfully','green');
  }
  function exportLogCSV(){
    let csv = "User,Email,Certificate Type,Score,Sent Date,Status\n";
    sessionSentLog.forEach(l => {
      csv += `"${l.full_name || ''}","${l.email || ''}","${l.cert_type || ''}",${l.score || ''},"${l.sent_at || ''}",${l.status || 'sent'}\n`;
    });
    const blob = new Blob([csv], {type: 'text/csv'});
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `certificate_log_${new Date().toISOString().slice(0,19)}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
    showToast('Log exported successfully','green');
  }

  // ---- Stats update ----
  function updateStats(){
    const eligible = DB_ASSESSMENTS.filter(a=>a.rank==='A').length;
    document.getElementById('stat-eligible').textContent = eligible;
    document.getElementById('stat-total').textContent = DB_ASSESSMENTS.length;
    document.getElementById('stat-sent').textContent = sessionSentLog.length;
  }

  // ---- Assessment table ----
  let certPg=1, CERT_PS=8;
  function renderCertTbl(){
    const f=document.getElementById('cert-rank-filter').value;
    // Latest assessment per user
    const latestMap={};
    DB_ASSESSMENTS.forEach(a=>{
      if(!latestMap[a.vid]||a.date>latestMap[a.vid].date) latestMap[a.vid]=a;
    });
    let d=Object.values(latestMap);
    if(f) d=d.filter(a=>a.rank===f);
    d.sort((a,b)=>new Date(b.date)-new Date(a.date));
    const tp=Math.ceil(d.length/CERT_PS);
    if(certPg>tp) certPg=1;
    const sl=d.slice((certPg-1)*CERT_PS, certPg*CERT_PS);

    document.getElementById('cert-tbl-body').innerHTML = sl.map(a=>{
      const eligible = a.rank==='A';
      const eligibleBadge = eligible
        ? `<span class="status-badge status-sent">✓ Eligible</span>`
        : `<span style="font-size:.72rem;color:var(--muted2);font-family:var(--mono)">Score &lt; 80</span>`;
      const sendBtn = eligible
        ? `<button class="btn btn-g btn-sm" onclick="event.stopPropagation();openSendModal(${a.vid},'${escHtml(a.vname)}','${escHtml(a.email||'')}',${a.score},'${a.rank}','${a.date}')">
             <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg> Send
           </button>`
        : `<button class="btn btn-s btn-sm" style="opacity:.45;cursor:not-allowed" disabled>Not Eligible</button>`;
      return `
        <tr>
          <td><input type="checkbox" class="row-check" data-vid="${a.vid}" onclick="event.stopPropagation()"></td>
          <td style="font-weight:600">${escHtml(a.vname)}</td>
          <td style="color:var(--muted2);font-family:var(--mono);font-size:.72rem">${escHtml(a.email||'—')}</td>
          <td><div style="display:flex;align-items:center;gap:.6rem"><div style="flex:1;height:4px;background:var(--border2);border-radius:2px"><div style="width:${a.score}%;height:100%;background:${sc(a.score)};border-radius:2px"></div></div><span style="font-family:var(--mono);font-size:.72rem;color:var(--muted2);min-width:32px;text-align:right">${a.score}%</span></div></td>
          <td><span class="rank r${a.rank}">${a.rank}</span></td>
          <td style="color:var(--muted2);font-family:var(--mono);font-size:.72rem">${a.date}</td>
          <td>${eligibleBadge}</td>
          <td>${sendBtn}</td>
        </tr>`;
    }).join('');

    let ph='';
    for(let i=1;i<=tp;i++) ph+=`<button class="pb ${i===certPg?'active':''}" onclick="certPg=${i};renderCertTbl()">${i}</button>`;
    document.getElementById('cert-pgn').innerHTML=ph || '';
  }

  function escHtml(str){ return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  // ---- Dynamic Certificate Content Updates ----
  function updateCertificateContent() {
    const certType = document.getElementById('cert-type').value;
    const titleElement = document.querySelector('.cert-title');
    const subtitleElement = document.querySelector('.cert-subtitle');
    const bodyElement = document.querySelector('.cert-body');
    const subjectElement = document.getElementById('cert-subject');
    const messageElement = document.getElementById('cert-message');
    
    const certificateContent = {
      compliance: {
        title: 'Certificate of Compliance',
        subtitle: 'CyberShield Assessment Program',
        body: 'Has successfully completed the CyberShield Cybersecurity Assessment<br>and demonstrated compliance with required security standards.',
        subject: 'Your CyberShield Compliance Certificate',
        message: 'Congratulations on achieving excellence in cybersecurity compliance. Your dedication to maintaining security standards is recognized and appreciated.'
      },
      assessment: {
        title: 'Certificate of Achievement',
        subtitle: 'CyberShield Assessment Completion',
        body: 'Has successfully completed the comprehensive CyberShield Assessment<br>demonstrating proficiency in essential security domains.',
        subject: 'Your CyberShield Assessment Certificate',
        message: 'Congratulations on completing the CyberShield Assessment. Your performance reflects strong cybersecurity knowledge and skills.'
      },
      excellence: {
        title: 'Certificate of Excellence',
        subtitle: 'CyberShield Honor Program',
        body: 'Has demonstrated exceptional performance in the CyberShield Assessment<br>achieving the highest standards of cybersecurity excellence.',
        subject: 'Your CyberShield Excellence Certificate',
        message: 'Outstanding achievement! You have earned the Certificate of Excellence by demonstrating superior cybersecurity knowledge and skills.'
      },
      training: {
        title: 'Certificate of Completion',
        subtitle: 'Security Awareness Training Program',
        body: 'Has successfully completed the CyberShield Security Awareness Training<br>gaining essential knowledge in cybersecurity best practices.',
        subject: 'Your Security Awareness Training Certificate',
        message: 'Congratulations on completing your security awareness training. Your commitment to cybersecurity education is valued.'
      }
    };
    
    const content = certificateContent[certType] || certificateContent.compliance;
    const currentName = subjectElement ? subjectElement.value.split(' - ').pop() : '';
    
    if (titleElement) titleElement.textContent = content.title;
    if (subtitleElement) subtitleElement.textContent = content.subtitle;
    if (bodyElement) bodyElement.innerHTML = content.body;
    if (subjectElement && currentName) {
      subjectElement.value = `${content.subject} - ${currentName}`;
    } else if (subjectElement) {
      subjectElement.value = content.subject;
    }
    if (messageElement) messageElement.value = content.message;
  }

  // ---- Send Certificate Modal ----
  function openSendModal(vid, vname, email, score, rank, date){
    document.getElementById('modal-title').textContent = `Send Certificate — ${vname}`;
    const certId = 'CS-' + String(vid).padStart(4,'0') + '-' + date.replace(/-/g,'');
    document.getElementById('modal-body').innerHTML = `
      <!-- Certificate Preview -->
      <div class="cert-preview" style="margin-bottom:1.25rem">
        <!-- Grade Display -->
        <div class="cert-grade-display" id="cert-grade-display">${rank}</div>
        
        <!-- Date Display -->
        <div class="cert-date-display" id="cert-date-display">
          <div style="font-size:.9rem;font-weight:600;margin-bottom:.2rem">Awarded</div>
          <div id="cert-date-text">${date}</div>
        </div>
        
        <div class="cert-seal">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
        </div>
        <div class="cert-title">Certificate of Compliance</div>
        <div class="cert-subtitle">CyberShield Assessment Program</div>
        <div class="cert-name" id="cert-name-display">${escHtml(vname)}</div>
        <div class="cert-body">Has successfully completed the CyberShield Cybersecurity Assessment<br>and demonstrated compliance with required security standards.</div>
        <div class="cert-score-badge">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4"/></svg>
          <span style="color:${sc(score)};font-weight:700">${score}%</span>
          <span style="color:var(--muted2)">Score</span>
          <span class="rank r${rank}" style="margin-left:.3rem">${rank}</span>
        </div>
        <div style="font-family:var(--mono);font-size:.6rem;color:var(--muted);margin-top:.75rem">ID: ${certId} &nbsp;|&nbsp; Date: <span id="cert-date-footer">${date}</span></div>
      </div>

      <!-- Send Form -->
      <div class="fg">
        <label class="fl">Recipient Email</label>
        <input class="fi" id="cert-email" type="email" value="${escHtml(email)}" placeholder="user@example.com">
      </div>
      <div class="fg">
        <label class="fl">Certificate Type</label>
        <select class="fi" id="cert-type" onchange="updateCertificateContent()">
          <option value="compliance">Cybersecurity Compliance Certificate</option>
          <option value="assessment">Assessment Completion Certificate</option>
          <option value="excellence">Certificate of Excellence (Rank A)</option>
          <option value="training">Security Awareness Training Certificate</option>
        </select>
      </div>
      <div class="fg">
        <label class="fl">Subject Line</label>
        <input class="fi" id="cert-subject" type="text" value="Your CyberShield Compliance Certificate - ${escHtml(vname)}">
      </div>
      <div class="fg">
        <label class="fl">Personal Message (Optional)</label>
        <textarea class="fi" id="cert-message" rows="3" placeholder="Add a personal note to the certificate email...">Congratulations on achieving a ${score}% score in the CyberShield Assessment. Your commitment to cybersecurity excellence is recognized and appreciated.</textarea>
      </div>
      <div style="display:flex;gap:.65rem;justify-content:flex-end;margin-top:.5rem">
        <button class="btn btn-s" onclick="closeModal()">Cancel</button>
        <button class="btn btn-p" onclick="previewCertificate('${escHtml(vname)}',${score},'${rank}','${date}','${certId}')">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          Preview
        </button>
        <button class="btn btn-g" onclick="confirmSend(${vid},'${escHtml(vname)}',${score},'${rank}','${certId}')">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg>
          Send Certificate
        </button>
      </div>
    `;
    document.getElementById('modal-overlay').classList.remove('hidden');
  }

  function previewCertificate(vname, score, rank, date, certId){
    showToast('Preview generated — check email client','blue');
  }

  function confirmSend(vid, vname, score, rank, certId){
    const email   = document.getElementById('cert-email').value.trim();
    const type    = document.getElementById('cert-type').value;
    const subject = document.getElementById('cert-subject').value.trim();
    const message = document.getElementById('cert-message').value.trim();

    if(!email){ showToast('Please enter a valid email address','red'); return; }

    // Update certificate date to current date when sending
    const currentDate = new Date().toLocaleDateString('en-US',{year:'numeric',month:'2-digit',day:'2-digit'});
    const certDateElements = document.querySelectorAll('#cert-date-text, #cert-date-footer');
    certDateElements.forEach(el => el.textContent = currentDate);

    const btn = event.target.closest('button');
    btn.disabled=true;
    btn.innerHTML='<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="animation:spin 1s linear infinite"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg> Sending...';

    // Send certificate via email
    fetch('send_certificate_handler.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        email: email,
        vname: vname,
        score: score,
        rank: rank,
        certId: certId,
        type: type,
        subject: subject,
        message: message,
        currentDate: currentDate,
        user_id: vid
      })
    })
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    })
    .then(data => {
      if (data.success) {
        // Add to session log and also to persistent storage
        const typeLabels={
          compliance:'Cybersecurity Compliance Certificate',
          assessment:'Assessment Completion Certificate',
          excellence:'Certificate of Excellence',
          training:'Security Awareness Training Certificate'
        };
        const newEntry = {
          id: Date.now(),
          user_id: vid,
          full_name: vname,
          email: email,
          cert_type: typeLabels[type]||type,
          score: score,
          rank: rank,
          cert_id: certId,
          sent_at: new Date().toLocaleString('en-US',{month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'}),
          status: 'sent'
        };
        sessionSentLog.unshift(newEntry);
        updateStats();
        renderLogTbl();
        closeModal();
        showToast(data.message || `Certificate sent to ${email}`,'green');
        
        // Refresh stats from server to ensure accuracy
        setTimeout(() => {
          fetch('get_certificate_stats.php')
            .then(res => res.json())
            .then(stats => {
              if (stats.sent_count) document.getElementById('stat-sent').textContent = stats.sent_count;
            })
            .catch(err => console.log('Stats refresh error:', err));
        }, 500);
      } else {
        btn.disabled=false;
        btn.innerHTML='<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg> Send Certificate';
        showToast(data.message || 'Failed to send certificate','red');
      }
    })
    .catch(error => {
      console.error('Fetch error details:', error);
      btn.disabled=false;
      btn.innerHTML='<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg> Send Certificate';
      
      let errorMessage = 'Network error. Please try again.';
      if (error.message.includes('Failed to fetch')) {
        errorMessage = 'Network connection failed. Check your internet connection.';
      } else if (error.message.includes('HTTP error')) {
        errorMessage = 'Server error occurred. Please try again.';
      }
      
      showToast(errorMessage,'red');
    });
  }

  function sendBulkCertificates(){
    const eligible = DB_ASSESSMENTS.filter(a=>a.rank==='A');
    if(!eligible.length){ showToast('No eligible users found','yellow'); return; }
    if(!confirm(`Send certificates to all ${eligible.length} Rank A users?`)) return;
    showToast(`Sending ${eligible.length} certificates…`,'blue');
    
    // Process each eligible user
    let sent = 0;
    eligible.forEach(user => {
      fetch('send_certificate_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          email: user.email,
          vname: user.vname,
          score: user.score,
          rank: user.rank,
          certId: 'CS-' + String(user.vid).padStart(4,'0') + '-' + user.date.replace(/-/g,''),
          type: 'compliance',
          subject: `Your CyberShield Compliance Certificate - ${user.vname}`,
          message: `Congratulations on achieving a ${user.score}% score in the CyberShield Assessment. Your commitment to cybersecurity excellence is recognized and appreciated.`,
          currentDate: new Date().toLocaleDateString('en-US',{year:'numeric',month:'2-digit',day:'2-digit'}),
          user_id: user.vid
        })
      })
      .then(res => res.json())
      .then(data => {
        if(data.success) {
          sent++;
          if(sent === eligible.length) {
            showToast(`${sent} certificates sent successfully!`,'green');
            window.location.reload();
          }
        }
      })
      .catch(err => console.log('Bulk send error:', err));
    });
  }

  // ---- Sent Log table ----
  function renderLogTbl(){
    const allLogs = sessionSentLog;
    const tbody = document.getElementById('log-tbl-body');
    const empty = document.getElementById('log-empty');
    if(!allLogs.length){ tbody.innerHTML=''; empty.style.display=''; return; }
    empty.style.display='none';
    tbody.innerHTML = allLogs.map((l,i)=>`
      <tr>
        <td style="font-family:var(--mono);font-size:.7rem;color:var(--muted2)">${i+1}</td>
        <td style="font-weight:600">${escHtml(l.full_name||'—')}</td>
        <td style="color:var(--muted2);font-family:var(--mono);font-size:.72rem">${escHtml(l.email||'—')}</td>
        <td><span class="cert-type-tag">${escHtml(l.cert_type||'Compliance')}</span></td>
        <td><span style="color:${sc(l.score||0)};font-family:var(--mono);font-size:.78rem;font-weight:700">${l.score||'—'}%</span></td>
        <td style="color:var(--muted2);font-family:var(--mono);font-size:.72rem">${l.sent_at||'—'}</td>
        <td><span class="status-badge status-sent">✓ Sent</span></td>
      </tr>`).join('');
  }

  // ---- Init ----
  document.addEventListener('DOMContentLoaded',()=>{
    const th=localStorage.getItem('cs_th')||'dark';
    document.documentElement.setAttribute('data-theme',th);
    const m=document.getElementById('tmoon'),s=document.getElementById('tsun');
    if(m) m.style.display=th==='dark'?'':'none';
    if(s) s.style.display=th==='dark'?'none':'';
    const sb=localStorage.getItem('cs_sb');
    if(sb==='1') document.getElementById('sidebar').classList.add('collapsed');
    const d=document.getElementById('tb-date');
    if(d) d.textContent=new Date().toLocaleDateString('en-US',{weekday:'short',month:'short',day:'numeric',year:'numeric'});
    updateStats();
    renderCertTbl();
    renderLogTbl();
  });
</script>
<style>
  @keyframes spin{ to{ transform:rotate(360deg) } }
</style>
</body>
</html>