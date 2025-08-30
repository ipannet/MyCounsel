<?php
session_start();
include 'db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Validate input
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing appointment ID']);
    exit();
}

$appointment_id = $_GET['id'];

// Get appointment data
$stmt = $conn->prepare("SELECT * FROM student_appointment WHERE studentId = ?");
$stmt->bind_param("s", $appointment_id);
$stmt->execute();
$result = $stmt->get_result();

header('Content-Type: application/json');

if ($row = $result->fetch_assoc()) {
    // Format date for the frontend
    echo json_encode(['success' => true, 'appointment' => $row]);
} else {
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
}

$stmt->close();
$conn->close();
?>