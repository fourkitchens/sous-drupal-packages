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

Use `editor` section 2 for work needing action now, `editor` section 3 for content
lists, and `site_admin` section 1 for site administration. Target the regions in
the [Dashboard layout contract](packages/sous-dashboard/README.md#layout-contract).

Section 0, Status, is full width on both dashboards and comes first. Packages add notices there at position 0, ahead of any baseline block.
Follow the [Sous Alerts recipe](packages/sous-alerts/recipe.yml) for notice placement.

For a list, ship `views.view.dashboard_<topic>` with one block display, the `dashboard` tag,
at most ten items, a more link, and plain empty text with no links.
Dashboard renders links in empty text as buttons whose Gin colors fail contrast,
and people add content and media from the Editor's Create block.
Use an access permission the intended audience already has.

Add one `addComponentToLayout` action under the optional
`'?dashboard.dashboard.<id>'` target. Include a layout-to-region map,
`default_region: content`, and `position: 99` for lists. Generate its UUID once with
`uuidgen` and keep that constant in the recipe. Follow the
[Profile recipe](packages/sous-profile/recipe.yml) example.

Never require or apply Sous Dashboard from another package. A baseline package
that adds a block comes after Dashboard in the starter's `baseline_recipes`.
Apply the recipe twice and verify exactly one component remains in its intended
section and region and the intended audience can render it. Unless the block is
a public notice such as Sous Alerts, verify anonymous users see nothing.

## Create links

The package that owns a content or media type ships its Create link as default
content in `packages/<owner>/content/menu_link_content/dashboard-create-<type>.yml`
and installs `menu_link_content` in its recipe.

Use the type's label as the title, `dashboard-create` as the menu, and
`internal:/node/add/<type>` or `internal:/media/add/<type>` as the link URI.
For `dashboard-create`, do not set a parent: links form one flat list.
Set `rediscover: true`.
Use weight 0 for content-type links and weights 50 and up for media links.
Generate each link's UUID once with `uuidgen` and keep it stable.

Links for other content a package owns, such as Sous Alerts' Alert, use weight 10.
They may also go under Navigation's Create in the `content` menu with parent
`navigation.create`.

Only link types owned by that package. A link to another package's type becomes
a dead link when that package is absent. Do not require Sous Dashboard: the link
stays hidden when its Create menu is absent. See Sous Media's
[File](packages/sous-media/content/menu_link_content/dashboard-create-file.yml)
and [Image](packages/sous-media/content/menu_link_content/dashboard-create-image.yml)
links for examples.

## Navigation links

A package that adds an admin list may ship a top-level link in core Navigation's
Content menu as `content/menu_link_content/content-<name>.yml`.
Use `content` as the menu, no parent, weight 10 (after Content and Media, before
Trash), and `rediscover: true`. Set an icon from Navigation's `navigation` icon
pack in the link options, and generate its UUID once with `uuidgen`.
Route access decides who sees the link. See Sous Admin's
[Menus link](packages/sous-admin/content/menu_link_content/content-menus.yml)
for an example.

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
