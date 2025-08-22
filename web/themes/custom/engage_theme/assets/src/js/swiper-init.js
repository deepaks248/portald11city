(function (Drupal, once) {
  Drupal.behaviors.swiperInit = {
    attach: function (context) {
      // Init for .mySwiper
      once('swiper-init', '.mySwiper.swiper', context).forEach(function (el) {
        new Swiper(el, {
          spaceBetween: 1,
          slidesPerView: 1,
          autoplay: {
            delay: 2500,
            disableOnInteraction: false,
          },
          breakpoints: {
            640: { slidesPerView: 1, spaceBetween: 2 },
            768: { slidesPerView: 2, spaceBetween: 4 },
            1024: { slidesPerView: 3, spaceBetween: 30 },
          },
          pagination: {
            el: ".swiper-pagination",
            clickable: true,
          },
        });
      });

      // Init for .gallerySwiper
      once('gallery-swiper-init', '.gallerySwiper.swiper', context).forEach(function (el) {
        new Swiper(el, {
          slidesPerView: 'auto',
          spaceBetween: 10,
          loop: true,
          autoplay: false,
          navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
          },
          pagination: {
            el: '.swiper-pagination',
            clickable: true,
          },
        });
      });

    }
  };
})(Drupal, once);
