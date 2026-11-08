# Sous Gallery

`fourkitchens/sous-gallery` · `drupal-recipe` · GPL-2.0-or-later

Adds a Gallery with a heading, introductory text, and the same width options as
Image. Use the Media Library to select and reorder any number of images and
hosted videos.

Select Gallery as an optional side with Paragraphs or Layout Builder. The
installer applies the matching integration. On an existing site using one of
those Sous compositions, apply the recipe and then the matching integration:

```sh
ddev composer require fourkitchens/sous-gallery:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-gallery

# Paragraphs
ddev drush recipe /var/www/html/web/recipes/sous-gallery/recipes/paragraphs

# Layout Builder
ddev drush recipe /var/www/html/web/recipes/sous-gallery/recipes/core-layout-builder
```

The default display renders each item with its media bundle's Default view mode.
Themes can customize the Gallery with `paragraph--gallery.html.twig` or
`block--inline-block--gallery.html.twig`.

[GPL-2.0-or-later](LICENSE). Dependency and package notices remain applicable.
