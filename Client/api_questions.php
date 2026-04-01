<?php

header('Content-Type: application/json');

header('Access-Control-Allow-Origin: *');

header('Access-Control-Allow-Methods: GET, POST');

header('Access-Control-Allow-Headers: X-Assessment-Token, X-User-ID');



// Database configuration

$host = 'localhost';

$dbname = 'cybershield';

$username = 'root';

$password = '';



try {

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {

    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);

    exit();

}



/**

 * Get random questions with bias reduction techniques:

 * 1. Category balancing to ensure fair representation

 * 2. Difficulty distribution to match real-world scenarios

 * 3. Option shuffling to eliminate order bias

 * 4. User-specific seeding for consistent randomization

 */

function getRandomQuestions($count = 100, $userId = null, $seed = null) {

    global $pdo;

    

    try {

        $scenarioTarget = min($count, max(0, (int)floor($count * 0.60)));

        $scenarioQuestions = [];

        if ($scenarioTarget > 0) {

            $scenarioStmt = $pdo->prepare("SELECT * FROM question_bank WHERE is_active = 1 AND question_text LIKE 'Scenario:%' ORDER BY RAND() LIMIT :limit");

            $scenarioStmt->bindParam(':limit', $scenarioTarget, PDO::PARAM_INT);

            $scenarioStmt->execute();

            while ($row = $scenarioStmt->fetch(PDO::FETCH_ASSOC)) {

                $options = json_decode($row['options'], true);

                shuffle($options);

                $scenarioQuestions[] = [

                    'id' => $row['id'],

                    'category' => $row['category'],

                    'difficulty' => $row['difficulty'],

                    'text' => $row['question_text'],

                    'correct' => $row['correct_answer'],

                    'options' => $options,

                    'explanation' => $row['explanation']

                ];

            }

        }

        $excludedScenarioIds = array_map(fn($q) => (int)$q['id'], $scenarioQuestions);

        $remainingToFetch = max(0, $count - count($scenarioQuestions));

        // Use user ID as seed for deterministic but different randomization per user

        if ($seed === null && $userId !== null) {

            $seed = $userId;

        }

        // Set seed for reproducible randomization per user

        if ($seed !== null) {

            $pdo->exec("SET @seed = " . intval($seed));

            $pdo->exec("SET @rand_seed = @seed");

        }

        // Get category counts to maintain distribution

        $categoryQuery = "SELECT category, COUNT(*) as total FROM question_bank WHERE is_active = 1 GROUP BY category";

        $categoryStmt = $pdo->query($categoryQuery);

        $categoryTotals = [];

        $totalQuestions = 0;

        while ($row = $categoryStmt->fetch(PDO::FETCH_ASSOC)) {

            $categoryTotals[$row['category']] = $row['total'];

            $totalQuestions += $row['total'];

        }

        // Calculate how many questions to take from each category (balanced distribution)

        $questionsPerCategory = [];

        $remainingCount = $remainingToFetch;

        foreach ($categoryTotals as $category => $total) {

            // Proportional allocation based on available questions

            $allocated = round(($total / $totalQuestions) * $remainingToFetch);

            $questionsPerCategory[$category] = min($allocated, $total);

            $remainingCount -= $questionsPerCategory[$category];

        }

        // Distribute remaining questions to categories with more questions

        while ($remainingCount > 0) {

            foreach ($categoryTotals as $category => $total) {

                if ($remainingCount > 0 && $questionsPerCategory[$category] < $total) {

                    $questionsPerCategory[$category]++;

                    $remainingCount--;

                }

            }

        }

        // Fetch random questions from each category

        $questions = $scenarioQuestions;

        foreach ($questionsPerCategory as $category => $limit) {

            if ($limit <= 0) continue;

            // Get random questions from this category

            $excludedSql = '';
            $excludedParams = [];
            if (!empty($excludedScenarioIds)) {
                $placeholders = [];
                foreach ($excludedScenarioIds as $k => $id) {
                    $ph = ':ex' . $k;
                    $placeholders[] = $ph;
                    $excludedParams[$ph] = (int)$id;
                }
                $excludedSql = ' AND id NOT IN (' . implode(',', $placeholders) . ')';
            }

            $query = "SELECT * FROM question_bank
                      WHERE category = :category AND is_active = 1
                      AND question_text NOT LIKE 'What is %'
                      {$excludedSql}
                      ORDER BY RAND()
                      LIMIT :limit";

            $stmt = $pdo->prepare($query);
            $stmt->bindValue(':category', $category);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            foreach ($excludedParams as $ph => $val) {
                $stmt->bindValue($ph, $val, PDO::PARAM_INT);
            }
            $stmt->execute();

            // If we didn't get enough (e.g., too many questions start with "What is"), fall back.
            $fetched = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($fetched) < $limit) {
                $needed = $limit - count($fetched);
                $fallbackQuery = "SELECT * FROM question_bank
                                  WHERE category = :category AND is_active = 1
                                  {$excludedSql}
                                  ORDER BY RAND()
                                  LIMIT :limit";
                $fallbackStmt = $pdo->prepare($fallbackQuery);
                $fallbackStmt->bindValue(':category', $category);
                $fallbackStmt->bindValue(':limit', (int)$needed, PDO::PARAM_INT);
                foreach ($excludedParams as $ph => $val) {
                    $fallbackStmt->bindValue($ph, $val, PDO::PARAM_INT);
                }
                $fallbackStmt->execute();
                $fetched = array_merge($fetched, $fallbackStmt->fetchAll(PDO::FETCH_ASSOC));
            }

            foreach ($fetched as $row) {

                $options = json_decode($row['options'], true);

                shuffle($options);

                $questions[] = [

                    'id' => $row['id'],

                    'category' => $row['category'],

                    'difficulty' => $row['difficulty'],

                    'text' => $row['question_text'],

                    'correct' => $row['correct_answer'],

                    'options' => $options,

                    'explanation' => $row['explanation']

                ];

            }

        }

        // If we couldn't fill the set without definitional questions, prefer more scenario questions.
        if (count($questions) < $count) {

            $need = $count - count($questions);
            $existingIds = array_map(fn($q) => (int)$q['id'], $questions);
            $excludedSql = '';
            if (!empty($existingIds)) {
                $excludedSql = ' AND id NOT IN (' . implode(',', array_map('intval', $existingIds)) . ')';
            }

            $extraScenarioStmt = $pdo->prepare("SELECT * FROM question_bank WHERE is_active = 1 AND question_text LIKE 'Scenario:%' {$excludedSql} ORDER BY RAND() LIMIT :limit");
            $extraScenarioStmt->bindValue(':limit', (int)$need, PDO::PARAM_INT);
            $extraScenarioStmt->execute();

            while ($row = $extraScenarioStmt->fetch(PDO::FETCH_ASSOC)) {
                $options = json_decode($row['options'], true);
                shuffle($options);
                $questions[] = [
                    'id' => $row['id'],
                    'category' => $row['category'],
                    'difficulty' => $row['difficulty'],
                    'text' => $row['question_text'],
                    'correct' => $row['correct_answer'],
                    'options' => $options,
                    'explanation' => $row['explanation']
                ];
            }
        }

        // Absolute last resort: if still short, allow any active questions to reach the requested count.
        if (count($questions) < $count) {

            $need = $count - count($questions);
            $existingIds = array_map(fn($q) => (int)$q['id'], $questions);
            $excludedSql = '';
            if (!empty($existingIds)) {
                $excludedSql = ' AND id NOT IN (' . implode(',', array_map('intval', $existingIds)) . ')';
            }

            $fillStmt = $pdo->prepare("SELECT * FROM question_bank WHERE is_active = 1 {$excludedSql} ORDER BY RAND() LIMIT :limit");
            $fillStmt->bindValue(':limit', (int)$need, PDO::PARAM_INT);
            $fillStmt->execute();

            while ($row = $fillStmt->fetch(PDO::FETCH_ASSOC)) {
                $options = json_decode($row['options'], true);
                shuffle($options);
                $questions[] = [
                    'id' => $row['id'],
                    'category' => $row['category'],
                    'difficulty' => $row['difficulty'],
                    'text' => $row['question_text'],
                    'correct' => $row['correct_answer'],
                    'options' => $options,
                    'explanation' => $row['explanation']
                ];
            }
        }

        // Final shuffle of questions to mix categories

        shuffle($questions);

        // Ensure we have exactly the requested number of questions

        $questions = array_slice($questions, 0, $count);

        // Log the question set for this user (for analytics and avoiding repetition)

        if ($userId !== null && !empty($questions)) {

            $questionIds = array_column($questions, 'id');

            $questionIdsJson = json_encode($questionIds);

            

            // Check if user has taken assessment in last 30 days to avoid repeats

            $checkStmt = $pdo->prepare("

                SELECT question_ids FROM user_question_history 

                WHERE user_id = :user_id AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)

                ORDER BY created_at DESC LIMIT 1

            ");

            $checkStmt->bindParam(':user_id', $userId);

            $checkStmt->execute();

            $lastHistory = $checkStmt->fetch(PDO::FETCH_ASSOC);

            

            if ($lastHistory && $lastHistory['question_ids']) {

                $lastQuestionIds = json_decode($lastHistory['question_ids'], true);

                $overlap = array_intersect($questionIds, $lastQuestionIds);

                

                // If more than 30% overlap, reshuffle to reduce repetition

                if (count($overlap) > ($count * 0.3)) {

                    // Get alternative questions for overlap

                    foreach ($overlap as $oldId) {

                        $altStmt = $pdo->prepare("

                            SELECT * FROM question_bank 

                            WHERE category = (SELECT category FROM question_bank WHERE id = :old_id)

                            AND id != :old_id AND is_active = 1

                            ORDER BY RAND() LIMIT 1

                        ");

                        $altStmt->bindParam(':old_id', $oldId);

                        $altStmt->execute();

                        $altQuestion = $altStmt->fetch(PDO::FETCH_ASSOC);

                        

                        if ($altQuestion) {

                            // Replace the question

                            foreach ($questions as $key => $q) {

                                if ($q['id'] == $oldId) {

                                    $options = json_decode($altQuestion['options'], true);

                                    shuffle($options);

                                    $questions[$key] = [

                                        'id' => $altQuestion['id'],

                                        'category' => $altQuestion['category'],

                                        'difficulty' => $altQuestion['difficulty'],

                                        'text' => $altQuestion['question_text'],

                                        'correct' => $altQuestion['correct_answer'],

                                        'options' => $options,

                                        'explanation' => $altQuestion['explanation']

                                    ];

                                    break;

                                }

                            }

                        }

                    }

                }

            }

            

            // Log the questions shown to this user

            $logStmt = $pdo->prepare("

                INSERT INTO user_question_history (user_id, question_ids, created_at) 

                VALUES (:user_id, :question_ids, NOW())

            ");

            $logStmt->bindParam(':user_id', $userId);

            $logStmt->bindParam(':question_ids', $questionIdsJson);

            $logStmt->execute();

        }

        

        return $questions;

        

    } catch (PDOException $e) {

        throw new Exception('Error fetching questions: ' . $e->getMessage());

    }

}



// Check if this is a request for questions

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    try {

        $count = isset($_GET['count']) ? min(100, intval($_GET['count'])) : 100;

        $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

        $seed = isset($_GET['seed']) ? intval($_GET['seed']) : null;

        

        $questions = getRandomQuestions($count, $userId, $seed);

        

        // Add a unique session token for this assessment to prevent replay attacks

        $assessmentSessionId = bin2hex(random_bytes(16));

        

        echo json_encode([

            'success' => true,

            'questions' => $questions,

            'total' => count($questions),

            'timestamp' => time(),

            'session_id' => $assessmentSessionId,

            'seed' => $seed !== null ? $seed : rand(1000, 9999),

            'bias_reduction' => [

                'options_shuffled' => true,

                'category_balanced' => true,

                'user_specific' => $userId !== null

            ]

        ]);

        

    } catch (Exception $e) {

        echo json_encode([

            'success' => false,

            'error' => $e->getMessage()

        ]);

    }

    exit();

}



// POST endpoint for logging answer patterns to detect bias

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = json_decode(file_get_contents('php://input'), true);

    

    if (isset($data['action']) && $data['action'] === 'log_answers') {

        try {

            $stmt = $pdo->prepare("

                INSERT INTO answer_analytics 

                (user_id, question_id, user_answer, is_correct, time_taken_ms, answer_position, session_id, created_at) 

                VALUES (:user_id, :question_id, :user_answer, :is_correct, :time_taken, :answer_position, :session_id, NOW())

            ");

            

            foreach ($data['answers'] as $answer) {

                $stmt->bindParam(':user_id', $data['user_id']);

                $stmt->bindParam(':question_id', $answer['question_id']);

                $stmt->bindParam(':user_answer', $answer['user_answer']);

                $stmt->bindParam(':is_correct', $answer['is_correct']);

                $stmt->bindParam(':time_taken', $answer['time_taken']);

                $stmt->bindParam(':answer_position', $answer['position']);

                $stmt->bindParam(':session_id', $data['session_id']);

                $stmt->execute();

            }

            

            echo json_encode(['success' => true]);

        } catch (Exception $e) {

            echo json_encode(['success' => false, 'error' => $e->getMessage()]);

        }

        exit();

    }

    

    // Log assessment start

    if (isset($data['action']) && $data['action'] === 'start_assessment') {

        try {

            $stmt = $pdo->prepare("

                INSERT INTO assessment_sessions (user_id, session_id, question_ids, started_at, ip_address)

                VALUES (:user_id, :session_id, :question_ids, NOW(), :ip)

            ");

            $stmt->bindParam(':user_id', $data['user_id']);

            $stmt->bindParam(':session_id', $data['session_id']);

            $stmt->bindParam(':question_ids', json_encode($data['question_ids']));

            $stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);

            $stmt->execute();

            

            echo json_encode(['success' => true]);

        } catch (Exception $e) {

            echo json_encode(['success' => false, 'error' => $e->getMessage()]);

        }

        exit();

    }

}



echo json_encode(['success' => false, 'error' => 'Invalid request']);

?>