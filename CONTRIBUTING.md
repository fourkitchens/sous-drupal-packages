# Contributing

Edit `packages/<name>/` for recipe changes. Each package owns its Composer
manifest, recipe, configuration, license and guide. Keep dependencies explicit;
common packages should not require either optional content composition.

Use Node.js `24.22.1` (`nvm use`), npm `11.21.1`, PHP `8.4+`, Composer and `zip`.
Install repository tooling with `npm ci`.

## Create a recipe

```sh
npm run create-package sous-event
```

This copies `recipe-template/` into `packages/sous-event`, substitutes its Composer
name (`fourkitchens/sous-event`) and human-readable name, and leaves the new recipe
ready to customize. An existing package is never overwritten.

1. Edit the generated README and descriptions to explain the recipe's purpose.
2. Add configuration YAML under `config/` and update `recipe.yml` with the modules,
   prerequisite recipes and configuration actions it needs.
3. Declare required Drupal modules and Sous packages in `composer.json`. Composer
   dependencies download code; the recipe must explicitly install/apply what it uses.
4. Run `npm test`, review the generated files and try the recipe in your application.

The archive builder discovers package directories automatically. The generator
creates a scaffold; it does not export configuration from a site or infer dependencies.
Do not add another content composition as an incidental dependency of a shared recipe.

## Checks

```sh
npm test
npm run format
npm test
```

Checks live under `devops/tests` and cover JavaScript/PHP syntax, code-sniffing and
code/JSON/YAML formatting. The formatting command fixes supported files; review
its changes before committing. CI runs these checks without installing Drupal,
creating databases or applying recipes. Contributors review and try feature or
configuration changes in the application where they will be used.

Maintainer tools, repository dependencies and checks belong outside `packages/`;
consumer ZIPs contain only recipe files and their notices.

## Build locally

From a committed checkout, substitute the full commit SHA for `COMMIT`:

```sh
npm run build -- --version 1.0.0 --reference COMMIT --output build \
  --dist-base-url https://github.com/fourkitchens/sous-drupal-packages/releases/download/1.0.0/
```

The ignored `build/` directory contains one ZIP per package, a release manifest
with checksums, and the static Composer index/catalog. Each ZIP has the package's
files directly at its root. The build command does not publish anything.

Published manifests retain their own Composer metadata. To include history in an
offline build, pass `--previous PATH/release-manifest.json` for each earlier release.
The automatic release workflow loads that history itself.

## Commit and review

Use Conventional Commits: `fix:` for corrections, `feat:` for additions, and a
`BREAKING CHANGE:` footer or `!` for incompatible changes. Other merge commits
produce a patch release. Keep the squash-merge title in this format as well.

A release applies to the whole collection, while installations still select
individual packages. Source manifests omit `version`; release tooling supplies
it and coordinates sibling constraints in the distributed metadata. Release
assets and the Composer index are generated, not committed. See
[RELEASING.md](docs/RELEASING.md).
