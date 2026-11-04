# Sous Config Split

This recipe adds `local` and `staging` splits for core Field UI, Syslog and
Views UI. Importing an active split enables its tools. Both splits have
`status: false` in exports, keeping the tools out of production. Choose
[Sous Dev Tools](../sous-dev-tools) for local Devel, Stage File Proxy and Masquerade.
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

For a new site, enable the three core modules normally and export with each split
active so their default settings reach both folders; the starter handles this.
Afterward, normal configuration imports select the environment’s tools and settings.
Leave both overrides unset in production and commit the complete site export.
