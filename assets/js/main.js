// assets/js/main.js - Global JavaScript functions

// Function to show notifications
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    notification.style.zIndex = '9999';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
}

// Function to format date
function formatDate(date) {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// Function to validate numeric input
function validateNumericInput(input) {
    return !isNaN(parseFloat(input)) && isFinite(input) && parseFloat(input) >= 0;
}

// Function to update progress bar
function updateProgressBar(current, goal, elementId) {
    const percentage = Math.min((current / goal) * 100, 100);
    const progressBar = document.getElementById(elementId);
    if (progressBar) {
        progressBar.style.width = `${percentage}%`;
        progressBar.textContent = `${Math.round(percentage)}%`;
        progressBar.setAttribute('aria-valuenow', percentage);
    }
}

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            if (!alert.classList.contains('alert-permanent')) {
                alert.remove();
            }
        });
    }, 5000);
});