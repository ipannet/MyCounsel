<?php
$servername = "localhost";
$username = "root"; // Change if needed
$password = ""; // Change if your MySQL has a password
$dbname = "mycounsel"; // Your database name

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
