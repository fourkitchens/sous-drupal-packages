# Contributing

Edit `packages/<basename>/` for production recipe changes. Each package owns its
Composer manifest, recipe, configuration, license and guide. Keep the existing
Composer names, directory basenames, nested Linkit recipe and explicit Admin
migration paths stable.

Use Node `24.22.1` (`nvm use`), npm `11.21.1`, PHP `8.4+`, Composer `2.10.3`, Git,
`zip` and `unzip`. PHP needs SQLite/PDO SQLite, GD, mbstring, XML and ZIP. The
repository has no npm dependencies, install step or npm lockfile. CI uses PHP
8.4; newer compatible local PHP versions may run the fixture too.

## Checks

```sh
npm run check
npm test
npm run test:consumer
```

The first two commands validate boundaries and archive/index/publication
contracts without network access. The consumer command creates a temporary
HTTP index from actual built ZIPs, isolated Composer homes/caches and disposable
SQLite Drupal fixtures. It requires internet access and never touches an
existing site database. Run one case while iterating:

```sh
npm run test:consumer -- --case core-layout-builder
```

Cases are `default`, `core-layout-builder`, `paragraphs` and `admin-only`. Add
`--config-split` to test any choice with native configuration splits;
`--case config-split` is an alias for default content with splits. With no case
selection all eight combinations run. Use `--keep` to retain a failing fixture for diagnosis. See the
command output for its temporary location. Test-only root patches reproduce
the starter's dependency policy and do not ship in packages. Tests verify that
native recipe-unpack exclusions retain only selected Sous recipes and that a
clean clone can restore them from a cold Composer cache. They also validate
native Drupal recipes, authoring configuration and mutually optional payloads.

Probes belong under `tests/recipes/<basename>/`; integrations belong under
`tests/`. Never add maintainer tests/tools or release dependencies inside a
production package. A fixture is not a replacement for the full starter
generated-theme and environment acceptance gate.

## Building a candidate

From a committed checkout, substitute the full reviewed commit for `COMMIT`:

```sh
node tools/build.mjs --version 2.0.0 --reference COMMIT --output build \
  --dist-base-url https://github.com/fourkitchens/sous-drupal-packages/releases/download/2.0.0/
```

Output is ignored by Git. Each ZIP contains a single package directly at its
root. Build twice with identical inputs to compare hashes; tests do this too.
For an offline historical snapshot add `--previous PATH/release-manifest.json`
for every prior release. Published metadata is loaded from those manifests,
not today's Composer source. Duplicate versions with changed bytes are errors.

## Testing source edits in a separate application

An explicit Composer path repository can map `packages/*` with
`options.versions` set to `2.0.0` for all nine IDs. Put it before the scoped
production index and retain the root's exact native unpack exclusions. Use a
disposable application; do not replace customized recipe folders without review.
Path installs are contributor conveniences, while CI consumer cases install
real package ZIPs. Before releasing an application remove path overrides and
perform a real update/install from public immutable assets with a clean cache.
`composer update --lock` alone does not convert locked path distributions to ZIPs.

## Review expectations

Common packages must require neither optional composition. Layout Builder must
never copy/install Paragraphs code or config; Paragraphs must not enable Layout
Builder on content displays or add its override fields. Core Navigation enables
the shared core module independently. Preserve Admin's strict Gin/login
whitelist and apply it once. New
application settings, environment policies and project theme builds belong in
the starter. Document dependency changes and include a relevant runtime probe.

The existing repositories remain historical download sources. Do not move old
tags, repoint legacy `dev-main`, delete repositories or close their PRs as part
of a source change here. Publication requires the separate reviewed process in
[docs/RELEASING.md](docs/RELEASING.md).
