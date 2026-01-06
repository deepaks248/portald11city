
(function (Drupal) {
  Drupal.behaviors.modalToggle = {
    attach(context, settings) {

      // Engage buttons
      const engageButtons = once('modal-engage', '[engage-button]', context);
      for (const button of engageButtons) {
        button.addEventListener('click', function () {
          const modalId = this.dataset.modalToggle;
          const modal = document.getElementById(modalId);

          if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
          } else {
            console.warn('Modal with ID', modalId, 'not found.');
          }
        });
      }

      // Close buttons
      const hideButtons = once('modal-hide', '[data-modal-hide]', context);
      for (const closeBtn of hideButtons) {
        closeBtn.addEventListener('click', function () {
          const targetId = this.dataset.modalHide;
          const targetModal = document.getElementById(targetId);

          if (targetModal) {
            targetModal.classList.add('hidden');
            targetModal.classList.remove('flex');
          }
        });
      }
    },
  };
})(Drupal);

document.addEventListener("DOMContentLoaded", function () {
  // Select all engage buttons
  const engageButtons = document.querySelectorAll("[engage-button]");

  for (const button of engageButtons) {
    button.addEventListener('click', function () {
      const modalId = this.dataset.modalToggle;
      const modal = document.getElementById(modalId);

      if (modal) {
        // Show the modal (remove hidden, add flex for Tailwind layout)
        modal.classList.remove('hidden');
        modal.classList.add('flex');
      } else {
        console.warn('Modal with ID', modalId, 'not found.');
      }
    });
  }

  // Handle close buttons with [data-modal-hide]
  const hideButtons = document.querySelectorAll("[data-modal-hide]");
  for (const closeBtn of hideButtons) {
    closeBtn.addEventListener('click', function () {
      const targetId = this.dataset.modalHide;
      const targetModal = document.getElementById(targetId);

      if (targetModal) {
        targetModal.classList.add('hidden');
        targetModal.classList.remove('flex');
      }
    });
  }
});

(function (Drupal) {
  Drupal.behaviors.faqToggle = {
    attach: function (context, settings) {
      const faqItems = once('faqToggle', '.collapse-arrow', context);

      for (const item of faqItems) {
        const title = item.querySelector('.collapse-title');
        const content = item.querySelector('.collapse-content');

        if (title && content) {
          // Set initial state
          content.style.maxHeight = '0px';
          content.style.overflow = 'hidden';
          content.style.transition = 'max-height 0.3s ease';

          title.addEventListener('click', () => {
            const isActive = item.classList.contains('active');

            // Collapse all
            for (const i of faqItems) {
              i.classList.remove('active');
              const c = i.querySelector('.collapse-content');
              if (c) c.style.maxHeight = '0px';
            }

            // Expand clicked one
            if (!isActive) {
              item.classList.add('active');
              content.style.maxHeight = content.scrollHeight + 'px';
            }
          });
        }
      }
    }
  };
})(Drupal);

(function (Drupal) {
  Drupal.behaviors.floatingLabels = {
    attach: function (context, settings) {
      const inputs = document.querySelectorAll(
        '.js-form-item input, .js-form-item select'
      );

      for (const input of inputs) {
        const label = input.closest('.js-form-item')?.querySelector('label');

        if (label && !label.classList.contains('no-float-label')) {
          input.addEventListener('focus', () => {
            label.classList.add('floating');
          });
          input.addEventListener('blur', () => {
            if (!input.value) {
              label.classList.remove('floating');
            }
          });
          // Trigger once on load
          if (input.value) {
            label.classList.add('floating');
          }
        }
      }
    }
  };
})(Drupal);

(function ($, Drupal) {
  Drupal.behaviors.scrollBelowBanner = {
    attach: function (context, settings) {
      if (globalThis.location.pathname !== '/') {
        setTimeout(function () {
          const target = $('#block-engage-theme-homepagesliderbannerblock', context);
          if (target.length) {
            const scrollTo = target.offset().top + target.outerHeight();
            $('html, body').animate({
              scrollTop: scrollTo
            }, 800);
          }
        }, 500);
      }
    }
  };
})(jQuery, Drupal);

(function (Drupal) {
  Drupal.behaviors.mobileMenuToggle = {
    attach(context) {
      const toggle = context.querySelector('#menu-toggle');
      const close = context.querySelector('#menu-close');
      const mobileMenu = context.querySelector('#mobile-menu');
      const backdrop = context.querySelector('#backdrop');

      if (!toggle || !close || !mobileMenu || !backdrop) return;

      // Toggle open
      for (const toggleBtn of once('mobile-menu-toggle', toggle)) {
        toggleBtn.addEventListener('click', () => {
          mobileMenu.classList.remove('translate-x-full');
          mobileMenu.classList.add('translate-x-0');
          backdrop.classList.remove('hidden');
        });
      }

      // Close via close button
      for (const closeBtn of once('mobile-menu-close', close)) {
        closeBtn.addEventListener('click', () => {
          mobileMenu.classList.add('translate-x-full');
          mobileMenu.classList.remove('translate-x-0');
          backdrop.classList.add('hidden');
        });
      }

      // Close via backdrop
      for (const backdropEl of once('mobile-menu-backdrop', backdrop)) {
        backdropEl.addEventListener('click', () => {
          mobileMenu.classList.add('translate-x-full');
          mobileMenu.classList.remove('translate-x-0');
          backdrop.classList.add('hidden');
        });
      }
    }
  };
})(Drupal);

(function (Drupal, once) {
  Drupal.behaviors.candyMenuToggle = {
    attach: function (context, settings) {
      // 'once' ensures it runs only once per element
      const buttons = once('candyMenu', '#candy-button', context);
      for (const button of buttons) {
        const menu = document.getElementById('candy-menu');
        const wrapper = document.getElementById('candy-wrapper');

        if (!menu || !wrapper) {
          continue; // exit this iteration if menu or wrapper does not exist
        }

        // Toggle on button click
        button.addEventListener('click', function (e) {
          e.stopPropagation();
          menu.classList.toggle('hidden');
        });

        // Click outside closes menu
        document.addEventListener('click', function (e) {
          if (!wrapper.contains(e.target)) {
            menu.classList.add('hidden');
          }
        });
      }
    }
  };
})(Drupal, once);