# Sous Profile

`fourkitchens/sous-profile` · `drupal-recipe` · GPL-2.0-or-later

Adds a Profile content type with required First Name and Last Name fields.
The title stays hidden and is generated again from the name on every save.
Pathauto uses the generated title for URLs such as `/profile/ada-lovelace`.
Optional Profile Image accepts one Image media item; Bio uses Basic HTML.
Content and Teaser tabs organize the form, with Metadata in the sidebar.

The full display shows the profile image and Bio; the generated title supplies
the name. Teaser mode uses the common teaser image and text fields. Profiles
start in Draft and use the shared Editorial workflow and scheduling controls.
They cannot be placed in menus. The model works without a content composition.

Use `profiles:embed_profiles` as a Content List source. It shows 12 published
profile teasers by default, ordered by Last Name, First Name and ID, with no
exposed filters and a **See all** link to `/profiles`.

The `page_profiles` display at `/profiles` shows 24 teasers per page. Its exposed
Search field (`profile_search`) finds terms in First Name, Last Name or Bio.

The same View's `entity_browser` display lets editors filter and select
published profiles by title.

## Installation and application

Choose Profile from the installer's optional sides, or configure the
[collection guide](../../README.md)'s repositories and installer paths, then run:

```sh
ddev composer require fourkitchens/sous-profile:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-profile
```

Recipes apply once and then unpack; sites own the resulting dependencies and
complete configuration export. Package updates do not replay configuration.

## Contributing

Edit this package in Sous Drupal Packages. See the
[contributor guide](../../CONTRIBUTING.md) for scaffolding and checks.

[GPL-2.0-or-later](LICENSE); dependency and package notices remain applicable.
