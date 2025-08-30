<?php
// Database connection
$servername = "localhost";
$username = "root"; // Change if needed
$password = ""; // Change if needed
$dbname = "mycounsel"; // Change to your actual database name

$conn = new mysqli($servername, $username, $password, $dbname);

session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: signin.php");
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

// Fetch only completed appointments
$sql = "SELECT * FROM student_appointment WHERE status = 'Completed'";

// Handle search functionality
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $_GET['search'];
    $sql .= " AND (studentName LIKE '%$search%' OR studentId LIKE '%$search%' OR 
              studentEmail LIKE '%$search%')";
}

// Add sorting functionality - sort by most recent appointment
$sql .= " ORDER BY appointmentDateTime DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Records | MyCounsel</title>
    <link rel="stylesheet" href="../css/ms.css">
    <style>
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .history-table th, .history-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .history-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        
        .history-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .issue-tag {
            display: inline-block;
            background-color: #e7f3fe;
            color: #0066cc;
            padding: 3px 8px;
            border-radius: 4px;
            margin: 2px;
            font-size: 12px;
        }
        
        .consultation-date {
            font-weight: bold;
            color: #333;
        }
        
        .student-info {
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        .student-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .student-id {
            color: #666;
            margin-bottom: 5px;
        }
        
        .back-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #007bff;
            color: #fff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            text-align: center;
            line-height: 40px;
            cursor: pointer;
            display: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .back-to-top:hover {
            background-color: #0056b3;
        }
        
        /* User profile picture styling */
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
            <a href="/php/studentappointment.php">Student Appointment</a>
            <a href="/php/managescheduled.php">Manage Scheduled</a>
            <a href="/php/appointmentrecords.php" class="active">Appointment Records</a>
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
    <h1>Student Consultation History</h1>
    <!-- Search Form -->
    <div class="search-container">
        <form method="GET" action="appointmentrecords.php" class="search-form">
            <input type="text" name="search" placeholder="Search student name or ID..." 
                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                   class="search-input">
            <button type="submit" class="search-button">Search</button>
            <?php if (isset($_GET['search'])): ?>
                <a href="appointmentrecords.php" class="clear-search-button">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    <br>
    <?php
    // Check if we have results
    if ($result->num_rows > 0) {
        // Group appointments by student ID
        $students = [];
        while ($row = $result->fetch_assoc()) {
            $studentId = $row['studentId'];
            if (!isset($students[$studentId])) {
                $students[$studentId] = [
                    'info' => [
                        'name' => $row['studentName'],
                        'id' => $row['studentId'],
                        'email' => $row['studentEmail'],
                        'phone' => $row['phoneNumber']
                    ],
                    'consultations' => []
                ];
            }
            
            // Prepare issue categories
            $issueCategories = [];
            if ($row['academicIssues']) {
                $issueCategories[] = [
                    'name' => 'Academic',
                    'type' => $row['academicIssueType']
                ];
            }
            if ($row['mentalHealth']) {
                $issueCategories[] = [
                    'name' => 'Mental Health',
                    'type' => null
                ];
            }
            if ($row['volunteer']) {
                $issueCategories[] = [
                    'name' => 'Volunteer',
                    'type' => $row['volunteerType']
                ];
            }
            if ($row['referred']) {
                $issueCategories[] = [
                    'name' => 'Referred',
                    'type' => $row['referralSource']
                ];
            }
            
            $students[$studentId]['consultations'][] = [
                'date' => $row['appointmentDateTime'],
                'issues' => $issueCategories
            ];
        }
        
        // Display student history
        foreach ($students as $student) {
            ?>
            <div class="student-info">
                <div class="student-name"><?php echo htmlspecialchars($student['info']['name']); ?></div>
                <div class="student-id">Student ID: <?php echo htmlspecialchars($student['info']['id']); ?></div>
                <div>Email: <?php echo htmlspecialchars($student['info']['email']); ?></div>
                <div>Phone: <?php echo htmlspecialchars($student['info']['phone']); ?></div>
                
                <h3>Consultation History</h3>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Issues Consulted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($student['consultations'] as $consultation) { ?>
                            <tr>
                                <td class="consultation-date"><?php echo htmlspecialchars($consultation['date']); ?></td>
                                <td>
                                    <?php 
                                    foreach ($consultation['issues'] as $issue) {
                                        $displayText = $issue['type'] 
                                            ? $issue['name'] . " (" . $issue['type'] . ")" 
                                            : $issue['name'];
                                        echo "<span class='issue-tag'>" . htmlspecialchars($displayText) . "</span>";
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php
        }
    } else {
        echo "<p>No consultation history found.</p>";
    }
    ?>
</div>

<div class="back-to-top" id="backToTop">↑</div>

<script>
    // Back to top button functionality
    const backToTopButton = document.getElementById('backToTop');
    
    window.addEventListener('scroll', () => {
        if (window.pageYOffset > 300) {
            backToTopButton.style.display = 'block';
        } else {
            backToTopButton.style.display = 'none';
        }
    });
    
    backToTopButton.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
</script>
</body>
</html>

<?php
$conn->close();
?>