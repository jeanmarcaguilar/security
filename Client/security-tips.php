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

$tips = [
    ['id' => 1, 'category' => 'password', 'title' => 'Use Strong, Unique Passwords', 'content' => 'Create passwords that are at least 12 characters long, combining uppercase, lowercase, numbers, and symbols. Never reuse passwords across different accounts.', 'icon' => '🔐'],
    ['id' => 2, 'category' => 'password', 'title' => 'Enable Multi-Factor Authentication', 'content' => 'MFA adds an extra layer of security. Even if your password is compromised, attackers cannot access your account without the second factor.', 'icon' => '📱'],
    ['id' => 3, 'category' => 'password', 'title' => 'Use a Password Manager', 'content' => 'Password managers generate and store strong, unique passwords for all your accounts. You only need to remember one master password.', 'icon' => '🔑'],
    ['id' => 4, 'category' => 'phishing', 'title' => 'Verify Email Senders', 'content' => 'Always check the sender\'s email address carefully. Phishing emails often use addresses that look legitimate but have slight misspellings.', 'icon' => '✉️'],
    ['id' => 5, 'category' => 'phishing', 'title' => 'Hover Before Clicking', 'content' => 'Hover over links to see the actual URL before clicking. If it looks suspicious, don\'t click.', 'icon' => '🖱️'],
    ['id' => 6, 'category' => 'phishing', 'title' => 'Watch for Urgent Language', 'content' => 'Phishing emails often create a sense of urgency to make you act without thinking. Be skeptical of threats or immediate action requests.', 'icon' => '⚠️'],
    ['id' => 7, 'category' => 'device', 'title' => 'Keep Software Updated', 'content' => 'Regularly update your operating system, browsers, and applications. Updates often include critical security patches.', 'icon' => '🔄'],
    ['id' => 8, 'category' => 'device', 'title' => 'Install Antivirus Software', 'content' => 'Use reputable antivirus software and keep it updated. Run regular scans to detect and remove malware.', 'icon' => '🛡️'],
    ['id' => 9, 'category' => 'device', 'title' => 'Lock Your Devices', 'content' => 'Always lock your computer and mobile devices when stepping away. Use strong PINs or biometric authentication.', 'icon' => '🔒'],
    ['id' => 10, 'category' => 'network', 'title' => 'Use VPN on Public Wi-Fi', 'content' => 'Public Wi-Fi networks are often unsecured. A VPN encrypts your internet traffic, protecting your data from eavesdroppers.', 'icon' => '🌐'],
    ['id' => 11, 'category' => 'network', 'title' => 'Secure Your Home Wi-Fi', 'content' => 'Use WPA3 encryption, change the default router password, and disable WPS. Create a separate guest network for visitors.', 'icon' => '🏠'],
    ['id' => 12, 'category' => 'network', 'title' => 'Enable Firewall', 'content' => 'Firewalls monitor incoming and outgoing network traffic. Keep your firewall enabled on all devices and networks.', 'icon' => '🔥'],
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Tips - CyberShield</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .tips-header {
            background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
            border-radius: 24px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
        }
        .tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        .tip-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--border-2);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .tip-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        .tip-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .tip-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }
        .tip-content {
            font-size: 0.85rem;
            color: var(--text-2);
            line-height: 1.5;
        }
        .category-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .category-password { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        .category-phishing { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }
        .category-device { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .category-network { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        
        .filter-bar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .filter-chip {
            padding: 0.5rem 1rem;
            border-radius: 30px;
            background: var(--navy-3);
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.85rem;
        }
        .filter-chip.active {
            background: var(--primary);
            color: white;
        }
        .filter-chip:hover {
            transform: translateY(-2px);
        }
        .quiz-link {
            margin-top: 2rem;
            text-align: center;
            padding: 2rem;
            background: var(--navy-3);
            border-radius: 16px;
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div id="app">
        <div id="sidebar">
            <div class="sb-brand">
                <div class="shield">🛡️</div>
                <div class="sb-brand-text"><h2>CyberShield</h2><span>Security Tips</span></div>
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
                    <h2>Security Tips</h2>
                    <p>Practical guides to improve your cybersecurity posture</p>
                </div>
                <div class="topbar-right">
                    <button class="theme-toggle" onclick="toggleTheme()">🌙</button>
                </div>
            </div>
            
            <div class="content">
                <!-- Header -->
                <div class="tips-header">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🛡️</div>
                    <h2 style="margin-bottom: 0.5rem;">Cybersecurity Best Practices</h2>
                    <p>Implement these tips to protect yourself and your organization from cyber threats</p>
                </div>
                
                <!-- Filter Bar -->
                <div class="filter-bar">
                    <span class="filter-chip active" data-category="all" onclick="filterTips('all', this)">All Tips</span>
                    <span class="filter-chip" data-category="password" onclick="filterTips('password', this)">🔐 Password Security</span>
                    <span class="filter-chip" data-category="phishing" onclick="filterTips('phishing', this)">🎣 Phishing Awareness</span>
                    <span class="filter-chip" data-category="device" onclick="filterTips('device', this)">💻 Device Security</span>
                    <span class="filter-chip" data-category="network" onclick="filterTips('network', this)">🌐 Network Security</span>
                </div>
                
                <!-- Tips Grid -->
                <div class="tips-grid" id="tips-grid">
                    <?php foreach ($tips as $tip): ?>
                    <div class="tip-card" data-category="<?php echo $tip['category']; ?>" onclick="showTipDetail(<?php echo $tip['id']; ?>)">
                        <div class="tip-icon"><?php echo $tip['icon']; ?></div>
                        <span class="category-badge category-<?php echo $tip['category']; ?>"><?php echo ucfirst($tip['category']); ?></span>
                        <div class="tip-title"><?php echo htmlspecialchars($tip['title']); ?></div>
                        <div class="tip-content"><?php echo htmlspecialchars(substr($tip['content'], 0, 100)) . '...'; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Quiz Link -->
                <div class="quiz-link">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">📝</div>
                    <h3>Test Your Knowledge</h3>
                    <p>Take our security assessment to see how well you're implementing these practices</p>
                    <button class="btn btn-primary" onclick="window.location.href='assessment.php'">Start Assessment →</button>
                </div>
            </div>
        </div>
    </div>
    
    <div id="modal-overlay" class="modal-overlay hidden" onclick="closeModal(event)">
        <div class="modal" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="modal-title">Security Tip</h3>
                <button class="modal-close" onclick="closeModal()">✕</button>
            </div>
            <div id="modal-body"></div>
        </div>
    </div>
    
    <script>
        const tips = <?php echo json_encode($tips); ?>;
        
        function filterTips(category, element) {
            // Update active filter
            document.querySelectorAll('.filter-chip').forEach(chip => chip.classList.remove('active'));
            if (element) element.classList.add('active');
            
            // Filter tips
            const cards = document.querySelectorAll('.tip-card');
            cards.forEach(card => {
                if (category === 'all' || card.dataset.category === category) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        function showTipDetail(tipId) {
            const tip = tips.find(t => t.id === tipId);
            if (!tip) return;
            
            const modalBody = `
                <div style="padding: 0.5rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">${tip.icon}</div>
                    <div class="category-badge category-${tip.category}" style="margin-bottom: 1rem;">${tip.category.toUpperCase()}</div>
                    <h3 style="margin-bottom: 1rem;">${escapeHtml(tip.title)}</h3>
                    <p style="line-height: 1.6; margin-bottom: 1.5rem;">${escapeHtml(tip.content)}</p>
                    <div style="background: var(--navy-3); padding: 1rem; border-radius: 12px;">
                        <strong>💡 Pro Tip:</strong><br>
                        ${getProTip(tip.category)}
                    </div>
                    <div style="margin-top: 1.5rem; display: flex; gap: 0.5rem;">
                        <button class="btn btn-primary" onclick="closeModal()">Got it</button>
                        <button class="btn btn-secondary" onclick="window.location.href='assessment.php'">Take Assessment</button>
                    </div>
                </div>
            `;
            
            document.getElementById('modal-title').textContent = tip.title;
            document.getElementById('modal-body').innerHTML = modalBody;
            document.getElementById('modal-overlay').classList.remove('hidden');
        }
        
        function getProTip(category) {
            const proTips = {
                'password': 'Use a passphrase instead of a password - combine 4 random words like "purple-tiger-jumps-7" - it\'s easier to remember and harder to crack!',
                'phishing': 'When in doubt, pick up the phone! Call the sender through a known number to verify suspicious requests.',
                'device': 'Enable "Find My Device" features on all your devices. If lost or stolen, you can remotely lock or wipe them.',
                'network': 'Disable auto-connect to Wi-Fi networks. Your device might connect to malicious networks without your knowledge.'
            };
            return proTips[category] || 'Stay vigilant and keep learning about cybersecurity best practices.';
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
    </script>
</body>
</html>