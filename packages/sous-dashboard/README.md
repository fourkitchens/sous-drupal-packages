# Sous Dashboard

`fourkitchens/sous-dashboard` · `drupal-recipe` · GPL-2.0-or-later

Adds Site admin and Editor dashboards that Superusers and Site Editors land on
after signing in. Apply it after Sous Admin and Sous Formats: Admin supplies
Navigation, the Content menu and the Site Editor role; Formats supplies Basic HTML.
The recipe applies Sous Workflow and imports Scheduler's scheduled-content list.

Superusers see Site admin first and can switch between Site admin and Editor
using Dashboard's tabs. Site Editors see Editor. Other authenticated accounts
receive no dashboard access. Dashboard supplies the sign-in redirect and tabs.
Only Superusers can rearrange blocks at `/admin/structure/dashboard`.

Site admin shows site status, recent errors and warnings, new accounts, recent
sign-ins, Top page not found errors and Top access denied errors. Editor provides
welcome text, the Create menu, drafts, scheduled changes, recently updated content
and accessibility alerts. Lists show up to ten items. Logs, sign-ins and
accessibility results refresh every five minutes.

The Editor's Create menu is a flat list with one link per content or media type,
supplied by the package that owns it. A link appears only when the account can
create that type. Sous Media supplies links for File, Image, Video and Video File;
content-type links follow separately.

For a type added by hand, add its link at Structure → Menus → Create. Delete
the link when deleting the type, or it becomes a dead link.

## Layout contract

Packages can append blocks to these zero-based sections and regions. Preserve
the baseline blocks and their order. Site owners can customize the layouts after
installation; reapplying this recipe preserves existing dashboard configuration.

| Dashboard    | Section | Label        | Layout                          | Region    | Baseline blocks                          |
| ------------ | ------- | ------------ | ------------------------------- | --------- | ---------------------------------------- |
| `site_admin` | 0       | Status       | `layout_onecol`                 | `content` | Site status                              |
| `site_admin` | 1       | Activity     | `layout_twocol_section` (50–50) | `first`   | Recent errors and warnings; New accounts |
| `site_admin` | 1       | Activity     | `layout_twocol_section` (50–50) | `second`  | Recent sign-ins                          |
| `site_admin` | 2       | Top errors   | `layout_twocol_section` (50–50) | `first`   | Top page not found errors                |
| `site_admin` | 2       | Top errors   | `layout_twocol_section` (50–50) | `second`  | Top access denied errors                 |
| `editor`     | 0       | Start        | `layout_twocol_section` (67–33) | `first`   | Welcome and help                         |
| `editor`     | 0       | Start        | `layout_twocol_section` (67–33) | `second`  | Create                                   |
| `editor`     | 1       | Your work    | `layout_twocol_section` (50–50) | `first`   | Drafts in progress                       |
| `editor`     | 1       | Your work    | `layout_twocol_section` (50–50) | `second`  | Scheduled changes                        |
| `editor`     | 2       | Site content | `layout_twocol_section` (50–50) | `first`   | Recently updated                         |
| `editor`     | 2       | Site content | `layout_twocol_section` (50–50) | `second`  | Accessibility alerts                     |

## Install and apply

Configure the [Sous Composer repository](../../README.md), then require the
recipe and apply it after the baseline prerequisites:

```sh
ddev composer require fourkitchens/sous-dashboard:^2.0
ddev drush recipe /var/www/html/web/recipes/sous-dashboard
ddev drush cache:rebuild
```

Recipes are applied once. Package updates do not reapply configuration; projects
maintain their complete configuration export. See [Contributing](../../CONTRIBUTING.md)
for the collection's checks.

[GPL-2.0-or-later](LICENSE); dependency and package notices remain applicable.
