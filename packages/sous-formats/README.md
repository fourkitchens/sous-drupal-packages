# Sous Formats

`fourkitchens/sous-formats` · `drupal-recipe` · GPL-2.0-or-later

Provides Basic, Full, Limited and Restricted HTML formats, their editor defaults,
and the shared default Linkit profile. Linkit is a direct Composer dependency.
No content composition or media bundle recipe is required.

`recipes/linkit` is a separate native subrecipe so Admin can share the default
node, media and external matchers without applying the full CKEditor/format
recipe. Keep this nested directory in the installed package and install packages
under `web/recipes` in Sous. An existing project-owned Linkit profile is retained
with its UUID and matcher settings, including omitted/custom matchers.

The full Formats recipe enables CKEditor 5, Linkit, Node and Media. The nested
Linkit subrecipe enables its own providers and no editor defaults. Its media
matcher uses Linkit's built-in media substitution to link to the source file;
media bundles are supplied independently. Linkit-enabled editors retain their
module/profile configuration dependencies. Existing editor/format configuration
must match the full recipe; only the shared Linkit profile is preserved when it
differs. Applying that small subrecipe to create a missing profile is supported;
replaying full Formats is not an existing-site update mechanism.

Text formats are security configuration: review permitted HTML and the roles
allowed to use each format in the generated project. Package updates do not
replace an already-applied project's editor or format configuration.

## Installation and application

Configure the Sous Composer index, Drupal repositories and installer paths as
shown in the [collection guide](../../README.md). Retain this package using the
root native recipe-unpack exclusion setting. Once the first collection release
is published, from a configured consuming site:

```sh
ddev composer require fourkitchens/sous-formats:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-formats
ddev drush cache:rebuild
```

Recipe application is explicit. Requiring or updating the package does not
reapply configuration. Projects maintain their complete configuration export
after initial recipe application.

## Contributing

Edit this package here in Sous Drupal Packages. See the
[contributor guide](../../CONTRIBUTING.md) for scaffolding and syntax/style checks.
A package source checkout is not a runnable Drupal application.

[GPL-2.0-or-later](LICENSE); dependency and package notices remain applicable.
