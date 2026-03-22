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

// Get badge achievements based on scores
$badges = [];
if ($stats['best_score'] >= 90) $badges[] = ['name' => 'Security Elite', 'icon' => '🏆', 'color' => '#f59e0b'];
if ($stats['best_score'] >= 80) $badges[] = ['name' => 'Security Champion', 'icon' => '🛡️', 'color' => '#10b981'];
if ($stats['total_assessments'] >= 5) $badges[] = ['name' => 'Consistent Learner', 'icon' => '📚', 'color' => '#3b82f6'];
if ($stats['latest_rank'] === 'A') $badges[] = ['name' => 'Low Risk Hero', 'icon' => '✅', 'color' => '#10b981'];
if ($stats['latest_rank'] === 'B') $badges[] = ['name' => 'On The Right Track', 'icon' => '📈', 'color' => '#3b82f6'];
if ($stats['total_assessments'] >= 10) $badges[] = ['name' => 'Dedicated Defender', 'icon' => '🔒', 'color' => '#8b5cf6'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - CyberShield</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <link rel="stylesheet" href="../style.css">
    <style>
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }
        .profile-avatar-wrap {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 600;
        }
        .profile-display-name {
            font-size: 1.2rem;
            font-weight: 600;
        }
        .profile-display-email {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .profile-sec-title {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border);
        }
        .pref-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-2);
        }
        .pref-text {
            flex: 1;
        }
        .pref-label {
            font-size: 0.9rem;
            font-weight: 500;
        }
        .pref-sub {
            font-size: 0.7rem;
            color: var(--text-muted);
        }
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--bg-tertiary);
            transition: 0.3s;
            border-radius: 34px;
            border: 1px solid var(--border);
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 2px;
            background-color: var(--text-muted);
            transition: 0.3s;
            border-radius: 50%;
        }
        input:checked + .toggle-slider {
            background-color: var(--primary);
        }
        input:checked + .toggle-slider:before {
            transform: translateX(24px);
            background-color: white;
        }
        .profile-stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }
        .profile-stat-card {
            background: var(--bg-tertiary);
            border-radius: var(--radius-lg);
            padding: 1rem;
            text-align: center;
        }
        .profile-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        .profile-stat-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }
        .badges-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        .badge-card {
            background: var(--bg-tertiary);
            border-radius: var(--radius-lg);
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid var(--border);
        }
        .badge-icon {
            font-size: 1.2rem;
        }
        .badge-name {
            font-size: 0.8rem;
            font-weight: 500;
        }
        .danger-card {
            background: rgba(239, 68, 68, 0.05);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        .danger-sec-title {
            color: var(--danger);
            border-bottom-color: rgba(239, 68, 68, 0.2);
        }
        .danger-desc {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        .btn-danger {
            background: var(--danger);
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            border: none;
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }
        .progress-ring {
            width: 100px;
            height: 100px;
        }
        .score-circle {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto;
        }
        .score-circle svg {
            transform: rotate(-90deg);
        }
        .score-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.2rem;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div id="app">
        <!-- SIDEBAR -->
        <aside id="sidebar" class="sidebar">
            <div class="sidebar-logo">
                <div class="shield-icon">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                </div>
                <span class="sidebar-brand">CyberShield</span>
            </div>

            <nav class="sidebar-nav">
                <p class="sidebar-section-label">Main Menu</p>
                <a class="sidebar-item" href="dashboard.php">
                    <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="3" width="7" height="7" rx="1.2"/><rect x="14" y="3" width="7" height="7" rx="1.2"/><rect x="3" y="14" width="7" height="7" rx="1.2"/><rect x="14" y="14" width="7" height="7" rx="1.2"/></svg></span>
                    <span class="sidebar-label">Dashboard</span>
                </a>
                <a class="sidebar-item" href="assessment.php">
                    <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
                    <span class="sidebar-label">Take Assessment</span>
                </a>
                <a class="sidebar-item" href="results.php">
                    <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>
                    <span class="sidebar-label">My Results</span>
                </a>
                <a class="sidebar-item" href="leaderboard.php">
                    <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M8 6l4-4 4 4"/><path d="M12 2v13"/><path d="M20 21H4"/><path d="M17 12h3v9"/><path d="M4 12h3v9"/></svg></span>
                    <span class="sidebar-label">Leaderboard</span>
                </a>
                
                <p class="sidebar-section-label" style="margin-top:1.25rem;">Reports & Analytics</p>
                <a class="sidebar-item" href="reports.php">
                    <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><polyline points="2 20 22 20"/></svg></span>
                    <span class="sidebar-label">Reports</span>
                </a>
                <a class="sidebar-item" href="heatmap.php">
                    <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
                    <span class="sidebar-label">Risk Heatmap</span>
                </a>
                <a class="sidebar-item" href="compare.php">
                    <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M3 3v18h18"/><path d="M7 15l3-3 3 3 4-4"/></svg></span>
                    <span class="sidebar-label">Compare</span>
                </a>
                <a class="sidebar-item" href="forecast.php">
                    <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M21 16v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2M7 8l5-4 5 4M12 4v12"/></svg></span>
                    <span class="sidebar-label">Forecast</span>
                </a>
                <a class="sidebar-item" href="compliance.php">
                    <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><polyline points="20 6 9 17 4 12"/></svg></span>
                    <span class="sidebar-label">Compliance</span>
                </a>
                
                <p class="sidebar-section-label" style="margin-top:1.25rem;">Seller Hub</p>
                <a class="sidebar-item" href="seller-store.php">
                    <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg></span>
                    <span class="sidebar-label">My Store</span>
                </a>
                <a class="sidebar-item" href="seller-analytics.php">
                    <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><polyline points="2 20 22 20"/></svg></span>
                    <span class="sidebar-label">Analytics</span>
                </a>
                
                <p class="sidebar-section-label" style="margin-top:1.25rem;">Account</p>
                <a class="sidebar-item active" href="profile.php">
                    <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                    <span class="sidebar-label">My Profile</span>
                </a>
                <a class="sidebar-item" href="settings.php">
                    <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H5.78a1.65 1.65 0 0 0-1.51 1 1.65 1.65 0 0 0 .33 1.82l.04.04A10 10 0 0 0 12 17.66a10 10 0 0 0 6.36-2.62l.04-.04z"/></svg></span>
                    <span class="sidebar-label">Settings</span>
                </a>
                <a class="sidebar-item" href="users.php">
                    <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                    <span class="sidebar-label">Users</span>
                </a>
                <a class="sidebar-item" href="activity.php">
                    <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></span>
                    <span class="sidebar-label">Activity Log</span>
                </a>
                <a class="sidebar-item" href="security-tips.php">
                    <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span>
                    <span class="sidebar-label">Security Tips</span>
                </a>
                <a class="sidebar-item" href="terms.php">
                    <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>
                    <span class="sidebar-label">Terms & Privacy</span>
                </a>
                <a class="sidebar-item" href="email.php">
                    <span class="sidebar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 7L2 7"/></svg></span>
                    <span class="sidebar-label">Email Report</span>
                </a>
            </nav>

            <div class="sidebar-bottom">
                <div class="sidebar-user-card" onclick="window.location.href='profile.php'">
                    <div class="sidebar-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
                    <div class="sidebar-user-info">
                        <div class="sidebar-user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                        <div class="sidebar-user-role"><?php echo htmlspecialchars($user['role'] ?? 'Vendor'); ?></div>
                    </div>
                    <svg class="sidebar-chevron" width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5"/></svg>
                </div>
                <button class="sidebar-signout-btn" onclick="window.location.href='../logout.php'">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    <span>Sign Out</span>
                </button>
            </div>
        </aside>

        <button id="sidebar-toggle" class="sidebar-toggle" onclick="toggleSidebar()">
            <span id="sidebar-toggle-icon"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg></span>
        </button>

        <button id="mobile-menu-btn" class="mobile-menu-btn" onclick="toggleMobileSidebar()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            Menu
        </button>

        <!-- MAIN CONTENT -->
        <div id="main-content" class="main-content">
            <header id="topbar" class="topbar">
                <div class="topbar-left">
                    <div class="topbar-breadcrumb">
                        <span class="topbar-app-name">CyberShield</span>
                        <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 4l4 4-4 4"/></svg>
                        <span id="topbar-page-title">My Profile</span>
                    </div>
                </div>
                <div class="topbar-right">
                    <button class="topbar-ctrl-btn theme-toggle" onclick="toggleTheme()" title="Toggle theme">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    </button>
                    <div class="topbar-divider"></div>
                    <div class="topbar-user" onclick="window.location.href='profile.php'">
                        <div class="topbar-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
                        <div class="topbar-user-info">
                            <span class="topbar-user-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
                            <span class="topbar-user-role">Vendor</span>
                        </div>
                        <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6l4 4 4-4"/></svg>
                    </div>
                </div>
            </header>

            <div class="content">
                <div class="page-header">
                    <div>
                        <h2 class="page-title">My Profile</h2>
                        <p class="page-subtitle">Manage your account information and preferences</p>
                    </div>
                </div>

                <div class="profile-grid">
                    <!-- Left Column - Account Info -->
                    <div class="profile-col">
                        <div class="card">
                            <div class="profile-sec-title">Account Information</div>
                            <div class="profile-avatar-wrap">
                                <div class="profile-avatar" id="profile-avatar-big"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
                                <div>
                                    <div class="profile-display-name" id="profile-name-display"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                    <div class="profile-display-email" id="profile-email-display"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Display Name</label>
                                <input type="text" id="profile-name" placeholder="Your full name" value="<?php echo htmlspecialchars($user['full_name']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="text" id="profile-email" readonly value="<?php echo htmlspecialchars($user['email']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Store / Company Name</label>
                                <input type="text" id="profile-company" placeholder="Your store name" value="<?php echo htmlspecialchars($user['store_name'] ?? ''); ?>">
                            </div>
                            <button class="btn btn-primary btn-full" onclick="saveProfile()">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                Save Changes
                            </button>
                        </div>

                        <!-- Change Password Card -->
                        <div class="card">
                            <div class="profile-sec-title">Change Password</div>
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" id="pw-current" placeholder="••••••••">
                            </div>
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" id="pw-new" placeholder="••••••••">
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" id="pw-confirm" placeholder="••••••••">
                            </div>
                            <div id="pw-change-error" class="form-error" style="display:none;"></div>
                            <button class="btn btn-primary btn-full" onclick="submitPasswordChange()">Update Password</button>
                        </div>

                        <!-- Preferences Card -->
                        <div class="card">
                            <div class="profile-sec-title">Preferences</div>
                            <div class="pref-row">
                                <div class="pref-text">
                                    <div class="pref-label">Dark Mode</div>
                                    <div class="pref-sub">Switch between dark and light theme</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="pref-dark" checked onchange="applyTheme(this.checked)">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="pref-row">
                                <div class="pref-text">
                                    <div class="pref-label">Question Timer</div>
                                    <div class="pref-sub">30-second countdown per question</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="pref-timer" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="pref-row">
                                <div class="pref-text">
                                    <div class="pref-label">Notifications</div>
                                    <div class="pref-sub">Alerts after each assessment</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="pref-notif" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="pref-row">
                                <div class="pref-text">
                                    <div class="pref-label">Large Text Mode</div>
                                    <div class="pref-sub">Bigger text for easier reading</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="pref-a11y" onchange="toggleAccessibility()">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Statistics & Badges -->
                    <div class="profile-col">
                        <!-- Statistics Card -->
                        <div class="card">
                            <div class="profile-sec-title">Your Statistics</div>
                            <div class="profile-stats-grid">
                                <div class="profile-stat-card">
                                    <div class="profile-stat-value"><?php echo $stats['total_assessments'] ?? 0; ?></div>
                                    <div class="profile-stat-label">Assessments</div>
                                </div>
                                <div class="profile-stat-card">
                                    <div class="profile-stat-value"><?php echo round($stats['avg_score'] ?? 0, 1); ?>%</div>
                                    <div class="profile-stat-label">Avg Score</div>
                                </div>
                                <div class="profile-stat-card">
                                    <div class="profile-stat-value"><?php echo $stats['best_score'] ?? 0; ?>%</div>
                                    <div class="profile-stat-label">Best Score</div>
                                </div>
                                <div class="profile-stat-card">
                                    <div class="profile-stat-value"><?php echo $stats['latest_score'] ?? 'N/A'; ?>%</div>
                                    <div class="profile-stat-label">Latest Score</div>
                                </div>
                            </div>
                            
                            <?php if ($stats['latest_rank']): ?>
                            <div style="margin-top: 1rem; text-align: center;">
                                <span class="rank-badge rank-<?php echo strtolower($stats['latest_rank']); ?>" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                    Current Risk Level: <?php echo $stats['latest_rank']; ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Badges Card -->
                        <div class="card">
                            <div class="profile-sec-title">Earned Badges</div>
                            <div class="badges-row" id="profile-badges">
                                <?php if (empty($badges)): ?>
                                    <p class="text-muted">Complete more assessments to earn badges!</p>
                                <?php else: ?>
                                    <?php foreach ($badges as $badge): ?>
                                    <div class="badge-card">
                                        <span class="badge-icon"><?php echo $badge['icon']; ?></span>
                                        <span class="badge-name"><?php echo $badge['name']; ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Danger Zone Card -->
                        <div class="card danger-card">
                            <div class="profile-sec-title danger-sec-title">⚠️ Danger Zone</div>
                            <p class="danger-desc">Permanently delete all your assessment history and cached data. This action cannot be undone.</p>
                            <button class="btn-danger" onclick="clearAllData()">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                                Clear All Data
                            </button>
                        </div>

                        <!-- Activity Summary -->
                        <div class="card">
                            <div class="profile-sec-title">Recent Activity</div>
                            <div id="recent-activity">
                                <p class="text-muted" style="font-size: 0.85rem;">No recent activity</p>
                            </div>
                            <button class="btn btn-secondary btn-sm btn-full" onclick="window.location.href='activity.php'" style="margin-top: 1rem;">
                                View Full Activity Log
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="modal-overlay" class="modal-overlay hidden" onclick="closeModal(event)">
        <div class="modal modal-sm" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3>Confirm Action</h3>
                <button class="modal-close" onclick="closeModal()">✕</button>
            </div>
            <div id="modal-body">
                <p>Are you sure you want to clear all your data?</p>
                <p style="font-size: 0.85rem; color: var(--danger);">This action cannot be undone.</p>
                <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                    <button class="btn btn-danger" onclick="confirmClearData()">Yes, Clear All</button>
                    <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sidebar functions
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const toggleIcon = document.getElementById('sidebar-toggle-icon');
            sidebar.classList.toggle('collapsed');
            if (sidebar.classList.contains('collapsed')) {
                toggleIcon.innerHTML = '<polyline points="9 18 15 12 9 6"/>';
            } else {
                toggleIcon.innerHTML = '<polyline points="15 18 9 12 15 6"/>';
            }
        }

        function toggleMobileSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('mobile-open');
        }

        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            const newTheme = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        }

        function closeModal(event) {
            if (event && event.target !== event.currentTarget) return;
            document.getElementById('modal-overlay').classList.add('hidden');
        }

        async function saveProfile() {
            const fullName = document.getElementById('profile-name').value.trim();
            const storeName = document.getElementById('profile-company').value.trim();
            
            try {
                const response = await fetch('../api/update_profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ full_name: fullName, store_name: storeName })
                });
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('profile-name-display').textContent = fullName;
                    document.getElementById('sidebar-name').textContent = fullName;
                    document.getElementById('nav-name').textContent = fullName;
                    showToast('Profile updated successfully!', 'success');
                } else {
                    showToast(result.error || 'Error updating profile', 'error');
                }
            } catch (error) {
                showToast('Error connecting to server', 'error');
            }
        }

        async function submitPasswordChange() {
            const current = document.getElementById('pw-current').value;
            const newPass = document.getElementById('pw-new').value;
            const confirm = document.getElementById('pw-confirm').value;
            const errorEl = document.getElementById('pw-change-error');
            
            if (newPass !== confirm) {
                errorEl.textContent = 'Passwords do not match';
                errorEl.style.display = 'block';
                return;
            }
            
            if (newPass.length < 6) {
                errorEl.textContent = 'Password must be at least 6 characters';
                errorEl.style.display = 'block';
                return;
            }
            
            try {
                const response = await fetch('../api/change_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ current_password: current, new_password: newPass })
                });
                const result = await response.json();
                
                if (result.success) {
                    errorEl.style.display = 'none';
                    document.getElementById('pw-current').value = '';
                    document.getElementById('pw-new').value = '';
                    document.getElementById('pw-confirm').value = '';
                    showToast('Password changed successfully!', 'success');
                } else {
                    errorEl.textContent = result.error;
                    errorEl.style.display = 'block';
                }
            } catch (error) {
                showToast('Error connecting to server', 'error');
            }
        }

        function clearAllData() {
            document.getElementById('modal-overlay').classList.remove('hidden');
        }

        function confirmClearData() {
            // Clear localStorage
            localStorage.clear();
            
            // Show success message
            closeModal();
            showToast('All local data cleared successfully!', 'success');
            
            // Reload page after 1 second
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }

        function applyTheme(isDark) {
            const html = document.documentElement;
            html.setAttribute('data-theme', isDark ? 'dark' : 'light');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        }

        function toggleAccessibility() {
            const isChecked = document.getElementById('pref-a11y').checked;
            if (isChecked) {
                document.body.style.fontSize = '1.1rem';
            } else {
                document.body.style.fontSize = '';
            }
            localStorage.setItem('largeText', isChecked);
        }

        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            toast.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: var(--card-bg);
                border-left: 3px solid ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
                padding: 0.75rem 1rem;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 1000;
                animation: slideIn 0.3s ease;
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        // Load saved preferences
        document.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) {
                document.documentElement.setAttribute('data-theme', savedTheme);
                document.getElementById('pref-dark').checked = savedTheme === 'dark';
            }
            
            const largeText = localStorage.getItem('largeText') === 'true';
            if (largeText) {
                document.getElementById('pref-a11y').checked = true;
                document.body.style.fontSize = '1.1rem';
            }
        });
    </script>
</body>
</html>