# Contributing

Edit `packages/<name>/` for recipe changes. Each package owns its Composer
manifest, recipe, configuration, license and guide. Keep dependencies explicit;
common packages should not require either optional content composition.
Shared moderation lives in `sous-workflow`; content recipes merge their own
bundles into it with `addNodeTypes`.

Use Node.js `24.22.1` (`nvm use`), npm `11.21.1`, PHP `8.4+`, Composer and `zip`.
Install repository tooling with `npm ci`.

## Create a recipe

```sh
npm run create-package sous-event
```

This copies `recipe-template/` into `packages/sous-event`, substitutes its Composer
name (`fourkitchens/sous-event`) and human-readable name, and leaves the new recipe
ready to customize. An existing package is never overwritten.
Set `extra.sous.menu` to `true` in `composer.json` when a recipe is useful on its own.

1. Edit the generated README and descriptions to explain the recipe's purpose.
2. Add configuration YAML under `config/` and update `recipe.yml` with the modules,
   prerequisite recipes and configuration actions it needs.
3. Declare required Drupal modules and Sous packages in `composer.json`. Composer
   dependencies download code; the recipe must explicitly install/apply what it uses.
4. Run `npm test`, review the generated files and try the recipe in your application.

The archive builder discovers package directories automatically. The generator
creates a scaffold; it does not export configuration from a site or infer dependencies.
Do not add another content composition as an incidental dependency of a shared recipe.

## Composition integrations

Optional content and included component recipes may bundle `recipes/<composition>/recipe.yml`.
The starter applies the integrations of optional sides and then of the included
components, in that order, so components can extend the Content fields the sides create.
For each recipe, it checks `web/recipes/$recipe/recipes/${content#sous-}` and applies
it when the directory exists.
Use `paragraphs` or `core-layout-builder` as the composition folder name.
Admin only leaves `content` empty and skips these integrations.

Content recipes use integrations to attach fields or enable layouts; component
recipes may adapt the selected host. The selected base composition supplies the
extension code, so an integration must not install the other composition.
See the [Events](packages/sous-events/recipes) and
[News Post](packages/sous-news-post/recipes) integrations for examples.

## Dashboard blocks

Use `editor` section 1 for work needing action now, `editor` section 2 for content
lists, and `site_admin` section 1 for site administration. Target the regions in
the [Dashboard layout contract](packages/sous-dashboard/README.md#layout-contract).

Ship `views.view.dashboard_<topic>` with one block display, the `dashboard` tag,
at most ten items, a more link, and plain empty text with no links.
Dashboard renders links in empty text as buttons whose Gin colors fail contrast,
and people add content and media from the Editor's Create block.
Use an access permission the intended audience already has.

Add one `addComponentToLayout` action under the optional
`'?dashboard.dashboard.<id>'` target. Include a layout-to-region map,
`default_region: content`, and `position: 99`. Generate its UUID once with
`uuidgen` and keep that constant in the recipe. Follow the
[Profile recipe](packages/sous-profile/recipe.yml) example.

Never require or apply Sous Dashboard from another package. A baseline package
that adds a block comes after Dashboard in the starter's `baseline_recipes`.
Apply the recipe twice and verify exactly one component remains in its intended
section and region, the intended audience can render it, and anonymous users see
nothing.

## Drupal compatibility

All packages and the recipe template require Drupal `^11.4`, the floor tested
by recipe CI.

## Checks

```sh
npm test
npm run format
npm test
```

`npm test` validates the packages, checks JavaScript and PHP syntax, and runs
ESLint and Prettier. Review any changes from `npm run format` before committing.

After these checks, CI applies the baseline, Events, News Post, Profile, Content
List, SEO, Alerts, and Search on fresh SQLite sites for `admin`, `content-types`,
`core-layout-builder`, and `paragraphs` and runs the composition and package
verification scripts. The Layout Builder and Paragraphs sites also get Gallery
and the matching Events, News Post, and Content List integrations.

Contributors also try changes in the application where they will be used.

Maintainer tools, repository dependencies and checks belong outside `packages/`;
consumer ZIPs contain only recipe files and their notices.

## Build locally

From a committed checkout, substitute the full commit SHA for `COMMIT`:

```sh
npm run build -- --version 2.0.0 --reference COMMIT --output build \
  --dist-base-url https://github.com/fourkitchens/sous-drupal-packages/releases/download/2.0.0/
```

The ignored `build/` directory contains one ZIP per package, a release manifest
with checksums, and the static Composer index/catalog. Each ZIP has the package's
files directly at its root. The build command does not publish anything.

Published manifests retain their own Composer metadata. To include history in an
offline build, pass `--previous PATH/release-manifest.json` for each earlier release.
The automatic release workflow loads that history itself.

## Commit and review

Use Conventional Commits for commits and squash titles. Use `fix:` for
corrections, `perf:` for performance improvements, `feat:` for additions, and
a `BREAKING CHANGE:` footer or `!` for incompatible changes. The default analyzer
assigns no increment to `docs`, `ci`, `refactor`, `test`, or `chore` commits.
After the initial `2.0.0` release, the collection's release plugin sets a patch
minimum for commits eligible for release.

A release applies to the whole collection, while installations still select
individual packages. Source manifests omit `version`; release tooling supplies
it and coordinates sibling constraints in the distributed metadata. Release
assets and the Composer index are generated, not committed. See
[RELEASING.md](docs/RELEASING.md).
