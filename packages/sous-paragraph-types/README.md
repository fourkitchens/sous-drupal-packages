# Sous Paragraph Types

`fourkitchens/sous-paragraph-types` · `drupal-recipe` · GPL-2.0-or-later

Provides Image, Text, Text with Media and Video Paragraph types, their fields and
authoring displays, plus the Text and Media component categories. It applies
Formats and Media and requires Paragraphs, Paragraphs Collapsible and Paragraphs
EE. It does not attach a Paragraph field to a node type.

The collection requires Drupal 11.4. Paragraphs Collapsible 1.6 remains for
compatibility.
Review the [project's maintenance and security coverage](https://www.drupal.org/project/paragraphs_collapsible)
when choosing its widget for a project.
A replacement belongs in a separately reviewed dependency/widget change.
Text with Media stores a left/right alignment value for themes; its default
formatter renders both fields without imposing a layout.

Use this package directly for project-owned hosts, or choose
[`sous-paragraphs`](https://github.com/fourkitchens/sous-drupal-packages/blob/main/packages/sous-paragraphs/README.md) to attach the shared field to
Page and Frontpage. Core Layout Builder and the common packages do not depend
on this package, so those projects never need its ZIP or contrib modules.
The strict list protects this recipe's type/field/display defaults while the
shared Paragraphs preview mode preserves an existing project's customization.

## Installation and application

Configure the Sous Composer index, Drupal repositories and installer paths as
shown in the [collection guide](../../README.md). Once the first collection
release is published, from a configured consuming site:

```sh
ddev composer require fourkitchens/sous-paragraph-types:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-paragraph-types
ddev drush cache:rebuild
```

Recipes are applied once and then unpacked. Package updates do not reapply
configuration. Projects maintain their complete configuration export.

## Contributing

Edit this package here in Sous Drupal Packages. See the
[contributor guide](../../CONTRIBUTING.md) for scaffolding and syntax/style checks.
A package source checkout is not a runnable Drupal application.

[GPL-2.0-or-later](LICENSE); dependency and package notices remain applicable.
