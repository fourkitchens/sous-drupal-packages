# Sous Content Types

`fourkitchens/sous-content-types` · `drupal-recipe` · GPL-2.0-or-later

Provides Page and Frontpage content types with Body, SEO metadata, teaser image
and text, shared fields, authoring displays and title-based Pathauto aliases.
Page additionally has tags. Media and Formats are explicit prerequisite recipes;
Pathauto and the modules referenced by the fields/widgets are direct dependencies.
Admin is independently optional and is never reapplied by this recipe.

Create content at `/node/add/page` or `/node/add/frontpage`, enter the required
SEO Title and write Body in the Content tab. The default display renders Body;
Page's teaser display uses its dedicated teaser fields. The recipe provides the
Frontpage content model, not a pre-created node or an automatic site-front-page
route assignment.

This content recipe adds neither Paragraphs host fields nor Layout Builder
display/override configuration. Core Navigation can enable the core Layout
Builder module transitively; its module state alone does not select the Sous
layout composition. Choose
[`sous-core-layout-builder`](https://github.com/fourkitchens/sous-drupal-packages/blob/main/packages/sous-core-layout-builder/README.md) or
[`sous-paragraphs`](https://github.com/fourkitchens/sous-drupal-packages/blob/main/packages/sous-paragraphs/README.md) when that project needs a
composition. Both retain Body. Do not replay the base recipe after a composition
has changed its displays; strict defaults protect those changes. Matching
configuration is accepted independently of generated UUID/default hashes;
conflicting content models require explicit project-owned configuration changes.

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
