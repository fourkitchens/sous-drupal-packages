# Sous Config Split

This recipe adds `local` and `staging` splits for Devel, Field UI, Stage File
Proxy, Syslog and Views UI. Composer installs their code; importing an active
split enables its tools. Both splits have `status: false` in exports, keeping
the tools out of production.
Their folders are `../config/local` and `../config/staging`, relative to the
Drupal web root. Create these directories before exporting configuration.

Recipes are applied once and then unpacked. Follow the
[collection guide](../../README.md), then export the site's configuration.
Package updates do not reapply the recipe.

Enable the local split in the site's `settings.local.php`:

```php
$config['config_split.config_split.local']['status'] = TRUE;
```

Enable staging in the staging host's settings:

```php
$config['config_split.config_split.staging']['status'] = TRUE;
```

After adding an override, import that split with
`ddev drush -y config-split:import local` (or `staging`), then export configuration.
Leave both overrides unset in production and commit the complete site export.
