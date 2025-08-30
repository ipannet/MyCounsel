<?php
session_start();
include 'db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin-login.php");
    exit();
}

$admin_username = $_SESSION['admin_username'];
$admin_id = $_SESSION['admin_id'];

// Get admin profile picture if any
$profile_picture = "";
$stmt = $conn->prepare("SELECT profile_picture FROM admins WHERE admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $profile_picture = $row['profile_picture'];
}
$stmt->close();

// Get admin user_id from users table
$admin_user_id = null;
$stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
$stmt->bind_param("s", $admin_username);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $admin_user_id = $row['user_id'];
}
$stmt->close();

// If admin doesn't exist in users table, create entry
if (!$admin_user_id) {
    $stmt = $conn->prepare("INSERT INTO users (username, email, password) SELECT username, username, 'admin_password' FROM admins WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $admin_user_id = $conn->insert_id;
    $stmt->close();
}

// Handle message reply
$messageSent = false;
$errorMessage = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_reply'])) {
    $recipient_username = $_POST['recipient'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    $originalId = $_POST['original_id'];
    
    // Get recipient user_id from username
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->bind_param("s", $recipient_username);
    $stmt->execute();
    $recipient_result = $stmt->get_result();
    $recipient_data = $recipient_result->fetch_assoc();
    
    if ($recipient_data) {
        $recipient_id = $recipient_data['user_id'];
        
        // Start a transaction
        $conn->begin_transaction();
        
        try {
            // Insert reply message into database using user IDs
            $insertSql = "INSERT INTO messages (sender_id, recipient_id, subject, message, sent_date, read_status) 
                          VALUES (?, ?, ?, ?, NOW(), 0)";
            $stmt = $conn->prepare($insertSql);
            $stmt->bind_param("iiss", $admin_user_id, $recipient_id, $subject, $message);
            $stmt->execute();
            
            // Mark the original message as read
            $updateSql = "UPDATE messages SET read_status = 1 WHERE id = ?";
            $stmt = $conn->prepare($updateSql);
            $stmt->bind_param("i", $originalId);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            $messageSent = true;
        } catch (Exception $e) {
            // Rollback in case of error
            $conn->rollback();
            $errorMessage = "Error sending message: " . $e->getMessage();
        }
    } else {
        $errorMessage = "Recipient not found.";
    }
}

// Determine which tab is active
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'all';

// Count messages in different categories using user_id
$countSql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN read_status = 0 THEN 1 ELSE 0 END) as unread,
    SUM(CASE WHEN read_status = 1 THEN 1 ELSE 0 END) as `read`
FROM messages WHERE recipient_id = ?";
$stmt = $conn->prepare($countSql);
$stmt->bind_param("i", $admin_user_id);
$stmt->execute();
$countResult = $stmt->get_result();
$counts = $countResult->fetch_assoc();

// Fetch messages from counselors using JOINs to get usernames
$messagesSql = "SELECT m.*, 
                 s.username as sender_username,
                 r.username as recipient_username,
                 COUNT(replies.id) as reply_count 
                FROM messages m 
                JOIN users s ON m.sender_id = s.user_id
                JOIN users r ON m.recipient_id = r.user_id
                LEFT JOIN messages replies ON m.sender_id = replies.recipient_id 
                    AND replies.sender_id = ? 
                    AND replies.subject LIKE CONCAT('RE: ', m.subject)
                WHERE m.recipient_id = ? ";

if ($activeTab == 'unread') {
    $messagesSql .= " AND m.read_status = 0";
} elseif ($activeTab == 'read') {
    $messagesSql .= " AND m.read_status = 1";
}

$messagesSql .= " GROUP BY m.id ORDER BY m.sent_date DESC";

$stmt = $conn->prepare($messagesSql);
$stmt->bind_param("ii", $admin_user_id, $admin_user_id);
$stmt->execute();
$messagesResult = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Messages | MyCounsel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        
        body {
            display: flex;
            min-height: 100vh;
            background-color: #f5f7fa;
        }
        
        .sidebar {
            width: 250px;
            background-color: #cd8b62;
            color: white;
            padding: 20px 0;
        }
        
        .logo {
            padding: 0 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
        }
        
        .logo img {
            width: 40px;
            height: 40px;
            margin-right: 10px;
        }
        
        .logo span {
            font-size: 18px;
            font-weight: bold;
        }
        
        .menu {
            list-style: none;
        }
        
        .menu li {
            margin-bottom: 5px;
        }
        
        .menu a {
            display: block;
            padding: 10px 20px;
            color: #ecf0f1;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        
        .menu a:hover, .menu a.active {
            color: white;
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }
        
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 20px;
        }
        
        .page-title {
            font-size: 24px;
            color: #333;
        }
        
        .admin-profile {
            display: flex;
            align-items: center;
        }
        
        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #cd8b62;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
            overflow: hidden;
        }
        
        .admin-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .admin-dropdown {
            position: relative;
            cursor: pointer;
        }
        
        .admin-dropdown:hover .dropdown-menu {
            display: block;
        }
        
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 5px;
            min-width: 150px;
            display: none;
            z-index: 1000;
        }
        
        .dropdown-menu a {
            display: block;
            padding: 10px 15px;
            color: #333;
            text-decoration: none;
        }
        
        .dropdown-menu a:hover {
            background-color: #f5f5f5;
        }

        /* Tab navigation */
        .tab-navigation {
            display: flex;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .tab-navigation a {
            padding: 15px 20px;
            color: #666;
            text-decoration: none;
            position: relative;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tab-navigation a.active {
            color: #cd8b62;
            background-color: #fff8f5;
        }
        
        .tab-navigation a.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: #cd8b62;
        }
        
        .tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 22px;
            background-color: #eee;
            color: #666;
            border-radius: 11px;
            font-size: 12px;
            padding: 0 6px;
        }
        
        .tab-navigation a.active .tab-badge {
            background-color: #cd8b62;
            color: white;
        }

        /* Message list */
        .message-list {
            margin-bottom: 20px;
        }
        
        .message-item {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 15px;
            overflow: hidden;
        }
        
        .message-header {
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .message-info {
            flex: 1;
        }
        
        .message-title {
            font-size: 16px;
            color: #333;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .message-meta {
            display: flex;
            font-size: 13px;
            color: #777;
            gap: 15px;
        }
        
        .status-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-unread {
            background-color: #fff8e1;
            color: #ffa000;
        }
        
        .status-read {
            background-color: #e8f5e9;
            color: #4caf50;
        }
        
        .status-replied {
            background-color: #e3f2fd;
            color: #2196f3;
        }
        
        .message-content {
            padding: 15px 20px;
            color: #555;
            border-bottom: 1px solid #f0f0f0;
            line-height: 1.5;
        }
        
        .message-actions {
            padding: 10px 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 15px;
            border-radius: 4px;
            border: none;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: #cd8b62;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #ba7c55;
        }
        
        .btn-secondary {
            background-color: #f0f0f0;
            color: #444;
        }
        
        .btn-secondary:hover {
            background-color: #e0e0e0;
        }
        
        .btn-danger {
            background-color: #ff4d4f;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #ff3133;
        }
        
        /* Reply form */
        .reply-form {
            padding: 15px 20px;
            background-color: #f9f9f9;
            border-top: 1px solid #f0f0f0;
            display: none;
        }
        
        .form-title {
            font-size: 15px;
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            font-size: 13px;
            color: #555;
            margin-bottom: 5px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #cd8b62;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        /* Alert messages */
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .alert-success {
            background-color: #f0f9eb;
            border: 1px solid #e1f3d8;
            color: #67c23a;
        }
        
        .alert-error {
            background-color: #fef0f0;
            border: 1px solid #fde2e2;
            color: #f56c6c;
        }
        
        .alert-close {
            background: none;
            border: none;
            color: inherit;
            font-size: 16px;
            cursor: pointer;
        }
        
        /* Empty state */
        .empty-state {
            background-color: white;
            border-radius: 8px;
            padding: 50px 20px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .empty-icon {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        .empty-title {
            font-size: 18px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .empty-message {
            color: #666;
            max-width: 500px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <img src="/picture/logo.png" alt="MyCounsel Logo">
            <span>MyCounsel Admin</span>
        </div>
        
        <ul class="menu">
            <li><a href="/php/admin-dashboard.php">Home</a></li>
            <li><a href="/php/admin-users.php">User Management</a></li>
            <li><a href="/php/admin-messages.php" class="active">Messages Management</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1 class="page-title"><i class="fas fa-envelope"></i> Counsellor Messages</h1>
            
            <div class="admin-profile">
                <div class="admin-dropdown">
                    <div class="admin-avatar">
                        <?php if ($profile_picture && file_exists('uploads/admin/' . $profile_picture)): ?>
                            <img src="uploads/admin/<?php echo htmlspecialchars($profile_picture); ?>" alt="Admin">
                        <?php else: ?>
                            <?php echo strtoupper(substr($admin_username, 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <span><?php echo htmlspecialchars($admin_username); ?></span>
                    
                    <div class="dropdown-menu">
                        <a href="/php/admin-profile.php">My Profile</a>
                        <a href="admin-logout.php">Logout</a>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($messageSent): ?>
            <div class="alert alert-success">
                <div><i class="fas fa-check-circle"></i> Your reply has been sent successfully!</div>
                <button class="alert-close" onclick="this.parentElement.style.display='none'"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>
        
        <?php if ($errorMessage): ?>
            <div class="alert alert-error">
                <div><i class="fas fa-exclamation-circle"></i> <?php echo $errorMessage; ?></div>
                <button class="alert-close" onclick="this.parentElement.style.display='none'"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>
        
        <div class="tab-navigation">
            <a href="?tab=all" class="<?php echo $activeTab == 'all' ? 'active' : ''; ?>">
                <i class="fas fa-inbox"></i> All Messages
                <span class="tab-badge"><?php echo $counts['total']; ?></span>
            </a>
            <a href="?tab=unread" class="<?php echo $activeTab == 'unread' ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i> Unread 
                <span class="tab-badge"><?php echo $counts['unread']; ?></span>
            </a>
            <a href="?tab=read" class="<?php echo $activeTab == 'read' ? 'active' : ''; ?>">
                <i class="fas fa-check-double"></i> Read
                <span class="tab-badge"><?php echo $counts['read']; ?></span>
            </a>
        </div>
        
        <div class="message-list">
            <?php if ($messagesResult->num_rows > 0): ?>
                <?php while ($message = $messagesResult->fetch_assoc()): ?>
                    <div class="message-item" id="message-<?php echo $message['id']; ?>">
                        <div class="message-header">
                            <div class="message-info">
                                <div class="message-title"><?php echo htmlspecialchars($message['subject']); ?></div>
                                <div class="message-meta">
                                    <span><i class="fas fa-user"></i> From: <?php echo htmlspecialchars($message['sender_username']); ?></span>
                                    <span><i class="far fa-clock"></i> <?php echo date('M d, Y - h:i A', strtotime($message['sent_date'])); ?></span>
                                </div>
                            </div>
                            
                            <?php if ($message['reply_count'] > 0): ?>
                                <span class="status-badge status-replied">
                                    <i class="fas fa-reply"></i> Replied
                                </span>
                            <?php elseif ($message['read_status'] == 1): ?>
                                <span class="status-badge status-read">
                                    <i class="fas fa-check-double"></i> Read
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-unread">
                                    <i class="fas fa-envelope"></i> Unread
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                        </div>
                        
                        <div class="message-actions">
                            <button class="btn btn-primary" onclick="toggleReplyForm(<?php echo $message['id']; ?>)">
                                <i class="fas fa-reply"></i> Reply
                            </button>
                            
                        </div>
                        
                        <div class="reply-form" id="reply-form-<?php echo $message['id']; ?>">
                            <div class="form-title">
                                <i class="fas fa-reply"></i> Reply to this message
                            </div>
                            <form method="POST" action="?tab=<?php echo $activeTab; ?>">
                                <input type="hidden" name="recipient" value="<?php echo htmlspecialchars($message['sender_username']); ?>">
                                <input type="hidden" name="original_id" value="<?php echo $message['id']; ?>">
                                
                                <div class="form-group">
                                    <label for="subject-<?php echo $message['id']; ?>">Subject:</label>
                                    <input type="text" class="form-control" id="subject-<?php echo $message['id']; ?>" 
                                           name="subject" value="RE: <?php echo htmlspecialchars($message['subject']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="message-<?php echo $message['id']; ?>">Message:</label>
                                    <textarea class="form-control" id="message-<?php echo $message['id']; ?>" 
                                              name="message" placeholder="Type your reply here..." required></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="button" class="btn btn-secondary" onclick="toggleReplyForm(<?php echo $message['id']; ?>)">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                    <button type="submit" name="send_reply" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Send Reply
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-inbox"></i></div>
                    <h3 class="empty-title">No messages found</h3>
                    <p class="empty-message">
                        <?php 
                        if ($activeTab == 'unread') {
                            echo "You don't have any unread messages. All messages have been read.";
                        } elseif ($activeTab == 'read') {
                            echo "You don't have any read messages in this category.";
                        } else {
                            echo "Your inbox is empty. When counsellors send you messages, they will appear here.";
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Toggle reply form visibility
        function toggleReplyForm(messageId) {
            const replyForm = document.getElementById('reply-form-' + messageId);
            
            if (replyForm.style.display === 'block') {
                replyForm.style.display = 'none';
            } else {
                // Hide all other open reply forms
                const allForms = document.querySelectorAll('.reply-form');
                allForms.forEach(form => {
                    form.style.display = 'none';
                });
                
                // Show this form
                replyForm.style.display = 'block';
                
                // Focus on the message textarea
                document.getElementById('message-' + messageId).focus();
            }
        }
        
        // Handle dropdown menu
        document.addEventListener('DOMContentLoaded', function() {
            const adminDropdown = document.querySelector('.admin-dropdown');
            
            adminDropdown.addEventListener('click', function(e) {
                const dropdownMenu = this.querySelector('.dropdown-menu');
                dropdownMenu.style.display = dropdownMenu.style.display === 'block' ? 'none' : 'block';
                e.stopPropagation();
            });
            
            document.addEventListener('click', function() {
                const dropdownMenu = document.querySelector('.dropdown-menu');
                if (dropdownMenu) {
                    dropdownMenu.style.display = 'none';
                }
            });
            
            // Hide alerts after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    alert.style.display = 'none';
                });
            }, 5000);
        });
    </script>
</body>
</html>

<?php
// Handle mark as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $messageId = $_GET['mark_read'];
    $updateSql = "UPDATE messages SET read_status = 1 WHERE id = ? AND recipient_id = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("ii", $messageId, $admin_user_id);
    $stmt->execute();
    
    // Redirect to remove the GET parameter
    header("Location: admin-messages.php" . (isset($_GET['tab']) ? "?tab=" . $_GET['tab'] : ""));
    exit();
}

// Handle delete message 
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $messageId = $_GET['delete'];
    $deleteSql = "DELETE FROM messages WHERE id = ? AND recipient_id = ?";
    $stmt = $conn->prepare($deleteSql);
    $stmt->bind_param("ii", $messageId, $admin_user_id);
    $stmt->execute();
    
    // Redirect to remove the GET parameter
    header("Location: admin-messages.php" . (isset($_GET['tab']) ? "?tab=" . $_GET['tab'] : ""));
    exit();
}

$conn->close();
?>