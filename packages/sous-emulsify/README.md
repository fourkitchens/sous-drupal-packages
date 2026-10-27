# Sous Emulsify

`fourkitchens/sous-emulsify` · `drupal-recipe` · GPL-2.0-or-later

Installs the Emulsify base theme and Emulsify Tools, then selects Emulsify as the
frontend default theme. Admin, Content Types and either content composition are
independent options. The starter generates a project-owned custom theme and its
frontend build tooling; browser assets are built in that application.

This theme recipe creates no authorization roles. Existing sites keep the
`superuser` role formerly supplied here and all assignments in their active
configuration/database. Keep it in the complete site export. A missing role can
be adopted through Admin's reviewed [native migration](https://github.com/fourkitchens/sous-drupal-packages/blob/main/packages/sous-admin/migrations/2026-10-superuser-role/README.md).
Updating this package neither removes an active role nor applies a migration.

## Installation and application

This source is a **candidate for the coordinated 2.0 release**; it has not been
published by this migration. After publication, configure the scoped Sous
Composer index, Drupal repositories and installer paths as described in the
[collection guide](https://github.com/fourkitchens/sous-drupal-packages/blob/main/README.md). Retain this package in Composer using the
root native recipe-unpack exclusion setting. From a configured consuming site:

```sh
ddev composer require fourkitchens/sous-emulsify:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-emulsify
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
