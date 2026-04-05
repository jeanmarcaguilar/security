<?php
/**
 * test_assessment_update.php
 * Test script to verify assessment update functionality
 */

require_once 'includes/config.php';
session_start();

// Simulate a logged-in user (you'll need to adjust this ID)
$_SESSION['user_id'] = 1; // Change this to an actual user ID in your database

$database = new Database();
$db = $database->getConnection();

// Test data
$test_data = [
    'score' => 85,
    'rank' => 'B',
    'password_score' => 90,
    'phishing_score' => 80,
    'device_score' => 85,
    'network_score' => 88,
    'social_engineering_score' => 82,
    'data_handling_score' => 86,
    'time_spent' => 1800,
    'questions_answered' => 100,
    'total_questions' => 100,
    'assessment_token' => bin2hex(random_bytes(32)),
    'answers_json' => json_encode([]),
    'session_id' => 'test_session_' . time()
];

echo "<h2>Testing Assessment Update Functionality</h2>";

// Check initial state
$stmt = $db->prepare("SELECT COUNT(*) as count FROM assessments WHERE vendor_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$initial_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
echo "<p>Initial assessment count: {$initial_count}</p>";

// First submission (should create new assessment)
echo "<h3>First Assessment Submission</h3>";
$ch = curl_init('http://localhost/security/Client/save_assessment.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $test_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
echo "<p>Response: " . json_encode($result) . "</p>";

// Check count after first submission
$stmt = $db->prepare("SELECT COUNT(*) as count FROM assessments WHERE vendor_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$after_first = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
echo "<p>Assessment count after first submission: {$after_first}</p>";

// Get the assessment ID
$stmt = $db->prepare("SELECT id, score FROM assessments WHERE vendor_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$first_assessment = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<p>First assessment - ID: {$first_assessment['id']}, Score: {$first_assessment['score']}</p>";

// Second submission (should update existing assessment)
echo "<h3>Second Assessment Submission (Update)</h3>";
$test_data['score'] = 92; // Different score
$test_data['rank'] = 'A'; // Different rank
$test_data['assessment_token'] = bin2hex(random_bytes(32)); // New token

$ch = curl_init('http://localhost/security/Client/save_assessment.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $test_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
echo "<p>Response: " . json_encode($result) . "</p>";

// Check count after second submission
$stmt = $db->prepare("SELECT COUNT(*) as count FROM assessments WHERE vendor_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$after_second = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
echo "<p>Assessment count after second submission: {$after_second}</p>";

// Get the updated assessment
$stmt = $db->prepare("SELECT id, score FROM assessments WHERE vendor_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$second_assessment = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<p>Updated assessment - ID: {$second_assessment['id']}, Score: {$second_assessment['score']}</p>";

// Verify results
if ($after_first == 1 && $after_second == 1 && $first_assessment['id'] == $second_assessment['id']) {
    echo "<h3 style='color: green;'>✅ SUCCESS: No duplication occurred, assessment was updated correctly</h3>";
} else {
    echo "<h3 style='color: red;'>❌ FAILURE: Duplication detected or update failed</h3>";
}

echo "<p>Score changed from {$first_assessment['score']} to {$second_assessment['score']}</p>";
?>
