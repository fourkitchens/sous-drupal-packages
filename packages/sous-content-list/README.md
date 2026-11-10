# Sous Content List

`fourkitchens/sous-content-list` · `drupal-recipe` · GPL-2.0-or-later

Adds a Content List with a heading, introduction, call to action, and a
**List Type** selector. Choose **Hand-picked** to add teasers individually or
**Automatic** to configure an automatic listing. In a **Content List Item**,
choose **Existing Content** to pick a node teaser or **Custom Content** to
enter an image, heading, text, and link.

The Paragraphs integration adds Content List and nested Content List Item
paragraphs. The Layout Builder integration provides the same types as inline
blocks. The base recipe supplies the shared content picker.
Admin-only and Content Types installations have no Content List component.

## Automatic listings

Choose an Embed display in the Teaser listing field. Use the `some` pager so the
field's item limit can override the display's default. Teaser embeds use tag-based
caching and expose their filters through the Content List editor.

The Teaser listing field enables the **Sift filters** setting. When the selected
display has supported contextual filters, editors see a control for each
enabled filter under **Options** → **Filter this list**.

- **All Upcoming Events** and **Past Events** offer **Event Type**.
- **Recent News** offers **News Categories**.
- **Profiles** offers **Items per page** and has no Sift control.

Events and News let editors match at least one selected term or require every
selected term. Leave the selection empty to show all results for the chosen
display up to the item limit.

## Theming

The package ships no templates. A theme can use
`paragraph--content-list.html.twig` and
`block--inline-block--content-list.html.twig` with the same field names. Use
the rendered `field_content` and `field_view` so Drupal can show the selected
source. Lists saved before List Type was added display their existing sources
until an editor picks a type.

## Installation and application

Choose Content List from the installer's optional sides on a Paragraphs or
Layout Builder site. Starter-generated sites already include the GitHub
repository for Views Reference Sift. Content List supports Sift 1.0.0-beta4
and later 1.x betas.

To install manually on an existing site, set up the Sous Composer index and
installer paths from the [collection guide](../../README.md). Add this entry
under `repositories` in the site's root `composer.json` if it is missing:

```json
"viewsreference-sift": {
  "type": "git",
  "url": "https://github.com/fourkitchens/viewsreference_sift.git",
  "only": ["fourkitchens/viewsreference_sift"]
}
```

Keep this entry for future Composer updates. Set `"minimum-stability": "beta"`
and `"prefer-stable": true` in the root manifest because the upstream Drupal
Views Reference 2.x dependency is still in beta.

```sh
ddev composer require fourkitchens/sous-content-list:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-content-list
```

Apply the matching integration after your composition and any content sides:

```sh
# Paragraphs
ddev drush recipe /var/www/html/web/recipes/sous-content-list/recipes/paragraphs

# Layout Builder
ddev drush recipe /var/www/html/web/recipes/sous-content-list/recipes/core-layout-builder
```

The Paragraphs integration allows Content List on Content fields that exist
when the recipe runs. For content types added later, allow Content List on
their Content field.

Recipes apply once and then unpack. Sites own the resulting dependencies and
configuration export. Package updates do not replay configuration.

## Contributing

Edit this package in Sous Drupal Packages. See the
[contributor guide](../../CONTRIBUTING.md) for scaffolding and checks.

[GPL-2.0-or-later](LICENSE). Dependency and package notices remain applicable.
