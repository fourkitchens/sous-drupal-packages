# Sous Core Layout Builder

`fourkitchens/sous-core-layout-builder` · `drupal-recipe` · GPL-2.0-or-later

Optional **native Drupal core** Layout Builder composition for Page and
Frontpage. It applies Content Types and core's Basic block recipe, installs the
core layout/UI dependencies, enables Layout Builder on both default displays,
and permits per-node overrides. Their visible fields, including Body, remain
available as field blocks; authors can add Basic inline blocks.

No Paragraphs recipe/package/module/type/host-field dependency is introduced.
A core-layout project downloads only this composition and its common
prerequisites. Paragraphs projects can omit this recipe and its configuration;
Layout Builder's PHP code is itself part of Drupal core.

## Installation and application

Configure the Sous Composer index, Drupal repositories and installer paths as
shown in the [collection guide](../../README.md). Once the first collection
release is published, from a configured consuming site:

```sh
ddev composer require fourkitchens/sous-core-layout-builder:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-core-layout-builder
ddev drush cache:rebuild
```

Recipes are applied once and then unpacked. Package updates do not reapply
configuration. Projects maintain their complete configuration export.

## Contributing

Edit this package here in Sous Drupal Packages. See the
[contributor guide](../../CONTRIBUTING.md) for scaffolding and syntax/style checks.
A package source checkout is not a runnable Drupal application.

[GPL-2.0-or-later](LICENSE); dependency and package notices remain applicable.
