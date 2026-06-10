// Password Checklist Live Validation
function updatePasswordChecklist(inputId, checklistId) {
    const password = document.getElementById(inputId).value;
    const checklist = document.getElementById(checklistId);
    if (!checklist) return;
    checklist.querySelector('[data-type="lower"] i').className = /[a-z]/.test(password) ? 'fas fa-check' : 'fas fa-times';
    checklist.querySelector('[data-type="upper"] i').className = /[A-Z]/.test(password) ? 'fas fa-check' : 'fas fa-times';
    checklist.querySelector('[data-type="number"] i').className = /[0-9]/.test(password) ? 'fas fa-check' : 'fas fa-times';
}
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('newPassword');
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            updatePasswordChecklist('newPassword', 'password-checklist');
        });
        updatePasswordChecklist('newPassword', 'password-checklist');
    }
});
