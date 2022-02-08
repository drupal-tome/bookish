(function (Drupal, once) {

  Drupal.behaviors.bookishCKEditorIframeSize = {
    attach: function attach(context, settings) {
      var f = function () {
        if (!this.height && this.contentDocument.body.offsetHeight > 250) {
          this.height = this.contentDocument.body.offsetHeight + 40;
        }
      };
      once('bookish-ckeditor-iframe', '.bookish-oembed-twitter', context).forEach(function (element) {
        setTimeout(f.bind(element), 500);
        setTimeout(f.bind(element), 1000);
        setTimeout(f.bind(element), 1500);
        setTimeout(f.bind(element), 2000);
      });
    }
  };

})(Drupal, once);
