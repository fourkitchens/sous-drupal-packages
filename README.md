# Sous Drupal Packages

Reusable Drupal recipes for [Sous Drupal Project](https://github.com/fourkitchens/sous-drupal-project).
Contributors work here; consuming projects download only their selected packages
and dependencies as individual ZIPs through a static Composer index.

**Migration candidate:** this branch implements the package repository. No
package release or Composer index has been published. The starter continues to
use its existing repositories until a separately reviewed cutover. See
[migration status](docs/MIGRATION.md), the [original plan](MIGRATION_PLAN.md), and
[release prerequisites](docs/RELEASING.md).

## Package catalog

The seven existing Composer names remain unchanged. The candidate collection
uses a shared `2.0.0` version and bounded `^2.0` sibling dependencies.

| Package | Purpose and direct Sous dependencies | Installation choice |
| --- | --- | --- |
| [sous-admin](packages/sous-admin) | Gin, Navigation, Superuser, management Views; shared Linkit subrecipe from Formats | Starter base |
| [sous-emulsify](packages/sous-emulsify) | Emulsify theme and tools | Starter base |
| [sous-content-types](packages/sous-content-types) | Page, Frontpage, fields and aliases; Formats + Media | Default content, both compositions |
| [sous-formats](packages/sous-formats) | Text formats and shared Linkit configuration | Shared dependency |
| [sous-media](packages/sous-media) | File, Image, Video and media authoring | Shared dependency |
| [sous-paragraph-types](packages/sous-paragraph-types) | Text, Image, Video, Text with Media components; Formats + Media | Paragraphs only |
| [sous-paragraphs](packages/sous-paragraphs) | Paragraphs authoring on Page/Frontpage; Content Types + Paragraph Types | Optional composition |
| [sous-core-layout-builder](packages/sous-core-layout-builder) | Native Layout Builder on Page/Frontpage; Content Types | Optional composition |
| [sous-config-split](packages/sous-config-split) | Native `_splits`, local and staging definitions | Independent opt-in |

Every Composer name is prefixed `fourkitchens/`. Core Layout Builder has no
Paragraphs dependency or configuration. Paragraphs has no Core Layout Builder
package dependency. Common packages require neither composition. A common
version does not install the whole collection. No Composer conflict prevents
an experienced site owner from deliberately composing both.

Drupal 11.4 Navigation (part of Admin) enables the core Layout Builder module.
Selecting Paragraphs still installs no Core Layout Builder recipe or per-bundle
layout/override configuration. The optional boundary controls the composition;
it cannot make that shared core module disappear while retaining Navigation.

The new name `fourkitchens/sous-core-layout-builder` avoids changing the meaning
of the older public `fourkitchens/sous-layout-builder` package.

## Consuming the future release

After publication, merge the [starter Composer fragment](examples/starter-composer.fragment.json)
into the application root. Its proposed index URL is
`https://fourkitchens.github.io/sous-drupal-packages/`; verify the actual HTTPS
endpoint before enabling clients. The repository is canonical for exactly nine
names, preserving access to unrelated Four Kitchens packages.

The root retains Drupal's native recipe-unpack exclusion for those nine names.
This keeps selected recipes in `composer.json` and `composer.lock`, so a clean
clone restores their files. The exclusion list does not require any package.
Other recipes retain normal native unpack behavior. Composer repository,
installer, patch and plugin settings belong to the consuming root.

The starter always requires Admin and Emulsify, then adds one content selection:

| Choice | Additional direct requirement | Apply after Admin and Emulsify |
| --- | --- | --- |
| Default content | `fourkitchens/sous-content-types:^2.0` | `sous-content-types` |
| Core Layout Builder | `fourkitchens/sous-core-layout-builder:^2.0` | `sous-core-layout-builder` |
| Paragraphs | `fourkitchens/sous-paragraphs:^2.0` | `sous-paragraphs` |
| Administration only | None | None |

For example, in a prepared consuming root after publication:

```sh
ddev composer require fourkitchens/sous-core-layout-builder:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-admin
ddev drush recipe /var/www/html/web/recipes/sous-emulsify
ddev drush recipe /var/www/html/web/recipes/sous-core-layout-builder
```

Recipe application is explicit. Composer install/update does not reapply recipe
configuration. Apply Admin once; its strict Gin validation can reject settings
changed by a prior application. Existing sites preserve their database and
complete configuration export and adopt changes through reviewed migrations.

Config Split can be selected with any choice. The recipe supplies the `_splits`
definition and inactive `sous_local`/`sous_staging` defaults. The application owns
`config/_splits`, `config/local`, `config/staging`, directory creation and runtime
environment overrides. See [starter adoption](docs/STARTER_ADOPTION.md).

## Contributing and distribution

Use Node.js `24.22.1`, npm `11.21.1`, PHP `8.4+`, Composer `2.10.3`, Git and
`zip`/`unzip`. No npm dependencies are needed.

```sh
npm run check
npm test
npm run test:consumer
```

Consumer tests download public dependencies into disposable SQLite applications
and exercise ZIP installation, explicit recipe application and clean-clone
recovery. See [CONTRIBUTING.md](CONTRIBUTING.md) for targeted runs and local
development. Source probes and tools stay outside consumer ZIPs.

The deterministic builder creates one ZIP per package, a full provenance/hash
manifest, and a dist-only Composer index. It never tags, uploads or deploys.
Future publication is a separately gated manual operation described in
[RELEASING.md](docs/RELEASING.md). Legacy repositories and tags remain available;
old lockfiles are not rewritten by this migration.

Distributed under [GPL-2.0-or-later](LICENSE). Original source commits, license
details and transformations are recorded in
[provenance/source-imports.json](provenance/source-imports.json) and [NOTICE](NOTICE).
