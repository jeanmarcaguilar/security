<?php
/**
 * ai-insights.php — Secure server-side proxy for Anthropic API
 * Called via fetch() from result.php frontend JS.
 */
session_start();
require_once '../includes/config.php';
header('Content-Type: application/json');

// ── Auth guard ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// ── Only accept POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// ── Read + validate JSON body ─────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);
if (!$body || !isset($body['score'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body']);
    exit();
}

// ── Your Anthropic API key ────────────────────────────────────────────────────
$apiKey = defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['error' => 'API key not configured. Define ANTHROPIC_API_KEY in config.php or as an environment variable.']);
    exit();
}

// ── Sanitise inputs ───────────────────────────────────────────────────────────
$score = intval($body['score'] ?? 0);
$rank = substr(preg_replace('/[^A-Da-d]/', '', $body['rank'] ?? 'D'), 0, 1) ?: 'D';
$catScores = [];
$allowedCats = ['password', 'phishing', 'device', 'network', 'social_engineering', 'data_handling'];
foreach ($allowedCats as $cat) {
    $catScores[$cat] = intval($body['categoryScores'][$cat] ?? 0);
}
$questionsAnswered = intval($body['questionsAnswered'] ?? 0);
$totalQuestions = intval($body['totalQuestions'] ?? 20);
$timeSpent = intval($body['timeSpent'] ?? 0);

// Sanitise missed questions — cap at 15 to keep prompt size reasonable
$rawMissed = $body['incorrectAnswers'] ?? [];
$missed = [];
foreach (array_slice($rawMissed, 0, 15) as $item) {
    $missed[] = [
        'question' => substr(strip_tags($item['question'] ?? ''), 0, 200),
        'correct_answer' => substr(strip_tags($item['correct_answer'] ?? ''), 0, 200),
        'explanation' => substr(strip_tags($item['explanation'] ?? ''), 0, 300),
        'category' => substr(preg_replace('/[^a-z_]/', '', $item['category'] ?? ''), 0, 50),
        'difficulty' => substr(preg_replace('/[^a-z]/', '', $item['difficulty'] ?? ''), 0, 20),
    ];
}

// ── Build prompt ──────────────────────────────────────────────────────────────
$weakCats = [];
foreach ($catScores as $cat => $cs) {
    if ($cs < 70)
        $weakCats[] = str_replace('_', ' ', $cat) . " ({$cs}%)";
}
$weakStr = $weakCats ? implode(', ', $weakCats) : 'none — great work across all categories';

$missedLines = count($missed) > 0
    ? implode("\n", array_map(
        fn($a) =>
        "  - [{$a['category']}] Q: \"{$a['question']}\" | Correct: \"{$a['correct_answer']}\" | Hint: {$a['explanation']}",
        $missed
    ))
    : '  None — all questions answered correctly.';

$catLines = implode("\n", array_map(
    fn($cat, $cs) => "  - " . str_replace('_', ' ', $cat) . ": {$cs}%",
    array_keys($catScores),
    $catScores
));

$minutes = round($timeSpent / 60);

$prompt = <<<PROMPT
You are CyberShield AI, an expert cybersecurity awareness analyst. Analyze this user's real assessment results and provide highly personalized, actionable security insights.

ASSESSMENT DATA:
- Overall Score: {$score}%
- Rank: {$rank}
- Questions answered: {$questionsAnswered}/{$totalQuestions}
- Time spent: {$minutes} minutes

CATEGORY SCORES:
{$catLines}

WEAK CATEGORIES (below 70%): {$weakStr}

MISSED QUESTIONS:
{$missedLines}

Respond ONLY with a valid JSON object — no markdown, no code fences, no explanation text outside the JSON.

Required JSON structure:
{
  "risk_level": "critical|high|moderate|low",
  "risk_label": "e.g. High Risk – Improvement Needed",
  "risk_detail": "2 sentences describing this specific user's security posture based on their actual scores and missed topics.",
  "recommendations": [
    {
      "emoji": "one emoji",
      "title": "short bold title",
      "body": "2-3 sentences of specific advice referencing the user's actual scores and the topics they missed"
    }
  ],
  "videos": [
    {
      "youtube_id": "11-character YouTube video ID from the verified list below",
      "title": "video title",
      "category": "which weakness this covers",
      "why": "one sentence: why this video is relevant to THIS user's specific weaknesses"
    }
  ],
  "quick_wins": [
    "Actionable tip 1 — be specific to the user's weak areas",
    "Actionable tip 2",
    "Actionable tip 3"
  ]
}

RULES — follow exactly:
1. risk_level: score<40=critical, 40-59=high, 60-79=moderate, >=80=low
2. recommendations: exactly 4-6 items. Each MUST mention a specific score or missed question topic from the data above.
3. videos: exactly 3-4 items. Select the most relevant IDs from this VERIFIED list based on the user's weak categories:
   - aEmXedWKxeQ  → password security    | "How to Choose a Password" – CGP Grey
   - XBkzBrXlle0  → phishing awareness   | "What is Phishing?" – CommonCraft
   - bPVaOlJ6ln0  → device security      | "Cybersecurity" – CrashCourse Computer Science #31 (PBS)
   - sdpxddDzXfE  → network security     | "Network Security Explained"
   - lc7scxvKQOo  → social engineering   | "Social Engineering Attacks" – IBM Technology
   - u-yLGIH0oFM  → data privacy         | "Data Protection Best Practices" – IBM Technology
   - inWWhr5tnEA  → cybersecurity basics | "What is Cybersecurity?" – IBM Technology
   - Dk-ZqQ-bfy4  → cyber threats        | "Top Cybersecurity Threats" – IBM Technology
   
   Prioritise videos for the user's weakest categories first. Use ONLY these IDs.
4. quick_wins: exactly 3 short, practical actions the user can take today.
5. Output ONLY the JSON object — nothing before or after it.
PROMPT;

// ── Call Anthropic API via cURL ───────────────────────────────────────────────
$payload = json_encode([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1200,
    'messages' => [['role' => 'user', 'content' => $prompt]]
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(502);
    echo json_encode(['error' => 'cURL error: ' . $curlErr]);
    exit();
}

if ($httpCode !== 200) {
    http_response_code(502);
    $errData = json_decode($response, true);
    echo json_encode(['error' => 'Anthropic API error: ' . ($errData['error']['message'] ?? "HTTP {$httpCode}")]);
    exit();
}

$apiData = json_decode($response, true);
$rawText = '';
foreach ($apiData['content'] ?? [] as $block) {
    if ($block['type'] === 'text')
        $rawText .= $block['text'];
}

// Strip any accidental markdown fences
$rawText = trim(preg_replace('/^```(?:json)?\s*/i', '', preg_replace('/\s*```$/', '', trim($rawText))));

// Validate it's real JSON before forwarding
$decoded = json_decode($rawText, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(502);
    echo json_encode(['error' => 'AI returned invalid JSON. Please try again.']);
    exit();
}

// ── Forward clean JSON to the browser ────────────────────────────────────────
echo json_encode($decoded);