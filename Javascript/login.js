document.addEventListener('DOMContentLoaded', function() {
  const togglePassword = document.querySelector('#togglePassword');
  const password = document.querySelector('#password');

  togglePassword.addEventListener('click', function() {
      const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
      password.setAttribute('type', type);
      this.classList.toggle('fa-eye');
      this.classList.toggle('fa-eye-slash');
  });

  // Clear fields based on error type
  const urlParams = new URLSearchParams(window.location.search);
  const error = urlParams.get('error');
  
  if (error === 'account_not_found') {
      document.getElementById('email').value = '';
  } else if (error === 'incorrect_password') {
      document.getElementById('password').value = '';
  } else if (error === 'account_inactive') {
      document.getElementById('email').value = '';
      document.getElementById('password').value = '';
  }

  if (error) {
    const url = new URL(window.location);
    url.searchParams.delete('error');
    url.searchParams.delete('email');
    history.replaceState(null, '', url.pathname);
  }
});