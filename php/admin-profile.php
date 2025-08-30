<?php
session_start();
include 'db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin-login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin_username = $_SESSION['admin_username'];
$admin_email = $_SESSION['admin_email'];

// Get admin data
$stmt = $conn->prepare("SELECT username, email, password, profile_picture FROM admins WHERE admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Admin not found - should not happen but just in case
    session_destroy();
    header("Location: admin-login.php");
    exit();
}

$admin = $result->fetch_assoc();
$username = $admin['username'];
$email = $admin['email'];
$password = $admin['password']; // Get the actual password
$profile_picture = $admin['profile_picture'];

// Handle profile picture upload
$upload_error = "";
$success_message = "";
$error_message = "";

// Create uploads directory if it doesn't exist
$upload_base_dir = 'uploads';
$upload_admin_dir = $upload_base_dir . '/admin';

// Check and create base uploads directory
if (!file_exists($upload_base_dir)) {
    if (!mkdir($upload_base_dir, 0777, true)) {
        $upload_error = "Failed to create uploads directory";
    }
}

// Check and create admin uploads directory
if (!file_exists($upload_admin_dir) && empty($upload_error)) {
    if (!mkdir($upload_admin_dir, 0777, true)) {
        $upload_error = "Failed to create admin uploads directory";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle profile picture removal
    if (isset($_POST['remove_profile_picture'])) {
        // Check if the admin has a profile picture
        if ($profile_picture && file_exists($upload_admin_dir . '/' . $profile_picture)) {
            // Delete the file
            if (unlink($upload_admin_dir . '/' . $profile_picture)) {
                // Update the database to remove the profile picture reference
                $stmt = $conn->prepare("UPDATE admins SET profile_picture = NULL WHERE admin_id = ?");
                $stmt->bind_param("i", $admin_id);
                
                if ($stmt->execute()) {
                    $profile_picture = null;
                    $success_message = "Profile picture removed successfully!";
                } else {
                    $error_message = "Error updating database after removing profile picture!";
                }
            } else {
                $error_message = "Error removing profile picture file!";
            }
        } else {
            $error_message = "No profile picture to remove!";
        }
    }

    // Handle username update
    if (isset($_POST['update_username'])) {
        $new_username = trim($_POST['username']);
        
        // Check if username already exists
        $stmt = $conn->prepare("SELECT admin_id FROM admins WHERE username = ? AND admin_id != ?");
        $stmt->bind_param("si", $new_username, $admin_id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $username_error = "Username already exists!";
        } else {
            $stmt->close();
            
            // Update username
            $stmt = $conn->prepare("UPDATE admins SET username = ? WHERE admin_id = ?");
            $stmt->bind_param("si", $new_username, $admin_id);
            
            if ($stmt->execute()) {
                $username = $new_username;
                $_SESSION['admin_username'] = $new_username; // Update session
                $success_message = "Username updated successfully!";
            } else {
                $error_message = "Error updating username!";
            }
        }
    }
    
    // Handle email update
    if (isset($_POST['update_email'])) {
        $new_email = trim($_POST['email']);
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT admin_id FROM admins WHERE email = ? AND admin_id != ?");
        $stmt->bind_param("si", $new_email, $admin_id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $email_error = "Email already exists!";
        } else {
            $stmt->close();
            
            // Update email
            $stmt = $conn->prepare("UPDATE admins SET email = ? WHERE admin_id = ?");
            $stmt->bind_param("si", $new_email, $admin_id);
            
            if ($stmt->execute()) {
                $email = $new_email;
                $_SESSION['admin_email'] = $new_email; // Update session
                $success_message = "Email updated successfully!";
            } else {
                $error_message = "Error updating email!";
            }
        }
    }
    
    // Handle password update
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password - direct comparison for plaintext passwords
        if ($current_password !== $password) {
            $password_error = "Current password is incorrect!";
        } else if ($new_password !== $confirm_password) {
            $password_error = "New passwords do not match!";
        } else {
            // Update password
            $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
            $stmt->bind_param("si", $new_password, $admin_id);
            
            if ($stmt->execute()) {
                $password = $new_password; // Update variable
                $success_message = "Password updated successfully!";
            } else {
                $error_message = "Error updating password!";
            }
        }
    }
    
    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 1 * 1024 * 1024; // 1MB
        
        if (!in_array($_FILES['profile_picture']['type'], $allowed_types)) {
            $upload_error = "Only JPEG and PNG images are allowed!";
        } else if ($_FILES['profile_picture']['size'] > $max_size) {
            $upload_error = "File size must be less than 1MB!";
        } else {
            // Generate unique filename
            $filename = $admin_id . '_' . time() . '_' . basename($_FILES['profile_picture']['name']);
            $target_file = $upload_admin_dir . '/' . $filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                // Delete old profile picture if it exists
                if ($profile_picture && file_exists($upload_admin_dir . '/' . $profile_picture)) {
                    unlink($upload_admin_dir . '/' . $profile_picture);
                }
                
                // Update profile picture in database
                $stmt = $conn->prepare("UPDATE admins SET profile_picture = ? WHERE admin_id = ?");
                $stmt->bind_param("si", $filename, $admin_id);
                
                if ($stmt->execute()) {
                    $profile_picture = $filename;
                    $success_message = "Profile picture updated successfully!";
                } else {
                    $upload_error = "Error updating profile picture in database!";
                }
            } else {
                $upload_error = "Error uploading file. Check directory permissions.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile | MyCounsel Admin</title>
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
        
        .profile-container {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }
        
        .profile-sidebar {
            flex: 0 0 250px;
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin: 0 auto 20px;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        
        .profile-picture img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-picture .upload-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: rgba(0,0,0,0.5);
            color: white;
            padding: 5px;
            font-size: 12px;
            cursor: pointer;
            display: none;
        }
        
        .profile-picture:hover .upload-overlay {
            display: block;
        }

        .profile-picture-actions {
            display: flex;
            justify-content: center;
            margin-top: 10px;
            gap: 10px;
        }

        .remove-picture-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
        }

        .remove-picture-btn:hover {
            background-color: #c82333;
        }
        
        .profile-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .profile-email {
            color: #666;
            margin-bottom: 20px;
        }
        
        .profile-main {
            flex: 1;
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .section-title {
            font-size: 18px;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #666;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .save-btn {
            background-color: #cd8b62;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .save-btn:hover {
            background-color: #b67a55;
        }
        
        .message {
            padding: 10px;
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
            <li><a href="/php/admin-users.php">User Management</a></li>
            <li><a href="/php/admin-messages.php">Messages Management</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1 class="page-title">Admin Profile</h1>
            
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
                        <a href="/php/admin-profile.php" class="active">My Profile</a>
                        <a href="admin-logout.php">Logout</a>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($success_message): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="profile-container">
            <div class="profile-sidebar">
                <form action="admin-profile.php" method="POST" enctype="multipart/form-data" id="profile-pic-form">
                    <div class="profile-picture">
                        <?php if ($profile_picture && file_exists('uploads/admin/' . $profile_picture)): ?>
                            <img src="uploads/admin/<?php echo htmlspecialchars($profile_picture); ?>" alt="Admin" id="preview-image">
                        <?php else: ?>
                            <div style="font-size: 48px; color: #999;">
                                <?php echo strtoupper(substr($admin_username, 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div class="upload-overlay" onclick="document.getElementById('profile-picture-input').click()">
                            Change Picture
                        </div>
                    </div>
                    
                    <input type="file" name="profile_picture" id="profile-picture-input" style="display: none;" onchange="previewImage(this); document.getElementById('profile-pic-form').submit();">
                    
                    <?php if ($upload_error): ?>
                        <div class="message error" style="margin-top: 10px; font-size: 12px;">
                            <?php echo $upload_error; ?>
                        </div>
                    <?php endif; ?>
                </form>

                <?php if ($profile_picture): ?>
                <div class="profile-picture-actions">
                    <form action="admin-profile.php" method="POST">
                        <input type="hidden" name="remove_profile_picture" value="1">
                        <button type="submit" class="remove-picture-btn">Remove Picture</button>
                    </form>
                </div>
                <?php endif; ?>
                
                <div class="profile-name"><?php echo htmlspecialchars($admin_username); ?></div>
                <div class="profile-email"><?php echo htmlspecialchars($admin_email); ?></div>
                
                <div style="margin-top: 20px; text-align: left;">
                    <div style="margin-bottom: 10px; font-weight: bold;">Admin Details</div>
                    <div style="margin-bottom: 5px; color: #666;">Role: Administrator</div>
                    <div style="margin-bottom: 5px; color: #666;">Status: Active</div>
                </div>
            </div>
            
            <div class="profile-main">
                <h2 class="section-title">Edit Profile</h2>
                
                <form action="admin-profile.php" method="POST">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>">
                        <?php if (isset($username_error)): ?>
                            <div class="message error" style="margin-top: 5px; font-size: 12px;">
                                <?php echo $username_error; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <input type="hidden" name="update_username" value="1">
                    <button type="submit" class="save-btn">Update Username</button>
                </form>
                
                <h2 class="section-title" style="margin-top: 30px;">Change Email</h2>
                
                <form action="admin-profile.php" method="POST">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        <?php if (isset($email_error)): ?>
                            <div class="message error" style="margin-top: 5px; font-size: 12px;">
                                <?php echo $email_error; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <input type="hidden" name="update_email" value="1">
                    <button type="submit" class="save-btn">Update Email</button>
                </form>
                
                <h2 class="section-title" style="margin-top: 30px;">Change Password</h2>
                
                <form action="admin-profile.php" method="POST">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <div class="password-container">
                            <input type="password" id="current_password" name="current_password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('current_password')">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="password-container">
                            <input type="password" id="new_password" name="new_password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('new_password')">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="password-container">
                            <input type="password" id="confirm_password" name="confirm_password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <?php if (isset($password_error)): ?>
                        <div class="message error">
                            <?php echo $password_error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <input type="hidden" name="update_password" value="1">
                    <button type="submit" class="save-btn">Update Password</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
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
        
        // Preview image before upload
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const previewImg = document.getElementById('preview-image');
                    if (previewImg) {
                        previewImg.src = e.target.result;
                    } else {
                        // If preview image element doesn't exist yet, create it
                        const profilePicture = document.querySelector('.profile-picture');
                        profilePicture.innerHTML = '<img src="' + e.target.result + '" alt="Admin" id="preview-image"><div class="upload-overlay" onclick="document.getElementById(\'profile-picture-input\').click()">Change Picture</div>';
                    }
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Toggle password visibility
        function togglePassword(id) {
            const input = document.getElementById(id);
            input.type = input.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>