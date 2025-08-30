<?php
session_start();
include 'db.php'; // Ensure this file correctly connects to the database

// Create the student_appointment table if it does not exist
$sql = "CREATE TABLE IF NOT EXISTS student_appointment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointmentDateTime DATETIME NOT NULL,
    studentName VARCHAR(255) NOT NULL,
    studentId VARCHAR(50) NOT NULL,
    phoneNumber VARCHAR(20) NOT NULL,
    studentEmail VARCHAR(255) NOT NULL,
    academicIssues BOOLEAN DEFAULT FALSE,
    academicIssueType VARCHAR(50) DEFAULT NULL,
    mentalHealth BOOLEAN DEFAULT FALSE,
    volunteer BOOLEAN DEFAULT FALSE,
    volunteerType VARCHAR(50) DEFAULT NULL,
    referred BOOLEAN DEFAULT FALSE,
    referralSource VARCHAR(50) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'Pending',
    username VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

if (!isset($_SESSION['username'])) {
    // Redirect to login page if not logged in
    header("Location: /php/signin.php");
    exit();
}
$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

// Get user profile picture
$profile_picture = "default.jpg"; // Default image if none exists
$stmt = $conn->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pic_result = $stmt->get_result();
$pic_data = $pic_result->fetch_assoc();

if ($pic_data && $pic_data['profile_picture']) {
    $profile_picture = $pic_data['profile_picture'];
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $appointmentDateTime = $_POST['appointmentDateTime'];
    $studentName = $_POST['studentName'];
    $studentId = $_POST['studentId'];
    $phoneNumber = $_POST['phoneNumber'];
    $studentEmail = $_POST['studentEmail'];
    $academicIssues = isset($_POST['academicIssues']) ? 1 : 0;
    $academicIssueType = $_POST['academicIssueType'] ?? NULL;
    $mentalHealth = isset($_POST['mentalHealth']) ? 1 : 0;
    $volunteer = isset($_POST['volunteer']) ? 1 : 0;
    $volunteerType = $_POST['volunteerType'] ?? NULL;
    $referred = isset($_POST['referred']) ? 1 : 0;
    $referralSource = $_POST['referralSource'] ?? NULL;

    // Check if appointment already exists for this student (any active appointment)
    $checkSql = "SELECT * FROM student_appointment WHERE studentId = ? AND status != 'Cancelled'";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $studentId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        // Student already has an appointment
        echo "<script>alert('You already set the appointment with this student'); window.history.back();</script>";
        $checkStmt->close();
        exit();
    }
    $checkStmt->close();

    // Check if there's already an appointment at the exact same date and time
    $checkTimeSql = "SELECT * FROM student_appointment WHERE appointmentDateTime = ? AND status != 'Cancelled'";
    $checkTimeStmt = $conn->prepare($checkTimeSql);
    $checkTimeStmt->bind_param("s", $appointmentDateTime);
    $checkTimeStmt->execute();
    $checkTimeResult = $checkTimeStmt->get_result();
    
    if ($checkTimeResult->num_rows > 0) {
        // There's already an appointment at this exact time
        echo "<script>alert('There is already an appointment scheduled at this exact time. Please choose a different time.'); window.history.back();</script>";
        $checkTimeStmt->close();
        exit();
    }
    $checkTimeStmt->close();

    $sql = "SELECT * FROM student_appointment WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>" . $row['appointmentDateTime'] . "</td>
                <td>" . $row['studentName'] . "</td>
                <td>" . $row['studentId'] . "</td>
                <td>" . $row['phoneNumber'] . "</td>
                <td>" . $row['studentEmail'] . "</td>
            </tr>";
    }

    
    $stmt = $conn->prepare("INSERT INTO student_appointment (username, appointmentDateTime, studentName, studentId, phoneNumber, studentEmail, academicIssues, academicIssueType, mentalHealth, volunteer, volunteerType, referred, referralSource) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssisissis", $username, $appointmentDateTime, $studentName, $studentId, $phoneNumber, $studentEmail, $academicIssues, $academicIssueType, $mentalHealth, $volunteer, $volunteerType, $referred, $referralSource);
    
    if ($stmt->execute()) {
        echo "<script>alert('Appointment successfully submitted!'); window.location.href='/php/studentappointment.php';</script>";
    } else {
        echo "<script>alert('Error submitting appointment.');</script>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Appointment | MyCounsel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="/css/sa.css">
    <script src="/js/sa.js"></script>
    <style>
        /* Additional styles for the profile picture */
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #4a6fa5;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
            overflow: hidden;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo-container">
            <div class="logo">
                Welcome to MyCounsel
                <img src="/picture/logo.png" alt="MyCounsel Logo" class="logo-img">
            </div>
        </div>
        <div class="nav-container">
            <div class="nav-left nav-links">
                <a href="/php/index.php">Home</a>
                <a href="/php/studentappointment.php" class="active">Student Appointment</a>
                <a href="/php/managescheduled.php">Manage Scheduled</a>
                <a href="/php/appointmentrecords.php">Appointment Records</a>
                <a href="/php/contact_us.php">Contact Us</a>
                <a href="/php/smart_helper.php">Smart Helper</a>
            </div>
            <div class="nav-right">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php if ($profile_picture && file_exists('uploads/profiles/' . $profile_picture)): ?>
                            <img src="uploads/profiles/<?php echo htmlspecialchars($profile_picture); ?>" alt="<?php echo htmlspecialchars($username); ?>">
                        <?php else: ?>
                            <?php echo isset($username) ? strtoupper(substr($username, 0, 1)) : "U"; ?>
                        <?php endif; ?>
                    </div>
                    <span class="username"><?php echo htmlspecialchars($username); ?></span>
                    <div class="dropdown-menu">
                        <?php if ($username !== "Guest") { ?>
                            <a href="/php/profile.php">Profile</a>
                            <a href="/php/logoutuser.php">Logout</a>
                        <?php } else { ?>
                            <a href="/php/signin.php">Login</a>
                            <a href="/php/signup.php">Register</a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <div class="container">

        <h1>Add Student Information</h1>
        <form id="appointmentForm" action="" method="POST">

            <div class="form-group">
                <label for="appointmentDateTime">Appointment Date & Time:</label>
                <input type="datetime-local" id="appointmentDateTime" name="appointmentDateTime" required>
            </div>

            <div class="form-section">
                <h2>Student Information</h2>
                
                <div class="form-group">
                    <label for="studentName">Student Name:</label>
                    <input type="text" id="studentName" name="studentName" required>
                </div>

                <div class="form-group">
                    <label for="studentId">Student ID:</label>
                    <input type="text" id="studentId" name="studentId" required>
                </div>

                <div class="form-group">
                    <label for="phoneNumber">Phone Number:</label>
                    <input type="tel" id="phoneNumber" name="phoneNumber" required>
                </div>

                <div class="form-group">
                    <label for="studentEmail">Student Email (KPTM):</label>
                    <input type="email" id="studentEmail" name="studentEmail" required>
                </div>
            </div>

            <div class="form-section">
                <h2>Issue Categories</h2>
                
                <div class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="academicIssues" name="academicIssues">
                        <label for="academicIssues">Academic Issues</label>
                        <select id="academicIssueType" name="academicIssueType" disabled>
                            <option value="">Select academic issue type</option>
                            <option value="Warning Letter Level 1">Warning Letter Level 1</option>
                            <option value="Warning Letter Level 2">Warning Letter Level 2</option>
                            <option value="BARRED">BARRED</option>
                        </select>
                    </div>

                    <div class="checkbox-item">
                        <input type="checkbox" id="mentalHealth" name="mentalHealth">
                        <label for="mentalHealth">Mental Health</label>
                    </div>

                    <div class="checkbox-item">
                        <input type="checkbox" id="volunteer" name="volunteer">
                        <label for="volunteer">Volunteer</label>
                        <select id="volunteerType" name="volunteerType" disabled>
                            <option value="">Select volunteer type</option>
                            <option value="Personal">Personal</option>
                            <option value="Career">Career</option>
                            <option value="Financial">Financial</option>
                            <option value="Social">Social</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="checkbox-item">
                        <input type="checkbox" id="referred" name="referred">
                        <label for="referred">Referred</label>
                        <select id="referralSource" name="referralSource" disabled>
                            <option value="">Select referral source</option>
                            <option value="Mentor">Mentor</option>
                            <option value="Lecturer">Lecturer</option>
                            <option value="Parents">Parents</option>
                        </select>
                    </div>
                </div>
            </div>

            <button type="submit" class="submit-btn">Submit</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</body>
</html>