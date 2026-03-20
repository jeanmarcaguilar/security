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

// Get all vendors with their latest assessment scores
$heatmap_query = "SELECT v.id, v.name, 
    va.password_score, va.phishing_score, va.device_score, va.network_score,
    va.score as overall_score, va.rank
    FROM vendors v
    LEFT JOIN vendor_assessments va ON v.id = va.vendor_id
    WHERE va.id IN (SELECT MAX(id) FROM vendor_assessments GROUP BY vendor_id)
    ORDER BY v.name";
$stmt = $db->prepare($heatmap_query);
$stmt->execute();
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Risk Heatmap - CyberShield</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .heatmap-container {
            overflow-x: auto;
            margin-top: 1rem;
        }
        .heatmap-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            font-family: var(--font);
        }
        .heatmap-table th,
        .heatmap-table td {
            padding: 12px 8px;
            text-align: center;
            border: 1px solid var(--border-2);
        }
        .heatmap-table th {
            background: var(--card-bg);
            font-weight: 600;
            font-family: var(--mono);
            font-size: 0.7rem;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
        }
        .heatmap-table th:first-child,
        .heatmap-table td:first-child {
            position: sticky;
            left: 0;
            background: var(--card-bg);
            font-weight: 500;
            text-align: left;
            font-family: var(--font);
        }
        .heatmap-cell {
            border-radius: 4px;
            padding: 6px;
            transition: transform 0.2s;
            font-family: var(--font);
        }
        .heatmap-cell:hover {
            transform: scale(1.05);
            cursor: pointer;
        }
        .score-0-20 { background: #dc2626; color: white; }
        .score-21-40 { background: #ef4444; color: white; }
        .score-41-60 { background: #f59e0b; color: white; }
        .score-61-80 { background: #10b981; color: white; }
        .score-81-100 { background: #059669; color: white; }
        .heatmap-legend {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin: 1rem 0;
            flex-wrap: wrap;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-family: var(--font);
            font-size: 0.85rem;
        }
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }
        .vendor-summary {
            margin-top: 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }
        .vendor-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid var(--border-2);
        }
        .vendor-name {
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-family: var(--font);
        }
        .category-scores {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        .category-item {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            font-family: var(--font);
        }
        .risk-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            font-family: var(--mono);
        }
        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        .filter-group label {
            font-family: var(--font);
            font-size: 0.85rem;
        }
        .filter-group select {
            font-family: var(--font);
        }
        .view-toggle {
            display: flex;
            gap: 0.5rem;
            margin-left: auto;
        }
        .btn-toggle {
            padding: 0.5rem 1rem;
            background: var(--navy-3);
            border: 1px solid var(--border-2);
            border-radius: 8px;
            cursor: pointer;
            font-family: var(--font);
            font-size: 0.85rem;
        }
        .btn-toggle.active {
            background: var(--primary);
            border-color: var(--primary);
        }
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-box {
            background: var(--card-bg);
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            font-family: var(--display);
        }
        .stat-box > div:last-child {
            font-family: var(--mono);
            font-size: 0.7rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--muted);
            margin-top: 0.5rem;
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
        .modal-body h4 {
            font-family: var(--display);
            font-size: 1rem;
            letter-spacing: 0.5px;
        }
        .modal-body div[style*="font-size: 2rem"] {
            font-family: var(--display) !important;
        }
        .modal-body div[style*="font-size: 2rem"] + div {
            font-family: var(--mono) !important;
            font-size: 0.7rem !important;
            letter-spacing: 1px !important;
            text-transform: uppercase !important;
            color: var(--muted) !important;
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div id="app">
        <div id="sidebar">
            <div class="sb-brand">
                <div class="shield">🛡️</div>
                <div class="sb-brand-text"><h2>CyberShield</h2><span>Risk Heatmap</span></div>
            </div>
            <div class="sb-section">
                <div class="sb-label">Navigation</div>
                <a class="sb-item" href="dashboard.php"><span class="sb-icon">📊</span><span class="sb-text">Dashboard</span></a>
                <a class="sb-item" href="reports.php"><span class="sb-icon">📈</span><span class="sb-text">Reports</span></a>
                <a class="sb-item" href="users.php"><span class="sb-icon">👥</span><span class="sb-text">Users</span></a>
                <a class="sb-item active" href="heatmap.php"><span class="sb-icon">🔥</span><span class="sb-text">Risk Heatmap</span></a>
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
                    <h2>Risk Heatmap</h2>
                    <p>Visual representation of vendor security posture across categories</p>
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
                <!-- Summary Statistics -->
                <div class="summary-stats" id="summary-stats"></div>
                
                <!-- Filters -->
                <div class="card">
                    <div class="filters">
                        <div class="filter-group">
                            <label>Risk Level:</label>
                            <select id="risk-filter" onchange="filterHeatmap()">
                                <option value="all">All Vendors</option>
                                <option value="A">Low Risk (A)</option>
                                <option value="B">Moderate (B)</option>
                                <option value="C">High Risk (C)</option>
                                <option value="D">Critical (D)</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Category:</label>
                            <select id="category-filter" onchange="filterHeatmap()">
                                <option value="all">All Categories</option>
                                <option value="password">Password Security</option>
                                <option value="phishing">Phishing Awareness</option>
                                <option value="device">Device Security</option>
                                <option value="network">Network Security</option>
                            </select>
                        </div>
                        <div class="view-toggle">
                            <button class="btn-toggle active" id="view-table" onclick="setView('table')">Table View</button>
                            <button class="btn-toggle" id="view-cards" onclick="setView('cards')">Card View</button>
                        </div>
                    </div>
                </div>
                
                <!-- Heatmap Legend -->
                <div class="heatmap-legend">
                    <div class="legend-item"><div class="legend-color score-0-20"></div><span>Critical (0-20%)</span></div>
                    <div class="legend-item"><div class="legend-color score-21-40"></div><span>High Risk (21-40%)</span></div>
                    <div class="legend-item"><div class="legend-color score-41-60"></div><span>Moderate (41-60%)</span></div>
                    <div class="legend-item"><div class="legend-color score-61-80"></div><span>Good (61-80%)</span></div>
                    <div class="legend-item"><div class="legend-color score-81-100"></div><span>Excellent (81-100%)</span></div>
                </div>
                
                <!-- Heatmap Container -->
                <div class="card">
                    <div id="heatmap-container" class="heatmap-container"></div>
                </div>
                
                <!-- Vendor Details Section -->
                <div id="vendor-details" class="vendor-summary" style="display: none;"></div>
            </div>
        </div>
    </div>
    
    <div id="modal-overlay" class="modal-overlay hidden" onclick="closeModal(event)">
        <div class="modal modal-sm" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="modal-title">Category Details</h3>
                <button class="modal-close" onclick="closeModal()">✕</button>
            </div>
            <div id="modal-body"></div>
        </div>
    </div>
    
    <script>
        const vendors = <?php echo json_encode($vendors); ?>;
        let currentView = 'table';
        
        function getScoreClass(score) {
            if (score === null) return '';
            if (score <= 20) return 'score-0-20';
            if (score <= 40) return 'score-21-40';
            if (score <= 60) return 'score-41-60';
            if (score <= 80) return 'score-61-80';
            return 'score-81-100';
        }
        
        function calculateStats() {
            const stats = {
                total: vendors.length,
                avgPassword: 0,
                avgPhishing: 0,
                avgDevice: 0,
                avgNetwork: 0,
                critical: 0,
                high: 0,
                moderate: 0,
                low: 0
            };
            
            let passwordSum = 0, phishingSum = 0, deviceSum = 0, networkSum = 0;
            let count = 0;
            
            vendors.forEach(v => {
                if (v.password_score) { passwordSum += v.password_score; count++; }
                if (v.phishing_score) phishingSum += v.phishing_score;
                if (v.device_score) deviceSum += v.device_score;
                if (v.network_score) networkSum += v.network_score;
                
                if (v.rank === 'D') stats.critical++;
                else if (v.rank === 'C') stats.high++;
                else if (v.rank === 'B') stats.moderate++;
                else if (v.rank === 'A') stats.low++;
            });
            
            stats.avgPassword = count ? (passwordSum / count).toFixed(1) : 0;
            stats.avgPhishing = count ? (phishingSum / count).toFixed(1) : 0;
            stats.avgDevice = count ? (deviceSum / count).toFixed(1) : 0;
            stats.avgNetwork = count ? (networkSum / count).toFixed(1) : 0;
            
            return stats;
        }
        
        function renderSummaryStats() {
            const stats = calculateStats();
            const html = `
                <div class="stat-box">
                    <div class="stat-value">${stats.total}</div>
                    <div>Total Vendors</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">${stats.avgPassword}%</div>
                    <div>Avg Password Security</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">${stats.avgPhishing}%</div>
                    <div>Avg Phishing Awareness</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">${stats.avgDevice}%</div>
                    <div>Avg Device Security</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">${stats.avgNetwork}%</div>
                    <div>Avg Network Security</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">${stats.critical + stats.high}</div>
                    <div>High/Critical Risk</div>
                </div>
            `;
            document.getElementById('summary-stats').innerHTML = html;
        }
        
        function renderHeatmapTable() {
            const riskFilter = document.getElementById('risk-filter').value;
            const categoryFilter = document.getElementById('category-filter').value;
            
            let filtered = vendors;
            if (riskFilter !== 'all') {
                filtered = filtered.filter(v => v.rank === riskFilter);
            }
            
            let html = `<table class="heatmap-table">
                <thead>
                    <tr>
                        <th>Vendor</th>
                        <th>Overall Score</th>
                        <th>Password Security</th>
                        <th>Phishing Awareness</th>
                        <th>Device Security</th>
                        <th>Network Security</th>
                        <th>Risk Level</th>
                    </tr>
                </thead>
                <tbody>`;
            
            filtered.forEach(vendor => {
                const overallClass = getScoreClass(vendor.overall_score);
                const passwordClass = getScoreClass(vendor.password_score);
                const phishingClass = getScoreClass(vendor.phishing_score);
                const deviceClass = getScoreClass(vendor.device_score);
                const networkClass = getScoreClass(vendor.network_score);
                
                let highlight = '';
                if (categoryFilter !== 'all') {
                    highlight = ' style="border: 2px solid var(--primary);"';
                }
                
                html += `<tr${highlight}>
                    <td><strong>${escapeHtml(vendor.name)}</strong></td>
                    <td><div class="heatmap-cell ${overallClass}">${vendor.overall_score || 'N/A'}%</div></td>
                    <td><div class="heatmap-cell ${passwordClass}" onclick="showCategoryDetails('${escapeHtml(vendor.name)}', 'Password Security', ${vendor.password_score || 0})">${vendor.password_score || 'N/A'}%</div></td>
                    <td><div class="heatmap-cell ${phishingClass}" onclick="showCategoryDetails('${escapeHtml(vendor.name)}', 'Phishing Awareness', ${vendor.phishing_score || 0})">${vendor.phishing_score || 'N/A'}%</div></td>
                    <td><div class="heatmap-cell ${deviceClass}" onclick="showCategoryDetails('${escapeHtml(vendor.name)}', 'Device Security', ${vendor.device_score || 0})">${vendor.device_score || 'N/A'}%</div></td>
                    <td><div class="heatmap-cell ${networkClass}" onclick="showCategoryDetails('${escapeHtml(vendor.name)}', 'Network Security', ${vendor.network_score || 0})">${vendor.network_score || 'N/A'}%</div></td>
                    <td><span class="rank-badge rank-${(vendor.rank || 'n/a').toLowerCase()}">${vendor.rank || 'N/A'}</span></td>
                </tr>`;
            });
            
            html += `</tbody></table>`;
            document.getElementById('heatmap-container').innerHTML = html;
        }
        
        function renderCardView() {
            const riskFilter = document.getElementById('risk-filter').value;
            const categoryFilter = document.getElementById('category-filter').value;
            
            let filtered = vendors;
            if (riskFilter !== 'all') {
                filtered = filtered.filter(v => v.rank === riskFilter);
            }
            
            let html = '<div class="vendor-summary">';
            filtered.forEach(vendor => {
                let highlight = '';
                if (categoryFilter !== 'all') {
                    let score = 0;
                    switch(categoryFilter) {
                        case 'password': score = vendor.password_score; break;
                        case 'phishing': score = vendor.phishing_score; break;
                        case 'device': score = vendor.device_score; break;
                        case 'network': score = vendor.network_score; break;
                    }
                    highlight = score < 60 ? ' style="border-left: 4px solid #ef4444;"' : '';
                }
                
                html += `<div class="vendor-card"${highlight}>
                    <div class="vendor-name">${escapeHtml(vendor.name)}</div>
                    <div class="category-scores">
                        <div class="category-item">
                            <span>Password:</span>
                            <span class="${getScoreClass(vendor.password_score)}" style="padding: 2px 8px; border-radius: 12px;">${vendor.password_score || 'N/A'}%</span>
                        </div>
                        <div class="category-item">
                            <span>Phishing:</span>
                            <span class="${getScoreClass(vendor.phishing_score)}" style="padding: 2px 8px; border-radius: 12px;">${vendor.phishing_score || 'N/A'}%</span>
                        </div>
                        <div class="category-item">
                            <span>Device:</span>
                            <span class="${getScoreClass(vendor.device_score)}" style="padding: 2px 8px; border-radius: 12px;">${vendor.device_score || 'N/A'}%</span>
                        </div>
                        <div class="category-item">
                            <span>Network:</span>
                            <span class="${getScoreClass(vendor.network_score)}" style="padding: 2px 8px; border-radius: 12px;">${vendor.network_score || 'N/A'}%</span>
                        </div>
                    </div>
                    <div style="margin-top: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                        <span class="rank-badge rank-${(vendor.rank || 'n/a').toLowerCase()}">${vendor.rank || 'N/A'}</span>
                        <span>Overall: ${vendor.overall_score || 'N/A'}%</span>
                    </div>
                </div>`;
            });
            html += '</div>';
            document.getElementById('heatmap-container').innerHTML = html;
        }
        
        function filterHeatmap() {
            if (currentView === 'table') {
                renderHeatmapTable();
            } else {
                renderCardView();
            }
        }
        
        function setView(view) {
            currentView = view;
            document.getElementById('view-table').classList.toggle('active', view === 'table');
            document.getElementById('view-cards').classList.toggle('active', view === 'cards');
            
            if (view === 'table') {
                renderHeatmapTable();
            } else {
                renderCardView();
            }
        }
        
        function showCategoryDetails(vendorName, category, score) {
            let recommendation = '';
            if (score < 40) {
                recommendation = '⚠️ CRITICAL: Immediate action required. Implement comprehensive security measures.';
            } else if (score < 60) {
                recommendation = '⚠️ HIGH RISK: Significant improvements needed. Review and update security policies.';
            } else if (score < 80) {
                recommendation = 'ℹ️ MODERATE: Good but room for improvement. Consider additional training/safeguards.';
            } else {
                recommendation = '✅ GOOD: Security posture is strong. Maintain regular reviews and updates.';
            }
            
            const modalBody = `
                <div style="padding: 1rem;">
                    <h4 style="margin-bottom: 1rem;">${vendorName} - ${category}</h4>
                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--primary);">${score}%</div>
                        <div>Score</div>
                    </div>
                    <div style="background: var(--navy-3); padding: 1rem; border-radius: 8px;">
                        <strong>Recommendation:</strong><br>
                        ${recommendation}
                    </div>
                    <div style="margin-top: 1rem;">
                        <button class="btn btn-primary" onclick="closeModal()">Close</button>
                    </div>
                </div>
            `;
            
            document.getElementById('modal-body').innerHTML = modalBody;
            document.getElementById('modal-overlay').classList.remove('hidden');
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
            renderSummaryStats();
            renderHeatmapTable();
        });
    </script>
    <script src="dashboard.js?v=<?php echo time(); ?>"></script>
</body>
</html>