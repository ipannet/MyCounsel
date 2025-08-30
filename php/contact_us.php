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

// Check if messages table exists with proper structure
$checkTableSql = "SHOW TABLES LIKE 'messages'";
$tableExists = $conn->query($checkTableSql)->num_rows > 0;

if (!$tableExists) {
    // Create the messages table with ID-only structure
    $createTableSql = "CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        recipient_id INT NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        sent_date DATETIME NOT NULL,
        read_status TINYINT(1) DEFAULT 0,
        FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (recipient_id) REFERENCES users(user_id) ON DELETE CASCADE
    )";
    $conn->query($createTableSql);
}

// Handle message submission
$messageSent = false;
$errorMessage = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
    $recipient_username = $_POST['recipient'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    $sender_id = $user_id;
    
    // Get recipient user_id from username
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->bind_param("s", $recipient_username);
    $stmt->execute();
    $recipient_result = $stmt->get_result();
    $recipient_data = $recipient_result->fetch_assoc();
    
    if ($recipient_data) {
        $recipient_id = $recipient_data['user_id'];
        
        // Insert message using only user IDs
        $insertSql = "INSERT INTO messages (sender_id, recipient_id, subject, message, sent_date, read_status) 
                      VALUES (?, ?, ?, ?, NOW(), 0)";
        $stmt = $conn->prepare($insertSql);
        $stmt->bind_param("iiss", $sender_id, $recipient_id, $subject, $message);
        
        if ($stmt->execute()) {
            $messageSent = true;
        } else {
            $errorMessage = "Error sending message: " . $conn->error;
        }
        $stmt->close();
    } else {
        $errorMessage = "Recipient not found.";
    }
}

// Fetch messages for the current user using user_id with JOIN to get usernames for display
$messagesSql = "
    SELECT m.*, 
           s.username as sender_username, 
           r.username as recipient_username
    FROM messages m
    JOIN users s ON m.sender_id = s.user_id
    JOIN users r ON m.recipient_id = r.user_id
    WHERE m.recipient_id = ? OR m.sender_id = ? 
    ORDER BY m.sent_date DESC
";
$stmt = $conn->prepare($messagesSql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$messagesResult = $stmt->get_result();
$stmt->close();

// Fetch all users for the recipient dropdown (excluding current user)
$usersSql = "SELECT user_id, username FROM users WHERE user_id != ?";
$stmt = $conn->prepare($usersSql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$usersResult = $stmt->get_result();
$stmt->close();

// Fetch admin users from the admins table for the admin tab
$adminSql = "SELECT a.username, u.user_id FROM admins a JOIN users u ON a.username = u.username";
try {
    $adminResult = $conn->query($adminSql);
} catch (Exception $e) {
    // If there was an error with the query, use a simpler fallback
    $adminSql = "SELECT user_id, username FROM users LIMIT 5";
    $adminResult = $conn->query($adminSql);
}

// If no admins found with the query, make a fallback
if ($adminResult->num_rows == 0) {
    // Fallback: Treat the first few users as admins
    $fallbackSql = "SELECT user_id, username FROM users LIMIT 5";
    $adminResult = $conn->query($fallbackSql);
}

// Mark message as read
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $messageId = $_GET['read'];
    $updateSql = "UPDATE messages SET read_status = 1 WHERE id = ? AND recipient_id = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("ii", $messageId, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: contact_us.php");
    exit();
}

// Delete message
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $messageId = $_GET['delete'];
    $deleteSql = "DELETE FROM messages WHERE id = ? AND (sender_id = ? OR recipient_id = ?)";
    $stmt = $conn->prepare($deleteSql);
    $stmt->bind_param("iii", $messageId, $user_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: contact_us.php");
    exit();
}

// Count unread messages
$unreadSql = "SELECT COUNT(*) as unread_count FROM messages WHERE recipient_id = ? AND read_status = 0";
$stmt = $conn->prepare($unreadSql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unreadResult = $stmt->get_result();
$unreadCount = $unreadResult->fetch_assoc()['unread_count'];
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | MyCounsel</title>
    <link rel="stylesheet" href="/css/index.css">
    <style>
        .contact-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        /* New Contact Tabs Styling */
        .contact-tabs {
            display: flex;
            border-bottom: 3px solid #cd8b62;
            margin-bottom: 25px;
            overflow-x: auto;
            background-color: #f9f9f9;
        }

        .tab {
            padding: 12px 20px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 16px;
            font-weight: 600;
            color: #555;
            position: relative;
            transition: all 0.3s ease;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab.active {
            color: #cd8b62;
            background-color: #fff;
        }

        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: #cd8b62;
        }

        .tab:hover {
            background-color: rgba(205, 139, 98, 0.1);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #6d8ab5;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 14px;
            font-weight: 600;
        }

        .tab.active .badge {
            background-color: #cd8b62;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .message-form {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group textarea {
            height: 150px;
            resize: vertical;
        }
        
        .btn {
            background-color: #4A90E2;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn:hover {
            background-color: #3A7BC8;
        }
        
        .messages-list {
            list-style: none;
            padding: 0;
        }
        
        .message-item {
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        
        .message-header {
            padding: 10px 15px;
            background-color: #f4f4f4;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .message-header h3 {
            margin: 0;
            font-size: 16px;
        }
        
        .message-meta {
            font-size: 14px;
            color: #666;
        }
        
        .message-body {
            padding: 15px;
            background-color: #fff;
        }
        
        .message-footer {
            padding: 10px 15px;
            background-color: #f4f4f4;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .message-footer .btn {
            padding: 5px 10px;
            font-size: 14px;
        }
        
        .btn-danger {
            background-color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .unread {
            position: relative;
        }
        
        .unread::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 5px;
            background-color: #4A90E2;
        }
        
        .alert {
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 0;
            color: #666;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }
        
        .reply-form {
            padding: 15px;
            background-color: #f9f9f9;
            display: none;
        }
        
        .contact-info {
            margin-top: 20px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
        }
        
        .contact-info h3 {
            margin-top: 0;
            margin-bottom: 15px;
        }
        
        .contact-item {
            margin-bottom: 10px;
        }
        
        .contact-item strong {
            margin-right: 10px;
        }
        
        .admin-contact-info {
            margin-top: 30px;
            padding: 15px;
            background-color: #f8f9fa;
            border-left: 4px solid #4A90E2;
            border-radius: 4px;
        }
        
        .admin-contact-info h3 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #333;
        }
        
        .admin-contact-info ul {
            padding-left: 20px;
        }
        
        .admin-contact-info li {
            margin-bottom: 5px;
        }
        /* User Profile */
        .user-profile {
            position: relative;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        .username {
            color: white;
            font-size: 16px;
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
            z-index: 1000;
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
                <a href="/php/appointmentrecords.php">Appointment Records</a>
                <a href="/php/contact_us.php" class="active">Contact Us</a>
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

    <div class="contact-container">
        <h1>Contact Portal</h1>
        <p>Use this portal to communicate with other counselors and administrators.</p>
        <br>
        
        <?php if ($messageSent): ?>
            <div class="alert alert-success">
                Your message has been sent successfully!
            </div>
        <?php endif; ?>
        
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger">
                <?php echo $errorMessage; ?>
            </div>
        <?php endif; ?>
        
        <div class="contact-tabs">
            <button class="tab active" onclick="openTab(event, 'compose')">
                Compose Message
            </button>
            <button class="tab" onclick="openTab(event, 'inbox')">
                Inbox
                <?php if ($unreadCount > 0): ?>
                    <span class="badge"><?php echo $unreadCount; ?></span>
                <?php endif; ?>
            </button>
            <button class="tab" onclick="openTab(event, 'sent')">
                Sent
                <?php
                // Count sent messages (optional)
                $sentCount = 0;
                $messagesResult->data_seek(0);
                while ($message = $messagesResult->fetch_assoc()) {
                    if ($message['sender_id'] == $user_id) {
                        $sentCount++;
                    }
                }
                if ($sentCount > 0): 
                ?>
                    <span class="badge"><?php echo $sentCount; ?></span>
                <?php endif; ?>
            </button>

            <button class="tab" onclick="openTab(event, 'admin')">
                Admin Contact
            </button>
        </div>
        
        <!-- Admin Communication Tab -->
        <div id="admin" class="tab-content">
            <div class="message-form">
                <h2>Contact Administrator</h2>
                <p>Use this form to communicate directly with system administrators.</p>
                
                <form method="post" action="">
                    <div class="form-group">
                        <label for="admin-recipient">Administrator:</label>
                        <select name="recipient" id="admin-recipient" required>
                            <option value="">Select Administrator</option>
                            <?php 
                            // Reset admin result pointer
                            if ($adminResult) {
                                $adminResult->data_seek(0);
                                while ($admin = $adminResult->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($admin['username']); ?>">
                                        <?php echo htmlspecialchars($admin['username']); ?> (Admin)
                                    </option>
                                <?php endwhile;
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin-subject">Subject:</label>
                        <input type="text" name="subject" id="admin-subject" required placeholder="e.g., System Issue, Account Request, etc.">
                    </div>
                    
                    <div class="form-group">
                        <label for="admin-message">Message:</label>
                        <textarea name="message" id="admin-message" required placeholder="Describe your issue or request in detail..."></textarea>
                    </div>
                    
                    <button type="submit" name="send_message" class="btn">Send to Administrator</button>
                </form>
            </div>
        </div>
                
        <!-- Compose Message Tab -->
        <div id="compose" class="tab-content active">
            <div class="message-form">
                <h2>Compose New Message</h2>
                <form method="post" action="">
                    <div class="form-group">
                        <label for="recipient">Recipient:</label>
                        <select name="recipient" id="recipient" required>
                            <option value="">Select Recipient</option>
                            <?php 
                            // Reset users result pointer
                            if ($usersResult) {
                                $usersResult->data_seek(0);
                                while ($user = $usersResult->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($user['username']); ?>">
                                        <?php echo htmlspecialchars($user['username']); ?>
                                    </option>
                                <?php endwhile; 
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Subject:</label>
                        <input type="text" name="subject" id="subject" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message:</label>
                        <textarea name="message" id="message" required></textarea>
                    </div>
                    
                    <button type="submit" name="send_message" class="btn">Send Message</button>
                </form>
            </div>
        </div>
        
        <!-- Inbox Tab -->
        <div id="inbox" class="tab-content">
            <h2>Inbox</h2>
            
            <ul class="messages-list">
                <?php
                $hasInboxMessages = false;
                // Reset message result pointer
                if ($messagesResult) {
                    $messagesResult->data_seek(0);
                    
                    while ($message = $messagesResult->fetch_assoc()) {
                        if ($message['recipient_id'] == $user_id) {
                            $hasInboxMessages = true;
                            $isUnread = $message['read_status'] == 0;
                    ?>
                        <li class="message-item <?php echo $isUnread ? 'unread' : ''; ?>">
                            <div class="message-header">
                                <h3><?php echo htmlspecialchars($message['subject']); ?></h3>
                                <div class="message-meta">
                                    From: <?php echo htmlspecialchars($message['sender_username']); ?> | 
                                    <?php echo date('M d, Y H:i', strtotime($message['sent_date'])); ?>
                                </div>
                            </div>
                            <div class="message-body">
                                <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                            </div>
                            <div class="message-footer">
                                <button class="btn" onclick="showReplyForm(<?php echo $message['id']; ?>)">Reply</button>
                                <?php if ($isUnread): ?>
                                    <a href="?read=<?php echo $message['id']; ?>" class="btn">Mark as Read</a>
                                <?php endif; ?>
                                <a href="?delete=<?php echo $message['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this message?');">Delete</a>
                            </div>
                            <div id="reply-form-<?php echo $message['id']; ?>" class="reply-form">
                                <form method="post" action="">
                                    <div class="form-group">
                                        <input type="hidden" name="recipient" value="<?php echo htmlspecialchars($message['sender_username']); ?>">
                                        <input type="hidden" name="subject" value="RE: <?php echo htmlspecialchars($message['subject']); ?>">
                                        <label for="reply-message-<?php echo $message['id']; ?>">Reply:</label>
                                        <textarea name="message" id="reply-message-<?php echo $message['id']; ?>" required></textarea>
                                    </div>
                                    <button type="submit" name="send_message" class="btn">Send Reply</button>
                                    <button type="button" class="btn" onclick="hideReplyForm(<?php echo $message['id']; ?>)">Cancel</button>
                                </form>
                            </div>
                        </li>
                    <?php
                        }
                    }
                }
                
                if (!$hasInboxMessages) {
                    echo '<div class="empty-state">
                            <p>Your inbox is empty.</p>
                          </div>';
                }
                ?>
            </ul>
        </div>
        
        <!-- Sent Tab -->
        <div id="sent" class="tab-content">
            <h2>Sent Messages</h2>
            
            <ul class="messages-list">
                <?php
                $hasSentMessages = false;
                // Reset message result pointer
                if ($messagesResult) {
                    $messagesResult->data_seek(0);
                    
                    while ($message = $messagesResult->fetch_assoc()) {
                        if ($message['sender_id'] == $user_id) {
                            $hasSentMessages = true;
                    ?>
                        <li class="message-item">
                            <div class="message-header">
                                <h3><?php echo htmlspecialchars($message['subject']); ?></h3>
                                <div class="message-meta">
                                    To: <?php echo htmlspecialchars($message['recipient_username']); ?> | 
                                    <?php echo date('M d, Y H:i', strtotime($message['sent_date'])); ?>
                                </div>
                            </div>
                            <div class="message-body">
                                <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                            </div>
                            <div class="message-footer">
                                <a href="?delete=<?php echo $message['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this message?');">Delete</a>
                            </div>
                        </li>
                    <?php
                        }
                    }
                }
                
                if (!$hasSentMessages) {
                    echo '<div class="empty-state">
                            <p>You haven\'t sent any messages yet.</p>
                          </div>';
                }
                ?>
            </ul>
        </div>
        
        <!-- User Directory Tab -->
        <div id="directory" class="tab-content">
            <h2>User Directory</h2>
            
            <div class="contact-info">
                <h3>All Users</h3>
                <?php
                $hasUsers = false;
                // Reset users result pointer
                if ($usersResult) {
                    $usersResult->data_seek(0);
                    
                    while ($user = $usersResult->fetch_assoc()) {
                        $hasUsers = true;
                    ?>
                        <div class="contact-item">
                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                            <button class="btn" onclick="prepareMessage('<?php echo htmlspecialchars($user['username']); ?>')">Send Message</button>
                        </div>
                    <?php
                    }
                }
                
                if (!$hasUsers) {
                    echo '<p>No other users found.</p>';
                }
                ?>
            </div>
        </div>
    </div>

    <script>
        function openTab(evt, tabName) {
            // Hide all tab content
            var tabcontent = document.getElementsByClassName("tab-content");
            for (var i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            
            // Remove active class from all tabs
            var tabs = document.getElementsByClassName("tab");
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove("active");
            }
            
            // Show the selected tab content and mark the button as active
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
        
        function showReplyForm(messageId) {
            var replyForm = document.getElementById('reply-form-' + messageId);
            if (replyForm) {
                replyForm.style.display = 'block';
            }
        }
        
        function hideReplyForm(messageId) {
            var replyForm = document.getElementById('reply-form-' + messageId);
            if (replyForm) {
                replyForm.style.display = 'none';
            }
        }
        
        function prepareMessage(recipient) {
            // Switch to compose tab
            var composeTab = document.getElementById('compose');
            var composeTabButton = document.querySelector('.tab:nth-child(1)');
            
            // Manually set active classes
            var tabcontent = document.getElementsByClassName("tab-content");
            for (var i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            
            var tabs = document.getElementsByClassName("tab");
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove("active");
            }
            
            composeTab.classList.add("active");
            composeTabButton.classList.add("active");
            
            // Fill recipient field
            var recipientSelect = document.getElementById('recipient');
            if (recipientSelect) {
                // Find the option with matching value
                for (var i = 0; i < recipientSelect.options.length; i++) {
                    if (recipientSelect.options[i].value === recipient) {
                        recipientSelect.selectedIndex = i;
                        break;
                    }
                }
                
                // Focus on subject field
                var subjectField = document.getElementById('subject');
                if (subjectField) {
                    subjectField.focus();
                }
            }
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>