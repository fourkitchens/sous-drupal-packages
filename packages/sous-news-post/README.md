# Sous News Post

`fourkitchens/sous-news-post` · `drupal-recipe` · GPL-2.0-or-later

Adds a News Post content type with a title, plain-text byline, News Categories
and the same teaser image/text fields used by Page and Frontpage. Title sits
above horizontal Content, Teaser and Organization tabs; Categories belongs to
Organization. Metadata is in the sidebar. Create News Categories terms before
adding posts; Tagify Select supports assigning multiple categories.

Aliases use the first assigned category and title:
`news/news-category/news-title`. Reorder the categories to choose the category
used in a new alias.

Use `news:embed_recent` (Recent News) as a Content List source. It shows ten
published post teasers by default, newest first, with no exposed filters and
a **See all** link to `/news`.

The `page_news` display at `/news` shows 12 teasers per page and exposes News
Categories (`news_categories`) and title/teaser-description Search
(`news_search`). The first contextual filter on the embed and the page accepts
News Categories term IDs. Use `+` for any of the listed IDs, `,` for all of them,
or `all` to skip term filtering.

The same View's `entity_browser` display lets editors filter and select
published news posts by title.

Choose News Post from the installer's optional sides. The News model works
without Page, Frontpage or Paragraphs. When the Paragraphs main course is
selected, the installer attaches optional `field_content` with the Layout
Paragraphs editor. With Layout Builder, it enables layouts and per-post
overrides. Admin only keeps the simple News Post form.

The shared Editorial workflow starts content in Draft and supports Published
and Archived states, with optional scheduled publishing and unpublishing.

## Installation and application

Configure the Sous Composer index, repositories and installer paths as shown in
the [collection guide](../../README.md), then run:

```sh
ddev composer require fourkitchens/sous-news-post:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-news-post
```

On a site that already uses Sous Paragraphs, apply its bundled integration:

```sh
ddev drush recipe /var/www/html/web/recipes/sous-news-post/recipes/paragraphs
```

For an existing Sous Layout Builder site, apply its integration instead:

```sh
ddev drush recipe /var/www/html/web/recipes/sous-news-post/recipes/core-layout-builder
```

Recipes apply once and then unpack; sites own the resulting dependencies and
complete configuration export. Package updates do not replay configuration or
migrate existing News Post content.

## Contributing

Edit this package in Sous Drupal Packages. See the
[contributor guide](../../CONTRIBUTING.md) for scaffolding and checks.

[GPL-2.0-or-later](LICENSE); dependency and package notices remain applicable.
