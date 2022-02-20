# Bookish Theme

This is the main theme for the Bookish profile. It makes use of many Single
File Components defined in the bookish_components module, and tries to
implement minimal global styling itself.

The theme and all components are written using vanilla CSS/JS, so no build step
is required.

## Using the container grid

By default, all elements are rendered at a full width, which allows deeply
nested elements to control their own sizing.

Most elements share the same CSS grid styles, which can be added by using the
"container" class. The container grid is 10 columns wide, with a max width
of 1280px. On desktop, most elements use 8 of the 10 grids to give some side
margin, but it's up to you to creatively decide when to not center elements.

## Breakpoints

Mobile styling isn't perfectly consistent in this theme, but in general mobile
is considered anything less than 560px wide, and desktop is anything greater
than 850px wide.
