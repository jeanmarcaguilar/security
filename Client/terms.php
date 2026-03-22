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
    <title>Terms & Privacy - CyberShield</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .terms-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .terms-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .terms-section {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-2);
        }
        .terms-section h3 {
            color: var(--primary);
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }
        .terms-section p {
            line-height: 1.6;
            margin-bottom: 1rem;
            color: var(--text-2);
        }
        .terms-section ul {
            margin-left: 1.5rem;
            margin-bottom: 1rem;
        }
        .terms-section li {
            margin-bottom: 0.5rem;
            line-height: 1.5;
        }
        .last-updated {
            text-align: center;
            font-size: 0.85rem;
            color: var(--text-3);
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-2);
        }
        .acceptance-bar {
            position: sticky;
            bottom: 0;
            background: var(--card-bg);
            border-top: 1px solid var(--border-2);
            padding: 1rem;
            text-align: center;
            margin-top: 2rem;
        }
        .print-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 100;
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div id="app">
        <div id="sidebar">
            <div class="sb-brand">
                <div class="shield">🛡️</div>
                <div class="sb-brand-text"><h2>CyberShield</h2><span>Terms & Privacy</span></div>
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
                    <h2>Terms of Service & Privacy Policy</h2>
                    <p>Your rights and our commitments</p>
                </div>
                <div class="topbar-right">
                    <button class="btn btn-secondary btn-sm" onclick="window.print()">🖨️ Print</button>
                    <button class="theme-toggle" onclick="toggleTheme()">🌙</button>
                </div>
            </div>
            
            <div class="content">
                <div class="terms-container">
                    <div class="terms-header">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📜</div>
                        <h1>Terms of Service & Privacy Policy</h1>
                        <p class="last-updated" style="margin-top: 0;">Last Updated: March 15, 2025</p>
                    </div>
                    
                    <div class="terms-section">
                        <h3>1. Introduction</h3>
                        <p>Welcome to CyberShield, a vendor cybersecurity hygiene assessment platform. By accessing or using our platform, you agree to be bound by these Terms of Service and our Privacy Policy. If you do not agree to these terms, please do not use our services.</p>
                        <p>CyberShield helps organizations evaluate their cybersecurity awareness and practices through standardized assessments, provides actionable insights, and tracks progress over time.</p>
                    </div>
                    
                    <div class="terms-section">
                        <h3>2. Data Collection and Usage</h3>
                        <p>We collect and process the following types of data:</p>
                        <ul>
                            <li><strong>Account Information:</strong> Name, email address, organization name, and role</li>
                            <li><strong>Assessment Data:</strong> Responses to security questionnaires, scores, and rankings</li>
                            <li><strong>Activity Logs:</strong> User actions within the platform (logins, assessments, exports)</li>
                            <li><strong>Technical Data:</strong> IP addresses, browser information, and device identifiers</li>
                        </ul>
                        <p>This data is used to:</p>
                        <ul>
                            <li>Calculate and display your security scores</li>
                            <li>Generate personalized recommendations</li>
                            <li>Track your progress over time</li>
                            <li>Provide leaderboard rankings (with your consent)</li>
                            <li>Improve our services and user experience</li>
                        </ul>
                    </div>
                    
                    <div class="terms-section">
                        <h3>3. Data Storage and Security</h3>
                        <p>All data is stored in a secure MySQL database hosted on our servers. We implement industry-standard security measures including:</p>
                        <ul>
                            <li>Password hashing using bcrypt</li>
                            <li>Session management with automatic expiration</li>
                            <li>Encrypted data transmission (HTTPS)</li>
                            <li>Regular security audits and updates</li>
                        </ul>
                        <p>Your data is never sold to third parties. We only share data when required by law or with your explicit consent.</p>
                    </div>
                    
                    <div class="terms-section">
                        <h3>4. Your Rights</h3>
                        <p>You have the following rights regarding your data:</p>
                        <ul>
                            <li><strong>Right to Access:</strong> View all data we hold about you</li>
                            <li><strong>Right to Rectification:</strong> Correct inaccurate or incomplete data</li>
                            <li><strong>Right to Erasure:</strong> Request deletion of your account and associated data</li>
                            <li><strong>Right to Data Portability:</strong> Export your data in a machine-readable format</li>
                            <li><strong>Right to Object:</strong> Opt-out of certain data processing activities</li>
                        </ul>
                        <p>To exercise these rights, contact your system administrator or email <strong>privacy@cybershield.ph</strong>.</p>
                    </div>
                    
                    <div class="terms-section">
                        <h3>5. Acceptable Use Policy</h3>
                        <p>You agree not to:</p>
                        <ul>
                            <li>Attempt to gain unauthorized access to other accounts</li>
                            <li>Upload malicious code or attempt to compromise the platform</li>
                            <li>Share assessment questions or answers outside the platform</li>
                            <li>Manipulate assessment results or leaderboard rankings</li>
                            <li>Use the platform for illegal activities</li>
                        </ul>
                        <p>Violation may result in immediate account termination and legal action.</p>
                    </div>
                    
                    <div class="terms-section">
                        <h3>6. Disclaimer of Warranties</h3>
                        <p>The platform is provided "as is" without warranties of any kind. While we strive for accuracy, we do not guarantee that:</p>
                        <ul>
                            <li>The platform will be uninterrupted or error-free</li>
                            <li>Assessment results guarantee complete security</li>
                            <li>Recommendations will prevent all security incidents</li>
                        </ul>
                        <p>CyberShield is a tool to help improve security awareness, not a guarantee of security.</p>
                    </div>
                    
                    <div class="terms-section">
                        <h3>7. Limitation of Liability</h3>
                        <p>To the maximum extent permitted by law, CyberShield shall not be liable for any indirect, incidental, special, consequential, or punitive damages resulting from your use of or inability to use the platform.</p>
                        <p>Our total liability shall not exceed the amount paid by you (if any) for using the platform.</p>
                    </div>
                    
                    <div class="terms-section">
                        <h3>8. Changes to Terms</h3>
                        <p>We may update these terms periodically. Significant changes will be notified via email or platform notification. Continued use of the platform after changes constitutes acceptance of the new terms.</p>
                        <p>This version was last updated on March 15, 2025.</p>
                    </div>
                    
                    <div class="terms-section">
                        <h3>9. Contact Information</h3>
                        <p>For questions about these terms or privacy practices, contact:</p>
                        <p><strong>Email:</strong> legal@cybershield.ph<br>
                        <strong>Address:</strong> CyberShield Security Inc., 123 Security Street, Tech City, 12345<br>
                        <strong>Phone:</strong> +1 (555) 123-4567</p>
                    </div>
                    
                    <div class="acceptance-bar">
                        <p>By using CyberShield, you acknowledge that you have read, understood, and agree to these Terms of Service and Privacy Policy.</p>
                        <button class="btn btn-primary" onclick="window.location.href='dashboard.php'">I Understand & Continue</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <button class="print-btn btn btn-secondary" onclick="window.print()">🖨️ Print Terms</button>
    
    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            html.setAttribute('data-theme', current === 'dark' ? 'light' : 'dark');
        }
    </script>
</body>
</html>