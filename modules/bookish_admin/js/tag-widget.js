(function ($, Drupal) {

  Drupal.behaviors.bookishAdminTagsWidget = {
    attach: function attach(context, settings) {
      $('input.bookish-tags-widget').once('bookish-tags-widget').each(function () {
        var input = this,
          tagify = new Tagify(input, {
            whitelist:[],
          }),
          controller;

        new DragSort(tagify.DOM.scope, {
          selector: '.' + tagify.settings.classNames.tag,
          callbacks: {
              dragEnd: function (elm) {
                tagify.updateValueByDOMTags()
              }
          }
        })

        var onInput = Drupal.debounce(function (e) {
          var value = e.detail.value;
          tagify.whitelist = null;

          // https://developer.mozilla.org/en-US/docs/Web/API/AbortController/abort
          controller && controller.abort();
          controller = new AbortController();

          // show loading animation and hide the suggestions dropdown
          tagify.loading(true);

          fetch($(input).attr('data-autocomplete-url') + '?q=' + encodeURIComponent(value), {signal: controller.signal})
            .then(res => res.json())
            .then(function (data) {
              var newData = [];
              data.forEach(function (current) {
                newData.push({
                  value: current.label,
                  entity_id: current.value.match(/.+\s\(([^\)]+)\)/)[1],
                });
              });
              tagify.whitelist = newData;
              tagify.loading(false);
              tagify.dropdown.show(value);
            });
        }, 500);
        tagify.on('input', onInput)
      });
    }
  };

})(jQuery, Drupal);
