# Bookish Admin

This module contains all non-theme-related functionality of the Bookish
profile. All sub-modules can be used independently of each-other, and are
documented in their own README files.

Functionality that is not worth splitting into its own sub-module is
present here. Right now, that's:

- Adding field_tag facets to Lunr search
- Providing a token for Metatag that points to a fallback node
- Styles the node form to be wider and CKEditor to be longer
- Provides a summary field formatter which filters out many unwanted HTML tags
without being plain text.
- Provides a text filter that adds anchor links to all headings.

If you want to use Bookish Admin features without using the Bookish profile,
you can install https://www.drupal.org/project/bookish_admin.
