# Sous Events

`fourkitchens/sous-events` · `drupal-recipe` · GPL-2.0-or-later

Adds an Event content type with recurring/all-day Smart Date values, an Event
Type vocabulary and Tagify Select widget, and the same teaser image/text fields
used by Page and Frontpage. Title sits above horizontal Content, Teaser and
Organization tabs; Event Type belongs to Organization. Metadata is in the sidebar.
Create Event Type terms before adding events; each Event has one required type.

Events adds a Create link for Event to the Editor dashboard when Sous Dashboard
is installed.

Aliases use the first event date, type and title:
`event/MM-YYYY/event-type/event-title`. Recurring schedules generate individual
date values; their aliases remain based on the first date.

Use `events:embed_upcoming` and `events:embed_past` as Content List sources.
Each embed shows six event teasers by default, with no exposed filters.
Ongoing occurrences count as upcoming. A recurring series can appear once in
each list when it has both past and future occurrences. Each list orders events
by their relevant date.

The `block_upcoming` and `block_past` displays show 12 teasers per page and
expose Event Type (`event_type`) and title/teaser-description Search
(`event_search`). Both embeds and both blocks have an Event Type contextual
filter. Content List editors can choose Event Type terms under **Filter this
list**. When passing term IDs directly,
use `+` for any of the listed IDs, `,` for all of them, or `all` to skip term
filtering.

The same View's `entity_browser` display lets editors filter and select
published events by title, including past events.

Choose Events from the installer's optional sides. Media and the Event model
work without Page, Frontpage or Paragraphs. When the Paragraphs main course is
selected, the installer attaches its existing `field_content` and Layout
Paragraphs editor. With Layout Builder, the installer enables layouts and
per-event overrides. Admin only keeps the simple Event form.

The shared Editorial workflow starts content in Draft and supports Published
and Archived states, with optional scheduled publishing and unpublishing.

## Installation and application

Configure the Sous Composer index, repositories and installer paths as shown in
the [collection guide](../../README.md), then run:

```sh
ddev composer require fourkitchens/sous-events:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-events
```

On a site that already uses Sous Paragraphs, apply its bundled integration:

```sh
ddev drush recipe /var/www/html/web/recipes/sous-events/recipes/paragraphs
```

For an existing Sous Layout Builder site, apply its integration instead:

```sh
ddev drush recipe /var/www/html/web/recipes/sous-events/recipes/core-layout-builder
```

Recipes apply once and then unpack; sites own the resulting dependencies and
complete configuration export. Package updates do not replay configuration or
migrate existing Event content.

## Contributing

Edit this package in Sous Drupal Packages. See the
[contributor guide](../../CONTRIBUTING.md) for scaffolding and checks.

[GPL-2.0-or-later](LICENSE); dependency and package notices remain applicable.
