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
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - CyberShield</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
        }
        .setting-group {
            margin-bottom: 1.5rem;
        }
        .setting-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: block;
            font-family: var(--font);
        }
        .setting-description {
            font-size: 0.75rem;
            color: var(--text-3);
            margin-top: 0.25rem;
            font-family: var(--font);
            line-height: 1.4;
        }
        .password-field {
            position: relative;
        }
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
        }
        .danger-zone {
            border: 1px solid #ef4444;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        .danger-zone h3 {
            color: #ef4444;
            margin-bottom: 1rem;
            font-family: var(--display);
            font-size: 1.1rem;
            letter-spacing: 1px;
        }
        .api-key {
            font-family: var(--mono);
            background: var(--navy-3);
            padding: 0.5rem;
            border-radius: 8px;
            word-break: break-all;
            font-size: 0.85rem;
        }
        .notification-settings {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .notification-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
        }
        .notification-item > div:first-child > div:first-child {
            font-family: var(--font);
            font-weight: 600;
            font-size: 0.9rem;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: var(--primary);
        }
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        .tab-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-2);
            padding-bottom: 0.5rem;
        }
        .tab-btn {
            padding: 0.5rem 1rem;
            background: none;
            border: none;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.2s;
            font-family: var(--font);
            font-size: 0.85rem;
        }
        .tab-btn.active {
            background: var(--primary);
            color: white;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
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
        .modal-body ul {
            font-family: var(--font);
            font-size: 0.9rem;
            line-height: 1.6;
        }
        .modal-body label {
            font-family: var(--font);
            font-size: 0.85rem;
        }
        .filter-select {
            font-family: var(--font);
        }
        div[style*="font-weight: 600"] {
            font-family: var(--font);
        }
        code {
            font-family: var(--mono);
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div id="app">
        <div id="sidebar">
            <div class="sb-brand">
                <div class="shield">🛡️</div>
                <div class="sb-brand-text"><h2>CyberShield</h2><span>Settings</span></div>
            </div>
            <div class="sb-section">
                <div class="sb-label">Navigation</div>
                <a class="sb-item" href="dashboard.php"><span class="sb-icon">📊</span><span class="sb-text">Dashboard</span></a>
                <a class="sb-item" href="reports.php"><span class="sb-icon">📈</span><span class="sb-text">Reports</span></a>
                <a class="sb-item" href="users.php"><span class="sb-icon">👥</span><span class="sb-text">Users</span></a>
                <a class="sb-item" href="heatmap.php"><span class="sb-icon">🔥</span><span class="sb-text">Risk Heatmap</span></a>
                <a class="sb-item" href="activity.php"><span class="sb-icon">📋</span><span class="sb-text">Activity Log</span></a>
                <a class="sb-item active" href="settings.php"><span class="sb-icon">⚙️</span><span class="sb-text">Settings</span></a>
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
                    <h2>Settings</h2>
                    <p>Configure your account and system preferences</p>
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
                <div class="tab-buttons">
                    <button class="tab-btn active" onclick="switchTab('profile')">Profile</button>
                    <button class="tab-btn" onclick="switchTab('security')">Security</button>
                    <button class="tab-btn" onclick="switchTab('notifications')">Notifications</button>
                    <button class="tab-btn" onclick="switchTab('api')">API Keys</button>
                    <button class="tab-btn" onclick="switchTab('danger')">Danger Zone</button>
                </div>
                
                <!-- Profile Tab -->
                <div id="tab-profile" class="tab-content active">
                    <div class="card">
                        <h3>Profile Information</h3>
                        <form id="profile-form" onsubmit="updateProfile(event)">
                            <div class="setting-group">
                                <label class="setting-label">Full Name</label>
                                <input type="text" id="full-name" value="<?php echo htmlspecialchars($user['full_name']); ?>" class="filter-select" style="width:100%">
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Email Address</label>
                                <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="filter-select" style="width:100%" readonly>
                                <div class="setting-description">Email cannot be changed. Contact support for assistance.</div>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Organization</label>
                                <input type="text" id="organization" value="<?php echo htmlspecialchars($user['store_name']); ?>" class="filter-select" style="width:100%">
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Language</label>
                                <select id="language" class="filter-select" style="width:100%">
                                    <option value="en">English</option>
                                    <option value="es">Spanish</option>
                                    <option value="fr">French</option>
                                    <option value="de">German</option>
                                </select>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Time Zone</label>
                                <select id="timezone" class="filter-select" style="width:100%">
                                    <option value="UTC">UTC</option>
                                    <option value="America/New_York">Eastern Time</option>
                                    <option value="America/Chicago">Central Time</option>
                                    <option value="America/Denver">Mountain Time</option>
                                    <option value="America/Los_Angeles">Pacific Time</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </form>
                    </div>
                </div>
                
                <!-- Security Tab -->
                <div id="tab-security" class="tab-content">
                    <div class="card">
                        <h3>Change Password</h3>
                        <form id="password-form" onsubmit="changePassword(event)">
                            <div class="setting-group">
                                <label class="setting-label">Current Password</label>
                                <div class="password-field">
                                    <input type="password" id="current-password" class="filter-select" style="width:100%">
                                    <span class="toggle-password" onclick="togglePassword('current-password')">👁️</span>
                                </div>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">New Password</label>
                                <div class="password-field">
                                    <input type="password" id="new-password" class="filter-select" style="width:100%">
                                    <span class="toggle-password" onclick="togglePassword('new-password')">👁️</span>
                                </div>
                                <div class="setting-description">Password must be at least 8 characters with uppercase, lowercase, and numbers.</div>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Confirm New Password</label>
                                <div class="password-field">
                                    <input type="password" id="confirm-password" class="filter-select" style="width:100%">
                                    <span class="toggle-password" onclick="togglePassword('confirm-password')">👁️</span>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Password</button>
                        </form>
                    </div>
                    
                    <div class="card" style="margin-top: 1.5rem;">
                        <h3>Two-Factor Authentication</h3>
                        <div class="setting-group">
                            <div class="notification-item">
                                <div>
                                    <div style="font-weight: 600;">Enable 2FA</div>
                                    <div class="setting-description">Add an extra layer of security to your account</div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="two-factor-toggle" onchange="toggle2FA(this.checked)">
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="margin-top: 1.5rem;">
                        <h3>Sessions</h3>
                        <div class="setting-group">
                            <div class="notification-item">
                                <div>
                                    <div style="font-weight: 600;">Active Sessions</div>
                                    <div class="setting-description">Manage devices where you're logged in</div>
                                </div>
                                <button class="btn btn-secondary" onclick="viewSessions()">View Sessions</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Notifications Tab -->
                <div id="tab-notifications" class="tab-content">
                    <div class="card">
                        <h3>Notification Preferences</h3>
                        <div class="notification-settings">
                            <div class="notification-item">
                                <div>
                                    <div style="font-weight: 600;">Email Alerts</div>
                                    <div class="setting-description">Receive important security alerts via email</div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="email-alerts" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="notification-item">
                                <div>
                                    <div style="font-weight: 600;">High Risk Vendor Alerts</div>
                                    <div class="setting-description">Get notified when vendors are flagged as high risk</div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="high-risk-alerts" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="notification-item">
                                <div>
                                    <div style="font-weight: 600;">Daily Summary</div>
                                    <div class="setting-description">Receive a daily summary of activities</div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="daily-summary">
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="notification-item">
                                <div>
                                    <div style="font-weight: 600;">Weekly Report</div>
                                    <div class="setting-description">Get weekly comprehensive risk reports</div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="weekly-report" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="notification-item">
                                <div>
                                    <div style="font-weight: 600;">Browser Notifications</div>
                                    <div class="setting-description">Show desktop notifications for important events</div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="browser-notifications" onchange="requestNotificationPermission()">
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                        <button class="btn btn-primary" style="margin-top: 1rem;" onclick="saveNotificationSettings()">Save Preferences</button>
                    </div>
                </div>
                
                <!-- API Keys Tab -->
                <div id="tab-api" class="tab-content">
                    <div class="card">
                        <h3>API Keys</h3>
                        <div class="setting-group">
                            <label class="setting-label">Your API Keys</label>
                            <div id="api-keys-list"></div>
                            <button class="btn btn-primary" style="margin-top: 1rem;" onclick="generateAPIKey()">Generate New API Key</button>
                        </div>
                    </div>
                </div>
                
                <!-- Danger Zone Tab -->
                <div id="tab-danger" class="tab-content">
                    <div class="danger-zone">
                        <h3>⚠️ Danger Zone</h3>
                        <div class="setting-group">
                            <div class="notification-item">
                                <div>
                                    <div style="font-weight: 600;">Delete Account</div>
                                    <div class="setting-description">Permanently delete your account and all associated data</div>
                                </div>
                                <button class="btn btn-danger" onclick="deleteAccount()">Delete Account</button>
                            </div>
                        </div>
                        <div class="setting-group" style="margin-top: 1rem;">
                            <div class="notification-item">
                                <div>
                                    <div style="font-weight: 600;">Export All Data</div>
                                    <div class="setting-description">Download all your data in JSON format</div>
                                </div>
                                <button class="btn btn-secondary" onclick="exportAllData()">Export Data</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div id="modal-overlay" class="modal-overlay hidden" onclick="closeModal(event)">
        <div class="modal modal-sm" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="modal-title">Confirm Action</h3>
                <button class="modal-close" onclick="closeModal()">✕</button>
            </div>
            <div id="modal-body"></div>
        </div>
    </div>
    
    <script>
        function switchTab(tab) {
            // Update tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Update content
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById(`tab-${tab}`).classList.add('active');
        }
        
        function updateProfile(event) {
            event.preventDefault();
            const data = {
                full_name: document.getElementById('full-name').value,
                organization: document.getElementById('organization').value,
                language: document.getElementById('language').value,
                timezone: document.getElementById('timezone').value
            };
            
            fetch('api/update_profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Profile updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
        
        function changePassword(event) {
            event.preventDefault();
            const current = document.getElementById('current-password').value;
            const newPass = document.getElementById('new-password').value;
            const confirm = document.getElementById('confirm-password').value;
            
            if (newPass !== confirm) {
                alert('New passwords do not match!');
                return;
            }
            
            if (newPass.length < 8) {
                alert('Password must be at least 8 characters!');
                return;
            }
            
            fetch('api/change_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ current: current, new: newPass })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Password changed successfully!');
                    document.getElementById('password-form').reset();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
        
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            field.type = field.type === 'password' ? 'text' : 'password';
        }
        
        function toggle2FA(enabled) {
            if (enabled) {
                alert('2FA setup would begin here. A verification code would be sent to your email.');
            } else {
                alert('2FA disabled. Your account is now less secure.');
            }
        }
        
        function viewSessions() {
            alert('Active sessions feature - would show all active sessions');
        }
        
        function requestNotificationPermission() {
            if ('Notification' in window) {
                Notification.requestPermission().then(permission => {
                    if (permission === 'granted') {
                        alert('Notifications enabled!');
                    }
                });
            } else {
                alert('Browser notifications not supported');
            }
        }
        
        function saveNotificationSettings() {
            const settings = {
                email_alerts: document.getElementById('email-alerts').checked,
                high_risk_alerts: document.getElementById('high-risk-alerts').checked,
                daily_summary: document.getElementById('daily-summary').checked,
                weekly_report: document.getElementById('weekly-report').checked,
                browser_notifications: document.getElementById('browser-notifications').checked
            };
            
            localStorage.setItem('notification_settings', JSON.stringify(settings));
            alert('Notification preferences saved!');
        }
        
        function generateAPIKey() {
            const key = 'cybershield_' + Math.random().toString(36).substr(2, 32);
            const apiKeysList = document.getElementById('api-keys-list');
            
            const keyElement = document.createElement('div');
            keyElement.className = 'api-key';
            keyElement.style.marginBottom = '0.5rem';
            keyElement.style.padding = '0.5rem';
            keyElement.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <code>${key}</code>
                    <button class="btn btn-xs btn-secondary" onclick="copyToClipboard('${key}')">Copy</button>
                </div>
                <div class="setting-description">Created: ${new Date().toLocaleString()}</div>
            `;
            apiKeysList.prepend(keyElement);
            alert('New API key generated! Keep it secure.');
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text);
            alert('Copied to clipboard!');
        }
        
        function deleteAccount() {
            const modalBody = `
                <p>Are you absolutely sure? This action cannot be undone.</p>
                <p style="color: #ef4444; margin-top: 1rem;">This will permanently delete:</p>
                <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                    <li>Your account</li>
                    <li>All vendor data</li>
                    <li>All assessments</li>
                    <li>Activity logs</li>
                </ul>
                <div style="margin-top: 1rem;">
                    <label>Type "DELETE" to confirm:</label>
                    <input type="text" id="delete-confirm" class="filter-select" style="width:100%; margin-top: 0.5rem;">
                </div>
                <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                    <button class="btn btn-danger" onclick="confirmDelete()">Delete Permanently</button>
                    <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            `;
            
            document.getElementById('modal-body').innerHTML = modalBody;
            document.getElementById('modal-overlay').classList.remove('hidden');
        }
        
        function confirmDelete() {
            const confirmText = document.getElementById('delete-confirm')?.value;
            if (confirmText === 'DELETE') {
                fetch('api/delete_account.php', { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Account deleted. Redirecting...');
                        window.location.href = '../logout.php';
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            } else {
                alert('Please type "DELETE" to confirm.');
            }
        }
        
        function exportAllData() {
            fetch('api/export_all_data.php')
                .then(response => response.json())
                .then(data => {
                    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `cybershield_export_${new Date().toISOString()}.json`;
                    a.click();
                    URL.revokeObjectURL(url);
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
        
        // Load saved notification settings
        document.addEventListener('DOMContentLoaded', () => {
            const saved = localStorage.getItem('notification_settings');
            if (saved) {
                const settings = JSON.parse(saved);
                if (document.getElementById('email-alerts')) document.getElementById('email-alerts').checked = settings.email_alerts;
                if (document.getElementById('high-risk-alerts')) document.getElementById('high-risk-alerts').checked = settings.high_risk_alerts;
                if (document.getElementById('daily-summary')) document.getElementById('daily-summary').checked = settings.daily_summary;
                if (document.getElementById('weekly-report')) document.getElementById('weekly-report').checked = settings.weekly_report;
                if (document.getElementById('browser-notifications')) document.getElementById('browser-notifications').checked = settings.browser_notifications;
            }
        });
    </script>
    <script src="dashboard.js?v=<?php echo time(); ?>"></script>
</body>
</html>