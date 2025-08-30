<?php
// get_current_username.php - Helper file to get current username from database

// Database connection
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "mycounsel";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get current username from database
$userSql = "SELECT username FROM users WHERE user_id = ?";
$stmt = $conn->prepare($userSql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userResult = $stmt->get_result();
$userData = $userResult->fetch_assoc();

if ($userData) {
    $username = $userData['username'];
    // Update session with current username
    $_SESSION['username'] = $username;
    
    echo json_encode([
        'username' => $username,
        'user_id' => $user_id,
        'success' => true
    ]);
} else {
    echo json_encode(['error' => 'User not found']);
}

$stmt->close();
$conn->close();
?>