# Sous Media

`fourkitchens/sous-media` · `drupal-recipe` · GPL-2.0-or-later

Provides File, Image and Video media bundles with shared fields, form/view
displays, Media Library defaults, media tags and authoring dependencies. It is
shared by Content Types and Paragraph Types and has no dependency on either
optional content composition.

The strict configuration list protects the recipe's bundles, fields, displays,
settings and non-View provider defaults. Native `media` and `media_library`
management Views are imported only if missing and retain project-owned
configuration when already present, including Admin's earlier native imports.
This supports Admin-before-Media and standalone Media without replaying or
replacing project Views.

Core's `media.full` and `media.media_library` modes are imported from their
providers, preserving matching configuration without copying core defaults.
Scheduler fields stay hidden on the three media forms. The consuming project
owns its complete export, including imported core modes and project overrides.

File and Image uploads use Drupal's Media Library upload form. Image editing
retains Focal Point. When the starter adopts this recipe, it can remove the named
`dropzone` Composer repository while retaining the general Drupal library
installer path. Existing sites should check their custom widgets and assets
before uninstalling DropzoneJS and removing its Composer dependencies.

## Installation and application

Configure the Sous Composer index, Drupal repositories and installer paths as
shown in the [collection guide](../../README.md). Once the first collection
release is published, from a configured consuming site:

```sh
ddev composer require fourkitchens/sous-media:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-media
ddev drush cache:rebuild
```

Recipes are applied once and then unpacked. Package updates do not reapply
configuration. Projects maintain their complete configuration export.

## Contributing

Edit this package here in Sous Drupal Packages. See the
[contributor guide](../../CONTRIBUTING.md) for scaffolding and syntax/style checks.
A package source checkout is not a runnable Drupal application.

[GPL-2.0-or-later](LICENSE); dependency and package notices remain applicable.
