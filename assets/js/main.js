// Infrastructure Maintenance Reporting System (IMRS) - Main JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
    
    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#ef4444';
                } else {
                    field.style.borderColor = '';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    });
    
    // Password confirmation validation
    const passwordForms = document.querySelectorAll('form');
    passwordForms.forEach(form => {
        const newPassword = form.querySelector('#new_password');
        const confirmPassword = form.querySelector('#confirm_password');
        
        if (newPassword && confirmPassword) {
            confirmPassword.addEventListener('input', function() {
                if (newPassword.value && confirmPassword.value) {
                    if (newPassword.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Passwords do not match');
                        confirmPassword.style.borderColor = '#ef4444';
                    } else {
                        confirmPassword.setCustomValidity('');
                        confirmPassword.style.borderColor = '';
                    }
                }
            });
        }
    });
    
    // File upload preview
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const files = e.target.files;
            if (files.length > 0) {
                const maxSize = 5 * 1024 * 1024; // 5MB
                let hasError = false;
                
                Array.from(files).forEach(file => {
                    if (file.size > maxSize) {
                        alert(`File "${file.name}" exceeds the maximum size of 5MB.`);
                        hasError = true;
                    }
                });
                
                if (hasError) {
                    input.value = '';
                }
            }
        });
    });
    
    // User dropdown removed — static user display used instead.
    
    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('[data-confirm]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm');
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    // Auto-refresh notifications count (every 30 seconds)
    if (document.querySelector('.notification-icon')) {
        const baseUrl = window.location.origin + window.location.pathname.split('/').slice(0, -1).join('/');
        setInterval(function() {
            fetch(baseUrl + '/api/notifications_count.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.querySelector('.badge-count');
                    if (data.count > 0) {
                        if (badge) {
                            badge.textContent = data.count;
                        } else {
                            const icon = document.querySelector('.notification-icon');
                            const newBadge = document.createElement('span');
                            newBadge.className = 'badge-count';
                            newBadge.textContent = data.count;
                            icon.appendChild(newBadge);
                        }
                    } else if (badge) {
                        badge.remove();
                    }
                })
                .catch(error => console.error('Error fetching notifications:', error));
        }, 30000);
    }
});

// Utility function to format dates
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Utility function to show loading state
function showLoading(element) {
    if (element) {
        element.disabled = true;
        element.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    }
}

// Utility function to hide loading state
function hideLoading(element, originalText) {
    if (element) {
        element.disabled = false;
        element.innerHTML = originalText;
    }
}

