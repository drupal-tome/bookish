(function (Drupal, once) {

  Drupal.behaviors.bookishAdminBlurImage = {
    attach: function attach(context, settings) {
      once('bookish-image-blur', '.bookish-image-blur-image', context).forEach(function (blurImage) {
        if (!blurImage.complete) {
          blurImage.classList.add('loading');
          blurImage.onload = function () {
            blurImage.classList.add('loaded');
          };
        }
      });
    }
  };

})(Drupal, once);
