<?php
session_start();

echo "<h2>Session Test</h2>";

echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session status: " . session_status() . "</p>";

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['test_time'] = date('Y-m-d H:i:s');
    echo "<p>Set session user_id = 1</p>";
} else {
    echo "<p>Session user_id already set: " . $_SESSION['user_id'] . "</p>";
    echo "<p>Session test_time: " . ($_SESSION['test_time'] ?? 'not set') . "</p>";
}

echo "<p>Session data: <pre>" . print_r($_SESSION, true) . "</pre></p>";

echo "<p><a href='Client/profile.php'>Go to Profile</a></p>";
echo "<p><a href='debug_profile.php'>Go to Debug Test</a></p>";
?>
