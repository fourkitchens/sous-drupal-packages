# Sous Paragraphs

`fourkitchens/sous-paragraphs` · `drupal-recipe` · GPL-2.0-or-later

Optional Paragraphs composition for both Sous Page and Frontpage. It applies
Content Types and Paragraph Types, installs Paragraphs Features, and adds the
shared `field_content` host field and its editor/display to both node types.
Both Content tabs retain the original Body field alongside components and keep
the base recipe's Info, Teaser and other authoring tabs.

Require/apply this recipe only when a project chooses Paragraphs. Neither the
default content model nor the Core Layout Builder composition depends on it.
No artificial Composer conflict prevents a project from deliberately designing
its own combined composition, but the standard installer selects one path.

Existing field data is never renamed or migrated by a package update. Sites
using an older `field_content_body` or a customized content model need a
reviewed, project-owned data/configuration migration.

The collection distributes this package under GPL-2.0-or-later. Its original
2023 Four Kitchens MIT copyright and full permission notice remain in
[NOTICE](NOTICE) for the imported source.

## Installation and application

This source is a **candidate for the coordinated 2.0 release**; it has not been
published by this migration. After publication, configure the scoped Sous
Composer index, Drupal repositories and installer paths as described in the
[collection guide](https://github.com/fourkitchens/sous-drupal-packages/blob/main/README.md). Retain this package in Composer using the
root native recipe-unpack exclusion setting. From a configured consuming site:

```sh
ddev composer require fourkitchens/sous-paragraphs:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-paragraphs
ddev drush cache:rebuild
```

Recipe application is explicit. Requiring or updating the package does not
apply configuration again. Existing sites keep their database and complete
configuration export and adopt changes through reviewed native migrations.

## Contributing and checks

Source, issues and releases live in
[fourkitchens/sous-drupal-packages](https://github.com/fourkitchens/sous-drupal-packages).
See the [contributor guide](https://github.com/fourkitchens/sous-drupal-packages/blob/main/CONTRIBUTING.md). Shared package and composition checks run in disposable fixtures; maintainer
tools and probes are excluded from consumer ZIPs. A package source checkout is not a runnable Drupal application.

[GPL-2.0-or-later](LICENSE); dependency and original source notices remain
applicable. Source commit provenance is recorded in the collection.
