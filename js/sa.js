document.addEventListener('DOMContentLoaded', function() {
    // Check for status parameters in URL
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    
    if (status === 'success') {

    } else if (status === 'error') {
    }

    // Function to show status messages
    function showMessage(message, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `alert alert-${type}`;
        messageDiv.textContent = message;
        
        const container = document.querySelector('.container');
        if (container) {
            container.insertBefore(messageDiv, container.firstChild);
        }
        
        // Remove message after 5 seconds
        setTimeout(() => {
            messageDiv.remove();
        }, 5000);
    }

    // Function to handle checkbox and select field dependency
    function handleCheckboxSelect(checkboxId, selectId) {
        const checkbox = document.getElementById(checkboxId);
        const select = document.getElementById(selectId);

        if (checkbox && select) {
            checkbox.addEventListener('change', function() {
                select.disabled = !this.checked;
                if (!this.checked) {
                    select.value = '';
                }
            });
        }
    }

    // Apply event listeners to each checkbox-select pair
    handleCheckboxSelect('academicIssues', 'academicIssueType');
    handleCheckboxSelect('volunteer', 'volunteerType');
    handleCheckboxSelect('referred', 'referralSource');

    // Form validation
    const form = document.getElementById('appointmentForm');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const errorMessages = [];

            // Validation checks for each checkbox-select pair
            const validationPairs = [
                { checkbox: 'academicIssues', select: 'academicIssueType', message: 'Please select an academic issue type' },
                { checkbox: 'volunteer', select: 'volunteerType', message: 'Please select a volunteer type' },
                { checkbox: 'referred', select: 'referralSource', message: 'Please select a referral source' }
            ];

            validationPairs.forEach(pair => {
                const checkbox = document.getElementById(pair.checkbox);
                const select = document.getElementById(pair.select);
                
                if (checkbox?.checked && (!select || !select.value)) {
                    isValid = false;
                    errorMessages.push(pair.message);
                }
            });

            // Display error messages if any
            if (!isValid) {
                e.preventDefault();
                alert(errorMessages.join('\n'));
            }
        });
    }
});
