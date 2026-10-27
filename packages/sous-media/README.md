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

DropzoneJS requires `dropzone/dropzone:^6.3.5`, a browser library distributed from
an upstream release ZIP. Composer ignores repositories declared by dependencies,
so the **consuming root** must provide this package repository in addition to the
Sous index and Drupal repository:

```json
{
  "type": "package",
  "package": {
    "name": "dropzone/dropzone",
    "version": "6.3.5",
    "type": "drupal-library",
    "extra": { "installer-name": "dropzone" },
    "dist": {
      "url": "https://github.com/enyo/dropzone/releases/download/dropzone%406.3.5/dist.zip",
      "type": "zip"
    }
  }
}
```

Retain the root installer path for Drupal libraries (`web/libraries/{$name}`).
Dependencies retain their own licenses; this recipe does not vendor Dropzone.

## Installation and application

Configure the Sous Composer index, Drupal repositories and installer paths as
shown in the [collection guide](../../README.md). Retain this package using the
root native recipe-unpack exclusion setting. Once the first collection release
is published, from a configured consuming site:

```sh
ddev composer require fourkitchens/sous-media:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-media
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
