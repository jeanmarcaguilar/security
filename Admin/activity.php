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

// Get activity logs (if table exists, otherwise simulate)
// For demo, we'll create a sample activities array
$activities = [
    ['id' => 1, 'user' => $user['full_name'], 'action' => 'export', 'details' => 'Exported CSV report', 'timestamp' => date('Y-m-d H:i:s', strtotime('-5 minutes'))],
    ['id' => 2, 'user' => $user['full_name'], 'action' => 'view', 'details' => 'Viewed vendor "ABC Corp" details', 'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour'))],
    ['id' => 3, 'user' => 'System', 'action' => 'alert', 'details' => 'High risk vendor detected: Tech Solutions Inc', 'timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours'))],
    ['id' => 4, 'user' => $user['full_name'], 'action' => 'update', 'details' => 'Updated vendor "Global Services" information', 'timestamp' => date('Y-m-d H:i:s', strtotime('-1 day'))],
    ['id' => 5, 'user' => $user['full_name'], 'action' => 'assess', 'details' => 'Created new assessment for "Data Systems"', 'timestamp' => date('Y-m-d H:i:s', strtotime('-2 days'))],
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - CyberShield</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .activity-timeline {
            position: relative;
            padding-left: 2rem;
        }
        .activity-item {
            position: relative;
            padding-bottom: 1.5rem;
            border-left: 2px solid var(--border-2);
            padding-left: 1.5rem;
            margin-bottom: 1rem;
        }
        .activity-item::before {
            content: '';
            position: absolute;
            left: -8px;
            top: 0;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: var(--primary);
        }
        .activity-item > div:first-child {
            font-family: var(--font);
            font-size: 0.9rem;
        }
        .activity-icon {
            font-size: 1.2rem;
            margin-right: 0.5rem;
        }
        .activity-action {
            font-weight: 600;
            color: var(--primary);
            font-family: var(--mono);
            font-size: 0.7rem;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .activity-details {
            color: var(--text-2);
            margin-top: 0.25rem;
            font-family: var(--font);
            font-size: 0.85rem;
            line-height: 1.5;
        }
        .activity-time {
            font-size: 0.75rem;
            color: var(--text-3);
            margin-top: 0.25rem;
            font-family: var(--mono);
            letter-spacing: 0.5px;
        }
        .filter-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .filter-chip {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            background: var(--navy-3);
            cursor: pointer;
            transition: all 0.2s;
            font-family: var(--font);
            font-size: 0.85rem;
        }
        .filter-chip.active {
            background: var(--primary);
            color: white;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--card-bg);
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
        }
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            font-family: var(--display);
        }
        .stat-card > div:last-child {
            font-family: var(--mono);
            font-size: 0.7rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--muted);
            margin-top: 0.5rem;
        }
        .export-options {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        .card h3 {
            font-family: var(--display);
            font-size: 1.1rem;
            letter-spacing: 1px;
        }
        .modal-header h3 {
            font-family: var(--display);
            font-size: 1.1rem;
            letter-spacing: 1px;
        }
        .modal-body p {
            font-family: var(--font);
            font-size: 0.9rem;
            line-height: 1.6;
        }
        .filter-select {
            font-family: var(--font);
        }
        div[style*="text-align: center"] {
            font-family: var(--font);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div id="app">
        <div id="sidebar">
            <div class="sb-brand">
                <div class="shield">🛡️</div>
                <div class="sb-brand-text"><h2>CyberShield</h2><span>Activity Log</span></div>
            </div>
            <div class="sb-section">
                <div class="sb-label">Navigation</div>
                <a class="sb-item" href="dashboard.php"><span class="sb-icon">📊</span><span class="sb-text">Dashboard</span></a>
                <a class="sb-item" href="reports.php"><span class="sb-icon">📈</span><span class="sb-text">Reports</span></a>
                <a class="sb-item" href="users.php"><span class="sb-icon">👥</span><span class="sb-text">Users</span></a>
                <a class="sb-item" href="heatmap.php"><span class="sb-icon">🔥</span><span class="sb-text">Risk Heatmap</span></a>
                <a class="sb-item active" href="activity.php"><span class="sb-icon">📋</span><span class="sb-text">Activity Log</span></a>
                <a class="sb-item" href="settings.php"><span class="sb-icon">⚙️</span><span class="sb-text">Settings</span></a>
                <a class="sb-item" href="compare.php"><span class="sb-icon">⚖️</span><span class="sb-text">Compare</span></a>
                <a class="sb-item" href="forecast.php"><span class="sb-icon">🔮</span><span class="sb-text">Forecast</span></a>
                <a class="sb-item" href="compliance.php"><span class="sb-icon">✅</span><span class="sb-text">Compliance</span></a>
                <a class="sb-item" href="email.php"><span class="sb-icon">📧</span><span class="sb-text">Email Report</span></a>
                      <div class="sb-divider"></div>
      <div class="sb-label">Tools</div>
      <a class="sb-item" onclick="exportCSV()"><span class="sb-icon">⬇</span><span class="sb-text">Export CSV</span></a>
      <a class="sb-item" onclick="exportPDF()"><span class="sb-icon">📄</span><span class="sb-text">Export PDF</span></a>
      <a class="sb-item" onclick="refreshData()"><span class="sb-icon">↻</span><span class="sb-text">Refresh Data</span></a>
    </div>
            <div class="sb-footer">
                <div class="sb-user">
                    <div class="sb-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
                    <div class="sb-user-info">
                        <p><?php echo htmlspecialchars($user['full_name']); ?></p>
                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                </div>
                <button class="btn-sb-logout" onclick="doSignOut()">Sign Out</button>
            </div>
        </div>
         
        <div id="main">
            <div class="topbar">
                <div class="topbar-left">
                    <h2>Activity Log</h2>
                    <p>Track all system events and user actions</p>
                </div>
                <div class="topbar-right">
                    <div class="topbar-search-wrap">
                        <span class="topbar-search-icon">🔍</span>
                        <input type="text" class="topbar-search" id="global-search" placeholder="Search vendors, scores…" oninput="onGlobalSearch(this.value)" />
                        <div class="search-results-panel hidden" id="search-results"></div>
                    </div>
                    <span class="topbar-date" id="topbar-date"><?php echo date('D, M d, Y'); ?></span>
                    <div class="notif-wrap">
                        <button class="notif-btn" id="notif-btn" onclick="toggleNotifPanel()">🔔<span class="notif-dot hidden" id="notif-dot"></span></button>
                        <div class="notif-panel hidden" id="notif-panel">
                            <div class="notif-header"><span>Alerts</span><button onclick="clearNotifs()">Clear all</button></div>
                            <div id="notif-list"><p class="notif-empty">No alerts</p></div>
                        </div>
                    </div>
                    <button class="theme-toggle" id="theme-toggle" onclick="toggleTheme()" title="Toggle theme">🌙</button>
                    <button class="btn btn-secondary btn-sm" onclick="refreshData()">↻ Refresh</button>
                </div>
            </div>
            
            <div class="content">
                <!-- Statistics -->
                <div class="stats-grid" id="activity-stats"></div>
                
                <!-- Filters -->
                <div class="card">
                    <div class="table-toolbar">
                        <h3>Recent Activity</h3>
                        <div class="filter-row">
                            <div class="filter-buttons" id="filter-buttons">
                                <span class="filter-chip active" data-filter="all" onclick="filterActivities('all')">All</span>
                                <span class="filter-chip" data-filter="export" onclick="filterActivities('export')">📊 Exports</span>
                                <span class="filter-chip" data-filter="alert" onclick="filterActivities('alert')">⚠️ Alerts</span>
                                <span class="filter-chip" data-filter="update" onclick="filterActivities('update')">✏️ Updates</span>
                                <span class="filter-chip" data-filter="assess" onclick="filterActivities('assess')">📝 Assessments</span>
                                <span class="filter-chip" data-filter="view" onclick="filterActivities('view')">👁️ Views</span>
                            </div>
                            <input type="text" id="search-activity" placeholder="Search activities..." class="filter-select" onkeyup="filterActivities()">
                        </div>
                    </div>
                    
                    <div class="activity-timeline" id="activity-timeline"></div>
                    
                    <div class="export-options">
                        <button class="btn btn-secondary" onclick="exportActivityLog('csv')">⬇ Export CSV</button>
                        <button class="btn btn-secondary" onclick="exportActivityLog('json')">📄 Export JSON</button>
                        <button class="btn btn-danger" onclick="clearActivityLog()">🗑️ Clear Log</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div id="modal-overlay" class="modal-overlay hidden" onclick="closeModal(event)">
        <div class="modal modal-sm" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3>Clear Activity Log</h3>
                <button class="modal-close" onclick="closeModal()">✕</button>
            </div>
            <div id="modal-body">
                <p>Are you sure you want to clear all activity logs? This action cannot be undone.</p>
                <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                    <button class="btn btn-danger" onclick="confirmClearLog()">Yes, Clear All</button>
                    <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let activities = <?php echo json_encode($activities); ?>;
        let currentFilter = 'all';
        let searchTerm = '';
        
        function getActivityIcon(action) {
            const icons = {
                'export': '📊',
                'alert': '⚠️',
                'update': '✏️',
                'assess': '📝',
                'view': '👁️',
                'login': '🔐',
                'logout': '🚪'
            };
            return icons[action] || '📋';
        }
        
        function formatTime(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);
            
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
            if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
            if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
            return date.toLocaleDateString();
        }
        
        function renderStats() {
            const stats = {
                total: activities.length,
                exports: activities.filter(a => a.action === 'export').length,
                alerts: activities.filter(a => a.action === 'alert').length,
                updates: activities.filter(a => a.action === 'update').length,
                assessments: activities.filter(a => a.action === 'assess').length
            };
            
            const html = `
                <div class="stat-card">
                    <div class="stat-number">${stats.total}</div>
                    <div>Total Activities</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${stats.exports}</div>
                    <div>Exports</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${stats.alerts}</div>
                    <div>Alerts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${stats.updates + stats.assessments}</div>
                    <div>Changes</div>
                </div>
            `;
            document.getElementById('activity-stats').innerHTML = html;
        }
        
        function renderActivities() {
            let filtered = activities;
            
            if (currentFilter !== 'all') {
                filtered = filtered.filter(a => a.action === currentFilter);
            }
            
            if (searchTerm) {
                filtered = filtered.filter(a => 
                    a.details.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    a.user.toLowerCase().includes(searchTerm.toLowerCase())
                );
            }
            
            if (filtered.length === 0) {
                document.getElementById('activity-timeline').innerHTML = `
                    <div style="text-align: center; padding: 3rem; color: var(--text-3);">
                        No activities found
                    </div>
                `;
                return;
            }
            
            let html = '';
            filtered.forEach(activity => {
                html += `
                    <div class="activity-item">
                        <div>
                            <span class="activity-icon">${getActivityIcon(activity.action)}</span>
                            <span class="activity-action">${activity.action.toUpperCase()}</span>
                            <span>by ${escapeHtml(activity.user)}</span>
                        </div>
                        <div class="activity-details">${escapeHtml(activity.details)}</div>
                        <div class="activity-time">${formatTime(activity.timestamp)}</div>
                    </div>
                `;
            });
            
            document.getElementById('activity-timeline').innerHTML = html;
        }
        
        function filterActivities(filter) {
            if (filter) {
                currentFilter = filter;
                // Update active filter chip
                document.querySelectorAll('.filter-chip').forEach(chip => {
                    chip.classList.remove('active');
                    if (chip.dataset.filter === filter) {
                        chip.classList.add('active');
                    }
                });
            }
            
            searchTerm = document.getElementById('search-activity').value;
            renderActivities();
        }
        
        function exportActivityLog(format) {
            let exportData = activities;
            
            if (format === 'csv') {
                let csv = "User,Action,Details,Timestamp\n";
                exportData.forEach(a => {
                    csv += `"${a.user}","${a.action}","${a.details}","${a.timestamp}"\n`;
                });
                
                const blob = new Blob([csv], { type: 'text/csv' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `activity_log_${new Date().toISOString()}.csv`;
                a.click();
                URL.revokeObjectURL(url);
            } else if (format === 'json') {
                const json = JSON.stringify(exportData, null, 2);
                const blob = new Blob([json], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `activity_log_${new Date().toISOString()}.json`;
                a.click();
                URL.revokeObjectURL(url);
            }
            
            // Log this export activity
            addActivity('export', `Exported activity log as ${format.toUpperCase()}`);
        }
        
        function addActivity(action, details) {
            const newActivity = {
                id: activities.length + 1,
                user: '<?php echo $user['full_name']; ?>',
                action: action,
                details: details,
                timestamp: new Date().toISOString()
            };
            activities.unshift(newActivity);
            renderStats();
            renderActivities();
        }
        
        function clearActivityLog() {
            document.getElementById('modal-overlay').classList.remove('hidden');
        }
        
        function confirmClearLog() {
            activities = [];
            renderStats();
            renderActivities();
            closeModal();
            addActivity('clear', 'Cleared all activity logs');
        }
        
        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
        
        function closeModal(event) {
            if (event && event.target !== event.currentTarget) return;
            document.getElementById('modal-overlay').classList.add('hidden');
        }
        
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            html.setAttribute('data-theme', current === 'dark' ? 'light' : 'dark');
        }
        
        function doSignOut(){
            if(confirm('Are you sure you want to sign out?')){
                window.location.href = '/security/landingpage.php';
            }
        }
        
        // Initialize on load
        document.addEventListener('DOMContentLoaded', () => {
            renderStats();
            renderActivities();
        });
    </script>
</body>
</html>