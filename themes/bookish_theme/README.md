# Bookish Theme

This is the main theme for the Bookish profile. It makes use of many Single
File Components, and tries to implement minimal global styling otherwise.

Normally components would go in a module if they were part of a design system,
but these aren't extremely flexible, and having in the theme is likely more
palatable to a general Drupal audience.

The theme and all components are written using vanilla CSS/JS, so no build step
is required, however while working on components you should run
`drush sfc:watch` so that cache is cleared automatically.

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

## CSS variables and dark mode

Colors, font weights, and other settings are configured using CSS variables.
These can be easily overridden in a child theme, but supporting dark mode is a
bit trickier because users can force the theme into dark/light mode with the 
CSS classes "dark" and "light". Add your dark mode styles in a block like this:

```css
body {
  /* Normal variables here */
}
@media (prefers-color-scheme: dark) {
  body:not(.light) {
    /* Dark overrides here */
  }
}
body.dark {
  /* Duplicate the overrides here */
}
```
