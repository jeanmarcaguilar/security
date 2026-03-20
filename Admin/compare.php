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

// Get all vendors
$vendors_query = "SELECT id, name FROM vendors ORDER BY name";
$stmt = $db->prepare($vendors_query);
$stmt->execute();
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all assessments for comparison
$assessments_query = "SELECT va.*, v.name as vendor_name 
    FROM vendor_assessments va 
    JOIN vendors v ON va.vendor_id = v.id 
    ORDER BY v.name, va.created_at DESC";
$stmt = $db->prepare($assessments_query);
$stmt->execute();
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compare Vendors - CyberShield</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .comparison-container {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .vendor-selector {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--border-2);
        }
        .vs-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            font-family: var(--display);
            letter-spacing: 2px;
        }
        .comparison-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }
        .comparison-card {
            background: var(--navy-3);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        .comparison-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            font-family: var(--display);
        }
        .comparison-label {
            font-size: 0.75rem;
            color: var(--text-3);
            margin-top: 0.25rem;
            font-family: var(--mono);
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .category-comparison {
            margin: 1rem 0;
        }
        .category-bar {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }
        .category-name {
            width: 100px;
            font-weight: 600;
            font-family: var(--font);
            font-size: 0.9rem;
        }
        .bar-container {
            flex: 1;
            background: var(--navy-3);
            border-radius: 10px;
            overflow: hidden;
        }
        .bar-fill {
            height: 30px;
            background: linear-gradient(90deg, var(--primary), #3b82f6);
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 8px;
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            font-family: var(--mono);
        }
        .winner-badge {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            margin-left: 0.5rem;
            font-family: var(--mono);
            font-weight: 600;
        }
        .historical-chart {
            margin-top: 1.5rem;
        }
        .insight-box {
            background: var(--navy-3);
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            border-left: 3px solid var(--primary);
        }
        .insight-box strong {
            font-family: var(--font);
            font-size: 0.95rem;
        }
        .insight-box br + span {
            font-family: var(--font);
            font-size: 0.85rem;
            line-height: 1.5;
        }
        .card h3 {
            font-family: var(--display);
            font-size: 1.1rem;
            letter-spacing: 1px;
        }
        .filter-select {
            font-family: var(--font);
        }
        label[style*="font-weight: 600"] {
            font-family: var(--font);
        }
        div[style*="margin: 1.5rem 0 1rem 0"] {
            font-family: var(--display);
            font-size: 1.1rem;
            letter-spacing: 1px;
        }
        div[style*="max-height: 300px"] {
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
                <div class="sb-brand-text"><h2>CyberShield</h2><span>Compare Vendors</span></div>
            </div>
            <div class="sb-section">
                <div class="sb-label">Navigation</div>
                <a class="sb-item" href="dashboard.php"><span class="sb-icon">📊</span><span class="sb-text">Dashboard</span></a>
                <a class="sb-item" href="reports.php"><span class="sb-icon">📈</span><span class="sb-text">Reports</span></a>
                <a class="sb-item" href="users.php"><span class="sb-icon">👥</span><span class="sb-text">Users</span></a>
                <a class="sb-item" href="heatmap.php"><span class="sb-icon">🔥</span><span class="sb-text">Risk Heatmap</span></a>
                <a class="sb-item" href="activity.php"><span class="sb-icon">📋</span><span class="sb-text">Activity Log</span></a>
                <a class="sb-item" href="settings.php"><span class="sb-icon">⚙️</span><span class="sb-text">Settings</span></a>
                <a class="sb-item active" href="compare.php"><span class="sb-icon">⚖️</span><span class="sb-text">Compare</span></a>
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
                    <h2>Vendor Comparison</h2>
                    <p>Compare security posture across vendors</p>
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
                <div class="comparison-container">
                    <div class="vendor-selector">
                        <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Vendor A</label>
                        <select id="vendor-a" class="filter-select" style="width:100%" onchange="compareVendors()">
                            <?php foreach($vendors as $vendor): ?>
                            <option value="<?php echo $vendor['id']; ?>"><?php echo htmlspecialchars($vendor['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="vs-divider">VS</div>
                    <div class="vendor-selector">
                        <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Vendor B</label>
                        <select id="vendor-b" class="filter-select" style="width:100%" onchange="compareVendors()">
                            <?php foreach($vendors as $vendor): ?>
                            <option value="<?php echo $vendor['id']; ?>"><?php echo htmlspecialchars($vendor['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div id="comparison-results"></div>
            </div>
        </div>
    </div>
    
    <script>
        const allAssessments = <?php echo json_encode($assessments); ?>;
        let chart = null;
        
        function getLatestAssessment(vendorId) {
            const vendorAssessments = allAssessments.filter(a => a.vendor_id == vendorId);
            if (vendorAssessments.length === 0) return null;
            return vendorAssessments[0]; // Already sorted DESC in query
        }
        
        function getHistoricalScores(vendorId) {
            return allAssessments
                .filter(a => a.vendor_id == vendorId)
                .sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
        }
        
        function compareVendors() {
            const vendorAId = document.getElementById('vendor-a').value;
            const vendorBId = document.getElementById('vendor-b').value;
            
            const vendorAData = getLatestAssessment(vendorAId);
            const vendorBData = getLatestAssessment(vendorBId);
            
            if (!vendorAData || !vendorBData) {
                document.getElementById('comparison-results').innerHTML = '<div class="card">No assessment data available for one or both vendors.</div>';
                return;
            }
            
            const vendorAName = vendorAData.vendor_name;
            const vendorBName = vendorBData.vendor_name;
            
            // Determine winners
            const winners = {
                overall: vendorAData.score > vendorBData.score ? 'A' : (vendorBData.score > vendorAData.score ? 'B' : 'tie'),
                password: vendorAData.password_score > vendorBData.password_score ? 'A' : (vendorBData.password_score > vendorAData.password_score ? 'B' : 'tie'),
                phishing: vendorAData.phishing_score > vendorBData.phishing_score ? 'A' : (vendorBData.phishing_score > vendorAData.phishing_score ? 'B' : 'tie'),
                device: vendorAData.device_score > vendorBData.device_score ? 'A' : (vendorBData.device_score > vendorAData.device_score ? 'B' : 'tie'),
                network: vendorAData.network_score > vendorBData.network_score ? 'A' : (vendorBData.network_score > vendorAData.network_score ? 'B' : 'tie')
            };
            
            const html = `
                <div class="card">
                    <div class="comparison-stats">
                        <div class="comparison-card">
                            <div class="comparison-value">${vendorAData.score}%</div>
                            <div class="comparison-label">Overall Score</div>
                            ${winners.overall === 'A' ? '<span class="winner-badge">Winner</span>' : ''}
                        </div>
                        <div class="comparison-card">
                            <div class="comparison-value">${vendorBData.score}%</div>
                            <div class="comparison-label">Overall Score</div>
                            ${winners.overall === 'B' ? '<span class="winner-badge">Winner</span>' : ''}
                        </div>
                    </div>
                    
                    <h3 style="margin: 1.5rem 0 1rem 0;">Category Comparison</h3>
                    
                    <div class="category-comparison">
                        <div class="category-bar">
                            <div class="category-name">Password Security</div>
                            <div class="bar-container">
                                <div class="bar-fill" style="width: ${vendorAData.password_score}%">${vendorAData.password_score}%</div>
                            </div>
                            <div class="bar-container">
                                <div class="bar-fill" style="width: ${vendorBData.password_score}%; background: linear-gradient(90deg, #8b5cf6, #a855f7)">${vendorBData.password_score}%</div>
                            </div>
                        </div>
                        <div class="category-bar">
                            <div class="category-name">Phishing Awareness</div>
                            <div class="bar-container">
                                <div class="bar-fill" style="width: ${vendorAData.phishing_score}%">${vendorAData.phishing_score}%</div>
                            </div>
                            <div class="bar-container">
                                <div class="bar-fill" style="width: ${vendorBData.phishing_score}%; background: linear-gradient(90deg, #8b5cf6, #a855f7)">${vendorBData.phishing_score}%</div>
                            </div>
                        </div>
                        <div class="category-bar">
                            <div class="category-name">Device Security</div>
                            <div class="bar-container">
                                <div class="bar-fill" style="width: ${vendorAData.device_score}%">${vendorAData.device_score}%</div>
                            </div>
                            <div class="bar-container">
                                <div class="bar-fill" style="width: ${vendorBData.device_score}%; background: linear-gradient(90deg, #8b5cf6, #a855f7)">${vendorBData.device_score}%</div>
                            </div>
                        </div>
                        <div class="category-bar">
                            <div class="category-name">Network Security</div>
                            <div class="bar-container">
                                <div class="bar-fill" style="width: ${vendorAData.network_score}%">${vendorAData.network_score}%</div>
                            </div>
                            <div class="bar-container">
                                <div class="bar-fill" style="width: ${vendorBData.network_score}%; background: linear-gradient(90deg, #8b5cf6, #a855f7)">${vendorBData.network_score}%</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="historical-chart">
                        <h3>Historical Score Trends</h3>
                        <canvas id="trend-chart" style="max-height: 300px;"></canvas>
                    </div>
                    
                    <div class="insight-box">
                        <strong>💡 Key Insights:</strong><br>
                        ${generateInsights(vendorAData, vendorBData)}
                    </div>
                </div>
            `;
            
            document.getElementById('comparison-results').innerHTML = html;
            
            // Render historical chart
            const historyA = getHistoricalScores(vendorAId);
            const historyB = getHistoricalScores(vendorBId);
            renderHistoricalChart(historyA, historyB, vendorAName, vendorBName);
        }
        
        function generateInsights(vendorA, vendorB) {
            let insights = [];
            
            if (vendorA.score > vendorB.score) {
                insights.push(`✓ ${vendorA.vendor_name} has a higher overall security score (${vendorA.score}% vs ${vendorB.score}%)`);
            } else if (vendorB.score > vendorA.score) {
                insights.push(`✓ ${vendorB.vendor_name} has a higher overall security score (${vendorB.score}% vs ${vendorA.score}%)`);
            }
            
            if (vendorA.password_score < 50) {
                insights.push(`⚠️ ${vendorA.vendor_name} needs immediate improvement in password security (${vendorA.password_score}%)`);
            }
            if (vendorB.password_score < 50) {
                insights.push(`⚠️ ${vendorB.vendor_name} needs immediate improvement in password security (${vendorB.password_score}%)`);
            }
            
            if (vendorA.phishing_score < 50) {
                insights.push(`📧 ${vendorA.vendor_name} should implement phishing awareness training`);
            }
            if (vendorB.phishing_score < 50) {
                insights.push(`📧 ${vendorB.vendor_name} should implement phishing awareness training`);
            }
            
            if (insights.length === 0) {
                insights.push('Both vendors have strong security postures. Continue monitoring regularly.');
            }
            
            return insights.join('<br>');
        }
        
        function renderHistoricalChart(historyA, historyB, nameA, nameB) {
            const ctx = document.getElementById('trend-chart');
            if (!ctx) return;
            
            if (chart) chart.destroy();
            
            const dates = [...new Set([...historyA.map(h => h.created_at), ...historyB.map(h => h.created_at)])].sort();
            const scoresA = dates.map(d => {
                const found = historyA.find(h => h.created_at === d);
                return found ? found.score : null;
            });
            const scoresB = dates.map(d => {
                const found = historyB.find(h => h.created_at === d);
                return found ? found.score : null;
            });
            
            chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates.map(d => new Date(d).toLocaleDateString()),
                    datasets: [
                        {
                            label: nameA,
                            data: scoresA,
                            borderColor: '#3b82f6',
                            tension: 0.4,
                            fill: false
                        },
                        {
                            label: nameB,
                            data: scoresB,
                            borderColor: '#8b5cf6',
                            tension: 0.4,
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'top' }
                    }
                }
            });
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
        
        // Initialize comparison on load
        document.addEventListener('DOMContentLoaded', () => {
            compareVendors();
        });
    </script>
    <script src="dashboard.js?v=<?php echo time(); ?>"></script>
</body>
</html>