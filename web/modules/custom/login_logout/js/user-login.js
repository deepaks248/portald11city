console.log("Hello user")
document.addEventListener("DOMContentLoaded", function () {
  // Select the button
  const toggleBtn = document.querySelector('button[aria-label="Toggle password visibility"]');

  // Get the password input and the two icons
  const passwordInput = document.querySelector('#edit-password');
  const hideEye = toggleBtn.querySelector('.hide-eye-old');
  const showEye = toggleBtn.querySelector('.show-eye-old');

  toggleBtn.addEventListener('click', function () {
    // Toggle input type
    const isPassword = passwordInput.type === 'password';
    passwordInput.type = isPassword ? 'text' : 'password';

    // Toggle icon visibility
    if (isPassword) {
      hideEye.classList.add('hidden');
      showEye.classList.remove('hidden');
    } else {
      hideEye.classList.remove('hidden');
      showEye.classList.add('hidden');
    }
  });
});
