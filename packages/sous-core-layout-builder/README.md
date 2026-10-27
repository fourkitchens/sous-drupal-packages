# Sous Core Layout Builder

`fourkitchens/sous-core-layout-builder` · `drupal-recipe` · GPL-2.0-or-later

Optional **native Drupal core** Layout Builder composition for Page and
Frontpage. It applies Content Types and core's Basic block recipe, installs the
core layout/UI dependencies, enables Layout Builder on both default displays,
and permits per-node overrides. Their visible fields, including Body, remain
available as field blocks; authors can add Basic inline blocks.

This is the new `fourkitchens/sous-core-layout-builder` package. The historical
`fourkitchens/sous-layout-builder` represented a different Gin-based
implementation; its releases and consumers remain unchanged.

No Paragraphs recipe/package/module/type/host-field dependency is introduced.
A core-layout project downloads only this composition and its common
prerequisites. Paragraphs projects can omit this recipe and its configuration;
Layout Builder's PHP code is itself part of Drupal core.

## Installation and application

This source is a **candidate for the coordinated 2.0 release**; it has not been
published by this migration. After publication, configure the scoped Sous
Composer index, Drupal repositories and installer paths as described in the
[collection guide](https://github.com/fourkitchens/sous-drupal-packages/blob/main/README.md). Retain this package in Composer using the
root native recipe-unpack exclusion setting. From a configured consuming site:

```sh
ddev composer require fourkitchens/sous-core-layout-builder:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-core-layout-builder
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
