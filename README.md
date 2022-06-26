[![Netlify Status](https://api.netlify.com/api/v1/badges/c7036c2e-996a-4bac-9da4-c4bba607ab04/deploy-status)](https://app.netlify.com/sites/bookish-drupal/deploys)
![Tests Status](https://github.com/drupal-tome/bookish/actions/workflows/test.yml/badge.svg)

# Bookish

[View demo site]

Bookish is an install profile for Drupal 9+ that tries to make the out of the
box experience for [Tome] users as nice as possible.

In terms of functionality, Bookish is similar to the Standard profile. Most of
the work in this profile has been to make the editing experience and frontend
as modern-feeling as possible.

Some feature highlights are:

* Ability to filter and crop images on upload, in CKEditor or a field
* Blur-up functionality for images, similar to GatsbyJS
* A theme with dark mode support, built using Single File Components
* Already configured Metatag, Pathauto, Lunr, and Simple XML sitemap integrations
* Ability to embed code snippets in CKEditor that are styled in the frontend
* A simplified toolbar that just lists the default shortcuts

## Install (with Tome)

The best way to use Bookish is with the [Tome Composer project].

The requirements for using Tome locally are:

* PHP 7+
* Composer
* Drush
* SQLite 3.26+ and the related PHP extensions

Alternatively you can run the commands below using the [mortenson/tome Docker
image]. See the [Docker script documentation] for reference.

The [Drush Launcher], which allows typing simply `drush`, is not required to use Tome. If not available, use `vendor/bin/drush` instead.

To install Tome and Bookish, run these commands:

```
composer create-project drupal-tome/tome-project my_site --stability dev --no-interaction
cd my_site
composer require drupal-tome/bookish
drush tome:init # Select Bookish in the prompt
```

You can now commit your initial codebase, content, config, and files to Git.

To start a local webserver, run:

```
drush runserver
```

then in another tab run:

```
drush uli -l 127.0.0.1:8888
```

and click the link to start editing.

To re-install your site, run:

```
drush tome:install
```

For information on deploying your site, you can visit
`/admin/help/topic/bookish_help.tome` on your local site, or read the docs at
https://tome.fyi/docs.

## Install (without Tome)

If you don't want to use Tome, you can run this from any Drupal 9+ install:

```
composer require drupal-tome/bookish
drush si bookish -y
drush pmu tome -y
```

## Further help

After logging in, click "Help" in the toolbar. This module has extensive
documentation located inside Drupal using the Help Topics module. A good place
to start would be the "Configuring your Bookish site" page, which will guide
you through personalizing the configuration of your site.

## Speeding up your site with AJAX navigation (experimental)

To emulate the behavior of JavaScript routers which refresh the main content of
the page instead of navigating to a new page when links are clicked, you can
enable the Bookish Speed module. Note that your JavaScript will have to run in
behaviors, and will have to use `once()`. Inline scripts and styles are not
supported.

## Exporting content as YAML (experimental)

If you would prefer exporting content as .yml files, you can try using the
experimental "yaml" encoder for Tome by adding this line to settings.php:

```
$settings['tome_sync_encoder'] = 'yaml';
```

Then enable the bookish_yaml module, which reformats rich text content to
make sure it's easily editable as a multi-line YAML string.

Then, run "drush tome:export" to re-export your content as .yml files, and
remove old .json files after running "drush tome:install" and confirming things
still work.

This feature is experimental, but may be the default for Tome installs in the
future, so thank you in advance for testing it out!

[View demo site]: https://bookish-drupal.netlify.app/
[Tome]: https://drupal.org/project/tome
[Tome Composer project]: https://github.com/drupal-tome/tome-project
[mortenson/tome Docker image]: https://github.com/drupal-tome/tome-docker
[Docker script documentation]: https://github.com/drupal-tome/tome-project/#docker
[Drush Launcher]: https://github.com/drush-ops/drush-launcher
