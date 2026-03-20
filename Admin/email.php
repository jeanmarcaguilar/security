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
$vendors_query = "SELECT id, name, email FROM vendors ORDER BY name";
$stmt = $db->prepare($vendors_query);
$stmt->execute();
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get latest assessments
$assessments_query = "SELECT va.*, v.name as vendor_name, v.email as vendor_email
    FROM vendor_assessments va 
    JOIN vendors v ON va.vendor_id = v.id 
    WHERE va.id IN (SELECT MAX(id) FROM vendor_assessments GROUP BY vendor_id)";
$stmt = $db->prepare($assessments_query);
$stmt->execute();
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get sent email log (simulated)
$emailLog = [
    ['id' => 1, 'vendor' => 'Tech Solutions Inc', 'recipient' => 'security@techsolutions.com', 'subject' => 'Risk Assessment Report', 'sent_at' => date('Y-m-d H:i:s', strtotime('-2 days'))],
    ['id' => 2, 'vendor' => 'Global Services', 'recipient' => 'compliance@globalservices.com', 'subject' => 'Security Compliance Report', 'sent_at' => date('Y-m-d H:i:s', strtotime('-1 week'))],
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Reports - CyberShield</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .email-preview {
            background: var(--navy-3);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1rem;
            border: 1px solid var(--border-2);
        }
        .email-subject {
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-family: var(--font);
        }
        .email-body {
            line-height: 1.6;
            font-size: 0.9rem;
            font-family: var(--font);
        }
        .score-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.75rem;
            font-family: var(--mono);
            letter-spacing: 0.5px;
        }
        .score-high {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        .score-moderate {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }
        .score-good {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        .template-selector {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .template-btn {
            padding: 0.5rem 1rem;
            background: var(--navy-3);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            font-family: var(--font);
            font-size: 0.85rem;
        }
        .template-btn.active {
            background: var(--primary);
            color: white;
        }
        .sent-log-item {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border-2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .log-details {
            flex: 1;
            font-family: var(--font);
            font-size: 0.85rem;
        }
        .log-time {
            font-size: 0.7rem;
            color: var(--text-3);
            font-family: var(--mono);
            letter-spacing: 0.5px;
        }
        .log-actions button {
            margin-left: 0.5rem;
        }
        .attachment-preview {
            margin-top: 1rem;
            padding: 0.5rem;
            background: var(--card-bg);
            border-radius: 8px;
            font-size: 0.8rem;
            font-family: var(--font);
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
        .modal-body p {
            font-family: var(--font);
            font-size: 0.9rem;
            line-height: 1.6;
        }
        .filter-select {
            font-family: var(--font);
        }
        .form-group label {
            font-family: var(--font);
            font-size: 0.85rem;
            font-weight: 500;
        }
        div[style*="font-size: 0.9rem"] {
            font-family: var(--font);
            line-height: 1.6;
        }
        div[style*="font-size: 0.85rem"] {
            font-family: var(--font);
        }
        div[style*="font-size: 0.7rem"] {
            font-family: var(--font);
        }
        strong[style*="color: #ef4444"] {
            font-family: var(--font);
        }
        strong[style*="color: #f59e0b"] {
            font-family: var(--font);
        }
        ul {
            font-family: var(--font);
            font-size: 0.85rem;
            line-height: 1.5;
        }
        em {
            font-family: var(--font);
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div id="app">
        <div id="sidebar">
            <div class="sb-brand">
                <div class="shield">🛡️</div>
                <div class="sb-brand-text"><h2>CyberShield</h2><span>Email Reports</span></div>
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
                <a class="sb-item active" href="email.php"><span class="sb-icon">📧</span><span class="sb-text">Email Report</span></a>
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
                    <h2>Email Reports</h2>
                    <p>Send risk assessment reports to vendors</p>
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
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <!-- Compose Email -->
                    <div class="card">
                        <h3>Compose Email</h3>
                        <div class="template-selector">
                            <span class="template-btn active" data-template="standard" onclick="selectTemplate('standard')">Standard Report</span>
                            <span class="template-btn" data-template="urgent" onclick="selectTemplate('urgent')">⚠️ Urgent Notice</span>
                            <span class="template-btn" data-template="compliance" onclick="selectTemplate('compliance')">Compliance Review</span>
                        </div>
                        
                        <div class="form-group">
                            <label>Vendor</label>
                            <select id="vendor-select" class="filter-select" style="width:100%" onchange="updateEmailPreview()">
                                <?php foreach($vendors as $vendor): ?>
                                <option value="<?php echo $vendor['id']; ?>" data-email="<?php echo $vendor['email']; ?>"><?php echo htmlspecialchars($vendor['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Recipient Email</label>
                            <input type="email" id="recipient-email" class="filter-select" style="width:100%" onchange="updateEmailPreview()">
                        </div>
                        
                        <div class="form-group">
                            <label>Subject</label>
                            <input type="text" id="email-subject" class="filter-select" style="width:100%" onchange="updateEmailPreview()">
                        </div>
                        
                        <div class="form-group">
                            <label>Additional Message</label>
                            <textarea id="additional-message" rows="4" class="filter-select" style="width:100%" placeholder="Add a personal message to the vendor..." onchange="updateEmailPreview()"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Attach Report</label>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <input type="checkbox" id="attach-pdf" checked> <label>Attach PDF Report</label>
                                <input type="checkbox" id="attach-csv"> <label>Attach CSV Data</label>
                            </div>
                        </div>
                        
                        <button class="btn btn-primary" style="width:100%; justify-content: center;" onclick="sendEmail()">
                            📧 Send Report
                        </button>
                    </div>
                    
                    <!-- Email Preview -->
                    <div class="card">
                        <h3>Email Preview</h3>
                        <div id="email-preview" class="email-preview"></div>
                        <div id="attachment-preview" class="attachment-preview"></div>
                    </div>
                </div>
                
                <!-- Sent Log -->
                <div class="card" style="margin-top: 1.5rem;">
                    <div class="table-toolbar">
                        <h3>Sent Reports Log</h3>
                        <button class="btn btn-secondary btn-sm" onclick="clearLog()">Clear Log</button>
                    </div>
                    <div id="sent-log"></div>
                </div>
            </div>
        </div>
    </div>
    
    <div id="modal-overlay" class="modal-overlay hidden" onclick="closeModal(event)">
        <div class="modal modal-sm" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3>Email Sent</h3>
                <button class="modal-close" onclick="closeModal()">✕</button>
            </div>
            <div id="modal-body">
                <p>Your report has been sent successfully!</p>
                <button class="btn btn-primary" onclick="closeModal()">OK</button>
            </div>
        </div>
    </div>
    
    <script>
        const vendors = <?php echo json_encode($vendors); ?>;
        const assessments = <?php echo json_encode($assessments); ?>;
        let sentEmails = <?php echo json_encode($emailLog); ?>;
        let currentTemplate = 'standard';
        
        function getVendorAssessment(vendorId) {
            return assessments.find(a => a.vendor_id == vendorId);
        }
        
        function getScoreClass(score) {
            if (score < 50) return 'score-high';
            if (score < 80) return 'score-moderate';
            return 'score-good';
        }
        
        function generateEmailBody(template, vendorName, assessment, additionalMessage) {
            const score = assessment?.score || 'N/A';
            const rank = assessment?.rank || 'N/A';
            const passwordScore = assessment?.password_score || 'N/A';
            const phishingScore = assessment?.phishing_score || 'N/A';
            const deviceScore = assessment?.device_score || 'N/A';
            const networkScore = assessment?.network_score || 'N/A';
            
            let body = '';
            
            if (template === 'standard') {
                body = `
                    <p>Dear ${vendorName},</p>
                    <p>Please find your latest cybersecurity risk assessment results below:</p>
                    <div style="background: var(--navy-3); padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                        <strong>Overall Security Score: ${score}%</strong> (Rank: ${rank})<br>
                        <strong>Category Breakdown:</strong><br>
                        • Password Security: ${passwordScore}%<br>
                        • Phishing Awareness: ${phishingScore}%<br>
                        • Device Security: ${deviceScore}%<br>
                        • Network Security: ${networkScore}%
                    </div>
                `;
            } else if (template === 'urgent') {
                body = `
                    <p><strong style="color: #ef4444;">⚠️ URGENT SECURITY NOTICE</strong></p>
                    <p>Dear ${vendorName},</p>
                    <p>Your organization has been identified as <strong style="color: #ef4444;">HIGH RISK</strong> with a security score of <strong>${score}%</strong>.</p>
                    <p>Immediate action is required to address the following critical vulnerabilities:</p>
                    <ul>
                        ${passwordScore < 50 ? '<li>Password security requires immediate improvement</li>' : ''}
                        ${phishingScore < 50 ? '<li>Phishing awareness training needed</li>' : ''}
                        ${deviceScore < 50 ? '<li>Device security controls inadequate</li>' : ''}
                        ${networkScore < 50 ? '<li>Network security vulnerabilities detected</li>' : ''}
                    </ul>
                    <p>Please review the attached detailed report and implement corrective measures within 7 days.</p>
                `;
            } else if (template === 'compliance') {
                body = `
                    <p>Dear ${vendorName},</p>
                    <p>This is a compliance notification regarding your organization's security posture.</p>
                    <p><strong>Current Compliance Status:</strong> ${score >= 80 ? 'Compliant' : (score >= 60 ? 'Partially Compliant' : 'Non-Compliant')}</p>
                    <p><strong>Overall Score:</strong> ${score}%</p>
                    <p>Please review the compliance checklist in the attached report and address any gaps identified.</p>
                    <p>Required actions must be completed within the timeframe specified in your service agreement.</p>
                `;
            }
            
            if (additionalMessage) {
                body += `<div style="margin-top: 1rem; padding: 1rem; background: var(--card-bg); border-left: 3px solid var(--primary);">
                    <strong>Note from Admin:</strong><br>${additionalMessage}
                </div>`;
            }
            
            body += `<p style="margin-top: 1rem;">Thank you,<br><strong>CyberShield Security Team</strong></p>`;
            
            return body;
        }
        
        function updateEmailPreview() {
            const vendorId = document.getElementById('vendor-select').value;
            const vendor = vendors.find(v => v.id == vendorId);
            const assessment = getVendorAssessment(vendorId);
            const additionalMessage = document.getElementById('additional-message').value;
            
            const subject = document.getElementById('email-subject').value;
            const body = generateEmailBody(currentTemplate, vendor.name, assessment, additionalMessage);
            
            document.getElementById('email-preview').innerHTML = `
                <div class="email-subject">Subject: ${escapeHtml(subject)}</div>
                <div class="email-body">${body}</div>
            `;
            
            // Update recipient email
            const vendorEmail = vendor.email;
            document.getElementById('recipient-email').value = vendorEmail;
            
            // Update attachment preview
            const attachPDF = document.getElementById('attach-pdf').checked;
            const attachCSV = document.getElementById('attach-csv').checked;
            let attachments = [];
            if (attachPDF) attachments.push('Risk_Assessment_Report.pdf');
            if (attachCSV) attachments.push('Assessment_Data.csv');
            
            document.getElementById('attachment-preview').innerHTML = attachments.length ? 
                `<strong>Attachments:</strong> ${attachments.join(', ')}` : 
                '<em>No attachments selected</em>';
        }
        
        function selectTemplate(template) {
            currentTemplate = template;
            
            // Update active template button
            document.querySelectorAll('.template-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.template === template) {
                    btn.classList.add('active');
                }
            });
            
            // Update subject based on template
            const subjects = {
                'standard': 'CyberShield Risk Assessment Report',
                'urgent': 'URGENT: Security Risk Alert - Immediate Action Required',
                'compliance': 'Compliance Review: Security Assessment Results'
            };
            document.getElementById('email-subject').value = subjects[template];
            
            updateEmailPreview();
        }
        
        function sendEmail() {
            const vendorId = document.getElementById('vendor-select').value;
            const vendor = vendors.find(v => v.id == vendorId);
            const recipient = document.getElementById('recipient-email').value;
            const subject = document.getElementById('email-subject').value;
            const additionalMessage = document.getElementById('additional-message').value;
            const attachPDF = document.getElementById('attach-pdf').checked;
            const attachCSV = document.getElementById('attach-csv').checked;
            
            if (!recipient) {
                alert('Please enter a recipient email address');
                return;
            }
            
            // Simulate sending email
            const sentEmail = {
                id: sentEmails.length + 1,
                vendor: vendor.name,
                recipient: recipient,
                subject: subject,
                sent_at: new Date().toISOString(),
                attachments: []
            };
            if (attachPDF) sentEmail.attachments.push('PDF');
            if (attachCSV) sentEmail.attachments.push('CSV');
            
            sentEmails.unshift(sentEmail);
            renderSentLog();
            
            // Show success modal
            document.getElementById('modal-overlay').classList.remove('hidden');
            
            // Clear additional message
            document.getElementById('additional-message').value = '';
            updateEmailPreview();
        }
        
        function renderSentLog() {
            if (sentEmails.length === 0) {
                document.getElementById('sent-log').innerHTML = '<p style="text-align: center; padding: 2rem; color: var(--text-3);">No reports sent yet.</p>';
                return;
            }
            
            let html = '';
            sentEmails.forEach(email => {
                html += `
                    <div class="sent-log-item">
                        <div class="log-details">
                            <strong>${escapeHtml(email.vendor)}</strong><br>
                            <span style="font-size: 0.85rem;">To: ${escapeHtml(email.recipient)}</span><br>
                            <span class="log-time">${new Date(email.sent_at).toLocaleString()}</span>
                        </div>
                        <div class="log-actions">
                            <button class="btn btn-xs btn-secondary" onclick="resendEmail(${email.id})">Resend</button>
                            <button class="btn btn-xs btn-danger" onclick="deleteLogEntry(${email.id})">Delete</button>
                        </div>
                    </div>
                `;
            });
            document.getElementById('sent-log').innerHTML = html;
        }
        
        function resendEmail(id) {
            const email = sentEmails.find(e => e.id === id);
            if (email) {
                alert(`Resending report to ${email.recipient}`);
            }
        }
        
        function deleteLogEntry(id) {
            sentEmails = sentEmails.filter(e => e.id !== id);
            renderSentLog();
        }
        
        function clearLog() {
            if (confirm('Clear all sent email logs?')) {
                sentEmails = [];
                renderSentLog();
            }
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
            selectTemplate('standard');
            renderSentLog();
        });
    </script>
    <script src="dashboard.js?v=<?php echo time(); ?>"></script>
</body>
</html>