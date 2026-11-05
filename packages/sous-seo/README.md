# Sous SEO

`fourkitchens/sous-seo` · `drupal-recipe` · GPL-2.0-or-later

Adds Redirect, Simple XML Sitemap, Easy Breadcrumb, Google Tag and Schema.org
Metatag without requiring a content composition. Alias changes create redirects;
breadcrumbs use page titles. The native sitemap variant and other module
settings are preserved. No tracking container ID or structured-data values are supplied.

## Installation and application

The Sous installer includes SEO in every build. To add it to another site,
configure the repositories in the [collection guide](../../README.md) and run
the following.

```sh
ddev composer require fourkitchens/sous-seo:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-seo
```

Recipes apply once and then unpack; sites own the resulting dependencies and
complete configuration export. Package updates do not replay configuration.

## Site configuration

For each content type to include, edit it at `/admin/structure/types/manage/TYPE`.
In its Simple XML Sitemap settings, enable indexing in the default sitemap and
save. Repeat for each installed type; this recipe does not choose types for the site.
Generate the sitemap at `/admin/config/search/simplesitemap` or with
`ddev drush simple-sitemap:generate`, and check `/sitemap.xml`.

Configure your site's Google Tag IDs at `/admin/config/services/google-tag` when
ready to track visitors. Enable the relevant Schema Metatag submodules and set
structured-data defaults at `/admin/config/search/metatag`; the base module
provides the framework, not default Schema.org output.

## Contributing

Edit this package in Sous Drupal Packages. See the
[contributor guide](../../CONTRIBUTING.md) for scaffolding and checks.

[GPL-2.0-or-later](LICENSE); dependency and package notices remain applicable.
