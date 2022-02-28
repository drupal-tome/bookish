# Bookish Image

This module attempts to make the core image module experience as nice as
possible.

## Image widget

A field widget is provided that allows the user to filter, adjust, and crop
their image uploads. Filters are sets of adjustments, and adjustments are
settings related to the look of an image - brightness, blur, etc.

Cropping is done using a focal point, but not the focal_point module. It was
hard to shim focal_point into the multi-tabbed widget, and the way it stores
edits would be annoying to use with Tome Sync since "crops" are their own
content entity. Instead, all these settings are stored in a File field.

The widget functionality is powered by routes, image effects, and a lot of glue
to make sure things work. It's pretty overboard, but once you start using it
it's hard to go back! To take advantage of all your edits, you will need to add
the "Bookish image effect" and "Bookish image scale and crop" or "Bookish image
crop" effects. "Bookish image crop" allows you to configure the "Zoom" of an
image, which effectively lets you freely crop an image to any size.

## CKEditor 5 plugin

A CKEditor plugin is also provided to edit uploaded inline images. It also lets
you embed using an image style, which core is also working to support. A custom
text filter is used to accomplish this.

## Blur-up formatter

A blur-up effect is available as an image formatter. This is done by making a
very small version of the image, displaying that in its own img tag using a
data attribute, and using a CSS filter to blur it. When the real full size
image is loaded, it is faded in with CSS. When JS is disabled, things still
work, you just don't see that fade.
