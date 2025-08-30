<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Initialize error and success variables
$username_error = "";
$email_error = "";
$password_error = "";
$upload_error = "";
$success_message = "";
$error_message = "";

// Get user data from database
$stmt = $conn->prepare("SELECT username, email, email_password, password, profile_picture FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // User not found
    session_destroy();
    header("Location: signin.php");
    exit();
}

$user = $result->fetch_assoc();
$username = $user['username'];
$email = $user['email'];
$email_password = $user['email_password'];
$password = $user['password'];

// Handle profile picture
$profile_picture = "default.jpg"; // Default image
if ($user['profile_picture']) {
    $profile_picture = $user['profile_picture'];
}

$stmt->close();

// Create upload directories if they don't exist
$upload_base_dir = 'uploads';
$upload_profiles_dir = $upload_base_dir . '/profiles';

// Check and create base uploads directory
if (!file_exists($upload_base_dir)) {
    if (!mkdir($upload_base_dir, 0777, true)) {
        $upload_error = "Failed to create uploads directory";
    }
}

// Check and create profiles directory
if (!file_exists($upload_profiles_dir) && empty($upload_error)) {
    if (!mkdir($upload_profiles_dir, 0777, true)) {
        $upload_error = "Failed to create profiles directory";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle username update
    if (isset($_POST['update_username'])) {
        $new_username = trim($_POST['username']);
        
        // Validate username
        if (empty($new_username)) {
            $username_error = "Username cannot be empty!";
        } else {
            // Check if username already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
            $stmt->bind_param("si", $new_username, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $username_error = "Username already exists!";
            } else {
                // Update username with proper parameter binding
                $stmt = $conn->prepare("UPDATE users SET username = ? WHERE user_id = ?");
                $stmt->bind_param("si", $new_username, $user_id);
                
                if ($stmt->execute()) {
                    $username = $new_username;
                    // Update session variable
                    $_SESSION['username'] = $new_username;
                    $success_message = "Username updated successfully!";
                } else {
                    $error_message = "Error updating username: " . $conn->error;
                }
            }
            $stmt->close();
        }
    }
    
    // Handle email update
    if (isset($_POST['update_email'])) {
        $new_email = trim($_POST['email']);
        
        // Validate email
        if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $email_error = "Please enter a valid email address!";
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->bind_param("si", $new_email, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $email_error = "Email already exists!";
            } else {
                // Update email with proper parameter binding
                $stmt = $conn->prepare("UPDATE users SET email = ? WHERE user_id = ?");
                $stmt->bind_param("si", $new_email, $user_id);
                
                if ($stmt->execute()) {
                    $email = $new_email;
                    // Update session variable
                    $_SESSION['email'] = $new_email;
                    $success_message = "Email updated successfully!";
                } else {
                    $error_message = "Error updating email: " . $conn->error;
                }
            }
            $stmt->close();
        }
    }
    
    // Handle email password update
    if (isset($_POST['update_email_password'])) {
        $new_email_password = $_POST['email_password'];
        
        // Update email password with proper parameter binding
        $stmt = $conn->prepare("UPDATE users SET email_password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $new_email_password, $user_id);
        
        if ($stmt->execute()) {
            $email_password = $new_email_password;
            // Update session variable if it exists
            if (isset($_SESSION['email_password'])) {
                $_SESSION['email_password'] = $new_email_password;
            }
            $success_message = "Email password updated successfully!";
        } else {
            $error_message = "Error updating email password: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Handle password update
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate passwords
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $password_error = "All password fields are required!";
        } else {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_data = $result->fetch_assoc();
            
            if ($current_password !== $user_data['password']) {
                $password_error = "Current password is incorrect!";
            } else if ($new_password !== $confirm_password) {
                $password_error = "New passwords do not match!";
            } else if (strlen($new_password) < 6) {
                $password_error = "New password must be at least 6 characters long!";
            } else {
                // Update password with proper parameter binding
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->bind_param("si", $new_password, $user_id);
                
                if ($stmt->execute()) {
                    $password = $new_password;
                    // Update session variable if it exists
                    if (isset($_SESSION['password'])) {
                        $_SESSION['password'] = $new_password;
                    }
                    $success_message = "Password updated successfully!";
                } else {
                    $error_message = "Error updating password: " . $conn->error;
                }
            }
            $stmt->close();
        }
    }
    
    // Handle profile picture deletion
    if (isset($_POST['delete_profile_picture'])) {
        // Get current profile picture
        $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $current_pic = $result->fetch_assoc();
        
        if ($current_pic && $current_pic['profile_picture'] && $current_pic['profile_picture'] != "default.jpg") {
            // Delete the file from the server
            $file_path = 'uploads/profiles/' . $current_pic['profile_picture'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Update the database to default image
            $default_pic = "default.jpg";
            $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
            $stmt->bind_param("si", $default_pic, $user_id);
            
            if ($stmt->execute()) {
                $profile_picture = $default_pic;
                // Update session variable
                $_SESSION['profile_picture'] = $default_pic;
                $success_message = "Profile picture removed successfully!";
            } else {
                $error_message = "Error removing profile picture: " . $conn->error;
            }
        }
        $stmt->close();
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
            $upload_dir = 'uploads/profiles/';
            
            // Create upload directories if they don't exist
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    $upload_error = "Failed to create upload directory. Please contact administrator.";
                }
            }
            
            // Continue if directory exists or was created successfully
            if (empty($upload_error)) {
                // Generate unique filename
                $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $filename = $user_id . '_' . time() . '.' . $file_extension;
                $target_file = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                    // Delete old profile picture if it exists and is not default
                    if ($profile_picture && $profile_picture != "default.jpg" && file_exists($upload_dir . $profile_picture)) {
                        unlink($upload_dir . $profile_picture);
                    }
                    
                    // Update profile picture in database with proper parameter binding
                    $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $filename, $user_id);
                    
                    if ($stmt->execute()) {
                        $profile_picture = $filename;
                        // Update session variable
                        $_SESSION['profile_picture'] = $filename;
                        $success_message = "Profile picture updated successfully!";
                    } else {
                        $upload_error = "Error updating profile picture in database: " . $conn->error;
                        // Delete the uploaded file if database update failed
                        if (file_exists($target_file)) {
                            unlink($target_file);
                        }
                    }
                    $stmt->close();
                } else {
                    $upload_error = "Error uploading file. Check directory permissions.";
                }
            }
        }
    }
    
    // Refresh user data after any updates to ensure consistency
    $stmt = $conn->prepare("SELECT username, email, email_password, password, profile_picture FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $updated_user = $result->fetch_assoc();
        $username = $updated_user['username'];
        $email = $updated_user['email'];
        $email_password = $updated_user['email_password'];
        $password = $updated_user['password'];
        if ($updated_user['profile_picture']) {
            $profile_picture = $updated_user['profile_picture'];
        }
        
        // Update all session variables with fresh data
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        if (isset($_SESSION['email_password'])) {
            $_SESSION['email_password'] = $email_password;
        }
        if (isset($_SESSION['password'])) {
            $_SESSION['password'] = $password;
        }
        $_SESSION['profile_picture'] = $profile_picture;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #f6f6f6;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .profile-header h1 {
            font-size: 24px;
            color: #333;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }

        .profile-content {
            display: flex;
            gap: 40px;
            background: white;
            padding: 30px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .profile-form {
            flex: 1;
        }

        .form-group {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .form-label {
            width: 120px;
            color: #666;
        }

        .form-control {
            flex: 1;
            max-width: 400px;
            display: flex;
            align-items: center;
        }

        .form-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .edit-button {
            color: #1a73e8;
            text-decoration: none;
            margin-left: 10px;
            font-size: 14px;
            cursor: pointer;
            background: none;
            border: none;
        }

        .profile-image {
            width: 240px;
            flex-shrink: 0;
        }

        .image-container {
            border: 1px solid #ddd;
            padding: 20px;
            text-align: center;
            border-radius: 4px;
        }

        .preview-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin-bottom: 15px;
            object-fit: cover;
            background-color: #f0f0f0;
        }

        .save-btn {
            background:#cd8b62;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .save-btn:hover {
            background:#b87952;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 400px;
            border-radius: 4px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }

        .modal-title {
            margin-bottom: 20px;
            color: #333;
        }

        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .form-error {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="profile-header">
            <h1>My Profile</h1>
        </div>
        <p class="subtitle">Manage and protect your account</p>

        <?php if (isset($success_message) && !empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($error_message) && !empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="profile-content">
            <div class="profile-form">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <div class="form-control">
                        <input type="text" class="form-input" id="username" value="<?php echo htmlspecialchars($username); ?>" readonly>
                        <button type="button" class="edit-button" onclick="openModal('usernameModal')">Edit</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Email</label>
                    <div class="form-control">
                        <input type="email" class="form-input" id="email" value="<?php echo htmlspecialchars($email); ?>" readonly>
                        <button type="button" class="edit-button" onclick="openModal('emailModal')">Edit</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="form-control">
                        <div style="position: relative; width: 100%;">
                            <input type="password" class="form-input" id="password-field" value="<?php echo htmlspecialchars($password); ?>" readonly>
                            <span onclick="togglePassword('password-field')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block;" id="password-show-icon">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;" id="password-hide-icon">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </span>
                        </div>
                        <button type="button" class="edit-button" onclick="openModal('passwordModal')">Change</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Password</label>
                    <div class="form-control">
                        <div style="position: relative; width: 100%;">
                            <input type="password" class="form-input" id="email-password-field" value="<?php echo htmlspecialchars($email_password); ?>" readonly>
                            <span onclick="togglePassword('email-password-field')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block;" id="email-password-show-icon">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;" id="email-password-hide-icon">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </span>
                        </div>
                        <button type="button" class="edit-button" onclick="openModal('emailPasswordModal')">Change</button>
                    </div>
                </div>
            </div>

            <div class="profile-image">
                <form action="profile.php" method="POST" enctype="multipart/form-data">
                    <div class="image-container">
                        <?php if ($profile_picture && file_exists('uploads/profiles/' . $profile_picture)): ?>
                            <img src="uploads/profiles/<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile" class="preview-image" id="imagePreview">
                        <?php else: ?>
                            <img src="/api/placeholder/120/120" alt="Profile" class="preview-image" id="imagePreview">
                        <?php endif; ?>
                        
                        <input type="file" name="profile_picture" id="profilePicture" style="display:none;" onchange="previewImage(this)">
                        <label for="profilePicture" style="color: #1a73e8; cursor: pointer; display: block; margin-bottom: 15px;">
                            Select Image
                        </label>
                        
                        <?php if (isset($upload_error) && !empty($upload_error)): ?>
                            <div class="form-error"><?php echo $upload_error; ?></div>
                        <?php endif; ?>
                        
                        <div style="color: #666; font-size: 12px; line-height: 1.5;">
                            File size: maximum 1 MB<br>
                            File extension: JPEG, PNG
                        </div>
                        
                        <button type="submit" class="save-btn" style="margin-top: 15px;">Upload Picture</button>
                        <?php if ($profile_picture && $profile_picture != "default.jpg"): ?>
                            <div style="margin-top: 10px;">
                                <form method="POST" action="profile.php">
                                    <input type="hidden" name="delete_profile_picture" value="1">
                                    <button type="submit" class="save-btn" style="background-color: #dc3545;">Remove Picture</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <div style="display: flex; gap: 15px; margin-top: 20px;">
            <a href="index.php" class="save-btn" style="background-color: #6c757d; text-decoration: none; display: inline-block; text-align: center;">Back to Home</a>
        </div>
    </div>

    <!-- Username Modal -->
    <div id="usernameModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('usernameModal')">&times;</span>
            <h3 class="modal-title">Change Username</h3>
            
            <?php if (isset($username_error) && !empty($username_error)): ?>
                <div class="alert alert-danger"><?php echo $username_error; ?></div>
            <?php endif; ?>
            
            <form action="profile.php" method="POST">
                <div style="margin-bottom: 15px;">
                    <label for="username" style="display: block; margin-bottom: 5px;">New Username</label>
                    <input type="text" name="username" class="form-input" value="<?php echo htmlspecialchars($username); ?>" required>
                </div>
                
                <input type="hidden" name="update_username" value="1">
                <button type="submit" class="save-btn">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Email Modal -->
    <div id="emailModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('emailModal')">&times;</span>
            <h3 class="modal-title">Change Email</h3>
            
            <?php if (isset($email_error) && !empty($email_error)): ?>
                <div class="alert alert-danger"><?php echo $email_error; ?></div>
            <?php endif; ?>
            
            <form action="profile.php" method="POST">
                <div style="margin-bottom: 15px;">
                    <label for="email" style="display: block; margin-bottom: 5px;">New Email</label>
                    <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>
                
                <input type="hidden" name="update_email" value="1">
                <button type="submit" class="save-btn">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Password Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('passwordModal')">&times;</span>
            <h3 class="modal-title">Change Password</h3>
            
            <?php if (isset($password_error) && !empty($password_error)): ?>
                <div class="alert alert-danger"><?php echo $password_error; ?></div>
            <?php endif; ?>
            
            <form action="profile.php" method="POST">
                <div style="margin-bottom: 15px; position: relative;">
                    <label for="current_password" style="display: block; margin-bottom: 5px;">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-input" required>
                </div>
                
                <div style="margin-bottom: 15px; position: relative;">
                    <label for="new_password" style="display: block; margin-bottom: 5px;">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-input" required>
                </div>
                
                <div style="margin-bottom: 15px; position: relative;">
                    <label for="confirm_password" style="display: block; margin-bottom: 5px;">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                </div>
                
                <input type="hidden" name="update_password" value="1">
                <button type="submit" class="save-btn">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Email Password Modal -->
    <div id="emailPasswordModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('emailPasswordModal')">&times;</span>
            <h3 class="modal-title">Change Email Password</h3>
            
            <form action="profile.php" method="POST">
                <div style="margin-bottom: 15px; position: relative;">
                    <label for="email_password" style="display: block; margin-bottom: 5px;">New Email Password</label>
                    <input type="password" id="email_password" name="email_password" class="form-input" required>
                </div>
                
                <input type="hidden" name="update_email_password" value="1">
                <button type="submit" class="save-btn">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
        // Open modal
        function openModal(modalId) {
            document.getElementById(modalId).style.display = "block";
        }
        
        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = "none";
        }
        
        // Preview image before upload
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                
                reader.onload = function(e) {
                    document.getElementById('imagePreview').src = e.target.result;
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = "none";
            }
        }
        
        // Toggle password visibility in main form
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const showIcon = document.getElementById(fieldId.replace('field', 'show-icon'));
            const hideIcon = document.getElementById(fieldId.replace('field', 'hide-icon'));
            
            if (field.type === 'password') {
                field.type = 'text';
                showIcon.style.display = 'none';
                hideIcon.style.display = 'inline-block';
            } else {
                field.type = 'password';
                showIcon.style.display = 'inline-block';
                hideIcon.style.display = 'none';
            }
        }
        
        // Handle DOM ready events
        document.addEventListener('DOMContentLoaded', function() {
            // Check if there's a success message and close any open modals
            const successMessage = document.querySelector('.alert-success');
            if (successMessage) {
                // Close all modals if success message is present
                const modals = document.querySelectorAll('.modal');
                modals.forEach(function(modal) {
                    modal.style.display = 'none';
                });
            }
            
            // Handle form submissions with better error handling
            const forms = document.querySelectorAll('form[method="POST"]');
            forms.forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    // Add loading state to submit buttons
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        const originalText = submitBtn.textContent;
                        submitBtn.textContent = 'Saving...';
                        submitBtn.disabled = true;
                        
                        // Re-enable button after a delay if form doesn't submit
                        setTimeout(function() {
                            submitBtn.textContent = originalText;
                            submitBtn.disabled = false;
                        }, 5000);
                    }
                });
            });
        });
    </script>
</body>
</html>