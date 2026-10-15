document.addEventListener('DOMContentLoaded', function () {
    const passwordField = document.getElementById('password');
    const toggleButton = document.getElementById('passwordToggle');
    const loginForm = document.getElementById('cmmpLoginForm');
    const loginSubmit = document.getElementById('loginSubmit');

    if (passwordField && toggleButton) {
        toggleButton.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            const visible = passwordField.type === 'text';
            passwordField.type = visible ? 'password' : 'text';
            toggleButton.setAttribute('aria-pressed', String(!visible));
            toggleButton.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');

            const icon = toggleButton.querySelector('i');
            if (icon) {
                icon.className = visible ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
            }
        });
    }

    if (loginForm && loginSubmit) {
        loginForm.addEventListener('submit', function () {
            if (!loginSubmit.disabled) {
                loginSubmit.disabled = true;
                loginSubmit.innerHTML = '<span class="cmmp-auth-spinner me-2"><i class="fa-solid fa-spinner fa-spin"></i></span>Signing in...';
            }
        });
    }
});
