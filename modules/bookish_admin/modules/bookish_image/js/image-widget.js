(function ($, Drupal, debounce) {

  Drupal.behaviors.bookishImageWidget = {
    attach: function attach(context, settings) {
      // Works around a #states issue where the Zoom slider was not always
      // available. Tempting to just drop #states at this point.
      var fixZoomStates = function () {
        $('.bookish-image-tabs input:checked').each(function () {
          if ($(this).val() != 1) {
            return;
          }
          if (!$(this).closest('.bookish-image-container').find('.bookish-image-zoom:visible').length) {
            $(this).prop('checked', false).change();
            $(this).prop('checked', true).change();
          };
        });
      }
      $('.bookish-image-tabs').once('bookish-image-tab').each(function () {
        $(this).on('click', fixZoomStates);
      });

      // Adds a background image to the preview parent, to avoid flashes of
      // white when waiting for images to load.
      $('.bookish-image-preview', context).once('bookish-image-preview').each(function () {
        var $img = $(this).find('img');
        var $wrapper = $(this).parent();
        $wrapper
          .css('width', $img.attr('width'))
          .css('height', 'auto')
          .css('max-width', '500px');
        $(this).promise().done(debounce(function () {
          var f = function () {
            $wrapper
              .css('background-image', 'url(' + $img.attr('src') + ')')
              .css('box-shadow', 'none')
              .css('background-repeat', 'no-repeat')
              .css('background-size', 'cover');
          }
          if ($img.prop('complete')) {
            f();
          } else {
            $img.on('load', f);
          }
        }, 100));
        // Piggy-back on this call to reflow #states.
        fixZoomStates();
      });

      // Adds a reset button to every range element in the form.
      $('.bookish-image-container', context).once('bookish-image-container').each(function () {
        $(this).find('input[type="range"]').each(function () {
          var $resetButton = $('<button class="bookish-image-reset"><span class="visually-hidden">Reset</span></button>');
          var $range = $(this);
          $resetButton.click(function (e) {
            e.preventDefault();
            $range.val(0);
            $range.trigger('change');
          });
          $(this).after($resetButton);

          $(this).on('input', debounce(function () {
            $(this).trigger('change');
          }, 100));
        });
      });

      // Initializes the focal point selector.
      $('.bookish-image-focal-point-container', context).once('bookish-image-focal-point').each(function () {
        var $img = $(this).find('img');
        var imageLoaded = function () {
          var $dot = $('<div class="bookish-image-focal-point-dot"></div>');
          var $container = $(this).closest('.bookish-image-container');
          var wasContainerVisible = $container.is(':visible');
          var wasVisible = $(this).is(':visible');
          $(this).show();
          $container.show();

          // Set default value from form element.
          var differenceX = $img.attr('width') / $img.width();
          var differenceY = $img.attr('height') / $img.height();
          var default_val = $container
            .find('.bookish-image-focal-point-input')
            .val();
          var pos = default_val.split(',');
          var defaultX = 0;
          var defaultY = 0;
          if (pos.length === 2) {
            defaultX = parseInt(pos[0]) / differenceX;
            defaultY = parseInt(pos[1]) / differenceY;
          }
          $dot.css('left', defaultX);
          $dot.css('top', defaultY);

          $(this).append($dot);
          if (!wasVisible) {
            $(this).hide();
          }
          if (!wasContainerVisible) {
            $container.hide();
          }

          var dragging = false;
          var updateDot = function (e) {
            var x = e.pageX - $img.offset().left;
            var y = e.pageY - $img.offset().top;
            $dot.css('left', x);
            $dot.css('top', y);
          }
          var updateInput = debounce(function (e) {
            var x = e.pageX - $img.offset().left;
            var y = e.pageY - $img.offset().top;
            var differenceX = $img.attr('width') / $img.width();
            var differenceY = $img.attr('height') / $img.height();
            $container
              .find('.bookish-image-focal-point-input')
              .val(Math.round(x * differenceX) + ',' + Math.round(y * differenceY));
            $container
              .find('.bookish-image-re-render')
              .click();
          }, 100);
          $img.on('mousedown', function (e) {
            dragging = true;
            updateDot(e);
            updateInput(e);
          });
          $img.on('mousemove', function (e) {
            if (dragging) {
              updateDot(e);
              updateInput(e);
            }
          });
          $img.on('mouseup', function () {
            dragging = false;
          });
        }.bind(this);
        if ($img.prop('complete')) {
          imageLoaded();
        } else {
          $img.on('load', imageLoaded);
        }
      });

      // Supports clicking a fitler to fill in filters automatically.
      $('.bookish-image-filter', context).once('bookish-image-filter').each(function () {
        $(this).on('click', function (e) {
          e.preventDefault();
          var data = JSON.parse($(this).attr('data-image-data'));
          $container = $(this).closest('.bookish-image-container');
          for (var key in data) {
            $container.find('input[type="range"][name*=' + key + ']').val(data[key]);
          }
          $container
            .find('.bookish-image-re-render')
            .click();
        });
      });
    }
  };

  // AJAX command to call a function in the CKEditor plugin's scope.
  Drupal.AjaxCommands.prototype.bookishImageCKEditor = function (ajax, response, status) {
    if (window.bookishImageAjaxCallback && response.url) {
      window.bookishImageAjaxCallback(response.url, response.imageStyle);
    }
  }

  // Override Drupal.Ajax.prototype.beforeSend, against my best judgement, to
  // not take focus away from range elements when dragging.
  var beforeSend = Drupal.Ajax.prototype.beforeSend;

  Drupal.Ajax.prototype.beforeSend = function (xmlhttprequest, options) {
    if (!$(this.element).is('.bookish-image-container input[type="range"]')) {
      beforeSend.call(this, xmlhttprequest, options);
      return;
    }

    // For forms without file inputs, the jQuery Form plugin serializes the
    // form values, and then calls jQuery's $.ajax() function, which invokes
    // this handler. In this circumstance, options.extraData is never used. For
    // forms with file inputs, the jQuery Form plugin uses the browser's normal
    // form submission mechanism, but captures the response in a hidden IFRAME.
    // In this circumstance, it calls this handler first, and then appends
    // hidden fields to the form to submit the values in options.extraData.
    // There is no simple way to know which submission mechanism will be used,
    // so we add to extraData regardless, and allow it to be ignored in the
    // former case.
    if (this.$form) {
      options.extraData = options.extraData || {};

      // Let the server know when the IFRAME submission mechanism is used. The
      // server can use this information to wrap the JSON response in a
      // TEXTAREA, as per http://jquery.malsup.com/form/#file-upload.
      options.extraData.ajax_iframe_upload = '1';

      // The triggering element is about to be disabled (see below), but if it
      // contains a value (e.g., a checkbox, textfield, select, etc.), ensure
      // that value is included in the submission. As per above, submissions
      // that use $.ajax() are already serialized prior to the element being
      // disabled, so this is only needed for IFRAME submissions.
      const v = $.fieldValue(this.element);
      if (v !== null) {
        options.extraData[this.element.name] = v;
      }
    }

    // Disable the element that received the change to prevent user interface
    // interaction while the Ajax request is in progress. ajax.ajaxing prevents
    // the element from triggering a new request, but does not prevent the user
    // from changing its value.
    // $(this.element).prop('disabled', true); <-- OUR HACK

    if (!this.progress || !this.progress.type) {
      return;
    }

    // Insert progress indicator.
    const progressIndicatorMethod = `setProgressIndicator${this.progress.type
      .slice(0, 1)
      .toUpperCase()}${this.progress.type.slice(1).toLowerCase()}`;
    if (
      progressIndicatorMethod in this &&
      typeof this[progressIndicatorMethod] === 'function'
    ) {
      this[progressIndicatorMethod].call(this);
    }
  };

})(jQuery, Drupal, Drupal.debounce);
