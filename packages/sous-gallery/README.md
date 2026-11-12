# Sous Gallery

`fourkitchens/sous-gallery` · `drupal-recipe` · GPL-2.0-or-later

Adds a Gallery with a heading, introductory text, and the same width options as
Image. Use the Media Library to select and reorder any number of images and
hosted videos. Choose Slider or Grid under Display Options.

The Sous installer includes Gallery with the Paragraphs and Layout Builder main
courses and applies the matching integration after any content sides. On an
existing site using one of those Sous compositions, apply the recipe and then the
matching integration:

```sh
ddev composer require fourkitchens/sous-gallery:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-gallery

# Paragraphs
ddev drush recipe /var/www/html/web/recipes/sous-gallery/recipes/paragraphs

# Layout Builder
ddev drush recipe /var/www/html/web/recipes/sous-gallery/recipes/core-layout-builder
```

The default display renders each item with its media bundle's Default view mode.
The Gallery Display field provides `gallery-slider` or `gallery-grid` for themes
to implement the selected layout. Themes can customize the Gallery with
`paragraph--gallery.html.twig` or `block--inline-block--gallery.html.twig`.

[Photo gallery icon by Freepik on Flaticon](https://www.flaticon.com/free-icon/photo-gallery_1837578).

[GPL-2.0-or-later](LICENSE). Dependency and package notices remain applicable.
