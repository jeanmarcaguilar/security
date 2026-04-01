<?php
/**
 * save_assessment.php
 * Receives POST data from assessment.php, saves to DB, returns JSON.
 * Uses $_SESSION['user_id'] — never trusts client-side vendor_id.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

// ── DB connection ─────────────────────────────────────────────────────────────
$host   = 'localhost';
$dbname = 'cybershield';
$dbuser = 'root';
$dbpass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed: ' . $e->getMessage()]);
    exit();
}

// ── Validate token (prevent double-submit) ────────────────────────────────────
$submitted_token = $_POST['assessment_token'] ?? '';
if (empty($submitted_token)) {
    echo json_encode(['success' => false, 'error' => 'Missing assessment token']);
    exit();
}

// Check token not already used
$tok_check = $pdo->prepare("SELECT id FROM assessments WHERE assessment_token = ? LIMIT 1");
$tok_check->execute([$submitted_token]);
if ($tok_check->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Assessment already submitted']);
    exit();
}

// ── Read & sanitize inputs ────────────────────────────────────────────────────
$user_id                  = (int) $_SESSION['user_id'];   // ALWAYS from session
$score                    = min(100, max(0, (int)($_POST['score']                    ?? 0)));
$rank                     = in_array($_POST['rank'] ?? '', ['A','B','C','D','F']) ? $_POST['rank'] : 'F';
$password_score           = min(100, max(0, (int)($_POST['password_score']           ?? 0)));
$phishing_score           = min(100, max(0, (int)($_POST['phishing_score']           ?? 0)));
$device_score             = min(100, max(0, (int)($_POST['device_score']             ?? 0)));
$network_score            = min(100, max(0, (int)($_POST['network_score']            ?? 0)));
$social_engineering_score = min(100, max(0, (int)($_POST['social_engineering_score'] ?? 0)));
$data_handling_score      = min(100, max(0, (int)($_POST['data_handling_score']      ?? 0)));
$time_spent               = max(0, (int)($_POST['time_spent']        ?? 0));
$questions_answered       = max(0, (int)($_POST['questions_answered'] ?? 0));
$total_questions          = max(1, (int)($_POST['total_questions']    ?? 100));
$assessment_date          = date('Y-m-d H:i:s'); // always server-side

// Per-question answers payload (JSON string from JS)
$answers_json = $_POST['answers_json'] ?? '[]';
$answers      = json_decode($answers_json, true) ?: [];

try {
    $pdo->beginTransaction();

    // ── 1. Insert assessment ──────────────────────────────────────────────────
    $ins = $pdo->prepare("
        INSERT INTO assessments (
            vendor_id, score, rank,
            password_score, phishing_score, device_score, network_score,
            social_engineering_score, data_handling_score,
            time_spent, questions_answered, total_questions,
            assessment_date, assessment_token
        ) VALUES (
            :uid, :score, :rank,
            :pw, :ph, :dev, :net,
            :se, :dh,
            :ts, :qa, :tq,
            :adate, :token
        )
    ");
    $ins->execute([
        ':uid'   => $user_id,
        ':score' => $score,
        ':rank'  => $rank,
        ':pw'    => $password_score,
        ':ph'    => $phishing_score,
        ':dev'   => $device_score,
        ':net'   => $network_score,
        ':se'    => $social_engineering_score,
        ':dh'    => $data_handling_score,
        ':ts'    => $time_spent,
        ':qa'    => $questions_answered,
        ':tq'    => $total_questions,
        ':adate' => $assessment_date,
        ':token' => $submitted_token,
    ]);
    $assessment_id = $pdo->lastInsertId();

    // ── 2. Save per-question answers (for AI missed-question analysis) ────────
    if (!empty($answers)) {
        $ans_ins = $pdo->prepare("
            INSERT INTO assessment_answers
                (assessment_id, question_id, question_text, user_answer, correct_answer, is_correct, category)
            VALUES
                (:aid, :qid, :qtxt, :uans, :cans, :correct, :cat)
        ");
        foreach ($answers as $a) {
            $ans_ins->execute([
                ':aid'     => $assessment_id,
                ':qid'     => $a['question_id']     ?? 0,
                ':qtxt'    => $a['question_text']    ?? '',
                ':uans'    => $a['user_answer']      ?? '',
                ':cans'    => $a['correct_answer']   ?? '',
                ':correct' => (int)($a['is_correct'] ?? 0),
                ':cat'     => $a['category']         ?? 'general',
            ]);
        }
    }

    // ── 3. Update user summary stats ─────────────────────────────────────────
    // Use INSERT … ON DUPLICATE KEY so it works even if column doesn't exist
    $pdo->prepare("
        UPDATE users
        SET last_assessment_score = :score,
            last_assessment_date  = :adate,
            total_assessments     = COALESCE(total_assessments, 0) + 1
        WHERE id = :uid
    ")->execute([':score' => $score, ':adate' => $assessment_date, ':uid' => $user_id]);

    // ── 4. Activity log (graceful — skip if table missing) ───────────────────
    try {
        $pdo->prepare("
            INSERT INTO activity_log (user_id, action_type, action_description, ip_address)
            VALUES (:uid, 'assessment', :desc, :ip)
        ")->execute([
            ':uid'  => $user_id,
            ':desc' => "Completed assessment — Score: {$score}% Rank: {$rank}",
            ':ip'   => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (PDOException $ignored) {}

    $pdo->commit();

    echo json_encode([
        'success'       => true,
        'assessment_id' => $assessment_id,
        'message'       => 'Assessment saved successfully',
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("save_assessment error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}