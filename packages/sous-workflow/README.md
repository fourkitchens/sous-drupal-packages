# Sous Workflow

`fourkitchens/sous-workflow` · `drupal-recipe` · GPL-2.0-or-later

Adds the shared Editorial workflow, Content Moderation, Scheduler and Scheduler
Content Moderation Integration. Content begins as Draft; publishing makes a
default revision, and archiving unpublishes a default revision. Every state can
transition to Draft, Published or Archived.

Admin installs the workflow without creating content types. Content Base,
Events and News Post add only their own node types with Drupal's `addNodeTypes`
recipe action. Existing workflow settings and type associations are preserved;
Media is not moderated by this recipe.

The content recipes provide optional publish/unpublish dates in the Scheduling
sidebar and create new revisions. Their single Published/Archived target states
are automatic hidden values; Scheduler cron processes those transitions.

## Installation and application

Before requiring this recipe, follow the [shared patch setup](../../README.md#shared-patches).
Copy the complete [2.0.0 patch directory](https://github.com/fourkitchens/sous-drupal-packages/tree/2.0.0/patches) into the site root;
its [manifest](https://github.com/fourkitchens/sous-drupal-packages/blob/2.0.0/patches/patches.json) is read by root Composer.
Sous Drupal Project already provides this setup.

The shared manifest includes the single-state patch for
[SCMI #3270462](https://www.drupal.org/project/scheduler_content_moderation_integration/issues/3270462).
Remove this adaptation when a released SCMI version includes automatic hidden
single-state choices.

```sh
ddev composer require fourkitchens/sous-workflow:^2.0
ddev composer patches-relock
ddev composer patches-repatch
ddev drush recipe /var/www/html/web/recipes/sous-workflow
```

Keep the root patches and patch lock after recipe unpacking.

Attach project content types with `addNodeTypes` in a project-owned recipe or
through Drupal's workflow administration. Applying this recipe alone moderates
no content types.

Recipes apply once and then unpack; sites own the resulting dependencies and
complete configuration export. See the [contributor guide](../../CONTRIBUTING.md)
for source changes.

[GPL-2.0-or-later](LICENSE); dependency and package notices remain applicable.
