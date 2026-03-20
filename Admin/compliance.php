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

// Get latest assessments for all vendors
$assessments_query = "SELECT va.*, v.name as vendor_name 
    FROM vendor_assessments va 
    JOIN vendors v ON va.vendor_id = v.id 
    WHERE va.id IN (SELECT MAX(id) FROM vendor_assessments GROUP BY vendor_id)";
$stmt = $db->prepare($assessments_query);
$stmt->execute();
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compliance framework items
$complianceItems = [
    'password' => [
        'title' => 'Password Security',
        'standards' => ['NIST SP 800-63B', 'ISO 27001 A.9.4.3'],
        'requirements' => [
            'Password complexity enforced (uppercase, lowercase, numbers, special chars)',
            'Password minimum length of 12 characters',
            'Multi-factor authentication implemented',
            'Password expiration policy (90 days max)',
            'Password history (last 5 passwords not reused)'
        ]
    ],
    'phishing' => [
        'title' => 'Phishing Awareness',
        'standards' => ['ISO 27001 A.7.2.2', 'GDPR Art. 32'],
        'requirements' => [
            'Annual security awareness training completed',
            'Quarterly phishing simulation exercises',
            'Report phishing button implemented in email client',
            'Immediate incident reporting procedure established',
            'Training completion rate above 95%'
        ]
    ],
    'device' => [
        'title' => 'Device Security',
        'standards' => ['NIST SP 800-53', 'ISO 27001 A.9.1.2'],
        'requirements' => [
            'Endpoint protection software installed and updated',
            'Full disk encryption enabled on all devices',
            'Automatic security updates configured',
            'Mobile device management (MDM) implemented',
            'Device inventory maintained and reviewed monthly'
        ]
    ],
    'network' => [
        'title' => 'Network Security',
        'standards' => ['PCI DSS Requirement 1', 'ISO 27001 A.13.1.1'],
        'requirements' => [
            'Firewall configured and maintained',
            'Network segmentation implemented',
            'Intrusion detection/prevention system active',
            'Regular vulnerability scans (monthly)',
            'Penetration testing performed annually'
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compliance Checklist - CyberShield</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .compliance-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .compliance-card {
            background: var(--card-bg);
            padding: 1.25rem;
            border-radius: 12px;
            text-align: center;
        }
        .compliance-score {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            font-family: var(--display);
        }
        .compliance-label {
            font-size: 0.8rem;
            color: var(--text-3);
            margin-top: 0.5rem;
            font-family: var(--mono);
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .checklist-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 0.75rem;
            border-bottom: 1px solid var(--border-2);
        }
        .checklist-status {
            flex-shrink: 0;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .status-pass {
            background: #10b981;
            color: white;
        }
        .status-fail {
            background: #ef4444;
            color: white;
        }
        .status-partial {
            background: #f59e0b;
            color: white;
        }
        .checklist-text {
            flex: 1;
            font-family: var(--font);
            font-size: 0.85rem;
            line-height: 1.5;
        }
        .checklist-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            font-family: var(--font);
        }
        .checklist-standard {
            font-size: 0.7rem;
            color: var(--text-3);
            font-family: var(--mono);
            letter-spacing: 0.5px;
        }
        .vendor-selector {
            margin-bottom: 1.5rem;
        }
        .framework-badge {
            display: inline-block;
            padding: 2px 8px;
            background: var(--navy-3);
            border-radius: 12px;
            font-size: 0.7rem;
            margin-right: 0.5rem;
            font-family: var(--mono);
        }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .progress-bar {
            height: 8px;
            background: var(--navy-3);
            border-radius: 10px;
            overflow: hidden;
            margin: 0.5rem 0;
        }
        .progress-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        .recommendation-box {
            background: var(--navy-3);
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            border-left: 3px solid var(--primary);
        }
        .recommendation-box strong {
            font-family: var(--font);
            font-size: 0.95rem;
        }
        .recommendation-box ul {
            font-family: var(--font);
            font-size: 0.85rem;
            line-height: 1.5;
        }
        .recommendation-box div {
            font-family: var(--font);
            font-size: 0.85rem;
            line-height: 1.5;
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
        .modal-body p {
            font-family: var(--font);
            font-size: 0.9rem;
            line-height: 1.6;
        }
        .filter-select {
            font-family: var(--font);
        }
        div[style*="font-size: 1rem"] {
            font-family: var(--display);
        }
        div[style*="font-size: 1.2rem"] {
            font-family: var(--display);
        }
        div[style*="font-size: 0.7rem"] {
            font-family: var(--font);
        }
        div[style*="color: #ef4444"] {
            font-family: var(--font);
        }
        div[style*="color: #f59e0b"] {
            font-family: var(--font);
        }
        div[style*="margin-left: 1rem"] {
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
                <div class="sb-brand-text"><h2>CyberShield</h2><span>Compliance</span></div>
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
                <a class="sb-item active" href="compliance.php"><span class="sb-icon">✅</span><span class="sb-text">Compliance</span></a>
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
                    <h2>Compliance Checklist</h2>
                    <p>Security compliance requirements per vendor</p>
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
                <div class="card vendor-selector">
                    <div class="table-toolbar">
                        <h3>Select Vendor</h3>
                        <select id="vendor-select" class="filter-select" onchange="loadCompliance()">
                            <?php foreach($vendors as $vendor): ?>
                            <option value="<?php echo $vendor['id']; ?>"><?php echo htmlspecialchars($vendor['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div id="compliance-content"></div>
            </div>
        </div>
    </div>
    
    <div id="modal-overlay" class="modal-overlay hidden" onclick="closeModal(event)">
        <div class="modal modal-md" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="modal-title">Compliance Details</h3>
                <button class="modal-close" onclick="closeModal()">✕</button>
            </div>
            <div id="modal-body"></div>
        </div>
    </div>
    
    <script>
        const assessments = <?php echo json_encode($assessments); ?>;
        const complianceItems = <?php echo json_encode($complianceItems); ?>;
        
        function getVendorAssessment(vendorId) {
            return assessments.find(a => a.vendor_id == vendorId);
        }
        
        function calculateComplianceScore(assessment) {
            if (!assessment) return 0;
            
            // Map scores to compliance percentages
            const passwordCompliance = Math.min(100, Math.max(0, assessment.password_score * 1.1));
            const phishingCompliance = Math.min(100, Math.max(0, assessment.phishing_score * 1.05));
            const deviceCompliance = Math.min(100, Math.max(0, assessment.device_score * 1.1));
            const networkCompliance = Math.min(100, Math.max(0, assessment.network_score * 1.1));
            
            return {
                overall: Math.round((passwordCompliance + phishingCompliance + deviceCompliance + networkCompliance) / 4),
                password: Math.round(passwordCompliance),
                phishing: Math.round(phishingCompliance),
                device: Math.round(deviceCompliance),
                network: Math.round(networkCompliance)
            };
        }
        
        function getRequirementStatus(score, requirementIndex) {
            // Simulate requirement status based on score
            const threshold = 100 - (requirementIndex * 20);
            if (score >= threshold) return 'pass';
            if (score >= threshold - 30) return 'partial';
            return 'fail';
        }
        
        function loadCompliance() {
            const vendorId = document.getElementById('vendor-select').value;
            const assessment = getVendorAssessment(vendorId);
            const complianceScores = calculateComplianceScore(assessment);
            
            let overallHtml = `
                <div class="compliance-summary">
                    <div class="compliance-card">
                        <div class="compliance-score">${complianceScores.overall}%</div>
                        <div class="compliance-label">Overall Compliance</div>
                        <div class="progress-bar"><div class="progress-fill" style="width: ${complianceScores.overall}%"></div></div>
                    </div>
                    <div class="compliance-card">
                        <div class="compliance-score">${complianceScores.password}%</div>
                        <div class="compliance-label">Password Security</div>
                    </div>
                    <div class="compliance-card">
                        <div class="compliance-score">${complianceScores.phishing}%</div>
                        <div class="compliance-label">Phishing Awareness</div>
                    </div>
                    <div class="compliance-card">
                        <div class="compliance-score">${complianceScores.device}%</div>
                        <div class="compliance-label">Device Security</div>
                    </div>
                    <div class="compliance-card">
                        <div class="compliance-score">${complianceScores.network}%</div>
                        <div class="compliance-label">Network Security</div>
                    </div>
                </div>
            `;
            
            // Generate checklists for each category
            let checklistsHtml = '<div class="card"><h3>Compliance Requirements Checklist</h3>';
            
            for (const [key, item] of Object.entries(complianceItems)) {
                const score = complianceScores[key];
                const statusClass = score >= 80 ? 'status-pass' : (score >= 50 ? 'status-partial' : 'status-fail');
                const statusIcon = score >= 80 ? '✓' : (score >= 50 ? '⚠️' : '✗');
                
                checklistsHtml += `
                    <div style="margin-bottom: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <h4 style="font-size: 1rem;">${item.title}</h4>
                            <div class="compliance-score" style="font-size: 1.2rem;">${score}%</div>
                        </div>
                        <div class="progress-bar"><div class="progress-fill" style="width: ${score}%"></div></div>
                        <div style="margin-top: 0.5rem;">
                            ${item.standards.map(s => `<span class="framework-badge">${s}</span>`).join('')}
                        </div>
                `;
                
                // Requirements list
                item.requirements.forEach((req, idx) => {
                    const status = getRequirementStatus(score, idx);
                    const statusIcon = status === 'pass' ? '✓' : (status === 'partial' ? '⚠️' : '✗');
                    const statusClass = status === 'pass' ? 'status-pass' : (status === 'partial' ? 'status-partial' : 'status-fail');
                    
                    checklistsHtml += `
                        <div class="checklist-item">
                            <div class="checklist-status ${statusClass}">${statusIcon}</div>
                            <div class="checklist-text">
                                <div class="checklist-title">${req}</div>
                                ${status === 'fail' ? '<div style="font-size: 0.7rem; color: #ef4444;">Requires immediate attention</div>' : ''}
                                ${status === 'partial' ? '<div style="font-size: 0.7rem; color: #f59e0b;">Partially compliant - improvements needed</div>' : ''}
                            </div>
                        </div>
                    `;
                });
                
                checklistsHtml += `</div>`;
            }
            
            // Generate recommendations
            let recommendationsHtml = '';
            if (complianceScores.overall < 70) {
                recommendationsHtml = `
                    <div class="recommendation-box">
                        <strong>📋 Action Plan:</strong><br>
                        <ul style="margin-top: 0.5rem; margin-left: 1rem;">
                            ${complianceScores.password < 70 ? '<li>Implement password policy enhancements (complexity, MFA, expiration)</li>' : ''}
                            ${complianceScores.phishing < 70 ? '<li>Schedule security awareness training and phishing simulations</li>' : ''}
                            ${complianceScores.device < 70 ? '<li>Review endpoint security controls and device management policies</li>' : ''}
                            ${complianceScores.network < 70 ? '<li>Conduct network security assessment and implement segmentation</li>' : ''}
                        </ul>
                        <div style="margin-top: 0.5rem;">
                            <button class="btn btn-primary btn-sm" onclick="exportComplianceReport()">📄 Export Compliance Report</button>
                            <button class="btn btn-secondary btn-sm" onclick="scheduleRemediation()">📅 Schedule Remediation</button>
                        </div>
                    </div>
                `;
            } else if (complianceScores.overall >= 85) {
                recommendationsHtml = `
                    <div class="recommendation-box" style="border-left-color: #10b981;">
                        <strong>✅ Excellent Compliance Status!</strong><br>
                        This vendor meets or exceeds compliance requirements. Schedule regular reviews to maintain standards.
                    </div>
                `;
            } else {
                recommendationsHtml = `
                    <div class="recommendation-box" style="border-left-color: #f59e0b;">
                        <strong>ℹ️ Moderate Compliance Status</strong><br>
                        Vendor meets most requirements but has room for improvement. Review highlighted areas and create remediation plan.
                    </div>
                `;
            }
            
            checklistsHtml += `</div>`;
            
            document.getElementById('compliance-content').innerHTML = overallHtml + checklistsHtml + recommendationsHtml;
        }
        
        function exportComplianceReport() {
            const vendorId = document.getElementById('vendor-select').value;
            const vendor = vendors.find(v => v.id == vendorId);
            const assessment = getVendorAssessment(vendorId);
            const scores = calculateComplianceScore(assessment);
            
            let report = `Compliance Report - ${vendor.name}\n`;
            report += `Generated: ${new Date().toLocaleString()}\n`;
            report += `Overall Compliance Score: ${scores.overall}%\n\n`;
            report += `Category Scores:\n`;
            report += `- Password Security: ${scores.password}%\n`;
            report += `- Phishing Awareness: ${scores.phishing}%\n`;
            report += `- Device Security: ${scores.device}%\n`;
            report += `- Network Security: ${scores.network}%\n\n`;
            report += `Compliance Framework: NIST SP 800-53, ISO 27001, PCI DSS\n`;
            
            const blob = new Blob([report], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `compliance_report_${vendor.name}_${new Date().toISOString()}.txt`;
            a.click();
            URL.revokeObjectURL(url);
        }
        
        function scheduleRemediation() {
            alert('Remediation scheduling feature - would open calendar for remediation planning');
        }
        
        function showComplianceDetails(framework) {
            const modalBody = `
                <div>
                    <h4>${framework} Compliance Requirements</h4>
                    <p>Detailed requirements and implementation guidance for ${framework} standard.</p>
                    <button class="btn btn-primary" onclick="closeModal()">Close</button>
                </div>
            `;
            document.getElementById('modal-body').innerHTML = modalBody;
            document.getElementById('modal-overlay').classList.remove('hidden');
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
            loadCompliance();
        });
    </script>
    <script src="dashboard.js?v=<?php echo time(); ?>"></script>
</body>
</html>