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

// Get all vendor assessments for reports
$assessments_query = "SELECT va.*, v.name as vendor_name, v.email as vendor_email 
    FROM vendor_assessments va 
    JOIN vendors v ON va.vendor_id = v.id 
    ORDER BY va.created_at DESC";
$stmt = $db->prepare($assessments_query);
$stmt->execute();
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics for reports
$stats_query = "SELECT 
    COUNT(*) as total_assessments,
    AVG(score) as avg_score,
    COUNT(CASE WHEN rank = 'A' THEN 1 END) as low_risk,
    COUNT(CASE WHEN rank = 'B' THEN 1 END) as moderate_risk,
    COUNT(CASE WHEN rank = 'C' THEN 1 END) as high_risk,
    COUNT(CASE WHEN rank = 'D' THEN 1 END) as critical_risk
    FROM vendor_assessments";
$stmt = $db->prepare($stats_query);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - CyberShield</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .report-filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .date-range {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        .date-range input {
            padding: 0.5rem;
            border-radius: 8px;
            border: 1px solid var(--border-2);
            background: var(--navy-3);
            color: var(--text);
            font-family: var(--font);
        }
        .export-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        .report-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .summary-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--border-2);
        }
        .summary-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            font-family: var(--display);
        }
        .summary-label {
            font-size: 0.85rem;
            color: var(--text-3);
            margin-top: 0.5rem;
            font-family: var(--mono);
        }
        .card h3 {
            font-family: var(--display);
            font-size: 1.1rem;
            letter-spacing: 1px;
        }
        #report-content h3 {
            font-family: var(--display);
            font-size: 1.1rem;
            letter-spacing: 1px;
            margin-bottom: 1rem;
        }
        #report-preview p {
            font-family: var(--font);
            font-size: 0.9rem;
            line-height: 1.6;
        }
        #report-preview table {
            font-family: var(--font);
        }
        #report-preview table th {
            font-family: var(--mono);
            font-size: 0.7rem;
            letter-spacing: 0.5px;
        }
        #report-preview table td {
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div id="app">
        <div id="sidebar">
            <!-- Sidebar content same as dashboard -->
            <div class="sb-brand">
                <div class="shield">🛡️</div>
                <div class="sb-brand-text"><h2>CyberShield</h2><span>Reports</span></div>
            </div>
            <div class="sb-section">
                <div class="sb-label">Navigation</div>
                <a class="sb-item" href="dashboard.php"><span class="sb-icon">📊</span><span class="sb-text">Dashboard</span></a>
                <a class="sb-item active" href="reports.php"><span class="sb-icon">📈</span><span class="sb-text">Reports</span></a>
                <a class="sb-item" href="users.php"><span class="sb-icon">👥</span><span class="sb-text">Users</span></a>
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
                    <h2>Reports & Analytics</h2>
                    <p>Generate comprehensive risk assessment reports</p>
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
                <!-- Report Filters -->
                <div class="card">
                    <h3>Report Filters</h3>
                    <div class="report-filters">
                        <select id="report-type" class="filter-select">
                            <option value="summary">Summary Report</option>
                            <option value="detailed">Detailed Report</option>
                            <option value="risk">Risk Analysis Report</option>
                            <option value="compliance">Compliance Report</option>
                        </select>
                        
                        <select id="risk-filter" class="filter-select">
                            <option value="">All Risk Levels</option>
                            <option value="A">Low Risk (A)</option>
                            <option value="B">Moderate Risk (B)</option>
                            <option value="C">High Risk (C)</option>
                            <option value="D">Critical Risk (D)</option>
                        </select>
                        
                        <div class="date-range">
                            <input type="date" id="date-from">
                            <span>to</span>
                            <input type="date" id="date-to">
                        </div>
                        
                        <button class="btn btn-primary" onclick="generateReport()">Generate Report</button>
                    </div>
                    <div class="export-buttons">
                        <button class="btn btn-secondary" onclick="exportPDF()">📄 Export as PDF</button>
                        <button class="btn btn-secondary" onclick="exportCSV()">📊 Export as CSV</button>
                        <button class="btn btn-secondary" onclick="printReport()">🖨️ Print Report</button>
                    </div>
                </div>
                
                <!-- Summary Statistics -->
                <div class="report-summary">
                    <div class="summary-card">
                        <div class="summary-value"><?php echo $stats['total_assessments']; ?></div>
                        <div class="summary-label">Total Assessments</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-value"><?php echo round($stats['avg_score'], 1); ?>%</div>
                        <div class="summary-label">Average Score</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-value"><?php echo $stats['high_risk'] + $stats['critical_risk']; ?></div>
                        <div class="summary-label">High Risk Vendors</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-value"><?php echo $stats['low_risk']; ?></div>
                        <div class="summary-label">Low Risk Vendors</div>
                    </div>
                </div>
                
                <!-- Charts -->
                <div class="charts-grid">
                    <div class="card chart-card">
                        <h3>Risk Distribution</h3>
                        <canvas id="risk-chart"></canvas>
                    </div>
                    <div class="card chart-card">
                        <h3>Score Trends</h3>
                        <canvas id="trend-chart"></canvas>
                    </div>
                </div>
                
                <!-- Report Content -->
                <div class="card" id="report-content">
                    <h3>Report Preview</h3>
                    <div id="report-preview">
                        <p>Select filters and click "Generate Report" to view data.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const assessments = <?php echo json_encode($assessments); ?>;
        const stats = <?php echo json_encode($stats); ?>;
        
        // Initialize charts
        function initCharts() {
            const ctx1 = document.getElementById('risk-chart').getContext('2d');
            new Chart(ctx1, {
                type: 'doughnut',
                data: {
                    labels: ['Low Risk (A)', 'Moderate (B)', 'High Risk (C)', 'Critical (D)'],
                    datasets: [{
                        data: [stats.low_risk, stats.moderate_risk, stats.high_risk, stats.critical_risk],
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#8b5cf6']
                    }]
                },
                options: { responsive: true }
            });
            
            const ctx2 = document.getElementById('trend-chart').getContext('2d');
            const monthlyScores = getMonthlyScores();
            new Chart(ctx2, {
                type: 'line',
                data: {
                    labels: monthlyScores.labels,
                    datasets: [{
                        label: 'Average Score',
                        data: monthlyScores.scores,
                        borderColor: '#3b82f6',
                        tension: 0.4
                    }]
                },
                options: { responsive: true }
            });
        }
        
        function getMonthlyScores() {
            const monthly = {};
            assessments.forEach(a => {
                const date = new Date(a.created_at);
                const key = `${date.getFullYear()}-${date.getMonth() + 1}`;
                if (!monthly[key]) monthly[key] = { total: 0, count: 0 };
                monthly[key].total += a.score;
                monthly[key].count++;
            });
            
            return {
                labels: Object.keys(monthly).sort(),
                scores: Object.keys(monthly).sort().map(k => (monthly[k].total / monthly[k].count).toFixed(1))
            };
        }
        
        function generateReport() {
            const type = document.getElementById('report-type').value;
            const risk = document.getElementById('risk-filter').value;
            const from = document.getElementById('date-from').value;
            const to = document.getElementById('date-to').value;
            
            let filtered = assessments;
            if (risk) filtered = filtered.filter(a => a.rank === risk);
            if (from) filtered = filtered.filter(a => a.created_at >= from);
            if (to) filtered = filtered.filter(a => a.created_at <= to);
            
            let html = `<h3>${type.toUpperCase()} Report</h3>`;
            html += `<p>Generated on: ${new Date().toLocaleString()}</p>`;
            html += `<p>Total Records: ${filtered.length}</p>`;
            html += `<table class="tbl"><thead><tr><th>Vendor</th><th>Score</th><th>Rank</th><th>Date</th></tr></thead><tbody>`;
            filtered.forEach(a => {
                html += `<tr>
                    <td>${a.vendor_name}</td>
                    <td>${a.score}%</td>
                    <td><span class="rank-badge rank-${a.rank.toLowerCase()}">${a.rank}</span></td>
                    <td>${new Date(a.created_at).toLocaleDateString()}</td>
                </tr>`;
            });
            html += `</tbody></table>`;
            
            document.getElementById('report-preview').innerHTML = html;
        }
        
        function exportPDF() {
            // PDF export logic
            alert('PDF export functionality - would generate PDF report');
        }
        
        function exportCSV() {
            let csv = "Vendor,Score,Rank,Password,Phishing,Device,Network,Date\n";
            assessments.forEach(a => {
                csv += `"${a.vendor_name}",${a.score},${a.rank},${a.password_score},${a.phishing_score},${a.device_score},${a.network_score},${a.created_at}\n`;
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `cybershield_report_${new Date().toISOString()}.csv`;
            a.click();
            URL.revokeObjectURL(url);
        }
        
        function printReport() {
            window.print();
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
            initCharts();
            generateReport();
        });
    </script>
    <script src="dashboard.js?v=<?php echo time(); ?>"></script>
</body>
</html>