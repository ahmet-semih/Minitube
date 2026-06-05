<?php
// Author: Ahmet Semih Gümus
// login.php — Handles POST authentication, redirects back to login.html on failure.

$servername = 'localhost';
$username   = 'root';
$password   = 'mysql';
$database   = 'ahmetsemih_gumus';

$loginPage = '../html/login.html';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $loginPage);
    exit;
}

$inputUsername = trim($_POST['username'] ?? '');
$inputPassword = $_POST['password'] ?? '';

// Empty field check
if ($inputUsername === '' || $inputPassword === '') {
    header('Location: ' . $loginPage . '?error=empty&username=' . urlencode($inputUsername));
    exit;
}

// DB connection
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    header('Location: ' . $loginPage . '?error=db');
    exit;
}
$conn->set_charset('utf8mb4');

// Look up user
$stmt = $conn->prepare('SELECT user_id, password FROM USERS WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $inputUsername);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

// Verify password
if (!$user) {
    header('Location: ' . $loginPage . '?error=credentials&username=' . urlencode($inputUsername));
    exit;
}

$storedPassword = (string) $user['password'];
$passwordMatches = password_verify($inputPassword, $storedPassword) || hash_equals($storedPassword, $inputPassword);

if (!$passwordMatches) {
    header('Location: ' . $loginPage . '?error=credentials&username=' . urlencode($inputUsername));
    exit;
}

// Success
header('Location: ../html/feed.html?user_id=' . (int) $user['user_id']);
exit;
?>