# Sous Emulsify

`fourkitchens/sous-emulsify` · `drupal-recipe` · GPL-2.0-or-later

Installs the Emulsify base theme and Emulsify Tools, then selects Emulsify as the
frontend default theme. Admin, Content Types and either content composition are
independent options. The starter generates a project-owned custom theme and its
frontend build tooling; browser assets are built in that application.

This theme recipe creates no authorization roles. Sous Admin provides the
Superuser role independently.

## Installation and application

Configure the Sous Composer index, Drupal repositories and installer paths as
shown in the [collection guide](../../README.md). Retain this package using the
root native recipe-unpack exclusion setting. Once the first collection release
is published, from a configured consuming site:

```sh
ddev composer require fourkitchens/sous-emulsify:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-emulsify
ddev drush cache:rebuild
```

Recipe application is explicit. Requiring or updating the package does not
reapply configuration. Projects maintain their complete configuration export
after initial recipe application.

## Contributing

Edit this package here in Sous Drupal Packages. See the
[contributor guide](../../CONTRIBUTING.md) for scaffolding and syntax/style checks.
A package source checkout is not a runnable Drupal application.

[GPL-2.0-or-later](LICENSE); dependency and package notices remain applicable.
