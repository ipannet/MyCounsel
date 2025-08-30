<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: signin.php");
    exit();
}

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

// Get user profile picture
$profile_picture = "default.jpg";
$stmt = $conn->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pic_result = $stmt->get_result();
$pic_data = $pic_result->fetch_assoc();

if ($pic_data && $pic_data['profile_picture']) {
    $profile_picture = $pic_data['profile_picture'];
}

// Create chat_history table if it doesn't exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS chat_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    response TEXT NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";
$conn->query($createTableSQL);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'send_message') {
        $user_message = trim($_POST['message']);
        
        if (empty($user_message)) {
            echo json_encode(['error' => 'Message cannot be empty']);
            exit();
        }
        
        // Get user's data from database for context
        $userData = getUserContextData($conn, $user_id, $username);
        
        // Check if it's a special command or needs database data
        $ai_response = processMessage($user_message, $userData, $conn, $user_id, $username);
        
        // Save to chat history
        $stmt = $conn->prepare("INSERT INTO chat_history (user_id, message, response) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $user_message, $ai_response);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'response' => $ai_response,
            'timestamp' => date('H:i')
        ]);
        exit();
    }
    
    if ($_POST['action'] === 'get_history') {
        $stmt = $conn->prepare("SELECT message, response, timestamp FROM chat_history WHERE user_id = ? ORDER BY timestamp DESC LIMIT 50");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = [
                'message' => $row['message'],
                'response' => $row['response'],
                'timestamp' => date('H:i', strtotime($row['timestamp']))
            ];
        }
        
        echo json_encode(['history' => array_reverse($history)]);
        exit();
    }
    
    if ($_POST['action'] === 'clear_history') {
        $stmt = $conn->prepare("DELETE FROM chat_history WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit();
    }
    
    if ($_POST['action'] === 'get_context_data') {
        $userData = getUserContextData($conn, $user_id, $username);
        echo json_encode(['data' => $userData]);
        exit();
    }
}

// Function to get user's context data from database
function getUserContextData($conn, $user_id, $username) {
    $data = [
        'appointments' => [],
        'todos' => [],
        'events' => [],
        'statistics' => []
    ];
    
    // Get user's appointments (both as counselor and student appointments they manage)
    $stmt = $conn->prepare("SELECT * FROM student_appointment WHERE username = ? ORDER BY appointmentDateTime DESC LIMIT 10");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $data['appointments'][] = [
            'id' => $row['id'],
            'student_name' => $row['studentName'],
            'student_id' => $row['studentId'],
            'student_email' => $row['studentEmail'],
            'phone_number' => $row['phoneNumber'],
            'datetime' => $row['appointmentDateTime'],
            'status' => $row['status'],
            'academic_issues' => $row['academicIssues'],
            'academic_type' => $row['academicIssueType'],
            'mental_health' => $row['mentalHealth'],
            'volunteer' => $row['volunteer'],
            'volunteer_type' => $row['volunteerType'],
            'referred' => $row['referred'],
            'referral_source' => $row['referralSource']
        ];
    }
    
    // Get user's todo list
    $stmt = $conn->prepare("SELECT * FROM todo_list WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $data['todos'][] = [
            'id' => $row['id'],
            'task' => $row['task'],
            'status' => $row['status'],
            'created_at' => $row['created_at']
        ];
    }
    
    // Get user's events
    $stmt = $conn->prepare("SELECT * FROM events WHERE user_id = ? ORDER BY event_date ASC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $data['events'][] = [
            'id' => $row['id'],
            'name' => $row['event_name'],
            'date' => $row['event_date'],
            'created_at' => $row['created_at']
        ];
    }
    
    // Calculate statistics
    $data['statistics'] = [
        'total_appointments' => count($data['appointments']),
        'pending_appointments' => count(array_filter($data['appointments'], function($apt) { return $apt['status'] === 'Pending'; })),
        'completed_appointments' => count(array_filter($data['appointments'], function($apt) { return $apt['status'] === 'Completed'; })),
        'pending_todos' => count(array_filter($data['todos'], function($todo) { return $todo['status'] === 'pending'; })),
        'completed_todos' => count(array_filter($data['todos'], function($todo) { return $todo['status'] === 'completed'; })),
        'upcoming_events' => count(array_filter($data['events'], function($event) { return strtotime($event['date']) >= strtotime('today'); }))
    ];
    
    return $data;
}

// Function to process messages and provide contextual responses
function processMessage($message, $userData, $conn, $user_id, $username) {
    $message_lower = strtolower($message);
    
    // Check for student-specific queries first
    if (preg_match('/(?:find|search|look|show).*student.*(?:name|called|named)\s+([a-zA-Z\s]+)/i', $message, $matches) ||
        preg_match('/student\s+([a-zA-Z\s]+)/i', $message, $matches)) {
        $studentName = trim($matches[1]);
        return findStudentByName($studentName, $userData['appointments'], $conn, $username);
    }
    
    // Student ID search
    if (preg_match('/(?:find|search|look|show).*(?:student.*id|id)\s*[:\-]?\s*([A-Z0-9]+)/i', $message, $matches) ||
        preg_match('/([A-Z]{3}[0-9]{9})/i', $message, $matches)) {
        $studentId = trim($matches[1]);
        return findStudentById($studentId, $userData['appointments'], $conn, $username);
    }
    
    // Issue-specific searches
    if (preg_match('/(?:show|find|list).*(?:mental health|depression|anxiety|stress)/i', $message)) {
        return findStudentsByIssue('mental_health', $userData['appointments']);
    }
    
    if (preg_match('/(?:show|find|list).*(?:academic|warning|barred)/i', $message)) {
        return findStudentsByIssue('academic', $userData['appointments']);
    }
    
    if (preg_match('/(?:show|find|list).*(?:volunteer|career)/i', $message)) {
        return findStudentsByIssue('volunteer', $userData['appointments']);
    }
    
    if (preg_match('/(?:show|find|list).*(?:referred|referral)/i', $message)) {
        return findStudentsByIssue('referred', $userData['appointments']);
    }
    
    // Status-based searches
    if (preg_match('/(?:show|find|list).*(?:pending|upcoming).*(?:appointment|student)/i', $message)) {
        return findStudentsByStatus('Pending', $userData['appointments']);
    }
    
    if (preg_match('/(?:show|find|list).*(?:completed|finished).*(?:appointment|student)/i', $message)) {
        return findStudentsByStatus('Completed', $userData['appointments']);
    }
    
    // Today's appointments
    if (preg_match('/(?:today|today\'s).*(?:appointment|student|schedule)/i', $message)) {
        return getTodaysAppointments($userData['appointments']);
    }
    
    // This week's appointments
    if (preg_match('/(?:this week|week|weekly).*(?:appointment|student|schedule)/i', $message)) {
        return getWeeklyAppointments($userData['appointments']);
    }
    
    // General appointment queries
    if (preg_match('/(?:show|list|view|get).*(?:appointment|meeting)/i', $message) || 
        preg_match('/(?:my|upcoming|pending).*appointment/i', $message)) {
        return getAppointmentsSummary($userData['appointments']);
    }
    
    if (preg_match('/(?:show|list|view|get).*(?:todo|task)/i', $message) || 
        preg_match('/(?:my|pending).*(?:todo|task)/i', $message)) {
        return getTodoSummary($userData['todos']);
    }
    
    if (preg_match('/(?:show|list|view|get).*(?:event|schedule)/i', $message) || 
        preg_match('/(?:my|upcoming).*event/i', $message)) {
        return getEventsSummary($userData['events']);
    }
    
    if (preg_match('/(?:summary|overview|dashboard|statistics|stats)/i', $message)) {
        return getDashboardSummary($userData);
    }
    
    if (preg_match('/(?:student.*issue|academic.*problem|help.*student)/i', $message)) {
        return getStudentIssuesInsights($userData['appointments']);
    }
    
    // If no specific data query, use AI with context
    return getAIResponseWithContext($message, $userData);
}

// Function to find student by name
function findStudentByName($studentName, $appointments, $conn, $username) {
    $foundStudents = [];
    
    foreach ($appointments as $apt) {
        if (stripos($apt['student_name'], $studentName) !== false) {
            $foundStudents[] = $apt;
        }
    }
    
    if (empty($foundStudents)) {
        return "🔍 **Student Search: \"" . htmlspecialchars($studentName) . "\"**\n\nNo students found with that name in your appointments.\n\n**Try:**\n- Check the spelling\n- Use partial names (e.g., \"Ahmad\" instead of full name)\n- Search by Student ID instead\n- Type \"show my appointments\" to see all students";
    }
    
    $response = "🔍 **Found " . count($foundStudents) . " student(s) matching \"" . htmlspecialchars($studentName) . "\":**\n\n";
    
    foreach ($foundStudents as $apt) {
        $response .= "👤 **" . htmlspecialchars($apt['student_name']) . "**\n";
        $response .= "🆔 ID: " . htmlspecialchars($apt['student_id']) . "\n";
        $response .= "📧 Email: " . htmlspecialchars($apt['student_email']) . "\n";
        $response .= "📞 Phone: " . htmlspecialchars($apt['phone_number']) . "\n";
        $response .= "📅 Appointment: " . date('M d, Y - g:i A', strtotime($apt['datetime'])) . "\n";
        $response .= "📊 Status: **" . $apt['status'] . "**\n";
        
        $issues = [];
        if ($apt['academic_issues']) $issues[] = "Academic" . ($apt['academic_type'] ? " (" . $apt['academic_type'] . ")" : "");
        if ($apt['mental_health']) $issues[] = "Mental Health";
        if ($apt['volunteer']) $issues[] = "Volunteer" . ($apt['volunteer_type'] ? " (" . $apt['volunteer_type'] . ")" : "");
        if ($apt['referred']) $issues[] = "Referred" . ($apt['referral_source'] ? " (" . $apt['referral_source'] . ")" : "");
        
        if (!empty($issues)) {
            $response .= "🏷️ Issues: " . implode(", ", $issues) . "\n";
        }
        $response .= "\n";
    }
    
    return $response;
}

// Function to find student by ID
function findStudentById($studentId, $appointments, $conn, $username) {
    $foundStudent = null;
    
    foreach ($appointments as $apt) {
        if (stripos($apt['student_id'], $studentId) !== false) {
            $foundStudent = $apt;
            break;
        }
    }
    
    if (!$foundStudent) {
        return "🔍 **Student ID Search: \"" . htmlspecialchars($studentId) . "\"**\n\nNo student found with that ID in your appointments.\n\n**Try:**\n- Check the ID format (e.g., ASJ233510004)\n- Search by student name instead\n- Type \"show my appointments\" to see all students";
    }
    
    $response = "🔍 **Student Found:**\n\n";
    $response .= "👤 **" . htmlspecialchars($foundStudent['student_name']) . "**\n";
    $response .= "🆔 Student ID: " . htmlspecialchars($foundStudent['student_id']) . "\n";
    $response .= "📧 Email: " . htmlspecialchars($foundStudent['student_email']) . "\n";
    $response .= "📞 Phone: " . htmlspecialchars($foundStudent['phone_number']) . "\n";
    $response .= "📅 Appointment: " . date('M d, Y - g:i A', strtotime($foundStudent['datetime'])) . "\n";
    $response .= "📊 Status: **" . $foundStudent['status'] . "**\n";
    
    $issues = [];
    if ($foundStudent['academic_issues']) $issues[] = "Academic" . ($foundStudent['academic_type'] ? " (" . $foundStudent['academic_type'] . ")" : "");
    if ($foundStudent['mental_health']) $issues[] = "Mental Health";
    if ($foundStudent['volunteer']) $issues[] = "Volunteer" . ($foundStudent['volunteer_type'] ? " (" . $foundStudent['volunteer_type'] . ")" : "");
    if ($foundStudent['referred']) $issues[] = "Referred" . ($foundStudent['referral_source'] ? " (" . $foundStudent['referral_source'] . ")" : "");
    
    if (!empty($issues)) {
        $response .= "🏷️ Issues: " . implode(", ", $issues) . "\n";
    }
    
    return $response;
}

// Function to find students by issue type
function findStudentsByIssue($issueType, $appointments) {
    $foundStudents = [];
    
    foreach ($appointments as $apt) {
        $match = false;
        switch ($issueType) {
            case 'mental_health':
                $match = $apt['mental_health'];
                break;
            case 'academic':
                $match = $apt['academic_issues'];
                break;
            case 'volunteer':
                $match = $apt['volunteer'];
                break;
            case 'referred':
                $match = $apt['referred'];
                break;
        }
        
        if ($match) {
            $foundStudents[] = $apt;
        }
    }
    
    if (empty($foundStudents)) {
        return "🔍 **" . ucfirst(str_replace('_', ' ', $issueType)) . " Cases**\n\nNo students found with " . str_replace('_', ' ', $issueType) . " issues in your appointments.";
    }
    
    $response = "🔍 **" . ucfirst(str_replace('_', ' ', $issueType)) . " Cases (" . count($foundStudents) . " students):**\n\n";
    
    foreach ($foundStudents as $apt) {
        $response .= "👤 **" . htmlspecialchars($apt['student_name']) . "** (" . $apt['student_id'] . ")\n";
        $response .= "📅 " . date('M d, Y - g:i A', strtotime($apt['datetime'])) . " | Status: " . $apt['status'] . "\n";
        
        // Show specific issue details
        if ($issueType === 'academic' && $apt['academic_type']) {
            $response .= "📚 Academic Type: " . $apt['academic_type'] . "\n";
        }
        if ($issueType === 'volunteer' && $apt['volunteer_type']) {
            $response .= "🤝 Volunteer Type: " . $apt['volunteer_type'] . "\n";
        }
        if ($issueType === 'referred' && $apt['referral_source']) {
            $response .= "👥 Referral Source: " . $apt['referral_source'] . "\n";
        }
        
        $response .= "\n";
    }
    
    return $response;
}

// Function to find students by status
function findStudentsByStatus($status, $appointments) {
    $foundStudents = array_filter($appointments, function($apt) use ($status) {
        return $apt['status'] === $status;
    });
    
    if (empty($foundStudents)) {
        return "🔍 **" . $status . " Appointments**\n\nNo " . strtolower($status) . " appointments found.";
    }
    
    $response = "🔍 **" . $status . " Appointments (" . count($foundStudents) . " students):**\n\n";
    
    foreach ($foundStudents as $apt) {
        $response .= "👤 **" . htmlspecialchars($apt['student_name']) . "** (" . $apt['student_id'] . ")\n";
        $response .= "📅 " . date('M d, Y - g:i A', strtotime($apt['datetime'])) . "\n";
        
        $issues = [];
        if ($apt['academic_issues']) $issues[] = "Academic" . ($apt['academic_type'] ? " (" . $apt['academic_type'] . ")" : "");
        if ($apt['mental_health']) $issues[] = "Mental Health";
        if ($apt['volunteer']) $issues[] = "Volunteer" . ($apt['volunteer_type'] ? " (" . $apt['volunteer_type'] . ")" : "");
        if ($apt['referred']) $issues[] = "Referred" . ($apt['referral_source'] ? " (" . $apt['referral_source'] . ")" : "");
        
        if (!empty($issues)) {
            $response .= "🏷️ " . implode(", ", $issues) . "\n";
        }
        $response .= "\n";
    }
    
    return $response;
}

// Function to get today's appointments
function getTodaysAppointments($appointments) {
    $today = date('Y-m-d');
    $todaysAppointments = array_filter($appointments, function($apt) use ($today) {
        return date('Y-m-d', strtotime($apt['datetime'])) === $today;
    });
    
    if (empty($todaysAppointments)) {
        return "📅 **Today's Appointments**\n\nNo appointments scheduled for today (" . date('M d, Y') . ").\n\nHave a great day!";
    }
    
    $response = "📅 **Today's Appointments (" . date('M d, Y') . "):**\n\n";
    
    // Sort by time
    usort($todaysAppointments, function($a, $b) {
        return strtotime($a['datetime']) - strtotime($b['datetime']);
    });
    
    foreach ($todaysAppointments as $apt) {
        $time = date('g:i A', strtotime($apt['datetime']));
        $response .= "⏰ **" . $time . "** - " . htmlspecialchars($apt['student_name']) . " (" . $apt['student_id'] . ")\n";
        $response .= "📊 Status: " . $apt['status'] . "\n";
        
        $issues = [];
        if ($apt['academic_issues']) $issues[] = "Academic" . ($apt['academic_type'] ? " (" . $apt['academic_type'] . ")" : "");
        if ($apt['mental_health']) $issues[] = "Mental Health";
        if ($apt['volunteer']) $issues[] = "Volunteer" . ($apt['volunteer_type'] ? " (" . $apt['volunteer_type'] . ")" : "");
        if ($apt['referred']) $issues[] = "Referred" . ($apt['referral_source'] ? " (" . $apt['referral_source'] . ")" : "");
        
        if (!empty($issues)) {
            $response .= "🏷️ " . implode(", ", $issues) . "\n";
        }
        $response .= "\n";
    }
    
    return $response;
}

// Function to get this week's appointments
function getWeeklyAppointments($appointments) {
    // Get the current date
    $today = date('Y-m-d');
    $currentDayOfWeek = date('w'); // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
    
    // Calculate start of week (Sunday)
    if ($currentDayOfWeek == 0) {
        // If today is Sunday, start of week is today
        $startOfWeek = $today;
    } else {
        // Otherwise, go back to the previous Sunday
        $daysToSubtract = $currentDayOfWeek;
        $startOfWeek = date('Y-m-d', strtotime($today . " -$daysToSubtract days"));
    }
    
    // Calculate end of week (Saturday)
    $daysToAdd = 6 - $currentDayOfWeek;
    if ($currentDayOfWeek == 0) {
        // If today is Sunday, Saturday is 6 days ahead
        $endOfWeek = date('Y-m-d', strtotime($today . " +6 days"));
    } else {
        $endOfWeek = date('Y-m-d', strtotime($today . " +$daysToAdd days"));
    }
    
    $weeklyAppointments = array_filter($appointments, function($apt) use ($startOfWeek, $endOfWeek) {
        $aptDate = date('Y-m-d', strtotime($apt['datetime']));
        return $aptDate >= $startOfWeek && $aptDate <= $endOfWeek;
    });
    
    if (empty($weeklyAppointments)) {
        return "📅 **This Week's Appointments**\n\nNo appointments scheduled for this week (" . date('M d', strtotime($startOfWeek)) . " - " . date('M d, Y', strtotime($endOfWeek)) . ").";
    }
    
    $response = "📅 **This Week's Appointments (" . date('M d', strtotime($startOfWeek)) . " - " . date('M d, Y', strtotime($endOfWeek)) . "):**\n\n";
    
    // Sort by date and time
    usort($weeklyAppointments, function($a, $b) {
        return strtotime($a['datetime']) - strtotime($b['datetime']);
    });
    
    $currentDate = '';
    foreach ($weeklyAppointments as $apt) {
        $aptDate = date('Y-m-d', strtotime($apt['datetime']));
        if ($aptDate !== $currentDate) {
            $currentDate = $aptDate;
            $response .= "**" . date('l, M d', strtotime($apt['datetime'])) . ":**\n";
        }
        
        $time = date('g:i A', strtotime($apt['datetime']));
        $response .= "  ⏰ " . $time . " - " . htmlspecialchars($apt['student_name']) . " (" . $apt['student_id'] . ") - " . $apt['status'] . "\n";
    }
    
    return $response;
}

// Function to get appointments summary
function getAppointmentsSummary($appointments) {
    if (empty($appointments)) {
        return "📅 **Your Appointments**\n\nYou don't have any appointments scheduled currently. You can add new student appointments through the Student Appointment page.";
    }
    
    $pending = array_filter($appointments, function($apt) { return $apt['status'] === 'Pending'; });
    $completed = array_filter($appointments, function($apt) { return $apt['status'] === 'Completed'; });
    
    $response = "📅 **Your Appointments Summary**\n\n";
    $response .= "**📊 Overview:**\n";
    $response .= "- Total: " . count($appointments) . " appointments\n";
    $response .= "- Pending: " . count($pending) . "\n";
    $response .= "- Completed: " . count($completed) . "\n\n";
    
    if (!empty($pending)) {
        $response .= "**⏰ Upcoming Pending Appointments:**\n";
        $count = 0;
        foreach ($pending as $apt) {
            if ($count >= 5) break; // Limit to 5 recent
            $response .= "• **" . htmlspecialchars($apt['student_name']) . "** (" . $apt['student_id'] . ")\n";
            $response .= "  📅 " . date('M d, Y - g:i A', strtotime($apt['datetime'])) . "\n";
            
            $issues = [];
            if ($apt['academic_issues']) $issues[] = "Academic" . ($apt['academic_type'] ? " (" . $apt['academic_type'] . ")" : "");
            if ($apt['mental_health']) $issues[] = "Mental Health";
            if ($apt['volunteer']) $issues[] = "Volunteer" . ($apt['volunteer_type'] ? " (" . $apt['volunteer_type'] . ")" : "");
            if ($apt['referred']) $issues[] = "Referred" . ($apt['referral_source'] ? " (" . $apt['referral_source'] . ")" : "");
            
            if (!empty($issues)) {
                $response .= "  🏷️ Issues: " . implode(", ", $issues) . "\n";
            }
            $response .= "\n";
            $count++;
        }
    }
    
    return $response;
}

// Function to get todo summary
function getTodoSummary($todos) {
    if (empty($todos)) {
        return "✅ **Your To-Do List**\n\nYour to-do list is empty. You can add new tasks from your dashboard to stay organized!";
    }
    
    $pending = array_filter($todos, function($todo) { return $todo['status'] === 'pending'; });
    $completed = array_filter($todos, function($todo) { return $todo['status'] === 'completed'; });
    
    $response = "✅ **Your To-Do List**\n\n";
    $response .= "**📊 Overview:**\n";
    $response .= "- Total tasks: " . count($todos) . "\n";
    $response .= "- Pending: " . count($pending) . "\n";
    $response .= "- Completed: " . count($completed) . "\n\n";
    
    if (!empty($pending)) {
        $response .= "**📝 Pending Tasks:**\n";
        foreach ($pending as $todo) {
            $response .= "• " . htmlspecialchars($todo['task']) . "\n";
            $response .= "  📅 Added: " . date('M d, Y', strtotime($todo['created_at'])) . "\n\n";
        }
    }
    
    if (!empty($completed)) {
        $response .= "**✅ Recently Completed:**\n";
        $recentCompleted = array_slice($completed, 0, 3);
        foreach ($recentCompleted as $todo) {
            $response .= "• ~~" . htmlspecialchars($todo['task']) . "~~\n";
        }
    }
    
    return $response;
}

// Function to get events summary
function getEventsSummary($events) {
    if (empty($events)) {
        return "📅 **Your Events**\n\nNo events scheduled. You can add events from your dashboard to keep track of important dates!";
    }
    
    $today = strtotime('today');
    $upcoming = array_filter($events, function($event) use ($today) { 
        return strtotime($event['date']) >= $today; 
    });
    $past = array_filter($events, function($event) use ($today) { 
        return strtotime($event['date']) < $today; 
    });
    
    $response = "📅 **Your Events**\n\n";
    $response .= "**📊 Overview:**\n";
    $response .= "- Total events: " . count($events) . "\n";
    $response .= "- Upcoming: " . count($upcoming) . "\n";
    $response .= "- Past: " . count($past) . "\n\n";
    
    if (!empty($upcoming)) {
        $response .= "**🔜 Upcoming Events:**\n";
        usort($upcoming, function($a, $b) { return strtotime($a['date']) - strtotime($b['date']); });
        
        foreach ($upcoming as $event) {
            $response .= "• **" . htmlspecialchars($event['name']) . "**\n";
            $response .= "  📅 " . date('M d, Y', strtotime($event['date'])) . "\n";
            
            $days_until = ceil((strtotime($event['date']) - time()) / (60 * 60 * 24));
            if ($days_until == 0) {
                $response .= "  ⏰ Today!\n";
            } elseif ($days_until == 1) {
                $response .= "  ⏰ Tomorrow\n";
            } else {
                $response .= "  ⏰ In " . $days_until . " days\n";
            }
            $response .= "\n";
        }
    }
    
    return $response;
}

// Function to get dashboard summary
function getDashboardSummary($userData) {
    $stats = $userData['statistics'];
    
    $response = "📊 **Your Dashboard Summary**\n\n";
    
    $response .= "**📅 Appointments:**\n";
    $response .= "- Total: " . $stats['total_appointments'] . "\n";
    $response .= "- Pending: " . $stats['pending_appointments'] . "\n";
    $response .= "- Completed: " . $stats['completed_appointments'] . "\n\n";
    
    $response .= "**✅ Tasks:**\n";
    $response .= "- Pending: " . $stats['pending_todos'] . "\n";
    $response .= "- Completed: " . $stats['completed_todos'] . "\n\n";
    
    $response .= "**📅 Events:**\n";
    $response .= "- Upcoming: " . $stats['upcoming_events'] . "\n\n";
    
    // Provide insights
    if ($stats['pending_appointments'] > 0) {
        $response .= "💡 **Insights:**\n";
        $response .= "- You have " . $stats['pending_appointments'] . " pending appointment(s) that need attention\n";
    }
    
    if ($stats['pending_todos'] > 5) {
        $response .= "- You have quite a few pending tasks (" . $stats['pending_todos'] . "). Consider prioritizing them!\n";
    }
    
    if ($stats['upcoming_events'] > 0) {
        $response .= "- Don't forget about your " . $stats['upcoming_events'] . " upcoming event(s)\n";
    }
    
    return $response;
}

// Function to get student issues insights
function getStudentIssuesInsights($appointments) {
    if (empty($appointments)) {
        return "📊 **Student Issues Analysis**\n\nNo appointment data available for analysis.";
    }
    
    $academic_issues = 0;
    $mental_health = 0;
    $volunteer = 0;
    $referred = 0;
    $academic_types = [];
    
    foreach ($appointments as $apt) {
        if ($apt['academic_issues']) {
            $academic_issues++;
            if ($apt['academic_type']) {
                $academic_types[$apt['academic_type']] = ($academic_types[$apt['academic_type']] ?? 0) + 1;
            }
        }
        if ($apt['mental_health']) $mental_health++;
        if ($apt['volunteer']) $volunteer++;
        if ($apt['referred']) $referred++;
    }
    
    $response = "📊 **Student Issues Analysis**\n\n";
    $response .= "**Based on your " . count($appointments) . " recent appointments:**\n\n";
    
    $response .= "**🎓 Academic Issues:** " . $academic_issues . " cases\n";
    if (!empty($academic_types)) {
        foreach ($academic_types as $type => $count) {
            $response .= "  - " . $type . ": " . $count . " case(s)\n";
        }
    }
    
    $response .= "**🧠 Mental Health:** " . $mental_health . " cases\n";
    $response .= "**🤝 Volunteer Issues:** " . $volunteer . " cases\n";
    $response .= "**👥 Referred Cases:** " . $referred . " cases\n\n";
    
    // Provide insights
    $response .= "**💡 Insights & Recommendations:**\n";
    
    if ($academic_issues > count($appointments) * 0.5) {
        $response .= "- Academic issues are prevalent. Consider organizing study skills workshops\n";
    }
    
    if ($mental_health > 0) {
        $response .= "- Mental health cases present. Ensure proper referral protocols are in place\n";
    }
    
    if (isset($academic_types['BARRED']) && $academic_types['BARRED'] > 0) {
        $response .= "- " . $academic_types['BARRED'] . " BARRED cases need immediate attention and intervention\n";
    }
    
    return $response;
}

// Function to get AI response with context using external API
function getAIResponseWithContext($message, $userData) {
    $stats = $userData['statistics'];
    
    // Create detailed database context for AI
    $databaseContext = createDatabaseContext($userData);
    
    // Enhanced system prompt with complete database context
    $systemPrompt = "You are a helpful AI counseling assistant for MyCounsel, a student counseling management system.

IMPORTANT: You have access to the counselor's real database information below. Use this data to provide personalized, data-driven responses.

" . $databaseContext . "

INSTRUCTIONS:
1. Provide supportive, empathetic responses for counseling work
2. Reference the actual data from their system when relevant
3. Give practical advice based on their real workload and student cases
4. Help with time management considering their actual appointments
5. Provide insights about student issue patterns from their data
6. Be encouraging about their completed work
7. Format responses with markdown for readability
8. Suggest specific actions they can take in their MyCounsel system

AVAILABLE COMMANDS to mention when helpful:
- \"show my appointments\" - displays appointment details
- \"show my todos\" - shows task list
- \"show my events\" - shows upcoming events  
- \"dashboard summary\" - complete overview
- \"student issues analysis\" - analyzes appointment patterns

Remember: You're helping a counselor manage their workload and provide better student support. Always reference their actual data when giving advice!";

    try {
        // Use cURL to make the API request
        $curl = curl_init();
        
        $requestData = [
            'model' => 'deepseek/deepseek-r1-0528:free',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $message]
            ],
            'temperature' => 0.7,
            'max_tokens' => 1000
        ];
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://openrouter.ai/api/v1/chat/completions",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer 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",
                "HTTP-Referer: https://mycounsel.com",
                "X-Title: MyCounsel Smart Helper",
                "Content-Type: application/json"
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: " . $httpCode);
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            throw new Exception("API Error: " . $data['error']['message']);
        }
        
        $aiResponse = $data['choices'][0]['message']['content'] ?? 'Sorry, I couldn\'t generate a response.';
        return $aiResponse;
        
    } catch (Exception $e) {
        error_log("AI API Error: " . $e->getMessage());
        
        // Fallback to contextual response if API fails
        return getFallbackResponse($message, $userData);
    }
}

// Function to create comprehensive database context for AI
function createDatabaseContext($userData) {
    $stats = $userData['statistics'];
    $context = "=== COUNSELOR'S CURRENT DATABASE STATUS ===\n\n";
    
    // Appointments Overview
    $context .= "APPOINTMENTS SUMMARY:\n";
    $context .= "- Total appointments managed: " . $stats['total_appointments'] . "\n";
    $context .= "- Pending appointments: " . $stats['pending_appointments'] . "\n";
    $context .= "- Completed appointments: " . $stats['completed_appointments'] . "\n\n";
    
    // Recent Appointments Details
    if (!empty($userData['appointments'])) {
        $context .= "RECENT APPOINTMENTS:\n";
        $recentAppointments = array_slice($userData['appointments'], 0, 5);
        foreach ($recentAppointments as $apt) {
            $context .= "• Student: " . $apt['student_name'] . " (" . $apt['student_id'] . ")\n";
            $context .= "  Date: " . date('M d, Y g:i A', strtotime($apt['datetime'])) . "\n";
            $context .= "  Status: " . $apt['status'] . "\n";
            
            $issues = [];
            if ($apt['academic_issues']) $issues[] = "Academic" . ($apt['academic_type'] ? " (" . $apt['academic_type'] . ")" : "");
            if ($apt['mental_health']) $issues[] = "Mental Health";
            if ($apt['volunteer']) $issues[] = "Volunteer" . ($apt['volunteer_type'] ? " (" . $apt['volunteer_type'] . ")" : "");
            if ($apt['referred']) $issues[] = "Referred" . ($apt['referral_source'] ? " (" . $apt['referral_source'] . ")" : "");
            
            if (!empty($issues)) {
                $context .= "  Issues: " . implode(", ", $issues) . "\n";
            }
            $context .= "\n";
        }
    }
    
    // Task Management
    $context .= "TASK MANAGEMENT:\n";
    $context .= "- Pending tasks: " . $stats['pending_todos'] . "\n";
    $context .= "- Completed tasks: " . $stats['completed_todos'] . "\n";
    
    if (!empty($userData['todos'])) {
        $pendingTodos = array_filter($userData['todos'], function($todo) { return $todo['status'] === 'pending'; });
        if (!empty($pendingTodos)) {
            $context .= "Current pending tasks:\n";
            foreach (array_slice($pendingTodos, 0, 5) as $todo) {
                $context .= "• " . $todo['task'] . "\n";
            }
        }
    }
    $context .= "\n";
    
    // Events Schedule
    $context .= "EVENTS SCHEDULE:\n";
    $context .= "- Upcoming events: " . $stats['upcoming_events'] . "\n";
    
    if (!empty($userData['events'])) {
        $today = strtotime('today');
        $upcomingEvents = array_filter($userData['events'], function($event) use ($today) { 
            return strtotime($event['date']) >= $today; 
        });
        
        if (!empty($upcomingEvents)) {
            $context .= "Upcoming events:\n";
            usort($upcomingEvents, function($a, $b) { return strtotime($a['date']) - strtotime($b['date']); });
            foreach (array_slice($upcomingEvents, 0, 3) as $event) {
                $context .= "• " . $event['name'] . " - " . date('M d, Y', strtotime($event['date'])) . "\n";
            }
        }
    }
    $context .= "\n";
    
    // Issue Analysis
    if (!empty($userData['appointments'])) {
        $issueStats = analyzeIssuePatterns($userData['appointments']);
        $context .= "STUDENT ISSUE PATTERNS:\n";
        foreach ($issueStats as $issue => $count) {
            $context .= "- " . $issue . ": " . $count . " cases\n";
        }
        $context .= "\n";
    }
    
    $context .= "=== END DATABASE STATUS ===\n\n";
    $context .= "Use this information to provide personalized advice and insights about their counseling work.";
    
    return $context;
}

// Function to analyze issue patterns from appointments
function analyzeIssuePatterns($appointments) {
    $patterns = [];
    
    foreach ($appointments as $apt) {
        if ($apt['academic_issues']) {
            $type = $apt['academic_type'] ? "Academic - " . $apt['academic_type'] : "Academic - General";
            $patterns[$type] = ($patterns[$type] ?? 0) + 1;
        }
        if ($apt['mental_health']) {
            $patterns['Mental Health'] = ($patterns['Mental Health'] ?? 0) + 1;
        }
        if ($apt['volunteer']) {
            $type = $apt['volunteer_type'] ? "Volunteer - " . $apt['volunteer_type'] : "Volunteer - General";
            $patterns[$type] = ($patterns[$type] ?? 0) + 1;
        }
        if ($apt['referred']) {
            $type = $apt['referral_source'] ? "Referred - " . $apt['referral_source'] : "Referred - General";
            $patterns[$type] = ($patterns[$type] ?? 0) + 1;
        }
    }
    
    return $patterns;
}

// Fallback response function when API fails
function getFallbackResponse($message, $userData) {
    $stats = $userData['statistics'];
    $message_lower = strtolower($message);
    
    if (strpos($message_lower, 'stress') !== false || strpos($message_lower, 'overwhelmed') !== false) {
        $response = "I understand you're feeling stressed. ";
        if ($stats['pending_appointments'] > 5) {
            $response .= "I notice you have " . $stats['pending_appointments'] . " pending appointments - that's quite a workload! ";
        }
        if ($stats['pending_todos'] > 3) {
            $response .= "Plus " . $stats['pending_todos'] . " pending tasks. ";
        }
        
        $response .= "\n\n**Here are some strategies to help:**\n";
        $response .= "1. **Prioritize**: Focus on the most urgent appointments first\n";
        $response .= "2. **Break it down**: Tackle one appointment at a time\n";
        $response .= "3. **Take breaks**: Schedule short breaks between appointments\n";
        $response .= "4. **Delegate**: If possible, share the workload with colleagues\n\n";
        $response .= "Would you like me to show your current appointments or tasks to help you prioritize?";
        
        return $response;
    }
    
    // Default response when API is unavailable
    $response = "I'm having trouble connecting to my AI service right now, but I can still help with your MyCounsel data!\n\n";
    $response .= "**Your Current Status:**\n";
    $response .= "- Appointments: " . $stats['total_appointments'] . " total (" . $stats['pending_appointments'] . " pending)\n";
    $response .= "- Tasks: " . $stats['pending_todos'] . " pending, " . $stats['completed_todos'] . " completed\n";
    $response .= "- Events: " . $stats['upcoming_events'] . " upcoming\n\n";
    $response .= "**Try these commands:**\n";
    $response .= "- \"show my appointments\"\n";
    $response .= "- \"show my todos\"\n";
    $response .= "- \"dashboard summary\"\n";
    
    return $response;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Helper - AI Assistant | MyCounsel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.3.1/css/bootstrap.min.css"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/4.0.2/marked.min.js"></script>
    <link rel="stylesheet" href="/css/index.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f7fa;
            min-height: 100vh;
        }

        /* Chat Container Styling */
        .chat-container {
            max-width: 1000px;
            margin: 30px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .chat-header {
            background: linear-gradient(135deg, #cd8b62 0%, #b87952 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }

        .chat-header h2 {
            margin: 0;
            font-size: 24px;
        }

        .chat-header p {
            margin: 5px 0 0 0;
            opacity: 0.9;
            font-size: 14px;
        }

        .chat-body {
            height: 500px;
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
        }

        .chat-messages {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .message {
            display: flex;
            max-width: 80%;
            animation: slideIn 0.3s ease-out;
        }

        .message.user {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .message.ai {
            align-self: flex-start;
        }

        .message-bubble {
            padding: 12px 18px;
            border-radius: 18px;
            word-wrap: break-word;
            line-height: 1.4;
        }

        .message.user .message-bubble {
            background: linear-gradient(135deg, #4a6fa5 0%, #6d8ab5 100%);
            color: white;
            border-bottom-right-radius: 5px;
        }

        .message.ai .message-bubble {
            background: white;
            color: #333;
            border: 1px solid #e0e0e0;
            border-bottom-left-radius: 5px;
        }

        .message-time {
            font-size: 11px;
            opacity: 0.7;
            margin: 5px 10px;
            align-self: flex-end;
        }

        .chat-input-container {
            padding: 20px;
            background: white;
            border-top: 1px solid #e0e0e0;
        }

        .input-group {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        #userInput {
            flex: 1;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            padding: 12px 20px;
            font-size: 16px;
            outline: none;
            transition: border-color 0.3s ease;
            resize: none;
            min-height: 50px;
            max-height: 120px;
        }

        #userInput:focus {
            border-color: #cd8b62;
        }

        .send-btn {
            background: linear-gradient(135deg, #cd8b62 0%, #b87952 100%);
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 18px;
        }

        .send-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(205, 139, 98, 0.4);
        }

        .send-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .typing-indicator {
            display: none;
            padding: 10px 20px;
            background: white;
            border-radius: 18px;
            border: 1px solid #e0e0e0;
            margin: 10px 0;
            align-self: flex-start;
            max-width: 80px;
        }

        .typing-dots {
            display: flex;
            gap: 4px;
        }

        .typing-dots span {
            width: 8px;
            height: 8px;
            background: #cd8b62;
            border-radius: 50%;
            animation: typing 1.4s infinite ease-in-out;
        }

        .typing-dots span:nth-child(1) { animation-delay: -0.32s; }
        .typing-dots span:nth-child(2) { animation-delay: -0.16s; }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin: 20px;
            flex-wrap: wrap;
        }

        .action-btn {
            background: transparent;
            border: 2px solid #cd8b62;
            color: #cd8b62;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: #cd8b62;
            color: white;
        }

        .data-btn {
            background: transparent;
            border: 2px solid #4a6fa5;
            color: #4a6fa5;
        }

        .data-btn:hover {
            background: #4a6fa5;
            color: white;
        }

        .welcome-message {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .welcome-message h3 {
            color: #cd8b62;
            margin-bottom: 10px;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes typing {
            0%, 80%, 100% {
                transform: scale(0);
                opacity: 0.5;
            }
            40% {
                transform: scale(1);
                opacity: 1;
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .chat-container {
                margin: 20px 10px;
                border-radius: 10px;
            }

            .chat-body {
                height: 400px;
            }

            .message {
                max-width: 90%;
            }

            .action-buttons {
                margin: 15px;
            }
        }

        /* Markdown styling for AI responses */
        .message-bubble h1, .message-bubble h2, .message-bubble h3 {
            color: #cd8b62;
            margin: 10px 0 5px 0;
        }

        .message-bubble ul, .message-bubble ol {
            margin: 10px 0;
            padding-left: 20px;
        }

        .message-bubble li {
            margin: 5px 0;
        }

        .message-bubble code {
            background: #f1f1f1;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: monospace;
        }

        .message-bubble pre {
            background: #f1f1f1;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            margin: 10px 0;
        }

        .message-bubble blockquote {
            border-left: 4px solid #cd8b62;
            padding-left: 15px;
            margin: 10px 0;
            font-style: italic;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-spinner {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
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

        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            min-width: 200px;
            margin-top: 10px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }

        .user-profile:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-menu a {
            display: block;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .dropdown-menu a:hover {
            background: #f8f9fa;
            color: #cd8b62;
        }
    </style>
        
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <div class="typing-dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <p style="margin-top: 10px;">Loading your data...</p>
        </div>
    </div>

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
                <a href="/php/appointmentrecords.php">Appointment Records</a>
                <a href="/php/contact_us.php">Contact Us</a>
                <a href="/php/smart_helper.php" class="active">Smart Helper</a>
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

    <div class="chat-container">
        <div class="chat-header">
            <h2>🤖 Smart Counseling Assistant</h2>
            <p>Your AI-powered helper with access to your MyCounsel data</p>
        </div>

        <div class="action-buttons">
            <!-- Student Search Buttons -->
            <button class="action-btn data-btn" onclick="sendQuickMessage('show today appointments')">📅 Today's Students</button>
            <button class="action-btn data-btn" onclick="sendQuickMessage('show pending appointments')">⏳ Pending Students</button>
            <button class="action-btn data-btn" onclick="sendQuickMessage('show mental health students')">🧠 Mental Health Cases</button>
            <button class="action-btn data-btn" onclick="sendQuickMessage('show academic students')">📚 Academic Issues</button>
            <button class="action-btn data-btn" onclick="sendQuickMessage('show this week appointments')">📅 This Week</button>
            <button class="action-btn data-btn" onclick="sendQuickMessage('show completed appointments')">✅ Completed</button>
            
            <!-- General Helper Buttons -->
            <button class="action-btn" onclick="sendQuickMessage('I need help managing my student caseload')">👥 Caseload Help</button>
            <button class="action-btn" onclick="sendQuickMessage('Help me prioritize my appointments')">⚡ Prioritize</button>
            <button class="action-btn" onclick="sendQuickMessage('dashboard summary')">📊 Dashboard</button>
            <button class="action-btn" onclick="clearChat()">🗑️ Clear Chat</button>
        </div>

        <div class="chat-body" id="chatBody">
            <div class="chat-messages" id="chatMessages">
                <div class="welcome-message">
                    <h3>Welcome to Smart Helper!</h3>
                    <p>I'm your AI assistant for managing student appointments and counseling cases.</p>
                    <p><strong>🔍 Search for students by:</strong></p>
                    <p>• Name: "find student Ahmad" or "student Nur"</p>
                    <p>• ID: "find ASJ233510004" or just type the ID</p>
                    <p>• Issue type: "show mental health students" or "academic cases"</p>
                    <p>• Status: "pending appointments" or "completed students"</p>
                    <p>• Time: "today's appointments" or "this week students"</p>
                </div>
            </div>
            
            <div class="typing-indicator" id="typingIndicator">
                <div class="typing-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>

        <div class="chat-input-container">
            <div class="input-group">
                <textarea 
                    id="userInput" 
                    placeholder="Ask me about your appointments, tasks, events, or any counseling questions..."
                    rows="1"
                ></textarea>
                <button class="send-btn" id="sendBtn" onclick="sendMessage()">
                    ➤
                </button>
            </div>
        </div>
    </div>

    <script>
        let isWaitingForResponse = false;
        let userContextData = null;

        // Load user context data when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadUserContextData();
            loadChatHistory();
            document.getElementById('userInput').focus();
        });

        // Auto-resize textarea
        document.getElementById('userInput').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });

        // Send message on Enter key (but allow Shift+Enter for new line)
        document.getElementById('userInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        async function loadUserContextData() {
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_context_data'
                });
                
                const data = await response.json();
                userContextData = data.data;
                console.log('User context loaded:', userContextData);
            } catch (error) {
                console.error('Error loading user context:', error);
            }
        }

        async function loadChatHistory() {
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_history'
                });
                
                const data = await response.json();
                
                if (data.history && data.history.length > 0) {
                    const chatMessages = document.getElementById('chatMessages');
                    const welcomeMessage = chatMessages.querySelector('.welcome-message');
                    if (welcomeMessage) {
                        welcomeMessage.remove();
                    }
                    
                    data.history.forEach(chat => {
                        addMessage(chat.message, true, chat.timestamp);
                        addMessage(chat.response, false, chat.timestamp);
                    });
                }
            } catch (error) {
                console.error('Error loading chat history:', error);
            }
        }

        function sendQuickMessage(message) {
            document.getElementById('userInput').value = message;
            sendMessage();
        }

        function addMessage(content, isUser, timestamp = null) {
            const chatMessages = document.getElementById('chatMessages');
            const welcomeMessage = chatMessages.querySelector('.welcome-message');
            
            // Remove welcome message if it exists
            if (welcomeMessage) {
                welcomeMessage.remove();
            }

            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isUser ? 'user' : 'ai'}`;
            
            const time = timestamp || new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            messageDiv.innerHTML = `
                <div class="message-bubble">
                    ${isUser ? escapeHtml(content) : marked.parse(content)}
                </div>
                <div class="message-time">${time}</div>
            `;
            
            chatMessages.appendChild(messageDiv);
            scrollToBottom();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showTypingIndicator() {
            document.getElementById('typingIndicator').style.display = 'block';
            scrollToBottom();
        }

        function hideTypingIndicator() {
            document.getElementById('typingIndicator').style.display = 'none';
        }

        function scrollToBottom() {
            const chatBody = document.getElementById('chatBody');
            chatBody.scrollTop = chatBody.scrollHeight;
        }

        function updateSendButton(disabled) {
            const sendBtn = document.getElementById('sendBtn');
            sendBtn.disabled = disabled;
            sendBtn.innerHTML = disabled ? '⏳' : '➤';
            isWaitingForResponse = disabled;
        }

        async function sendMessage() {
            const input = document.getElementById('userInput');
            const message = input.value.trim();
            
            if (!message || isWaitingForResponse) {
                return;
            }

            // Add user message
            addMessage(message, true);
            input.value = '';
            input.style.height = 'auto';
            
            // Show typing indicator and disable send button
            showTypingIndicator();
            updateSendButton(true);

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=send_message&message=${encodeURIComponent(message)}`
                });

                const data = await response.json();
                
                if (data.error) {
                    throw new Error(data.error);
                }

                const aiResponse = data.response || 'Sorry, I couldn\'t generate a response. Please try again.';
                
                // Hide typing indicator and add AI response
                hideTypingIndicator();
                addMessage(aiResponse, false, data.timestamp);
                
            } catch (error) {
                console.error('Error:', error);
                hideTypingIndicator();
                
                // Fallback response for errors
                const fallbackResponse = `I apologize, but I'm having trouble processing your request right now. Here are some things you can try:

**Available Commands:**
- "show my appointments" - View your student appointments
- "show my todos" - View your task list  
- "show my events" - View your upcoming events
- "dashboard summary" - Get an overview of everything
- "student issues analysis" - Analyze student appointment patterns

**General Help:**
- Ask about stress management
- Request time management tips
- Seek career guidance
- Get study advice

Would you like to try one of these commands?`;
                
                addMessage(fallbackResponse, false);
            } finally {
                updateSendButton(false);
            }
        }

        async function clearChat() {
            if (confirm('Are you sure you want to clear the chat history?')) {
                try {
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=clear_history'
                    });

                    const data = await response.json();
                    
                    if (data.success) {
                        const chatMessages = document.getElementById('chatMessages');
                        chatMessages.innerHTML = `
                            <div class="welcome-message">
                                <h3>Chat Cleared!</h3>
                                <p>How can I help you today?</p>
                                <p>Try asking about your appointments, tasks, or events!</p>
                            </div>
                        `;
                    }
                } catch (error) {
                    console.error('Error clearing chat:', error);
                    alert('Error clearing chat history');
                }
            }
        }
    </script>
</body>
</html>