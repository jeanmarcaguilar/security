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

// Get all vendors with their assessment history
$vendors_query = "SELECT v.id, v.name, 
    (SELECT score FROM vendor_assessments WHERE vendor_id = v.id ORDER BY created_at DESC LIMIT 1) as current_score,
    (SELECT rank FROM vendor_assessments WHERE vendor_id = v.id ORDER BY created_at DESC LIMIT 1) as current_rank,
    (SELECT created_at FROM vendor_assessments WHERE vendor_id = v.id ORDER BY created_at DESC LIMIT 1) as last_assessment
    FROM vendors v
    ORDER BY v.name";
$stmt = $db->prepare($vendors_query);
$stmt->execute();
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get historical data for forecasting
$history_query = "SELECT v.id as vendor_id, v.name, va.score, va.created_at
    FROM vendor_assessments va
    JOIN vendors v ON va.vendor_id = v.id
    ORDER BY v.id, va.created_at";
$stmt = $db->prepare($history_query);
$stmt->execute();
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Risk Forecast - CyberShield</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .forecast-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .forecast-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.25rem;
            border: 1px solid var(--border-2);
            transition: transform 0.2s;
        }
        .forecast-card:hover {
            transform: translateY(-2px);
        }
        .forecast-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .vendor-name {
            font-weight: 700;
            font-size: 1.1rem;
            font-family: var(--font);
        }
        .trend-indicator {
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            font-family: var(--mono);
            letter-spacing: 0.5px;
        }
        .trend-up {
            background: #10b981;
            color: white;
        }
        .trend-down {
            background: #ef4444;
            color: white;
        }
        .trend-stable {
            background: #6b7280;
            color: white;
        }
        .forecast-score {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin: 1rem 0;
            font-family: var(--display);
        }
        .confidence-bar {
            background: var(--navy-3);
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin: 0.5rem 0;
        }
        .confidence-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), #3b82f6);
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        .risk-level {
            margin-top: 1rem;
            padding: 0.5rem;
            border-radius: 8px;
            text-align: center;
            font-family: var(--font);
            font-size: 0.85rem;
            font-weight: 600;
        }
        .risk-critical {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        .risk-high {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }
        .risk-moderate {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }
        .risk-low {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        .recommendation {
            margin-top: 1rem;
            padding: 0.75rem;
            background: var(--navy-3);
            border-radius: 8px;
            font-size: 0.85rem;
            font-family: var(--font);
            line-height: 1.5;
        }
        .recommendation strong {
            font-family: var(--font);
        }
        .filter-bar {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .filter-btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            background: var(--navy-3);
            cursor: pointer;
            transition: all 0.2s;
            font-family: var(--font);
            font-size: 0.85rem;
        }
        .filter-btn.active {
            background: var(--primary);
            color: white;
        }
        .card h3 {
            font-family: var(--display);
            font-size: 1.1rem;
            letter-spacing: 1px;
        }
        div[style*="font-size: 0.85rem"] {
            font-family: var(--font);
            line-height: 1.5;
        }
        div[style*="text-align: center"] {
            font-family: var(--font);
        }
        div[style*="display: flex"] strong {
            font-family: var(--font);
        }
        div[style*="font-size: 0.75rem"] {
            font-family: var(--font);
        }
        div[style*="font-size: 0.7rem"] {
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
                <div class="sb-brand-text"><h2>CyberShield</h2><span>Risk Forecast</span></div>
            </div>
            <div class="sb-section">
                <div class="sb-label">Navigation</div>
                <a class="sb-item" href="dashboard.php"><span class="sb-icon">📊</span><span class="sb-text">Dashboard</span></a>
                <a class="sb-item" href="reports.php"><span class="sb-icon">📈</span><span class="sb-text">Reports</span></a>
                <a class="sb-item" href="users.php"><span class="sb-icon">👥</span><span class="sb-text">Users</span></a>
                <a class="sb-item" href="heatmap.php"><span class="sb-icon">🔥</span><span class="sb-text">Risk Heatmap</span></a>
                <a class="sb-item" href="activity.php"><span class="sb-icon">📋</span><span class="sb-text">Activity Log</span></a>
                <a class="sb-item" href="settings.php"><span class="sb-icon">⚙️</span><span class="sb-text">Settings</span></a>
                <a class="sb-item" href="compare.php"><span class="sb-icon">⚖️</span><span class="sb-text">Compare</span></a>
                <a class="sb-item active" href="forecast.php"><span class="sb-icon">🔮</span><span class="sb-text">Forecast</span></a>
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
                    <h2>Risk Score Forecast</h2>
                    <p>AI-powered predictions for vendor security scores</p>
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
                <div class="card">
                    <div class="filter-bar" id="filter-bar">
                        <span class="filter-btn active" data-filter="all" onclick="filterForecasts('all')">All Vendors</span>
                        <span class="filter-btn" data-filter="improving" onclick="filterForecasts('improving')">📈 Improving</span>
                        <span class="filter-btn" data-filter="declining" onclick="filterForecasts('declining')">📉 Declining</span>
                        <span class="filter-btn" data-filter="stable" onclick="filterForecasts('stable')">➡️ Stable</span>
                        <span class="filter-btn" data-filter="at-risk" onclick="filterForecasts('at-risk')">⚠️ At Risk</span>
                    </div>
                    <p style="font-size: 0.85rem; color: var(--text-3);">Forecasts based on historical trends and machine learning models. Confidence levels indicate prediction reliability.</p>
                </div>
                
                <div id="forecast-grid" class="forecast-grid"></div>
            </div>
        </div>
    </div>
    
    <script>
        const vendors = <?php echo json_encode($vendors); ?>;
        const historyData = <?php echo json_encode($history); ?>;
        let currentFilter = 'all';
        
        // Calculate forecast for a vendor
        function calculateForecast(vendorId, historicalScores) {
            if (historicalScores.length < 2) {
                return {
                    forecastScore: historicalScores[0]?.score || 50,
                    trend: 'stable',
                    confidence: 30,
                    recommendation: 'Insufficient data for accurate forecast. Schedule another assessment.'
                };
            }
            
            // Simple linear regression for trend
            const scores = historicalScores.map(h => h.score);
            const indices = scores.map((_, i) => i);
            const n = scores.length;
            
            const sumX = indices.reduce((a, b) => a + b, 0);
            const sumY = scores.reduce((a, b) => a + b, 0);
            const sumXY = indices.reduce((a, b, i) => a + b * scores[i], 0);
            const sumX2 = indices.reduce((a, b) => a + b * b, 0);
            
            const slope = (n * sumXY - sumX * sumY) / (n * sumX2 - sumX * sumX);
            const intercept = (sumY - slope * sumX) / n;
            
            // Predict next score (index = n)
            const forecast = Math.min(100, Math.max(0, intercept + slope * n));
            
            // Determine trend
            let trend = 'stable';
            if (slope > 2) trend = 'improving';
            else if (slope < -2) trend = 'declining';
            
            // Calculate confidence based on data points and consistency
            const variance = scores.reduce((sum, score, i) => {
                const predicted = intercept + slope * i;
                return sum + Math.pow(score - predicted, 2);
            }, 0) / n;
            const confidence = Math.min(95, Math.max(20, 100 - (variance / 10) * (10 / n)));
            
            // Generate recommendation
            let recommendation = '';
            if (forecast < 40) {
                recommendation = '⚠️ URGENT: Immediate security intervention required. Schedule comprehensive security audit.';
            } else if (forecast < 60) {
                recommendation = '⚠️ HIGH RISK: Implement security improvements within next quarter. Focus on weak areas.';
            } else if (forecast < 80) {
                recommendation = 'ℹ️ MODERATE: Maintain current security posture and schedule regular reviews.';
            } else {
                recommendation = '✅ GOOD: Security posture is strong. Continue monitoring and regular assessments.';
            }
            
            return {
                forecastScore: Math.round(forecast),
                trend: trend,
                confidence: Math.round(confidence),
                recommendation: recommendation,
                slope: slope
            };
        }
        
        function getHistoricalScores(vendorId) {
            return historyData
                .filter(h => h.vendor_id == vendorId)
                .sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
        }
        
        function getRiskClass(score) {
            if (score < 40) return 'risk-critical';
            if (score < 60) return 'risk-high';
            if (score < 80) return 'risk-moderate';
            return 'risk-low';
        }
        
        function getRiskText(score) {
            if (score < 40) return 'Critical Risk';
            if (score < 60) return 'High Risk';
            if (score < 80) return 'Moderate Risk';
            return 'Low Risk';
        }
        
        function renderForecasts() {
            let filteredVendors = [...vendors];
            
            if (currentFilter === 'improving') {
                filteredVendors = filteredVendors.filter(v => {
                    const history = getHistoricalScores(v.id);
                    const forecast = calculateForecast(v.id, history);
                    return forecast.trend === 'improving';
                });
            } else if (currentFilter === 'declining') {
                filteredVendors = filteredVendors.filter(v => {
                    const history = getHistoricalScores(v.id);
                    const forecast = calculateForecast(v.id, history);
                    return forecast.trend === 'declining';
                });
            } else if (currentFilter === 'stable') {
                filteredVendors = filteredVendors.filter(v => {
                    const history = getHistoricalScores(v.id);
                    const forecast = calculateForecast(v.id, history);
                    return forecast.trend === 'stable';
                });
            } else if (currentFilter === 'at-risk') {
                filteredVendors = filteredVendors.filter(v => {
                    const forecast = calculateForecast(v.id, getHistoricalScores(v.id));
                    return forecast.forecastScore < 60;
                });
            }
            
            if (filteredVendors.length === 0) {
                document.getElementById('forecast-grid').innerHTML = '<div class="card" style="text-align: center; padding: 3rem;">No vendors match the selected filter.</div>';
                return;
            }
            
            let html = '';
            filteredVendors.forEach(vendor => {
                const history = getHistoricalScores(vendor.id);
                const forecast = calculateForecast(vendor.id, history);
                const currentScore = vendor.current_score || 'N/A';
                const riskClass = getRiskClass(forecast.forecastScore);
                const riskText = getRiskText(forecast.forecastScore);
                const trendClass = forecast.trend === 'improving' ? 'trend-up' : (forecast.trend === 'declining' ? 'trend-down' : 'trend-stable');
                const trendIcon = forecast.trend === 'improving' ? '📈' : (forecast.trend === 'declining' ? '📉' : '➡️');
                
                html += `
                    <div class="forecast-card">
                        <div class="forecast-header">
                            <span class="vendor-name">${escapeHtml(vendor.name)}</span>
                            <span class="trend-indicator ${trendClass}">${trendIcon} ${forecast.trend.toUpperCase()}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>Current Score: <strong>${currentScore}%</strong></span>
                            <span>Forecast: <strong>${forecast.forecastScore}%</strong></span>
                        </div>
                        <div class="forecast-score">${forecast.forecastScore}%</div>
                        <div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.75rem; margin-bottom: 0.25rem;">
                                <span>Confidence Level</span>
                                <span>${forecast.confidence}%</span>
                            </div>
                            <div class="confidence-bar">
                                <div class="confidence-fill" style="width: ${forecast.confidence}%"></div>
                            </div>
                        </div>
                        <div class="risk-level ${riskClass}">
                            ${riskText} - ${forecast.forecastScore}%
                        </div>
                        <div class="recommendation">
                            <strong>💡 Recommendation:</strong><br>
                            ${forecast.recommendation}
                        </div>
                        ${forecast.slope ? `<div style="margin-top: 0.75rem; font-size: 0.7rem; color: var(--text-3);">
                            Trend: ${forecast.slope > 0 ? '+' : ''}${forecast.slope.toFixed(1)}% per assessment
                        </div>` : ''}
                    </div>
                `;
            });
            
            document.getElementById('forecast-grid').innerHTML = html;
        }
        
        function filterForecasts(filter) {
            currentFilter = filter;
            
            // Update active filter button
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.filter === filter) {
                    btn.classList.add('active');
                }
            });
            
            renderForecasts();
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
            renderForecasts();
        });
    </script>
    <script src="dashboard.js?v=<?php echo time(); ?>"></script>
</body>
</html>