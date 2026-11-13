# Sous Search

`fourkitchens/sous-search` · `drupal-recipe` · GPL-2.0-or-later

Provides a **Search results** block with keyword search and a Content type
filter. Place the block on an existing page or in a theme region. The database
index covers all node bundles, including Page, Events, News Posts, Profiles
and content types added later. Search indexes titles and the text from each
node's default display. Results show teasers and respect the visitor's access
to the content.

## Installation and application

Choose Search from the installer's optional recipes once published, or configure
the repositories in the [collection guide](../../README.md) and run the following.

```sh
ddev composer require fourkitchens/sous-search:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-search
```

Recipes apply once and then unpack. Sites own the resulting dependencies and
complete configuration export. Package updates do not replay configuration.

## Site configuration

New and updated content is indexed immediately. When adding the recipe to a site
with existing content, run `ddev drush search-api:index` to index that content.

To exclude a content type, edit the Content index at
`/admin/config/search/search-api/index/content/edit`. Under the Content datasource,
keep **All except those selected**, select the content types to exclude, save,
and rebuild the index.

On Pantheon or Acquia, configure the site's Solr server in Search API and switch
the Content index to that server. The search view uses the index and can keep
its existing configuration.

## Contributing

Edit this package in Sous Drupal Packages. See the
[contributor guide](../../CONTRIBUTING.md) for scaffolding and checks.

[GPL-2.0-or-later](LICENSE) applies, along with dependency and package notices.
