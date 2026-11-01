# Sous Paragraphs

`fourkitchens/sous-paragraphs` · `drupal-recipe` · GPL-2.0-or-later

Optional Layout Paragraphs composition for Sous Page and Frontpage. It applies
[Content Base](../sous-content-base) and [Paragraph Types](../sous-paragraph-types),
then attaches `field_content` with the Layout Paragraphs widget and formatter.
Each Content tab contains only that component reference; Body and SEO Title are
absent, and Title sits above the horizontal tabs with no Info tab.

Text, Text with Media, Video and Image share grouped authoring forms and media
selectors. The module suite includes Paragraphs Library, its Layout Paragraphs
integration, Paragraphs Features, per-type permissions and Layout Paragraphs
Limit. The component chooser uses a searchable list with icons, matching the
reference authoring workflow. Layout Discovery is required by Layout Paragraphs.
Navigation also requires core Layout Builder, but these node displays do not
enable Layout Builder or its per-node overrides.

Require this recipe only when choosing Paragraphs. The default content model
and Core Layout Builder composition do not require it. Package updates do not
rename existing fields or migrate stored content; sites own subsequent model
changes.

The collection distributes this package under GPL-2.0-or-later. Its additional
copyright and permission notice is retained in [NOTICE](NOTICE).

## Installation and application

Configure the Sous index, Drupal repositories and recipe installer paths as
shown in the [collection guide](../../README.md), then require this recipe:

```sh
ddev composer require fourkitchens/sous-paragraphs:^2.0
```

The component chooser uses the [#3487314 UI patch](https://www.drupal.org/project/layout_paragraphs/issues/3487314),
tested with Layout Paragraphs 3.0.1. The
[Style Selector compatibility patch](../sous-paragraph-types/README.md#style-selector-compatibility)
is also required. Sous Drupal Project registers both persistent copies. Other
consumers need Composer Patches 2, with
`cweagans/composer-patches` allowed in `config.allow-plugins`:

```sh
ddev composer require cweagans/composer-patches:^2.0
mkdir -p patches
cp web/recipes/sous-paragraphs/assets/patches/layout-paragraphs-component-list-3487314.patch patches/
cp web/recipes/sous-paragraph-types/assets/patches/style-selector-drupal11-compatibility.patch patches/
```

Configure `extra.composer-patches.patches-file` to use the site's patch manifest
(for example `patches/patches.json`). Register the Style Selector entry linked
above and add this Layout Paragraphs entry to that manifest:

```json
{
  "patches": {
    "drupal/layout_paragraphs": [
      {
        "description": "Improve Layout Paragraphs component selection (#3487314)",
        "url": "patches/layout-paragraphs-component-list-3487314.patch",
        "sha256": "1f41f7ef7024f9c4c88b98f8250539581357c62f2306b5b89b3368ac19d7ffaa",
        "depth": 1
      }
    ]
  }
}
```

Rebuild the patch lock, apply both patches, then apply the recipe:

```sh
ddev composer patches-relock
ddev composer patches-repatch
ddev drush recipe /var/www/html/web/recipes/sous-paragraphs
ddev drush cache:rebuild
```

Keep the copied patch and patch lock in the site. Recipe unpacking moves
requirements into the project; it does not register recipe patch definitions.
Recipes apply once and then unpack, and sites own the resulting dependencies
and configuration export.

## Contributing

Edit this package here in Sous Drupal Packages. See the
[contributor guide](../../CONTRIBUTING.md) for scaffolding and syntax/style checks.
A package source checkout is not a runnable Drupal application.

[GPL-2.0-or-later](LICENSE); dependency and package notices remain applicable.
