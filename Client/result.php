<?php
session_start();
require_once '../includes/config.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    } else {
        header('Location: ../login.php');
        exit();
    }
}

// Check if this is a POST request (from assessment submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        $user_id = $_SESSION['user_id'];
        $score = isset($_POST['score']) ? intval($_POST['score']) : 0;
        $rank = isset($_POST['rank']) ? $_POST['rank'] : 'D';
        $password_score = isset($_POST['password_score']) ? intval($_POST['password_score']) : 0;
        $phishing_score = isset($_POST['phishing_score']) ? intval($_POST['phishing_score']) : 0;
        $device_score = isset($_POST['device_score']) ? intval($_POST['device_score']) : 0;
        $network_score = isset($_POST['network_score']) ? intval($_POST['network_score']) : 0;
        $social_engineering_score = isset($_POST['social_engineering_score']) ? intval($_POST['social_engineering_score']) : 0;
        $data_handling_score = isset($_POST['data_handling_score']) ? intval($_POST['data_handling_score']) : 0;
        $time_spent = isset($_POST['time_spent']) ? intval($_POST['time_spent']) : 0;
        $questions_answered = isset($_POST['questions_answered']) ? intval($_POST['questions_answered']) : 0;
        $total_questions = isset($_POST['total_questions']) ? intval($_POST['total_questions']) : 20;
        $assessment_date = isset($_POST['assessment_date']) ? $_POST['assessment_date'] : date('Y-m-d H:i:s');
        $session_seed = isset($_POST['session_seed']) ? $_POST['session_seed'] : null;
        $assessment_token = isset($_POST['assessment_token']) ? $_POST['assessment_token'] : 'ASSESS_' . md5(uniqid() . $user_id . time());
        
        $query = "INSERT INTO assessments (
            vendor_id, score, rank, password_score, phishing_score, 
            device_score, network_score, social_engineering_score, 
            data_handling_score, time_spent, questions_answered, total_questions,
            assessment_date, assessment_token, session_seed
        ) VALUES (
            :vendor_id, :score, :rank, :password_score, :phishing_score,
            :device_score, :network_score, :social_engineering_score,
            :data_handling_score, :time_spent, :questions_answered, :total_questions,
            :assessment_date, :assessment_token, :session_seed
        )";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':vendor_id', $user_id);
        $stmt->bindParam(':score', $score);
        $stmt->bindParam(':rank', $rank);
        $stmt->bindParam(':password_score', $password_score);
        $stmt->bindParam(':phishing_score', $phishing_score);
        $stmt->bindParam(':device_score', $device_score);
        $stmt->bindParam(':network_score', $network_score);
        $stmt->bindParam(':social_engineering_score', $social_engineering_score);
        $stmt->bindParam(':data_handling_score', $data_handling_score);
        $stmt->bindParam(':time_spent', $time_spent);
        $stmt->bindParam(':questions_answered', $questions_answered);
        $stmt->bindParam(':total_questions', $total_questions);
        $stmt->bindParam(':assessment_date', $assessment_date);
        $stmt->bindParam(':assessment_token', $assessment_token);
        $stmt->bindParam(':session_seed', $session_seed);
        
        if ($stmt->execute()) {
            $assessment_id = $db->lastInsertId();
            
            if (isset($_POST['answers']) && is_array($_POST['answers'])) {
                $answer_query = "INSERT INTO assessment_answers (assessment_id, question_id, user_answer, is_correct) VALUES (:assessment_id, :question_id, :user_answer, :is_correct)";
                $answer_stmt = $db->prepare($answer_query);
                
                foreach ($_POST['answers'] as $answer_data) {
                    $answer_stmt->bindParam(':assessment_id', $assessment_id);
                    $answer_stmt->bindParam(':question_id', $answer_data['question_id']);
                    $answer_stmt->bindParam(':user_answer', $answer_data['user_answer']);
                    $answer_stmt->bindParam(':is_correct', $answer_data['is_correct']);
                    $answer_stmt->execute();
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Assessment saved successfully',
                'assessment_id' => $assessment_id,
                'redirect' => 'result.php'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to save assessment']);
        }
        
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// If this is a GET request, display the results page
$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Get latest assessment
$query = "SELECT * FROM assessments WHERE vendor_id = :vendor_id ORDER BY created_at DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':vendor_id', $user_id);
$stmt->execute();
$latest_assessment = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user info
$user_query = "SELECT * FROM users WHERE id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $user_id);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

$initial = strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 1));

// If no assessment found, show no assessment message
if (!$latest_assessment) {
    $no_assessment = true;
    $score = 0;
    $rank = 'D';
    $categoryScores = [
        'password' => 0,
        'phishing' => 0,
        'device' => 0,
        'network' => 0,
        'social_engineering' => 0,
        'data_handling' => 0,
    ];
    $incorrectAnswers = [];
    $aiInsights = null;
} else {
    $no_assessment = false;
    $score = $latest_assessment['score'];
    $rank = $latest_assessment['rank'];
    
    // Fetch incorrect answers for AI analysis
    $incorrectAnswers = [];
    try {
        $incorrectQuery = "
            SELECT 
                aa.*,
                qb.question_text,
                qb.correct_answer,
                qb.explanation,
                qb.category,
                qb.difficulty
            FROM assessment_answers aa
            JOIN question_bank qb ON aa.question_id = qb.id
            WHERE aa.assessment_id = :assessment_id AND aa.is_correct = 0
            ORDER BY qb.category
        ";
        $incorrectStmt = $db->prepare($incorrectQuery);
        $incorrectStmt->bindParam(':assessment_id', $latest_assessment['id']);
        $incorrectStmt->execute();
        $incorrectAnswers = $incorrectStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching incorrect answers: " . $e->getMessage());
    }
    
    $categoryScores = [
        'password' => $latest_assessment['password_score'] ?? 0,
        'phishing' => $latest_assessment['phishing_score'] ?? 0,
        'device' => $latest_assessment['device_score'] ?? 0,
        'network' => $latest_assessment['network_score'] ?? 0,
        'social_engineering' => $latest_assessment['social_engineering_score'] ?? 0,
        'data_handling' => $latest_assessment['data_handling_score'] ?? 0,
    ];
    
    // Get AI Insights from OpenAI
    $aiInsights = getAIInsights($score, $categoryScores, $incorrectAnswers, $user);
}

function getRankDetails($score, $rank) {
    if ($score >= 80) {
        return [
            'letter' => 'A',
            'title' => 'Excellent!',
            'description' => 'You have demonstrated strong cybersecurity awareness. Keep up the great work!',
            'color' => '#10D982',
            'bg' => 'rgba(16,217,130,0.1)',
            'icon' => '🏆',
            'advice' => 'Share your knowledge with colleagues and stay updated on new threats.'
        ];
    } elseif ($score >= 60) {
        return [
            'letter' => 'B',
            'title' => 'Good Job!',
            'description' => 'You have a solid foundation in security practices. There\'s room for improvement.',
            'color' => '#3B8BFF',
            'bg' => 'rgba(59,139,255,0.1)',
            'icon' => '👍',
            'advice' => 'Review areas where you scored lower and take our security tips course.'
        ];
    } elseif ($score >= 40) {
        return [
            'letter' => 'C',
            'title' => 'Needs Improvement',
            'description' => 'Your security awareness needs attention. Consider reviewing basic security practices.',
            'color' => '#F5B731',
            'bg' => 'rgba(245,183,49,0.1)',
            'icon' => '⚠️',
            'advice' => 'We recommend taking our security awareness training and retaking the assessment.'
        ];
    } else {
        return [
            'letter' => 'D',
            'title' => 'Critical Risk',
            'description' => 'Immediate action required. Your security knowledge needs significant improvement.',
            'color' => '#FF3B5C',
            'bg' => 'rgba(255,59,92,0.1)',
            'icon' => '🚨',
            'advice' => 'Mandatory security training is recommended. Contact IT support for resources.'
        ];
    }
}

/**
 * Get AI-powered insights using Anthropic Claude API
 * The risk_score is ALWAYS calculated dynamically from real scores — never hardcoded.
 */
function getAIInsights($score, $categoryScores, $incorrectAnswers, $user) {
    // ── Always calculate dynamic risk score FIRST ─────────────────────────────
    $riskScore = calculateDynamicRiskScore($score, $categoryScores);

    if      ($riskScore >= 75) { $riskLevel = 'critical'; $riskColor = '#FF3B5C'; $riskIcon = '🚨'; }
    elseif  ($riskScore >= 55) { $riskLevel = 'high';     $riskColor = '#F5B731'; $riskIcon = '⚠️'; }
    elseif  ($riskScore >= 35) { $riskLevel = 'moderate'; $riskColor = '#3B8BFF'; $riskIcon = '📊'; }
    else                       { $riskLevel = 'low';      $riskColor = '#10D982'; $riskIcon = '🏆'; }

    // ── Check for Anthropic API key ───────────────────────────────────────────
    $apiKey = getenv('ANTHROPIC_API_KEY') ?: 'YOUR_ANTHROPIC_API_KEY_HERE';

    if ($apiKey === 'YOUR_ANTHROPIC_API_KEY_HERE' || empty(trim($apiKey))) {
        // No API key — use fully dynamic fallback (risk score is still real)
        return getFallbackAnalysis($score, $categoryScores, $incorrectAnswers);
    }

    // ── Prepare data for Claude ───────────────────────────────────────────────
    $categoryNames = [
        'password'           => 'Password Security',
        'phishing'           => 'Phishing Awareness',
        'device'             => 'Device Security',
        'network'            => 'Network Security',
        'social_engineering' => 'Social Engineering',
        'data_handling'      => 'Data Handling',
    ];

    $catLines = [];
    foreach ($categoryScores as $cat => $cs) {
        $status     = $cs >= 80 ? '✅' : ($cs >= 60 ? '⚠️' : '❌');
        $catLines[] = "- {$categoryNames[$cat]}: {$cs}% {$status}";
    }

    $missedSample = array_slice($incorrectAnswers, 0, 8);
    $missedLines  = array_map(fn($m) =>
        "• [{$m['category']}] " . substr($m['question_text'] ?? '', 0, 70) .
        " → Correct: " . substr($m['correct_answer'] ?? '', 0, 40),
        $missedSample
    );

    $userName  = htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Vendor');
    $perfLabel = $score >= 80 ? 'Excellent' : ($score >= 60 ? 'Good' : ($score >= 40 ? 'Needs Improvement' : 'Critical Risk'));

    $prompt = "You are CyberShield AI, a cybersecurity expert. Analyze this assessment and return ONLY a valid JSON object — no markdown, no explanation outside JSON.

VENDOR: {$userName}
OVERALL SCORE: {$score}% ({$perfLabel})
CALCULATED RISK SCORE: {$riskScore}/100 (Risk Level: {$riskLevel})
TOTAL WRONG: " . count($incorrectAnswers) . " out of 100 questions

CATEGORY BREAKDOWN:
" . implode("\n", $catLines) . "

MISSED QUESTIONS SAMPLE:
" . (empty($missedLines) ? "None available" : implode("\n", $missedLines)) . "

CRITICAL RULES:
- risk_score MUST be exactly {$riskScore} — do not change this value
- risk_level MUST be exactly \"{$riskLevel}\" — do not change this value  
- risk_color MUST be \"{$riskColor}\"
- risk_icon MUST be \"{$riskIcon}\"
- Be specific — reference actual category scores and percentages in your text
- executive_summary must mention their {$score}% score and {$riskScore}/100 risk

Return exactly this JSON structure:
{
  \"risk_level\": \"{$riskLevel}\",
  \"risk_score\": {$riskScore},
  \"risk_color\": \"{$riskColor}\",
  \"risk_icon\": \"{$riskIcon}\",
  \"executive_summary\": \"2-3 sentences referencing their actual {$score}% score and {$riskScore}/100 risk score with specific category percentages\",
  \"advice\": [
    \"Specific actionable advice referencing their actual weakest category score\",
    \"Second advice item\",
    \"Third advice item\",
    \"Fourth advice item\",
    \"Fifth advice item\"
  ],
  \"video_topics\": [\"Topic 1\", \"Topic 2\", \"Topic 3\", \"Topic 4\"],
  \"encouragement\": \"One motivational sentence appropriate for their {$score}% performance level\",
  \"chat_greeting\": \"One warm personalized greeting from CyberShield AI referencing their {$score}% score and {$riskLevel} risk level\"
}";

    try {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model'      => 'claude-sonnet-4-6',
                'max_tokens' => 1100,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) throw new Exception("cURL error: $curlErr");

        if ($httpCode === 200) {
            $result  = json_decode($response, true);
            $content = $result['content'][0]['text'] ?? '';

            // Extract JSON from Claude response
            if (preg_match('/\{[\s\S]*\}/m', $content, $match)) {
                $parsed = json_decode($match[0], true);
                if ($parsed) {
                    // FORCE the dynamic risk score — Claude must not override it
                    $parsed['risk_score'] = $riskScore;
                    $parsed['risk_level'] = $riskLevel;
                    $parsed['risk_color'] = $riskColor;
                    $parsed['risk_icon']  = $riskIcon;
                    $parsed['source']     = 'claude';
                    return $parsed;
                }
            }
        }

        error_log("Claude API error {$httpCode}: {$response}");
        return getFallbackAnalysis($score, $categoryScores, $incorrectAnswers);

    } catch (Exception $e) {
        error_log("Claude API exception: " . $e->getMessage());
        return getFallbackAnalysis($score, $categoryScores, $incorrectAnswers);
    }
}

/**
 * DYNAMIC RISK SCORE ENGINE
 * Calculates the real risk score from the actual assessment scores.
 * Each category is weighted by real-world attack vector frequency.
 * Score range: 1–99 (never exactly 0 or 100 — realistic)
 */
function calculateDynamicRiskScore($score, $categoryScores) {
    // Weights based on real-world attack vector frequency (must sum to 1.0)
    $weights = [
        'phishing'           => 0.22,  // #1 attack vector globally
        'password'           => 0.20,  // #1 breach cause
        'social_engineering' => 0.18,  // human layer is weakest
        'network'            => 0.16,  // network exposure
        'device'             => 0.14,  // endpoint risk
        'data_handling'      => 0.10,  // data mishandling
    ];

    // For each category: lower score = higher vulnerability
    $weightedRisk = 0.0;
    foreach ($weights as $cat => $weight) {
        $catScore    = isset($categoryScores[$cat]) ? (int)$categoryScores[$cat] : 0;
        $vulnerability = 100 - $catScore;  // invert: 0% score = 100% vulnerable
        $weightedRisk += $vulnerability * $weight;
    }

    // Apply multiplier based on overall score tier
    $multiplier = 1.0;
    if      ($score < 20)  $multiplier = 1.35;
    elseif  ($score < 40)  $multiplier = 1.20;
    elseif  ($score < 60)  $multiplier = 1.05;
    elseif  ($score < 80)  $multiplier = 0.95;
    elseif  ($score >= 90) $multiplier = 0.70;
    elseif  ($score >= 80) $multiplier = 0.82;

    $finalRisk = (int)round($weightedRisk * $multiplier);

    // Clamp between 1 and 99
    return max(1, min(99, $finalRisk));
}

function getFallbackAnalysis($score, $categoryScores, $incorrectAnswers) {
    // ── Calculate the REAL dynamic risk score from actual data ────────────────
    $riskScore = calculateDynamicRiskScore($score, $categoryScores);

    // ── Determine risk level from the real score (not hardcoded) ─────────────
    if      ($riskScore >= 75) { $riskLevel = 'critical'; $riskColor = '#FF3B5C'; $riskIcon = '🚨'; }
    elseif  ($riskScore >= 55) { $riskLevel = 'high';     $riskColor = '#F5B731'; $riskIcon = '⚠️'; }
    elseif  ($riskScore >= 35) { $riskLevel = 'moderate'; $riskColor = '#3B8BFF'; $riskIcon = '📊'; }
    else                       { $riskLevel = 'low';      $riskColor = '#10D982'; $riskIcon = '🏆'; }

    $categoryNames = [
        'password'           => 'Password Security',
        'phishing'           => 'Phishing Awareness',
        'device'             => 'Device Security',
        'network'            => 'Network Security',
        'social_engineering' => 'Social Engineering',
        'data_handling'      => 'Data Handling',
    ];

    // Sort categories weakest first for specific advice
    $sorted = $categoryScores;
    asort($sorted);
    $weakKeys   = array_keys($sorted);
    $weakNames  = array_map(fn($k) => $categoryNames[$k] ?? $k, array_slice($weakKeys, 0, 3));
    $weakName1  = $weakNames[0] ?? 'Unknown';
    $weakName2  = $weakNames[1] ?? 'Unknown';
    $weakScore1 = $categoryScores[$weakKeys[0] ?? 'phishing'] ?? 0;
    $weakScore2 = $categoryScores[$weakKeys[1] ?? 'password'] ?? 0;

    // ── Build dynamic executive summary using real numbers ────────────────────
    $perfLabel = match(true) {
        $score >= 90 => 'outstanding',
        $score >= 80 => 'strong',
        $score >= 60 => 'moderate',
        $score >= 40 => 'concerning',
        default      => 'critical',
    };

    $execSummary = match(true) {
        $score >= 90 => "Outstanding security awareness with an overall score of {$score}%. Your calculated risk exposure is {$riskScore}/100 — a {$riskLevel} risk profile. Your weakest area, {$weakName1} ({$weakScore1}%), should still receive attention to maintain this excellent standard.",
        $score >= 80 => "Strong cybersecurity knowledge at {$score}% with a risk score of {$riskScore}/100 ({$riskLevel} risk). Your main gaps are in {$weakName1} ({$weakScore1}%) and {$weakName2} ({$weakScore2}%), which represent the most exploitable vulnerabilities in your current posture.",
        $score >= 60 => "Moderate security awareness at {$score}%, resulting in a risk score of {$riskScore}/100 ({$riskLevel} risk). Key weaknesses in {$weakName1} ({$weakScore1}%) and {$weakName2} ({$weakScore2}%) are active targets for attackers. Targeted improvement is needed immediately.",
        $score >= 40 => "Your assessment score of {$score}% reveals significant security gaps, placing your risk exposure at {$riskScore}/100 — {$riskLevel} risk. Critical vulnerabilities in {$weakName1} ({$weakScore1}%) and {$weakName2} ({$weakScore2}%) require urgent remediation.",
        default      => "Your score of {$score}% indicates serious cybersecurity knowledge deficiencies across multiple domains, resulting in a risk exposure of {$riskScore}/100. Your {$riskLevel} risk profile means attackers targeting {$weakName1} ({$weakScore1}%) and {$weakName2} ({$weakScore2}%) are likely to succeed. Immediate mandatory training is required.",
    };

    // ── Dynamic advice referencing actual weak categories ─────────────────────
    $allAdviceMap = [
        'phishing'           => "📧 Your Phishing Awareness score of {$categoryScores['phishing']}% means you are likely to click malicious links. Complete phishing simulation training and always verify sender email addresses before clicking any link.",
        'password'           => "🔐 With a Password Security score of {$categoryScores['password']}%, credential theft is your biggest risk. Adopt a password manager and enable MFA on all accounts today.",
        'social_engineering' => "🧠 Your Social Engineering score of {$categoryScores['social_engineering']}% shows vulnerability to manipulation. Establish a verification protocol — never share information based on urgency alone.",
        'network'            => "🌐 Your Network Security score of {$categoryScores['network']}% exposes your traffic on public networks. Install a VPN and only use HTTPS sites for sensitive tasks.",
        'device'             => "📱 A Device Security score of {$categoryScores['device']}% means delayed patching and weak endpoint controls. Enable automatic OS updates and full-disk encryption today.",
        'data_handling'      => "📁 Your Data Handling score of {$categoryScores['data_handling']}% suggests risky data practices. Review your data classification policy and encrypt sensitive files before sharing.",
    ];
    $advice = array_map(fn($k) => $allAdviceMap[$k], array_slice($weakKeys, 0, 5));

    // ── Dynamic encouragement ─────────────────────────────────────────────────
    $encouragement = match(true) {
        $score >= 90 => "Outstanding! You scored {$score}% — you are a security role model for your entire organization.",
        $score >= 80 => "Great work at {$score}%! A few focused improvements will push you to elite-level security.",
        $score >= 60 => "Good foundation at {$score}%! Commit to one security improvement per week and your risk score will drop fast.",
        $score >= 40 => "You scored {$score}% — everyone starts somewhere. Your commitment to learning is already reducing your risk.",
        default      => "Your {$score}% score is a starting point, not a destination. Taking this assessment is the most important step — start today.",
    };

    $videoTopics = array_map(fn($k) => $categoryNames[$k] ?? $k, array_slice($weakKeys, 0, 4));

    return [
        'risk_level'        => $riskLevel,
        'risk_score'        => $riskScore,
        'executive_summary' => $execSummary,
        'advice'            => $advice,
        'video_topics'      => $videoTopics,
        'encouragement'     => $encouragement,
        'risk_color'        => $riskColor,
        'risk_icon'         => $riskIcon,
    ];
}

function getDefaultAdvice($score, $categoryScores) {
    $advice = [];
    foreach ($categoryScores as $cat => $catScore) {
        if ($catScore < 50) {
            switch($cat) {
                case 'password':
                    $advice[] = "🔐 Use a password manager and enable MFA for all accounts";
                    break;
                case 'phishing':
                    $advice[] = "📧 Always verify sender addresses and hover over links before clicking";
                    break;
                case 'device':
                    $advice[] = "📱 Keep all devices updated with the latest security patches";
                    break;
                case 'network':
                    $advice[] = "🌐 Use a VPN on public Wi-Fi and secure your home router";
                    break;
                case 'social_engineering':
                    $advice[] = "🧠 Never share sensitive information with unsolicited callers or emails";
                    break;
                case 'data_handling':
                    $advice[] = "📁 Encrypt sensitive data and follow the principle of least privilege";
                    break;
            }
        }
    }
    return array_slice(array_unique($advice), 0, 5);
}

function getDefaultVideoTopics($categoryScores) {
    $topics = [];
    $weakest = [];
    foreach ($categoryScores as $cat => $score) {
        if ($score < 60) {
            $weakest[$cat] = $score;
        }
    }
    arsort($weakest);
    $weakest = array_keys($weakest);
    
    $topicMap = [
        'password' => 'Password Security Best Practices',
        'phishing' => 'How to Spot Phishing Emails',
        'device' => 'Device Security and Updates',
        'network' => 'Network Security and VPNs',
        'social_engineering' => 'Social Engineering Awareness',
        'data_handling' => 'Data Protection and Privacy'
    ];
    
    foreach (array_slice($weakest, 0, 3) as $weak) {
        if (isset($topicMap[$weak])) {
            $topics[] = $topicMap[$weak];
        }
    }
    
    if (empty($topics)) {
        $topics = ['Cybersecurity Fundamentals', 'Protecting Your Digital Identity'];
    }
    
    return $topics;
}

/**
 * ENHANCED: Intelligent video recommendation engine based on assessment results
 * This function analyzes the user's performance and recommends the 6 most relevant videos
 */
function getIntelligentVideoRecommendations($score, $categoryScores, $incorrectAnswers, $user) {
    // Comprehensive video library with tags and difficulty levels
    $videoLibrary = [
        'password' => [
            [
                'video_id' => '65x1FakHcdU',
                'title' => 'How to Create a Strong Password – Google Security',
                'description' => 'Learn Google\'s best practices for creating and managing strong, secure passwords',
                'difficulty' => 'beginner',
                'tags' => ['passwords', 'security basics', 'account protection']
            ],
            [
                'video_id' => 'hR4yZqR3g7o',
                'title' => 'Password Managers Explained – Google',
                'description' => 'Discover how password managers protect your accounts and simplify your digital life',
                'difficulty' => 'intermediate',
                'tags' => ['password managers', 'security tools', 'account security']
            ],
            [
                'video_id' => 'inWWhr5tnEA',
                'title' => 'Multi-Factor Authentication – IBM Security',
                'description' => 'Understand why MFA is essential and how to set it up on your accounts',
                'difficulty' => 'intermediate',
                'tags' => ['MFA', 'two-factor', 'account security']
            ]
        ],
        'phishing' => [
            [
                'video_id' => 'XBkzBrXlle0',
                'title' => 'What is Phishing? – IBM Technology',
                'description' => 'Learn to identify phishing emails, text messages, and social media scams',
                'difficulty' => 'beginner',
                'tags' => ['phishing', 'email security', 'scam detection']
            ],
            [
                'video_id' => 'CkG5X9tJh1I',
                'title' => 'Advanced Phishing Techniques – IBM Security',
                'description' => 'Discover sophisticated phishing attacks like spear phishing and whaling',
                'difficulty' => 'advanced',
                'tags' => ['spear phishing', 'advanced threats', 'email security']
            ],
            [
                'video_id' => 'lc7scxvKQOo',
                'title' => 'Social Engineering Attacks – IBM Technology',
                'description' => 'How attackers manipulate human psychology to gain unauthorized access',
                'difficulty' => 'intermediate',
                'tags' => ['social engineering', 'human factors', 'manipulation']
            ]
        ],
        'device' => [
            [
                'video_id' => 'bPVaOlJ6ln0',
                'title' => 'Cybersecurity Fundamentals – CrashCourse',
                'description' => 'Comprehensive overview of device and system security basics',
                'difficulty' => 'beginner',
                'tags' => ['device security', 'fundamentals', 'system protection']
            ],
            [
                'video_id' => 'inWWhr5tnEA',
                'title' => 'Device Security Best Practices – IBM',
                'description' => 'Practical steps to secure your computers, phones, and tablets',
                'difficulty' => 'intermediate',
                'tags' => ['device updates', 'antivirus', 'mobile security']
            ],
            [
                'video_id' => 'sdpxddDzXfE',
                'title' => 'Malware Protection – IBM Security',
                'description' => 'Learn how to protect your devices from viruses, ransomware, and spyware',
                'difficulty' => 'intermediate',
                'tags' => ['malware', 'ransomware', 'virus protection']
            ]
        ],
        'network' => [
            [
                'video_id' => 'sdpxddDzXfE',
                'title' => 'Network Security Explained – IBM Technology',
                'description' => 'Understand network vulnerabilities and how to protect your home and work networks',
                'difficulty' => 'intermediate',
                'tags' => ['network security', 'Wi-Fi security', 'firewalls']
            ],
            [
                'video_id' => 'hR4yZqR3g7o',
                'title' => 'VPN and Network Privacy – Google',
                'description' => 'How VPNs work and why they\'re essential for public Wi-Fi security',
                'difficulty' => 'beginner',
                'tags' => ['VPN', 'privacy', 'public Wi-Fi']
            ],
            [
                'video_id' => 'CkG5X9tJh1I',
                'title' => 'Router Security – Cisco',
                'description' => 'Secure your home router against unauthorized access and attacks',
                'difficulty' => 'intermediate',
                'tags' => ['router security', 'home network', 'configuration']
            ]
        ],
        'social_engineering' => [
            [
                'video_id' => 'lc7scxvKQOo',
                'title' => 'Social Engineering Attacks – IBM Technology',
                'description' => 'How attackers exploit human psychology and how to defend yourself',
                'difficulty' => 'intermediate',
                'tags' => ['social engineering', 'human factors', 'manipulation']
            ],
            [
                'video_id' => 'CkG5X9tJh1I',
                'title' => 'Psychology of Social Engineering – Cisco',
                'description' => 'Deep dive into the psychological tactics used by attackers',
                'difficulty' => 'advanced',
                'tags' => ['psychology', 'advanced threats', 'manipulation']
            ],
            [
                'video_id' => 'XBkzBrXlle0',
                'title' => 'Pretexting and Impersonation – IBM',
                'description' => 'Learn how attackers create fake scenarios to steal information',
                'difficulty' => 'intermediate',
                'tags' => ['pretexting', 'impersonation', 'phone scams']
            ]
        ],
        'data_handling' => [
            [
                'video_id' => 'u-yLGIH0oFM',
                'title' => 'Data Privacy & Protection – IBM Technology',
                'description' => 'Essential practices for handling and protecting sensitive data',
                'difficulty' => 'intermediate',
                'tags' => ['data privacy', 'protection', 'GDPR']
            ],
            [
                'video_id' => '65x1FakHcdU',
                'title' => 'Data Encryption Basics – Google',
                'description' => 'Understand how encryption protects your information at rest and in transit',
                'difficulty' => 'beginner',
                'tags' => ['encryption', 'data protection', 'security']
            ],
            [
                'video_id' => 'inWWhr5tnEA',
                'title' => 'Secure Data Sharing – IBM',
                'description' => 'Best practices for sharing sensitive information safely',
                'difficulty' => 'intermediate',
                'tags' => ['data sharing', 'secure transfer', 'collaboration']
            ]
        ],
        'general' => [
            [
                'video_id' => 'bPVaOlJ6ln0',
                'title' => 'Complete Cybersecurity Course – CrashCourse',
                'description' => 'Comprehensive cybersecurity overview for all skill levels',
                'difficulty' => 'beginner',
                'tags' => ['fundamentals', 'overview', 'basics']
            ],
            [
                'video_id' => 'inWWhr5tnEA',
                'title' => 'Cybersecurity in the Modern World – IBM',
                'description' => 'Understanding current threats and protection strategies',
                'difficulty' => 'intermediate',
                'tags' => ['modern threats', 'protection', 'awareness']
            ],
            [
                'video_id' => 'CkG5X9tJh1I',
                'title' => 'Advanced Threat Protection – Microsoft',
                'description' => 'Modern approaches to defending against sophisticated attacks',
                'difficulty' => 'advanced',
                'tags' => ['advanced threats', 'defense', 'enterprise']
            ]
        ]
    ];
    
    // Calculate weak areas and their priority
    $weakCategories = [];
    $categoryNames = [
        'password' => 'Password Security',
        'phishing' => 'Phishing Awareness',
        'device' => 'Device Security',
        'network' => 'Network Security',
        'social_engineering' => 'Social Engineering',
        'data_handling' => 'Data Handling'
    ];
    
    foreach ($categoryScores as $cat => $catScore) {
        $priority = 'low';
        if ($catScore < 40) {
            $priority = 'critical';
        } elseif ($catScore < 60) {
            $priority = 'high';
        } elseif ($catScore < 80) {
            $priority = 'medium';
        }
        
        $weakCategories[$cat] = [
            'score' => $catScore,
            'priority' => $priority,
            'gap' => 100 - $catScore,
            'name' => $categoryNames[$cat]
        ];
    }
    
    // Sort by priority (critical first, then high, then medium)
    uasort($weakCategories, function($a, $b) {
        $priorityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        return $priorityOrder[$a['priority']] <=> $priorityOrder[$b['priority']];
    });
    
    // Analyze missed questions to identify specific learning needs
    $missedTopics = [];
    $missedCategories = [];
    foreach ($incorrectAnswers as $answer) {
        $category = $answer['category'];
        $missedCategories[$category] = ($missedCategories[$category] ?? 0) + 1;
        
        // Extract keywords from question for more targeted recommendations
        $questionLower = strtolower($answer['question_text']);
        if (strpos($questionLower, 'password') !== false || strpos($questionLower, 'passphrase') !== false) {
            $missedTopics[] = 'password_basics';
        }
        if (strpos($questionLower, 'phish') !== false || strpos($questionLower, 'email') !== false) {
            $missedTopics[] = 'phishing_detection';
        }
        if (strpos($questionLower, 'mfa') !== false || strpos($questionLower, 'multi-factor') !== false) {
            $missedTopics[] = 'mfa_implementation';
        }
        if (strpos($questionLower, 'vpn') !== false || strpos($questionLower, 'wi-fi') !== false) {
            $missedTopics[] = 'network_security';
        }
        if (strpos($questionLower, 'update') !== false || strpos($questionLower, 'patch') !== false) {
            $missedTopics[] = 'device_updates';
        }
        if (strpos($questionLower, 'social') !== false || strpos($questionLower, 'engineering') !== false) {
            $missedTopics[] = 'social_engineering';
        }
        if (strpos($questionLower, 'data') !== false || strpos($questionLower, 'encrypt') !== false) {
            $missedTopics[] = 'data_protection';
        }
    }
    
    $missedTopics = array_unique($missedTopics);
    
    // Build recommended videos array
    $recommendedVideos = [];
    $addedVideos = [];
    
    // First priority: Add videos for categories with critical scores
    foreach ($weakCategories as $cat => $data) {
        if ($data['priority'] === 'critical' && count($recommendedVideos) < 6) {
            if (isset($videoLibrary[$cat]) && count($videoLibrary[$cat]) > 0) {
                // Add the most relevant video for this critical category
                $videoIndex = 0;
                // If we have specific missed topics, try to match them
                if (!empty($missedTopics)) {
                    foreach ($videoLibrary[$cat] as $idx => $video) {
                        foreach ($video['tags'] as $tag) {
                            foreach ($missedTopics as $topic) {
                                if (strpos($tag, str_replace('_', ' ', $topic)) !== false) {
                                    $videoIndex = $idx;
                                    break 3;
                                }
                            }
                        }
                    }
                }
                
                $video = $videoLibrary[$cat][$videoIndex];
                if (!in_array($video['video_id'], $addedVideos)) {
                    $video['category'] = $cat;
                    $video['priority'] = 'critical';
                    $video['reason'] = "Critical weakness in {$data['name']} (Score: {$data['score']}%)";
                    $recommendedVideos[] = $video;
                    $addedVideos[] = $video['video_id'];
                }
            }
        }
    }
    
    // Second priority: Add videos for categories with high scores
    foreach ($weakCategories as $cat => $data) {
        if ($data['priority'] === 'high' && count($recommendedVideos) < 6) {
            if (isset($videoLibrary[$cat]) && count($videoLibrary[$cat]) > 0) {
                // Try to find a video that matches missed topics
                $videoIndex = 0;
                if (!empty($missedTopics)) {
                    foreach ($videoLibrary[$cat] as $idx => $video) {
                        foreach ($video['tags'] as $tag) {
                            foreach ($missedTopics as $topic) {
                                if (strpos($tag, str_replace('_', ' ', $topic)) !== false) {
                                    $videoIndex = $idx;
                                    break 3;
                                }
                            }
                        }
                    }
                }
                
                $video = $videoLibrary[$cat][$videoIndex];
                if (!in_array($video['video_id'], $addedVideos)) {
                    $video['category'] = $cat;
                    $video['priority'] = 'high';
                    $video['reason'] = "Significant improvement needed in {$data['name']} (Score: {$data['score']}%)";
                    $recommendedVideos[] = $video;
                    $addedVideos[] = $video['video_id'];
                }
            }
        }
    }
    
    // Third priority: Add videos for categories with medium scores
    foreach ($weakCategories as $cat => $data) {
        if ($data['priority'] === 'medium' && count($recommendedVideos) < 6) {
            if (isset($videoLibrary[$cat]) && count($videoLibrary[$cat]) > 0) {
                $video = $videoLibrary[$cat][0];
                if (!in_array($video['video_id'], $addedVideos)) {
                    $video['category'] = $cat;
                    $video['priority'] = 'medium';
                    $video['reason'] = "Room for improvement in {$data['name']} (Score: {$data['score']}%)";
                    $recommendedVideos[] = $video;
                    $addedVideos[] = $video['video_id'];
                }
            }
        }
    }
    
    // Fourth priority: Add general cybersecurity videos to reach 6
    $generalIndex = 0;
    while (count($recommendedVideos) < 6 && $generalIndex < count($videoLibrary['general'])) {
        $video = $videoLibrary['general'][$generalIndex];
        if (!in_array($video['video_id'], $addedVideos)) {
            $video['category'] = 'general';
            $video['priority'] = 'low';
            $video['reason'] = "Foundational knowledge to strengthen overall security awareness";
            $recommendedVideos[] = $video;
            $addedVideos[] = $video['video_id'];
        }
        $generalIndex++;
    }
    
    // Fifth priority: Add duplicate from strongest weak area if still not enough
    if (count($recommendedVideos) < 6) {
        $firstCategory = array_key_first($weakCategories);
        if ($firstCategory && isset($videoLibrary[$firstCategory])) {
            foreach ($videoLibrary[$firstCategory] as $video) {
                if (!in_array($video['video_id'], $addedVideos) && count($recommendedVideos) < 6) {
                    $video['category'] = $firstCategory;
                    $video['priority'] = 'medium';
                    $video['reason'] = "Additional resource to reinforce {$categoryNames[$firstCategory]} concepts";
                    $recommendedVideos[] = $video;
                    $addedVideos[] = $video['video_id'];
                }
            }
        }
    }
    
    // Final safety: Ensure exactly 6 videos
    $recommendedVideos = array_slice($recommendedVideos, 0, 6);
    while (count($recommendedVideos) < 6) {
        $defaultVideos = [
            ['video_id' => 'bPVaOlJ6ln0', 'title' => 'Cybersecurity Fundamentals – CrashCourse', 'description' => 'Essential cybersecurity concepts for everyone', 'category' => 'general', 'difficulty' => 'beginner', 'priority' => 'medium', 'reason' => 'Core security knowledge'],
            ['video_id' => 'XBkzBrXlle0', 'title' => 'Phishing Awareness – IBM', 'description' => 'Learn to spot and avoid phishing attacks', 'category' => 'phishing', 'difficulty' => 'beginner', 'priority' => 'medium', 'reason' => 'Critical email security skills'],
            ['video_id' => '65x1FakHcdU', 'title' => 'Password Security – Google', 'description' => 'Create and manage strong passwords effectively', 'category' => 'password', 'difficulty' => 'beginner', 'priority' => 'medium', 'reason' => 'Account protection fundamentals']
        ];
        
        if (count($recommendedVideos) < count($defaultVideos)) {
            $recommendedVideos[] = $defaultVideos[count($recommendedVideos)];
        } else {
            break;
        }
    }
    
    // Log for debugging
    error_log("Intelligent video recommendations generated: " . count($recommendedVideos) . " videos");
    
    return $recommendedVideos;
}

$rankDetails = getRankDetails($score, $rank);

// Get intelligent video recommendations based on assessment results
$recommendedVideos = getIntelligentVideoRecommendations($score, $categoryScores, $incorrectAnswers, $user);

// Ensure we always have exactly 6 videos (safety check)
$recommendedVideos = array_slice($recommendedVideos, 0, 6);
if (count($recommendedVideos) < 6) {
    // Fallback to ensure 6 videos
    $fallbackVideos = [
        ['video_id' => 'bPVaOlJ6ln0', 'title' => 'Cybersecurity Fundamentals – CrashCourse', 'description' => 'Essential cybersecurity concepts for everyone', 'category' => 'general', 'difficulty' => 'beginner', 'priority' => 'medium', 'reason' => 'Core security knowledge'],
        ['video_id' => 'XBkzBrXlle0', 'title' => 'Phishing Awareness – IBM', 'description' => 'Learn to spot and avoid phishing attacks', 'category' => 'phishing', 'difficulty' => 'beginner', 'priority' => 'medium', 'reason' => 'Critical email security skills'],
        ['video_id' => '65x1FakHcdU', 'title' => 'Password Security – Google', 'description' => 'Create and manage strong passwords effectively', 'category' => 'password', 'difficulty' => 'beginner', 'priority' => 'medium', 'reason' => 'Account protection fundamentals'],
        ['video_id' => 'sdpxddDzXfE', 'title' => 'Network Security Basics – IBM', 'description' => 'Understanding network protection fundamentals', 'category' => 'network', 'difficulty' => 'beginner', 'priority' => 'medium', 'reason' => 'Network safety essentials'],
        ['video_id' => 'lc7scxvKQOo', 'title' => 'Social Engineering Defense – IBM', 'description' => 'Recognize and resist manipulation tactics', 'category' => 'social_engineering', 'difficulty' => 'intermediate', 'priority' => 'medium', 'reason' => 'Protect against psychological attacks'],
        ['video_id' => 'u-yLGIH0oFM', 'title' => 'Data Protection Essentials – IBM', 'description' => 'Best practices for data privacy and security', 'category' => 'data_handling', 'difficulty' => 'beginner', 'priority' => 'medium', 'reason' => 'Safe data handling practices']
    ];
    
    while (count($recommendedVideos) < 6) {
        $recommendedVideos[] = $fallbackVideos[count($recommendedVideos)];
    }
}

error_log("Final video count: " . count($recommendedVideos));
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Results - CyberShield</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Syne:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap');
        
        :root {
            --font: 'Inter', sans-serif;
            --display: 'Syne', sans-serif;
            --mono: 'JetBrains Mono', monospace;
            --blue: #3B8BFF;
            --purple: #7B72F0;
            --teal: #00D4AA;
            --green: #10D982;
            --yellow: #F5B731;
            --orange: #FF8C42;
            --red: #FF3B5C;
            --t: .18s ease;
        }
        
        [data-theme=dark] {
            --bg: #030508;
            --bg2: #080d16;
            --bg3: #0d1421;
            --border: rgba(59,139,255,.08);
            --border2: rgba(255,255,255,.07);
            --text: #dde4f0;
            --muted: #4a6080;
            --muted2: #8898b4;
            --card-bg: #0a1020;
            --shadow: 0 4px 24px rgba(0,0,0,.5);
        }
        
        [data-theme=light] {
            --bg: #f0f4f8;
            --bg2: #e8eef5;
            --bg3: #fff;
            --border: rgba(59,139,255,.12);
            --border2: rgba(0,0,0,.1);
            --text: #0f172a;
            --muted: #94a3b8;
            --muted2: #475569;
            --card-bg: #fff;
            --shadow: 0 4px 24px rgba(0,0,0,.1);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; overflow: hidden; }
        body { font-family: var(--font); background: var(--bg); color: var(--text); transition: background .18s, color .18s; }
        
        .bg-grid {
            position: fixed; inset: 0; pointer-events: none; z-index: 0;
            background-image: linear-gradient(rgba(59,139,255,.025) 1px, transparent 1px), linear-gradient(90deg, rgba(59,139,255,.025) 1px, transparent 1px);
            background-size: 40px 40px;
        }
        
        #app { display: flex; height: 100vh; position: relative; z-index: 1; }
        
        /* Sidebar */
        #sidebar { width: 228px; min-width: 228px; background: var(--bg2); border-right: 1px solid var(--border); display: flex; flex-direction: column; transition: width .18s, min-width .18s; overflow: hidden; z-index: 10; flex-shrink: 0; }
        #sidebar.collapsed { width: 58px; min-width: 58px; }
        .sb-brand { display: flex; align-items: center; gap: .75rem; padding: 1rem .9rem .9rem; border-bottom: 1px solid var(--border); flex-shrink: 0; }
        .shield { width: 34px; height: 34px; background: linear-gradient(135deg, var(--blue), var(--purple)); border-radius: 9px; display: grid; place-items: center; flex-shrink: 0; box-shadow: 0 0 16px rgba(59,139,255,.3); }
        .sb-brand-text { flex: 1; overflow: hidden; white-space: nowrap; }
        .sb-brand-text h2 { font-family: var(--display); font-size: .95rem; font-weight: 700; letter-spacing: 1px; }
        .sb-brand-text .badge { font-family: var(--mono); font-size: .55rem; letter-spacing: 1.5px; text-transform: uppercase; background: rgba(16,217,130,.12); color: var(--green); border: 1px solid rgba(16,217,130,.2); border-radius: 4px; padding: .08rem .38rem; display: inline-block; margin-top: .1rem; }
        .sb-toggle { width:28px; height:28px; background:rgba(59,139,255,0.1); border:1px solid var(--blue); border-radius:6px; cursor:pointer; color:var(--blue); display:grid; place-items:center; flex-shrink:0; transition:var(--t); z-index:100 }
        .sb-toggle:hover { background:rgba(59,139,255,0.2); border-color:var(--blue); color:var(--text); transform:scale(1.05) }
        #sidebar.collapsed .sb-toggle svg { transform: rotate(180deg); }
        .sb-section { flex: 1; overflow-y: auto; overflow-x: hidden; padding: .65rem 0; }
        .sb-section::-webkit-scrollbar { width: 3px; }
        .sb-section::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 2px; }
        .sb-label { font-family: var(--mono); font-size: .55rem; letter-spacing: 2px; text-transform: uppercase; color: var(--muted); padding: .5rem .9rem .25rem; white-space: nowrap; overflow: hidden; }
        #sidebar.collapsed .sb-label { opacity: 0; }
        .sb-divider { height: 1px; background: var(--border); margin: .5rem .9rem; }
        .sb-item { display: flex; align-items: center; gap: .65rem; padding: .52rem .9rem; cursor: pointer; color: var(--muted2); font-size: .82rem; font-weight: 500; text-decoration: none; transition: var(--t); white-space: nowrap; overflow: hidden; position: relative; }
        .sb-item:hover { background: rgba(59,139,255,.07); color: var(--text); }
        .sb-item.active { background: rgba(59,139,255,.1); color: var(--blue); }
        .sb-item.active::before { content: ''; position: absolute; left: 0; top: 20%; bottom: 20%; width: 3px; background: var(--blue); border-radius: 0 3px 3px 0; }
        .sb-icon { display: flex; align-items: center; justify-content: center; width: 18px; flex-shrink: 0; }
        .sb-text { overflow: hidden; }
        #sidebar.collapsed .sb-text { display: none; }
        .sb-footer { border-top: 1px solid var(--border); padding: .75rem .9rem; flex-shrink: 0; }
        .sb-user { display: flex; align-items: center; gap: .65rem; overflow: hidden; }
        .sb-avatar { width: 30px; height: 30px; border-radius: 8px; background: linear-gradient(135deg, var(--blue), var(--purple)); color: #fff; display: grid; place-items: center; font-size: .75rem; font-weight: 700; }
        .sb-user-info { flex: 1; overflow: hidden; white-space: nowrap; }
        .sb-user-info p { font-size: .82rem; font-weight: 600; color: var(--text); }
        .sb-user-info span { font-size: .7rem; color: var(--muted); }
        #sidebar.collapsed .sb-user-info { display: none; }
        .btn-sb-logout { display: flex; align-items: center; gap: .5rem; width: 100%; padding: .6rem; margin-top: .6rem; background: rgba(255,59,92,.1); border: 1px solid rgba(255,59,92,.15); border-radius: 6px; color: var(--red); font-size: .75rem; font-weight: 600; cursor: pointer; transition: var(--t); }
        .btn-sb-logout:hover { background: rgba(255,59,92,.15); }
        #sidebar.collapsed .btn-sb-logout span { display: none; }
        
        /* Main */
        #main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .topbar { display: flex; justify-content: space-between; align-items: center; padding: 0.8rem 1.2rem; background: var(--bg2); border-bottom: 1px solid var(--border); flex-shrink: 0; }
        .tb-bc { display: flex; align-items: center; gap: .5rem; margin-bottom: .2rem; }
        .tb-app { font-family: var(--mono); font-size: .7rem; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; }
        .tb-title { font-family: var(--display); font-size: 1.1rem; font-weight: 700; color: var(--text); }
        .tb-sub { font-size: .8rem; color: var(--muted); margin-top: .2rem; }
        .tb-right { display: flex; align-items: center; gap: 1rem; }
        .tb-date { font-family: var(--mono); font-size: .7rem; color: var(--muted); }
        .tb-divider { width: 1px; height: 20px; background: var(--border); }
        .tb-icon-btn { width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--border2); background: var(--bg3); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: var(--t); color: var(--muted2); }
        .tb-icon-btn:hover { border-color: var(--blue); color: var(--text); }
        .tb-admin { display: flex; align-items: center; gap: .6rem; padding: .5rem .8rem; background: var(--bg3); border: 1px solid var(--border2); border-radius: 8px; text-decoration: none; color: var(--text); transition: var(--t); }
        .tb-admin:hover { border-color: var(--blue); }
        .tb-admin-av { width: 24px; height: 24px; border-radius: 6px; background: linear-gradient(135deg, var(--blue), var(--purple)); color: #fff; display: grid; place-items: center; font-size: .7rem; font-weight: 700; }
        .tb-admin-info { display: flex; flex-direction: column; }
        .tb-admin-name { font-size: .8rem; font-weight: 600; color: var(--text); }
        .tb-admin-role { font-size: .65rem; color: var(--muted); }
        
        .content { flex: 1; overflow-y: auto; padding: 0.5rem; }
        .content::-webkit-scrollbar { width: 6px; }
        .content::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 3px; }
        .sec-hdr { margin-bottom: 0.5rem; }
        .sec-hdr h2 { font-family: var(--display); font-size: 1.3rem; font-weight: 700; color: var(--text); margin-bottom: 0.2rem; }
        .sec-hdr p { color: var(--muted); font-size: .9rem; }
        
        .results-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 20px; padding: 1rem; margin-bottom: 1rem; box-shadow: var(--shadow); }
        
        /* Score and Category Container */
        .score-category-container {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 2rem;
            align-items: start;
        }
        
        .score-section {
            text-align: center;
            padding-right: 1rem;
            border-right: 1px solid var(--border);
        }
        
        .category-section {
            padding-left: 1rem;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border);
        }
        
        .section-header h3 {
            font-family: var(--display);
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-header .badge-count {
            background: var(--bg2);
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .category-table {
            width: 100%;
        }
        
        .category-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border);
        }
        
        .category-row:last-child {
            border-bottom: none;
        }
        
        .category-info {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            flex: 1;
        }
        
        .category-icon {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        
        .category-name {
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .category-score {
            font-family: var(--mono);
            font-size: 0.85rem;
            font-weight: 600;
            min-width: 40px;
            text-align: right;
        }
        
        .progress-bar-mini {
            width: 100px;
            height: 5px;
            background: var(--border2);
            border-radius: 3px;
            overflow: hidden;
            margin-left: 0.6rem;
        }
        
        .progress-fill-mini {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }
        .score-circle { width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, var(--blue), var(--purple)); display: flex; flex-direction: column; align-items: center; justify-content: center; margin: 0 auto 0.5rem; position: relative; }
        .score-number { font-family: var(--display); font-size: 2rem; font-weight: 800; color: white; }
        .score-label { font-family: var(--mono); font-size: 0.7rem; color: rgba(255,255,255,0.8); }
        .rank-badge-large { display: inline-block; padding: 0.3rem 0.8rem; border-radius: 50px; font-family: var(--display); font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem; }
        .result-title { text-align: center; font-family: var(--display); font-size: 1.2rem; margin-bottom: 0.3rem; }
        .result-description { text-align: center; color: var(--muted2); margin-bottom: 0.5rem; }
        .result-advice { text-align: center; padding: 0.8rem; background: var(--bg2); border-radius: 10px; margin-top: 0.5rem; }
        
        .categories-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 0.8rem; margin-bottom: 1.5rem; }
        .category-card { background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; padding: 0.8rem; transition: transform 0.2s ease; }
        .category-card:hover { transform: translateX(5px); border-color: var(--blue); }
        .category-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
        .category-name { font-family: var(--mono); font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; padding: 0.25rem 0.5rem; border-radius: 6px; }
        .category-score { font-family: var(--display); font-size: 1.1rem; font-weight: 700; }
        .progress-bar-cat { height: 8px; background: var(--border2); border-radius: 4px; overflow: hidden; }
        .progress-fill-cat { height: 100%; border-radius: 4px; transition: width 0.5s ease; }
        
        .action-buttons { display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem; }
        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; border-radius: 10px; font-family: var(--font); font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.18s ease; text-decoration: none; border: none; }
        .btn-primary { background: var(--blue); color: white; }
        .btn-primary:hover { background: #2e7ae8; transform: translateY(-2px); }
        .btn-secondary { background: var(--bg2); color: var(--text); border: 1px solid var(--border2); }
        .btn-secondary:hover { border-color: var(--blue); transform: translateY(-2px); }
        .btn-success { background: var(--green); color: white; }
        .btn-success:hover { background: #0ec473; transform: translateY(-2px); }
        
        .certificate { background: linear-gradient(135deg, var(--bg2), var(--bg3)); border: 2px solid var(--blue); border-radius: 20px; padding: 1.2rem; text-align: center; margin-top: 1.5rem; }
        .certificate h3 { font-family: var(--display); margin-bottom: 0.8rem; }
        .certificate-badge { font-size: 2.5rem; margin: 0.8rem 0; }
        
        /* AI Insights and Videos Container */
        .ai-videos-container {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .ai-section {
            padding-right: 1rem;
            border-right: 1px solid var(--border);
        }
        
        .videos-section {
            padding-left: 1rem;
        }
        
        /* Enhanced Video Grid */
        .videos-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.8rem;
        }
        
        .video-card-enhanced {
            display: flex;
            gap: 0.8rem;
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .video-card-enhanced:hover {
            transform: translateY(-2px);
            border-color: var(--blue);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }
        
        .video-thumb-enhanced {
            position: relative;
            width: 100px;
            height: 56px;
            border-radius: 8px;
            overflow: hidden;
            background: linear-gradient(135deg, var(--blue), var(--purple));
            flex-shrink: 0;
        }
        
        .video-thumb-enhanced img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .play-icon-enhanced {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 28px;
            height: 28px;
            background: rgba(59, 139, 255, 0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease;
        }
        
        .video-card-enhanced:hover .play-icon-enhanced {
            transform: translate(-50%, -50%) scale(1.1);
        }
        
        .video-info-enhanced {
            flex: 1;
            min-width: 0;
        }
        
        .video-badges {
            display: flex;
            gap: 0.3rem;
            margin-bottom: 0.3rem;
            flex-wrap: wrap;
        }
        
        .video-badge {
            display: inline-block;
            background: rgba(59, 139, 255, 0.15);
            color: var(--blue);
            padding: 0.15rem 0.5rem;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .difficulty-badge {
            font-size: 0.55rem;
            font-weight: 600;
            padding: 0.15rem 0.4rem;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(59, 139, 255, 0.1);
            color: var(--blue);
        }
        
        .priority-badge {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: #FF3B5C;
            color: white;
            font-size: 0.55rem;
            font-weight: 600;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            animation: pulse 2s ease-in-out infinite;
        }
        
        .priority-badge.critical {
            background: #FF3B5C;
        }
        
        .priority-badge.high {
            background: #F5B731;
        }
        
        .priority-badge.medium {
            background: #3B8BFF;
        }
        
        .priority-badge.low {
            background: #10D982;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.9; transform: scale(1.05); }
        }
        
        .video-title-enhanced {
            font-size: 0.85rem;
            font-weight: 700;
            margin: 0 0 0.2rem 0;
            color: var(--text);
            line-height: 1.3;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .video-desc-enhanced {
            font-size: 0.7rem;
            color: var(--muted2);
            line-height: 1.3;
            margin: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .video-reason {
            font-size: 0.65rem;
            color: var(--blue);
            margin-top: 0.3rem;
            font-style: italic;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        /* Encouragement Banner */
        .encouragement-banner {
            background: linear-gradient(135deg, rgba(16,217,130,0.15), rgba(59,139,255,0.15));
            border: 1px solid rgba(16,217,130,0.2);
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: center;
            animation: fadeInUp 0.6s ease forwards;
        }
        
        .encouragement-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .encouragement-icon {
            font-size: 1.2rem;
            animation: sparkle 2s ease-in-out infinite;
        }
        
        .encouragement-text {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text);
            margin: 0 0.5rem;
        }
        
        @keyframes sparkle {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.1); }
        }
        
        /* Small Encouragement Message */
        .encouragement-small {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
            padding: 0.5rem 0.8rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, rgba(16,217,130,0.1), rgba(59,139,255,0.1));
            border: 1px solid rgba(16,217,130,0.15);
            border-radius: 12px;
            animation: fadeInUp 0.6s ease forwards;
        }
        
        .encouragement-icon-small {
            font-size: 0.8rem;
            animation: sparkle 2s ease-in-out infinite;
        }
        
        .encouragement-text-small {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text);
            text-align: center;
            line-height: 1.3;
        }
        
        /* Action Buttons */
        .action-buttons-compact {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-compact {
            padding: 0.4rem 0.8rem;
            font-size: 0.7rem;
            font-weight: 600;
            border-radius: 8px;
            min-width: 60px;
            transition: all 0.2s ease;
        }
        
        .btn-compact:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .risk-banner { padding: 0.8rem; border-radius: 12px; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
        .risk-critical { background: rgba(255,59,92,0.15); border-left: 4px solid #FF3B5C; }
        .risk-high { background: rgba(245,183,49,0.15); border-left: 4px solid #F5B731; }
        .risk-moderate { background: rgba(59,139,255,0.15); border-left: 4px solid #3B8BFF; }
        .risk-low { background: rgba(16,217,130,0.15); border-left: 4px solid #10D982; }
        .risk-score-meter { width: 100px; height: 4px; background: rgba(255,255,255,0.2); border-radius: 2px; overflow: hidden; }
        .risk-score-fill { height: 100%; border-radius: 2px; transition: width 0.5s ease; }
        
        .advice-item { background: var(--bg2); padding: 0.6rem 0.8rem; border-radius: 10px; border: 1px solid var(--border); display: flex; align-items: flex-start; gap: 0.75rem; transition: transform 0.2s ease; margin-bottom: 0.6rem; }
        .advice-item:hover { transform: translateX(5px); border-color: var(--blue); }
        
        .missed-question { background: var(--bg2); border-left: 3px solid var(--red); padding: 0.75rem; margin-bottom: 0.75rem; border-radius: 8px; }
        .missed-question p { margin-bottom: 0.5rem; font-size: 0.85rem; }
        .correct-answer { color: var(--green); font-weight: 600; font-size: 0.8rem; }
        .explanation { color: var(--muted2); font-size: 0.75rem; margin-top: 0.25rem; }
        
        .ai-badge { display: inline-flex; align-items: center; gap: 0.5rem; background: linear-gradient(135deg, rgba(59,139,255,0.2), rgba(123,114,240,0.2)); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.7rem; border: 1px solid rgba(59,139,255,0.3); }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .fade-in { animation: fadeInUp 0.5s ease forwards; }
        
        .section-title { 
            font-family: var(--mono); 
            font-size: 0.75rem; 
            letter-spacing: 1px; 
            margin-bottom: 1rem; 
            color: var(--blue); 
            display: flex; 
            align-items: center; 
            gap: 0.5rem; 
        }
        .section-title::before { 
            content: ''; 
            width: 20px; 
            height: 2px; 
            background: var(--blue); 
            display: inline-block; 
        }
        
        /* Video Modal */
        .video-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.95);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(8px);
        }
        
        .video-modal.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }
        
        .video-modal-content {
            background: var(--card-bg);
            border-radius: 20px;
            max-width: 900px;
            width: 90%;
            max-height: 90%;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border: 1px solid var(--border);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        }
        
        .video-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }
        
        .video-modal-header h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .video-modal-close {
            background: none;
            border: none;
            color: var(--muted2);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0 0.5rem;
            transition: color 0.2s;
        }
        
        .video-modal-close:hover {
            color: var(--red);
        }
        
        .video-modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            flex: 1;
        }
        
        .video-modal-iframe {
            width: 100%;
            aspect-ratio: 16/9;
            border: none;
            border-radius: 12px;
        }
        
        @media (max-width: 768px) {
            .score-category-container {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .score-section {
                border-right: none;
                border-bottom: 1px solid var(--border);
                padding-right: 0;
                padding-bottom: 1.5rem;
                margin-bottom: 0.5rem;
            }
            
            .category-section {
                padding-left: 0;
                padding-top: 1rem;
            }
            
            .ai-videos-container {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .ai-section {
                border-right: none;
                border-bottom: 1px solid var(--border);
                padding-right: 0;
                padding-bottom: 1.5rem;
                margin-bottom: 0.5rem;
            }
            
            .videos-section {
                padding-left: 0;
                padding-top: 1rem;
            }
            
            .video-card-enhanced {
                flex-direction: column;
            }
            
            .video-thumb-enhanced {
                width: 100%;
                height: auto;
                aspect-ratio: 16/9;
            }
            
            .video-title-enhanced {
                white-space: normal;
            }
            
            .video-desc-enhanced {
                white-space: normal;
            }
        }
        
        @keyframes loading {
            0% { width: 0%; }
            50% { width: 70%; }
            100% { width: 100%; }
        }

        /* ── Animated counter & chat additions ── */
        @keyframes riskPulse {
            0%, 100% { transform: scale(1); }
            50%       { transform: scale(1.08); }
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.35; }
        }
        @keyframes typingBounce {
            0%, 80%, 100% { transform: translateY(0); opacity: .5; }
            40%            { transform: translateY(-5px); opacity: 1; }
        }
        @keyframes msgSlideIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: none; }
        }
        .chat-msg { animation: msgSlideIn .28s ease both; }
        #chat-messages::-webkit-scrollbar { width: 3px; }
        #chat-messages::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 2px; }
    </style>
</head>
<body>
<div class="bg-grid"></div>
<div id="app">

    <aside id="sidebar">
        <div class="sb-brand">
            <div class="shield">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
            </div>
            <div class="sb-brand-text">
                <h2>CyberShield</h2>
                <span class="badge">Client Portal</span>
            </div>
            <button class="sb-toggle" onclick="toggleSidebar()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </button>
        </div>
        <div class="sb-section">
            <div class="sb-label">Navigation</div>
            <a class="sb-item" href="index.php">
                <span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="3" width="7" height="7" rx="1.2"/><rect x="14" y="3" width="7" height="7" rx="1.2"/><rect x="3" y="14" width="7" height="7" rx="1.2"/><rect x="14" y="14" width="7" height="7" rx="1.2"/></svg></span>
                <span class="sb-text">Dashboard</span>
            </a>
            <a class="sb-item" href="assessment.php">
                <span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
                <span class="sb-text">Take Assessment</span>
            </a>
            <a class="sb-item active" href="result.php">
                <span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>
                <span class="sb-text">Results</span>
            </a>
            <a class="sb-item" href="review.php">
                <span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M8 6l4-4 4 4"/><path d="M12 2v13"/><path d="M20 21H4"/><path d="M17 12h3v9"/><path d="M4 12h3v9"/></svg></span>
                <span class="sb-text">Review</span>
            </a>
            <div class="sb-divider"></div>
            <div class="sb-label">Account</div>
            <a class="sb-item" href="profile.php">
                <span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                <span class="sb-text">Profile</span>
            </a>
            <a class="sb-item" href="security-tips.php">
                <span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span>
                <span class="sb-text">Security Tips</span>
            </a>
            <a class="sb-item" href="terms.php">
                <span class="sb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>
                <span class="sb-text">Terms & Privacy</span>
            </a>
        </div>
        <div class="sb-footer">
            <div class="sb-user">
                <div class="sb-avatar"><?php echo htmlspecialchars($initial); ?></div>
                <div class="sb-user-info">
                    <p><?php echo htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'User'); ?></p>
                    <span>Client Account</span>
                </div>
            </div>
            <button class="btn-sb-logout" onclick="doLogout()">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9.5 2.5L12 5L14.5 2.5L17 5L19.5 2.5L21 5V16L18.5 18.5L16 21L12 19L8 21L5.5 18.5L3 16V5L4.5 2.5L7 5L9.5 2.5Z"/>
                    <path d="M12 9v4M12 17h.01"/>
                </svg>
                <span>Sign Out</span>
            </button>
        </div>
    </aside>

    <div id="main">
        <div class="topbar">
            <div>
                <div class="tb-bc">
                    <span class="tb-app">CyberShield</span>
                    <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 4l4 4-4 4"/></svg>
                    <span class="tb-title">Assessment Results</span>
                </div>
                <p class="tb-sub">Your cybersecurity assessment performance and AI-powered recommendations</p>
            </div>
            <div class="tb-right">
                <span class="tb-date" id="tb-date"></span>
                <div class="tb-divider"></div>
                <button class="tb-icon-btn" onclick="toggleTheme()" title="Toggle theme">
                    <svg id="tmoon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    <svg id="tsun" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="display:none"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>
                </button>
                <div class="tb-divider"></div>
                <a class="tb-admin" href="profile.php">
                    <div class="tb-admin-av"><?php echo htmlspecialchars($initial); ?></div>
                    <div class="tb-admin-info">
                        <span class="tb-admin-name"><?php echo htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'User'); ?></span>
                        <span class="tb-admin-role">Client</span>
                    </div>
                    <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" style="color:var(--muted);margin-left:.2rem"><path d="M4 6l4 4 4-4"/></svg>
                </a>
            </div>
        </div>

        <div class="content">
            <div class="sec-hdr">
                <h2>Assessment Results</h2>
                <p>Your cybersecurity assessment performance and personalized AI recommendations.</p>
            </div>

            <?php if ($no_assessment): ?>
            <div class="results-card fade-in">
                <div style="text-align: center; padding: 3rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📊</div>
                    <h2>No Assessment Results Yet</h2>
                    <p>You haven't completed any cybersecurity assessments yet.</p>
                    <p style="color: var(--muted2); margin-bottom: 2rem;">Take your first assessment to see your security score and personalized AI recommendations.</p>
                    <a href="assessment.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                        Take Your First Assessment
                    </a>
                </div>
            </div>
            <?php else: ?>

            <!-- Score and Category Performance Container -->
            <div class="results-card fade-in">
                <div class="score-category-container">
                    <!-- Left: Overall Score -->
                    <div class="score-section">
                        <div class="score-circle">
                            <div class="score-number"><?php echo $score; ?>%</div>
                            <div class="score-label">Overall Score</div>
                        </div>
                        <div class="rank-badge-large" style="color: <?php echo $rankDetails['color']; ?>; text-align: center;">
                            Rank <?php echo $rankDetails['letter']; ?>
                        </div>
                        <h2 class="result-title"><?php echo $rankDetails['title']; ?> <?php echo $rankDetails['icon']; ?></h2>
                        <p class="result-description"><?php echo $rankDetails['description']; ?></p>
                        <div class="result-advice">
                            <strong>💡 Recommendation:</strong> <?php echo $rankDetails['advice']; ?>
                        </div>
                        
                        <!-- Action Buttons - Under Score -->
                        <div class="action-buttons-compact">
                            <a href="assessment.php" class="btn btn-primary btn-compact">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                                Retake
                            </a>
                            <a href="security-tips.php" class="btn btn-secondary btn-compact">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                Tips
                            </a>
                            <a href="index.php" class="btn btn-secondary btn-compact">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                                Home
                            </a>
                        </div>
                    </div>

                    <!-- Right: Category Performance -->
                    <div class="category-section">
                        <div class="section-header">
                            <h3>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2">
                                    <line x1="18" y1="20" x2="18" y2="10"/>
                                    <line x1="12" y1="20" x2="12" y2="4"/>
                                    <line x1="6" y1="20" x2="6" y2="14"/>
                                </svg>
                                Category Performance
                            </h3>
                            <span class="badge-count">6 categories</span>
                        </div>
                        
                        <!-- Small Encouragement Message -->
                        <?php if ($aiInsights): ?>
                        <div class="encouragement-small">
                            <span class="encouragement-icon-small">✨</span>
                            <span class="encouragement-text-small"><?php echo htmlspecialchars($aiInsights['encouragement']); ?></span>
                            <span class="encouragement-icon-small">✨</span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="category-table">
                            <?php
                            $categoryList = [
                                'password' => ['name' => 'Password Security', 'icon' => '🔐', 'color' => '#10D982'],
                                'phishing' => ['name' => 'Phishing Awareness', 'icon' => '📧', 'color' => '#3B8BFF'],
                                'device' => ['name' => 'Device Security', 'icon' => '📱', 'color' => '#7B72F0'],
                                'network' => ['name' => 'Network Security', 'icon' => '🌐', 'color' => '#00D4AA'],
                                'social_engineering' => ['name' => 'Social Engineering', 'icon' => '🧠', 'color' => '#FF8C42'],
                                'data_handling' => ['name' => 'Data Handling', 'icon' => '📁', 'color' => '#F5B731']
                            ];
                            foreach ($categoryList as $key => $cat):
                                $catScore = $categoryScores[$key] ?? 0;
                                $scoreColor = $catScore >= 80 ? '#10D982' : ($catScore >= 60 ? '#3B8BFF' : ($catScore >= 40 ? '#F5B731' : '#FF3B5C'));
                            ?>
                            <div class="category-row">
                                <div class="category-info">
                                    <div class="category-icon" style="background: <?php echo $scoreColor; ?>20;"><?php echo $cat['icon']; ?></div>
                                    <span class="category-name"><?php echo $cat['name']; ?></span>
                                    <div class="progress-bar-mini">
                                        <div class="progress-fill-mini" style="width: <?php echo $catScore; ?>%; background: <?php echo $scoreColor; ?>;"></div>
                                    </div>
                                </div>
                                <span class="category-score" style="color: <?php echo $scoreColor; ?>;"><?php echo $catScore; ?>%</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AI Insights and Videos Container -->
            <div class="ai-videos-container fade-in">
                <!-- Left: AI Assistant Recommendations -->
                <div class="ai-section">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.75rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="background: linear-gradient(135deg, rgba(59,139,255,0.2), rgba(123,114,240,0.2)); padding: 0.5rem; border-radius: 12px;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="1.8">
                                    <path d="M9.5 2.5L12 5L14.5 2.5L17 5L19.5 2.5L21 5V16L18.5 18.5L16 21L12 19L8 21L5.5 18.5L3 16V5L4.5 2.5L7 5L9.5 2.5Z"/>
                                    <path d="M12 9v4M12 17h.01"/>
                                </svg>
                            </div>
                            <h3 style="margin: 0; font-family: var(--display);">CyberShield AI Assistant</h3>
                            <span class="ai-badge">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                GPT-4 Powered
                            </span>
                        </div>
                        <button onclick="refreshAIInsights()" class="btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.7rem;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            Refresh Analysis
                        </button>
                    </div>

                    <?php if ($aiInsights): ?>

                    <!-- ═══════════════════════════════════════════════════════
                         AI RISK BANNER — LIVE ANIMATED COUNTER
                         risk_score is now calculated from real assessment data
                         ═══════════════════════════════════════════════════════ -->
                    <div class="risk-banner risk-<?php echo $aiInsights['risk_level']; ?>" id="ai-risk-banner" style="position:relative;overflow:hidden;">

                        <!-- Animated risk icon -->
                        <span style="font-size:2.2rem;flex-shrink:0;animation:riskPulse 2s ease-in-out infinite;" id="risk-icon-el">
                            <?php echo $aiInsights['risk_icon']; ?>
                        </span>

                        <div style="flex:1;">
                            <!-- Title row -->
                            <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:.4rem;">
                                <strong style="font-size:1rem;font-family:var(--display);">
                                    AI Risk Assessment:
                                    <span style="color:<?php echo $aiInsights['risk_color']; ?>;">
                                        <?php echo ucfirst($aiInsights['risk_level']); ?> Risk Level
                                    </span>
                                </strong>
                                <span style="display:inline-flex;align-items:center;gap:.3rem;background:rgba(59,139,255,.12);border:1px solid rgba(59,139,255,.2);padding:.15rem .6rem;border-radius:20px;font-size:.62rem;font-family:var(--mono);color:var(--blue);">
                                    <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    <?php echo isset($aiInsights['source']) && $aiInsights['source'] === 'claude' ? 'Claude AI · Anthropic' : 'CyberShield Engine'; ?>
                                </span>
                            </div>

                            <!-- ANIMATED RISK SCORE COUNTER — this is the core fix -->
                            <div style="display:flex;align-items:center;gap:1.2rem;margin:.5rem 0;flex-wrap:wrap;">
                                <div style="display:flex;align-items:baseline;gap:.2rem;">
                                    <span style="font-family:var(--mono);font-size:.8rem;color:var(--muted2);">Risk Score:</span>
                                    &nbsp;
                                    <!-- The counter starts at 0 and counts up to the real value -->
                                    <span id="risk-score-counter"
                                          data-target="<?php echo $aiInsights['risk_score']; ?>"
                                          style="font-family:var(--display);font-size:2.4rem;font-weight:800;color:<?php echo $aiInsights['risk_color']; ?>;line-height:1;min-width:2ch;display:inline-block;transition:color .3s;">
                                        0
                                    </span>
                                    <span style="font-family:var(--mono);font-size:.85rem;color:var(--muted2);">/100</span>
                                    &nbsp;
                                    <!-- Risk level chip -->
                                    <span style="display:inline-flex;align-items:center;gap:.3rem;background:<?php echo $aiInsights['risk_color']; ?>18;border:1px solid <?php echo $aiInsights['risk_color']; ?>30;padding:.22rem .7rem;border-radius:20px;font-family:var(--mono);font-size:.7rem;font-weight:700;color:<?php echo $aiInsights['risk_color']; ?>;letter-spacing:.5px;text-transform:uppercase;">
                                        <?php echo $aiInsights['risk_icon']; ?>
                                        <?php echo strtoupper($aiInsights['risk_level']); ?> RISK
                                    </span>
                                </div>

                                <!-- Gradient risk meter that fills in sync with counter -->
                                <div style="flex:1;min-width:140px;">
                                    <div style="display:flex;justify-content:space-between;font-family:var(--mono);font-size:.58rem;color:var(--muted);margin-bottom:.3rem;">
                                        <span>Low Risk (0)</span><span>Critical (100)</span>
                                    </div>
                                    <div style="height:10px;background:rgba(255,255,255,.07);border-radius:5px;overflow:hidden;">
                                        <div id="risk-meter-fill"
                                             style="height:100%;width:0%;border-radius:5px;background:linear-gradient(90deg,#10D982 0%,#F5B731 50%,#FF3B5C 100%);transition:none;">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Summary text -->
                            <div style="font-size:.82rem;color:var(--muted2);line-height:1.6;margin-top:.3rem;">
                                <?php echo htmlspecialchars($aiInsights['executive_summary']); ?>
                            </div>
                        </div>
                    </div>

                    <!-- AI Risk Assessment -->
                    <div style="margin-bottom: 1.5rem;">
                        <div class="section-title">AI RISK ASSESSMENT</div>
                        <div style="background: var(--bg2); padding: 1rem; border-radius: 12px; border: 1px solid var(--border);">
                            <p style="line-height: 1.6; font-size: 0.95rem;"><?php echo htmlspecialchars($aiInsights['executive_summary']); ?></p>
                        </div>
                    </div>

                    <!-- Personalized Advice -->
                    <?php if (!empty($aiInsights['advice'])): ?>
                    <div style="margin-bottom: 2rem;">
                        <div class="section-title">AI-GENERATED RECOMMENDATIONS</div>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <?php foreach ($aiInsights['advice'] as $advice): ?>
                            <div class="advice-item">
                                <span style="font-size: 1.2rem;">🔍</span>
                                <span style="font-size: 0.9rem; line-height: 1.4;"><?php echo htmlspecialchars($advice); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════════════
     CYBERSHIELD AI — FLOATING SUPPORT BUTTON + POPUP CHAT
     Replaces the inline chat panel with a fixed bottom-right button.
     ═══════════════════════════════════════════════════════════════════════ -->

<?php if ($aiInsights): ?>
<!-- ── Floating Support Button ──────────────────────────────────────── -->
<button id="cs-support-btn" onclick="toggleChatPopup()" aria-label="Open CyberShield AI Support"
    style="
        position:fixed;
        bottom:28px;
        right:28px;
        z-index:9998;
        width:58px;
        height:58px;
        border-radius:50%;
        background:linear-gradient(135deg,var(--blue,#3B8BFF),var(--purple,#8B5CF6));
        border:none;
        cursor:pointer;
        display:flex;
        align-items:center;
        justify-content:center;
        box-shadow:0 4px 24px rgba(59,139,255,.55), 0 2px 8px rgba(0,0,0,.35);
        transition:transform .2s ease, box-shadow .2s ease;
    "
    onmouseover="this.style.transform='scale(1.1)';this.style.boxShadow='0 6px 32px rgba(59,139,255,.75), 0 2px 12px rgba(0,0,0,.4)'"
    onmouseout="this.style.transform='scale(1)';this.style.boxShadow='0 4px 24px rgba(59,139,255,.55), 0 2px 8px rgba(0,0,0,.35)'">
    <!-- Shield/chat icon shown when closed -->
    <svg id="cs-btn-icon-open" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
    </svg>
    <!-- X icon shown when open -->
    <svg id="cs-btn-icon-close" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
    </svg>
    <!-- Unread badge (notification dot) -->
    <span id="cs-notif-dot" style="
        position:absolute;
        top:4px;right:4px;
        width:13px;height:13px;
        border-radius:50%;
        background:#FF3B5C;
        border:2px solid #0d0f14;
        animation:blink 2s ease-in-out infinite;
    "></span>
</button>

<!-- ── Tooltip label beside the button ─────────────────────────────── -->
<div id="cs-support-label" onclick="toggleChatPopup()" style="
    position:fixed;
    bottom:38px;
    right:98px;
    z-index:9997;
    background:rgba(13,15,20,.92);
    border:1px solid rgba(59,139,255,.3);
    backdrop-filter:blur(12px);
    border-radius:22px;
    padding:.38rem .9rem;
    font-size:.72rem;
    font-family:var(--font,'sans-serif');
    color:var(--text,#e0e6f0);
    white-space:nowrap;
    cursor:pointer;
    box-shadow:0 2px 16px rgba(0,0,0,.4);
    transition:opacity .3s, transform .3s;
    display:flex;
    align-items:center;
    gap:.45rem;
">
    <span style="width:7px;height:7px;border-radius:50%;background:#10D982;animation:blink 2s ease-in-out infinite;flex-shrink:0;"></span>
    <span>CyberShield AI — Support</span>
</div>

<!-- ── Popup Chat Window ─────────────────────────────────────────────── -->
<div id="cs-chat-popup" style="
    position:fixed;
    bottom:100px;
    right:28px;
    z-index:9999;
    width:360px;
    max-width:calc(100vw - 40px);
    background:var(--card-bg,#111318);
    border:1px solid rgba(59,139,255,.25);
    border-radius:18px;
    box-shadow:0 16px 56px rgba(0,0,0,.6), 0 0 0 1px rgba(59,139,255,.08);
    display:none;
    flex-direction:column;
    overflow:hidden;
    transform:translateY(16px) scale(.97);
    opacity:0;
    transition:transform .28s cubic-bezier(.34,1.56,.64,1), opacity .22s ease;
    font-family:var(--font,'sans-serif');
">

    <!-- Popup header -->
    <div style="
        background:linear-gradient(135deg,rgba(59,139,255,.18),rgba(139,92,246,.12));
        border-bottom:1px solid rgba(59,139,255,.18);
        padding:.85rem 1rem .75rem;
        display:flex;
        align-items:center;
        gap:.65rem;
    ">
        <div style="width:36px;height:36px;background:linear-gradient(135deg,var(--blue,#3B8BFF),var(--purple,#8B5CF6));border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 0 12px rgba(59,139,255,.4);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><circle cx="12" cy="11" r="3"/></svg>
        </div>
        <div style="flex:1;min-width:0;">
            <div style="font-family:var(--display,'sans-serif');font-size:.88rem;font-weight:700;color:var(--text,#e0e6f0);">CyberShield AI Assistant</div>
            <div style="font-size:.62rem;color:var(--muted2,'#8899aa');white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                Score: <strong style="color:var(--text,#e0e6f0);"><?php echo $score; ?>%</strong>
                &nbsp;·&nbsp; Risk: <strong style="color:<?php echo $aiInsights['risk_color']; ?>;"><?php echo ucfirst($aiInsights['risk_level']); ?> (<?php echo $aiInsights['risk_score']; ?>/100)</strong>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:.3rem;flex-shrink:0;">
            <div style="width:7px;height:7px;border-radius:50%;background:var(--green,#10D982);animation:blink 2s ease-in-out infinite;"></div>
            <span style="font-family:var(--mono,'monospace');font-size:.58rem;color:var(--green,#10D982);">Online</span>
        </div>
        <button onclick="toggleChatPopup()" style="background:none;border:none;cursor:pointer;color:var(--muted2,'#8899aa');padding:.2rem;border-radius:6px;display:flex;transition:color .15s;"
            onmouseover="this.style.color='var(--text,#e0e6f0)'" onmouseout="this.style.color='var(--muted2,#8899aa)'">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>

    <!-- Chat messages -->
    <div id="chat-messages" style="
        height:280px;
        overflow-y:auto;
        padding:.85rem;
        display:flex;
        flex-direction:column;
        gap:.65rem;
        background:var(--bg,#0d0f14);
        scrollbar-width:thin;
        scrollbar-color:rgba(59,139,255,.25) transparent;
    ">
        <!-- AI greeting seeded from PHP -->
        <div class="chat-msg assistant" style="display:flex;gap:.5rem;align-items:flex-start;animation:msgSlideIn .28s ease both;">
            <div style="width:26px;height:26px;border-radius:8px;background:linear-gradient(135deg,var(--blue,#3B8BFF),var(--purple,#8B5CF6));color:#fff;display:flex;align-items:center;justify-content:center;font-size:.68rem;flex-shrink:0;">🤖</div>
            <div>
                <div style="background:var(--card-bg,#111318);border:1px solid var(--border,#1e2330);border-radius:10px 10px 10px 3px;padding:.55rem .8rem;font-size:.79rem;line-height:1.55;max-width:260px;color:var(--text,#e0e6f0);">
                    <?php
                    $greeting = $aiInsights['chat_greeting']
                        ?? "Hello! I've analyzed your assessment — you scored <strong>{$score}%</strong> with a <strong style='color:{$aiInsights['risk_color']}'>{$aiInsights['risk_level']} risk level ({$aiInsights['risk_score']}/100)</strong>. Ask me anything about your results or how to improve!";
                    echo $greeting;
                    ?>
                </div>
                <div style="font-family:var(--mono,'monospace');font-size:.56rem;color:var(--muted,'#5a6478');margin-top:.2rem;"><?php echo date('g:i A'); ?></div>
            </div>
        </div>
        <!-- Typing indicator -->
        <div id="chat-typing" style="display:none;gap:.5rem;align-items:center;">
            <div style="width:26px;height:26px;border-radius:8px;background:linear-gradient(135deg,var(--blue,#3B8BFF),var(--purple,#8B5CF6));color:#fff;display:flex;align-items:center;justify-content:center;font-size:.68rem;flex-shrink:0;">🤖</div>
            <div style="background:var(--card-bg,#111318);border:1px solid var(--border,#1e2330);border-radius:10px 10px 10px 3px;padding:.55rem .85rem;display:flex;gap:.28rem;align-items:center;">
                <div class="typing-dot" style="width:6px;height:6px;border-radius:50%;background:var(--muted2,'#8899aa');animation:typingBounce 1.4s ease-in-out infinite;"></div>
                <div class="typing-dot" style="width:6px;height:6px;border-radius:50%;background:var(--muted2,'#8899aa');animation:typingBounce 1.4s ease-in-out .2s infinite;"></div>
                <div class="typing-dot" style="width:6px;height:6px;border-radius:50%;background:var(--muted2,'#8899aa');animation:typingBounce 1.4s ease-in-out .4s infinite;"></div>
            </div>
        </div>
    </div>

    <!-- Quick chip questions -->
    <div style="padding:.5rem .85rem .4rem;display:flex;gap:.32rem;flex-wrap:wrap;border-top:1px solid var(--border,#1e2330);background:var(--bg,#0d0f14);">
        <?php
        $chips = [
            "What is my biggest risk?",
            "Explain my risk score of {$aiInsights['risk_score']}/100",
            "How do I improve my score?",
            "What should I prioritize first?",
        ];
        foreach ($chips as $chip): ?>
        <button onclick="sendChatChip(this)"
            style="background:var(--bg2,#13161e);border:1px solid var(--border2,#252a38);border-radius:20px;padding:.26rem .65rem;font-size:.63rem;color:var(--muted2,'#8899aa');cursor:pointer;transition:all .15s;font-family:var(--font,'sans-serif');"
            onmouseover="this.style.borderColor='var(--blue,#3B8BFF)';this.style.color='var(--blue,#3B8BFF)'"
            onmouseout="this.style.borderColor='var(--border2,#252a38)';this.style.color='var(--muted2,#8899aa)'">
            <?php echo htmlspecialchars($chip); ?>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- Chat input -->
    <div style="padding:.6rem .75rem .75rem;background:var(--card-bg,#111318);display:flex;gap:.5rem;border-top:1px solid var(--border,#1e2330);">
        <input type="text" id="chat-input"
               placeholder="Ask about your results, risks, or how to improve…"
               onkeydown="if(event.key==='Enter') sendChatMessage()"
               style="flex:1;background:var(--bg2,#13161e);border:1px solid var(--border2,#252a38);border-radius:22px;padding:.46rem .95rem;font-family:var(--font,'sans-serif');font-size:.77rem;color:var(--text,#e0e6f0);outline:none;transition:border-color .18s;"
               onfocus="this.style.borderColor='var(--blue,#3B8BFF)'"
               onblur="this.style.borderColor='var(--border2,#252a38)'" />
        <button id="chat-send-btn" onclick="sendChatMessage()"
                style="width:34px;height:34px;border-radius:50%;background:var(--blue,#3B8BFF);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .15s,transform .15s;"
                onmouseover="this.style.background='#2e7ae8';this.style.transform='scale(1.08)'"
                onmouseout="this.style.background='var(--blue,#3B8BFF)';this.style.transform='scale(1)'">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        </button>
    </div>
</div>

<script>
// ── Toggle chat popup open / closed ─────────────────────────────────────────
(function() {
    var isOpen = false;

    window.toggleChatPopup = function() {
        var popup   = document.getElementById('cs-chat-popup');
        var iconO   = document.getElementById('cs-btn-icon-open');
        var iconC   = document.getElementById('cs-btn-icon-close');
        var dot     = document.getElementById('cs-notif-dot');
        var label   = document.getElementById('cs-support-label');

        isOpen = !isOpen;

        if (isOpen) {
            popup.style.display  = 'flex';
            // Trigger animation next frame
            requestAnimationFrame(function() {
                popup.style.transform = 'translateY(0) scale(1)';
                popup.style.opacity   = '1';
            });
            iconO.style.display = 'none';
            iconC.style.display = 'block';
            if (dot) dot.style.display = 'none';
            if (label) label.style.opacity = '0';
            if (label) label.style.pointerEvents = 'none';
            // Focus input
            setTimeout(function() {
                var inp = document.getElementById('chat-input');
                if (inp) inp.focus();
            }, 280);
        } else {
            popup.style.transform = 'translateY(16px) scale(.97)';
            popup.style.opacity   = '0';
            setTimeout(function() { popup.style.display = 'none'; }, 260);
            iconO.style.display = 'block';
            iconC.style.display = 'none';
            if (label) label.style.opacity = '1';
            if (label) label.style.pointerEvents = 'auto';
        }
    };

    // Hide tooltip after 5 s on load
    setTimeout(function() {
        var label = document.getElementById('cs-support-label');
        if (label && !isOpen) {
            label.style.opacity = '0';
            label.style.pointerEvents = 'none';
        }
    }, 5000);
})();
</script>
<?php endif; ?>
                </div>

                <!-- Right: Intelligent Recommended Videos -->
                <div class="videos-section">
                    <div class="section-header">
                        <h3>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2">
                                <rect x="2" y="4" width="20" height="16" rx="2"/>
                                <polygon points="10 8 16 12 10 16 10 8" fill="var(--blue)"/>
                            </svg>
                            INTELLIGENT VIDEO RECOMMENDATIONS
                        </h3>
                        <span class="badge-count">6 videos based on your results</span>
                    </div>
                    
                    <div class="videos-grid">
                        <?php foreach ($recommendedVideos as $index => $video):
                            $videoUrl = 'https://www.youtube.com/embed/' . $video['video_id'] . '?autoplay=0&rel=0';
                            $thumbnail = 'https://img.youtube.com/vi/' . $video['video_id'] . '/maxresdefault.jpg';
                            $priorityColor = $video['priority'] === 'critical' ? '#FF3B5C' : ($video['priority'] === 'high' ? '#F5B731' : ($video['priority'] === 'medium' ? '#3B8BFF' : '#10D982'));
                            $difficultyBadge = $video['difficulty'] === 'advanced' ? 'Advanced' : ($video['difficulty'] === 'intermediate' ? 'Intermediate' : 'Beginner');
                            $categoryDisplay = ucfirst(str_replace('_', ' ', $video['category']));
                            $priorityLabel = $video['priority'] === 'critical' ? 'CRITICAL' : ($video['priority'] === 'high' ? 'HIGH PRIORITY' : ($video['priority'] === 'medium' ? 'Recommended' : 'Nice to Know'));
                        ?>
                        <div class="video-card-enhanced" onclick="playVideo('<?php echo htmlspecialchars($video['title'], ENT_QUOTES); ?>', '<?php echo $videoUrl; ?>')">
                            <div class="video-thumb-enhanced">
                                <img src="<?php echo $thumbnail; ?>" alt="<?php echo htmlspecialchars($video['title']); ?>" loading="lazy"
                                     onerror="if(this.src.includes('maxresdefault')){this.src=this.src.replace('maxresdefault','hqdefault');}else{this.onerror=null;this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 16 9%22%3E%3Crect width=%22100%25%22 height=%22100%25%22 fill=%22%233B8BFF22%22/%3E%3Ctext x=%2250%25%22 y=%2255%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 fill=%22%233B8BFF%22 font-size=%221.8%22%3E▶%3C/text%3E%3C/svg%3E';}">
                                <div class="play-icon-enhanced">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="white"><polygon points="5 3 19 12 5 21 5 3" fill="white"/></svg>
                                </div>
                                <?php if ($video['priority'] !== 'low'): ?>
                                <div class="priority-badge <?php echo $video['priority']; ?>" style="background: <?php echo $priorityColor; ?>;"><?php echo $priorityLabel; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="video-info-enhanced">
                                <div class="video-badges">
                                    <span class="video-badge"><?php echo $categoryDisplay; ?></span>
                                    <span class="difficulty-badge"><?php echo $difficultyBadge; ?></span>
                                </div>
                                <h5 class="video-title-enhanced"><?php echo htmlspecialchars($video['title']); ?></h5>
                                <p class="video-desc-enhanced"><?php echo htmlspecialchars($video['description']); ?></p>
                                <?php if (isset($video['reason'])): ?>
                                <div class="video-reason">
                                    <span>🎯</span>
                                    <span><?php echo htmlspecialchars($video['reason']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        </div><!-- .content -->
    </div><!-- #main -->
</div>

<!-- Video Modal -->
<div id="videoModal" class="video-modal">
    <div class="video-modal-content">
        <div class="video-modal-header">
            <h3 id="modalTitle">Watch: Cybersecurity Video</h3>
            <button class="video-modal-close" onclick="closeVideoModal()">&times;</button>
        </div>
        <div class="video-modal-body">
            <iframe id="videoIframe" class="video-modal-iframe" src="" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
        </div>
    </div>
</div>

<script>
    // Theme toggle
    function toggleTheme() {
        const html = document.documentElement;
        const newTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', newTheme);
        localStorage.setItem('cs_th', newTheme);
        document.getElementById('tmoon').style.display = newTheme === 'dark' ? 'block' : 'none';
        document.getElementById('tsun').style.display  = newTheme === 'dark' ? 'none'  : 'block';
    }

    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('collapsed');
        localStorage.setItem('cs_sb', document.getElementById('sidebar').classList.contains('collapsed') ? '1' : '0');
    }

    function doLogout() {
        if (confirm('Are you sure you want to sign out?')) {
            window.location.href = '../landingpage.php';
        }
    }

    function downloadCertificate() { 
        window.print(); 
    }
    
    function playVideo(title, videoUrl) {
        const modal = document.getElementById('videoModal');
        const iframe = document.getElementById('videoIframe');
        const modalTitle = document.getElementById('modalTitle');
        modalTitle.textContent = 'Watch: ' + title;
        // Ensure autoplay is enabled when modal opens
        videoUrl = videoUrl.replace('autoplay=0', 'autoplay=1');
        iframe.src = videoUrl;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeVideoModal() {
        const modal = document.getElementById('videoModal');
        const iframe = document.getElementById('videoIframe');
        iframe.src = '';
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }

    function refreshAIInsights() {
        const aiSection = document.querySelector('.ai-videos-container');
        if (aiSection) {
            const loadingHtml = `
                <div style="text-align: center; padding: 2rem;">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">🤖</div>
                    <p>CyberShield AI is analyzing your results...</p>
                    <div style="width: 100%; height: 4px; background: var(--border2); border-radius: 2px; overflow: hidden; margin-top: 1rem;">
                        <div style="width: 0%; height: 100%; background: var(--blue); animation: loading 1.5s infinite;"></div>
                    </div>
                </div>
            `;
            aiSection.innerHTML = loadingHtml;
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            location.reload();
        }
    }

    // Restore theme
    const savedTheme = localStorage.getItem('cs_th') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
    document.getElementById('tmoon').style.display = savedTheme === 'dark' ? 'block' : 'none';
    document.getElementById('tsun').style.display  = savedTheme === 'dark' ? 'none'  : 'block';

    // Restore sidebar
    if (localStorage.getItem('cs_sb') === '1') {
        document.getElementById('sidebar').classList.add('collapsed');
    }

    // Date
    const dateEl = document.getElementById('tb-date');
    if (dateEl) {
        dateEl.textContent = new Date().toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
    }
    
    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeVideoModal();
        }
    });
    
    // Close modal on outside click
    document.getElementById('videoModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeVideoModal();
        }
    });

    // ═══════════════════════════════════════════════════════════════════
    //  ANIMATED RISK SCORE COUNTER
    //  Counts from 0 up to the real calculated risk score on page load.
    //  The meter bar fills in sync with the counter.
    // ═══════════════════════════════════════════════════════════════════
    function animateRiskCounter() {
        const counterEl = document.getElementById('risk-score-counter');
        const meterFill = document.getElementById('risk-meter-fill');
        if (!counterEl) return;

        const target   = parseInt(counterEl.dataset.target || '0', 10);
        const duration = 2000;  // 2 seconds
        const start    = performance.now();

        function easeOutCubic(t) { return 1 - Math.pow(1 - t, 3); }

        function tick(now) {
            const elapsed  = now - start;
            const progress = Math.min(elapsed / duration, 1);
            const eased    = easeOutCubic(progress);
            const current  = Math.round(eased * target);

            counterEl.textContent = current;

            // Fill the gradient meter bar proportionally
            if (meterFill) {
                meterFill.style.width = current + '%';
            }

            if (progress < 1) {
                requestAnimationFrame(tick);
            } else {
                // Ensure final value is exact
                counterEl.textContent = target;
                if (meterFill) meterFill.style.width = target + '%';
            }
        }

        // Small delay so user sees 0 before counting starts
        setTimeout(() => requestAnimationFrame(tick), 600);
    }

    // Run counter when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', animateRiskCounter);
    } else {
        animateRiskCounter();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  CYBERSHIELD AI CHAT ASSISTANT
    //  Sends user messages to ai_chat.php which calls Claude API.
    //  Falls back to smart rule-based answers if no API key.
    // ═══════════════════════════════════════════════════════════════════
    const CHAT_CONTEXT = {
        score:      <?php echo json_encode($score); ?>,
        rank:       <?php echo json_encode($rank); ?>,
        riskScore:  <?php echo json_encode($aiInsights['risk_score'] ?? 0); ?>,
        riskLevel:  <?php echo json_encode($aiInsights['risk_level'] ?? 'unknown'); ?>,
        riskColor:  <?php echo json_encode($aiInsights['risk_color'] ?? '#3B8BFF'); ?>,
        categories: <?php echo json_encode($categoryScores); ?>,
        userName:   <?php echo json_encode($user['full_name'] ?? $user['username'] ?? 'Vendor'); ?>,
    };

    function appendChatMessage(role, text) {
        const container = document.getElementById('chat-messages');
        if (!container) return;

        const initial = <?php echo json_encode($initial); ?>;
        const time    = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });

        const isAI = role === 'assistant';
        const div  = document.createElement('div');
        div.className = 'chat-msg ' + role;
        div.style.cssText = 'display:flex;gap:.5rem;align-items:flex-start;animation:msgSlideIn .28s ease both;' + (isAI ? '' : 'flex-direction:row-reverse;');

        div.innerHTML = `
            <div style="width:26px;height:26px;border-radius:8px;background:${isAI ? 'linear-gradient(135deg,var(--blue),var(--purple))' : 'linear-gradient(135deg,var(--teal),var(--green))'};color:#fff;display:flex;align-items:center;justify-content:center;font-size:.68rem;flex-shrink:0;">
                ${isAI ? '🤖' : initial}
            </div>
            <div>
                <div style="background:${isAI ? 'var(--card-bg);border:1px solid var(--border);border-radius:10px 10px 10px 3px' : 'linear-gradient(135deg,var(--blue),var(--purple));border-radius:10px 10px 3px 10px'};padding:.55rem .8rem;font-size:.79rem;line-height:1.55;max-width:85%;color:${isAI ? 'var(--text)' : '#fff'}">
                    ${text.replace(/\n/g, '<br>')}
                </div>
                <div style="font-family:var(--mono);font-size:.56rem;color:var(--muted);margin-top:.2rem;${isAI ? '' : 'text-align:right'}">${time}</div>
            </div>`;

        // Insert before typing indicator
        const typing = document.getElementById('chat-typing');
        container.insertBefore(div, typing);
        container.scrollTop = container.scrollHeight;
    }

    function setTypingIndicator(visible) {
        const t = document.getElementById('chat-typing');
        if (t) {
            t.style.display = visible ? 'flex' : 'none';
            if (visible) {
                const c = document.getElementById('chat-messages');
                if (c) c.scrollTop = c.scrollHeight;
            }
        }
    }

    async function sendChatMessage() {
        const input   = document.getElementById('chat-input');
        const sendBtn = document.getElementById('chat-send-btn');
        const message = input.value.trim();
        if (!message) return;

        input.value = '';
        input.disabled = true;
        if (sendBtn) sendBtn.disabled = true;

        appendChatMessage('user', message);
        setTypingIndicator(true);

        try {
            const fd = new FormData();
            fd.append('message', message);
            fd.append('context', JSON.stringify(CHAT_CONTEXT));

            let reply = '';

            try {
                const res = await fetch('ai_chat.php', { method: 'POST', body: fd });
                if (!res.ok) throw new Error('ai_chat_missing');
                const data = await res.json();
                reply = data.reply || '';
            } catch (_fetchErr) {
                // ai_chat.php not found — generate smart local reply
                reply = generateLocalReply(message, CHAT_CONTEXT);
            }

            setTypingIndicator(false);
            appendChatMessage('assistant', reply || 'I could not process that. Please try again.');

        } catch (err) {
            setTypingIndicator(false);
            appendChatMessage('assistant', '⚠️ Connection error. Please refresh and try again.');
        }

        input.disabled = false;
        if (sendBtn) sendBtn.disabled = false;
        input.focus();
    }

    function sendChatChip(el) {
        const input = document.getElementById('chat-input');
        if (input) { input.value = el.textContent.trim(); sendChatMessage(); }
    }

    // ── Local rule-based reply engine (used when ai_chat.php is absent) ────────
    function generateLocalReply(msg, ctx) {
        const m   = msg.toLowerCase();
        const cat = ctx.categories || {};
        const catNames = {
            password:'Password Security', phishing:'Phishing Awareness',
            device:'Device Security', network:'Network Security',
            social_engineering:'Social Engineering', data_handling:'Data Handling'
        };
        const sorted  = Object.entries(cat).sort((a,b) => a[1]-b[1]);
        const weakest = sorted[0]  ? catNames[sorted[0][0]] + ' (' + sorted[0][1] + '%)' : 'an area';
        const best    = sorted[sorted.length-1] ? catNames[sorted[sorted.length-1][0]] + ' (' + sorted[sorted.length-1][1] + '%)' : 'your strongest area';

        if (/risk score|risk level|what.*risk/i.test(m))
            return 'Your risk score of <strong>' + ctx.riskScore + '/100</strong> is calculated from vulnerability gaps across all categories, weighted by real-world attack frequency. Yours is <strong style="color:' + ctx.riskColor + '">' + ctx.riskLevel + '</strong> because your overall score was <strong>' + ctx.score + '%</strong> with gaps in ' + weakest + '.';

        if (/biggest risk|main risk|worst|weakest/i.test(m))
            return 'Your biggest risk right now is <strong>' + weakest + '</strong>. This area has the lowest score and is heavily weighted — focus here first for the fastest risk reduction.';

        if (/improve|better|how.*score|boost/i.test(m))
            return 'To improve your score:\n\u2022 \uD83C\uDFAF Start with <strong>' + weakest + '</strong> — your weakest area\n\u2022 \uD83D\uDCDA Watch the recommended videos below\n\u2022 \uD83D\uDD04 Retake the assessment after training\n\u2022 \uD83D\uDD10 Enable MFA and use a password manager today';

        if (/priorit/i.test(m))
            return 'Prioritize in this order:\n1. <strong>' + (sorted[0] ? catNames[sorted[0][0]] : 'Lowest category') + '</strong>\n2. <strong>' + (sorted[1] ? catNames[sorted[1][0]] : 'Second lowest') + '</strong>\n3. Complete the video recommendations for each weak area.\n\nQuick win: Enable MFA on all accounts — this alone blocks 99% of automated attacks.';

        if (/password/i.test(m))
            return 'Your Password Security score is <strong>' + (cat.password ?? '?') + '%</strong>. Use a password manager (Bitwarden/1Password), never reuse passwords, enable MFA everywhere, and aim for 16+ character random passwords.';

        if (/phish/i.test(m))
            return 'Your Phishing Awareness score is <strong>' + (cat.phishing ?? '?') + '%</strong>. Always hover links before clicking, verify sender addresses carefully (not just display names), and never enter credentials from an emailed link.';

        if (/device/i.test(m))
            return 'Your Device Security score is <strong>' + (cat.device ?? '?') + '%</strong>. Enable automatic updates, use full-disk encryption, lock your screen after inactivity, and never plug in unknown USB drives.';

        if (/network/i.test(m))
            return 'Your Network Security score is <strong>' + (cat.network ?? '?') + '%</strong>. Use a VPN on public Wi-Fi, stick to HTTPS sites, and keep your router firmware updated with a strong unique password.';

        if (/social/i.test(m))
            return 'Your Social Engineering score is <strong>' + (cat.social_engineering ?? '?') + '%</strong>. Attackers exploit urgency. Always verify unusual requests through a separate channel before acting — even if the message looks legitimate.';

        if (/data|handling/i.test(m))
            return 'Your Data Handling score is <strong>' + (cat.data_handling ?? '?') + '%</strong>. Encrypt sensitive files before sharing, classify data by sensitivity, and use secure file-sharing tools instead of personal email.';

        if (/score|percent|result/i.test(m))
            return 'You scored <strong>' + ctx.score + '%</strong> overall — <strong style="color:' + ctx.riskColor + '">' + ctx.riskLevel + ' risk (' + ctx.riskScore + '/100)</strong>. Strongest area: ' + best + '. Focus on ' + weakest + ' for the biggest improvement.';

        if (/certif/i.test(m))
            return ctx.score >= 60
                ? 'Certificates are awarded for 60%+. \uD83C\uDF89 You qualify! Look for the Certificate section on this page.'
                : 'Certificates require 60%+. Your current score is ' + ctx.score + '%. Complete the recommended training and retake to earn it.';

        if (/retake|again|redo/i.test(m))
            return 'You can retake the assessment anytime from the dashboard. Study your weak areas — especially ' + weakest + ' — first so you can track real improvement.';

        if (/hello|hi |hey|help/i.test(m))
            return 'Hi! I can explain your score of <strong>' + ctx.score + '%</strong>, your <strong style="color:' + ctx.riskColor + '">' + ctx.riskLevel + ' risk (' + ctx.riskScore + '/100)</strong>, and give specific advice to improve. What would you like to know?';

        return 'Based on your <strong>' + ctx.score + '%</strong> score and <strong style="color:' + ctx.riskColor + '">' + ctx.riskLevel + ' risk (' + ctx.riskScore + '/100)</strong>, I recommend focusing on <strong>' + weakest + '</strong> first. Check the video recommendations for targeted training. Anything specific you\'d like help with?';
    }
</script>
</body>
</html>