# Sous Paragraph Types

`fourkitchens/sous-paragraph-types` · `drupal-recipe` · GPL-2.0-or-later

Provides Image, Text, Text with Media and Video Paragraph types, their fields and
authoring displays, plus the Text and Media component categories. It applies
Formats and Media and requires Paragraphs, Paragraphs Collapsible and Paragraphs
EE. It does not attach a Paragraph field to a node type.

The selected Paragraphs EE 10.2 line requires Drupal 11.4; this collection's
minimum is core 11.4.11. Paragraphs Collapsible 1.6 remains for compatibility.
The imported source records its maintainer's recommendation to use the native
Paragraphs widget and the [project's maintenance/security-coverage limitation](https://www.drupal.org/project/paragraphs_collapsible).
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

This source is a **candidate for the coordinated 2.0 release**; it has not been
published by this migration. After publication, configure the scoped Sous
Composer index, Drupal repositories and installer paths as described in the
[collection guide](https://github.com/fourkitchens/sous-drupal-packages/blob/main/README.md). Retain this package in Composer using the
root native recipe-unpack exclusion setting. From a configured consuming site:

```sh
ddev composer require fourkitchens/sous-paragraph-types:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-paragraph-types
ddev drush cache:rebuild
```

Recipe application is explicit. Requiring or updating the package does not
apply configuration again. Existing sites keep their database and complete
configuration export and adopt changes through reviewed native migrations.

## Contributing and checks

Source, issues and releases live in
[fourkitchens/sous-drupal-packages](https://github.com/fourkitchens/sous-drupal-packages).
See the [contributor guide](https://github.com/fourkitchens/sous-drupal-packages/blob/main/CONTRIBUTING.md). Maintainer probes live in
[`tests/recipes/sous-paragraph-types`](https://github.com/fourkitchens/sous-drupal-packages/tree/main/tests/recipes/sous-paragraph-types) when this package has
runtime checks; they are mounted into disposable fixtures and excluded from
consumer ZIPs. A package source checkout is not a runnable Drupal application.

[GPL-2.0-or-later](LICENSE); dependency and original source notices remain
applicable. Source commit provenance is recorded in the collection.
