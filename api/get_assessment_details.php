<?php
require_once '../includes/config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get user ID from request
$userId = $_GET['user_id'] ?? null;

if (!$userId || !is_numeric($userId)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid user ID']);
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

try {
    // Get the latest assessment for this user
    $assessmentQuery = "SELECT id, score, assessment_date FROM assessments 
                     WHERE vendor_id = :user_id 
                     ORDER BY assessment_date DESC 
                     LIMIT 1";
    $stmt = $db->prepare($assessmentQuery);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $latestAssessment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$latestAssessment) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No assessments found for this user']);
        exit();
    }

    $assessmentId = $latestAssessment['id'];

    // Get category scores from assessment answers
    $categoryQuery = "SELECT 
        category,
        COUNT(*) as total_questions,
        SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_answers,
        ROUND((SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 2) as category_score
        FROM assessment_answers 
        WHERE assessment_id = :assessment_id 
        GROUP BY category 
        ORDER BY category";

    $stmt = $db->prepare($categoryQuery);
    $stmt->bindParam(':assessment_id', $assessmentId);
    $stmt->execute();
    $categoryScores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get user info
    $userQuery = "SELECT full_name, email, store_name FROM users WHERE id = :user_id";
    $stmt = $db->prepare($userQuery);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Format response
    $response = [
        'success' => true,
        'user' => $user,
        'assessment' => [
            'id' => $latestAssessment['id'],
            'overall_score' => (int) $latestAssessment['score'],
            'assessment_date' => $latestAssessment['assessment_date'],
            'category_scores' => $categoryScores
        ]
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (PDOException $exception) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $exception->getMessage()]);
}
?>