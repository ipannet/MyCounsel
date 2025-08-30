<?php
session_start();

// Clear all admin-related session variables
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_email']);
unset($_SESSION['is_admin']);

// Redirect to admin login page
header("Location: admin-login.php");
exit();
?>