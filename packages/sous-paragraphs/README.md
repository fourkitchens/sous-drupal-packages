# Sous Paragraphs

`fourkitchens/sous-paragraphs` · `drupal-recipe` · GPL-2.0-or-later

Optional Layout Paragraphs composition for Sous Page and Frontpage. It applies
[Content Base](../sous-content-base) and [Paragraph Types](../sous-paragraph-types),
then attaches `field_content` with the Layout Paragraphs widget and formatter.
Each Content tab contains only that component reference; Body and SEO Title are
absent, and Title sits above the horizontal tabs with no Info tab.

The component set includes text/media, Accordion, Tabs, Quote, Embed, Table
and From the Library. Layout is a field-free container offering only core one-,
two-, three- and four-column layouts. Accordion Item and Tabs Item are edited
inside their containers; the top-level picker omits them. The module suite includes Paragraphs Library, its Layout Paragraphs
integration, Paragraphs Features, per-type permissions and Layout Paragraphs
Limit. The component chooser uses a searchable grid with icons and descriptions, matching the
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

Before requiring this recipe, follow the [shared patch setup](../../README.md#shared-patches).
Copy the complete [2.0.0 patch directory](https://github.com/fourkitchens/sous-drupal-packages/tree/2.0.0/patches) into the site root;
its [manifest](https://github.com/fourkitchens/sous-drupal-packages/blob/2.0.0/patches/patches.json) is read by root Composer.
Sous Drupal Project already provides this setup.

The shared manifest includes the [Layout Paragraphs chooser fix (#3487314)](https://www.drupal.org/project/layout_paragraphs/issues/3487314),
tested with 3.0.1, plus the required
[Style Selector](../sous-paragraph-types/README.md#style-selector-compatibility)
and [Tablefield fixes](../sous-paragraph-types/README.md#tablefield-compatibility).

```sh
ddev composer require fourkitchens/sous-paragraphs:^2.0
ddev composer patches-relock
ddev composer patches-repatch
ddev drush recipe /var/www/html/web/recipes/sous-paragraphs
ddev drush cache:rebuild
```

Recipes apply once and then unpack; sites keep the root patches, patch lock,
dependencies and complete configuration export.

## Contributing

Edit this package here in Sous Drupal Packages. See the
[contributor guide](../../CONTRIBUTING.md) for scaffolding and syntax/style checks.
A package source checkout is not a runnable Drupal application.

[GPL-2.0-or-later](LICENSE); dependency and package notices remain applicable.
