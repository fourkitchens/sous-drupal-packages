# Sous Paragraph Types

`fourkitchens/sous-paragraph-types` · `drupal-recipe` · GPL-2.0-or-later

Provides Text, Text with Media, Image, Video, Accordion, Accordion Item,
Quote, Embed, Table, Tabs, Tabs Item and From the Library components with
their fields, authoring forms and portable display defaults. It applies Formats
and Media and installs the Paragraphs authoring suite, Entity Browser,
Field Group, Style Selector and Tablefield. It does not attach a Paragraph
field to a node type.

Media components retain caption and presentation choices. Accordion items hold
Image, Text or Video; Tabs Item can contain every provided component except the
Layout container supplied by the Layout Paragraphs composition. Nested editors
use default form modes so required fields remain editable. From the Library
opens a modal component picker with rendered previews. Themes supply component
markup and interaction; no reference site's theme libraries are required.

Use this package directly for project-owned hosts, or choose
[`sous-paragraphs`](https://github.com/fourkitchens/sous-drupal-packages/blob/main/packages/sous-paragraphs/README.md)
to attach the shared field to Page and Frontpage. Core Layout Builder and the
common packages do not depend on this package or its Paragraphs modules. The strict list protects the recipe's own defaults;
the shared Paragraphs preview mode preserves project customization.

## Installation and application

Before requiring this recipe, follow the [shared patch setup](../../README.md#shared-patches).
Copy the complete [2.0.0 patch directory](https://github.com/fourkitchens/sous-drupal-packages/tree/2.0.0/patches) into the site root;
its [manifest](https://github.com/fourkitchens/sous-drupal-packages/blob/2.0.0/patches/patches.json) is read by root Composer.
Sous Drupal Project already provides this setup.

Tabs Item uses Layout Paragraphs; the shared manifest includes its
[chooser fix (#3487314)](https://www.drupal.org/project/layout_paragraphs/issues/3487314).

```sh
ddev composer require fourkitchens/sous-paragraph-types:^2.0
ddev composer patches-relock
ddev composer patches-repatch
ddev drush recipe /var/www/html/web/recipes/sous-paragraph-types
ddev drush cache:rebuild
```

Recipes apply once and then unpack; sites keep the root patches, patch lock,
dependencies and complete configuration export.

### Style Selector compatibility

The shared compatibility patch includes the
[library discovery fix (#3589133)](https://www.drupal.org/project/style_selector/issues/3589133)
and custom field-default schema additions for Drupal 11. The custom patch is
retained; upstream schema tracking is deferred to the maintainer.

### Tablefield compatibility

The shared manifest includes the output-buffer, rich-text-cell and Add Column
fixes, plus the [default-value schema adaptation (#3593703)](https://www.drupal.org/project/tablefield/issues/3593703)
for Drupal 11.4 recipes. The table starts with two rows and columns, Limited HTML
cell editors and row headers; the theme supplies its presentation.

## Contributing

Edit this package here in Sous Drupal Packages. See the
[contributor guide](../../CONTRIBUTING.md) for scaffolding and syntax/style checks.
A package source checkout is not a runnable Drupal application.

[GPL-2.0-or-later](LICENSE); dependency and package notices remain applicable.
