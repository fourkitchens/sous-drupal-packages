# Sous Config Split

`fourkitchens/sous-config-split` · `drupal-recipe` · GPL-2.0-or-later

Optional native Config Split defaults for projects whose modules or stored
configuration differ by environment. Requiring this recipe also requires
`drupal/config_split:^2.0.2`; it is no longer a starter-bundled recipe. It adds no
development/mail module, Config Ignore rules, settings loader or runtime policy.

The always-active `_splits` definition routes `sous_local` and `sous_staging`
definitions into `config/_splits`. Its own bootstrap definition stays in the
complete `config/default` export so a fresh import can discover the definitions
before the environment values. Native Config Split priority handles ordering.

| Project directory | Ownership |
| --- | --- |
| `config/default` | Complete production base and `_splits` bootstrap |
| `config/_splits` | Project-owned environment split definitions |
| `config/local` | Local split values/modules |
| `config/staging` | Staging split values/modules |

Create these directories outside the web root before export/import and commit
them with the site's exported configuration. Relative recipe folder settings
assume the Sous `web` Drupal root. Both environment splits have empty lists and
stored `status: false`; project runtime settings choose the applicable split.
A typical policy enables local in DDEV, staging for dev/test/multidev and neither
in production. Hosting configuration must set the intended policy explicitly.
Projects can add more native definitions and directories; update
`_splits.complete_list` and project-owned settings together.

Apply the recipe once, configure the project-owned lists and export through
normal native `config:export`/deployment commands. Do not store active runtime
flags in the complete export. Do not combine split-owned modules with
`config_exclude_modules`; core exclusion is not a split or uninstall policy.
Package updates preserve existing definitions. Renaming a legacy nonproduction
split or moving values requires reviewed configuration changes and a fresh
import/export check; changing a directory alone is insufficient.

## Installation and application

This source is a **candidate for the coordinated 2.0 release**; it has not been
published by this migration. After publication, configure the scoped Sous
Composer index, Drupal repositories and installer paths as described in the
[collection guide](https://github.com/fourkitchens/sous-drupal-packages/blob/main/README.md). Retain this package in Composer using the
root native recipe-unpack exclusion setting. From a configured consuming site:

```sh
ddev composer require fourkitchens/sous-config-split:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-config-split
ddev drush cache:rebuild
```

Recipe application is explicit. Requiring or updating the package does not
apply configuration again. Existing sites keep their database and complete
configuration export and adopt changes through reviewed native migrations.

## Contributing and checks

Source, issues and releases live in
[fourkitchens/sous-drupal-packages](https://github.com/fourkitchens/sous-drupal-packages).
See the [contributor guide](https://github.com/fourkitchens/sous-drupal-packages/blob/main/CONTRIBUTING.md). Maintainer probes live in
[`tests/recipes/sous-config-split`](https://github.com/fourkitchens/sous-drupal-packages/tree/main/tests/recipes/sous-config-split) when this package has
runtime checks; they are mounted into disposable fixtures and excluded from
consumer ZIPs. A package source checkout is not a runnable Drupal application.

[GPL-2.0-or-later](LICENSE); dependency and original source notices remain
applicable. Source commit provenance is recorded in the collection.
