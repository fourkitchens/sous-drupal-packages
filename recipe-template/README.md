# **PACKAGE_TITLE**

`fourkitchens/__PACKAGE_NAME__` · `drupal-recipe` · GPL-2.0-or-later

Describe what this recipe provides and which projects should use it.

## Customize this recipe

1. Add the exported configuration this recipe owns to `config/`. Remove exported
   site-specific `uuid` and `_core.default_config_hash` values.
2. Add required modules to `install` in `recipe.yml` and their Composer packages
   to `require` in `composer.json`. Update both descriptions.
3. If another recipe is needed, require its package in `composer.json` and list
   its recipe basename under `recipes` in `recipe.yml`. Keep Layout Builder and
   Paragraphs dependencies optional unless this recipe specifically needs them.
4. Run `npm test` from the repository root and review the configuration in a
   disposable Drupal project before submitting a conventional commit.

The collection discovers packages automatically. Release tooling supplies the
shared version; do not add `version`, `source`, or `dist` to `composer.json`.

## Install and apply

Configure the [Sous Composer repository](https://github.com/fourkitchens/sous-drupal-packages/blob/main/README.md) in the consuming project,
then install the published recipe and apply it explicitly:

```sh
ddev composer require fourkitchens/__PACKAGE_NAME__:^1.0
ddev drush recipe /var/www/html/web/recipes/__PACKAGE_NAME__
```

Installing or updating the Composer package does not apply its configuration.

Distributed under [GPL-2.0-or-later](LICENSE).
