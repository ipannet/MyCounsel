<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Check if email exists in admin table
    $stmt = $conn->prepare("SELECT admin_id, username, password, email FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $username, $db_password, $email);
        $stmt->fetch();

        // Direct password comparison (plaintext) for admin authentication
        if ($password === $db_password) {
            $_SESSION['admin_id'] = $id;
            $_SESSION['admin_username'] = $username;
            $_SESSION['admin_email'] = $email;
            $_SESSION['is_admin'] = true;

            echo "<script>
                    alert('Login successful!');
                    window.location.href = 'admin-dashboard.php'; 
                  </script>";
            exit();
        } else {
            echo "<script>alert('Incorrect password!'); window.history.back();</script>";
            exit();
        }
    } else {
        echo "<script>alert('No admin account found with this email!'); window.history.back();</script>";
        exit();
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | MyCounsel Admin</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    
    :root {
      --accent-color: #cd8b62;
      --base-color: white;
      --text-color: #6e7070;
      --input-color: #F3F0FF;
    }
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    html {
      font-family: 'Poppins', sans-serif;
      font-size: 16px;
      color: var(--text-color);
    }
    
    body {
      min-height: 100vh;
      display: flex;
      background-image: url('/picture/backgroundlogin3.jpg');
      background-size: cover;
      background-position: right;
    }
    
    .wrapper {
      background-color: #efe2db;
      width: 400px;
      padding: 30px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
      animation: slideIn 0.8s ease-out forwards;
    }
    
    .logo {
      margin-bottom: 15px;
      display: flex;
      justify-content: center;
    }
    
    .logo img {
      max-width: 80px;
      height: auto;
    }
    
    .tagline {
      font-family: 'Segoe UI', sans-serif;
      font-size: 1rem;
      margin-bottom: 18px;
      text-align: center;
    }
    
    h1 {
      font-size: 1.6rem;
      font-weight: 700;
      color: #3e4040;
      margin-bottom: 15px;
      text-align: center;
      animation: fadeIn 0.8s ease-out 0.3s both;
    }
    
    .welcome-text {
      font-size: 1rem;
      margin-bottom: 20px;
      text-align: center;
      animation: fadeIn 0.8s ease-out 0.4s both;
    }
    
    form {
      width: 100%;
      margin-bottom: 18px;
      display: flex;
      flex-direction: column;
      gap: 15px;
      animation: fadeIn 0.8s ease-out 0.5s both;
    }
    
    form > div {
      width: 100%;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 5px;
      opacity: 0;
      animation: fadeIn 0.5s ease-out forwards;
    }
    
    form h4 {
      font-size: 1.1rem;
      font-weight: 500;
      margin-bottom: 5px;
    }
    
    form input {
      width: 100%;
      height: 45px;
      padding: 0 15px;
      font-size: 1.05rem;
      border: 1px solid var(--input-color);
      background-color: var(--input-color);
      border-radius: 4px;
      transition: 150ms ease;
    }
    
    form input:focus {
      outline: none;
      border-color: #84cdee;
      box-shadow: 0 0 5px rgba(132, 205, 238, 0.5);
    }
    
    form input::placeholder {
      color: #aaa;
    }
    
    .forgot-password {
      width: 100%;
      display: flex;
      justify-content: flex-end;
      margin-top: 5px;
    }
    
    .forgot-password a {
      font-size: 0.9rem;
      color: var(--accent-color);
      text-decoration: none;
    }
    
    button {
      width: 100%;
      padding: 14px;
      margin-top: 10px;
      background-color: var(--accent-color);
      color: var(--base-color);
      border: none;
      border-radius: 4px;
      font-weight: 600;
      text-transform: uppercase;
      cursor: pointer;
      transition: 150ms ease;
      font-size: 1.1rem;
      opacity: 0;
      animation: fadeIn 0.5s ease-out 0.8s forwards;
    }
    
    button:hover {
      background-color: var(--text-color);
    }
    
    .signup-options {
      width: 100%;
      text-align: center;
      margin-top: 8px;
      font-size: 0.95rem;
      opacity: 0;
      animation: fadeIn 0.5s ease-out 0.9s forwards;
    }
    
    a {
      text-decoration: none;
      color: var(--accent-color);
    }
    
    a:hover {
      text-decoration: underline;
    }
    
    #error-message {
      color: #f06272;
      font-size: 0.9rem;
      margin-bottom: 8px;
    }
    
    /* Animations */
    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateX(-50px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }
    
    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    /* Animation delays for form elements */
    form > div:nth-child(1) {
      animation-delay: 0.6s;
    }
    
    form > div:nth-child(2) {
      animation-delay: 0.7s;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
      .wrapper {
        width: 100%;
        height: 100vh;
      }
    }
  </style>
  <script type="text/javascript" defer>
    document.addEventListener('DOMContentLoaded', function() {
      const errorMessage = document.getElementById('error-message');
      
      const form = document.querySelector('form');
      form.addEventListener('submit', function(e) {
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        
        if (!email || !password) {
          e.preventDefault();
          errorMessage.textContent = 'Please fill in all fields.';
        }
      });
    });
  </script>
</head>
<body>
  <div class="wrapper">
    <div class="logo">
      <img src="/picture/logo.png" alt="MyCounsel Logo">
    </div>
    <span class="tagline">More We Care, The Better You Flourish.</span>
    <h1>Admin Login</h1>
    <p class="welcome-text">Enter your admin credentials to access dashboard</p>
    
    <p id="error-message"></p>
    
    <form action="admin-login.php" method="POST">
      <div>
        <h4>Email</h4>
        <input type="email" name="email" id="email" placeholder="Enter your email" required>
      </div>
      <div>
        <h4>Password</h4>
        <input type="password" name="password" id="password" placeholder="Enter your password" required>
        <div class="forgot-password">
          <a href="admin-forgot-password.php">Forgot Password?</a>
        </div>
      </div>
      <button type="submit">Login</button>
    </form>

    <div class="signup-options">
      <p>Need admin access? <a href="admin-signup.php">Register as Admin</a></p>
    </div>
    <div class="signup-options">
      <p>Not an admin? <a href="/php/signin.php">Login as Counsellor</a></p>
    </div>
  </div>
</body>
</html>