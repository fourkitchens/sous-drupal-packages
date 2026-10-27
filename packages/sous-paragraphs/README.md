# Sous Paragraphs

`fourkitchens/sous-paragraphs` · `drupal-recipe` · GPL-2.0-or-later

Optional Paragraphs composition for both Sous Page and Frontpage. It applies
Content Types and Paragraph Types, installs Paragraphs Features, and adds the
shared `field_content` host field and its editor/display to both node types.
Both Content tabs retain the original Body field alongside components and keep
the base recipe's Info, Teaser and other authoring tabs.

Require/apply this recipe only when a project chooses Paragraphs. Neither the
default content model nor the Core Layout Builder composition depends on it.
No artificial Composer conflict prevents a project from deliberately designing
its own combined composition, but the standard installer selects one path.

Package updates do not rename existing fields or change stored content.
Projects own subsequent changes to their content model.

The collection distributes this package under GPL-2.0-or-later. The additional
copyright and permission notice is retained in [NOTICE](NOTICE).

## Installation and application

Configure the Sous Composer index, Drupal repositories and installer paths as
shown in the [collection guide](../../README.md). Retain this package using the
root native recipe-unpack exclusion setting. Once the first collection release
is published, from a configured consuming site:

```sh
ddev composer require fourkitchens/sous-paragraphs:^1.0
ddev drush recipe /var/www/html/web/recipes/sous-paragraphs
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
