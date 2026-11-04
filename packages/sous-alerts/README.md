# Sous Alerts

`fourkitchens/sous-alerts` · `drupal-recipe` · GPL-2.0-or-later

Adds Sitewide Alert with its banner block at the top of each public page.
Visitors see published alerts during their scheduled windows and can dismiss
alerts when dismissal is enabled. Alerts load with JavaScript and are hidden on
administrative pages.

## Installation and application

Choose Alerts from the installer's optional recipes once published, or configure
the repositories in the [collection guide](../../README.md) and run the following.

```sh
ddev composer require fourkitchens/sous-alerts:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-alerts
```

Recipes apply once and then unpack. Sites own the resulting dependencies and
complete configuration export. Package updates do not replay configuration.

## Site configuration

Editors with the Superuser role manage alerts at `/admin/content/sitewide_alert`.
Set the message, publishing status, schedule and dismissal options on each alert.

Manage global settings at `/admin/config/sitewide_alerts`. The default style is
`primary|Default`. Add each style on its own `key|label` line. The key becomes
an alert class on the wrapper, such as `alert-primary`, and the label appears in
the alert form.

## Theming

Override `sitewide-alert.html.twig` in the project theme to customize the banner.
Keep `{{ attributes }}` on the wrapper to retain `data-uuid`, which JavaScript uses
to load and dismiss alerts. Keep the `js-dismiss-button` class on the dismiss button.

## Contributing

Edit this package in Sous Drupal Packages. See the
[contributor guide](../../CONTRIBUTING.md) for scaffolding and checks.

[GPL-2.0-or-later](LICENSE) applies, along with dependency and package notices.
