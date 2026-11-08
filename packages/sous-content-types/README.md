# Sous Content Types

`fourkitchens/sous-content-types` · `drupal-recipe` · GPL-2.0-or-later

Provides the default Page and Frontpage composition with Body. It applies
[Content Base](../sous-content-base), which owns shared fields, authoring tabs,
teasers and title-based Pathauto aliases, and attaches Body to both types.
Formats and Media come through the shared base;
Admin remains independently optional.

Create content at `/node/add/page` or `/node/add/frontpage`. Title appears above
the horizontal tabs, and Body lives in Content; there is no separate SEO Title
field or Info tab. Page's teaser display uses its dedicated teaser fields.
Frontpage is a content model, not a pre-created node or automatic front-page
route assignment.

Choose [Layout Paragraphs](../sous-paragraphs) for component-only Content tabs,
or [Core Layout Builder](../sous-core-layout-builder) for native layouts with
Body and matching custom blocks. Neither choice requires both compositions.
Recipes define fresh-install defaults; existing sites own field/content
migrations and should not replay a conflicting content model over their data.

## Installation and application

Configure the Sous Composer index, Drupal repositories and installer paths as
shown in the [collection guide](../../README.md). Once the first collection
release is published, from a configured consuming site:

```sh
ddev composer require fourkitchens/sous-content-types:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-content-types
ddev drush cache:rebuild
```

Recipes are applied once and then unpacked. Package updates do not reapply
configuration. Projects maintain their complete configuration export.

## Contributing

Edit this package here in Sous Drupal Packages. See the
[contributor guide](../../CONTRIBUTING.md) for scaffolding and syntax/style checks.
A package source checkout is not a runnable Drupal application.

[GPL-2.0-or-later](LICENSE); dependency and package notices remain applicable.
