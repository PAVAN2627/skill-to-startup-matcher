// Form validation functions
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validatePassword(password) {
    return password.length >= 6;
}

function validatePhone(phone) {
    const re = /^[0-9]{10}$/;
    return re.test(phone.replace(/\D/g, ''));
}

// Show/hide password functionality
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);
}

// Real-time form validation
function setupFormValidation() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                clearError(this);
            });
        });
        
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            inputs.forEach(input => {
                if (!validateField(input)) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showAlert('Please fix the errors below', 'danger');
            }
        });
    });
}

function validateField(field) {
    const value = field.value.trim();
    const type = field.type;
    const required = field.hasAttribute('required');
    
    clearError(field);
    
    if (required && !value) {
        showFieldError(field, 'This field is required');
        return false;
    }
    
    if (value) {
        switch(type) {
            case 'email':
                if (!validateEmail(value)) {
                    showFieldError(field, 'Please enter a valid email address');
                    return false;
                }
                break;
            case 'password':
                if (!validatePassword(value)) {
                    showFieldError(field, 'Password must be at least 6 characters long');
                    return false;
                }
                break;
            case 'tel':
                if (!validatePhone(value)) {
                    showFieldError(field, 'Please enter a valid 10-digit phone number');
                    return false;
                }
                break;
        }
    }
    
    // Custom validations
    if (field.name === 'confirm_password') {
        const password = document.querySelector('input[name="password"]');
        if (password && value !== password.value) {
            showFieldError(field, 'Passwords do not match');
            return false;
        }
    }
    
    return true;
}

function showFieldError(field, message) {
    field.classList.add('error');
    field.style.borderColor = '#dc3545';
    
    let errorDiv = field.parentNode.querySelector('.error-message');
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.style.color = '#dc3545';
        errorDiv.style.fontSize = '0.875rem';
        errorDiv.style.marginTop = '0.25rem';
        field.parentNode.appendChild(errorDiv);
    }
    errorDiv.textContent = message;
}

function clearError(field) {
    field.classList.remove('error');
    field.style.borderColor = '';
    
    const errorDiv = field.parentNode.querySelector('.error-message');
    if (errorDiv) {
        errorDiv.remove();
    }
}

// Alert system
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="close" onclick="this.parentElement.remove()" style="float: right; background: none; border: none; font-size: 1.2rem; cursor: pointer;">&times;</button>
    `;
    
    const container = document.querySelector('.container') || document.body;
    container.insertBefore(alertDiv, container.firstChild);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// File upload handling
function setupFileUpload() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    fileInputs.forEach(input => {
        const uploadArea = input.closest('.upload-area');
        
        if (uploadArea) {
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            
            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });
            
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    input.files = files;
                    updateFileDisplay(input);
                }
            });
        }
        
        input.addEventListener('change', function() {
            updateFileDisplay(this);
        });
    });
}

function updateFileDisplay(input) {
    const file = input.files[0];
    if (file) {
        const fileName = file.name;
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        
        let display = input.parentNode.querySelector('.file-display');
        if (!display) {
            display = document.createElement('div');
            display.className = 'file-display';
            display.style.marginTop = '0.5rem';
            display.style.padding = '0.5rem';
            display.style.backgroundColor = '#f8f9fa';
            display.style.borderRadius = '4px';
            input.parentNode.appendChild(display);
        }
        
        display.innerHTML = `
            <strong>Selected:</strong> ${fileName} (${fileSize} MB)
            <button type="button" onclick="clearFile('${input.id}')" style="margin-left: 10px; background: #dc3545; color: white; border: none; padding: 2px 8px; border-radius: 3px; cursor: pointer;">Remove</button>
        `;
    }
}

function clearFile(inputId) {
    const input = document.getElementById(inputId);
    input.value = '';
    const display = input.parentNode.querySelector('.file-display');
    if (display) {
        display.remove();
    }
}

// Loading state management
function showLoading(element) {
    const originalText = element.textContent;
    element.textContent = 'Loading...';
    element.disabled = true;
    element.dataset.originalText = originalText;
}

function hideLoading(element) {
    const originalText = element.dataset.originalText || 'Submit';
    element.textContent = originalText;
    element.disabled = false;
}

// AJAX helper function
function makeRequest(url, method = 'GET', data = null) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        
        if (method === 'POST' && data) {
            if (data instanceof FormData) {
                // Don't set Content-Type for FormData, let browser set it
            } else {
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            }
        }
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        resolve(response);
                    } catch (e) {
                        resolve(xhr.responseText);
                    }
                } else {
                    reject(new Error(`HTTP ${xhr.status}: ${xhr.statusText}`));
                }
            }
        };
        
        xhr.send(data);
    });
}

// OTP input handling
function setupOTPInput() {
    const otpInputs = document.querySelectorAll('.otp-input');
    
    otpInputs.forEach((input, index) => {
        input.addEventListener('input', function() {
            if (this.value.length === 1 && index < otpInputs.length - 1) {
                otpInputs[index + 1].focus();
            }
        });
        
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && this.value === '' && index > 0) {
                otpInputs[index - 1].focus();
            }
        });
    });
}

// Auto-capitalize first letter
function capitalizeFirst(input) {
    input.value = input.value.charAt(0).toUpperCase() + input.value.slice(1);
}

// Character counter for text areas
function setupCharacterCounter() {
    const textareas = document.querySelectorAll('textarea[maxlength]');
    
    textareas.forEach(textarea => {
        const maxLength = textarea.getAttribute('maxlength');
        const counter = document.createElement('div');
        counter.className = 'character-counter';
        counter.style.textAlign = 'right';
        counter.style.fontSize = '0.875rem';
        counter.style.color = '#6c757d';
        counter.style.marginTop = '0.25rem';
        
        textarea.parentNode.appendChild(counter);
        
        function updateCounter() {
            const remaining = maxLength - textarea.value.length;
            counter.textContent = `${textarea.value.length}/${maxLength}`;
            counter.style.color = remaining < 50 ? '#dc3545' : '#6c757d';
        }
        
        textarea.addEventListener('input', updateCounter);
        updateCounter();
    });
}

// Initialize all functionality when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    setupFormValidation();
    setupFileUpload();
    setupOTPInput();
    setupCharacterCounter();
    
    // Add smooth scrolling to anchor links
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    anchorLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
});

// Search functionality for tables
function searchTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    input.addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        
        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const cells = row.getElementsByTagName('td');
            let found = false;
            
            for (let j = 0; j < cells.length; j++) {
                if (cells[j].textContent.toLowerCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
            
            row.style.display = found ? '' : 'none';
        }
    });
}

// Confirmation dialogs
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Auto-logout warning
let logoutWarningShown = false;
let logoutTimer;

function resetLogoutTimer() {
    clearTimeout(logoutTimer);
    logoutWarningShown = false;
    
    // Show warning after 25 minutes of inactivity
    logoutTimer = setTimeout(() => {
        if (!logoutWarningShown) {
            logoutWarningShown = true;
            if (confirm('You will be logged out in 5 minutes due to inactivity. Click OK to stay logged in.')) {
                resetLogoutTimer();
            } else {
                // Auto-logout after 30 minutes total
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 5 * 60 * 1000);
            }
        }
    }, 25 * 60 * 1000);
}

// Reset timer on user activity
document.addEventListener('mousemove', resetLogoutTimer);
document.addEventListener('keypress', resetLogoutTimer);
document.addEventListener('click', resetLogoutTimer);

// Initialize logout timer
resetLogoutTimer();
