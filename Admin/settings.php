<?php
require_once '../includes/config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header('Location: ../index.html');
  exit();
}

$database = new Database();
$db = $database->getConnection();

// Get current admin user data
$user_query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($user_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  header('Location: ../index.html');
  exit();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>Settings — CyberShield</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
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

    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0
    }

    html,
    body {
      height: 100%;
      overflow: hidden
    }

    body {
      font-family: var(--font);
      background: var(--bg);
      color: var(--text);
      transition: background .18s, color .18s
    }

    .bg-grid {
      position: fixed;
      inset: 0;
      pointer-events: none;
      z-index: 0;
      background-image: linear-gradient(rgba(59, 139, 255, .025) 1px, transparent 1px), linear-gradient(90deg, rgba(59, 139, 255, .025) 1px, transparent 1px);
      background-size: 40px 40px
    }

    #app {
      display: flex;
      height: 100vh;
      position: relative;
      z-index: 1
    }

    #sidebar {
      width: 228px;
      min-width: 228px;
      background: var(--bg2);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      transition: width .18s, min-width .18s;
      overflow: hidden;
      z-index: 10;
      flex-shrink: 0
    }

    #sidebar.collapsed {
      width: 58px;
      min-width: 58px
    }

    .sb-brand {
      display: flex;
      align-items: center;
      gap: .75rem;
      padding: 1rem .9rem .9rem;
      border-bottom: 1px solid var(--border);
      flex-shrink: 0
    }

    .shield {
      width: 34px;
      height: 34px;
      background: linear-gradient(135deg, var(--blue), var(--purple));
      border-radius: 9px;
      display: grid;
      place-items: center;
      flex-shrink: 0;
      box-shadow: 0 0 16px rgba(59, 139, 255, .3)
    }

    .sb-brand-text {
      flex: 1;
      overflow: hidden;
      white-space: nowrap
    }

    .sb-brand-text h2 {
      font-family: var(--display);
      font-size: .95rem;
      font-weight: 700;
      letter-spacing: 1px
    }

    .sb-brand-text .badge {
      font-family: var(--mono);
      font-size: .55rem;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      background: rgba(255, 59, 92, .12);
      color: var(--red);
      border: 1px solid rgba(255, 59, 92, .2);
      border-radius: 4px;
      padding: .08rem .38rem;
      display: inline-block;
      margin-top: .1rem
    }

    .sb-toggle {
      width: 22px;
      height: 22px;
      background: none;
      border: 1px solid var(--border2);
      border-radius: 5px;
      cursor: pointer;
      color: var(--muted2);
      display: grid;
      place-items: center;
      flex-shrink: 0;
      transition: var(--t)
    }

    .sb-toggle:hover {
      border-color: var(--blue);
      color: var(--text)
    }

    #sidebar.collapsed .sb-toggle svg {
      transform: rotate(180deg)
    }

    .sb-section {
      flex: 1;
      overflow-y: auto;
      overflow-x: hidden;
      padding: .65rem 0
    }

    .sb-section::-webkit-scrollbar {
      width: 3px
    }

    .sb-section::-webkit-scrollbar-thumb {
      background: var(--border2);
      border-radius: 2px
    }

    .sb-label {
      font-family: var(--mono);
      font-size: .55rem;
      letter-spacing: 2px;
      text-transform: uppercase;
      color: var(--muted);
      padding: .5rem .9rem .25rem;
      white-space: nowrap;
      overflow: hidden
    }

    #sidebar.collapsed .sb-label {
      opacity: 0
    }

    .sb-divider {
      height: 1px;
      background: var(--border);
      margin: .5rem .9rem
    }

    .sb-item {
      display: flex;
      align-items: center;
      gap: .65rem;
      padding: .52rem .9rem;
      cursor: pointer;
      color: var(--muted2);
      font-size: .82rem;
      font-weight: 500;
      text-decoration: none;
      transition: var(--t);
      white-space: nowrap;
      overflow: hidden;
      position: relative
    }

    .sb-item:hover {
      background: rgba(59, 139, 255, .07);
      color: var(--text)
    }

    .sb-item.active {
      background: rgba(59, 139, 255, .1);
      color: var(--blue)
    }

    .sb-item.active::before {
      content: '';
      position: absolute;
      left: 0;
      top: 20%;
      bottom: 20%;
      width: 3px;
      background: var(--blue);
      border-radius: 0 3px 3px 0
    }

    .sb-icon {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 18px;
      flex-shrink: 0
    }

    .sb-text {
      overflow: hidden
    }

    #sidebar.collapsed .sb-text {
      display: none
    }

    .sb-footer {
      border-top: 1px solid var(--border);
      padding: .75rem .9rem;
      flex-shrink: 0
    }

    .sb-user {
      display: flex;
      align-items: center;
      gap: .65rem;
      overflow: hidden
    }

    .sb-avatar {
      width: 30px;
      height: 30px;
      border-radius: 8px;
      background: linear-gradient(135deg, var(--red), var(--orange));
      color: #fff;
      display: grid;
      place-items: center;
      font-size: .75rem;
      font-weight: 700;
      flex-shrink: 0;
      font-family: var(--display)
    }

    .sb-user-info {
      overflow: hidden;
      white-space: nowrap
    }

    .sb-user-info p {
      font-size: .82rem;
      font-weight: 600
    }

    .sb-user-info span {
      font-size: .68rem;
      color: var(--muted2)
    }

    #sidebar.collapsed .sb-user-info {
      display: none
    }

    .btn-sb-logout {
      display: flex;
      align-items: center;
      gap: .35rem;
      margin-top: .65rem;
      width: 100%;
      background: rgba(255, 59, 92, .08);
      border: 1px solid rgba(255, 59, 92, .18);
      color: var(--red);
      font-family: var(--font);
      font-size: .75rem;
      font-weight: 600;
      border-radius: 7px;
      padding: .42rem .8rem;
      cursor: pointer;
      transition: var(--t)
    }

    .btn-sb-logout:hover {
      background: rgba(255, 59, 92, .15)
    }

    #sidebar.collapsed .btn-sb-logout span {
      display: none
    }

    #main {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      min-width: 0
    }

    .topbar {
      height: 54px;
      min-height: 54px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 1.5rem;
      background: var(--bg2);
      border-bottom: 1px solid var(--border);
      gap: 1rem;
      flex-shrink: 0
    }

    .tb-bc {
      display: flex;
      align-items: center;
      gap: .4rem
    }

    .tb-app {
      font-family: var(--mono);
      font-size: .68rem;
      color: var(--muted);
      letter-spacing: .5px
    }

    .tb-title {
      font-family: var(--display);
      font-size: 1.05rem;
      letter-spacing: 1px
    }

    .tb-sub {
      font-family: var(--mono);
      font-size: .63rem;
      letter-spacing: .5px;
      color: var(--muted);
      margin-top: 1px
    }

    .tb-right {
      display: flex;
      align-items: center;
      gap: .55rem
    }

    .tb-search-wrap {
      position: relative
    }

    .tb-search-icon {
      position: absolute;
      left: .65rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--muted2);
      pointer-events: none
    }

    .tb-search {
      background: rgba(255, 255, 255, .04);
      border: 1px solid var(--border2);
      border-radius: 8px;
      padding: .38rem .8rem .38rem 2rem;
      font-family: var(--font);
      font-size: .78rem;
      color: var(--text);
      outline: none;
      width: 200px;
      transition: var(--t)
    }

    .tb-search:focus {
      border-color: rgba(59, 139, 255, .4)
    }

    .tb-search::placeholder {
      color: var(--muted)
    }

    .tb-date {
      font-family: var(--mono);
      font-size: .65rem;
      color: var(--muted2);
      white-space: nowrap
    }

    .tb-divider {
      width: 1px;
      height: 20px;
      background: var(--border2);
      margin: 0 .2rem
    }

    .tb-icon-btn {
      width: 32px;
      height: 32px;
      border-radius: 7px;
      border: 1px solid var(--border2);
      background: rgba(255, 255, 255, .04);
      cursor: pointer;
      display: grid;
      place-items: center;
      color: var(--muted2);
      transition: var(--t);
      flex-shrink: 0
    }

    .tb-icon-btn:hover {
      border-color: var(--blue);
      color: var(--text)
    }

    .tb-admin {
      display: flex;
      align-items: center;
      gap: .55rem;
      background: rgba(255, 255, 255, .04);
      border: 1px solid var(--border2);
      border-radius: 9px;
      padding: .28rem .65rem .28rem .28rem;
      cursor: pointer;
      transition: var(--t)
    }

    .tb-admin:hover {
      border-color: rgba(255, 59, 92, .28);
      background: rgba(255, 59, 92, .06)
    }

    .tb-admin-av {
      width: 28px;
      height: 28px;
      border-radius: 7px;
      background: linear-gradient(135deg, var(--red), var(--orange));
      color: #fff;
      display: grid;
      place-items: center;
      font-size: .7rem;
      font-weight: 700;
      flex-shrink: 0;
      font-family: var(--display)
    }

    .tb-admin-info {
      display: flex;
      flex-direction: column
    }

    .tb-admin-name {
      font-size: .78rem;
      font-weight: 600;
      line-height: 1.2
    }

    .tb-admin-role {
      font-size: .6rem;
      color: var(--red);
      letter-spacing: .5px;
      font-family: var(--mono)
    }

    .notif-wrap {
      position: relative
    }

    .notif-dot {
      position: absolute;
      top: 5px;
      right: 5px;
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--red);
      border: 1.5px solid var(--bg2)
    }

    .np {
      position: absolute;
      right: 0;
      top: calc(100% + 8px);
      width: 280px;
      background: var(--bg3);
      border: 1px solid var(--border2);
      border-radius: 10px;
      box-shadow: var(--shadow);
      z-index: 100
    }

    .np.hidden {
      display: none
    }

    .np-hdr {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: .75rem 1rem;
      border-bottom: 1px solid var(--border);
      font-size: .82rem;
      font-weight: 600
    }

    .np-hdr button {
      font-size: .72rem;
      color: var(--muted2);
      background: none;
      border: none;
      cursor: pointer
    }

    .np-empty {
      font-size: .8rem;
      color: var(--muted2);
      padding: 1rem;
      text-align: center
    }

    .np-item {
      display: flex;
      gap: .6rem;
      padding: .7rem 1rem;
      border-bottom: 1px solid var(--border);
      font-size: .78rem
    }

    .np-item:last-child {
      border-bottom: none
    }

    .np-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--red);
      flex-shrink: 0;
      margin-top: 4px
    }

    .content {
      flex: 1;
      overflow-y: auto;
      padding: 1.5rem
    }

    .content::-webkit-scrollbar {
      width: 4px
    }

    .content::-webkit-scrollbar-thumb {
      background: var(--border2);
      border-radius: 2px
    }

    .sec-hdr {
      margin-bottom: 1.25rem
    }

    .sec-hdr h2 {
      font-family: var(--display);
      font-size: 1.25rem;
      font-weight: 700;
      letter-spacing: .5px
    }

    .sec-hdr p {
      font-size: .82rem;
      color: var(--muted2);
      margin-top: .2rem
    }

    .card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 12px;
      box-shadow: var(--shadow);
      transition: border-color .18s
    }

    .card:hover {
      border-color: var(--border2)
    }

    .stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: .9rem;
      margin-bottom: 1.25rem
    }

    .stat-card {
      padding: 1.15rem 1.25rem;
      position: relative;
      overflow: hidden
    }

    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 2px;
      background: var(--accent, var(--blue));
      opacity: .7
    }

    .si {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      display: grid;
      place-items: center;
      margin-bottom: .65rem
    }

    .slabel {
      font-family: var(--mono);
      font-size: .6rem;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      color: var(--muted2);
      margin-bottom: .3rem
    }

    .sval {
      font-family: var(--display);
      font-size: 1.9rem;
      font-weight: 700;
      line-height: 1
    }

    .ssub {
      font-size: .7rem;
      color: var(--muted);
      margin-top: .3rem
    }

    .charts-grid {
      display: grid;
      gap: .9rem;
      margin-bottom: 1.25rem
    }

    .chart-card {
      padding: 1.15rem 1.25rem
    }

    .chart-card h3 {
      font-family: var(--mono);
      font-size: .65rem;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      color: var(--muted2);
      margin-bottom: .85rem;
      display: flex;
      align-items: center;
      gap: .5rem
    }

    .chart-card h3::before {
      content: '';
      width: 10px;
      height: 3px;
      background: var(--blue);
      border-radius: 2px;
      flex-shrink: 0
    }

    .cw {
      width: 100%
    }

    .cw.sm {
      height: 160px
    }

    .cw.md {
      height: 190px
    }

    .cw.lg {
      height: 240px
    }

    .tbl-card {
      padding: 1.25rem 1.5rem
    }

    .tbl-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: .65rem;
      margin-bottom: 1rem
    }

    .tbl-bar h3 {
      font-family: var(--display);
      font-size: 1rem;
      font-weight: 700
    }

    .frow {
      display: flex;
      align-items: center;
      gap: .5rem;
      flex-wrap: wrap
    }

    .fsel {
      background: rgba(255, 255, 255, .04);
      border: 1px solid var(--border2);
      border-radius: 7px;
      padding: .38rem .75rem;
      font-family: var(--font);
      font-size: .78rem;
      color: var(--text);
      cursor: pointer;
      outline: none;
      transition: var(--t)
    }

    .fsel:focus {
      border-color: var(--blue)
    }

    [data-theme=light] .fsel {
      background: #fff
    }

    .tw {
      overflow-x: auto
    }

    table {
      width: 100%;
      border-collapse: collapse
    }

    thead th {
      text-align: left;
      padding: .55rem .75rem;
      font-family: var(--mono);
      font-size: .6rem;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      color: var(--muted2);
      border-bottom: 1px solid var(--border);
      white-space: nowrap
    }

    tbody tr {
      border-bottom: 1px solid var(--border);
      transition: background .18s
    }

    tbody tr:last-child {
      border-bottom: none
    }

    tbody tr:hover {
      background: rgba(59, 139, 255, .04)
    }

    tbody td {
      padding: .65rem .75rem;
      font-size: .82rem
    }

    .rank {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 22px;
      height: 22px;
      border-radius: 5px;
      font-family: var(--mono);
      font-size: .7rem;
      font-weight: 700
    }

    .rA {
      background: rgba(16, 217, 130, .15);
      color: var(--green)
    }

    .rB {
      background: rgba(245, 183, 49, .15);
      color: var(--yellow)
    }

    .rC {
      background: rgba(255, 140, 66, .15);
      color: var(--orange)
    }

    .rD {
      background: rgba(255, 59, 92, .15);
      color: var(--red)
    }

    .sbw {
      display: flex;
      align-items: center;
      gap: .6rem
    }

    .sbb {
      flex: 1;
      height: 4px;
      background: var(--border2);
      border-radius: 2px
    }

    .sbf {
      height: 100%;
      border-radius: 2px
    }

    .sbn {
      font-family: var(--mono);
      font-size: .72rem;
      color: var(--muted2);
      min-width: 32px;
      text-align: right
    }

    .pgn {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: .4rem;
      margin-top: 1rem
    }

    .pb {
      min-width: 30px;
      height: 30px;
      border-radius: 6px;
      border: 1px solid var(--border2);
      background: none;
      font-family: var(--mono);
      font-size: .72rem;
      color: var(--muted2);
      cursor: pointer;
      display: grid;
      place-items: center;
      transition: var(--t)
    }

    .pb:hover,
    .pb.active {
      border-color: var(--blue);
      color: var(--blue);
      background: rgba(59, 139, 255, .07)
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      padding: .42rem .9rem;
      border-radius: 8px;
      font-family: var(--font);
      font-size: .78rem;
      font-weight: 600;
      cursor: pointer;
      transition: var(--t);
      border: none;
      text-decoration: none
    }

    .btn-p {
      background: var(--blue);
      color: #fff
    }

    .btn-p:hover {
      background: #2e7ae8
    }

    .btn-s {
      background: rgba(255, 255, 255, .05);
      color: var(--muted2);
      border: 1px solid var(--border2)
    }

    .btn-s:hover {
      border-color: var(--blue);
      color: var(--text)
    }

    .btn-d {
      background: rgba(255, 59, 92, .1);
      color: var(--red);
      border: 1px solid rgba(255, 59, 92, .2)
    }

    .btn-d:hover {
      background: rgba(255, 59, 92, .2)
    }

    .btn-sm {
      font-size: .72rem;
      padding: .32rem .7rem
    }

    .sdot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      display: inline-block;
      flex-shrink: 0
    }

    .sdot-g {
      background: var(--green);
      box-shadow: 0 0 6px rgba(16, 217, 130, .5)
    }

    .sdot-y {
      background: var(--yellow)
    }

    .sdot-r {
      background: var(--red);
      box-shadow: 0 0 6px rgba(255, 59, 92, .5)
    }

    .mo {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .6);
      display: grid;
      place-items: center;
      z-index: 200;
      backdrop-filter: blur(4px)
    }

    .mo.hidden {
      display: none
    }

    .modal {
      background: var(--bg3);
      border: 1px solid var(--border2);
      border-radius: 14px;
      width: min(90vw, 560px);
      box-shadow: 0 20px 60px rgba(0, 0, 0, .6);
      animation: su .2s ease
    }

    @keyframes su {
      from {
        opacity: 0;
        transform: translateY(20px)
      }

      to {
        opacity: 1;
        transform: none
      }
    }

    .mhdr {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1rem 1.25rem;
      border-bottom: 1px solid var(--border)
    }

    .mhdr h3 {
      font-family: var(--display);
      font-size: 1rem;
      font-weight: 700
    }

    .mcl {
      width: 28px;
      height: 28px;
      border-radius: 7px;
      border: 1px solid var(--border2);
      background: none;
      color: var(--muted2);
      cursor: pointer;
      display: grid;
      place-items: center;
      transition: var(--t)
    }

    .mcl:hover {
      border-color: var(--red);
      color: var(--red)
    }

    .mbdy {
      padding: 1.25rem
    }

    .ts {
      position: relative;
      display: inline-block;
      width: 38px;
      height: 21px;
      flex-shrink: 0
    }

    .ts input {
      opacity: 0;
      width: 0;
      height: 0
    }

    .tsl {
      position: absolute;
      inset: 0;
      cursor: pointer;
      background: rgba(255, 255, 255, .1);
      border-radius: 21px;
      transition: var(--t)
    }

    .tsl::before {
      content: '';
      position: absolute;
      height: 15px;
      width: 15px;
      left: 3px;
      bottom: 3px;
      background: var(--muted2);
      border-radius: 50%;
      transition: var(--t)
    }

    .ts input:checked+.tsl {
      background: var(--blue)
    }

    .ts input:checked+.tsl::before {
      transform: translateX(17px);
      background: #fff
    }

    #toast-c {
      position: fixed;
      bottom: 1.25rem;
      right: 1.25rem;
      display: flex;
      flex-direction: column;
      gap: .5rem;
      z-index: 300
    }

    .toast {
      background: var(--bg3);
      border: 1px solid var(--border2);
      border-radius: 9px;
      padding: .75rem 1rem;
      font-size: .82rem;
      box-shadow: var(--shadow);
      display: flex;
      align-items: center;
      gap: .6rem;
      animation: sl .2s ease;
      min-width: 240px
    }

    @keyframes sl {
      from {
        opacity: 0;
        transform: translateX(20px)
      }

      to {
        opacity: 1;
        transform: none
      }
    }

    .ti {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      flex-shrink: 0
    }

    .fi {
      background: rgba(255, 255, 255, .04);
      border: 1px solid var(--border2);
      border-radius: 8px;
      padding: .5rem .85rem;
      font-family: var(--font);
      font-size: .82rem;
      color: var(--text);
      outline: none;
      transition: var(--t);
      width: 100%
    }

    .fi:focus {
      border-color: var(--blue)
    }

    .fi[readonly],
    .fi:disabled {
      opacity: .6;
      cursor: not-allowed
    }

    textarea.fi {
      resize: vertical
    }

    [data-theme=light] .fi {
      background: #f8fafc
    }

    .fl {
      font-family: var(--mono);
      font-size: .62rem;
      letter-spacing: 1px;
      text-transform: uppercase;
      color: var(--muted);
      display: block;
      margin-bottom: .4rem
    }

    .fg {
      margin-bottom: .85rem
    }

    .pref-r {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: .6rem 0;
      border-bottom: 1px solid var(--border)
    }

    .pref-r:last-child {
      border-bottom: none
    }

    .tab-btns {
      display: flex;
      gap: .4rem;
      margin-bottom: 1.25rem;
      border-bottom: 1px solid var(--border);
      padding-bottom: .65rem;
      flex-wrap: wrap
    }

    .tab-btn {
      padding: .4rem .85rem;
      background: none;
      border: 1px solid transparent;
      cursor: pointer;
      border-radius: 7px;
      transition: var(--t);
      font-family: var(--font);
      font-size: .8rem;
      color: var(--muted2)
    }

    .tab-btn.active {
      background: rgba(59, 139, 255, .1);
      color: var(--blue);
      border-color: rgba(59, 139, 255, .2)
    }

    .tab-btn:hover {
      color: var(--text)
    }

    .tab-c {
      display: none
    }

    .tab-c.active {
      display: block
    }

    .settings-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
      gap: 1.5rem
    }

    .danger-zone {
      border: 1px solid rgba(255, 59, 92, .3);
      border-radius: 12px;
      padding: 1.5rem
    }

    .danger-zone h3 {
      color: var(--red);
      margin-bottom: 1rem;
      font-family: var(--display);
      font-size: 1.1rem;
      letter-spacing: 1px
    }

    .notif-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: .55rem 0;
      border-bottom: 1px solid var(--border)
    }

    .notif-item:last-child {
      border-bottom: none
    }

    .api-key {
      font-family: var(--mono);
      background: rgba(255, 255, 255, .04);
      padding: .5rem .85rem;
      border-radius: 8px;
      word-break: break-all;
      font-size: .8rem;
      border: 1px solid var(--border2);
      margin-bottom: .5rem
    }

    /* Form Error */
    .form-error {
      font-size: .75rem;
      color: var(--red);
      background: rgba(255, 59, 92, .08);
      border: 1px solid rgba(255, 59, 92, .2);
      border-radius: 7px;
      padding: .45rem .75rem;
      margin-bottom: .65rem
    }

    /* OTP Modal Styles */
    .otp-input-group {
      display: flex;
      gap: 0.5rem;
      justify-content: center;
      margin: 1.5rem 0
    }

    .otp-digit {
      width: 50px;
      height: 60px;
      text-align: center;
      font-size: 1.5rem;
      font-family: var(--mono);
      font-weight: 600;
      background: var(--bg2);
      border: 1px solid var(--border2);
      border-radius: 10px;
      color: var(--text);
      outline: none;
      transition: var(--t)
    }

    .otp-digit:focus {
      border-color: var(--blue);
      box-shadow: 0 0 0 2px rgba(59, 139, 255, .2)
    }

    .otp-digit::-webkit-outer-spin-button,
    .otp-digit::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0
    }

    .resend-timer {
      text-align: center;
      font-size: 0.75rem;
      color: var(--muted2);
      margin-top: 0.75rem
    }

    .resend-link {
      color: var(--blue);
      cursor: pointer;
      text-decoration: none;
      font-weight: 500
    }

    .resend-link:hover {
      text-decoration: underline
    }
  </style>
</head>

<body>
  <div class="bg-grid"></div>
  <div id="app">

    <aside id="sidebar">
      <div class="sb-brand">
        <div class="shield"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white"
            stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
          </svg></div>
        <div class="sb-brand-text">
          <h2>CyberShield</h2><span class="badge">Admin Panel</span>
        </div>
        <button class="sb-toggle" onclick="toggleSidebar()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6" />
          </svg></button>
      </div>
      <div class="sb-section">
        <div class="sb-label">Navigation</div>
        <a class="sb-item" href="dashboard.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="7" height="7" rx="1.2" />
              <rect x="14" y="3" width="7" height="7" rx="1.2" />
              <rect x="3" y="14" width="7" height="7" rx="1.2" />
              <rect x="14" y="14" width="7" height="7" rx="1.2" />
            </svg></span><span class="sb-text">Dashboard</span></a>
        <a class="sb-item" href="reports.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="20" x2="18" y2="10" />
              <line x1="12" y1="20" x2="12" y2="4" />
              <line x1="6" y1="20" x2="6" y2="14" />
            </svg></span><span class="sb-text">Reports</span></a>
        <a class="sb-item" href="users.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
            </svg></span><span class="sb-text">Users</span></a>
        <a class="sb-item" href="heatmap.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="7" height="7" />
              <rect x="14" y="3" width="7" height="7" />
              <rect x="14" y="14" width="7" height="7" />
              <rect x="3" y="14" width="7" height="7" />
            </svg></span><span class="sb-text">Risk Heatmap</span></a>
        <a class="sb-item" href="activity.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <polyline points="14 2 14 8 20 8" />
              <line x1="16" y1="13" x2="8" y2="13" />
              <line x1="16" y1="17" x2="8" y2="17" />
            </svg></span><span class="sb-text">Activity Log</span></a>
        <a class="sb-item active" href="settings.php"><span class="sb-icon"><svg width="15" height="15"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"
              stroke-linejoin="round">
              <circle cx="12" cy="8" r="4" />
              <path d="M6 20v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2" />
            </svg></span><span class="sb-text">Settings</span></a>
        <a class="sb-item" href="send_certificate.php"><span class="sb-icon"><svg width="15" height="15"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"
              stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <polyline points="14 2 14 8 20 8" />
              <line x1="16" y1="13" x2="8" y2="13" />
              <line x1="16" y1="17" x2="8" y2="17" />
              <path d="M10 19l-2 2v-3" />
              <path d="M14 19l2 2v-3" />
            </svg></span><span class="sb-text">Certificates</span></a>
        <div class="sb-divider"></div>
        <div class="sb-label">Tools</div>
        <a class="sb-item" href="compare.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="8" x2="6" y2="8" />
              <line x1="21" y1="16" x2="3" y2="16" />
            </svg></span><span class="sb-text">Compare</span></a>
        <a class="sb-item" href="email.php"><span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
              <polyline points="22,6 12,13 2,6" />
            </svg></span><span class="sb-text">Email Report</span></a>
        <div class="sb-divider"></div>
        <div class="sb-label">Quick Actions</div>
        <a class="sb-item" onclick="showToast('CSV exported','green')"><span class="sb-icon"><svg width="15" height="15"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"
              stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
              <polyline points="7 10 12 15 17 10" />
              <line x1="12" y1="15" x2="12" y2="3" />
            </svg></span><span class="sb-text">Export CSV</span></a>
        <a class="sb-item" onclick="showToast('PDF exported','green')"><span class="sb-icon"><svg width="15" height="15"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"
              stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <polyline points="14 2 14 8 20 8" />
            </svg></span><span class="sb-text">Export PDF</span></a>
        <a class="sb-item" onclick="showToast('Data refreshed','blue')"><span class="sb-icon"><svg width="15"
              height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
              stroke-linecap="round" stroke-linejoin="round">
              <polyline points="23 4 23 10 17 10" />
              <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
            </svg></span><span class="sb-text">Refresh Data</span></a>
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
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <polyline points="16 17 21 12 16 7" />
            <line x1="21" y1="12" x2="9" y2="12" />
          </svg>
          <span>Sign Out</span>
        </button>
      </div>
    </aside>
    <div id="main">

      <div class="topbar">
        <div>
          <div class="tb-bc">
            <span class="tb-app">CyberShield</span>
            <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"
              stroke-linecap="round">
              <path d="M6 4l4 4-4 4" />
            </svg>
            <span class="tb-title">Admin Settings</span>
          </div>
          <p class="tb-sub">Account and preferences</p>
        </div>
        <div class="tb-right">
          <div class="tb-search-wrap">
            <span class="tb-search-icon"><svg width="12" height="12" viewBox="0 0 20 20" fill="none">
                <circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.7" />
                <path d="M15 15l3 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
              </svg></span>
            <input type="text" class="tb-search" placeholder="Search vendors, scores…" autocomplete="off" />
          </div>
          <span class="tb-date" id="tb-date"></span>
          <div class="tb-divider"></div>
          <a class="tb-admin" href="settings.php">
            <div class="tb-admin-av"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
            <div class="tb-admin-info"><span
                class="tb-admin-name"><?php echo htmlspecialchars($user['full_name']); ?></span><span
                class="tb-admin-role"><?php echo htmlspecialchars($user['role'] ?? 'Admin'); ?></span>
            </div>
            <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"
              stroke-linecap="round" style="color:var(--muted);margin-left:.2rem">
              <path d="M4 6l4 4 4-4" />
            </svg>
          </a>
        </div>
      </div>
      <div class="content">

        <div class="sec-hdr">
          <h2>Admin Settings</h2>
          <p>Configure your account and security settings.</p>
        </div>
        <div class="tab-btns">
          <button class="tab-btn active" onclick="switchTab('profile',this)">Profile</button>
        </div>
        <!-- PROFILE -->
        <div id="tab-profile" class="tab-c active">
          <div class="settings-grid">
            <div class="card" style="padding:1.5rem">
              <h3 style="font-family:var(--display);font-size:1rem;font-weight:700;margin-bottom:1.15rem">Profile
                Information</h3>
              <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem">
                <div
                  style="width:52px;height:52px;border-radius:12px;background:linear-gradient(135deg,var(--red),var(--orange));color:#fff;display:grid;place-items:center;font-size:1.3rem;font-weight:700;font-family:var(--display);flex-shrink:0">
                  A</div>
                <div>
                  <div style="font-weight:700;font-size:1rem"><?php echo htmlspecialchars($user['full_name']); ?></div>
                  <div style="font-size:.8rem;color:var(--muted2)"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>
              </div>
              <div class="fg"><label class="fl">Display Name</label><input class="fi" type="text" id="profile-name"
                  value="<?php echo htmlspecialchars($user['full_name']); ?>" />
              </div>
              <div class="fg"><label class="fl">Email</label><input class="fi" type="email" id="profile-email"
                  value="<?php echo htmlspecialchars($user['email']); ?>" />
              </div>
              <div class="fg"><label class="fl">Organization</label><input class="fi" type="text" id="profile-company"
                  value="<?php echo htmlspecialchars($user['store_name'] ?? ''); ?>" />
              </div>
              <div class="fg"><label class="fl">Language</label><select class="fi">
                  <option>English</option>
                  <option>Spanish</option>
                  <option>French</option>
                </select></div>
              <div class="fg"><label class="fl">Timezone</label><select class="fi">
                  <option>UTC</option>
                  <option>Eastern Time</option>
                  <option>Pacific Time</option>
                </select></div>
              <button class="btn btn-p" style="width:100%;justify-content:center" onclick="requestProfileOTP()">Save
                Changes</button>
            </div>
            <div class="card" style="padding:1.5rem">
              <h3 style="font-family:var(--display);font-size:1rem;font-weight:700;margin-bottom:1.15rem">Change
                Password</h3>
              <div class="fg"><label class="fl">Current Password</label><input class="fi" type="password"
                  id="pw-current" placeholder="•••••••" /></div>
              <div class="fg"><label class="fl">New Password</label><input class="fi" type="password" id="pw-new"
                  placeholder="••••••••" />
                <div style="font-size:.72rem;color:var(--muted2);margin-top:.35rem">Min 8 chars, uppercase, lowercase,
                  numbers.</div>
              </div>
              <div class="fg"><label class="fl">Confirm New Password</label><input class="fi" type="password"
                  id="pw-confirm" placeholder="••••••••" /></div>
              <div id="pw-change-error" class="form-error" style="display:none"></div>
              <button class="btn btn-p" onclick="requestPasswordOTP()">Update Password</button>
            </div>
          </div>
        </div>
        <!-- NOTIFICATIONS -->
        <div id="tab-notifications" class="tab-c">
          <div class="card" style="padding:1.5rem;max-width:600px">
            <h3 style="font-family:var(--display);font-size:1rem;font-weight:700;margin-bottom:1.15rem">Notification
              Preferences</h3>
            <div class="notif-item">
              <div>
                <div style="font-size:.88rem;font-weight:600">Email Alerts</div>
                <div style="font-size:.76rem;color:var(--muted2)">Receive important security alerts via email</div>
              </div><label class="ts"><input type="checkbox" checked><span class="tsl"></span></label>
            </div>
            <div class="notif-item">
              <div>
                <div style="font-size:.88rem;font-weight:600">High Risk Vendor Alerts</div>
                <div style="font-size:.76rem;color:var(--muted2)">Get notified when vendors are flagged as high risk
                </div>
              </div><label class="ts"><input type="checkbox" checked><span class="tsl"></span></label>
            </div>
            <div class="notif-item">
              <div>
                <div style="font-size:.88rem;font-weight:600">Daily Summary</div>
                <div style="font-size:.76rem;color:var(--muted2)">Receive a daily summary of activities</div>
              </div><label class="ts"><input type="checkbox"><span class="tsl"></span></label>
            </div>
            <div class="notif-item">
              <div>
                <div style="font-size:.88rem;font-weight:600">Weekly Report</div>
                <div style="font-size:.76rem;color:var(--muted2)">Get weekly comprehensive risk reports</div>
              </div><label class="ts"><input type="checkbox" checked><span class="tsl"></span></label>
            </div>
            <div class="notif-item">
              <div>
                <div style="font-size:.88rem;font-weight:600">Browser Notifications</div>
                <div style="font-size:.76rem;color:var(--muted2)">Show desktop notifications for important events</div>
              </div><label class="ts"><input type="checkbox"><span class="tsl"></span></label>
            </div>
            <button class="btn btn-p" style="margin-top:1rem" onclick="showToast('Preferences saved','green')">Save
              Preferences</button>
          </div>
        </div>
        <!-- API KEYS -->
        <div id="tab-api" class="tab-c">
          <div class="card" style="padding:1.5rem;max-width:600px">
            <h3 style="font-family:var(--display);font-size:1rem;font-weight:700;margin-bottom:1.15rem">API Keys</h3>
            <div id="api-keys-list">
              <div class="api-key">
                <div style="display:flex;justify-content:space-between;align-items:center"><code
                    style="font-family:var(--mono);font-size:.78rem">cybershield_xk9m2p3r7q...••••••••</code><button
                    class="btn btn-s btn-sm" onclick="showToast('Copied','blue')">Copy</button></div>
                <div style="font-size:.7rem;color:var(--muted2);margin-top:.3rem">Created: Jan 15, 2025</div>
              </div>
            </div>
            <button class="btn btn-p" style="margin-top:1rem" onclick="genKey()">Generate New API Key</button>
          </div>
        </div>
        <!-- DANGER ZONE -->
        <div id="tab-danger" class="tab-c">
          <div class="danger-zone" style="max-width:600px">
            <h3>⚠ Danger Zone</h3>
            <div class="notif-item">
              <div>
                <div style="font-size:.88rem;font-weight:600">Delete Account</div>
                <div style="font-size:.76rem;color:var(--muted2)">Permanently delete your account and all data</div>
              </div><button class="btn btn-d" onclick="showToast('Confirm in dialog','red')">Delete Account</button>
            </div>
            <div class="notif-item" style="margin-top:.5rem">
              <div>
                <div style="font-size:.88rem;font-weight:600">Export All Data</div>
                <div style="font-size:.76rem;color:var(--muted2)">Download all your data as JSON</div>
              </div><button class="btn btn-s" onclick="showToast('Data exported','green')">Export Data</button>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <div id="modal-overlay" class="mo hidden" onclick="if(event.target===this)closeModal()">
    <div class="modal">
      <div class="mhdr">
        <h3 id="modal-title">Detail</h3><button class="mcl" onclick="closeModal()"><svg width="13" height="13"
            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg></button>
      </div>
      <div class="mbdy" id="modal-body"></div>
    </div>
  </div>
  <div id="toast-c"></div>

  <!-- OTP Modal for Profile Changes -->
  <div id="otp-profile-modal" class="mo hidden" onclick="if(event.target===this)closeOTPModal('profile')">
    <div class="modal">
      <div class="mhdr">
        <h3>🔐 Verify with OTP</h3>
        <button class="mcl" onclick="closeOTPModal('profile')"><svg width="13" height="13" viewBox="0 0 24 24"
            fill="none" stroke="currentColor" stroke-width="2.2">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg></button>
      </div>
      <div class="mbdy">
        <p style="text-align:center;margin-bottom:0.5rem">A verification code has been sent to your email</p>
        <p style="text-align:center;font-size:0.75rem;color:var(--muted2);margin-bottom:1rem">
          <?php echo htmlspecialchars($user['email']); ?></p>
        <div class="otp-input-group" id="profile-otp-group">
          <input type="text" maxlength="1" class="otp-digit" id="profile-otp-1"
            onkeyup="moveToNext(this, 'profile-otp-2')" onkeydown="handleBackspace(event, 'profile-otp-1')">
          <input type="text" maxlength="1" class="otp-digit" id="profile-otp-2"
            onkeyup="moveToNext(this, 'profile-otp-3')" onkeydown="handleBackspace(event, 'profile-otp-1')">
          <input type="text" maxlength="1" class="otp-digit" id="profile-otp-3"
            onkeyup="moveToNext(this, 'profile-otp-4')" onkeydown="handleBackspace(event, 'profile-otp-2')">
          <input type="text" maxlength="1" class="otp-digit" id="profile-otp-4"
            onkeyup="moveToNext(this, 'profile-otp-5')" onkeydown="handleBackspace(event, 'profile-otp-3')">
          <input type="text" maxlength="1" class="otp-digit" id="profile-otp-5"
            onkeyup="moveToNext(this, 'profile-otp-6')" onkeydown="handleBackspace(event, 'profile-otp-4')">
          <input type="text" maxlength="1" class="otp-digit" id="profile-otp-6" onkeyup="verifyProfileOTP()"
            onkeydown="handleBackspace(event, 'profile-otp-5')">
        </div>
        <div id="profile-otp-error" class="form-error" style="display:none;text-align:center"></div>
        <div class="resend-timer" id="profile-resend-timer">Resend code in <span id="profile-countdown">60</span>
          seconds</div>
        <div style="display:flex;gap:0.75rem;justify-content:center;margin-top:1rem">
          <button class="btn btn-s" onclick="closeOTPModal('profile')">Cancel</button>
          <button class="btn btn-p" onclick="verifyProfileOTP()">Verify & Save</button>
        </div>
      </div>
    </div>
  </div>

  <!-- OTP Modal for Password Change -->
  <div id="otp-password-modal" class="mo hidden" onclick="if(event.target===this)closeOTPModal('password')">
    <div class="modal">
      <div class="mhdr">
        <h3>🔐 Verify with OTP</h3>
        <button class="mcl" onclick="closeOTPModal('password')"><svg width="13" height="13" viewBox="0 0 24 24"
            fill="none" stroke="currentColor" stroke-width="2.2">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg></button>
      </div>
      <div class="mbdy">
        <p style="text-align:center;margin-bottom:0.5rem">A verification code has been sent to your email</p>
        <p style="text-align:center;font-size:0.75rem;color:var(--muted2);margin-bottom:1rem">
          <?php echo htmlspecialchars($user['email']); ?></p>
        <div class="otp-input-group" id="password-otp-group">
          <input type="text" maxlength="1" class="otp-digit" id="password-otp-1"
            onkeyup="moveToNext(this, 'password-otp-2')" onkeydown="handleBackspace(event, 'password-otp-1')">
          <input type="text" maxlength="1" class="otp-digit" id="password-otp-2"
            onkeyup="moveToNext(this, 'password-otp-3')" onkeydown="handleBackspace(event, 'password-otp-1')">
          <input type="text" maxlength="1" class="otp-digit" id="password-otp-3"
            onkeyup="moveToNext(this, 'password-otp-4')" onkeydown="handleBackspace(event, 'password-otp-2')">
          <input type="text" maxlength="1" class="otp-digit" id="password-otp-4"
            onkeyup="moveToNext(this, 'password-otp-5')" onkeydown="handleBackspace(event, 'password-otp-3')">
          <input type="text" maxlength="1" class="otp-digit" id="password-otp-5"
            onkeyup="moveToNext(this, 'password-otp-6')" onkeydown="handleBackspace(event, 'password-otp-4')">
          <input type="text" maxlength="1" class="otp-digit" id="password-otp-6" onkeyup="verifyPasswordOTP()"
            onkeydown="handleBackspace(event, 'password-otp-5')">
        </div>
        <div id="password-otp-error" class="form-error" style="display:none;text-align:center"></div>
        <div class="resend-timer" id="password-resend-timer">Resend code in <span id="password-countdown">60</span>
          seconds</div>
        <div style="display:flex;gap:0.75rem;justify-content:center;margin-top:1rem">
          <button class="btn btn-s" onclick="closeOTPModal('password')">Cancel</button>
          <button class="btn btn-p" onclick="verifyPasswordOTP()">Verify & Update</button>
        </div>
      </div>
    </div>
  </div>
  <script>
    const MOCK = {
      vendors: [
        { id: 1, name: 'TechNova Solutions' }, { id: 2, name: 'CloudSafe Inc' },
        { id: 3, name: 'Apex Corp' }, { id: 4, name: 'DataGuard LLC' },
        { id: 5, name: 'NetShield Pro' }, { id: 6, name: 'Vertex Systems' },
        { id: 7, name: 'IronCore Security' }, { id: 8, name: 'BlueSky Tech' },
        { id: 9, name: 'CipherNet' }, { id: 10, name: 'Quantum Sec' },
        { id: 11, name: 'SafeNet LLC' }, { id: 12, name: 'TrustArc Inc' }
      ],
      cats: ['Access Control', 'Network Security', 'Data Encryption', 'Compliance', 'Incident Response', 'Physical Security']
    };
    MOCK.assessments = Array.from({ length: 60 }, (_, i) => {
      const s = Math.round(Math.random() * 78 + 20);
      const r = s >= 80 ? 'A' : s >= 60 ? 'B' : s >= 40 ? 'C' : 'D';
      const v = MOCK.vendors[i % MOCK.vendors.length];
      const d = new Date(2024, Math.floor(Math.random() * 14), Math.floor(Math.random() * 28) + 1);
      return { id: i + 1, vid: v.id, vname: v.name, score: s, rank: r, cat: MOCK.cats[i % 6], date: d.toISOString().split('T')[0] };
    });
    MOCK.activity = [
      { type: 'export', msg: 'Admin exported CSV report', time: '2 min ago' },
      { type: 'alert', msg: 'Apex Corp dropped to Rank D', time: '15 min ago' },
      { type: 'refresh', msg: 'Data refreshed manually', time: '32 min ago' },
      { type: 'flag', msg: 'NetShield Pro flagged for review', time: '1 hr ago' },
      { type: 'profile', msg: 'Admin profile updated', time: '3 hrs ago' },
      { type: 'export', msg: 'PDF report downloaded', time: '5 hrs ago' },
      { type: 'alert', msg: 'Quantum Sec score dropped 12%', time: '8 hrs ago' },
    ];

    function sc(s) { return s >= 80 ? 'var(--green)' : s >= 60 ? 'var(--yellow)' : s >= 40 ? 'var(--orange)' : 'var(--red)' }
    function isDark() { return document.documentElement.getAttribute('data-theme') === 'dark' }
    function ax() { return isDark() ? { tick: '#8898b4', grid: 'rgba(59,139,255,.04)', tt: '#0d1421', ttB: 'rgba(255,255,255,.1)', tc: '#dde4f0', bc: '#8898b4' } : { tick: '#64748b', grid: 'rgba(0,0,0,.06)', tt: '#fff', ttB: 'rgba(0,0,0,.1)', tc: '#0f172a', bc: '#475569' } }
    const CC = { A: { s: '#10D982', b: 'rgba(16,217,130,.55)' }, B: { s: '#F5B731', b: 'rgba(245,183,49,.55)' }, C: { s: '#FF7A45', b: 'rgba(255,122,69,.55)' }, D: { s: '#FF4D6A', b: 'rgba(255,77,106,.55)' } };
    function riskCounts() {
      const lat = {};
      MOCK.assessments.forEach(a => { if (!lat[a.vid] || a.date > lat[a.vid].date) lat[a.vid] = a; });
      const c = { A: 0, B: 0, C: 0, D: 0 };
      Object.values(lat).forEach(a => c[a.rank]++);
      return c;
    }
    function toggleSidebar() {
      document.getElementById('sidebar').classList.toggle('collapsed');
      localStorage.setItem('cs_sb', document.getElementById('sidebar').classList.contains('collapsed') ? '1' : '0');
    }
    function toggleTheme() {
      const d = !isDark();
      document.documentElement.setAttribute('data-theme', d ? 'dark' : 'light');
      localStorage.setItem('cs_th', d ? 'dark' : 'light');
      const m = document.getElementById('tmoon'), s = document.getElementById('tsun');
      if (m) m.style.display = d ? '' : 'none';
      if (s) s.style.display = d ? 'none' : '';
      if (typeof onThemeChange === 'function') onThemeChange();
    }
    function toggleNotif() {
      const p = document.getElementById('np');
      if (p) p.classList.toggle('hidden');
    }
    function clearNotifs() {
      const l = document.getElementById('np-list');
      if (l) l.innerHTML = '<p class="np-empty">No alerts</p>';
      const d = document.getElementById('notif-dot');
      if (d) d.style.display = 'none';
      const p = document.getElementById('np');
      if (p) p.classList.add('hidden');
    }
    function showToast(msg, color = 'blue') {
      const cols = { blue: 'var(--blue)', green: 'var(--green)', red: 'var(--red)', yellow: 'var(--yellow)' };
      const t = document.createElement('div'); t.className = 'toast';
      t.innerHTML = `<span class="ti" style="background:${cols[color] || cols.blue}"></span><span>${msg}</span>`;
      document.getElementById('toast-c').appendChild(t);
      setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; setTimeout(() => t.remove(), 300); }, 2500);
    }
    function doLogout() {
      if (confirm('Are you sure you want to sign out?')) {
        window.location.href = 'logout.php';
      }
    }
    function closeModal() { document.getElementById('modal-overlay').classList.add('hidden') }
    document.addEventListener('DOMContentLoaded', () => {
      const th = localStorage.getItem('cs_th') || 'dark';
      document.documentElement.setAttribute('data-theme', th);
      const m = document.getElementById('tmoon'), s = document.getElementById('tsun');
      if (m) m.style.display = th === 'dark' ? '' : 'none';
      if (s) s.style.display = th === 'dark' ? 'none' : '';
      const sb = localStorage.getItem('cs_sb');
      if (sb === '1') document.getElementById('sidebar').classList.add('collapsed');
      const d = document.getElementById('tb-date');
      if (d) d.textContent = new Date().toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
      if (typeof pageInit === 'function') pageInit();
    });

    function pageInit() {
      const d = isDark();
      const cb = document.getElementById('pref-dark');
      if (cb) cb.checked = d;
    }
    function switchTab(name, btn) {
      document.querySelectorAll('.tab-c').forEach(el => el.classList.remove('active'));
      document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
      document.getElementById('tab-' + name).classList.add('active');
      btn.classList.add('active');
    }
    function genKey() {
      const key = 'cybershield_' + Math.random().toString(36).substr(2, 20);
      const el = document.createElement('div'); el.className = 'api-key';
      el.innerHTML = `<div style="display:flex;justify-content:space-between;align-items:center"><code style="font-family:var(--mono);font-size:.78rem">${key}</code><button class="btn btn-s btn-sm" onclick="showToast('Copied','blue')">Copy</button></div><div style="font-size:.7rem;color:var(--muted2);margin-top:.3rem">Created: ${new Date().toLocaleDateString()}</div>`;
      document.getElementById('api-keys-list').prepend(el);
      showToast('New API key generated', 'green');
    }

    // Global variables to store pending data
    let pendingProfileData = null;
    let pendingPasswordData = null;
    let profileResendTimer = null;
    let passwordResendTimer = null;
    let profileCountdown = 60;
    let passwordCountdown = 60;

    // ==================== OTP Input Handlers ====================
    function moveToNext(current, nextId) {
      if (current.value.length === 1) {
        const next = document.getElementById(nextId);
        if (next) next.focus();
      }
    }

    function handleBackspace(event, prevId) {
      if (event.key === 'Backspace' && event.target.value === '') {
        const prev = document.getElementById(prevId);
        if (prev) prev.focus();
      }
    }

    function getOTPValue(prefix) {
      let otp = '';
      for (let i = 1; i <= 6; i++) {
        const digit = document.getElementById(`${prefix}-otp-${i}`).value;
        if (!digit) return null;
        otp += digit;
      }
      return otp;
    }

    function clearOTPInputs(prefix) {
      for (let i = 1; i <= 6; i++) {
        const input = document.getElementById(`${prefix}-otp-${i}`);
        if (input) input.value = '';
      }
      const firstInput = document.getElementById(`${prefix}-otp-1`);
      if (firstInput) firstInput.focus();
    }

    function closeOTPModal(type) {
      const modal = document.getElementById(`otp-${type}-modal`);
      if (modal) modal.classList.add('hidden');
      // Clear OTP inputs
      clearOTPInputs(type);
      // Clear error message
      const errorEl = document.getElementById(`${type}-otp-error`);
      if (errorEl) errorEl.style.display = 'none';
    }

    // ==================== Profile OTP Flow ====================
    async function requestProfileOTP() {
      const fullName = document.getElementById('profile-name').value.trim();
      const email = document.getElementById('profile-email').value.trim();
      const storeName = document.getElementById('profile-company').value.trim();

      if (!fullName) { showToast('Display name is required', 'red'); return; }
      if (!email) { showToast('Email address is required', 'red'); return; }
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) { showToast('Please enter a valid email address', 'red'); return; }

      // Store pending data
      pendingProfileData = { full_name: fullName, email: email, store_name: storeName };

      try {
        // Send OTP request to server
        const response = await fetch('../api/send_otp.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ type: 'profile' })
        });
        const result = await response.json();

        if (result.success) {
          showToast('OTP sent to your email', 'green');
          // Reset countdown
          profileCountdown = 60;
          startProfileResendTimer();
          // Clear previous OTP inputs
          clearOTPInputs('profile');
          // Show OTP modal
          document.getElementById('otp-profile-modal').classList.remove('hidden');
          // Clear any previous error
          document.getElementById('profile-otp-error').style.display = 'none';
        } else {
          showToast(result.error || 'Failed to send OTP', 'red');
        }
      } catch (error) {
        console.error('Error sending OTP:', error);
        showToast('Error connecting to server', 'red');
      }
    }

    function startProfileResendTimer() {
      if (profileResendTimer) clearInterval(profileResendTimer);
      const timerSpan = document.getElementById('profile-countdown');
      const resendDiv = document.getElementById('profile-resend-timer');

      profileResendTimer = setInterval(() => {
        if (profileCountdown <= 1) {
          clearInterval(profileResendTimer);
          resendDiv.innerHTML = '<span class="resend-link" onclick="resendProfileOTP()">Resend Code</span>';
        } else {
          profileCountdown--;
          timerSpan.textContent = profileCountdown;
        }
      }, 1000);
    }

    async function resendProfileOTP() {
      try {
        const response = await fetch('../api/send_otp.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ type: 'profile' })
        });
        const result = await response.json();

        if (result.success) {
          showToast('OTP resent to your email', 'green');
          profileCountdown = 60;
          startProfileResendTimer();
        } else {
          showToast(result.error || 'Failed to resend OTP', 'red');
        }
      } catch (error) {
        console.error('Error resending OTP:', error);
        showToast('Error connecting to server', 'red');
      }
    }

    async function verifyProfileOTP() {
      const otp = getOTPValue('profile');
      if (!otp) {
        document.getElementById('profile-otp-error').textContent = 'Please enter 6-digit code';
        document.getElementById('profile-otp-error').style.display = 'block';
        return;
      }

      const errorEl = document.getElementById('profile-otp-error');
      errorEl.style.display = 'none';

      try {
        // Verify OTP first
        const verifyRes = await fetch('../api/verify_otp.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ type: 'profile', otp: otp })
        });
        const verifyResult = await verifyRes.json();

        if (verifyResult.success) {
          // OTP is correct, now update profile
          const updateRes = await fetch('../api/update_profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(pendingProfileData)
          });
          const updateResult = await updateRes.json();

          if (updateResult.success) {
            closeOTPModal('profile');
            showToast('Profile updated successfully!', 'green');
            setTimeout(() => location.reload(), 1000);
          } else {
            errorEl.textContent = updateResult.error || 'Error updating profile';
            errorEl.style.display = 'block';
          }
        } else {
          errorEl.textContent = verifyResult.error || 'Invalid OTP';
          if (verifyResult.remaining_attempts !== undefined) {
            errorEl.textContent += ` (${verifyResult.remaining_attempts} attempts left)`;
          }
          errorEl.style.display = 'block';
        }
      } catch (e) {
        console.error('Error verifying OTP:', e);
        errorEl.textContent = 'Error connecting to server';
        errorEl.style.display = 'block';
      }
    }

    async function requestPasswordOTP() {
      const current = document.getElementById('pw-current').value;
      const newPass = document.getElementById('pw-new').value;
      const confirm = document.getElementById('pw-confirm').value;
      const errorEl = document.getElementById('pw-change-error');
      errorEl.style.display = 'none';
      errorEl.textContent = '';

      if (!current) { errorEl.textContent = 'Current password is required'; errorEl.style.display = 'block'; return; }
      if (!newPass) { errorEl.textContent = 'New password is required'; errorEl.style.display = 'block'; return; }
      if (!confirm) { errorEl.textContent = 'Please confirm your new password'; errorEl.style.display = 'block'; return; }
      if (newPass !== confirm) { errorEl.textContent = 'Passwords do not match'; errorEl.style.display = 'block'; return; }
      if (newPass.length < 6) { errorEl.textContent = 'Password must be at least 6 characters'; errorEl.style.display = 'block'; return; }
      if (newPass === current) { errorEl.textContent = 'New password must be different from current password'; errorEl.style.display = 'block'; return; }

      // Store pending data
      pendingPasswordData = { new_password: newPass };

      try {
        // Send OTP request to server
        const response = await fetch('../api/send_otp.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ type: 'password' })
        });
        const result = await response.json();

        if (result.success) {
          showToast('OTP sent to your email', 'green');
          passwordCountdown = 60;
          startPasswordResendTimer();
          clearOTPInputs('password');
          document.getElementById('otp-password-modal').classList.remove('hidden');
          document.getElementById('password-otp-error').style.display = 'none';
        } else {
          showToast(result.error || 'Failed to send OTP', 'red');
        }
      } catch (error) {
        console.error('Error sending OTP:', error);
        showToast('Error connecting to server', 'red');
      }
    }

    function startPasswordResendTimer() {
      if (passwordResendTimer) clearInterval(passwordResendTimer);
      const timerSpan = document.getElementById('password-countdown');
      const resendDiv = document.getElementById('password-resend-timer');

      passwordResendTimer = setInterval(() => {
        if (passwordCountdown <= 1) {
          clearInterval(passwordResendTimer);
          resendDiv.innerHTML = '<span class="resend-link" onclick="resendPasswordOTP()">Resend Code</span>';
        } else {
          passwordCountdown--;
          timerSpan.textContent = passwordCountdown;
        }
      }, 1000);
    }

    async function resendPasswordOTP() {
      try {
        const response = await fetch('../api/send_otp.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ type: 'password' })
        });
        const result = await response.json();

        if (result.success) {
          showToast('OTP resent to your email', 'green');
          passwordCountdown = 60;
          startPasswordResendTimer();
        } else {
          showToast(result.error || 'Failed to resend OTP', 'red');
        }
      } catch (error) {
        console.error('Error resending OTP:', error);
        showToast('Error connecting to server', 'red');
      }
    }

    async function verifyPasswordOTP() {
      const otp = getOTPValue('password');
      if (!otp) {
        document.getElementById('password-otp-error').textContent = 'Please enter 6-digit code';
        document.getElementById('password-otp-error').style.display = 'block';
        return;
      }

      const errorEl = document.getElementById('password-otp-error');
      errorEl.style.display = 'none';

      try {
        // Verify OTP first
        const verifyRes = await fetch('../api/verify_otp.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ type: 'password', otp: otp })
        });
        const verifyResult = await verifyRes.json();

        if (verifyResult.success) {
          // OTP is correct, now change password
          const changeRes = await fetch('../api/change_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ new_password: pendingPasswordData.new_password })
          });
          const changeResult = await changeRes.json();

          if (changeResult.success) {
            closeOTPModal('password');
            document.getElementById('pw-current').value = '';
            document.getElementById('pw-new').value = '';
            document.getElementById('pw-confirm').value = '';
            showToast('Password changed successfully!', 'green');
            setTimeout(() => location.reload(), 1000);
          } else {
            errorEl.textContent = changeResult.error || 'Error changing password';
            errorEl.style.display = 'block';
          }
        } else {
          errorEl.textContent = verifyResult.error || 'Invalid OTP';
          if (verifyResult.remaining_attempts !== undefined) {
            errorEl.textContent += ` (${verifyResult.remaining_attempts} attempts left)`;
          }
          errorEl.style.display = 'block';
        }
      } catch (e) {
        console.error('Error verifying OTP:', e);
        errorEl.textContent = 'Error connecting to server';
        errorEl.style.display = 'block';
      }
    }
  </script>
</body>

</html>