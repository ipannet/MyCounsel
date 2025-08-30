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

// Fetch statistics for the dashboard (excluding admin users)
$total_users = 0;
if (!empty($adminUsernames)) {
    $adminPlaceholders = str_repeat('?,', count($adminUsernames) - 1) . '?';
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE username NOT IN ($adminPlaceholders)");
    $stmt->bind_param(str_repeat('s', count($adminUsernames)), ...$adminUsernames);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $total_users = $row['count'];
    }
    $stmt->close();
} else {
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    if ($row = $result->fetch_assoc()) {
        $total_users = $row['count'];
    }
}

$total_appointments = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM student_appointment");
if ($row = $result->fetch_assoc()) {
    $total_appointments = $row['count'];
}

$pending_appointments = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM student_appointment WHERE status = 'Pending'");
if ($row = $result->fetch_assoc()) {
    $pending_appointments = $row['count'];
}

$completed_appointments = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM student_appointment WHERE status = 'Completed'");
if ($row = $result->fetch_assoc()) {
    $completed_appointments = $row['count'];
}

// Get total admin count for display
$total_admins = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM admins");
if ($row = $result->fetch_assoc()) {
    $total_admins = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | MyCounsel</title>
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
            background-color:#cd8b62;
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
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            position: relative;
            transition: transform 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .stat-title {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }
        
        .stat-subtitle {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        .stat-icon {
            width: 20px;
            height: 20px;
            opacity: 0.7;
        }
        
        .recent-section {
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
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .recent-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .recent-table th, .recent-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .recent-table th {
            color: #666;
            font-weight: normal;
            background-color: #f9f9f9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 30px;
            font-size: 12px;
        }
        
        .status-pending {
            background-color: #fff8e1;
            color: #ffa000;
        }
        
        .status-completed {
            background-color: #e8f5e9;
            color: #4caf50;
        }
        
        .status-cancelled {
            background-color: #ffebee;
            color: #f44336;
        }
        
        .status-active {
            background-color: #e8f5e9;
            color: #4caf50;
        }
        
        .view-all {
            display: block;
            text-align: center;
            color: #cd8b62;
            text-decoration: none;
            margin-top: 15px;
            font-size: 14px;
            padding: 8px;
            border-radius: 4px;
            transition: background-color 0.2s ease;
        }
        
        .view-all:hover {
            text-decoration: underline;
            background-color: #f8f9fa;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: #f0f0f0;
            color: #666;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
            font-size: 12px;
            overflow: hidden;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .info-banner {
            background-color: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 4px;
            padding: 12px;
            margin-bottom: 20px;
            color: #1976d2;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .admin-stat {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .admin-stat .stat-title {
            color: rgba(255, 255, 255, 0.9);
        }
        
        .admin-stat .stat-value {
            color: white;
        }
        
        .admin-stat .stat-subtitle {
            color: rgba(255, 255, 255, 0.7);
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
            <li><a href="/php/admin-dashboard.php" class="active">Home</a></li>
            <li><a href="/php/admin-users.php">User Management</a></li>
            <li><a href="/php/admin-messages.php">Messages Management</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1 class="page-title">Admin Dashboard</h1>
            
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
            ℹ️ <strong>Dashboard Overview:</strong> Statistics show counsellor users only. Admin accounts are managed separately for security.
        </div>
        
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-title">
                    <svg class="stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="8.5" cy="7" r="4"></circle>
                        <path d="M20 8v6M23 11h-6"></path>
                    </svg>
                    Counsellor Users
                </div>
                <div class="stat-value"><?php echo $total_users; ?></div>
                <div class="stat-subtitle">Registered counsellors in system</div>
            </div>
            
            <div class="stat-card admin-stat">
                <div class="stat-title">
                    <svg class="stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 12l2 2 4-4"></path>
                        <path d="M21 12c-1 0-3-1-3-3s2-3 3-3 3 1 3 3-2 3-3 3"></path>
                        <path d="M3 12c1 0 3-1 3-3s-2-3-3-3-3 1-3 3 2 3 3 3"></path>
                        <path d="M13 12h1"></path>
                    </svg>
                    System Administrators
                </div>
                <div class="stat-value"><?php echo $total_admins; ?></div>
                <div class="stat-subtitle">Active admin accounts</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">
                    <svg class="stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    Total Appointments
                </div>
                <div class="stat-value"><?php echo $total_appointments; ?></div>
                <div class="stat-subtitle">All student appointments</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">
                    <svg class="stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12,6 12,12 16,14"></polyline>
                    </svg>
                    Pending Appointments
                </div>
                <div class="stat-value"><?php echo $pending_appointments; ?></div>
                <div class="stat-subtitle">Awaiting completion</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">
                    <svg class="stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22,4 12,14.01 9,11.01"></polyline>
                    </svg>
                    Completed Appointments
                </div>
                <div class="stat-value"><?php echo $completed_appointments; ?></div>
                <div class="stat-subtitle">Successfully finished</div>
            </div>
        </div>
        
        <div class="recent-section">
            <h2 class="section-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="8.5" cy="7" r="4"></circle>
                    <path d="M20 8v6M23 11h-6"></path>
                </svg>
                Recent Counsellor Users
            </h2>
            
            <table class="recent-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Joined Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch recent users (excluding admin users)
                    if (!empty($adminUsernames)) {
                        $adminPlaceholders = str_repeat('?,', count($adminUsernames) - 1) . '?';
                        $stmt = $conn->prepare("SELECT * FROM users WHERE username NOT IN ($adminPlaceholders) ORDER BY created_at DESC LIMIT 5");
                        $stmt->bind_param(str_repeat('s', count($adminUsernames)), ...$adminUsernames);
                        $stmt->execute();
                        $result = $stmt->get_result();
                    } else {
                        $result = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
                    }
                    
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>";
                            echo "<div class='user-info'>";
                            echo "<div class='user-avatar'>";
                            if (isset($row['profile_picture']) && !empty($row['profile_picture']) && file_exists('uploads/profiles/' . $row['profile_picture'])) {
                                echo "<img src='uploads/profiles/" . htmlspecialchars($row['profile_picture']) . "' alt='User'>";
                            } else {
                                echo strtoupper(substr($row['username'], 0, 1));
                            }
                            echo "</div>";
                            echo htmlspecialchars($row['username']);
                            echo "</div>";
                            echo "</td>";
                            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                            echo "<td>" . date("M d, Y", strtotime($row['created_at'])) . "</td>";
                            echo "<td><span class='status-badge status-active'>Active</span></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' style='text-align: center; color: #666; font-style: italic;'>No counsellor users found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            
            <a href="/php/admin-users.php" class="view-all">View All Counselor Users</a>
        </div>
    </div>
    
    <script>
        // Handle dropdown menu with JavaScript
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
            
            // Add hover effects to stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>