# Sous Drupal Packages

Reusable Drupal recipes for [Sous Drupal Project](https://github.com/fourkitchens/sous-drupal-project).
Install the packages your project needs through Composer. Each recipe is a separate
ZIP; sharing a repository and release version does not install the entire collection.

## Package catalog

Every package uses the `fourkitchens/` Composer namespace and the collection's
shared version, beginning with `2.0.0`.

| Package                                                       | Purpose                                                                 | Installation choice                   |
| ------------------------------------------------------------- | ----------------------------------------------------------------------- | ------------------------------------- |
| [sous-admin](packages/sous-admin)                             | Gin, Navigation, Superuser, management Views and shared Linkit settings | Starter base                          |
| [sous-emulsify](packages/sous-emulsify)                       | Emulsify theme and tools                                                | Starter base                          |
| [sous-content-types](packages/sous-content-types)             | Page, Frontpage, fields and aliases; Formats + Media                    | Default content and both compositions |
| [sous-formats](packages/sous-formats)                         | Text formats and shared Linkit configuration                            | Shared dependency                     |
| [sous-media](packages/sous-media)                             | File, Image, Video and media authoring                                  | Shared dependency                     |
| [sous-paragraph-types](packages/sous-paragraph-types)         | Text, Image, Video and Text with Media components; Formats + Media      | Paragraphs only                       |
| [sous-paragraphs](packages/sous-paragraphs)                   | Paragraphs authoring on Page/Frontpage; Content Types + Paragraph Types | Optional composition                  |
| [sous-core-layout-builder](packages/sous-core-layout-builder) | Native Layout Builder on Page/Frontpage; Content Types                  | Optional composition                  |
| [sous-config-split](packages/sous-config-split)               | Inactive local and staging Config Split definitions                     | Independent opt-in                    |

Core Layout Builder and Paragraphs are independently optional. Common packages
require neither composition, and choosing Layout Builder adds no Paragraphs
recipe, modules or configuration. Drupal's Navigation module, included by Admin,
enables the shared core Layout Builder module; it does not apply the optional
Sous layout recipe or its content display/override configuration.

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
