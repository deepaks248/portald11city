(function (Drupal, once) {

  Drupal.behaviors.modalToggle = {
    attach(context) {
      attachEngageButtons(context);
      attachCloseButtons(context);
    }
  };

  function attachEngageButtons(context) {
    const buttons = once('modal-engage', '[engage-button]', context);

    buttons.forEach((button) => {
      button.addEventListener('click', () => {
        toggleModal(button.dataset.modalToggle, true);
      });
    });
  }

  function attachCloseButtons(context) {
    const buttons = once('modal-hide', '[data-modal-hide]', context);

    buttons.forEach((button) => {
      button.addEventListener('click', () => {
        toggleModal(button.dataset.modalHide, false);
      });
    });
  }

  function toggleModal(modalId, show) {
    const modal = document.getElementById(modalId);

    if (!modal) {
      console.warn('Modal with ID', modalId, 'not found.');
      return;
    }

    modal.classList.toggle('hidden', !show);
    modal.classList.toggle('flex', show);
  }

})(Drupal, once);

(function ($, Drupal, once) {
  Drupal.behaviors.scrollBelowBanner = {
    attach(context) {
      if (globalThis.location.pathname === '/') {
        return;
      }

      once('scroll-banner', 'body', context).forEach(() => {
        setTimeout(() => {
          const target = $('#block-engage-theme-homepagesliderbannerblock');
          if (!target.length) {
            return;
          }

          const scrollTo = target.offset().top + target.outerHeight();
          $('html, body').animate({ scrollTop: scrollTo }, 800);
        }, 500);
      });
    }
  };
})(jQuery, Drupal, once);
