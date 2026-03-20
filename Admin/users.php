<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get user info
$user_query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($user_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all vendors with their latest assessment
$vendors_query = "SELECT v.*, 
    (SELECT score FROM vendor_assessments WHERE vendor_id = v.id ORDER BY created_at DESC LIMIT 1) as latest_score,
    (SELECT rank FROM vendor_assessments WHERE vendor_id = v.id ORDER BY created_at DESC LIMIT 1) as latest_rank,
    (SELECT created_at FROM vendor_assessments WHERE vendor_id = v.id ORDER BY created_at DESC LIMIT 1) as last_assessment,
    (SELECT COUNT(*) FROM vendor_assessments WHERE vendor_id = v.id) as assessment_count
    FROM vendors v
    ORDER BY v.name";
$stmt = $db->prepare($vendors_query);
$stmt->execute();
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users (for admin users)
$users_query = "SELECT id, full_name, email, store_name, created_at FROM users WHERE role = 'admin' ORDER BY created_at DESC";
$stmt = $db->prepare($users_query);
$stmt->execute();
$system_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - CyberShield</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .user-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-badge {
            background: var(--card-bg);
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            font-family: var(--display);
        }
        .stat-badge > div:last-child {
            font-family: var(--mono);
            font-size: 0.7rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--muted);
            margin-top: 0.5rem;
        }
        .user-actions {
            display: flex;
            gap: 0.5rem;
        }
        .btn-icon {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
        .modal {
            max-width: 500px;
        }
        .form-row {
            margin-bottom: 1rem;
        }
        .form-row label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-family: var(--font);
        }
        .form-row input, .form-row select, .form-row textarea {
            width: 100%;
            padding: 0.5rem;
            border-radius: 8px;
            border: 1px solid var(--border-2);
            background: var(--navy-3);
            color: var(--text);
            font-family: var(--font);
        }
        .card h3 {
            font-family: var(--display);
            font-size: 1.1rem;
            letter-spacing: 1px;
        }
        .table-toolbar h3 {
            font-family: var(--display);
            font-size: 1.1rem;
            letter-spacing: 1px;
        }
        .modal-header h3 {
            font-family: var(--display);
            font-size: 1.1rem;
            letter-spacing: 1px;
        }
        .tbl th {
            font-family: var(--mono);
            font-size: 0.7rem;
            letter-spacing: 0.5px;
        }
        .tbl td {
            font-family: var(--font);
            font-size: 0.85rem;
        }
        .filter-select {
            font-family: var(--font);
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div id="app">
        <div id="sidebar">
            <div class="sb-brand">
                <div class="shield">🛡️</div>
                <div class="sb-brand-text"><h2>CyberShield</h2><span>User Management</span></div>
            </div>
            <div class="sb-section">
                <div class="sb-label">Navigation</div>
                <a class="sb-item" href="dashboard.php"><span class="sb-icon">📊</span><span class="sb-text">Dashboard</span></a>
                <a class="sb-item" href="reports.php"><span class="sb-icon">📈</span><span class="sb-text">Reports</span></a>
                <a class="sb-item active" href="users.php"><span class="sb-icon">👥</span><span class="sb-text">Users</span></a>
                <a class="sb-item" href="heatmap.php"><span class="sb-icon">🔥</span><span class="sb-text">Risk Heatmap</span></a>
                <a class="sb-item" href="activity.php"><span class="sb-icon">📋</span><span class="sb-text">Activity Log</span></a>
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
                    <h2>User Management</h2>
                    <p>Manage vendors and system users</p>
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
                <!-- Action Buttons -->
                <div style="margin-bottom: 2rem;">
                    <button class="btn btn-primary" onclick="showAddVendorModal()">+ Add New Vendor</button>
                    <button class="btn btn-secondary" onclick="showAddUserModal()">+ Add Admin User</button>
                </div>
                
                <!-- Statistics -->
                <div class="user-stats">
                    <div class="stat-badge">
                        <div class="stat-number"><?php echo count($vendors); ?></div>
                        <div>Total Vendors</div>
                    </div>
                    <div class="stat-badge">
                        <div class="stat-number"><?php 
                            $high_risk = 0;
                            foreach($vendors as $v) {
                                if(in_array($v['latest_rank'], ['C', 'D'])) $high_risk++;
                            }
                            echo $high_risk;
                        ?></div>
                        <div>High Risk Vendors</div>
                    </div>
                    <div class="stat-badge">
                        <div class="stat-number"><?php echo count($system_users); ?></div>
                        <div>System Users</div>
                    </div>
                </div>
                
                <!-- Vendors Table -->
                <div class="card">
                    <div class="table-toolbar">
                        <h3>Vendors</h3>
                        <div class="filter-row">
                            <input type="text" id="vendor-search" placeholder="Search vendors..." class="filter-select" onkeyup="filterVendors()">
                            <select id="risk-filter" class="filter-select" onchange="filterVendors()">
                                <option value="">All Risks</option>
                                <option value="A">Low Risk (A)</option>
                                <option value="B">Moderate (B)</option>
                                <option value="C">High Risk (C)</option>
                                <option value="D">Critical (D)</option>
                            </select>
                        </div>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>Vendor Name</th>
                                    <th>Contact Email</th>
                                    <th>Latest Score</th>
                                    <th>Risk Level</th>
                                    <th>Assessments</th>
                                    <th>Last Assessment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="vendors-table">
                                <?php foreach($vendors as $vendor): ?>
                                <tr class="vendor-row" data-rank="<?php echo $vendor['latest_rank']; ?>" data-name="<?php echo strtolower($vendor['name']); ?>">
                                    <td><?php echo htmlspecialchars($vendor['name']); ?></td>
                                    <td><?php echo htmlspecialchars($vendor['email']); ?></td>
                                    <td><?php echo $vendor['latest_score'] ? $vendor['latest_score'] . '%' : 'N/A'; ?></td>
                                    <td>
                                        <?php if($vendor['latest_rank']): ?>
                                        <span class="rank-badge rank-<?php echo strtolower($vendor['latest_rank']); ?>">
                                            <?php echo $vendor['latest_rank']; ?>
                                        </span>
                                        <?php else: echo 'N/A'; endif; ?>
                                    </td>
                                    <td><?php echo $vendor['assessment_count']; ?></td>
                                    <td><?php echo $vendor['last_assessment'] ? date('M j, Y', strtotime($vendor['last_assessment'])) : 'Never'; ?></td>
                                    <td class="user-actions">
                                        <button class="btn btn-xs btn-primary" onclick="viewVendor(<?php echo $vendor['id']; ?>)">View</button>
                                        <button class="btn btn-xs btn-secondary" onclick="editVendor(<?php echo $vendor['id']; ?>)">Edit</button>
                                        <button class="btn btn-xs btn-danger" onclick="deleteVendor(<?php echo $vendor['id']; ?>)">Delete</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- System Users Table -->
                <div class="card">
                    <h3>System Administrators</h3>
                    <div style="overflow-x: auto;">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Organization</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($system_users as $sys_user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($sys_user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($sys_user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($sys_user['store_name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($sys_user['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-xs btn-secondary" onclick="resetUserPassword(<?php echo $sys_user['id']; ?>)">Reset Password</button>
                                        <?php if($sys_user['id'] != $_SESSION['user_id']): ?>
                                        <button class="btn btn-xs btn-danger" onclick="deleteUser(<?php echo $sys_user['id']; ?>)">Delete</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modals -->
    <div id="modal-overlay" class="modal-overlay hidden" onclick="closeModal(event)">
        <div class="modal" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="modal-title">Add Vendor</h3>
                <button class="modal-close" onclick="closeModal()">✕</button>
            </div>
            <div id="modal-body">
                <form id="vendor-form" onsubmit="saveVendor(event)">
                    <div class="form-row">
                        <label>Vendor Name *</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-row">
                        <label>Contact Email *</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-row">
                        <label>Phone</label>
                        <input type="text" name="phone">
                    </div>
                    <div class="form-row">
                        <label>Address</label>
                        <textarea name="address" rows="3"></textarea>
                    </div>
                    <div class="form-row">
                        <label>Industry</label>
                        <input type="text" name="industry">
                    </div>
                    <div class="form-row">
                        <button type="submit" class="btn btn-primary">Save Vendor</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function filterVendors() {
            const search = document.getElementById('vendor-search').value.toLowerCase();
            const risk = document.getElementById('risk-filter').value;
            const rows = document.querySelectorAll('.vendor-row');
            
            rows.forEach(row => {
                const name = row.dataset.name;
                const rank = row.dataset.rank;
                let show = true;
                
                if(search && !name.includes(search)) show = false;
                if(risk && rank !== risk) show = false;
                
                row.style.display = show ? '' : 'none';
            });
        }
        
        function showAddVendorModal() {
            document.getElementById('modal-title').textContent = 'Add New Vendor';
            document.getElementById('vendor-form').reset();
            document.getElementById('modal-overlay').classList.remove('hidden');
        }
        
        function showAddUserModal() {
            alert('Add admin user functionality - would open form to add new admin');
        }
        
        function saveVendor(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            
            fetch('api/add_vendor.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    alert('Vendor added successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving vendor');
            });
        }
        
        function viewVendor(id) {
            window.location.href = `vendor_details.php?id=${id}`;
        }
        
        function editVendor(id) {
            alert(`Edit vendor ${id} functionality`);
        }
        
        function deleteVendor(id) {
            if(confirm('Are you sure you want to delete this vendor? This will also delete all associated assessments.')) {
                fetch(`api/delete_vendor.php?id=${id}`, { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        alert('Vendor deleted successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }
        
        function resetUserPassword(id) {
            const newPassword = prompt('Enter new password for user:');
            if(newPassword) {
                fetch('api/reset_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, password: newPassword })
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) alert('Password reset successfully');
                    else alert('Error: ' + data.message);
                });
            }
        }
        
        function deleteUser(id) {
            if(confirm('Are you sure you want to delete this user?')) {
                fetch(`api/delete_user.php?id=${id}`, { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        alert('User deleted successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }
        
        function closeModal(event) {
            if(event && event.target !== event.currentTarget) return;
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
    </script>
    <script src="dashboard.js?v=<?php echo time(); ?>"></script>
</body>
</html>