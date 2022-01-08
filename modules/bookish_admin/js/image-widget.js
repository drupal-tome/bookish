(function ($, Drupal, debounce) {

  Drupal.behaviors.bookishAdminImageWidget = {
    attach: function attach(context, settings) {
      // This is insane, but I'm trying to work around a graphical bug where
      // image previews would flash on the screen since core AJAX animations
      // are really lacking.
      $('.bookish-image-preview', context).once('bookish-image-preview').each(function () {
        $(this).parent().css('position', 'relative');
        $clone = $(this).clone();
        $clone.attr('id', '');
        $clone.attr('class', 'bookish-image-preview-clone');
        $clone.attr('style', '');
        $clone.css('position', 'absolute');
        $(this).before($clone);
        $(this).promise().done(function(){
          $(this).parent().find('.bookish-image-preview-clone').each(function() {
            if (!$(this).is($clone)) {
              $(this).remove();
            }
          });
        });
      });
      $('.bookish-image-data-container', context).once('bookish-image-container').each(function () {
        $(this).find('input[type="range"]').each(function () {
          var $resetButton = $('<button class="bookish-image-reset"><span class="visually-hidden">Reset</span></button>');
          var $range = $(this);
          $resetButton.click(function(e) {
            e.preventDefault();
            $range.val(0);
            $range.trigger('change');
          });
          $(this).after($resetButton);

          // $(this).on('input', debounce(function () {
          //   $(this).trigger('change');
          // }, 200));
        });
      });
    }
  };

})(jQuery, Drupal, Drupal.debounce);
