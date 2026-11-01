# Sous Core Layout Builder

`fourkitchens/sous-core-layout-builder` · `drupal-recipe` · GPL-2.0-or-later

Optional **native Drupal core** Layout Builder composition for Page and
Frontpage. It applies Content Types, enables Layout Builder on both default
displays and permits per-node overrides. Existing fields, including Body,
remain available as field blocks.

Authors can add Text, Text with Media, Video and Image inline custom blocks.
Their fields, media selectors, Content/Display Options tabs and presentation
choices match the Paragraph components, using block fields and displays instead
of Paragraph entities. Text keeps its simpler heading/text form.

No Paragraphs recipe, module, type or host-field dependency is introduced.
The shared Page/Frontpage forms place Title above the horizontal tabs and omit
SEO Title and Info. Paragraphs projects omit this composition and keep Layout
Builder disabled on those entity displays, even when Navigation requires the
core module itself.

## Installation and application

Configure the Sous Composer index, Drupal repositories and installer paths as
shown in the [collection guide](../../README.md). Once the first collection
release is published, from a configured consuming site:

```sh
ddev composer require fourkitchens/sous-core-layout-builder:^2.0
mkdir -p patches
cp web/recipes/sous-core-layout-builder/assets/patches/style-selector-drupal11-compatibility.patch patches/
```

The Style Selector widgets require the supplied Drupal 11 compatibility patch.
Sous Drupal Project registers it automatically; other consumers must register
this persistent copy with Composer Patches 2 using the manifest entry in
[Style Selector compatibility](../sous-paragraph-types/README.md#style-selector-compatibility).
That patch includes the upstream library discovery fix and custom field-default
schema additions; it does not require the Paragraphs recipe or modules.

```sh
ddev composer patches-relock
ddev composer patches-repatch
ddev drush recipe /var/www/html/web/recipes/sous-core-layout-builder
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
