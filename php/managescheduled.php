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

// Handle appointment delete
if (isset($_GET['delete'])) {
    $appointmentId = $_GET['delete'];
    $deleteSql = "DELETE FROM student_appointment WHERE studentId = ?";
    $stmt = $conn->prepare($deleteSql);
    $stmt->bind_param("s", $appointmentId);
    $stmt->execute();
    $stmt->close();
    header("Location: managescheduled.php");
    exit();
}

// Handle appointment update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
    $appointmentId = $_POST['appointmentId'];
    $academicIssues = isset($_POST['academicIssues']) ? 1 : 0;
    $academicIssueType = $_POST['academicIssueType'] ?? NULL;
    $mentalHealth = isset($_POST['mentalHealth']) ? 1 : 0;
    $volunteer = isset($_POST['volunteer']) ? 1 : 0;
    $volunteerType = $_POST['volunteerType'] ?? NULL;
    $referred = isset($_POST['referred']) ? 1 : 0;
    $referralSource = $_POST['referralSource'] ?? NULL;
    $appointmentDateTime = $_POST['appointmentDateTime'] ?? NULL;

    $updateSql = "UPDATE student_appointment 
                  SET academicIssues = ?, academicIssueType = ?, 
                      mentalHealth = ?, volunteer = ?, volunteerType = ?, 
                      referred = ?, referralSource = ?, 
                      studentName = ?, studentId = ?, 
                      phoneNumber = ?, studentEmail = ?, 
                      status = ?, appointmentDateTime = ?
                  WHERE studentId = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("issisissssssss", 
        $academicIssues, $academicIssueType, 
        $mentalHealth, $volunteer, $volunteerType, 
        $referred, $referralSource,
        $_POST['studentName'], $_POST['studentId'], 
        $_POST['phoneNumber'], $_POST['studentEmail'], 
        $_POST['status'], $appointmentDateTime, $appointmentId);
    $stmt->execute();
    $stmt->close();

    // Display success message
    $_SESSION['success_message'] = "Appointment updated successfully!";
    
    header("Location: managescheduled.php");
    exit();
}

// Determine which tab is active
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'all';

// Base SQL query
$sql = "SELECT * FROM student_appointment WHERE 1=1";

// Add conditions based on active tab
switch ($activeTab) {
    case 'today':
        $sql .= " AND DATE(appointmentDateTime) = CURDATE() AND status = 'Pending'";
        break;
    case 'upcoming':
        $sql .= " AND appointmentDateTime > NOW() AND status = 'Pending'";
        break;
    case 'pending':
        $sql .= " AND status = 'Pending'";
        break;
    case 'completed':
        $sql .= " AND status = 'Completed'";
        break;
    case 'cancelled':
        $sql .= " AND status = 'Cancelled'";
        break;
    case 'all':
    default:
        // No additional WHERE clause for "All" tab
        break;
}

// Handle search functionality
$search_query = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = $_GET['search'];
    $search_param = "%" . $search_query . "%";
    $sql .= " AND (studentName LIKE ? OR studentId LIKE ? OR studentEmail LIKE ? OR phoneNumber LIKE ?)";
}

// Handle appointment sorting options
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'date';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'desc'; // Default to newest first

// Add sorting
if ($sort_by == 'date') {
    $sql .= " ORDER BY appointmentDateTime " . ($sort_order == 'asc' ? 'ASC' : 'DESC');
} else if ($sort_by == 'status') {
    $sql .= " ORDER BY status " . ($sort_order == 'asc' ? 'ASC' : 'DESC');
} else if ($sort_by == 'name') {
    $sql .= " ORDER BY studentName " . ($sort_order == 'asc' ? 'ASC' : 'DESC');
}

// Prepare and execute query
$stmt = $conn->prepare($sql);

// Bind parameters if needed
if (!empty($search_query)) {
    $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
}

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// Get appointment statistics for tab counters
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(CASE WHEN DATE(appointmentDateTime) = CURDATE() AND status = 'Pending' THEN 1 ELSE 0 END) as today,
    SUM(CASE WHEN appointmentDateTime > NOW() AND status = 'Pending' THEN 1 ELSE 0 END) as upcoming
FROM student_appointment";

$stats_result = $conn->query($stats_sql);
$stats = [
    'total' => 0,
    'pending' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'today' => 0,
    'upcoming' => 0
];

if ($stats_row = $stats_result->fetch_assoc()) {
    $stats = $stats_row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Student Appointments</title>
    <link rel="stylesheet" href="../css/ms.css">
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
            <a href="/php/managescheduled.php" class="active">Manage Scheduled</a>
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
    <h1>Manage Student Appointments</h1>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php 
                echo htmlspecialchars($_SESSION['success_message']);
                unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
            <?php 
                echo htmlspecialchars($_SESSION['error_message']);
                unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>
    
    <!-- Tabs Navigation -->
    <div class="tabs-container">
        <div class="tabs">
            <a href="?tab=all<?php echo !empty($search_query) ? '&search='.urlencode($search_query) : ''; ?><?php echo isset($sort_by) ? '&sort_by='.$sort_by.'&sort_order='.$sort_order : ''; ?>" 
               class="tab <?php echo $activeTab == 'all' ? 'active' : ''; ?>">
                All Appointments
                <span class="badge"><?php echo $stats['total']; ?></span>
            </a>
            <a href="?tab=today<?php echo !empty($search_query) ? '&search='.urlencode($search_query) : ''; ?><?php echo isset($sort_by) ? '&sort_by='.$sort_by.'&sort_order='.$sort_order : ''; ?>" 
               class="tab <?php echo $activeTab == 'today' ? 'active' : ''; ?>">
                Today
                <span class="badge"><?php echo $stats['today']; ?></span>
            </a>
            <a href="?tab=upcoming<?php echo !empty($search_query) ? '&search='.urlencode($search_query) : ''; ?><?php echo isset($sort_by) ? '&sort_by='.$sort_by.'&sort_order='.$sort_order : ''; ?>" 
               class="tab <?php echo $activeTab == 'upcoming' ? 'active' : ''; ?>">
                Upcoming
                <span class="badge"><?php echo $stats['upcoming']; ?></span>
            </a>
            <a href="?tab=pending<?php echo !empty($search_query) ? '&search='.urlencode($search_query) : ''; ?><?php echo isset($sort_by) ? '&sort_by='.$sort_by.'&sort_order='.$sort_order : ''; ?>" 
               class="tab <?php echo $activeTab == 'pending' ? 'active' : ''; ?>">
                Pending
                <span class="badge"><?php echo $stats['pending']; ?></span>
            </a>
            <a href="?tab=completed<?php echo !empty($search_query) ? '&search='.urlencode($search_query) : ''; ?><?php echo isset($sort_by) ? '&sort_by='.$sort_by.'&sort_order='.$sort_order : ''; ?>" 
               class="tab <?php echo $activeTab == 'completed' ? 'active' : ''; ?>">
                Completed
                <span class="badge"><?php echo $stats['completed']; ?></span>
            </a>
            <a href="?tab=cancelled<?php echo !empty($search_query) ? '&search='.urlencode($search_query) : ''; ?><?php echo isset($sort_by) ? '&sort_by='.$sort_by.'&sort_order='.$sort_order : ''; ?>" 
               class="tab <?php echo $activeTab == 'cancelled' ? 'active' : ''; ?>">
                Cancelled
                <span class="badge"><?php echo $stats['cancelled']; ?></span>
            </a>
        </div>
    </div>
    
    <!-- Quick Filters -->
    <div class="filter-options">
        <div class="filter-label">Filter by issue:</div>
        <div class="quick-filters">
            <a href="javascript:void(0)" class="filter-chip" data-filter="academic">Academic</a>
            <a href="javascript:void(0)" class="filter-chip" data-filter="mental">Mental Health</a>
            <a href="javascript:void(0)" class="filter-chip" data-filter="volunteer">Volunteer</a>
            <a href="javascript:void(0)" class="filter-chip" data-filter="referred">Referred</a>
        </div>
    </div>
    
    <!-- Export Options -->
    <div class="export-options">
        <button id="exportPDF" class="btn btn-export">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
                <polyline points="10 9 9 9 8 9"></polyline>
            </svg>
            Export PDF
        </button>
        <button id="exportExcel" class="btn btn-export">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="12" y1="18" x2="12" y2="12"></line>
                <line x1="9" y1="15" x2="15" y2="15"></line>
            </svg>
            Export Excel
        </button>
    </div>

    <!-- Search Form -->
    <div class="search-container">
        <form method="GET" action="managescheduled.php" class="search-form">
            <input type="hidden" name="tab" value="<?php echo $activeTab; ?>">
            <?php if (isset($sort_by) && isset($sort_order)): ?>
                <input type="hidden" name="sort_by" value="<?php echo htmlspecialchars($sort_by); ?>">
                <input type="hidden" name="sort_order" value="<?php echo htmlspecialchars($sort_order); ?>">
            <?php endif; ?>
            <input type="text" name="search" placeholder="Search appointments..." 
                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                   class="search-input">
            <button type="submit" class="search-button">Search</button>
            <?php if (isset($_GET['search'])): ?>
                <a href="?tab=<?php echo $activeTab; ?><?php echo isset($sort_by) ? '&sort_by='.$sort_by.'&sort_order='.$sort_order : ''; ?>" class="clear-search-button">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    
    <table class="appointments-table" id="appointmentsTable">
        <thead>
            <tr>
                <th onclick="sortTable('date')">
                    Appointment Date
                    <?php if ($sort_by == 'date'): ?>
                        <span class="sort-icon"><?php echo $sort_order == 'asc' ? '▲' : '▼'; ?></span>
                    <?php endif; ?>
                </th>
                <th onclick="sortTable('name')">
                    Student Name
                    <?php if ($sort_by == 'name'): ?>
                        <span class="sort-icon"><?php echo $sort_order == 'asc' ? '▲' : '▼'; ?></span>
                    <?php endif; ?>
                </th>
                <th>Student ID</th>
                <th>Phone Number</th>
                <th>Student Email</th>
                <th>Issue Categories</th>
                <th onclick="sortTable('status')">
                    Status
                    <?php if ($sort_by == 'status'): ?>
                        <span class="sort-icon"><?php echo $sort_order == 'asc' ? '▲' : '▼'; ?></span>
                    <?php endif; ?>
                </th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
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
                    
                    // Check if this appointment has been called
                    $wasCalled = isset($_SESSION['called_appointment_' . $row['studentId']]) && 
                                $_SESSION['called_appointment_' . $row['studentId']] === true;

                    // If the URL has the 'called' parameter and it matches the current appointment, mark it as called
                    if (isset($_GET['called']) && $_GET['called'] == $row['studentId']) {
                        $wasCalled = true;
                        $_SESSION['called_appointment_' . $row['studentId']] = true;
                    }
            ?>
            <tr data-id="<?php echo htmlspecialchars($row['studentId']); ?>">
                <td><?php echo date("M d, Y - g:i A", strtotime($row['appointmentDateTime'])); ?></td>
                <td><?php echo htmlspecialchars($row['studentName']); ?></td>
                <td><?php echo htmlspecialchars($row['studentId']); ?></td>
                <td><?php echo htmlspecialchars($row['phoneNumber']); ?></td>
                <td><?php echo htmlspecialchars($row['studentEmail']); ?></td>
                <td>
                    <div class="issue-categories">
                        <?php 
                        foreach ($issueCategories as $category) {
                            $displayText = $category['type'] 
                                ? $category['name'] . " (" . $category['type'] . ")" 
                                : $category['name'];
                            echo "<span class='issue-tag'>" . htmlspecialchars($displayText) . "</span>";
                        }
                        ?>
                    </div>
                </td>
                <td>
                    <span class="status-badge 
                        <?php 
                        echo strtolower($row['status']) == 'pending' ? 'status-pending' : 
                             (strtolower($row['status']) == 'completed' ? 'status-completed' : 'status-cancelled'); 
                        ?>">
                        <?php echo htmlspecialchars($row['status']); ?>
                    </span>
                </td>
                <td class="action-buttons">
                    <button class="btn btn-edit" onclick="openEditModal(this)">Edit</button>
                    <a href="?tab=<?php echo $activeTab; ?>&delete=<?php echo htmlspecialchars($row['studentId']); ?>" 
                       class="btn btn-delete"
                       onclick="return confirm('Are you sure you want to delete this appointment?');">Delete</a>
                </td>
                <td class="action-buttons">
                    <div class="notification-buttons" data-appointment-id="<?php echo htmlspecialchars($row['studentId']); ?>">
                        <?php if (!$wasCalled): ?>
                        <form method="POST" action="call_student.php" class="call-student-form">
                            <input type="hidden" name="appointment_id" value="<?php echo htmlspecialchars($row['studentId']); ?>">
                            <button type="submit" name="call_student" class="btn btn-call">
                                Remind Student
                            </button>
                        </form>
                        <?php else: ?>
                        <div class="call-sent-indicator">
                            <span>✓ Notified </span>
                        </div>
                        <?php endif; ?>
                        <form method="POST" action="send_reminder.php" style="margin-bottom: <?php echo $wasCalled ? '0' : '5px'; ?>;">
                            <input type="hidden" name="appointment_id" value="<?php echo htmlspecialchars($row['studentId']); ?>">
                            <button type="submit" name="send_reminder" class="btn btn-email" style="background-color: #d4ac0d ;">
                                Remind Again
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php
                }
            } else {
                echo "<tr><td colspan='9' class='text-center'>No appointments found</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditModal()">&times;</span>
        <h2>Edit Student Appointment</h2>
        <form id="editForm" method="POST" action="">
            <input type="hidden" name="update" value="1">
            <input type="hidden" name="appointmentId" id="modalAppointmentId">

            <div class="edit-form-container">
                <div class="form-left-column">
                    <div class="form-group">
                        <label>Student Name:</label>
                        <input type="text" name="studentName" id="modalStudentName" required>
                    </div>

                    <div class="form-group">
                        <label>Student ID:</label>
                        <input type="text" name="studentId" id="modalStudentId" required>
                    </div>

                    <div class="form-group">
                        <label>Phone Number:</label>
                        <input type="tel" name="phoneNumber" id="modalPhoneNumber" required>
                    </div>

                    <div class="form-group">
                        <label>Student Email:</label>
                        <input type="email" name="studentEmail" id="modalStudentEmail" required>
                    </div>

                    <div class="form-group">
                        <label>Appointment Status:</label>
                        <select name="status" id="modalStatus" required>
                            <option value="Pending">Pending</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="form-right-column">
                    <h3>Issue Categories</h3>
                    <div class="checkbox-group">
                        <input type="checkbox" name="academicIssues" id="modalAcademicIssues">
                        <label for="modalAcademicIssues">Academic Issues : </label>
                        <select name="academicIssueType" id="modalAcademicIssueType" disabled>
                            <option value="">Select Issue Type</option>
                            <option value="Warning Letter Level 1">Warning Letter Level 1</option>
                            <option value="Warning Letter Level 2">Warning Letter Level 2</option>
                            <option value="BARRED">BARRED</option>
                        </select>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" name="mentalHealth" id="modalMentalHealth">
                        <label for="modalMentalHealth">Mental Health</label>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" name="volunteer" id="modalVolunteer">
                        <label for="modalVolunteer">Volunteer : </label>
                        <select name="volunteerType" id="modalVolunteerType" disabled>
                            <option value="">Select Volunteer Type</option>
                            <option value="Personal">Personal</option>
                            <option value="Career">Career</option>
                            <option value="Financial">Financial</option>
                            <option value="Social">Social</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" name="referred" id="modalReferred">
                        <label for="modalReferred">Referred : </label>
                        <select name="referralSource" id="modalReferralSource" disabled>
                            <option value="">Select Referral Source</option>
                            <option value="Mentor">Mentor</option>
                            <option value="Lecturer">Lecturer</option>
                            <option value="Parents">Parents</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Appointment Date and Time:</label>
                        <input type="datetime-local" name="appointmentDateTime" id="modalAppointmentDateTime" required>
                    </div>
                </div>
            </div>

            <div class="form-bottom">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script src="../js/ms.js"></script>
<script>
// Function to handle table sorting
function sortTable(column) {
    const currentTab = '<?php echo $activeTab; ?>';
    const currentSearch = '<?php echo htmlspecialchars($search_query); ?>';
    const currentSortBy = '<?php echo $sort_by; ?>';
    const currentSortOrder = '<?php echo $sort_order; ?>';
    
    let newSortOrder = 'asc';
    if (column === currentSortBy && currentSortOrder === 'asc') {
        newSortOrder = 'desc';
    }
    
    let url = `?tab=${currentTab}&sort_by=${column}&sort_order=${newSortOrder}`;
    if (currentSearch) {
        url += `&search=${encodeURIComponent(currentSearch)}`;
    }
    
    window.location.href = url;
}

// Add this to your existing JavaScript to handle the Call Student button visibility
document.addEventListener('DOMContentLoaded', function() {
    // Find all call student forms
    const callStudentForms = document.querySelectorAll('form[action="call_student.php"]');
    
    callStudentForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // Get the appointment ID from the form
            const appointmentId = this.querySelector('input[name="appointment_id"]').value;
            
            // Store in sessionStorage that this appointment has been called
            sessionStorage.setItem('called_' + appointmentId, 'true');
            
            // Continue with form submission
        });
    });
    
    // Check the URL for the 'called' parameter for immediate feedback without page reload
    const urlParams = new URLSearchParams(window.location.search);
    const calledAppointmentId = urlParams.get('called');
    
    if (calledAppointmentId) {
        // Find the row with this appointment ID
        const row = document.querySelector(`tr[data-id="${calledAppointmentId}"]`);
        if (row) {
            updateButtonsAfterCall(row);
        }
    }
    
    // Check if any appointments were previously called using sessionStorage
    const appointmentRows = document.querySelectorAll('tr[data-id]');
    appointmentRows.forEach(row => {
        const appointmentId = row.getAttribute('data-id');
        // Check both sessionStorage and the 'called' parameter
        const wasCalled = sessionStorage.getItem('called_' + appointmentId) === 'true' || 
                         (calledAppointmentId && calledAppointmentId === appointmentId);
        
        if (wasCalled) {
            updateButtonsAfterCall(row);
        }
    });
});

// Function to update buttons after a call is made
function updateButtonsAfterCall(row) {
    // Find the notification buttons container
    const notificationContainer = row.querySelector('.notification-buttons');
    if (!notificationContainer) return;
    

    // Show the Send Reminder form
    const reminderForm = notificationContainer.querySelector('form[action="send_reminder.php"]');
    if (reminderForm) {
        reminderForm.style.marginBottom = '0';
    }
    

}
</script>


</body>
</html>

<?php
$conn->close();
?>