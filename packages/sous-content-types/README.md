# Sous Content Types

`fourkitchens/sous-content-types` · `drupal-recipe` · GPL-2.0-or-later

Provides Page and Frontpage content types with Body, SEO metadata, teaser image
and text, shared fields, authoring displays and title-based Pathauto aliases.
Page additionally has tags. Media and Formats are explicit prerequisite recipes;
Pathauto and the modules referenced by the fields/widgets are direct dependencies.
Admin is independently optional and is never reapplied by this recipe.

Create content at `/node/add/page` or `/node/add/frontpage`, enter the required
SEO Title and write Body in the Content tab. The default display renders Body;
Page's teaser display uses its dedicated teaser fields. The recipe provides the
Frontpage content model, not a pre-created node or an automatic site-front-page
route assignment.

This content recipe adds neither Paragraphs host fields nor Layout Builder
display/override configuration. Core Navigation can enable the core Layout
Builder module transitively; its module state alone does not select the Sous
layout composition. Choose
[`sous-core-layout-builder`](https://github.com/fourkitchens/sous-drupal-packages/blob/main/packages/sous-core-layout-builder/README.md) or
[`sous-paragraphs`](https://github.com/fourkitchens/sous-drupal-packages/blob/main/packages/sous-paragraphs/README.md) when that project needs a
composition. Both retain Body. Do not replay the base recipe after a composition
has changed its displays; strict defaults protect those changes. Matching
configuration is accepted independently of generated UUID/default hashes;
conflicting content models require an explicit site migration.

## Installation and application

This source is a **candidate for the coordinated 2.0 release**; it has not been
published by this migration. After publication, configure the scoped Sous
Composer index, Drupal repositories and installer paths as described in the
[collection guide](https://github.com/fourkitchens/sous-drupal-packages/blob/main/README.md). Retain this package in Composer using the
root native recipe-unpack exclusion setting. From a configured consuming site:

```sh
ddev composer require fourkitchens/sous-content-types:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-content-types
ddev drush cache:rebuild
```

Recipe application is explicit. Requiring or updating the package does not
apply configuration again. Existing sites keep their database and complete
configuration export and adopt changes through reviewed native migrations.

## Contributing and checks

Source, issues and releases live in
[fourkitchens/sous-drupal-packages](https://github.com/fourkitchens/sous-drupal-packages).
See the [contributor guide](https://github.com/fourkitchens/sous-drupal-packages/blob/main/CONTRIBUTING.md). Maintainer probes live in
[`tests/recipes/sous-content-types`](https://github.com/fourkitchens/sous-drupal-packages/tree/main/tests/recipes/sous-content-types) when this package has
runtime checks; they are mounted into disposable fixtures and excluded from
consumer ZIPs. A package source checkout is not a runnable Drupal application.

[GPL-2.0-or-later](LICENSE); dependency and original source notices remain
applicable. Source commit provenance is recorded in the collection.
