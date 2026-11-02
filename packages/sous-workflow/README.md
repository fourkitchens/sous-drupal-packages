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

This is a shared dependency of Sous Admin and the node content recipes.
To use it independently, configure the repository and installer paths in the
[collection guide](../../README.md), then run:

```sh
ddev composer require fourkitchens/sous-workflow:^2.0
```

Sous Drupal Project already registers the persistent single-state patch for
[SCMI #3270462](https://www.drupal.org/project/scheduler_content_moderation_integration/issues/3270462).
Other consumers need Composer Patches 2 allowed in `config.allow-plugins` and a
root manifest configured through `extra.composer-patches.patches-file`:

```sh
ddev composer require cweagans/composer-patches:^2.0
mkdir -p patches
cp web/recipes/sous-workflow/assets/patches/*.patch patches/
```

Merge [scheduler.json](assets/patches/scheduler.json)'s entries into that manifest,
then apply the patch before the recipe:

```sh
ddev composer patches-relock
ddev composer patches-repatch
ddev drush recipe /var/www/html/web/recipes/sous-workflow
```

Keep the patch and lock after recipe unpacking. Remove this adaptation when a
released SCMI version includes automatic hidden single-state choices.

Attach project content types with `addNodeTypes` in a project-owned recipe or
through Drupal's workflow administration. Applying this recipe alone moderates
no content types.

Recipes apply once and then unpack; sites own the resulting dependencies and
complete configuration export. See the [contributor guide](../../CONTRIBUTING.md)
for source changes.

[GPL-2.0-or-later](LICENSE); dependency and package notices remain applicable.
