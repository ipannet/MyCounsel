document.addEventListener("DOMContentLoaded", () => {
    // Form elements
    const form = document.getElementById("form");
    const emailInput = document.getElementById("email-input");
    const passwordInput = document.getElementById("password-input");
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
  
      // Add your company domain validation here if needed
      // Example: const allowedDomains = ['company.com', 'admin.company.com'];
      // return allowedDomains.some(domain => email.endsWith('@' + domain));
      
      return true;
    }
  
    function displayError(message) {
      errorMessage.textContent = message;
      errorMessage.style.color = "red";
      // Shake animation for error feedback
      form.classList.add('shake');
      setTimeout(() => form.classList.remove('shake'), 500);
    }
  
    // Form submission handler
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      errorMessage.textContent = "";
  
      const email = emailInput.value.trim();
      const password = passwordInput.value.trim();
  
      // Basic validation
      if (!validateEmail(email)) {
        displayError("Please enter a valid admin email address.");
        return;
      }
  
      if (!password) {
        displayError("Please enter your password.");
        return;
      }
  
      try {

        console.log('Login attempt with:', { email });
        
        // Simulate API call delay
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        // Redirect to admin dashboard
        window.location.href = '/admin/dashboard.php';
        
      } catch (error) {
        console.error('Login error:', error);
        displayError("An error occurred during login. Please try again later.");
      }
    });
  
    // Add some basic security features
    // Prevent multiple rapid login attempts
    let lastSubmitTime = 0;
    const SUBMIT_DELAY = 2000; // 2 seconds
  
    form.addEventListener("submit", (e) => {
      const now = Date.now();
      if (now - lastSubmitTime < SUBMIT_DELAY) {
        e.preventDefault();
        displayError("Please wait a moment before trying again.");
        return;
      }
      lastSubmitTime = now;
    });
  
    // Clear sensitive data when navigating away
    window.addEventListener('beforeunload', () => {
      passwordInput.value = '';
      // Clear any stored sensitive data
    });
  });