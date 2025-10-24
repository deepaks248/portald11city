document.addEventListener("DOMContentLoaded", function () {

  /* =========================
   * 1️⃣ PASSWORD VISIBILITY TOGGLE
   * ========================= */
  document.querySelectorAll('button[aria-label="Toggle password visibility"]').forEach(btn => {
    const input = btn.closest('.relative').querySelector('input[type="password"], input[type="text"]');
    const hideEye = btn.querySelector('.hide-eye-old');
    const showEye = btn.querySelector('.show-eye-old');

    btn.addEventListener('click', () => {
      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';

      hideEye.classList.toggle('hidden', isPassword);
      showEye.classList.toggle('hidden', !isPassword);
    });
  });


  /* =========================
   * 2️⃣ PASSWORD VALIDATION (NEW + CONFIRM)
   * ========================= */
  const newPassInput = document.querySelector('#edit-new-password');
  const confirmPassInput = document.querySelector('#edit-confirm-password');

  const tooltipNew = document.querySelector('#password-tooltip-new');
  const tooltipConfirm = document.querySelector('#password-tooltip-confirm');

  const rulesNew = {
    length: document.querySelector('#rule-length-new'),
    uppercase: document.querySelector('#rule-uppercase-new'),
    lowercase: document.querySelector('#rule-lowercase-new'),
    number: document.querySelector('#rule-number-new'),
    special: document.querySelector('#rule-special-new')
  };

  const rulesConfirm = {
    length: document.querySelector('#rule-length-confirm'),
    uppercase: document.querySelector('#rule-uppercase-confirm'),
    lowercase: document.querySelector('#rule-lowercase-confirm'),
    number: document.querySelector('#rule-number-confirm'),
    special: document.querySelector('#rule-special-confirm'),
    match: document.querySelector('#passmatch')
  };

  // ✅ Helper to toggle colors (red ⇄ green)
  function setRuleColor(el, valid) {
    el.classList.toggle('text-green-600', valid);
    el.classList.toggle('text-red-600', !valid);
  }

  // ✅ Validate password strength
  function validatePassword(password, rules) {
    const validations = {
      length: password.length >= 8,
      uppercase: /[A-Z]/.test(password),
      lowercase: /[a-z]/.test(password),
      number: /[0-9]/.test(password),
      special: /[@!$#%^&*]/.test(password)
    };

    for (const [key, el] of Object.entries(rules)) {
      if (validations[key] !== undefined) setRuleColor(el, validations[key]);
    }

    return Object.values(validations).every(Boolean);
  }

  // ✅ Handle New Password typing
  newPassInput?.addEventListener('input', () => {
    validatePassword(newPassInput.value, rulesNew);

    // Show tooltip while typing
    tooltipNew.classList.remove('invisible', 'opacity-0');
    if (newPassInput.value === '') {
      tooltipNew.classList.add('invisible', 'opacity-0');
    }

    // Re-validate confirm field if user changes new password
    validateConfirmPassword();
  });

  // ✅ Handle Confirm Password typing
  confirmPassInput?.addEventListener('input', validateConfirmPassword);

  function validateConfirmPassword() {
    const newPass = newPassInput.value;
    const confirmPass = confirmPassInput.value;

    // Validate confirm field strength (optional)
    validatePassword(confirmPass, rulesConfirm);

    // Check match
    const match = newPass === confirmPass && confirmPass !== '';
    setRuleColor(rulesConfirm.match, match);

    tooltipConfirm.classList.remove('invisible', 'opacity-0');
    if (confirmPass === '') {
      tooltipConfirm.classList.add('invisible', 'opacity-0');
    }
  }

  // ✅ Hide tooltips on blur
  [newPassInput, confirmPassInput].forEach(input => {
    input?.addEventListener('blur', () => {
      setTimeout(() => {
        tooltipNew.classList.add('invisible', 'opacity-0');
        tooltipConfirm.classList.add('invisible', 'opacity-0');
      }, 300);
    });
  });

});
