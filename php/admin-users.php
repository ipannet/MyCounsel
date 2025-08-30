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

// Initialize variables
$success_message = "";
$error_message = "";
$edit_user_id = "";
$edit_username = "";
$edit_email = "";
$edit_password = "";
$view_user_id = "";
$view_username = "";

// Get list of admin usernames to exclude
function getAdminUsernames($conn) {
    $adminUsernames = [];
    $stmt = $conn->prepare("SELECT username FROM admins");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $adminUsernames[] = $row['username'];
    }
    $stmt->close();
    return $adminUsernames;
}

$adminUsernames = getAdminUsernames($conn);

// View user details
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $view_user_id = $_GET['view'];
    
    // Check if user is not an admin
    $stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ? AND username NOT IN (SELECT username FROM admins)");
    $stmt->bind_param("i", $view_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $view_username = $result->fetch_assoc()['username'];
    } else {
        $error_message = "User not found or access denied!";
        $view_user_id = "";
    }
}

// Process user deletion
if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    
    // Check if user exists and is not an admin
    $stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ? AND username NOT IN (SELECT username FROM admins)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        
        // Delete user's profile picture if exists
        $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc() && $row['profile_picture']) {
            $pic_path = 'uploads/profiles/' . $row['profile_picture'];
            if (file_exists($pic_path)) {
                unlink($pic_path);
            }
        }
        
        // Delete user from database
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND username NOT IN (SELECT username FROM admins)");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success_message = "User deleted successfully!";
        } else {
            $error_message = "Error deleting user or user is protected!";
        }
    } else {
        $error_message = "User not found or access denied!";
    }
}

// Process user edit form submission
if (isset($_POST['update_user'])) {
    $user_id = $_POST['user_id'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Check if the user being edited is not an admin
    $stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ? AND username NOT IN (SELECT username FROM admins)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        
        // Check if new username conflicts with existing users (excluding admins and current user)
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $stmt->bind_param("si", $username, $user_id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error_message = "Username already exists!";
        } else {
            $stmt->close();
            
            // Check if new username conflicts with admin usernames
            if (in_array($username, $adminUsernames)) {
                $error_message = "Username conflicts with admin account!";
            } else {
                // Check if email already exists for other users
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                $stmt->bind_param("si", $email, $user_id);
                $stmt->execute();
                $stmt->store_result();
                
                if ($stmt->num_rows > 0) {
                    $error_message = "Email already exists!";
                } else {
                    $stmt->close();
                    
                    // Update user information
                    if (!empty($password)) {
                        // With password change
                        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password = ? WHERE user_id = ? AND username NOT IN (SELECT username FROM admins)");
                        $stmt->bind_param("sssi", $username, $email, $password, $user_id);
                    } else {
                        // Without password change
                        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE user_id = ? AND username NOT IN (SELECT username FROM admins)");
                        $stmt->bind_param("ssi", $username, $email, $user_id);
                    }
                    
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $success_message = "User updated successfully!";
                    } else {
                        $error_message = "Error updating user or user is protected!";
                    }
                }
            }
        }
    } else {
        $error_message = "User not found or access denied!";
    }
}

// Get user info for edit form
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_user_id = $_GET['edit'];
    
    // Only allow editing of non-admin users
    $stmt = $conn->prepare("SELECT user_id, username, email, password FROM users WHERE user_id = ? AND username NOT IN (SELECT username FROM admins)");
    $stmt->bind_param("i", $edit_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $edit_user_id = $user['user_id'];
        $edit_username = $user['username'];
        $edit_email = $user['email'];
        $edit_password = $user['password'];
    } else {
        $error_message = "User not found or access denied!";
        $edit_user_id = "";
    }
}

// Handle search query - exclude admin users
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Create a placeholder string for the IN clause based on number of admin usernames
$adminPlaceholders = '';
if (!empty($adminUsernames)) {
    $adminPlaceholders = str_repeat('?,', count($adminUsernames) - 1) . '?';
    $users_sql = "SELECT * FROM users WHERE username NOT IN ($adminPlaceholders)";
} else {
    $users_sql = "SELECT * FROM users WHERE 1=1";
}

if (!empty($search_query)) {
    $users_sql .= " AND (username LIKE ? OR email LIKE ?)";
}

// Prepare statement for counting total users
if (!empty($adminUsernames)) {
    $stmt = $conn->prepare($users_sql);
    if (!empty($search_query)) {
        $search_param = "%$search_query%";
        $params = array_merge($adminUsernames, [$search_param, $search_param]);
        $types = str_repeat('s', count($adminUsernames)) . 'ss';
        $stmt->bind_param($types, ...$params);
    } else {
        $types = str_repeat('s', count($adminUsernames));
        $stmt->bind_param($types, ...$adminUsernames);
    }
} else {
    if (!empty($search_query)) {
        $search_param = "%$search_query%";
        $stmt = $conn->prepare($users_sql);
        $stmt->bind_param("ss", $search_param, $search_param);
    } else {
        $stmt = $conn->prepare($users_sql);
    }
}

$stmt->execute();
$users_result = $stmt->get_result();
$total_users = $users_result->num_rows;

// Pagination
$items_per_page = 10;
$total_pages = ceil($total_users / $items_per_page);
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

// Adjust SQL query for pagination
$users_sql .= " ORDER BY created_at DESC LIMIT ?, ?";

// Prepare paginated query
if (!empty($adminUsernames)) {
    $stmt = $conn->prepare($users_sql);
    if (!empty($search_query)) {
        $search_param = "%$search_query%";
        $params = array_merge($adminUsernames, [$search_param, $search_param, $offset, $items_per_page]);
        $types = str_repeat('s', count($adminUsernames)) . 'ssii';
        $stmt->bind_param($types, ...$params);
    } else {
        $params = array_merge($adminUsernames, [$offset, $items_per_page]);
        $types = str_repeat('s', count($adminUsernames)) . 'ii';
        $stmt->bind_param($types, ...$params);
    }
} else {
    if (!empty($search_query)) {
        $search_param = "%$search_query%";
        $stmt = $conn->prepare($users_sql);
        $stmt->bind_param("ssii", $search_param, $search_param, $offset, $items_per_page);
    } else {
        $stmt = $conn->prepare($users_sql);
        $stmt->bind_param("ii", $offset, $items_per_page);
    }
}

$stmt->execute();
$users_result = $stmt->get_result();

// Fetch user certificates, todo list, and events if viewing user details
$certificates = [];
$todos = [];
$events = [];

if ($view_user_id) {
    // Fetch certificates using user_id
    $stmt = $conn->prepare("SELECT * FROM certificates WHERE user_id = ?");
    $stmt->bind_param("i", $view_user_id);
    $stmt->execute();
    $certificates_result = $stmt->get_result();
    if ($certificates_result) {
        while ($row = $certificates_result->fetch_assoc()) {
            $certificates[] = $row;
        }
    }
    
    // Fetch todo list using user_id
    $stmt = $conn->prepare("SELECT * FROM todo_list WHERE user_id = ?");
    $stmt->bind_param("i", $view_user_id);
    $stmt->execute();
    $todos_result = $stmt->get_result();
    if ($todos_result) {
        while ($row = $todos_result->fetch_assoc()) {
            $todos[] = $row;
        }
    }
    
    // Fetch events using user_id
    $stmt = $conn->prepare("SELECT * FROM events WHERE user_id = ? ORDER BY event_date ASC");
    $stmt->bind_param("i", $view_user_id);
    $stmt->execute();
    $events_result = $stmt->get_result();
    if ($events_result) {
        while ($row = $events_result->fetch_assoc()) {
            $events[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | MyCounsel Admin</title>
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
        
        .manage-section {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 18px;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .user-table th, .user-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .user-table th {
            color: #666;
            font-weight: normal;
            background-color: #f9f9f9;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 6px 12px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background-color: #4a6fa5;
            color: white;
        }
        
        .btn-secondary {
            background-color: #cd8b62;
            color: white;
        }
        
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
        
        .btn-info {
            background-color: #3498db;
            color: white;
        }
        
        .edit-form {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .form-title {
            font-size: 16px;
            margin-bottom: 15px;
            color: #333;
        }
        
        .form-row {
            margin-bottom: 15px;
        }
        
        .form-row label {
            display: block;
            margin-bottom: 5px;
            color: #666;
        }
        
        .form-row input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .save-btn, .cancel-btn {
            padding: 8px 16px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }
        
        .save-btn {
            background-color: #cd8b62;
            color: white;
        }
        
        .cancel-btn {
            background-color: #6c757d;
            color: white;
        }
        
        .search-bar {
            margin-bottom: 20px;
        }
        
        .search-bar form {
            display: flex;
            gap: 10px;
        }
        
        .search-bar input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .search-bar button {
            padding: 8px 16px;
            background-color: #cd8b62;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }
        
        .pagination a {
            padding: 8px 12px;
            background-color: white;
            border: 1px solid #ddd;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
        }
        
        .pagination a.active {
            background-color: #cd8b62;
            color: white;
            border-color: #cd8b62;
        }
        
        .pagination a:hover:not(.active) {
            background-color: #f5f5f5;
        }
        
        .message {
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 30px;
            font-size: 12px;
        }
        
        .status-active {
            background-color: #e8f5e9;
            color: #4caf50;
        }
        
        .status-inactive {
            background-color: #ffebee;
            color: #f44336;
        }
        
        .password-container {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #f0f0f0;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            overflow: hidden;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-details {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            padding: 20px;
        }
        
        .user-details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .user-details-header h2 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }
        
        .tab.active {
            color: #cd8b62;
            border-bottom-color: #cd8b62;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .certificate-item, .todo-item, .event-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .certificate-item:last-child, .todo-item:last-child, .event-item:last-child {
            border-bottom: none;
        }
        
        .certificate-name, .todo-name, .event-name {
            flex: 1;
        }
        
        .event-date {
            color: #666;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .badge-pending {
            background-color: #fff3e0;
            color: #ffa000;
        }
        
        .badge-completed {
            background-color: #e8f5e9;
            color: #2ecc71;
        }
        
        .upcoming-event {
            background-color: #f0f8ff;
            border-left: 3px solid #4a6fa5;
        }
        
        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            color: #333;
            text-decoration: none;
        }
        
        .back-btn:hover {
            text-decoration: underline;
        }
        
        .empty-message {
            color: #666;
            font-style: italic;
            text-align: center;
            padding: 20px;
        }

        .info-banner {
            background-color: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 4px;
            padding: 12px;
            margin-bottom: 20px;
            color: #1976d2;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <img src="/picture/logo.png" alt="MyCounsel Logo">
            <span>MyCounsel</span>
        </div>
        
        <ul class="menu">
            <li><a href="/php/admin-dashboard.php">Home</a></li>
            <li><a href="/php/admin-users.php" class="active">User Management</a></li>
            <li><a href="/php/admin-messages.php">Messages Management</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1 class="page-title">User Management</h1>
            
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

        <div class="info-banner">
            ℹ️ <strong>Note:</strong> This page shows only regular users (counsellors). Admin accounts are managed separately and not displayed here for security purposes.
        </div>
        
        <?php if ($success_message): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if ($view_user_id): ?>
            <!-- User Details View -->
            <a href="admin-users.php" class="back-btn">&laquo; Back to Users</a>
            
            <div class="user-details">
                <div class="user-details-header">
                    <h2>
                        <?php 
                        $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
                        $stmt->bind_param("i", $view_user_id);
                        $stmt->execute();
                        $user_pic_result = $stmt->get_result();
                        $user_pic = $user_pic_result->fetch_assoc()['profile_picture'] ?? '';
                        ?>
                        <div class="user-avatar">
                            <?php if ($user_pic && file_exists('uploads/profiles/' . $user_pic)): ?>
                                <img src="uploads/profiles/<?php echo htmlspecialchars($user_pic); ?>" alt="User">
                            <?php else: ?>
                                <?php echo strtoupper(substr($view_username, 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        User Details: <?php echo htmlspecialchars($view_username); ?>
                    </h2>
                    
                    <div class="action-buttons">
                        <a href="admin-users.php?edit=<?php echo $view_user_id; ?>" class="btn btn-primary">Edit User</a>
                        <form action="admin-users.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');" style="display: inline;">
                            <input type="hidden" name="user_id" value="<?php echo $view_user_id; ?>">
                            <button type="submit" name="delete_user" class="btn btn-danger">Delete User</button>
                        </form>
                    </div>
                </div>
                
                <div class="tabs">
                    <div class="tab active" onclick="switchTab('todos')">To-Do List</div>
                    <div class="tab " onclick="switchTab('events')">Upcoming Events</div>
                </div>
                
                <div id="todos-tab" class="tab-content active">
                    <h3>User To-Do List</h3>
                    <?php if (count($todos) > 0): ?>
                        <?php foreach ($todos as $todo): ?>
                            <div class="todo-item">
                                <div class="todo-name">
                                    <?php echo htmlspecialchars($todo['task']); ?>
                                </div>
                                <div>
                                    <span class="status-badge badge-<?php echo strtolower($todo['status']); ?>">
                                        <?php echo ucfirst($todo['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-message">No to-do items found for this user.</div>
                    <?php endif; ?>
                </div>
                
                <div id="events-tab" class="tab-content">
                    <h3>User Events</h3>
                    <?php if (count($events) > 0): ?>
                        <?php foreach ($events as $event): 
                            $eventDate = strtotime($event['event_date']);
                            $today = strtotime(date('Y-m-d'));
                            $isUpcoming = $eventDate >= $today;
                            $eventClass = $isUpcoming ? 'upcoming-event' : '';
                        ?>
                            <div class="event-item <?php echo $eventClass; ?>">
                                <div class="event-name">
                                    <?php echo htmlspecialchars($event['event_name']); ?>
                                </div>
                                <div class="event-date">
                                    <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-message">No events found for this user.</div>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif ($edit_user_id): ?>
            <!-- Edit User Form -->
            <div class="edit-form">
                <h2 class="form-title">Edit User</h2>
                
                <form action="admin-users.php" method="POST">
                    <input type="hidden" name="user_id" value="<?php echo $edit_user_id; ?>">
                    
                    <div class="form-row">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($edit_username); ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($edit_email); ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <label for="password">New Password (leave blank to keep current)</label>
                        <div class="password-container">
                            <input type="password" id="password" name="password">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_user" class="save-btn">Save Changes</button>
                        <a href="admin-users.php" class="cancel-btn" style="text-decoration: none; text-align: center;">Cancel</a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- User List View -->
            <div class="search-bar">
                <form action="admin-users.php" method="GET">
                    <input type="text" name="search" placeholder="Search by username or email..." value="<?php echo htmlspecialchars($search_query); ?>">
                    <button type="submit">Search</button>
                    <?php if (!empty($search_query)): ?>
                        <a href="admin-users.php" style="margin-left: 10px; color: #666; text-decoration: none;">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="manage-section">
                <h2 class="section-title">Regular Users (<?php echo $total_users; ?>)</h2>
                
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Joined Date</th>
                            <th>Status</th>
                            <th>Activities</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users_result->num_rows > 0): ?>
                            <?php while ($user = $users_result->fetch_assoc()): 
                                // Count user's activities using user_id instead of username
                                $user_id_for_count = $user['user_id'];
                                
                                // Count certificates - using user_id
                                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM certificates WHERE user_id = ?");
                                $stmt->bind_param("i", $user_id_for_count);
                                $stmt->execute();
                                $cert_result = $stmt->get_result();
                                $cert_count = $cert_result ? $cert_result->fetch_assoc()['count'] : 0;
                                
                                // Count todos - using user_id
                                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM todo_list WHERE user_id = ?");
                                $stmt->bind_param("i", $user_id_for_count);
                                $stmt->execute();
                                $todo_result = $stmt->get_result();
                                $todo_count = $todo_result ? $todo_result->fetch_assoc()['count'] : 0;
                                
                                // Count events - using user_id
                                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM events WHERE user_id = ?");
                                $stmt->bind_param("i", $user_id_for_count);
                                $stmt->execute();
                                $event_result = $stmt->get_result();
                                $event_count = $event_result ? $event_result->fetch_assoc()['count'] : 0;
                                
                                // Count upcoming events - using user_id
                                $today = date('Y-m-d');
                                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM events WHERE user_id = ? AND event_date >= ?");
                                $stmt->bind_param("is", $user_id_for_count, $today);
                                $stmt->execute();
                                $upcoming_result = $stmt->get_result();
                                $upcoming_count = $upcoming_result ? $upcoming_result->fetch_assoc()['count'] : 0;
                            ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div class="user-avatar">
                                                <?php if (isset($user['profile_picture']) && !empty($user['profile_picture']) && file_exists('uploads/profiles/' . $user['profile_picture'])): ?>
                                                    <img src="uploads/profiles/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="User">
                                                <?php else: ?>
                                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo date("M d, Y", strtotime($user['created_at'])); ?></td>
                                    <td><span class="status-badge status-active">Active</span></td>
                                    <td>
                                        <div style="display: flex; gap: 10px;">
                                            <span title="To-Do Items" style="display: flex; align-items: center; gap: 5px;"> 
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M9 11l3 3L22 4"></path>
                                                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                                                </svg>
                                                <?php echo $todo_count; ?>
                                            </span>
                                            <span title="Events (Upcoming)" style="display: flex; align-items: center; gap: 5px;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                                </svg>
                                                <?php echo $event_count; ?> (<?php echo $upcoming_count; ?>)
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="admin-users.php?view=<?php echo $user['user_id']; ?>" class="btn btn-secondary">View Details</a>
                                            <a href="admin-users.php?edit=<?php echo $user['user_id']; ?>" class="btn btn-primary">Edit</a>
                                            <form action="admin-users.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-danger">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No users found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                            <a href="admin-users.php?page=<?php echo $current_page - 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>">&laquo; Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $current_page - 2); $i <= min($current_page + 2, $total_pages); $i++): ?>
                            <a href="admin-users.php?page=<?php echo $i; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" class="<?php echo $i == $current_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="admin-users.php?page=<?php echo $current_page + 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>">Next &raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>    
    <script>
        // Toggle password visibility
        function togglePassword(id) {
            const input = document.getElementById(id);
            input.type = input.type === 'password' ? 'text' : 'password';
        }
        
        // Toggle dropdown menu
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
        });
        
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
            });
    
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
            });
    
            // Activate selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
    
            // Activate selected tab button
            document.querySelectorAll('.tab').forEach(tab => {
                if (tab.getAttribute('onclick').includes("switchTab('" + tabName + "')")) {
                    tab.classList.add('active');
                }
            });
        }
    </script>
</body>
</html>