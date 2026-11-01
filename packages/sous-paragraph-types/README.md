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

Configure the Sous Composer index, Drupal repositories and installer paths as
shown in the [collection guide](../../README.md). Once the first collection
release is published, from a configured consuming site:

```sh
ddev composer require fourkitchens/sous-paragraph-types:^2.0
```

Tabs Item uses Layout Paragraphs. Register its supplied chooser patch using the
[Layout Paragraphs manifest entry](../sous-paragraphs/README.md#installation-and-application):

```sh
mkdir -p patches
cp web/recipes/sous-paragraph-types/assets/patches/layout-paragraphs-component-list-3487314.patch patches/
```

### Style Selector compatibility

Apply the supplied Drupal 11 compatibility patch before the recipe. Sous Drupal
Project already registers its persistent copy. Other consumers need Composer
Patches 2 allowed in `config.allow-plugins`, and a root patch manifest configured
through `extra.composer-patches.patches-file`:

```sh
ddev composer require cweagans/composer-patches:^2.0
mkdir -p patches
cp web/recipes/sous-paragraph-types/assets/patches/style-selector-drupal11-compatibility.patch patches/
```

Add this entry to the site's patch manifest:

```json
{
  "patches": {
    "drupal/style_selector": [
      {
        "description": "Custom Drupal 11 field-default schema and library discovery compatibility",
        "url": "patches/style-selector-drupal11-compatibility.patch",
        "sha256": "39c6d214990c04690c5dd425e080a7410be8a248dedb81da9d63b3bd66d1809c",
        "depth": 1
      }
    ]
  }
}
```

The library discovery change is tracked in [#3589133](https://www.drupal.org/project/style_selector/issues/3589133).
The field-default schema additions are custom and await separate upstream tracking.
Rebuild and apply the patch lock before installing the recipe:

```sh
ddev composer patches-relock
ddev composer patches-repatch
ddev drush recipe /var/www/html/web/recipes/sous-paragraph-types
ddev drush cache:rebuild
```

Keep the copied patch and lock in the site; recipe unpacking does not register
its patch metadata. Recipes apply once and then unpack; sites own subsequent
dependencies and complete configuration exports.

### Tablefield compatibility

Tablefield 3 uses SFASU's output-buffer, rich-text-cell and Add Column fixes,
plus a [default-value schema adaptation (#3593703)](https://www.drupal.org/project/tablefield/issues/3593703)
for Drupal 11.4 recipes. Sous Drupal Project registers all four persistent
patches. Other consumers copy them before applying this recipe:

```sh
cp web/recipes/sous-paragraph-types/assets/patches/tablefield-*.patch patches/
```

Merge the entries from [tablefield.json](assets/patches/tablefield.json) into
the site's patch manifest, then run `ddev composer patches-relock` and
`ddev composer patches-repatch`. The table starts with two rows and columns,
Limited HTML cell editors, and row headers; the theme supplies its presentation.

## Contributing

Edit this package here in Sous Drupal Packages. See the
[contributor guide](../../CONTRIBUTING.md) for scaffolding and syntax/style checks.
A package source checkout is not a runnable Drupal application.

[GPL-2.0-or-later](LICENSE); dependency and package notices remain applicable.
