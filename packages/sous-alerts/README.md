# Sous Alerts

`fourkitchens/sous-alerts` · `drupal-recipe` · GPL-2.0-or-later

Adds Sitewide Alert with its banner block at the top of each public page and,
when Sous Dashboard is installed, at the top of both Site admin and Editor.
Alerts load with JavaScript. Visitors see published alerts during their scheduled
windows and can dismiss them when dismissal is enabled. An empty alert card
remains on each dashboard when no alert applies.

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

Site Editors and Superusers manage alerts at `/admin/content/sitewide_alert`.
Site Editors receive permission to add, administer, edit and delete alerts, and
to view unpublished alerts.
Set the message, publishing status, schedule and dismissal options on each alert.

Alerts adds Alert to the Editor's Create block and under Create in Navigation,
and an Alerts link to Navigation's Content section. Each link shows only to
accounts that can open it. Superusers edit the links under Structure › Menus.

To show an alert only on dashboards, enable **Limit by Page** under **Page
Visibility**, enter both paths below and leave **Negate for listed pages** unchecked:

```text
/admin/dashboard
/admin/dashboard/*
```

Every published alert is public through `/sitewide_alert/load`, even when its
display is restricted to dashboard paths. Do not put confidential content in alerts.

Superusers manage global settings at `/admin/config/sitewide_alerts`. The default style is
`primary|Default`. Add each style on its own `key|label` line. The key becomes
an alert class on the wrapper, such as `alert-primary`, and the label appears in
the alert form.

## Theming

Override `sitewide-alert.html.twig` in the project theme to customize alert markup.
The alert endpoint uses the public theme, so this override also affects alerts
loaded on dashboards. Dashboards use the admin theme around the alerts and load
Sitewide Alert's default styles.
Keep `{{ attributes }}` on the wrapper to retain `data-uuid`, which JavaScript uses
to load and dismiss alerts. Keep the `js-dismiss-button` class on the dismiss button.

## Contributing

Edit this package in Sous Drupal Packages. See the
[contributor guide](../../CONTRIBUTING.md) for scaffolding and checks.

[GPL-2.0-or-later](LICENSE) applies, along with dependency and package notices.
