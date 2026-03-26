<?php
// Simple API test
require_once '../config.php';
session_start();

// Test database connection
$database = new Database();
$db = $database->getConnection();

if ($db) {
    echo "Database connection: SUCCESS<br>";

    // Test if tables exist
    $tables = ['vendors', 'vendor_assessments', 'users', 'activity_log'];
    foreach ($tables as $table) {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->rowCount() > 0) {
            echo "Table '$table': EXISTS<br>";
        } else {
            echo "Table '$table': MISSING<br>";
        }
    }

    // Test session
    if (isset($_SESSION['user_id'])) {
        echo "Session: ACTIVE (User ID: " . $_SESSION['user_id'] . ")<br>";
    } else {
        echo "Session: NOT ACTIVE<br>";
    }

} else {
    echo "Database connection: FAILED<br>";
}

// Test API endpoint
echo "<br>Testing API endpoint:<br>";
echo "<a href='api.php?action=get_stats'>Test get_stats</a><br>";
echo "<a href='api.php?action=get_vendors'>Test get_vendors</a><br>";
echo "<a href='api.php?action=get_assessments'>Test get_assessments</a><br>";
?>