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

// Get leaderboard data
$leaderboard_query = "SELECT v.name as vendor_name, v.store_name, 
    va.score, va.rank, va.password_score, va.phishing_score, 
    va.device_score, va.network_score, va.created_at
    FROM vendor_assessments va
    JOIN vendors v ON va.vendor_id = v.id
    WHERE va.id IN (SELECT MAX(id) FROM vendor_assessments GROUP BY vendor_id)
    ORDER BY va.score DESC";
$stmt = $db->prepare($leaderboard_query);
$stmt->execute();
$leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - CyberShield</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .leaderboard-header {
            background: linear-gradient(135deg, var(--primary) 0%, #3b82f6 100%);
            border-radius: 24px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            text-align: center;
        }
        .rank-1 {
            background: linear-gradient(135deg, #f59e0b, #ef4444);
            color: white;
        }
        .rank-2 {
            background: linear-gradient(135deg, #6b7280, #9ca3af);
            color: white;
        }
        .rank-3 {
            background: linear-gradient(135deg, #b45309, #f59e0b);
            color: white;
        }
        .medal {
            font-size: 1.5rem;
            margin-right: 0.5rem;
        }
        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
        }
        .leaderboard-table th,
        .leaderboard-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-2);
        }
        .leaderboard-table tr:hover {
            background: var(--navy-3);
        }
        .current-user-row {
            background: rgba(59, 130, 246, 0.1);
            border-left: 3px solid var(--primary);
        }
        .filter-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
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
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div id="app">
        <div id="sidebar">
            <div class="sb-brand">
                <div class="shield">🛡️</div>
                <div class="sb-brand-text"><h2>CyberShield</h2><span>Leaderboard</span></div>
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
                    <h2>Vendor Leaderboard</h2>
                    <p>Top performers in cybersecurity hygiene</p>
                </div>
                <div class="topbar-right">
                    <button class="btn btn-secondary btn-sm" onclick="exportLeaderboard()">📊 Export CSV</button>
                    <button class="theme-toggle" onclick="toggleTheme()">🌙</button>
                </div>
            </div>
            
            <div class="content">
                <!-- Hero Section -->
                <div class="leaderboard-header">
                    <h2 style="margin-bottom: 0.5rem;">🏆 Top Security Champions 🏆</h2>
                    <p>Vendors with the best cybersecurity practices</p>
                </div>
                
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo count($leaderboard); ?></div>
                        <div>Total Vendors</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php
                            $avgScore = array_sum(array_column($leaderboard, 'score')) / max(1, count($leaderboard));
                            echo round($avgScore, 1) . '%';
                            ?>
                        </div>
                        <div>Average Score</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php
                            $topScore = !empty($leaderboard) ? $leaderboard[0]['score'] : 0;
                            echo $topScore . '%';
                            ?>
                        </div>
                        <div>Top Score</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php
                            $aCount = count(array_filter($leaderboard, fn($v) => $v['rank'] === 'A'));
                            echo $aCount;
                            ?>
                        </div>
                        <div>Low Risk (A)</div>
                    </div>
                </div>
                
                <!-- Filter Buttons -->
                <div class="card">
                    <div class="filter-buttons">
                        <button class="filter-btn active" onclick="filterLeaderboard('all', this)">All Vendors</button>
                        <button class="filter-btn" onclick="filterLeaderboard('A', this)">🏆 Low Risk (A)</button>
                        <button class="filter-btn" onclick="filterLeaderboard('B', this)">⭐ Moderate (B)</button>
                        <button class="filter-btn" onclick="filterLeaderboard('C', this)">⚠️ High Risk (C)</button>
                        <button class="filter-btn" onclick="filterLeaderboard('D', this)">🔥 Critical (D)</button>
                    </div>
                    
                    <div style="overflow-x: auto;">
                        <table class="leaderboard-table" id="leaderboard-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Vendor</th>
                                    <th>Overall Score</th>
                                    <th>Risk Level</th>
                                    <th>Password</th>
                                    <th>Phishing</th>
                                    <th>Device</th>
                                    <th>Network</th>
                                    <th>Last Assessment</th>
                                </tr>
                            </thead>
                            <tbody id="leaderboard-body">
                                <?php foreach ($leaderboard as $index => $vendor): ?>
                                <tr data-rank="<?php echo $vendor['rank']; ?>" 
                                    data-score="<?php echo $vendor['score']; ?>"
                                    <?php if (isset($vendor['store_name']) && $vendor['store_name'] === $user['store_name']): ?>class="current-user-row"<?php endif; ?>>
                                    <td>
                                        <?php if ($index === 0): ?>
                                            <span class="medal">🥇</span> 1st
                                        <?php elseif ($index === 1): ?>
                                            <span class="medal">🥈</span> 2nd
                                        <?php elseif ($index === 2): ?>
                                            <span class="medal">🥉</span> 3rd
                                        <?php else: ?>
                                            <?php echo $index + 1; ?>th
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($vendor['vendor_name']); ?></strong></td>
                                    <td><span style="font-size: 1.2rem; font-weight: 700;"><?php echo $vendor['score']; ?>%</span></td>
                                    <td><span class="rank-badge rank-<?php echo strtolower($vendor['rank']); ?>"><?php echo $vendor['rank']; ?></span></td>
                                    <td><?php echo $vendor['password_score']; ?>%</td>
                                    <td><?php echo $vendor['phishing_score']; ?>%</td>
                                    <td><?php echo $vendor['device_score']; ?>%</td>
                                    <td><?php echo $vendor['network_score']; ?>%</td>
                                    <td><?php echo date('M j, Y', strtotime($vendor['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let currentFilter = 'all';
        
        function filterLeaderboard(rank, btn) {
            currentFilter = rank;
            
            // Update active button
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');
            
            const rows = document.querySelectorAll('#leaderboard-body tr');
            rows.forEach(row => {
                const rowRank = row.dataset.rank;
                if (rank === 'all' || rowRank === rank) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        function exportLeaderboard() {
            let csv = "Rank,Vendor,Score,Rank,Password,Phishing,Device,Network,Last Assessment\n";
            const rows = document.querySelectorAll('#leaderboard-body tr');
            let visibleRank = 1;
            
            rows.forEach((row, index) => {
                if (row.style.display !== 'none') {
                    const cells = row.querySelectorAll('td');
                    csv += `${visibleRank},"${cells[1]?.innerText.replace(/"/g, '""') || ''}",${cells[2]?.innerText || ''},${cells[3]?.innerText || ''},${cells[4]?.innerText || ''},${cells[5]?.innerText || ''},${cells[6]?.innerText || ''},${cells[7]?.innerText || ''},${cells[8]?.innerText || ''}\n`;
                    visibleRank++;
                }
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `leaderboard_${new Date().toISOString()}.csv`;
            a.click();
            URL.revokeObjectURL(url);
        }
        
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            html.setAttribute('data-theme', current === 'dark' ? 'light' : 'dark');
        }
    </script>
</body>
</html>