# Bookish Speed

This module adds client side routing to a traditional Drupal site. This is
accomplished by adding a click event handler to every link on the page, and
making a `fetch()` call instead of following the link.

Once fetch completes, the HTML response is parsed and the `<main>` tag on the
current page is swapped out with the new `<main>`.

CSS and JS on the new page are detected by checking the new `drupalSettings`.
Bookish Speed adds a new drupalSetting to every page that tries to list all
CSS and JS files that are included, even if preprocessing is enabled.

CSS is technically loaded first, but if it doesn't finish before the deadline,
`<main>` will load which may lead to a FOUC. This deadline is configurable.

JS is loaded synchronously, and Drupal behaviors are attached last. If you
aren't using Drupal behaviors and `once()`, your code will likely break.

If you want to ignore a link, you can configure the regular expression that
ignores `href`s at `/admin/structure/bookish-speed/settings`. You can also add
the class "no-speed" to any `a` tag to ignore it.

Links are also prefetched if hovered for long enough, which provides similar
features to Quicklink.

## Responding to navigation

Two window-level events are provided which can be used to do things
`Drupal.behaviors` can't, for instance to re-render areas of the page outside
of `<main>`. Those events are `bookish-speed-html` (when main is replaced) and
`bookish-speed-js` (after behaviors are triggered).

## Accessibility

On load, focus is moved to the skip link (`#skip-link`), and a Drupal
announcement is made. While this should help to indicate what is going on, if
you want the most accessible experience you may not want to use this module at
all. That said, if you have any ideas for improvement let me know in an issue!

## Security

This is kind of a security nightmare, since you're making AJAX requests on
potentially untrusted links and swapping in HTML from them, but many
protections have been made:

- `Drupal.url.isLocal` is called before the link is processed
- Only the link pathname is passed to `fetch`, and a single forward slash must
be present in the pathname
- The response's content type must be `text/html.*`
- Fetch uses `mode: 'same-origin'` as a final protection against cross origin
requests
