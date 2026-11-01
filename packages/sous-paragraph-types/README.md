# Sous Paragraph Types

`fourkitchens/sous-paragraph-types` · `drupal-recipe` · GPL-2.0-or-later

Provides Image, Text, Text with Media and Video Paragraph types with their
fields, authoring forms, display defaults and Text/Media component categories.
It applies Formats and Media and installs Paragraphs, Paragraphs Library,
Paragraphs EE, Field Group and Style Selector. It does not attach a Paragraph
field to a node type.

Every type has a heading and formatted text. Image and Video include caption
visibility and layout width choices. Text with Media supports image or video
media, caption visibility, display style, component focus and media alignment.
Content and Display Options tabs group the media forms; Text keeps its simpler
heading/text form. Text and Text with Media can convert into reusable library
items. The recipe imports Paragraphs Library and Style Selector module defaults.
Style values remain project data; themes decide how to render them.

Use this package directly for project-owned hosts, or choose
[`sous-paragraphs`](https://github.com/fourkitchens/sous-drupal-packages/blob/main/packages/sous-paragraphs/README.md)
to attach the shared field to Page and Frontpage. Core Layout Builder and the
common packages do not depend on this package, so those projects never need its
ZIP or Paragraphs modules. The strict list protects the recipe's own defaults;
the shared Paragraphs preview mode preserves project customization.

## Installation and application

Configure the Sous Composer index, Drupal repositories and installer paths as
shown in the [collection guide](../../README.md). Once the first collection
release is published, from a configured consuming site:

```sh
ddev composer require fourkitchens/sous-paragraph-types:^2.0
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

## Contributing

Edit this package here in Sous Drupal Packages. See the
[contributor guide](../../CONTRIBUTING.md) for scaffolding and syntax/style checks.
A package source checkout is not a runnable Drupal application.

[GPL-2.0-or-later](LICENSE); dependency and package notices remain applicable.
