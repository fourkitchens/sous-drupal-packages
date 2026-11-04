# Sous Dev Tools

`fourkitchens/sous-dev-tools` · `drupal-recipe` · GPL-2.0-or-later

Adds Devel, Stage File Proxy and Masquerade to the local Config Split only.
The recipe applies `sous-config-split` and merges the three modules into its
local module list. The saved split stays inactive so local settings control activation.

## Installation and application

Once published, choose Dev Tools from the installer's optional recipes or
configure the repositories in the [collection guide](../../README.md) and run the following.

```sh
ddev composer require fourkitchens/sous-dev-tools:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-dev-tools
```

Recipes apply once and then unpack; sites own the resulting dependencies and
complete configuration export. Package updates do not replay configuration.
Enable the local split before exporting. Sous enables it in DDEV's local settings.
Staging and production imports leave these modules disabled. On a new Sous site,
the administrative Superuser role inherits Masquerade access. The recipe adds
no role grants.

## Local files and dependencies

In `web/sites/default/settings.local.php`, set Stage File Proxy's origin to the
production site's URL so missing uploaded files are fetched locally:

```php
$config['stage_file_proxy.settings']['origin'] = 'https://www.example.com';
```

After unpacking, sites may move these modules to `require-dev`; keep their code
available wherever the local split is enabled.

## Contributing

Edit this package in Sous Drupal Packages. See the
[contributor guide](../../CONTRIBUTING.md) for scaffolding and checks.

[GPL-2.0-or-later](LICENSE); dependency and package notices remain applicable.
