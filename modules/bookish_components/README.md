# Bookish Components

This module provides re-usable components for the Bookish Theme. It also
provides some static pages like 404, 403, /blog, and /home.

It may be weird to see theme-related stuff in a module. Having the components
in a module has a number of benefits:

-You can re-use components in any theme (not just sub-themes)
-Components in modules can provide Blocks (ex: "components/bk_blog_list.sfc")
-Modules providing components can also provide routes

However, this module isn't really a design system, so things are pretty
specific to the theme.
