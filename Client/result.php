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

// Get latest assessment for this user's vendor
$assessment_query = "SELECT va.*, v.name as vendor_name 
    FROM vendor_assessments va 
    JOIN vendors v ON va.vendor_id = v.id 
    WHERE v.email = :email 
    ORDER BY va.created_at DESC LIMIT 1";
$stmt = $db->prepare($assessment_query);
$stmt->bindParam(':email', $user['email']);
$stmt->execute();
$latest_assessment = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all assessments for history
$history_query = "SELECT va.*, v.name as vendor_name 
    FROM vendor_assessments va 
    JOIN vendors v ON va.vendor_id = v.id 
    WHERE v.email = :email 
    ORDER BY va.created_at DESC";
$stmt = $db->prepare($history_query);
$stmt->bindParam(':email', $user['email']);
$stmt->execute();
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Results - CyberShield</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .result-hero {
            background: linear-gradient(135deg, var(--card-bg) 0%, var(--navy-3) 100%);
            border-radius: 24px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
            border: 1px solid var(--border-2);
        }
        .score-circle {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: conic-gradient(var(--primary) 0deg, var(--navy-3) 0deg);
            position: relative;
        }
        .score-inner {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: var(--card-bg);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .score-value {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary);
        }
        .rank-badge-large {
            font-size: 2rem;
            font-weight: 700;
            padding: 0.25rem 1rem;
            border-radius: 50px;
            display: inline-block;
            margin-top: 1rem;
        }
        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .category-card {
            background: var(--navy-3);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
        }
        .category-score {
            font-size: 1.5rem;
            font-weight: 700;
            margin-top: 0.5rem;
        }
        .recommendation-item {
            padding: 0.75rem;
            border-left: 3px solid var(--primary);
            margin-bottom: 0.75rem;
            background: var(--navy-3);
            border-radius: 8px;
        }
        .badge-earned {
            background: linear-gradient(135deg, #f59e0b, #ef4444);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .video-card {
            background: var(--navy-3);
            border-radius: 12px;
            padding: 1rem;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .video-card:hover {
            transform: translateY(-4px);
        }
        .video-thumb {
            width: 100%;
            height: 120px;
            background: var(--card-bg);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div id="app">
        <div id="sidebar">
            <div class="sb-brand">
                <div class="shield">🛡️</div>
                <div class="sb-brand-text"><h2>CyberShield</h2><span>Results</span></div>
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
                    <h2>Assessment Results</h2>
                    <p>Your cybersecurity hygiene evaluation</p>
                </div>
                <div class="topbar-right">
                    <button class="btn btn-secondary btn-sm" onclick="exportResults()">📄 Export Report</button>
                    <button class="btn btn-primary btn-sm" onclick="startNewAssessment()">Start New Assessment</button>
                    <button class="theme-toggle" onclick="toggleTheme()">🌙</button>
                </div>
            </div>
            
            <div class="content">
                <?php if ($latest_assessment): ?>
                <!-- Hero Section -->
                <div class="result-hero">
                    <div class="score-circle" id="score-circle">
                        <div class="score-inner">
                            <div class="score-value"><?php echo $latest_assessment['score']; ?>%</div>
                            <div style="font-size: 0.8rem;">Overall Score</div>
                        </div>
                    </div>
                    <div class="rank-badge-large rank-<?php echo strtolower($latest_assessment['rank']); ?>">
                        Rank <?php echo $latest_assessment['rank']; ?>
                    </div>
                    <p style="margin-top: 1rem;">
                        <?php
                        if ($latest_assessment['rank'] == 'A') echo "Excellent! Your security practices are outstanding.";
                        elseif ($latest_assessment['rank'] == 'B') echo "Good work! You have a solid foundation with room for improvement.";
                        elseif ($latest_assessment['rank'] == 'C') echo "Significant improvements needed. Review recommendations below.";
                        else echo "Critical risk detected. Immediate action required!";
                        ?>
                    </p>
                    <div style="margin-top: 1rem;">
                        <span class="badge-earned">
                            <?php
                            $badges = [];
                            if ($latest_assessment['score'] >= 80) $badges[] = "🏆 Security Champion";
                            if ($latest_assessment['password_score'] >= 80) $badges[] = "🔒 Password Pro";
                            if ($latest_assessment['phishing_score'] >= 80) $badges[] = "🎣 Phishing Aware";
                            if ($latest_assessment['device_score'] >= 80) $badges[] = "💻 Device Guardian";
                            if ($latest_assessment['network_score'] >= 80) $badges[] = "🌐 Network Defender";
                            echo implode(' ', array_slice($badges, 0, 3));
                            ?>
                        </span>
                    </div>
                </div>
                
                <!-- Category Scores -->
                <div class="card">
                    <h3>Category Performance</h3>
                    <div class="category-grid">
                        <div class="category-card">
                            <div>🔐 Password Security</div>
                            <div class="category-score"><?php echo $latest_assessment['password_score']; ?>%</div>
                        </div>
                        <div class="category-card">
                            <div>🎣 Phishing Awareness</div>
                            <div class="category-score"><?php echo $latest_assessment['phishing_score']; ?>%</div>
                        </div>
                        <div class="category-card">
                            <div>💻 Device Security</div>
                            <div class="category-score"><?php echo $latest_assessment['device_score']; ?>%</div>
                        </div>
                        <div class="category-card">
                            <div>🌐 Network Security</div>
                            <div class="category-score"><?php echo $latest_assessment['network_score']; ?>%</div>
                        </div>
                    </div>
                </div>
                
                <!-- Radar Chart -->
                <div class="card chart-card">
                    <h3>Security Posture Radar</h3>
                    <div class="chart-wrap" style="height: 300px;">
                        <canvas id="radar-chart"></canvas>
                    </div>
                </div>
                
                <!-- Recommendations -->
                <div class="card">
                    <h3>Personalized Recommendations</h3>
                    <div id="recommendations">
                        <?php
                        $recommendations = [];
                        if ($latest_assessment['password_score'] < 60) {
                            $recommendations[] = "🔐 Use a password manager to generate and store strong, unique passwords for all accounts.";
                            $recommendations[] = "🔐 Enable multi-factor authentication (MFA) on all critical accounts.";
                        }
                        if ($latest_assessment['phishing_score'] < 60) {
                            $recommendations[] = "🎣 Complete security awareness training to identify phishing attempts.";
                            $recommendations[] = "🎣 Always verify suspicious emails by contacting the sender through a known channel.";
                        }
                        if ($latest_assessment['device_score'] < 60) {
                            $recommendations[] = "💻 Install and regularly update antivirus/anti-malware software.";
                            $recommendations[] = "💻 Enable automatic updates for your operating system and applications.";
                        }
                        if ($latest_assessment['network_score'] < 60) {
                            $recommendations[] = "🌐 Use a VPN when connecting to public Wi-Fi networks.";
                            $recommendations[] = "🌐 Secure your home Wi-Fi with WPA3 encryption and a strong password.";
                        }
                        if (empty($recommendations)) {
                            $recommendations[] = "✅ Great job! Maintain your security practices and stay vigilant.";
                        }
                        
                        foreach ($recommendations as $rec) {
                            echo "<div class='recommendation-item'>$rec</div>";
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Progress Chart -->
                <?php if (count($history) > 1): ?>
                <div class="card chart-card">
                    <h3>Progress Over Time</h3>
                    <div class="chart-wrap" style="height: 300px;">
                        <canvas id="progress-chart"></canvas>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Learning Resources -->
                <div class="card">
                    <h3>Learning Resources</h3>
                    <div class="video-grid" id="video-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 1rem;">
                        <?php
                        $videos = [
                            ['title' => 'Password Security Best Practices', 'url' => 'https://www.youtube.com/embed/example1', 'category' => 'password'],
                            ['title' => 'How to Spot Phishing Emails', 'url' => 'https://www.youtube.com/embed/example2', 'category' => 'phishing'],
                            ['title' => 'Device Security Essentials', 'url' => 'https://www.youtube.com/embed/example3', 'category' => 'device'],
                            ['title' => 'Network Security Fundamentals', 'url' => 'https://www.youtube.com/embed/example4', 'category' => 'network'],
                        ];
                        
                        $weakCategories = [];
                        if ($latest_assessment['password_score'] < 70) $weakCategories[] = 'password';
                        if ($latest_assessment['phishing_score'] < 70) $weakCategories[] = 'phishing';
                        if ($latest_assessment['device_score'] < 70) $weakCategories[] = 'device';
                        if ($latest_assessment['network_score'] < 70) $weakCategories[] = 'network';
                        
                        foreach ($videos as $video) {
                            if (empty($weakCategories) || in_array($video['category'], $weakCategories)) {
                                echo "<div class='video-card' onclick=\"window.open('{$video['url']}', '_blank')\">
                                    <div class='video-thumb'>📹</div>
                                    <div><strong>{$video['title']}</strong></div>
                                    <div style='font-size: 0.7rem; color: var(--text-3); margin-top: 0.5rem;'>Click to watch</div>
                                </div>";
                            }
                        }
                        ?>
                    </div>
                </div>
                
                <?php else: ?>
                <div class="card" style="text-align: center; padding: 3rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📊</div>
                    <h3>No Assessment Results Yet</h3>
                    <p>You haven't completed any assessments. Start your first assessment now!</p>
                    <button class="btn btn-primary" onclick="startNewAssessment()" style="margin-top: 1rem;">Start Assessment</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        const latestAssessment = <?php echo json_encode($latest_assessment); ?>;
        const history = <?php echo json_encode($history); ?>;
        
        function initRadarChart() {
            if (!latestAssessment) return;
            
            const ctx = document.getElementById('radar-chart').getContext('2d');
            new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: ['Password Security', 'Phishing Awareness', 'Device Security', 'Network Security'],
                    datasets: [{
                        label: 'Your Score',
                        data: [
                            latestAssessment.password_score,
                            latestAssessment.phishing_score,
                            latestAssessment.device_score,
                            latestAssessment.network_score
                        ],
                        backgroundColor: 'rgba(59, 130, 246, 0.2)',
                        borderColor: '#3b82f6',
                        borderWidth: 2,
                        pointBackgroundColor: '#3b82f6'
                    }, {
                        label: 'Benchmark (Industry Avg)',
                        data: [65, 60, 70, 55],
                        backgroundColor: 'rgba(139, 92, 246, 0.2)',
                        borderColor: '#8b5cf6',
                        borderWidth: 2,
                        pointBackgroundColor: '#8b5cf6'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100,
                            ticks: { stepSize: 20 }
                        }
                    }
                }
            });
        }
        
        function initProgressChart() {
            if (history.length < 2) return;
            
            const ctx = document.getElementById('progress-chart').getContext('2d');
            const dates = history.map(h => new Date(h.created_at).toLocaleDateString()).reverse();
            const scores = history.map(h => h.score).reverse();
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [{
                        label: 'Assessment Score',
                        data: scores,
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
                        tooltip: {
                            callbacks: {
                                label: (context) => `Score: ${context.raw}%`
                            }
                        }
                    }
                }
            });
        }
        
        function exportResults() {
            if (!latestAssessment) {
                alert('No assessment data to export.');
                return;
            }
            
            const report = `CyberShield Assessment Report
Generated: ${new Date().toLocaleString()}
Vendor: ${latestAssessment.vendor_name}
Overall Score: ${latestAssessment.score}%
Risk Rank: ${latestAssessment.rank}

Category Breakdown:
- Password Security: ${latestAssessment.password_score}%
- Phishing Awareness: ${latestAssessment.phishing_score}%
- Device Security: ${latestAssessment.device_score}%
- Network Security: ${latestAssessment.network_score}%

Recommendations:
${document.querySelector('#recommendations').innerText}

Assessment Date: ${new Date(latestAssessment.created_at).toLocaleString()}`;
            
            const blob = new Blob([report], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `cybershield_report_${new Date().toISOString()}.txt`;
            a.click();
            URL.revokeObjectURL(url);
        }
        
        function startNewAssessment() {
            window.location.href = 'assessment.php';
        }
        
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            html.setAttribute('data-theme', current === 'dark' ? 'light' : 'dark');
        }
        
        // Draw score circle
        function drawScoreCircle() {
            if (!latestAssessment) return;
            
            const circle = document.getElementById('score-circle');
            const score = latestAssessment.score;
            const angle = (score / 100) * 360;
            circle.style.background = `conic-gradient(var(--primary) 0deg ${angle}deg, var(--navy-3) ${angle}deg)`;
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            drawScoreCircle();
            initRadarChart();
            initProgressChart();
        });
    </script>
</body>
</html>