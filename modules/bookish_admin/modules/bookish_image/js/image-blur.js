(function ($, Drupal) {

  Drupal.behaviors.bookishAdminBlurImage = {
    attach: function attach(context, settings) {
      $('.bookish-image-blur-image').once('bookish-image-blur').each(function () {
        if (!$(this).prop('complete')) {
          $(this).addClass('loading');
          $(this).on('load', function () {
            $(this).addClass('loaded');
          });
        }
      });
    }
  };

})(jQuery, Drupal);
