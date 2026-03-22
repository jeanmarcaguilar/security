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

// Assessment questions database
$questions = [
    ['id' => 1, 'category' => 'password', 'text' => 'Do you use a password manager to store and generate strong passwords?', 'options' => ['Yes, always' => 100, 'Sometimes' => 50, 'No, I remember them' => 25, 'I use the same password everywhere' => 0]],
    ['id' => 2, 'category' => 'password', 'text' => 'How often do you change your passwords?', 'options' => ['Every 30 days' => 100, 'Every 90 days' => 75, 'Every 6 months' => 50, 'Only when forced' => 25, 'Never' => 0]],
    ['id' => 3, 'category' => 'password', 'text' => 'Do you use multi-factor authentication (MFA) on your important accounts?', 'options' => ['Yes, on all accounts' => 100, 'On most accounts' => 75, 'On a few accounts' => 50, 'No' => 0]],
    ['id' => 4, 'category' => 'phishing', 'text' => 'How do you verify suspicious emails asking for credentials?', 'options' => ['Contact sender through known channel' => 100, 'Check email headers' => 75, 'Look for spelling errors' => 50, 'Click links to verify' => 25, 'I always trust emails' => 0]],
    ['id' => 5, 'category' => 'phishing', 'text' => 'Have you completed security awareness training in the past year?', 'options' => ['Yes, with certification' => 100, 'Yes, online course' => 75, 'Only watched videos' => 50, 'No training' => 0]],
    ['id' => 6, 'category' => 'phishing', 'text' => 'What do you do when you receive an unexpected attachment?', 'options' => ['Verify with sender before opening' => 100, 'Scan with antivirus' => 75, 'Open if it looks legitimate' => 25, 'Always open' => 0]],
    ['id' => 7, 'category' => 'device', 'text' => 'Is your device protected with antivirus/anti-malware software?', 'options' => ['Yes, always updated' => 100, 'Yes, but not always updated' => 50, 'No antivirus' => 0]],
    ['id' => 8, 'category' => 'device', 'text' => 'Do you lock your device when away from it?', 'options' => ['Always immediately' => 100, 'Sometimes' => 50, 'Never' => 0]],
    ['id' => 9, 'category' => 'device', 'text' => 'How often do you update your operating system and applications?', 'options' => ['Automatically updated' => 100, 'Weekly manual checks' => 75, 'Monthly' => 50, 'When reminded' => 25, 'Never' => 0]],
    ['id' => 10, 'category' => 'network', 'text' => 'Do you use a VPN when connecting to public Wi-Fi?', 'options' => ['Always' => 100, 'Sometimes' => 50, 'Never' => 0]],
    ['id' => 11, 'category' => 'network', 'text' => 'Is your home Wi-Fi secured with WPA2/WPA3 encryption?', 'options' => ['Yes, with strong password' => 100, 'Yes, default password' => 50, 'No encryption' => 0, 'Not sure' => 25]],
    ['id' => 12, 'category' => 'network', 'text' => 'Do you have a firewall enabled on your network/devices?', 'options' => ['Yes, hardware and software' => 100, 'Software only' => 75, 'Hardware only' => 50, 'No firewall' => 0]]
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Assessment - CyberShield</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .assessment-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .question-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-2);
            transition: all 0.3s ease;
        }
        .question-number {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        .question-text {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            line-height: 1.4;
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
        
        .options-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .option-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            border-radius: 12px;
            background: var(--navy-3);
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
        }
        .option-item:hover {
            background: var(--navy-2);
            transform: translateX(4px);
        }
        .option-item.selected {
            border-color: var(--primary);
            background: rgba(59, 130, 246, 0.1);
        }
        .option-radio {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--text-3);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .option-item.selected .option-radio {
            border-color: var(--primary);
        }
        .option-item.selected .option-radio::after {
            content: '';
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary);
        }
        .option-text {
            flex: 1;
            font-size: 0.9rem;
        }
        .option-score {
            font-size: 0.75rem;
            color: var(--text-3);
        }
        
        .progress-section {
            position: sticky;
            top: 20px;
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-2);
        }
        .progress-bar-container {
            background: var(--navy-3);
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin: 0.5rem 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), #3b82f6);
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        .nav-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }
        .timer {
            font-family: monospace;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
        }
        .warning-timer {
            color: #ef4444;
            animation: pulse 1s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .results-preview {
            background: var(--navy-3);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div id="app">
        <div id="sidebar">
            <div class="sb-brand">
                <div class="shield">🛡️</div>
                <div class="sb-brand-text"><h2>CyberShield</h2><span>Assessment</span></div>
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
                    <h2>Security Assessment</h2>
                    <p>Evaluate your cybersecurity hygiene across key domains</p>
                </div>
                <div class="topbar-right">
                    <button class="theme-toggle" onclick="toggleTheme()">🌙</button>
                </div>
            </div>
            
            <div class="content">
                <div class="assessment-container">
                    <!-- Progress Section -->
                    <div class="progress-section">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong>Question <span id="current-q-num">1</span> of <span id="total-q"><?php echo count($questions); ?></span></strong>
                                <div class="progress-bar-container">
                                    <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
                                </div>
                            </div>
                            <div class="timer" id="timer">00:30</div>
                        </div>
                    </div>
                    
                    <!-- Questions Container -->
                    <div id="questions-container"></div>
                    
                    <!-- Navigation Buttons -->
                    <div class="nav-buttons">
                        <button class="btn btn-secondary" id="prev-btn" onclick="prevQuestion()" disabled>← Previous</button>
                        <button class="btn btn-primary" id="next-btn" onclick="nextQuestion()">Next →</button>
                        <button class="btn btn-success" id="submit-btn" onclick="submitAssessment()" style="display: none;">Submit Assessment</button>
                    </div>
                    
                    <!-- Live Score Preview -->
                    <div class="results-preview" id="score-preview" style="display: none;">
                        <h4>Current Score Preview</h4>
                        <div style="font-size: 2rem; font-weight: 700; color: var(--primary);" id="preview-score">0%</div>
                        <div id="preview-rank"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div id="modal-overlay" class="modal-overlay hidden" onclick="closeModal(event)">
        <div class="modal modal-sm" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3>Confirm Submission</h3>
                <button class="modal-close" onclick="closeModal()">✕</button>
            </div>
            <div id="modal-body">
                <p>Are you sure you want to submit this assessment?</p>
                <p style="font-size: 0.85rem; color: var(--text-3);">You cannot change your answers after submission.</p>
                <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                    <button class="btn btn-primary" onclick="confirmSubmit()">Yes, Submit</button>
                    <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const questions = <?php echo json_encode($questions); ?>;
        let userAnswers = new Array(questions.length).fill(null);
        let currentQuestion = 0;
        let timerInterval = null;
        let timeLeft = 30;
        let timerEnabled = true;
        
        function getCategoryClass(category) {
            return `category-${category}`;
        }
        
        function renderQuestion(index) {
            const q = questions[index];
            const selectedValue = userAnswers[index];
            
            let optionsHtml = '';
            for (const [text, score] of Object.entries(q.options)) {
                const isSelected = selectedValue === score;
                optionsHtml += `
                    <div class="option-item ${isSelected ? 'selected' : ''}" onclick="selectAnswer(${index}, ${score}, '${text.replace(/'/g, "\\'")}')">
                        <div class="option-radio"></div>
                        <div class="option-text">${escapeHtml(text)}</div>
                        <div class="option-score">${score}%</div>
                    </div>
                `;
            }
            
            const html = `
                <div class="question-card">
                    <div class="category-badge ${getCategoryClass(q.category)}">${q.category.toUpperCase()}</div>
                    <div class="question-number">Question ${index + 1} of ${questions.length}</div>
                    <div class="question-text">${escapeHtml(q.text)}</div>
                    <div class="options-list">
                        ${optionsHtml}
                    </div>
                </div>
            `;
            
            document.getElementById('questions-container').innerHTML = html;
            document.getElementById('current-q-num').textContent = index + 1;
            
            // Update progress bar
            const progress = ((index + 1) / questions.length) * 100;
            document.getElementById('progress-fill').style.width = `${progress}%`;
            
            // Update navigation buttons
            document.getElementById('prev-btn').disabled = index === 0;
            
            if (index === questions.length - 1) {
                document.getElementById('next-btn').style.display = 'none';
                document.getElementById('submit-btn').style.display = 'inline-flex';
            } else {
                document.getElementById('next-btn').style.display = 'inline-flex';
                document.getElementById('submit-btn').style.display = 'none';
            }
            
            // Reset and start timer
            resetTimer();
            if (timerEnabled && !userAnswers[index]) {
                startTimer();
            }
        }
        
        function selectAnswer(questionIndex, score, text) {
            userAnswers[questionIndex] = score;
            renderQuestion(currentQuestion);
            
            // Auto-advance if timer is enabled and answer selected
            if (timerEnabled && currentQuestion < questions.length - 1) {
                setTimeout(() => nextQuestion(), 300);
            }
            
            updateScorePreview();
        }
        
        function nextQuestion() {
            if (currentQuestion < questions.length - 1) {
                currentQuestion++;
                renderQuestion(currentQuestion);
            }
        }
        
        function prevQuestion() {
            if (currentQuestion > 0) {
                currentQuestion--;
                renderQuestion(currentQuestion);
            }
        }
        
        function resetTimer() {
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
            timeLeft = 30;
            updateTimerDisplay();
        }
        
        function startTimer() {
            if (timerInterval) clearInterval(timerInterval);
            timerInterval = setInterval(() => {
                if (timeLeft <= 1) {
                    clearInterval(timerInterval);
                    timerInterval = null;
                    // Auto-select default answer (first option) if none selected
                    if (userAnswers[currentQuestion] === null) {
                        const firstOption = Object.values(questions[currentQuestion].options)[0];
                        selectAnswer(currentQuestion, firstOption, Object.keys(questions[currentQuestion].options)[0]);
                    }
                } else {
                    timeLeft--;
                    updateTimerDisplay();
                }
            }, 1000);
        }
        
        function updateTimerDisplay() {
            const timerEl = document.getElementById('timer');
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerEl.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 10) {
                timerEl.classList.add('warning-timer');
            } else {
                timerEl.classList.remove('warning-timer');
            }
        }
        
        function calculateScore() {
            let totalScore = 0;
            let answeredCount = 0;
            
            for (let i = 0; i < userAnswers.length; i++) {
                if (userAnswers[i] !== null) {
                    totalScore += userAnswers[i];
                    answeredCount++;
                }
            }
            
            if (answeredCount === 0) return 0;
            return Math.round(totalScore / answeredCount);
        }
        
        function calculateCategoryScores() {
            const categories = {
                password: { total: 0, count: 0 },
                phishing: { total: 0, count: 0 },
                device: { total: 0, count: 0 },
                network: { total: 0, count: 0 }
            };
            
            for (let i = 0; i < questions.length; i++) {
                const q = questions[i];
                const answer = userAnswers[i];
                if (answer !== null) {
                    categories[q.category].total += answer;
                    categories[q.category].count++;
                }
            }
            
            return {
                password: categories.password.count ? Math.round(categories.password.total / categories.password.count) : 0,
                phishing: categories.phishing.count ? Math.round(categories.phishing.total / categories.phishing.count) : 0,
                device: categories.device.count ? Math.round(categories.device.total / categories.device.count) : 0,
                network: categories.network.count ? Math.round(categories.network.total / categories.network.count) : 0
            };
        }
        
        function getRank(score) {
            if (score >= 80) return { letter: 'A', text: 'Low Risk - Excellent security practices', color: '#10b981' };
            if (score >= 60) return { letter: 'B', text: 'Moderate Risk - Good foundation, room for improvement', color: '#3b82f6' };
            if (score >= 40) return { letter: 'C', text: 'High Risk - Significant improvements needed', color: '#f59e0b' };
            return { letter: 'D', text: 'Critical Risk - Immediate action required', color: '#ef4444' };
        }
        
        function updateScorePreview() {
            const score = calculateScore();
            const rank = getRank(score);
            const preview = document.getElementById('score-preview');
            const previewScore = document.getElementById('preview-score');
            const previewRank = document.getElementById('preview-rank');
            
            if (userAnswers.some(a => a !== null)) {
                preview.style.display = 'block';
                previewScore.textContent = `${score}%`;
                previewRank.innerHTML = `<span class="rank-badge rank-${rank.letter.toLowerCase()}">${rank.letter}</span> - ${rank.text}`;
            }
        }
        
        function submitAssessment() {
            // Check if all questions are answered
            const unanswered = userAnswers.filter(a => a === null).length;
            if (unanswered > 0) {
                alert(`Please answer all questions. ${unanswered} question(s) remaining.`);
                // Jump to first unanswered question
                const firstUnanswered = userAnswers.findIndex(a => a === null);
                if (firstUnanswered !== -1) {
                    currentQuestion = firstUnanswered;
                    renderQuestion(currentQuestion);
                }
                return;
            }
            
            document.getElementById('modal-overlay').classList.remove('hidden');
        }
        
        async function confirmSubmit() {
            closeModal();
            
            const score = calculateScore();
            const rank = getRank(score);
            const categoryScores = calculateCategoryScores();
            
            // Save to database via API
            const assessmentData = {
                vendor_id: 0, // Will be auto-linked
                score: score,
                rank: rank.letter,
                password_score: categoryScores.password,
                phishing_score: categoryScores.phishing,
                device_score: categoryScores.device,
                network_score: categoryScores.network,
                assessment_notes: `Completed on ${new Date().toLocaleString()}`
            };
            
            try {
                const response = await fetch('api/save_assessment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(assessmentData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Store results in localStorage for results page
                    localStorage.setItem('lastAssessment', JSON.stringify({
                        score: score,
                        rank: rank.letter,
                        categoryScores: categoryScores,
                        answers: userAnswers,
                        date: new Date().toISOString()
                    }));
                    
                    // Redirect to results page
                    window.location.href = 'results.php';
                } else {
                    alert('Error saving assessment: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error saving assessment. Please try again.');
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
        
        // Initialize on load
        document.addEventListener('DOMContentLoaded', () => {
            renderQuestion(0);
        });
    </script>
</body>
</html>