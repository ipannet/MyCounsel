// Globals to track the current state
let currentAppointmentData = null;
let currentTab = 'all';
let appointments = [];

/**
 * Opens the edit modal and populates it with appointment data
 * @param {HTMLElement} button - The edit button that was clicked
 */
function openEditModal(button) {
    // Get the parent row of the button
    const row = button.closest('tr');
    const appointmentId = row.getAttribute('data-id');
    
    // Fetch all data from the row
    const appointmentDate = row.cells[0].textContent.trim();
    const studentName = row.cells[1].textContent.trim();
    const studentId = row.cells[2].textContent.trim();
    const phoneNumber = row.cells[3].textContent.trim();
    const studentEmail = row.cells[4].textContent.trim();
    const status = row.cells[6].querySelector('.status-badge').textContent.trim();
    
    // Parse issue categories from the row
    const issueCategories = parseIssueCategories(row.cells[5]);
    
    // Store the current appointment data
    currentAppointmentData = {
        appointmentId,
        appointmentDate,
        studentName,
        studentId,
        phoneNumber,
        studentEmail,
        status,
        ...issueCategories
    };
    
    // Populate the modal form with appointment data
    populateModalForm(currentAppointmentData);
    
    // Show the modal
    document.getElementById('editModal').style.display = 'block';
}

/**
 * Parses issue categories from the issue categories cell
 * @param {HTMLElement} cell - The cell containing issue categories
 * @returns {Object} - Object containing issue category information
 */
function parseIssueCategories(cell) {
    const issueTags = cell.querySelectorAll('.issue-tag');
    const issueCategories = {
        academicIssues: false,
        academicIssueType: '',
        mentalHealth: false,
        volunteer: false,
        volunteerType: '',
        referred: false,
        referralSource: ''
    };
    
    issueTags.forEach(tag => {
        const text = tag.textContent.trim();
        
        if (text.startsWith('Academic')) {
            issueCategories.academicIssues = true;
            // Extract the academic issue type from between parentheses if present
            const match = text.match(/\(([^)]+)\)/);
            if (match) {
                issueCategories.academicIssueType = match[1];
            }
        }
        
        if (text.startsWith('Mental Health')) {
            issueCategories.mentalHealth = true;
        }
        
        if (text.startsWith('Volunteer')) {
            issueCategories.volunteer = true;
            // Extract the volunteer type from between parentheses if present
            const match = text.match(/\(([^)]+)\)/);
            if (match) {
                issueCategories.volunteerType = match[1];
            }
        }
        
        if (text.startsWith('Referred')) {
            issueCategories.referred = true;
            // Extract the referral source from between parentheses if present
            const match = text.match(/\(([^)]+)\)/);
            if (match) {
                issueCategories.referralSource = match[1];
            }
        }
    });
    
    return issueCategories;
}

/**
 * Formats a date string into the format required by datetime-local input
 * @param {string} dateString - The date string to format
 * @returns {string} - Formatted date string (YYYY-MM-DDTHH:MM)
 */
function formatDateForInput(dateString) {
    // Parse the date string
    const date = parseAppointmentDate(dateString);
    
    // Format the date as YYYY-MM-DDTHH:MM (required by datetime-local input)
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

/**
 * Populates the modal form with appointment data
 * @param {Object} data - The appointment data
 */
function populateModalForm(data) {
    // Populate basic information
    document.getElementById('modalAppointmentId').value = data.appointmentId;
    document.getElementById('modalStudentName').value = data.studentName;
    document.getElementById('modalStudentId').value = data.studentId;
    document.getElementById('modalPhoneNumber').value = data.phoneNumber;
    document.getElementById('modalStudentEmail').value = data.studentEmail;
    document.getElementById('modalStatus').value = data.status;
    
    // Format and set the appointment date and time
    document.getElementById('modalAppointmentDateTime').value = formatDateForInput(data.appointmentDate);
    
    // Populate checkboxes and associated selects for issues
    const academicIssuesCheckbox = document.getElementById('modalAcademicIssues');
    const academicIssueTypeSelect = document.getElementById('modalAcademicIssueType');
    academicIssuesCheckbox.checked = data.academicIssues;
    academicIssueTypeSelect.value = data.academicIssueType;
    academicIssueTypeSelect.disabled = !data.academicIssues;
    
    document.getElementById('modalMentalHealth').checked = data.mentalHealth;
    
    const volunteerCheckbox = document.getElementById('modalVolunteer');
    const volunteerTypeSelect = document.getElementById('modalVolunteerType');
    volunteerCheckbox.checked = data.volunteer;
    volunteerTypeSelect.value = data.volunteerType;
    volunteerTypeSelect.disabled = !data.volunteer;
    
    const referredCheckbox = document.getElementById('modalReferred');
    const referralSourceSelect = document.getElementById('modalReferralSource');
    referredCheckbox.checked = data.referred;
    referralSourceSelect.value = data.referralSource;
    referralSourceSelect.disabled = !data.referred;
}

/**
 * Closes the edit modal
 */
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
    currentAppointmentData = null;
}

/**
 * Handles the submission of the edit form
 * @param {Event} event - The form submission event
 */
function handleEditFormSubmit(event) {
    // Validation can be added here if needed
    return true; // Allow the form to submit
}

/**
 * Toggles the enabled state of a select based on its corresponding checkbox
 * @param {HTMLElement} checkbox - The checkbox element
 * @param {string} selectId - The ID of the select element to toggle
 */
function toggleSelect(checkbox, selectId) {
    const select = document.getElementById(selectId);
    select.disabled = !checkbox.checked;
    
    // Clear the select value if the checkbox is unchecked
    if (!checkbox.checked) {
        select.value = '';
    }
}

/**
 * Parse appointment date string into a Date object
 * Handles different date formats including those with time components
 * @param {string} dateString - The date string to parse
 * @returns {Date} - Parsed date object
 */
function parseAppointmentDate(dateString) {
    // Try to detect the format and parse accordingly
    if (!dateString) return new Date(); // Default to current date if empty
    
    // Common formats to try
    const formats = [
        // Try direct Date parsing first
        () => new Date(dateString),
        // Format: YYYY-MM-DD HH:MM:SS
        () => {
            const match = dateString.match(/(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})/);
            if (match) {
                return new Date(match[1], match[2] - 1, match[3], match[4], match[5], match[6]);
            }
            return null;
        },
        // Format: MM/DD/YYYY HH:MM:SS
        () => {
            const match = dateString.match(/(\d{2})\/(\d{2})\/(\d{4}) (\d{2}):(\d{2}):(\d{2})/);
            if (match) {
                return new Date(match[3], match[1] - 1, match[2], match[4], match[5], match[6]);
            }
            return null;
        },
        // Format: DD/MM/YYYY HH:MM:SS
        () => {
            const match = dateString.match(/(\d{2})\/(\d{2})\/(\d{4}) (\d{2}):(\d{2}):(\d{2})/);
            if (match) {
                return new Date(match[3], match[2] - 1, match[1], match[4], match[5], match[6]);
            }
            return null;
        },
        // Format: Month DD, YYYY - H:MM AM/PM
        () => {
            const match = dateString.match(/([A-Za-z]{3})\s+(\d{1,2}),\s+(\d{4})\s+-\s+(\d{1,2}):(\d{2})\s+(AM|PM)/i);
            if (match) {
                const months = {"Jan":0,"Feb":1,"Mar":2,"Apr":3,"May":4,"Jun":5,"Jul":6,"Aug":7,"Sep":8,"Oct":9,"Nov":10,"Dec":11};
                const month = months[match[1]];
                const day = parseInt(match[2], 10);
                const year = parseInt(match[3], 10);
                let hour = parseInt(match[4], 10);
                const minute = parseInt(match[5], 10);
                const isPM = match[6].toUpperCase() === 'PM';
                
                if (isPM && hour < 12) hour += 12;
                if (!isPM && hour === 12) hour = 0;
                
                return new Date(year, month, day, hour, minute);
            }
            return null;
        },
        // Format: YYYY-MM-DD
        () => {
            const match = dateString.match(/(\d{4})-(\d{2})-(\d{2})/);
            if (match) {
                return new Date(match[1], match[2] - 1, match[3]);
            }
            return null;
        },
        // Format: MM/DD/YYYY
        () => {
            const match = dateString.match(/(\d{2})\/(\d{2})\/(\d{4})/);
            if (match) {
                return new Date(match[3], match[1] - 1, match[2]);
            }
            return null;
        },
        // Format: DD/MM/YYYY
        () => {
            const match = dateString.match(/(\d{2})\/(\d{2})\/(\d{4})/);
            if (match) {
                return new Date(match[3], match[2] - 1, match[1]);
            }
            return null;
        }
    ];
    
    // Try each format until one works
    for (const format of formats) {
        const date = format();
        if (date && !isNaN(date.getTime())) {
            return date;
        }
    }
    
    // If all attempts fail, return current date
    console.warn('Could not parse date string:', dateString);
    return new Date();
}

/**
 * Apply client-side filters for quick filters and search
 */
function filterAppointments() {
    const rows = document.getElementById('appointmentsTable').querySelectorAll('tbody tr');
    const searchTerm = document.querySelector('.search-input')?.value.toLowerCase() || '';
    
    // Get current date with time set to beginning of day for proper comparison
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    
    // Get active filter tags
    const activeFilters = Array.from(document.querySelectorAll('.filter-chip.active'))
        .map(chip => chip.getAttribute('data-filter'));
    
    rows.forEach(row => {
        if (row.cells.length <= 1) return; // Skip empty message rows
        
        const cells = row.getElementsByTagName('td');
        // Parse the date string from the appointment
        const dateString = cells[0].textContent.trim();
        const appointmentDate = parseAppointmentDate(dateString);
        
        const status = cells[6].querySelector('.status-badge').textContent.trim().toLowerCase();
        const issueCategories = cells[5].textContent.trim().toLowerCase();
        
        // Apply quick filters if any are active
        let isVisible = true;
        
        if (activeFilters.length > 0) {
            // Check if row matches any of the active filters
            isVisible = activeFilters.some(filter => {
                if (filter === 'academic') {
                    return issueCategories.includes('academic');
                } else if (filter === 'mental') {
                    return issueCategories.includes('mental health');
                } else if (filter === 'volunteer') {
                    return issueCategories.includes('volunteer');
                } else if (filter === 'referred') {
                    return issueCategories.includes('referred');
                }
                return false;
            });
        }
        
        // Apply search filter if there's a search term
        if (isVisible && searchTerm) {
            isVisible = false;
            // Check each cell in the row
            for (let i = 0; i < cells.length; i++) {
                if (cells[i].textContent.toLowerCase().includes(searchTerm)) {
                    isVisible = true;
                    break;
                }
            }
        }
        
        // Show or hide the row
        row.style.display = isVisible ? '' : 'none';
    });
}

/**
 * Initialize quick filter chips
 */
function initializeQuickFilters() {
    document.querySelectorAll('.filter-chip').forEach(chip => {
        chip.addEventListener('click', function(e) {
            e.preventDefault();
            // Toggle active state
            this.classList.toggle('active');
            
            // Apply filters
            filterAppointments();
        });
    });
}

/**
 * Export the appointments table to Excel (CSV)
 */
function exportToExcel() {
    // Get the table data
    const table = document.getElementById('appointmentsTable');
    const rows = table.querySelectorAll('tbody tr:not([style*="display: none"])');
    const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
    
    // Remove the "Actions" column
    const actionColumnIndex = headers.findIndex(header => header === "Actions");
    if (actionColumnIndex !== -1) {
        headers.splice(actionColumnIndex, 1);
    }
    
    // Create CSV content
    let csvContent = headers.join(',') + '\n';
    
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const cells = row.querySelectorAll('td');
            let rowData = [];
            
            // Process each cell, excluding the actions column
            for (let i = 0; i < cells.length; i++) {
                if (i !== actionColumnIndex && i !== actionColumnIndex + 1) { // Skip both action columns
                    // For issue categories, get the text without HTML
                    if (i === 5) { // Issue Categories column
                        const issueText = Array.from(cells[i].querySelectorAll('.issue-tag'))
                            .map(tag => tag.textContent.trim())
                            .join('; ');
                        rowData.push('"' + issueText + '"');
                    } else if (i === 6) { // Status column
                        rowData.push('"' + cells[i].querySelector('.status-badge').textContent.trim() + '"');
                    } else if (i < actionColumnIndex) {
                        // Regular cell content with quotes to handle commas
                        rowData.push('"' + cells[i].textContent.trim().replace(/"/g, '""') + '"');
                    }
                }
            }
            
            csvContent += rowData.join(',') + '\n';
        }
    });
    
    // Create a Blob and download link
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    
    link.setAttribute('href', url);
    link.setAttribute('download', 'student_appointments.csv');
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Export the appointments table to PDF
 */
function exportToPDF() {
    // Create a new window for PDF content
    const printWindow = window.open('', '_blank', 'height=600,width=800');
    
    // Get the table data
    const table = document.getElementById('appointmentsTable');
    const rows = table.querySelectorAll('tbody tr:not([style*="display: none"])');
    const headers = Array.from(table.querySelectorAll('thead th'));
    
    // Remove the Actions column
    const actionColumnIndex = headers.findIndex(th => th.textContent.trim() === "Actions");
    const filteredHeaders = headers.filter((_, index) => index !== actionColumnIndex);
    
    // Create HTML content for the PDF
    let htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Student Appointments</title>
            <style>
                body { font-family: Arial, sans-serif; }
                h1 { text-align: center; color: #3366cc; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background-color: #3366cc; color: white; padding: 8px; text-align: left; }
                td { padding: 8px; border-bottom: 1px solid #ddd; }
                tr:nth-child(even) { background-color: #f2f2f2; }
                .status-pending { color: #ff9800; }
                .status-completed { color: #4caf50; }
                .status-cancelled { color: #f44336; }
                .issue-tag { 
                    display: inline-block;
                    margin: 2px;
                    padding: 2px 5px;
                    background-color: #e1e1e1;
                    border-radius: 3px;
                    font-size: 0.9em;
                }
                .footer { 
                    text-align: center; 
                    margin-top: 20px; 
                    font-size: 0.8em; 
                    color: #666;
                }
            </style>
        </head>
        <body>
            <h1>Student Appointments</h1>
            <p>Export Date: ${new Date().toLocaleString()}</p>
            <table>
                <thead>
                    <tr>`;
    
    // Add headers except Actions
    filteredHeaders.forEach(th => {
        htmlContent += `<th>${th.textContent.trim()}</th>`;
    });
    
    htmlContent += `
                    </tr>
                </thead>
                <tbody>`;
    
    // Add rows
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const cells = row.querySelectorAll('td');
            htmlContent += '<tr>';
            
            for (let i = 0; i < cells.length; i++) {
                if (i !== actionColumnIndex && i !== actionColumnIndex + 1) { // Skip both Actions columns
                    if (i === 5) { // Issue Categories column
                        const issueTags = cells[i].querySelectorAll('.issue-tag');
                        let issues = '';
                        issueTags.forEach(tag => {
                            issues += `<span class="issue-tag">${tag.textContent.trim()}</span> `;
                        });
                        htmlContent += `<td>${issues}</td>`;
                    } else if (i === 6) { // Status column
                        const status = cells[i].querySelector('.status-badge');
                        const statusText = status.textContent.trim();
                        const statusClass = status.className.split(' ')[1]; // Get the status class (status-pending, etc.)
                        htmlContent += `<td><span class="${statusClass}">${statusText}</span></td>`;
                    } else {
                        htmlContent += `<td>${cells[i].textContent.trim()}</td>`;
                    }
                }
            }
            
            htmlContent += '</tr>';
        }
    });
    
    htmlContent += `
                </tbody>
            </table>
            <div class="footer">
                <p>MyCounsel - Student Appointment System</p>
            </div>
            <script>
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                        window.close();
                    }, 500);
                };
            </script>
        </body>
        </html>`;
    
    // Write to the new window and trigger printing
    printWindow.document.open();
    printWindow.document.write(htmlContent);
    printWindow.document.close();
}

/**
 * Initialize export functionality
 */
function initializeExport() {
    const exportPDFBtn = document.getElementById('exportPDF');
    const exportExcelBtn = document.getElementById('exportExcel');
    
    if (exportPDFBtn) {
        exportPDFBtn.addEventListener('click', exportToPDF);
    }
    
    if (exportExcelBtn) {
        exportExcelBtn.addEventListener('click', exportToExcel);
    }
}

/**
 * Initialize event listeners for the edit form
 */
function initializeEditFormListeners() {
    const modalAcademicIssues = document.getElementById('modalAcademicIssues');
    const modalVolunteer = document.getElementById('modalVolunteer');
    const modalReferred = document.getElementById('modalReferred');
    const editForm = document.getElementById('editForm');
    
    if (modalAcademicIssues) {
        modalAcademicIssues.addEventListener('change', function() {
            toggleSelect(this, 'modalAcademicIssueType');
        });
    }
    
    if (modalVolunteer) {
        modalVolunteer.addEventListener('change', function() {
            toggleSelect(this, 'modalVolunteerType');
        });
    }
    
    if (modalReferred) {
        modalReferred.addEventListener('change', function() {
            toggleSelect(this, 'modalReferralSource');
        });
    }
    
    if (editForm) {
        editForm.addEventListener('submit', handleEditFormSubmit);
    }
}

/**
 * Initialize enhanced search functionality
 */
function initializeSearch() {
    const searchInput = document.querySelector('.search-input');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterAppointments();
        });
    }
}

/**
 * Add keyboard shortcut for quick actions
 */
function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(event) {
        // Alt+N for new appointment
        if (event.altKey && event.key === 'n') {
            window.location.href = '/php/studentappointment.php';
        }
        
        // Alt+F to focus search
        if (event.altKey && event.key === 'f') {
            const searchInput = document.querySelector('.search-input');
            if (searchInput) {
                searchInput.focus();
                event.preventDefault();
            }
        }
        
        // Escape to close modal
        if (event.key === 'Escape') {
            closeEditModal();
        }
    });
}

/**
 * Update buttons after a call is made
 * @param {HTMLElement} row - The table row containing the buttons
 */
function updateButtonsAfterCall(row) {
    // Find the notification buttons container
    const notificationContainer = row.querySelector('.notification-buttons');
    if (!notificationContainer) return;
    

    // Show the Send Reminder form
    const reminderForm = notificationContainer.querySelector('form[action="send_reminder.php"]');
    if (reminderForm) {
        reminderForm.style.marginBottom = '0';
    }
    

}

/**
 * Initialize call student functionality
 */
function initializeCallStudentFunctionality() {
    // Find all call student forms
    const callStudentForms = document.querySelectorAll('form[action="call_student.php"]');
    
    callStudentForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // Get the appointment ID from the form
            const appointmentId = this.querySelector('input[name="appointment_id"]').value;
            
            // Store in sessionStorage that this appointment has been called
            sessionStorage.setItem('called_' + appointmentId, 'true');
            
            // Continue with form submission
        });
    });
    
    // Check the URL for the 'called' parameter for immediate feedback without page reload
    const urlParams = new URLSearchParams(window.location.search);
    const calledAppointmentId = urlParams.get('called');
    
    if (calledAppointmentId) {
        // Find the row with this appointment ID
        const row = document.querySelector(`tr[data-id="${calledAppointmentId}"]`);
        if (row) {
            updateButtonsAfterCall(row);
        }
    }
    
    // Check if any appointments were previously called using sessionStorage
    const appointmentRows = document.querySelectorAll('tr[data-id]');
    appointmentRows.forEach(row => {
        const appointmentId = row.getAttribute('data-id');
        // Check both sessionStorage and the 'called' parameter
        const wasCalled = sessionStorage.getItem('called_' + appointmentId) === 'true' || 
                         (calledAppointmentId && calledAppointmentId === appointmentId);
        
        if (wasCalled) {
            updateButtonsAfterCall(row);
        }
    });
}

// Initialize all features when the DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all features
    initializeEditFormListeners();
    initializeQuickFilters();
    initializeSearch();
    initializeExport();
    initializeKeyboardShortcuts();
    initializeCallStudentFunctionality();
});