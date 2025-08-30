document.addEventListener("DOMContentLoaded", () => {
    // Form elements
    const form = document.getElementById("form");
    const emailInput = document.getElementById("email-input");
    const passwordInput = document.getElementById("password-input");
    const repeatPasswordInput = document.getElementById("repeat-password-input");
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
    document.querySelector('.toggle-password')
      .addEventListener('click', togglePassword);
  
    // Validation functions

    function validateEmail(email) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      
      // Basic email validation
      if (!emailRegex.test(email)) {
        return false;
      }
  
      // Add your company domain validation here
      // Example: const allowedDomains = ['company.com', 'admin.company.com'];
      // return allowedDomains.some(domain => email.endsWith('@' + domain));
      
      return true;
    }
  
    function validatePassword(password) {
      // Password requirements:
      // - At least 8 characters
      // - At least one uppercase letter
      // - At least one lowercase letter
      // - At least one number
      // - At least one special character
      const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
      return passwordRegex.test(password);
    }
  
    function displayError(message) {
      errorMessage.textContent = message;
      errorMessage.style.color = "red";
    }
  
    // Form submission handler
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      errorMessage.textContent = "";
  
      const email = emailInput.value.trim();
      const password = passwordInput.value.trim();
      const repeatPassword = repeatPasswordInput.value.trim();
  
      // Validation checks

      if (!validateEmail(email)) {
        displayError("Please use your official admin email address.");
        return;
      }
  
      if (!validatePassword(password)) {
        displayError(
          "Password must be at least 8 characters long and include uppercase, " +
          "lowercase, numbers, and special characters."
        );
        return;
      }
  
      if (password !== repeatPassword) {
        displayError("Passwords do not match.");
        return;
      }
  
      try {
        alert("Admin signup successful! Awaiting approval from super admin.");
        form.reset();
        
      } catch (error) {
        displayError("An error occurred during signup. Please try again later.");
        console.error("Signup error:", error);
      }
    });
  });