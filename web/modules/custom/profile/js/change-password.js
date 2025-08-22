  document.querySelector(".hide-eye-old").addEventListener("click", (e) => {
    let x = document.getElementById("old-password");
    if (x.type === "password") {
      x.type = "text";
      document.querySelector(".show-eye-old").removeAttribute("hidden");
      document.querySelector(".hide-eye-old").setAttribute("hidden", true);
    } else {
      x.type = "password";
      document.querySelector(".hide-eye-old").removeAttribute("hidden");
      document.querySelector(".show-eye-old").setAttribute("hidden", true);
    }
  });

  document.querySelector(".show-eye-old").addEventListener("click", (e) => {
    let x = document.getElementById("old-password");
    if (x.type === "text") {
      x.type = "password";
      document.querySelector(".hide-eye-old").removeAttribute("hidden");
      document.querySelector(".show-eye-old").setAttribute("hidden", true);
    } else {
      x.type = "text";
      document.querySelector(".show-eye-old").removeAttribute("hidden");
      document.querySelector(".hide-eye-old").setAttribute("hidden", true);
    }
  });
  document.querySelector(".hide-eye-new").addEventListener("click", (e) => {
    let x = document.getElementById("new-password");
    if (x.type === "password") {
      x.type = "text";
      document.querySelector(".show-eye-new").removeAttribute("hidden");
      document.querySelector(".hide-eye-new").setAttribute("hidden", true);
    } else {
      x.type = "password";
      document.querySelector(".hide-eye-new").removeAttribute("hidden");
      document.querySelector(".show-eye-new").setAttribute("hidden", true);
    }
  });

  document.querySelector(".show-eye-new").addEventListener("click", (e) => {
    let x = document.getElementById("new-password");
    if (x.type === "text") {
      x.type = "password";
      document.querySelector(".hide-eye-new").removeAttribute("hidden");
      document.querySelector(".show-eye-new").setAttribute("hidden", true);
    } else {
      x.type = "text";
      document.querySelector(".show-eye-new").removeAttribute("hidden");
      document.querySelector(".hide-eye-new").setAttribute("hidden", true);
    }
  });
  document.querySelector(".hide-eye-con").addEventListener("click", (e) => {
    let x = document.getElementById("confirm-password");
    if (x.type === "password") {
      x.type = "text";
      document.querySelector(".show-eye-con").removeAttribute("hidden");
      document.querySelector(".hide-eye-con").setAttribute("hidden", true);
    } else {
      x.type = "password";
      document.querySelector(".hide-eye-con").removeAttribute("hidden");
      document.querySelector(".show-eye-con").setAttribute("hidden", true);
    }
  });

  document.querySelector(".show-eye-con").addEventListener("click", (e) => {
    let x = document.getElementById("confirm-password");
    if (x.type === "text") {
      x.type = "password";
      document.querySelector(".hide-eye-con").removeAttribute("hidden");
      document.querySelector(".show-eye-con").setAttribute("hidden", true);
    } else {
      x.type = "text";
      document.querySelector(".show-eye-con").removeAttribute("hidden");
      document.querySelector(".hide-eye-con").setAttribute("hidden", true);
    }
  });





 document.addEventListener('DOMContentLoaded', function () {
  const pwInput = document.getElementById('new-password');
  const tooltip = document.getElementById('password-tooltip-new');

 const rules = {
  length: {
    el: document.getElementById('rule-length-new'),
    check: v => v.length >= 8,
  },
  uppercase: {
    el: document.getElementById('rule-uppercase-new'),
    check: v => /[A-Z]/.test(v), // ✅ matches A-Z anywhere
  },
  lowercase: {
    el: document.getElementById('rule-lowercase-new'),
    check: v => /[a-z]/.test(v),
  },
  number: {
    el: document.getElementById('rule-number-new'),
    check: v => /\d/.test(v), // ✅ alternative to [0-9]
  },
  special: {
    el: document.getElementById('rule-special-new'),
    check: v => /[!@#$%^&*]/.test(v), // ✅ no backslashes needed here
  },
};


  pwInput.addEventListener('focusin', () => {
    tooltip.classList.remove('invisible', 'opacity-0');
    tooltip.classList.add('visible', 'opacity-100');
  });

  pwInput.addEventListener('focusout', () => {
    tooltip.classList.add('invisible', 'opacity-0');
    tooltip.classList.remove('visible', 'opacity-100');
  });

  pwInput.addEventListener('input', () => {
    const value = pwInput.value;
    for (const key in rules) {
      const isValid = rules[key].check(value);
      rules[key].el.classList.toggle('text-green-600', isValid);
      rules[key].el.classList.toggle('text-red-600', !isValid);
    }
  });
});



  document.addEventListener('DOMContentLoaded', function () {
  const pwInput = document.getElementById('confirm-password');
  const tooltip = document.getElementById('password-tooltip-confirm');
  const newPasswordInput = document.getElementById('new-password');

  const rules = {
    length: {
      el: document.getElementById('rule-length-confirm'),
      check: v => v.length >= 8,
    },
    uppercase: {
      el: document.getElementById('rule-uppercase-confirm'),
      check: v => /[A-Z]/.test(v),
    },
    lowercase: {
      el: document.getElementById('rule-lowercase-confirm'),
      check: v => /[a-z]/.test(v),
    },
    number: {
      el: document.getElementById('rule-number-confirm'),
      check: v => /[0-9]/.test(v),
    },
    special: {
      el: document.getElementById('rule-special-confirm'),
      check: v => /[@!$#%^&*]/.test(v),
    },
    match: {
      el: document.getElementById('passmatch'),
      check: v => v === newPasswordInput.value,
    },
  };

  pwInput.addEventListener('focusin', () => {
    tooltip.classList.remove('invisible', 'opacity-0');
    tooltip.classList.add('visible', 'opacity-100');
  });

  pwInput.addEventListener('focusout', () => {
    tooltip.classList.add('invisible', 'opacity-0');
    tooltip.classList.remove('visible', 'opacity-100');
  });

  // Validate on every input
  pwInput.addEventListener('input', () => {
    const value = pwInput.value;

    for (const key in rules) {
      const isValid = rules[key].check(value);
      rules[key].el.classList.toggle('text-green-600', isValid);
      rules[key].el.classList.toggle('text-red-600', !isValid);
    }
  });

  // Re-check match when new password changes
  newPasswordInput.addEventListener('input', () => {
    const matchEl = rules.match.el;
    const isMatch = pwInput.value === newPasswordInput.value;
    matchEl.classList.toggle('text-green-600', isMatch);
    matchEl.classList.toggle('text-red-600', !isMatch);
  });
});
