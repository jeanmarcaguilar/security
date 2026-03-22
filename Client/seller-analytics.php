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

// Get products for analytics
$products_query = "SELECT * FROM products WHERE user_id = :user_id";
$stmt = $db->prepare($products_query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - CyberShield</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .kpi-card {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .kpi-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .kpi-trend {
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }
        .trend-up { color: #10b981; }
        .trend-down { color: #ef4444; }
        .analytics-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }
        @media (max-width: 768px) {
            .analytics-grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div id="app">
        <div id="sidebar">
            <div class="sb-brand">
                <div class="shield">🛡️</div>
                <div class="sb-brand-text"><h2>CyberShield</h2><span>Analytics</span></div>
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
                <a class="sb-item" href="forecast.php"><span class="sb-icon">🔮</span><span class="sb-text">Forecast</span></a>
                <a class="sb-item" href="compliance.php"><span class="sb-icon">✅</span><span class="sb-text">Compliance</span></a>
                <a class="sb-item" href="email.php"><span class="sb-icon">📧</span><span class="sb-text">Email Report</span></a>
            </div>
            <div class="sb-footer">
                <div class="sb-user">
                    <div class="sb-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
                    <div class="sb-user-info">
                        <p><?php echo htmlspecialchars($user['full_name']); ?></p>
                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                </div>
                <a href="../logout.php" class="btn-sb-logout">Sign Out</a>
            </div>
        </div>
        
        <div id="main">
            <div class="topbar">
                <div class="topbar-left">
                    <h2>Analytics Dashboard</h2>
                    <p>Store performance metrics and insights</p>
                </div>
                <div class="topbar-right">
                    <select id="period-select" class="filter-select" onchange="updateAnalytics()">
                        <option value="7">Last 7 Days</option>
                        <option value="30" selected>Last 30 Days</option>
                        <option value="90">Last 90 Days</option>
                    </select>
                    <button class="theme-toggle" onclick="toggleTheme()">🌙</button>
                </div>
            </div>
            
            <div class="content">
                <!-- KPI Cards -->
                <div class="stats-grid">
                    <div class="stat-card kpi-card">
                        <div class="kpi-icon">💰</div>
                        <div>
                            <div class="stat-label">Total Revenue</div>
                            <div class="stat-value" id="kpi-revenue">₱0</div>
                            <div class="kpi-trend" id="revenue-trend">+0%</div>
                        </div>
                    </div>
                    <div class="stat-card kpi-card">
                        <div class="kpi-icon">📦</div>
                        <div>
                            <div class="stat-label">Orders</div>
                            <div class="stat-value" id="kpi-orders">0</div>
                            <div class="kpi-trend" id="orders-trend">+0%</div>
                        </div>
                    </div>
                    <div class="stat-card kpi-card">
                        <div class="kpi-icon">👁️</div>
                        <div>
                            <div class="stat-label">Product Views</div>
                            <div class="stat-value" id="kpi-views">0</div>
                            <div class="kpi-trend" id="views-trend">+0%</div>
                        </div>
                    </div>
                    <div class="stat-card kpi-card">
                        <div class="kpi-icon">📊</div>
                        <div>
                            <div class="stat-label">Conversion Rate</div>
                            <div class="stat-value" id="kpi-conversion">0%</div>
                            <div class="kpi-trend" id="conversion-trend">+0%</div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts -->
                <div class="analytics-grid-2">
                    <div class="card chart-card">
                        <h3>Revenue Trend</h3>
                        <div class="chart-wrap" style="height: 250px;">
                            <canvas id="revenue-chart"></canvas>
                        </div>
                    </div>
                    <div class="card chart-card">
                        <h3>Sales by Category</h3>
                        <div class="chart-wrap" style="height: 250px;">
                            <canvas id="category-chart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="analytics-grid-2">
                    <div class="card chart-card">
                        <h3>Top Products</h3>
                        <div id="top-products-list" style="margin-top: 1rem;"></div>
                    </div>
                    <div class="card chart-card">
                        <h3>Customer Engagement</h3>
                        <div class="chart-wrap" style="height: 250px;">
                            <canvas id="engagement-chart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Inventory Status -->
                <div class="card">
                    <h3>Inventory Status</h3>
                    <div id="inventory-status"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const products = <?php echo json_encode($products); ?>;
        let revenueChart, categoryChart, engagementChart;
        
        // Generate mock analytics data
        function generateAnalyticsData(days) {
            const data = [];
            const now = new Date();
            for (let i = days; i >= 0; i--) {
                const date = new Date(now);
                date.setDate(date.getDate() - i);
                data.push({
                    date: date.toISOString().split('T')[0],
                    revenue: Math.floor(Math.random() * 5000) + 1000,
                    orders: Math.floor(Math.random() * 50) + 10,
                    views: Math.floor(Math.random() * 500) + 100
                });
            }
            return data;
        }
        
        function updateAnalytics() {
            const days = parseInt(document.getElementById('period-select').value);
            const data = generateAnalyticsData(days);
            
            // Calculate totals
            const totalRevenue = data.reduce((sum, d) => sum + d.revenue, 0);
            const totalOrders = data.reduce((sum, d) => sum + d.orders, 0);
            const totalViews = data.reduce((sum, d) => sum + d.views, 0);
            const conversionRate = totalOrders > 0 ? ((totalOrders / totalViews) * 100).toFixed(1) : 0;
            
            // Calculate trends (compare last 7 days to previous period)
            const midPoint = Math.floor(data.length / 2);
            const recentRevenue = data.slice(-7).reduce((sum, d) => sum + d.revenue, 0);
            const previousRevenue = data.slice(-14, -7).reduce((sum, d) => sum + d.revenue, 0);
            const revenueTrend = previousRevenue > 0 ? ((recentRevenue - previousRevenue) / previousRevenue * 100).toFixed(1) : 0;
            
            // Update KPI displays
            document.getElementById('kpi-revenue').textContent = `₱${totalRevenue.toLocaleString()}`;
            document.getElementById('kpi-orders').textContent = totalOrders;
            document.getElementById('kpi-views').textContent = totalViews;
            document.getElementById('kpi-conversion').textContent = `${conversionRate}%`;
            
            document.getElementById('revenue-trend').innerHTML = `${revenueTrend >= 0 ? '↑' : '↓'} ${Math.abs(revenueTrend)}%`;
            document.getElementById('revenue-trend').className = `kpi-trend ${revenueTrend >= 0 ? 'trend-up' : 'trend-down'}`;
            
            // Update charts
            updateRevenueChart(data);
            updateCategoryChart();
            updateEngagementChart();
            updateTopProducts();
            updateInventoryStatus();
        }
        
        function updateRevenueChart(data) {
            const ctx = document.getElementById('revenue-chart').getContext('2d');
            if (revenueChart) revenueChart.destroy();
            
            revenueChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.date),
                    datasets: [{
                        label: 'Revenue (₱)',
                        data: data.map(d => d.revenue),
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
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
        
        function updateCategoryChart() {
            const categories = {};
            products.forEach(p => {
                const cat = p.category || 'Other';
                categories[cat] = (categories[cat] || 0) + (p.stock * p.price);
            });
            
            const ctx = document.getElementById('category-chart').getContext('2d');
            if (categoryChart) categoryChart.destroy();
            
            categoryChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(categories),
                    datasets: [{
                        data: Object.values(categories),
                        backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true
                }
            });
        }
        
        function updateEngagementChart() {
            const ctx = document.getElementById('engagement-chart').getContext('2d');
            if (engagementChart) engagementChart.destroy();
            
            engagementChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: products.map(p => p.name.substring(0, 15)),
                    datasets: [{
                        label: 'Stock Level',
                        data: products.map(p => p.stock),
                        backgroundColor: '#3b82f6'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    indexAxis: 'y'
                }
            });
        }
        
        function updateTopProducts() {
            const sorted = [...products].sort((a, b) => (b.price * b.stock) - (a.price * a.stock)).slice(0, 5);
            const container = document.getElementById('top-products-list');
            
            if (sorted.length === 0) {
                container.innerHTML = '<p>No products yet</p>';
                return;
            }
            
            let html = '<div style="display: flex; flex-direction: column; gap: 0.75rem;">';
            sorted.forEach(p => {
                const value = p.price * p.stock;
                html += `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem; background: var(--navy-3); border-radius: 8px;">
                        <div>
                            <div style="font-weight: 600;">${escapeHtml(p.name)}</div>
                            <div style="font-size: 0.7rem; color: var(--text-3);">Stock: ${p.stock} units</div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 700; color: var(--primary);">₱${value.toLocaleString()}</div>
                            <div style="font-size: 0.7rem;">Inventory Value</div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        }
        
        function updateInventoryStatus() {
            const container = document.getElementById('inventory-status');
            const lowStock = products.filter(p => p.stock < 10 && p.stock > 0);
            const outOfStock = products.filter(p => p.stock === 0);
            
            let html = '<div style="display: flex; gap: 1rem; flex-wrap: wrap;">';
            
            if (lowStock.length > 0) {
                html += `<div style="flex: 1; background: rgba(245, 158, 11, 0.1); padding: 1rem; border-radius: 12px; border-left: 3px solid #f59e0b;">
                    <strong>⚠️ Low Stock Alert</strong><br>
                    ${lowStock.map(p => `${p.name}: ${p.stock} left`).join('<br>')}
                </div>`;
            }
            
            if (outOfStock.length > 0) {
                html += `<div style="flex: 1; background: rgba(239, 68, 68, 0.1); padding: 1rem; border-radius: 12px; border-left: 3px solid #ef4444;">
                    <strong>🔥 Out of Stock</strong><br>
                    ${outOfStock.map(p => p.name).join('<br>')}
                </div>`;
            }
            
            if (lowStock.length === 0 && outOfStock.length === 0) {
                html += `<div style="flex: 1; background: rgba(16, 185, 129, 0.1); padding: 1rem; border-radius: 12px;">
                    ✅ All products have sufficient stock levels
                </div>`;
            }
            
            html += '</div>';
            container.innerHTML = html;
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
        
        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            updateAnalytics();
        });
    </script>
</body>
</html>