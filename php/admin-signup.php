<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $repeat_password = $_POST['repeat_password'];
    
    // Check if passwords match
    if ($password !== $repeat_password) {
        echo "<script>alert('Passwords do not match!'); window.history.back();</script>";
        exit();
    } else {
        // Store password as plaintext (as requested)
        $plaintext_password = $password;

        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT admin_id FROM admins WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            echo "<script>alert('Username or Email already exists!'); window.history.back();</script>";
            exit();
        } else {
            $stmt->close();
            
            // Insert new admin into database
            $stmt = $conn->prepare("INSERT INTO admins (username, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $email, $plaintext_password);
            
            if ($stmt->execute()) {
                echo "<script>
                        alert('Admin account created successfully!');
                        window.location.href = 'admin-login.php';
                      </script>";
                exit();
            } else {
                echo "<script>alert('Error: " . $stmt->error . "'); window.history.back();</script>";
                exit();
            }
            
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Sign Up | MyCounsel Admin</title>
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
      width: 370px;
      padding: 25px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
      animation: slideIn 0.8s ease-out forwards;
    }
    
    .logo {
      margin-bottom: 12px;
      display: flex;
      justify-content: center;
    }
    
    .logo img {
      max-width: 70px;
      height: auto;
    }
    
    .tagline {
      font-family: 'Segoe UI', sans-serif;
      font-size: 0.95rem;
      margin-bottom: 15px;
      text-align: center;
    }
    
    h1 {
      font-size: 1.5rem;
      font-weight: 700;
      text-transform: uppercase;
      color: #3e4040;
      margin-bottom: 12px;
      text-align: center;
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
      margin-bottom: 3px;
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
      margin-top: 8px;
      opacity: 0;
      animation: fadeIn 0.5s ease-out 0.9s forwards;
      text-align: center;
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
      margin-bottom: 6px;
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
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
      .wrapper {
        width: 100%;
        height: 100vh;
      }
    }
  </style>
  <script defer>
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.querySelector('form');
      const password = document.getElementById('password');
      const confirmPassword = document.getElementById('repeat_password');
      const errorMessage = document.getElementById('error-message');
      
      form.addEventListener('submit', function(e) {
        if (password.value !== confirmPassword.value) {
          e.preventDefault();
          errorMessage.textContent = 'Passwords do not match!';
        } else {
          errorMessage.textContent = '';
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
    <h1>Admin Sign Up</h1>

    <p id="error-message"></p>
    <form action="admin-signup.php" method="POST">
      <div>
        <h4>Username</h4>
        <input type="text" name="username" id="username" placeholder="Username" required>
      </div>
      <div>
        <h4>Email</h4>
        <input type="email" name="email" id="email" placeholder="Email" required>
      </div>
      <div>
        <h4>Password</h4>
        <input type="password" name="password" id="password" placeholder="Password" required>
      </div>
      <div>
        <h4>Re-enter password</h4>
        <input type="password" name="repeat_password" id="repeat_password" placeholder="Confirm Password" required>
      </div>
      <button type="submit">Signup</button>
    </form>
    <p>Already have an Account? <a href="admin-login.php">Login</a></p>
    <p>Not an admin? <a href="/php/signup.php">Register as Counsellor</a></p>
  </div>
</body>
</html>