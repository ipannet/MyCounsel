<?php
session_start();
include 'db.php'; // Ensure this file correctly connects to the database

// Redirect to login if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: signin.php");
    exit();
}
$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

// Get user profile picture - first check session, then database if not found
$profile_picture = "default.jpg"; // Default image if none exists

// Check if profile picture is already in session
if (isset($_SESSION['profile_picture']) && !empty($_SESSION['profile_picture'])) {
    $profile_picture = $_SESSION['profile_picture'];
} else {
    // If not in session, get from database
    $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $pic_result = $stmt->get_result();
    $pic_data = $pic_result->fetch_assoc();

    if ($pic_data && $pic_data['profile_picture']) {
        $profile_picture = $pic_data['profile_picture'];
        // Store in session for future use
        $_SESSION['profile_picture'] = $profile_picture;
    }
    $stmt->close();
}

// Handle To-Do List actions
if (isset($_POST['add_task'])) {
    $task = $_POST['task'];
    $stmt = $conn->prepare("INSERT INTO todo_list (user_id, task, status) VALUES (?, ?, 'pending')");
    $stmt->bind_param("is", $user_id, $task);
    $stmt->execute();
    $stmt->close();
}

if (isset($_POST['delete_task'])) {
    $task_id = $_POST['task_id'];
    $stmt = $conn->prepare("DELETE FROM todo_list WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $task_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

if (isset($_POST['mark_complete'])) {
    $task_id = $_POST['task_id'];
    $stmt = $conn->prepare("UPDATE todo_list SET status = 'completed' WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $task_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

// Handle Certificate Upload
if (isset($_POST['upload_certificate'])) {
    $upload_dir = "uploads/certificates/";
    
    // Check if the directory exists, if not, create it
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true); // Create directory with full permissions
    }

    $file_name = $_FILES['certificate']['name'];
    $file_tmp = $_FILES['certificate']['tmp_name'];
    $file_path = $upload_dir . $file_name;

    if (move_uploaded_file($file_tmp, $file_path)) {
        $stmt = $conn->prepare("INSERT INTO certificates (user_id, file_name, file_path) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $file_name, $file_path);
        $stmt->execute();
        $stmt->close();
    } else {
        echo "Failed to upload file.";
    }
}

// Handle Certificate Deletion
if (isset($_POST['delete_certificate'])) {
    $certificate_id = $_POST['certificate_id'];
    
    // Get the file path before deleting the record (ensure user owns this certificate)
    $stmt = $conn->prepare("SELECT file_path FROM certificates WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $certificate_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $filePathRow = $result->fetch_assoc();
    
    if ($filePathRow) {
        $filePath = $filePathRow['file_path'];
        
        // Delete the physical file if it exists
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Delete the database record
        $stmt = $conn->prepare("DELETE FROM certificates WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $certificate_id, $user_id);
        $stmt->execute();
    }
    $stmt->close();
}

// Handle Adding a New Event
if (isset($_POST['add_event'])) {
    $event_name = $_POST['event_name'];
    $event_date = $_POST['event_date'];
    $stmt = $conn->prepare("INSERT INTO events (user_id, event_name, event_date) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $event_name, $event_date);
    $stmt->execute();
    $stmt->close();
}

// Handle Deleting an Event
if (isset($_POST['delete_event'])) {
    $event_id = $_POST['event_id'];
    $stmt = $conn->prepare("DELETE FROM events WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $event_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

// Handle Updating an Event
if (isset($_POST['update_event'])) {
    $event_id = $_POST['event_id'];
    $new_event_name = $_POST['new_event_name'];
    $new_event_date = $_POST['new_event_date'];
    $stmt = $conn->prepare("UPDATE events SET event_name = ?, event_date = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ssii", $new_event_name, $new_event_date, $event_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

// Handle Search for Appointments
$searchQuery = "";
$searchFilter = "";
if (isset($_GET['search'])) {
    $searchQuery = $_GET['search'];
    
    if (isset($_GET['filter']) && !empty($_GET['filter'])) {
        $searchFilter = $_GET['filter'];
    }
}

// Fetch To-Do List using user_id
$stmt = $conn->prepare("SELECT * FROM todo_list WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$tasks = $stmt->get_result();

// Fetch Uploaded Certificates using user_id
$stmt = $conn->prepare("SELECT * FROM certificates WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$certificates = $stmt->get_result();

// Check if consultations table exists before querying it
$tableExists = false;
$result = mysqli_query($conn, "SHOW TABLES LIKE 'consultations'");
if ($result && mysqli_num_rows($result) > 0) {
    $tableExists = true;
    // Check if consultations table has user_id column
    $columnExists = mysqli_query($conn, "SHOW COLUMNS FROM consultations LIKE 'user_id'");
    if ($columnExists && mysqli_num_rows($columnExists) > 0) {
        // Fetch Student Consultation Status using user_id
        $stmt = $conn->prepare("SELECT * FROM consultations WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $consultations = $stmt->get_result();
    } else {
        // Fallback to username if user_id column doesn't exist
        $consultations = mysqli_query($conn, "SELECT * FROM consultations WHERE username='$username'");
    }
} else {
    // Create an empty result set to avoid errors when referencing $consultations
    $consultations = false;
}

// Fetch Events using user_id
$stmt = $conn->prepare("SELECT * FROM events WHERE user_id = ? ORDER BY event_date ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$events = $stmt->get_result();

// Prepare base query for appointments
$appointmentsSql = "SELECT * FROM student_appointment";
$whereClause = [];

// Apply search filters
if (!empty($searchQuery)) {
    if (empty($searchFilter) || $searchFilter === "all") {
        $whereClause[] = "(studentName LIKE '%$searchQuery%' OR status LIKE '%$searchQuery%')";
    } else {
        if ($searchFilter === "name") {
            $whereClause[] = "studentName LIKE '%$searchQuery%'";
        } elseif ($searchFilter === "status") {
            $whereClause[] = "status LIKE '%$searchQuery%'";
        } elseif ($searchFilter === "date") {
            $whereClause[] = "appointmentDateTime LIKE '%$searchQuery%'";
        }
    }
}

// Apply WHERE clause if filters are present
if (!empty($whereClause)) {
    $appointmentsSql .= " WHERE " . implode(" AND ", $whereClause);
}

// Check if student_appointment table exists before querying it
$appointmentsTableExists = false;
$result = mysqli_query($conn, "SHOW TABLES LIKE 'student_appointment'");
if ($result && mysqli_num_rows($result) > 0) {
    $appointmentsTableExists = true;
    // Fetch Student Appointments for Analysis only if the table exists
    $appointments = mysqli_query($conn, $appointmentsSql);
    
    // Initialize variables
    $totalAppointments = mysqli_num_rows($appointments);
    $pendingAppointments = 0;
    $completedAppointments = 0;
    $cancelledAppointments = 0;
    $academicIssuesCount = 0;
    $mentalHealthCount = 0;
    $volunteerCount = 0;
    $referredCount = 0;

    // Reset the pointer to the beginning of the result set
    mysqli_data_seek($appointments, 0);

    while ($row = mysqli_fetch_assoc($appointments)) {
        if (strtolower($row['status']) == 'pending') {
            $pendingAppointments++;
        } elseif (strtolower($row['status']) == 'completed') {
            $completedAppointments++;
        } elseif (strtolower($row['status']) == 'cancelled') {
            $cancelledAppointments++;
        }
        
        if (isset($row['academicIssues']) && $row['academicIssues']) {
            $academicIssuesCount++;
        }
        if (isset($row['mentalHealth']) && $row['mentalHealth']) {
            $mentalHealthCount++;
        }
        if (isset($row['volunteer']) && $row['volunteer']) {
            $volunteerCount++;
        }
        if (isset($row['referred']) && $row['referred']) {
            $referredCount++;
        }
    }

    // Calculate percentages (avoid division by zero)
    $pendingPercentage = $totalAppointments > 0 ? round(($pendingAppointments / $totalAppointments) * 100, 1) : 0;
    $completedPercentage = $totalAppointments > 0 ? round(($completedAppointments / $totalAppointments) * 100, 1) : 0;
    $cancelledPercentage = $totalAppointments > 0 ? round(($cancelledAppointments / $totalAppointments) * 100, 1) : 0;

    // Get most recent appointments
    $recentAppointmentsSql = "SELECT * FROM student_appointment";

    // Apply WHERE clause if filters are present
    if (!empty($whereClause)) {
        $recentAppointmentsSql .= " WHERE " . implode(" AND ", $whereClause);
    }

    $recentAppointmentsSql .= " ORDER BY appointmentDateTime DESC";
    $recentAppointments = mysqli_query($conn, $recentAppointmentsSql);
} else {
    // Create empty variables to avoid errors
    $appointments = false;
    $recentAppointments = false;
    $totalAppointments = 0;
    $pendingAppointments = 0;
    $completedAppointments = 0;
    $cancelledAppointments = 0;
    $academicIssuesCount = 0;
    $mentalHealthCount = 0;
    $volunteerCount = 0;
    $referredCount = 0;
    $pendingPercentage = 0;
    $completedPercentage = 0;
    $cancelledPercentage = 0;
}

// Update Consultation Status (Only if table exists and has user_id column)
if (isset($_POST['update_status']) && $tableExists) {
    $consultation_id = $_POST['consultation_id'];
    $new_status = $_POST['new_status'];
    
    // Check if consultations table has user_id column
    $columnExists = mysqli_query($conn, "SHOW COLUMNS FROM consultations LIKE 'user_id'");
    if ($columnExists && mysqli_num_rows($columnExists) > 0) {
        $stmt = $conn->prepare("UPDATE consultations SET status = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sii", $new_status, $consultation_id, $user_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Fallback to username if user_id column doesn't exist
        $sql = "UPDATE consultations SET status='$new_status' WHERE id='$consultation_id'";
        mysqli_query($conn, $sql);
    }
    echo "<script>window.location.reload();</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Home | MyCounsel</title>
    <link rel="stylesheet" href="/css/index.css">
    <script src="/js/index.js"></script>

    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f7fa;
        }
        
        .container {
            display: flex;
            flex-wrap: wrap;
            margin: 20px;
            gap: 20px;
        }
        
        .left-column {
            flex: 1;
            min-width: 250px;
        }
        
        .middle-column {
            flex: 2;
            min-width: 400px;
        }
        
        .right-column {
            flex: 1;
            min-width: 250px;
        }
        
        .section {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .section h2 {
            color:rgb(0, 0, 0);
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
            margin-top: 0;
        }
        
        /* Form styling */
        form {
            margin-bottom: 15px;
        }
        
        input[type="text"], 
        input[type="date"], 
        input[type="file"],
        select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-right: 8px;
            margin-bottom: 8px;
        }
        
        button {
            background-color: #4a6fa5;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        button:hover {
            background-color: #3a5a8a;
        }
        
        /* Task list styling */
        ul {
            list-style-type: none;
            padding: 0;
        }
        
        li {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        li:last-child {
            border-bottom: none;
        }
        
        /* Search bar styling */
        .search-container {
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .search-container input[type="text"] {
            flex: 1;
            min-width: 200px;
        }
        
        /* Additional styles for student analysis section */
        .analysis-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 15px;
        }
        
        .analysis-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 15px;
            flex: 1;
            min-width: 120px;
        }
        
        .analysis-card h3 {
            margin-top: 0;
            color: #333;
            font-size: 16px;
        }
        
        .analysis-card .number {
            font-size: 28px;
            font-weight: bold;
            color: #4a6fa5;
        }
        
        .analysis-card .percentage {
            font-size: 14px;
            color: #666;
        }
        
        .status-pending {
            color: #ffa500;
        }
        
        .status-completed {
            color: #2ecc71;
        }
        
        .status-cancelled {
            color: #e74c3c;
        }
        
        .issue-distribution {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            margin-top: 20px;
            gap: 10px;
        }
        
        .issue-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            flex: 1;
            min-width: 100px;
            text-align: center;
        }
        
        .issue-card h4 {
            margin-top: 0;
            font-size: 14px;
        }
        
        .recent-appointments {
            margin-top: 20px;
        }
        
        .recent-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .recent-table th, .recent-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .recent-table th {
            background-color: #f5f5f5;
        }
        
        .chart-container {
            width: 100%;
            margin-top: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 20px;
        }
        
        .chart {
            flex: 1;
            min-width: 300px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 15px;
        }
        
        .status-bar {
            height: 30px;
            background: #f0f0f0;
            border-radius: 15px;
            margin-top: 10px;
            overflow: hidden;
            display: flex;
        }
        
        .status-bar-segment {
            height: 100%;
            display: inline-block;
        }
        
        .status-bar-pending {
            background-color: #ffa500;
        }
        
        .status-bar-completed {
            background-color: #2ecc71;
        }
        
        .status-bar-cancelled {
            background-color: #e74c3c;
        }
        
        /* Certificate list styling */
        .certificate-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .certificate-actions {
            display: flex;
            gap: 5px;
        }
        
        .certificate-actions button {
            background-color: #e74c3c;
            padding: 5px 8px;
            font-size: 12px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .container {
                flex-direction: column;
            }
            
            .left-column, .middle-column, .right-column {
                width: 100%;
            }
        }

        /* Badge styling */
        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-pending {
            background-color: #fff3e0;
            color: #ffa500;
        }
        
        .badge-completed {
            background-color: #e8f5e9;
            color: #2ecc71;
        }
        
        .badge-cancelled {
            background-color: #ffebee;
            color: #e74c3c;
        }
        
        /* Pagination controls */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }
        
        .pagination a {
            padding: 8px 12px;
            background-color: #f5f5f5;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
        }
        
        .pagination a.active {
            background-color: #4a6fa5;
            color: white;
        }
        
        /* Event styling */
        .event-date {
            color: #666;
            font-size: 12px;
        }
        
        .event-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .upcoming-event {
            background-color: #f0f8ff;
            border-left: 3px solid #4a6fa5;
        }

        /* User profile picture styling */
        .user-profile {
            position: relative;
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
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

        /* No data message styling */
        .no-data-message {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
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
                <a href="/php/index.php" class="active">Home</a>
                <a href="/php/studentappointment.php">Student Appointment</a>
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
        <!-- Left Column -->
        <div class="left-column">
            <!-- Upcoming Events Section - Moved to left column -->
            <div class="section">
                <h2>Upcoming Events</h2>
                
                <!-- Add Event Form -->
                <form method="post">
                    <input type="text" name="event_name" placeholder="Event name" required>
                    <input type="date" name="event_date" required>
                    <button type="submit" name="add_event">Add Event</button>
                </form>

                <h3>Your Events</h3>
                <ul>
                    <?php 
                    if (mysqli_num_rows($events) > 0) {
                        while ($row = mysqli_fetch_assoc($events)) { 
                            // Check if event is upcoming
                            $eventDate = strtotime($row['event_date']);
                            $today = strtotime(date('Y-m-d'));
                            $isUpcoming = $eventDate >= $today;
                            $eventClass = $isUpcoming ? 'upcoming-event' : '';
                    ?>
                        <li class="<?php echo $eventClass; ?>">
                            <div>
                                <div class="event-name"><?php echo htmlspecialchars($row['event_name']); ?></div>
                                <div class="event-date"><?php echo date('M d, Y', strtotime($row['event_date'])); ?></div>
                            </div>
                            <div>
                                <button type="button" onclick="toggleEventForm(<?php echo $row['id']; ?>)">Edit</button>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="event_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" name="delete_event">Delete</button>
                                </form>
                            </div>
                            
                            <!-- Update Event Form (hidden by default) -->
                            <div id="event-form-<?php echo $row['id']; ?>" style="display:none; margin-top: 10px; width: 100%;">
                                <form method="post">
                                    <input type="hidden" name="event_id" value="<?php echo $row['id']; ?>">
                                    <input type="text" name="new_event_name" value="<?php echo htmlspecialchars($row['event_name']); ?>" required>
                                    <input type="date" name="new_event_date" value="<?php echo $row['event_date']; ?>" required>
                                    <button type="submit" name="update_event">Update</button>
                                </form>
                            </div>
                        </li>
                    <?php 
                        }
                    } else {
                        echo '<li style="text-align: center;">No events scheduled</li>';
                    }
                    ?>
                </ul>
            </div>
            
            <!-- To-Do List Section -->
            <div class="section">
                <h2>To-Do List</h2>
                <form method="post">
                    <input type="text" name="task" placeholder="Enter new task" required>
                    <button type="submit" name="add_task">Add Task</button>
                </form>
                <ul>
                    <?php 
                    if ($tasks && mysqli_num_rows($tasks) > 0) {
                        while ($row = mysqli_fetch_assoc($tasks)) { 
                    ?>
                        <li>
                            <div><?php echo htmlspecialchars($row['task']); ?> 
                                <span class="status-badge badge-<?php echo strtolower($row['status']); ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </div>
                            <div>
                                <form method="post" style="display:inline; margin: 0;">
                                    <input type="hidden" name="task_id" value="<?php echo $row['id']; ?>">
                                    <?php if ($row['status'] != 'completed') { ?>
                                        <button type="submit" name="mark_complete">Complete</button>
                                    <?php } ?>
                                    <button type="submit" name="delete_task">Delete</button>
                                </form>
                            </div>
                        </li>
                    <?php 
                        }
                    } else {
                        echo '<li style="text-align: center;">No tasks added yet</li>';
                    }
                    ?>
                </ul>
            </div>

            <!-- Upload Certificate Section -->
            <div class="section">
                <h2>Certificates</h2>
                <form method="post" enctype="multipart/form-data">
                    <input type="file" name="certificate" required>
                    <button type="submit" name="upload_certificate">Upload</button>
                </form>
                <h3>Uploaded Certificates</h3>
                <ul>
                    <?php 
                    if ($certificates && mysqli_num_rows($certificates) > 0) {
                        while ($row = mysqli_fetch_assoc($certificates)) { 
                    ?>
                        <li class="certificate-item">
                            <a href="<?php echo htmlspecialchars($row['file_path']); ?>" target="_blank"><?php echo htmlspecialchars($row['file_name']); ?></a>
                            <div class="certificate-actions">
                                <a href="<?php echo htmlspecialchars($row['file_path']); ?>" download="<?php echo htmlspecialchars($row['file_name']); ?>">
                                    <button type="button">Download</button>
                                </a>
                                <form method="post">
                                    <input type="hidden" name="certificate_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" name="delete_certificate">Delete</button>
                                </form>
                            </div>
                        </li>
                    <?php 
                        }
                    } else {
                        echo '<li style="text-align: center;">No certificates uploaded</li>';
                    }
                    ?>
                </ul>
            </div>
        </div>

        <!-- Middle Column -->
        <div class="middle-column">
            <!-- Student Appointment Analysis Section -->
            <div class="section">
                <h2>Student Appointment Analysis</h2>
                
                <?php if ($appointmentsTableExists): ?>
                
                <div class="analysis-container">
                    <div class="analysis-card">
                        <h3>Total Appointments</h3>
                        <div class="number"><?php echo $totalAppointments; ?></div>
                    </div>
                    
                    <div class="analysis-card">
                        <h3>Pending</h3>
                        <div class="number status-pending"><?php echo $pendingAppointments; ?></div>
                        <div class="percentage"><?php echo $pendingPercentage; ?>% of total</div>
                    </div>
                    
                    <div class="analysis-card">
                        <h3>Completed</h3>
                        <div class="number status-completed"><?php echo $completedAppointments; ?></div>
                        <div class="percentage"><?php echo $completedPercentage; ?>% of total</div>
                    </div>
                    
                    <div class="analysis-card">
                        <h3>Cancelled</h3>
                        <div class="number status-cancelled"><?php echo $cancelledAppointments; ?></div>
                        <div class="percentage"><?php echo $cancelledPercentage; ?>% of total</div>
                    </div>
                </div>
                
                <div class="chart-container">
                    <div class="chart">
                        <h3>Appointment Status Distribution</h3>
                        <div class="status-bar">
                            <?php if ($totalAppointments > 0): ?>
                                <div class="status-bar-segment status-bar-pending" style="width: <?php echo $pendingPercentage; ?>%;"></div>
                                <div class="status-bar-segment status-bar-completed" style="width: <?php echo $completedPercentage; ?>%;"></div>
                                <div class="status-bar-segment status-bar-cancelled" style="width: <?php echo $cancelledPercentage; ?>%;"></div>
                            <?php else: ?>
                                <div class="status-bar-segment" style="width: 100%; background-color: #f0f0f0;">No data</div>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; margin-top: 10px;">
                            <span><span style="display: inline-block; width: 12px; height: 12px; background-color: #ffa500; margin-right: 5px;"></span> Pending (<?php echo $pendingPercentage; ?>%)</span>
                            <span><span style="display: inline-block; width: 12px; height: 12px; background-color: #2ecc71; margin-right: 5px;"></span> Completed (<?php echo $completedPercentage; ?>%)</span>
                            <span><span style="display: inline-block; width: 12px; height: 12px; background-color: #e74c3c; margin-right: 5px;"></span> Cancelled (<?php echo $cancelledPercentage; ?>%)</span>
                        </div>
                    </div>
                    
                    <div class="chart">
                        <h3>Issue Categories</h3>
                        <div class="issue-distribution">
                            <div class="issue-card">
                                <h4>Academic</h4>
                                <div class="number"><?php echo $academicIssuesCount; ?></div>
                                <div class="percentage"><?php echo $totalAppointments > 0 ? round(($academicIssuesCount / $totalAppointments) * 100, 1) : 0; ?>%</div>
                            </div>
                            
                            <div class="issue-card">
                                <h4>Mental Health</h4>
                                <div class="number"><?php echo $mentalHealthCount; ?></div>
                                <div class="percentage"><?php echo $totalAppointments > 0 ? round(($mentalHealthCount / $totalAppointments) * 100, 1) : 0; ?>%</div>
                            </div>
                            
                            <div class="issue-card">
                                <h4>Volunteer</h4>
                                <div class="number"><?php echo $volunteerCount; ?></div>
                                <div class="percentage"><?php echo $totalAppointments > 0 ? round(($volunteerCount / $totalAppointments) * 100, 1) : 0; ?>%</div>
                            </div>
                            
                            <div class="issue-card">
                                <h4>Referred</h4>
                                <div class="number"><?php echo $referredCount; ?></div>
                                <div class="percentage"><?php echo $totalAppointments > 0 ? round(($referredCount / $totalAppointments) * 100, 1) : 0; ?>%</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="recent-appointments">
                    <h3>Appointments <?php echo !empty($searchQuery) ? "Search Results" : "Recent Appointments"; ?></h3>
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Student Name</th>
                                <th>Issue Categories</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($recentAppointments && mysqli_num_rows($recentAppointments) > 0) {
                                while ($row = mysqli_fetch_assoc($recentAppointments)) {
                                    // Prepare issue categories
                                    $issueCategories = [];
                                    if (isset($row['academicIssues']) && $row['academicIssues']) {
                                        $issueCategories[] = 'Academic';
                                    }
                                    if (isset($row['mentalHealth']) && $row['mentalHealth']) {
                                        $issueCategories[] = 'Mental Health';
                                    }
                                    if (isset($row['volunteer']) && $row['volunteer']) {
                                        $issueCategories[] = 'Volunteer';
                                    }
                                    if (isset($row['referred']) && $row['referred']) {
                                        $issueCategories[] = 'Referred';
                                    }
                                    $issueText = implode(', ', $issueCategories);
                                    
                                    // Determine status badge class
                                    $statusClass = "";
                                    if (strtolower($row['status']) == 'pending') {
                                        $statusClass = "badge-pending";
                                    } elseif (strtolower($row['status']) == 'completed') {
                                        $statusClass = "badge-completed";
                                    } elseif (strtolower($row['status']) == 'cancelled') {
                                        $statusClass = "badge-cancelled";
                                    }
                            ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($row['appointmentDateTime'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['studentName']); ?></td>
                                    <td><?php echo htmlspecialchars($issueText); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php 
                                }
                            } else {
                                echo '<tr><td colspan="4" style="text-align: center;">No appointments found</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="no-data-message">
                    <p>Appointment management system is not yet configured. Please create the necessary database tables.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleEventForm(eventId) {
            var form = document.getElementById('event-form-' + eventId);
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }
    </script>
</body>
</html>