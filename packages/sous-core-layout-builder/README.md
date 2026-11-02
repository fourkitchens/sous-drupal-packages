# Sous Core Layout Builder

`fourkitchens/sous-core-layout-builder` · `drupal-recipe` · GPL-2.0-or-later

Optional **native Drupal core** Layout Builder composition for Page and
Frontpage. It applies Content Types, enables Layout Builder on both default
displays and permits per-node overrides. Existing fields, including Body,
remain available as field blocks.

Authors can add Text, Text with Media, Video, Image, Accordion, Accordion Item,
Quote, Embed, Table, Tabs and Tabs Item inline custom blocks.
Their fields, media selectors, Content/Display Options tabs and presentation
choices match the Paragraph components, using block fields and displays instead
of Paragraph entities. Text keeps its simpler heading/text form. Nested blocks
use Inline Entity Form to create, edit and reorder child blocks, with revision
references preserving the content recorded by each parent revision. Accordion
accepts Accordion Items containing Image, Text or Video; Tabs accepts Tabs Items,
which can contain every supplied block type. Native one- through four-column
sections supply layouts. There is no From the Library block.

No Paragraphs recipe, module, type or host-field dependency is introduced.
The shared Page/Frontpage forms place Title above the horizontal tabs and omit
SEO Title and Info. Paragraphs projects omit this composition and keep Layout
Builder disabled on those entity displays, even when Navigation requires the
core module itself.

## Installation and application

Before requiring this recipe, follow the [shared patch setup](../../README.md#shared-patches).
Copy the complete [2.0.0 patch directory](https://github.com/fourkitchens/sous-drupal-packages/tree/2.0.0/patches) into the site root;
its [manifest](https://github.com/fourkitchens/sous-drupal-packages/blob/2.0.0/patches/patches.json) is read by root Composer.
Sous Drupal Project already provides this setup.

The shared manifest includes the required
[Style Selector](../sous-paragraph-types/README.md#style-selector-compatibility)
and [Tablefield fixes](../sous-paragraph-types/README.md#tablefield-compatibility).
These support the block fields without installing Paragraphs.

```sh
ddev composer require fourkitchens/sous-core-layout-builder:^2.0
ddev composer patches-relock
ddev composer patches-repatch
ddev drush recipe /var/www/html/web/recipes/sous-core-layout-builder
ddev drush cache:rebuild
```

Recipes apply once and then unpack; sites keep the root patches, patch lock,
dependencies and complete configuration export.

## Contributing

Edit this package here in Sous Drupal Packages. See the
[contributor guide](../../CONTRIBUTING.md) for scaffolding and syntax/style checks.
A package source checkout is not a runnable Drupal application.

[GPL-2.0-or-later](LICENSE); dependency and package notices remain applicable.
