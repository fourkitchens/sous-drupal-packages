# Sous Drupal Packages

Reusable Drupal recipes for [Sous Drupal Project](https://github.com/fourkitchens/sous-drupal-project).
Install the packages your project needs through Composer. Each recipe is a separate
ZIP; sharing a repository and release version does not install the entire collection.

## Package catalog

Every package uses the `fourkitchens/` Composer namespace and the collection's
shared version, beginning with `2.0.0`.

| Package                                                       | Purpose                                                          | Installation choice        |
| ------------------------------------------------------------- | ---------------------------------------------------------------- | -------------------------- |
| [sous-admin](packages/sous-admin)                             | Gin, Navigation, Superuser and management Views                  | Starter base               |
| [sous-emulsify](packages/sous-emulsify)                       | Emulsify theme and tools                                         | Starter base               |
| [sous-workflow](packages/sous-workflow)                       | Shared Editorial moderation and scheduled publishing             | Starter/shared dependency  |
| [sous-content-base](packages/sous-content-base)               | Shared Page/Frontpage fields, tabs, teasers and aliases          | Shared dependency          |
| [sous-content-types](packages/sous-content-types)             | Page and Frontpage with Body                                     | Default content            |
| [sous-formats](packages/sous-formats)                         | Text formats, CKEditor 5 and Linkit defaults                     | Shared dependency          |
| [sous-media](packages/sous-media)                             | File, Image, Video and media authoring                           | Starter base               |
| [sous-events](packages/sous-events)                           | Recurring events, event types, teasers and upcoming/past embeds  | Optional recipe            |
| [sous-news-post](packages/sous-news-post)                     | News Posts, bylines, categories, teasers and a Recent News embed | Optional recipe            |
| [sous-paragraph-types](packages/sous-paragraph-types)         | Text, media, accordion, tabs, quote, embed and table components  | Paragraphs building blocks |
| [sous-paragraphs](packages/sous-paragraphs)                   | Layout Paragraphs on Page/Frontpage without Body                 | Optional composition       |
| [sous-core-layout-builder](packages/sous-core-layout-builder) | Native layouts with matching nested inline block types           | Optional composition       |
| [sous-config-split](packages/sous-config-split)               | Local/staging development tools excluded from production         | Starter base               |

The content compositions share a Body-free base. The default content package
adds Body; Paragraphs adds its component reference instead. Page and Frontpage
place Title above the horizontal tabs, with no separate SEO Title or Info tab.
Layout Builder adds no Paragraphs recipe, modules or configuration.

Navigation, included by Admin, requires core Layout Builder, and Layout
Paragraphs requires Layout Discovery. Those module dependencies do not enable
Layout Builder on the Paragraphs composition's entity displays.

## Use in a project

Add this scoped Composer repository to the site's `composer.json`:

```json
{
  "type": "composer",
  "url": "https://fourkitchens.github.io/sous-drupal-packages/",
  "only": ["fourkitchens/sous-*"]
}
```

Require and apply a recipe:

```sh
ddev composer require fourkitchens/sous-admin:^2.0 drupal/login_destination:^2.0.0-beta10@beta
ddev drush recipe /var/www/html/web/recipes/sous-admin
```

Recipes are applied once during setup and then unpacked, so the site owns its
dependencies and configuration.

## Contribute

Use Node.js `24.22.1`, npm `11.21.1`, PHP `8.4+`, Composer and `zip`.

```sh
npm ci
npm test
npm run create-package sous-event
```

The generator copies [recipe-template](recipe-template) into `packages/sous-event`
and fills in its name. Add the recipe's configuration, module requirements and
Sous dependencies before using it. Packages are discovered automatically by the
archive builder; no central package registry needs editing.

`npm test` checks syntax, code standards and formatting. The CI `recipes` job
applies recipes to fresh SQLite Drupal sites in five compositions. See
[CONTRIBUTING.md](CONTRIBUTING.md) for contribution and local build instructions.

Merges to `main` automatically create a semantic release, publish every package's
ZIP at the same version and update the static Composer index. The first release
is `2.0.0`; see [RELEASING.md](docs/RELEASING.md) for version rules and setup.

Distributed under [GPL-2.0-or-later](LICENSE), matching Sous Drupal Project.
Copyright and dependency notices are retained in [NOTICE](NOTICE) and the packages.
