<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $username = trim($_POST['register-username']);
    $email = trim($_POST['register-email']);
    $password = $_POST['register-password'];
    $confirmPassword = $_POST['repeat-password'];
    $email_password = $_POST['email_password'];
    
    // Validate passwords match
    if ($password !== $confirmPassword) {
        echo "<script>alert('Passwords do not match!'); window.history.back();</script>";
        exit();
    }
    
    // Check if email already exists
    $checkEmail = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $checkEmail->store_result();
    
    if ($checkEmail->num_rows > 0) {
        echo "<script>alert('Email already registered!'); window.history.back();</script>";
        $checkEmail->close();
        exit();
    }
    $checkEmail->close();
    
    // Check if username already exists
    $checkUsername = $conn->prepare("SELECT username FROM users WHERE username = ?");
    $checkUsername->bind_param("s", $username);
    $checkUsername->execute();
    $checkUsername->store_result();
    
    if ($checkUsername->num_rows > 0) {
        echo "<script>alert('Username already taken!'); window.history.back();</script>";
        $checkUsername->close();
        exit();
    }
    $checkUsername->close();
    
    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, email_password, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $username, $email, $password, $email_password);
    
    if ($stmt->execute()) {
        // Get the new user_id
        $user_id = $conn->insert_id;
        
        // Set session variables
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        $_SESSION['email_password'] = $email_password;
        
        echo "<script>
                alert('Registration successful!');
                window.location.href = 'signin.php';
              </script>";
    } else {
        echo "<script>alert('Registration failed: " . $stmt->error . "'); window.history.back();</script>";
    }
    
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Signup | MyCounsel</title>
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
      font-size: 15px;
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
      width: 360px;
      padding: 25px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
      animation: slideIn 0.8s ease-out forwards;
    }
    
    .logo {
      margin-bottom: 10px;
      display: flex;
      justify-content: center;
    }
    
    .logo img {
      max-width: 70px;
      height: auto;
    }
    
    .tagline {
      font-family: 'Segoe UI', sans-serif;
      font-size: 0.9rem;
      margin-bottom: 15px;
      text-align: center;
    }
    
    h1 {
      font-size: 1.4rem;
      font-weight: 700;
      text-transform: uppercase;
      color: #3e4040;
      margin-bottom: 15px;
      animation: fadeIn 0.8s ease-out 0.3s both;
    }
    
    form {
      width: 100%;
      margin-bottom: 15px;
      display: flex;
      flex-direction: column;
      gap: 12px;
      animation: fadeIn 0.8s ease-out 0.5s both;
    }
    
    form > div {
      width: 100%;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 4px;
      opacity: 0;
      animation: fadeIn 0.5s ease-out forwards;
    }
    
    form h4 {
      font-size: 1rem;
      font-weight: 500;
      margin-bottom: 4px;
    }
    
    form input {
      width: 100%;
      height: 40px;
      padding: 0 12px;
      font-size: 1rem;
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
    
    .form-group {
      width: 100%;
    }
    
    button {
      width: 100%;
      padding: 12px;
      margin-top: 8px;
      background-color: var(--accent-color);
      color: var(--base-color);
      border: none;
      border-radius: 4px;
      font-weight: 600;
      text-transform: uppercase;
      cursor: pointer;
      transition: 150ms ease;
      font-size: 1rem;
      opacity: 0;
      animation: fadeIn 0.5s ease-out 0.8s forwards;
    }
    
    button:hover {
      background-color: var(--text-color);
    }
    
    p {
      font-size: 0.9rem;
      margin-top: 10px;
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
      font-size: 0.85rem;
      margin-bottom: 5px;
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
    
    form > div:nth-child(3) {
      animation-delay: 0.8s;
    }
    
    form > div:nth-child(4) {
      animation-delay: 0.9s;
    }
    
    form > div:nth-child(5) {
      animation-delay: 1s;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
      .wrapper {
        width: 100%;
        height: 100vh;
      }
    }
    
    /* Password help text */
    .password-help {
      font-size: 0.85rem;
      color: #6e7070;
      margin-top: 4px;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    
    .password-help-link {
      color: #cd8b62;
      font-weight: 500;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
    }
    
    .password-help-link:hover {
      text-decoration: underline;
    }
    
    /* Notification badge */
    .notification-badge {
      position: fixed;
      top: 20px;
      right: 20px;
      background-color: rgba(255, 255, 255, 0.95);
      border-left: 4px solid #cd8b62;
      padding: 12px 15px;
      border-radius: 4px;
      box-shadow: 0 3px 15px rgba(0, 0, 0, 0.1);
      font-size: 0.9rem;
      color: #6e7070;
      max-width: 300px;
      z-index: 1000;
      cursor: pointer;
      transition: all 0.3s ease;
      animation: slideInRight 0.5s ease-out forwards, pulse 2s infinite;
    }
    
    .notification-badge:hover {
      background-color: white;
      transform: translateY(-3px);
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
    }
    
    @keyframes slideInRight {
      from {
        opacity: 0;
        transform: translateX(30px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }
    
    @keyframes pulse {
      0% { box-shadow: 0 3px 15px rgba(0, 0, 0, 0.1); }
      50% { box-shadow: 0 3px 20px rgba(205, 139, 98, 0.3); }
      100% { box-shadow: 0 3px 15px rgba(0, 0, 0, 0.1); }
    }
    
    /* Floating info button styles */
    .floating-info-btn {
      position: fixed;
      bottom: 30px;
      right: 30px;
      width: 40px;
      height: 40px;
      background-color: #cd8b62;
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      font-weight: bold;
      cursor: pointer;
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
      z-index: 1000;
      transition: all 0.3s ease;
    }
    
    .floating-info-btn:hover {
      transform: scale(1.1);
      background-color: #b67b56;
    }
    
    /* Modal styles */
    .gmail-guide-modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 1001;
      align-items: center;
      justify-content: center;
    }
    
    .gmail-guide-content {
      background-color: #fff;
      border-radius: 8px;
      width: 90%;
      max-width: 450px;
      padding: 25px;
      position: relative;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
      animation: slideInUp 0.3s ease-out;
    }
    
    @keyframes slideInUp {
      from {
        transform: translateY(50px);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
      }
    }
    
    .close-btn {
      position: absolute;
      top: 15px;
      right: 20px;
      font-size: 24px;
      color: #6e7070;
      cursor: pointer;
      transition: color 0.2s;
    }
    
    .close-btn:hover {
      color: #cd8b62;
    }
    
    .gmail-guide-content h3 {
      color: #cd8b62;
      margin-bottom: 20px;
      font-size: 18px;
      padding-right: 20px;
    }
    
    .guide-steps {
      display: flex;
      flex-direction: column;
      gap: 15px;
    }
    
    .guide-step {
      display: flex;
      align-items: flex-start;
      gap: 12px;
    }
    
    .step-number {
      background-color: #cd8b62;
      color: white;
      width: 24px;
      height: 24px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      font-weight: 600;
      flex-shrink: 0;
    }
    
    .step-text {
      font-size: 14px;
      color: #6e7070;
      line-height: 1.5;
    }
    
    .step-text a {
      color: #cd8b62;
      text-decoration: none;
    }
    
    .step-text a:hover {
      text-decoration: underline;
    }
    
    /* Responsive adjustments for info button */
    @media (max-width: 768px) {
      .floating-info-btn {
        bottom: 20px;
        right: 20px;
        width: 35px;
        height: 35px;
        font-size: 18px;
      }
      
      .gmail-guide-content {
        width: 85%;
        padding: 20px;
      }
      
      .notification-badge {
        top: 10px;
        right: 10px;
        max-width: calc(100% - 20px);
        font-size: 0.8rem;
      }
    }
  </style>
  <script defer>
    document.addEventListener('DOMContentLoaded', function() {
      // Password validation
      const form = document.querySelector('form');
      const password = document.getElementById('register-password');
      const confirmPassword = document.getElementById('repeat-password');
      const errorMessage = document.getElementById('error-message');
      
      form.addEventListener('submit', function(e) {
        if (password.value !== confirmPassword.value) {
          e.preventDefault();
          errorMessage.textContent = 'Passwords do not match!';
        } else {
          errorMessage.textContent = '';
        }
      });
      
      // Gmail guide modal functionality
      const infoBtn = document.getElementById('gmail-info-btn');
      const notificationBadge = document.getElementById('notification-badge');
      const guideModal = document.getElementById('gmail-guide-modal');
      const closeBtn = document.getElementById('close-guide');
      const passwordHelpLink = document.getElementById('password-help-link');
      
      function openGmailGuide() {
        guideModal.style.display = 'flex';
        // Hide notification once clicked
        if (notificationBadge) {
          notificationBadge.style.display = 'none';
        }
      }
      
      infoBtn.addEventListener('click', openGmailGuide);
      
      if (notificationBadge) {
        notificationBadge.addEventListener('click', openGmailGuide);
      }
      
      if (passwordHelpLink) {
        passwordHelpLink.addEventListener('click', openGmailGuide);
      }
      
      closeBtn.addEventListener('click', function() {
        guideModal.style.display = 'none';
      });
      
      // Close modal when clicking outside the content
      window.addEventListener('click', function(event) {
        if (event.target === guideModal) {
          guideModal.style.display = 'none';
        }
      });
      
      // Auto-hide notification after 10 seconds
      if (notificationBadge) {
        setTimeout(function() {
          notificationBadge.style.opacity = '0';
          setTimeout(function() {
            notificationBadge.style.display = 'none';
          }, 500);
        }, 10000);
      }
    });
  </script>
</head>
<body>
  <!-- Notification badge -->
  <div class="notification-badge" id="notification-badge">
    <strong>How to get Gmail App Password?</strong> <span style="color: #cd8b62; margin-left: 5px;">Click Here</span>
  </div>
  
  <div class="wrapper">
    <div class="logo">
      <img src="/picture/logo.png" alt="Logo">
    </div>
    <span class="tagline">More We Care, The Better You Flourish.</span>
    <h1>Sign Up</h1>

    <p id="error-message"></p>
    <form action="signup.php" method="POST">
      <div>
        <h4>Username</h4>
        <input type="text" name="register-username" id="register-username" placeholder="Username" required>
      </div>
      <div>
        <h4>Email</h4>
        <input type="email" name="register-email" id="register-email" placeholder="Email" required>
      </div>
      <div>
        <h4>Password</h4>
        <input type="password" name="register-password" id="register-password" placeholder="Password" required>
      </div>
      <div>
        <h4>Re-enter password</h4>
        <input type="password" name="repeat-password" id="repeat-password" placeholder="Confirm Password" required>
      </div>
      <div class="form-group">
        <h4>Gmail App Password</h4>
        <input type="password" name="email_password" id="email_password" placeholder="Gmail App Password" required>
        <div class="password-help">
          Need help? <span class="password-help-link" id="password-help-link">How to get Gmail App Password</span>
        </div>
      </div>
      
      <button type="submit">Signup</button>
    </form>
    <p>Already have an Account? <a href="/php/signin.php">Login</a></p>
  </div>
  
  <!-- Floating Gmail Info Button and Modal -->
  <div class="floating-info-btn" id="gmail-info-btn">i</div>

  <div class="gmail-guide-modal" id="gmail-guide-modal">
    <div class="gmail-guide-content">
      <span class="close-btn" id="close-guide">&times;</span>
      <h3>How to Get Gmail App Password</h3>
      <div class="guide-steps">
        <div class="guide-step">
          <div class="step-number">1</div>
          <div class="step-text">Go to your <a href="https://myaccount.google.com/security" target="_blank">Google Account Security</a></div>
        </div>
        
        <div class="guide-step">
          <div class="step-number">2</div>
          <div class="step-text">Enable "2-Step Verification" if you haven't already</div>
        </div>
        
        <div class="guide-step">
          <div class="step-number">3</div>
          <div class="step-text">Select "App passwords" (under "Signing in to Google")</div>
        </div>
        
        <div class="guide-step">
          <div class="step-number">4</div>
          <div class="step-text">Choose "Other" and type "MyCounsel"</div>
        </div>
        
        <div class="guide-step">
          <div class="step-number">5</div>
          <div class="step-text">Copy the 16-character code and paste it in the Gmail App Password field</div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>