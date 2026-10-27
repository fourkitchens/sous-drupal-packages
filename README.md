# Sous Drupal Packages

Reusable Drupal recipes for [Sous Drupal Project](https://github.com/fourkitchens/sous-drupal-project).
Install the packages your project needs through Composer. Each recipe is a separate
ZIP; sharing a repository and release version does not install the entire collection.

## Package catalog

Every package uses the `fourkitchens/` Composer namespace and the collection's
shared version, beginning with `1.0.0`.

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
| [sous-config-split](packages/sous-config-split)               | Native `_splits`, local and staging definitions                         | Independent opt-in                    |

Core Layout Builder and Paragraphs are independently optional. Common packages
require neither composition, and choosing Layout Builder adds no Paragraphs
recipe, modules or configuration. Drupal's Navigation module, included by Admin,
enables the shared core Layout Builder module; it does not apply the optional
Sous layout recipe or its content display/override configuration.

## Use in a project

[Sous Drupal Project](https://github.com/fourkitchens/sous-drupal-project) supplies
the Composer repositories, installer paths and installation prompts. Select default
content, Layout Builder, Paragraphs or administration only, then independently
choose whether to install Config Split.

For another application, add the static Composer repository to its root manifest:

```json
{
  "type": "composer",
  "url": "https://fourkitchens.github.io/sous-drupal-packages/",
  "only": ["fourkitchens/sous-*"]
}
```

Keep the Drupal repository and appropriate Composer installer/plugin settings in
that root manifest. Install recipes under `web/recipes` and add each retained Sous
package to `extra.drupal-recipe-unpack.ignore` so Composer keeps it as a dependency
and restores its files on clean clones. This setting does not install a package.
The [Media guide](packages/sous-media/README.md) describes the root Dropzone library
repository required by the content recipes. Admin requires the root stability
exception `drupal/login_destination:^2.0.0-beta10@beta`.

Once the first collection release is published, for example:

```sh
ddev composer require fourkitchens/sous-core-layout-builder:^1.0
ddev drush recipe /var/www/html/web/recipes/sous-core-layout-builder
ddev drush cache:rebuild
```

| Content choice             | Direct package to require/apply              |
| -------------------------- | -------------------------------------------- |
| Default Page and Frontpage | `fourkitchens/sous-content-types:^1.0`       |
| Layout Builder             | `fourkitchens/sous-core-layout-builder:^1.0` |
| Paragraphs                 | `fourkitchens/sous-paragraphs:^1.0`          |
| Administration only        | No content package                           |

Admin and Emulsify can be required and applied independently. Recipe application
is explicit: Composer install/update does not reapply configuration. Apply recipes
to the intended initial site, then maintain the site's complete configuration export.
Config Split supplies inactive environment definitions; applications own their
split directories and runtime settings. See the [Config Split guide](packages/sous-config-split/README.md).

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

Checks under `devops/tests` cover syntax, code standards and formatting. They do
not install Drupal or test recipe behavior. See [CONTRIBUTING.md](CONTRIBUTING.md)
for contribution and local build instructions.

Merges to `main` automatically create a semantic release, publish every package's
ZIP at the same version and update the static Composer index. The first release
is `1.0.0`; see [RELEASING.md](docs/RELEASING.md) for version rules and setup.

Distributed under [GPL-2.0-or-later](LICENSE), matching Sous Drupal Project.
Copyright and dependency notices are retained in [NOTICE](NOTICE) and the packages.
