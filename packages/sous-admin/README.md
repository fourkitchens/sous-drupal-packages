# Sous Admin

`fourkitchens/sous-admin` · `drupal-recipe` · GPL-2.0-or-later

Supplies Gin, core Navigation and its Content menu, the Superuser administrator
role, native management Views, and editorial/administration conveniences. It
installs the Node, Media, Media Library, Block Content, Taxonomy and Views
providers used by those management screens. It does not create Page/Frontpage
or apply Sous Media bundle defaults.

The package requires Sous Formats but applies only its shared
`recipes/sous-formats/recipes/linkit` subrecipe. Install recipes under the Drupal
root's `recipes/` directory (`web/recipes` in Sous) so this native include resolves.
Admin alone does not install CKEditor/text-format defaults. An existing Linkit
profile, including its UUID and customized matchers, remains unchanged.

Apply Admin once to a supported Minimal baseline. The strict Gin/login import
list intentionally validates that initial baseline. Native Views stay outside
that list so existing project-owned Views are preserved. Reapplying Admin after
its own Gin actions or site customization can fail the strict check; package
updates are not a reason to reapply it.

The `superuser` role uses `is_admin: true` and receives every permission,
including future module permissions. Admin preserves an existing role and its
account assignments. Existing sites that received it from Sous Emulsify keep
it in their complete configuration export. If the role is missing, review the
explicit [missing-role migration](migrations/2026-10-superuser-role/README.md)
instead of replaying Admin; it creates a missing role and never promotes or
replaces an existing customized role.

Autosave Form, Config Log, Entity Usage, Login History/Destination, Save and Edit,
Scheduler, Tagify, Trash, Views AJAX History and Views Bulk Operations remain
installed conveniences. A project can replace them through a project-owned
recipe or reviewed module uninstall/configuration migration. Removing a Composer
requirement or recipe entry alone does not uninstall a module or preserve its data.

## Installation and application

This source is a **candidate for the coordinated 2.0 release**; it has not been
published by this migration. After publication, configure the scoped Sous
Composer index, Drupal repositories and installer paths as described in the
[collection guide](https://github.com/fourkitchens/sous-drupal-packages/blob/main/README.md). Retain this package in Composer using the
root native recipe-unpack exclusion setting. From a configured consuming site:

```sh
ddev composer require fourkitchens/sous-admin:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-admin
ddev drush cache:rebuild
```

Recipe application is explicit. Requiring or updating the package does not
apply configuration again. Existing sites keep their database and complete
configuration export and adopt changes through reviewed native migrations.

## Contributing and checks

Source, issues and releases live in
[fourkitchens/sous-drupal-packages](https://github.com/fourkitchens/sous-drupal-packages).
See the [contributor guide](https://github.com/fourkitchens/sous-drupal-packages/blob/main/CONTRIBUTING.md). Maintainer probes live in
[`tests/recipes/sous-admin`](https://github.com/fourkitchens/sous-drupal-packages/tree/main/tests/recipes/sous-admin) when this package has
runtime checks; they are mounted into disposable fixtures and excluded from
consumer ZIPs. A package source checkout is not a runnable Drupal application.

[GPL-2.0-or-later](LICENSE); dependency and original source notices remain
applicable. Source commit provenance is recorded in the collection.
