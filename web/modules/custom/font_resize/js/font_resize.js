// (function ($, Drupal, once) {
//   Drupal.behaviors.fontResize = {
//     attach: function (context, settings) {
//       // Apply the plugin once to the <body>
//       $(once('font-resize', 'body', context)).each(function () {
//         $('body').font_resize();
//       });
//     }
//   };

//   $.fn.font_resize = function (options) {
//     var $this = $(this);
//     var defaults = {
//       btnMinusId: '#font_resize-minus',
//       btnDefaultId: '#font_resize-default',
//       btnPlusId: '#font_resize-plus',
//       btnMinusMaxHits: 10,
//       btnPlusMaxHits: 10,
//       sizeChange: 1
//     };

//     options = $.extend(defaults, options || {});

//     var limite = [];
//     var fontsize_pattern = [];

//     // Save initial font sizes
//     $this.each(function (i) {
//       limite[i] = 0;
//       fontsize_pattern[i] = parseInt($(this).css('font-size').replace('px', ''));
//     });

//     // Remove href + add cursor styling
//     $(options.btnMinusId + ', ' + options.btnDefaultId + ', ' + options.btnPlusId)
//       .removeAttr('href')
//       .css('cursor', 'pointer');

//     /* A- (decrease) */
//     $(options.btnMinusId).on('click', function (e) {
//       e.preventDefault();
//       $(options.btnPlusId).removeClass('font_resize-disabled');
//       $this.each(function (i) {
//         if (limite[i] > -options.btnMinusMaxHits) {
//           var fontsize = parseInt($(this).css('font-size').replace('px', ''));
//           $(this).css('font-size', (fontsize - options.sizeChange) + 'px');
//           limite[i]--;
//           if (limite[i] === -options.btnMinusMaxHits) {
//             $(options.btnMinusId).addClass('font_resize-disabled');
//           }
//         }
//       });
//     });

//     /* A (reset) */
//     $(options.btnDefaultId).on('click', function (e) {
//       e.preventDefault();
//       $(options.btnMinusId).removeClass('font_resize-disabled');
//       $(options.btnPlusId).removeClass('font_resize-disabled');
//       $this.each(function (i) {
//         limite[i] = 0;
//         $(this).css('font-size', fontsize_pattern[i] + 'px');
//       });
//     });

//     /* A+ (increase) */
//     $(options.btnPlusId).on('click', function (e) {
//       e.preventDefault();
//       $(options.btnMinusId).removeClass('font_resize-disabled');
//       $this.each(function (i) {
//         if (limite[i] < options.btnPlusMaxHits) {
//           var fontsize = parseInt($(this).css('font-size').replace('px', ''));
//           $(this).css('font-size', (fontsize + options.sizeChange) + 'px');
//           limite[i]++;
//           if (limite[i] === options.btnPlusMaxHits) {
//             $(options.btnPlusId).addClass('font_resize-disabled');
//           }
//         }
//       });
//     });
//   };
// })(jQuery, Drupal, once);


// (function ($, Drupal, once) {
//   Drupal.behaviors.fontResize = {
//     attach: function (context, settings) {
//       $(once('font-resize', 'html', context)).each(function () {
//         initFontResize();
//       });
//     }
//   };

//   function initFontResize() {
//     var $html = $('html');
//     var baseSize = parseFloat($html.css('font-size')); // default browser/Tailwind base
//     var currentStep = 0;
//     var minStep = -5;
//     var maxStep = 5;
//     var stepSize = 1; // px increment

//     // Remove href and set cursor
//     $('#font_resize-minus, #font_resize-default, #font_resize-plus')
//       .removeAttr('href')
//       .css('cursor', 'pointer');

//     // A- (decrease)
//     $(once('font-resize-minus', '#font_resize-minus')).on('click', function (e) {
//       e.preventDefault();
//       if (currentStep > minStep) {
//         currentStep--;
//         $html.css('font-size', (baseSize + currentStep * stepSize) + 'px');
//       }
//     });

//     // A (reset)
//     $(once('font-resize-default', '#font_resize-default')).on('click', function (e) {
//       e.preventDefault();
//       currentStep = 0;
//       $html.css('font-size', baseSize + 'px');
//     });

//     // A+ (increase)
//     $(once('font-resize-plus', '#font_resize-plus')).on('click', function (e) {
//       e.preventDefault();
//       if (currentStep < maxStep) {
//         currentStep++;
//         $html.css('font-size', (baseSize + currentStep * stepSize) + 'px');
//       }
//     });
//   }
// })(jQuery, Drupal, once);


(function ($, Drupal, once) {
    Drupal.behaviors.fontResize = {
        attach: function (context, settings) {
            $(once('font-resize-init', 'html', context)).each(function () {
                initFontResize();
            });
        }
    };

    function initFontResize() {
        var $html = $('html');
        var baseSize = parseFloat($html.css('font-size')); // Tailwind/browser base size
        var currentStep = 0;
        var minStep = -5;
        var maxStep = 5;
        var stepSize = 1; // px increment

        var $minus = $('#font_resize-minus');
        var $default = $('#font_resize-default');
        var $plus = $('#font_resize-plus');
        var $buttons = $minus.add($default).add($plus);

        // Remove href and set cursor
        $buttons.removeAttr('href').css('cursor', 'pointer');

        // Utility: highlight active button
        function setActive($btn) {
            $buttons.removeClass('active');
            $btn.addClass('active');
        }

        // A- (decrease)
        $(once('font-resize-minus', $minus)).on('click', function (e) {
            e.preventDefault();
            if (currentStep > minStep) {
                currentStep--;
                $html.css('font-size', (baseSize + currentStep * stepSize) + 'px');
                setActive($minus);
            }
        });

        // A (reset)
        $(once('font-resize-default', $default)).on('click', function (e) {
            e.preventDefault();
            currentStep = 0;
            $html.css('font-size', baseSize + 'px');
            setActive($default);
        });

        // A+ (increase)
        $(once('font-resize-plus', $plus)).on('click', function (e) {
            e.preventDefault();
            if (currentStep < maxStep) {
                currentStep++;
                $html.css('font-size', (baseSize + currentStep * stepSize) + 'px');
                setActive($plus);
            }
        });

        // Start with default active
        setActive($default);
    }
})(jQuery, Drupal, once);
