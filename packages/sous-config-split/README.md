# Sous Config Split

This recipe adds two inactive Config Split definitions: `local` and `staging`.
Both have `status: false` and empty module and configuration lists.
Their folders are `../config/local` and `../config/staging`, relative to the
Drupal web root. Create these directories before exporting configuration.

Recipes are applied once and then unpacked. Follow the
[collection guide](../../README.md), then configure each split's modules and
configuration and export the site's config. Package updates do not reapply the
recipe.

Enable the local split in the site's `settings.local.php`:

```php
$config['config_split.config_split.local']['status'] = TRUE;
```

Enable staging in the staging host's settings:

```php
$config['config_split.config_split.staging']['status'] = TRUE;
```

Leave both inactive in production and commit the site's exported configuration.
