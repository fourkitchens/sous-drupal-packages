# Sous Admin

`fourkitchens/sous-admin` · `drupal-recipe` · GPL-2.0-or-later

Supplies Gin, core Navigation and its Content menu, the Superuser administrator
and Site Editor roles, native management Views, and editorial tools. It
installs the Node, Media, Media Library, Block Content, Taxonomy and Views
providers used by those management screens. It does not create Page/Frontpage
or apply Sous Media bundle defaults. It applies [Sous Workflow](../sous-workflow)
without assigning content types to it.

Admin makes the No Non-breaking Space filter available without adding text
formats. Projects that need CKEditor, Linkit and HTML filter defaults can apply
Sous Formats independently. Login Destination redirects every role to Content.

Apply Admin once to a supported Minimal baseline. The strict Gin/login import
list intentionally validates that initial baseline. Native Views stay outside
that list so existing project-owned Views are preserved. Reapplying Admin after
its own Gin actions or site customization can fail the strict check; package
updates are not a reason to reapply it.

The `superuser` role uses `is_admin: true` and receives every permission from
enabled modules. Admin preserves existing roles and account assignments.
Site Editor can create, edit, publish and delete content supplied by the recipes
applied to the site. It can use the configured content browsers and does not
grant module, configuration, role or permission administration. Keep project-owned
role changes in the complete site export.

Superusers can assign every custom role from the account edit form. Site Editors
can assign or remove only Site Editor on the Roles tab at `/user/{uid}/roles`.
Drupal gives every signed-in user the Authenticated role automatically.

Autosave Form, Config Log, Editoria11y, Entity Usage, Gin Login, Login
History/Destination, Role Delegation, Save and Edit, Tagify, Trash, Twig Tweak,
Views AJAX History and Views Bulk Operations are enabled by default. A project
can replace them through a project-owned recipe or reviewed module uninstall or
configuration change. Removing a Composer
requirement or recipe entry alone does not uninstall a module or preserve its data.

## Installation and application

Configure the Sous Composer index, Drupal repositories and installer paths as
shown in the [collection guide](../../README.md). Once the first collection
release is published, from a configured consuming site:

```sh
ddev composer require fourkitchens/sous-admin:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-admin
ddev drush cache:rebuild
```

Recipes are applied once and then unpacked. Package updates do not reapply
configuration. Projects maintain their complete configuration export.

## Contributing

Edit this package here in Sous Drupal Packages. See the
[contributor guide](../../CONTRIBUTING.md) for scaffolding and syntax/style checks.
A package source checkout is not a runnable Drupal application.

[GPL-2.0-or-later](LICENSE); dependency and package notices remain applicable.
