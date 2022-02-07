(function (Drupal, once) {

  Drupal.behaviors.bookishCKEditorIframeSize = {
    attach: function attach(context, settings) {
      once('bookish-ckeditor-iframe', '.bookish-oembed-twitter', context).forEach(function (element) {
        setTimeout(function () {
          if (!element.height && element.contentDocument.body.offsetHeight > 0) {
            element.height = element.contentDocument.body.offsetHeight + 30;
          }
          if (!element.width && element.contentDocument.body.offsetWidth > 0) {
            element.width = element.contentDocument.body.offsetWidth;
          }
        }, 1000);
      });
    }
  };

})(Drupal, once);
