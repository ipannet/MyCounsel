// JavaScript for Login Page

document.addEventListener("DOMContentLoaded", () => {
  // Select form and input fields
  const form = document.getElementById("form");
  const emailInput = document.getElementById("email-input");
  const passwordInput = document.getElementById("password-input");
  const errorMessage = document.getElementById("error-message");

  function togglePassword() {
    const passwordInput = document.getElementById('password-input');
    const toggleButton = document.querySelector('.toggle-password i');
    
    if (passwordInput.type === 'password') {
      passwordInput.type = 'text';
      toggleButton.className = 'eye-slash-icon';
    } else {
      passwordInput.type = 'password';
      toggleButton.className = 'eye-icon';
    }
  }

  // Add click event listener to the toggle button
  document.querySelector('.toggle-password').addEventListener('click', togglePassword);
  // Validate email format
  const validateEmail = (email) => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  };

  // Form submission event
  form.addEventListener("submit", (event) => {
    event.preventDefault(); // Prevent default form submission

    // Get input values
    const email = emailInput.value.trim();
    const password = passwordInput.value.trim();

    // Reset error message
    errorMessage.textContent = "";

    // Validate inputs
    if (!validateEmail(email)) {
      errorMessage.textContent = "Please enter a valid email address.";
      return;
    }

    if (password.length < 6) {
      errorMessage.textContent = "Password must be at least 6 characters long.";
      return;
    }

    // Mock login process
    if (email === "test@example.com" && password === "password123") {
      alert("Login successful! Redirecting to your dashboard...");
      window.location.href = "/dashboard.html"; // Replace with your dashboard URL
    } else {
      errorMessage.textContent = "Invalid email or password. Please try again.";
    }
  });
});

//JavaScript for Sign Up Page
document.addEventListener("DOMContentLoaded", () => {
  // Select form elements
  const form = document.getElementById("form");
  const usernameInput = document.getElementById("username-input");
  const emailInput = document.getElementById("email-input");
  const passwordInput = document.getElementById("password-input");
  const repeatPasswordInput = document.getElementById("repeat-password");
  const errorMessage = document.getElementById("error-message");

  // Password visibility toggle
  function togglePassword() {
    const passwordInput = document.getElementById('password-input');
    const toggleButton = document.querySelector('.toggle-password i');
    
    if (passwordInput.type === 'password') {
      passwordInput.type = 'text';
      toggleButton.className = 'eye-slash-icon';
    } else {
      passwordInput.type = 'password';
      toggleButton.className = 'eye-icon';
    }
  }

  // Add click event listener to the toggle button
  document.querySelector('.toggle-password').addEventListener('click', togglePassword);

  // Validation functions
  function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  function validateUsername(username) {
    return username.length >= 3 && 
           username.length <= 20 && 
           /^[a-zA-Z0-9_]+$/.test(username);
  }

  function validatePassword(password) {
    return password.length >= 6;
  }

  // Form submission handler
  form.addEventListener("submit", (e) => {
    e.preventDefault();
    
    // Reset error message
    errorMessage.textContent = "";
    errorMessage.style.color = "red";

    // Get input values
    const username = usernameInput.value.trim();
    const email = emailInput.value.trim();
    const password = passwordInput.value.trim();
    const repeatPassword = repeatPasswordInput.value.trim();

    // Validate username
    if (!validateUsername(username)) {
      errorMessage.textContent = "Username must be 3-20 characters and contain only letters, numbers, and underscores.";
      return;
    }

    // Validate email
    if (!validateEmail(email)) {
      errorMessage.textContent = "Please enter a valid email address.";
      return;
    }

    // Validate password
    if (!validatePassword(password)) {
      errorMessage.textContent = "Password must be at least 6 characters long.";
      return;
    }

    // Validate password match
    if (password !== repeatPassword) {
      errorMessage.textContent = "Passwords do not match.";
      return;
    }

    // If all validations pass
    alert("Signup successful!");
    form.reset();
  });
});
