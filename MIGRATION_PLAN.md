# Sous package repository migration plan

This is the original planning record. See [implementation status](docs/MIGRATION.md)
for delivered work, remaining cutover steps and the Navigation/Layout Builder
acceptance adjustment discovered during runtime tests.

Keep `fourkitchens/sous-drupal-project` as the application starter and make
`fourkitchens/sous-drupal-packages` the contributor home for reusable recipes.
Publish each recipe as its own ZIP through a public static Composer index.
A generated site receives its selected composition and shared dependencies.
Choosing Core Layout Builder must never copy Paragraphs recipe/configuration
files or install Paragraphs modules.

Status: implementation plan, prepared October 27, 2026. At planning start, the
new packages repository was public and empty, on an unborn `main` branch. No source migration,
release, Pages deployment, repository archival, or downstream upgrade has been
performed by this planning step.

## Decisions and compatibility contract

- Preserve all seven existing Composer package names. Moving Git source does
  not rename packages, their installed directory basenames, or existing tags.
- Maintain two active development repositories. Retain the old repositories
  read-only for historical tags, source references and download URLs; they do
  not become automatically maintained split mirrors. Deleting them would break
  existing lockfiles and is outside this migration.
- Use one coordinated version for all packages in the collection initially.
  Candidate first versions are packages `2.0.0` and starter `8.0.0`, subject to
  a final publication audit. The current public stable maxima are Content Types
  `1.0.3` and starter `7.0.1`; the other six recipes have no stable release today.
- Publish new stable package versions in the new index only. Leave historical
  Packagist metadata, version numbers and legacy `dev-main` references intact.
  Clients adopt the new release line deliberately.
- Keep `minimum-stability: stable`; replace inter-package `dev-main` requirements
  with bounded `^2.0` constraints. CI tests all packages from the same source
  commit. A shared version never implies that every package is installed.
- Keep the initial publisher small: shell/Node tools, the existing runtimes,
  standard ZIP tooling and GitHub Actions. No Composer plugin, package splitter,
  private registry subscription, or application update framework is required.

Public Packagist expects a package manifest at the repository root. The new
subdirectories therefore need explicit per-package distribution metadata.
[Packagist package submission](https://packagist.org/about)

## Package boundaries and source move map

Every `packages/<name>/` directory keeps its own `composer.json`, `recipe.yml`,
configuration, README and license. All are `drupal-recipe` packages.

| Existing source or bundled recipe | New directory and Composer name | Responsibility |
| --- | --- | --- |
| `sous-admin` | `packages/sous-admin` / `fourkitchens/sous-admin` | Gin, Navigation, Superuser, administration defaults and native management Views |
| `sous-content-types` | `packages/sous-content-types` / `fourkitchens/sous-content-types` | Page and Frontpage, Body/SEO/teasers, shared fields and URL aliases |
| `sous-emulsify` | `packages/sous-emulsify` / `fourkitchens/sous-emulsify` | Emulsify theme/tools dependencies and frontend theme defaults |
| `sous-formats` | `packages/sous-formats` / `fourkitchens/sous-formats` | Editor/text formats and shared Linkit subrecipe |
| `sous-media` | `packages/sous-media` / `fourkitchens/sous-media` | File, Image and Video bundles and media defaults |
| `sous-paragraph-types` | `packages/sous-paragraph-types` / `fourkitchens/sous-paragraph-types` | Optional Paragraph types and their module dependencies |
| `sous-paragraphs` | `packages/sous-paragraphs` / `fourkitchens/sous-paragraphs` | Optional Paragraphs composition for both content types |
| Starter bundle `sous-layout-builder` | `packages/sous-core-layout-builder` / new `fourkitchens/sous-core-layout-builder` | Core Layout Builder composition for both content types |
| Starter bundle `sous-config-split` | `packages/sous-config-split` / new `fourkitchens/sous-config-split` | Optional native split definitions and Config Split dependency |

Reserve and verify the two new names before publication. Do not reuse the
legacy public `fourkitchens/sous-layout-builder` name: that package represented
the older Gin-based implementation. Its existing consumers and releases stay
in the legacy lane.

Fold the two starter-only composition layers into the existing packages:

- From `sous-starter-content`, move Frontpage fields/displays, alias patterns
  and Page path authoring into Content Types. Move native management View
  imports and their provider modules into Admin. Preserve Admin's strict
  Gin/login import whitelist and keep the added Views outside that list; do
  not replace it with global `strict: false`. The Views depend on native
  modules/modes, not the Page or Frontpage bundles, so this introduces no cycle.
- From `sous-starter-paragraphs`, move Frontpage's shared `field_content`
  attachment/displays and both types' Body/Content-tab repair into Paragraphs.
- These two bundle selectors were never published Composer package names;
  update installer calls and documentation rather than inventing aliases.
- Preserve `sous-formats/recipes/linkit/` and its existing root-relative recipe
  includes. Preserve `sous-admin/migrations/2026-10-superuser-role/` inside the
  Admin package.
- Content Types must not reapply Admin as a prerequisite. Admin's strict Gin
  imports can reject the settings its first application changes. The starter
  applies Admin once, followed by exactly one selected content composition.

The intended dependency graph is:

```text
Admin --------------------> Formats package / Linkit subrecipe only
Content Types ------------> Formats + Media + Pathauto
Core Layout Builder ------> Content Types + native core layout/basic-block recipes
Paragraph Types ----------> Formats + Media + Paragraphs contrib modules
Paragraphs ---------------> Content Types + Paragraph Types + Paragraphs Features
Config Split -------------> drupal/config_split
Emulsify -----------------> Emulsify theme and tools
```

Common packages must not require either optional composition. Do not introduce
an umbrella dependency that requires the entire collection or artificial
conflicts between the two compositions.

## Repository responsibilities

`Sous Drupal Packages` owns the package catalog, contributor guide, recipe
source, package tests, archive/index publisher and package release notes.
Consolidate the duplicated Node release tooling and CI here. A catalog entry
must state each package's purpose, dependencies, default/optional status and
installation choices that use it.

`Sous Drupal Project` retains Composer create-project scaffolding, DDEV/runtime
settings, installer and restore helpers, environment policies, application CI
examples and generated-site operating documentation. Its consumer artifact
contains no bundled optional recipe payloads or product-maintainer tooling.
The template keeps required empty configuration directories and its runtime
settings; optional Config Split definition YAML arrives only when selected.

Keep one integration implementation in the starter initially and let packages
CI check out a reviewed starter revision and supply the local monorepo source.
Adapt its source catalog/path overrides for `packages/*`. This avoids copying
another harness. Starter CI tests released package ZIPs by default; an explicit
validation-only input can test a reviewed packages commit. Neither a floating
branch nor a candidate override may enter a published application lockfile.

## ZIP and Composer index contract

Build one ZIP per package from one reviewed commit. Its extracted package root
must contain `composer.json` and `recipe.yml`, without a `packages/<name>` wrapper
or sibling directories. Normalize entry ordering, timestamps and permissions;
rebuild the same candidate and compare archive hashes. Preserve package basenames so the existing
`web/recipes/{$name}` installer layout and nested recipe includes still resolve.

Include required config/content/assets, nested recipes, explicit migrations,
README and license. Exclude `.git`, `.github`, caches, Node dependencies, shared
maintainer tests/tools and other packages. Mount source-only test probes into
disposable fixtures rather than requiring them inside consumer ZIPs. Validate
all referenced recipe/config/assets paths after extraction.

Publish versioned assets such as
`sous-media-2.0.0.zip` on the `2.0.0` GitHub Release. The small static index starts
as one `packages.json` advertising nine independent names and their complete
metadata. Generate the candidate metadata from each manifest. Store its full
package metadata with the release provenance manifest, then rebuild later
indexes from immutable release manifests; never reconstruct old versions from
today's changed source manifests. Include the release version, `type: drupal-recipe`,
`dist.type: zip`, the specific asset URL and reviewed commit reference. Use
**dist-only** entries so `--prefer-source` cannot clone the entire monorepo into
a selected package. Support/source links may still point contributors to GitHub.
[Composer repository format](https://getcomposer.org/doc/05-repositories.md#composer)
and [distribution metadata](https://getcomposer.org/doc/04-schema.md#dist)

The proposed index base is
`https://fourkitchens.github.io/sous-drupal-packages/`. Confirm the actual Pages
URL and HTTPS behavior before configuring clients; Pages is not enabled by
this plan. If Pages cannot be enabled, use an organization-approved public
HTTPS static host for the same index; resolve the URL before cutting over.
Publish a human-readable package catalog alongside the index and use the GitHub Actions Pages deployment workflow.
[GitHub Pages deployment](https://docs.github.com/en/pages/getting-started-with-github-pages/using-custom-workflows-with-github-pages)

Release ordering must prevent index entries pointing to unavailable assets:

1. Validate manifests, dependency closure and archives; run the candidate ZIP
   installation matrix against a temporary HTTP index with isolated caches.
2. Obtain the reviewed release commit/tag and pass the full release gate.
   Publishing requires a reviewed release operation, not an ordinary PR push.
3. Enable native immutable releases for the repository. Attach all package
   ZIPs and a SHA-256/provenance manifest to a draft release, verify uploaded
   bytes with authenticated access, then publish the release. Verify the now
   public URLs and hashes before advertising the version.
4. Deploy a complete index snapshot that retains every previously advertised
   version. Serialize publishers and reject a changed tag, duplicate version
   with different bytes, or a stale normal deployment that would remove newer
   entries.
5. Verify a cold-cache install through the public index, then release the
   compatible starter. Record commit, archive hashes and index deployment ID.

A failed pre-index upload may leave unadvertised assets; retry by verifying
identical bytes. Never overwrite a published version to fix it. Correct the
source and publish a new version. An explicitly reviewed emergency withdrawal
may roll the index back to the previous snapshot, as an exception to normal publication retaining all versions.
Keep every existing asset URL available. Withdrawal prevents selection only
after clients refresh metadata; cached metadata and existing locks may still
use the version. It does not downgrade applications already locked to it.
Downstream recovery uses an explicit known-good constraint/lock or a fix-forward
release. Follow GitHub's [immutable release sequence](https://docs.github.com/en/code-security/concepts/supply-chain-security/immutable-releases)
so every asset is attached before publication locks it.

## Composer consumer configuration and legacy compatibility

Add the new index in the **starter's root** `composer.json`, ahead of Packagist,
with `canonical: true` and an explicit `only` list of the nine names in the move
map. Keep the existing Drupal/asset repositories and public Packagist available
for other dependencies. Do not capture `fourkitchens/sous-drupal-project` or the
legacy Gin-based Layout Builder package through a broad wildcard.

Repositories declared inside recipe dependencies are not inherited by clients.
A canonical index containing a name but no compatible version also does not
fall through to Packagist for that name.
[Root repository configuration](https://getcomposer.org/doc/04-schema.md#repositories)
and [repository priorities](https://getcomposer.org/doc/articles/repository-priorities.md)

The first index advertises the new `2.x` line only. Therefore:

- Existing applications retain their current repository settings, lockfiles,
  exported configuration and legacy download URLs until an explicit upgrade.
- An application opting into the new canonical index must adopt compatible
  `^2.0` requirements together. A leftover `^1.0` or `dev-main` requirement is a
  migration error to report, not a reason to silently relax canonical priority.
- Do not copy historical wildcard dependency metadata into the new index:
  Content Types `1.0.3` could otherwise resolve incompatible new sibling majors.
- Freeze old releases and legacy `dev-main` source pointers. Do not repoint old
  branches to the new major as a shortcut. Keep release assets/tags available
  when the source repositories are eventually archived.
- Initially publish no floating development versions. Contributors use local
  Composer path repositories or immutable candidate ZIPs; production uses
  stable releases. A later development channel requires a separate design.

Contributor `path` fixtures and consumer ZIP-index fixtures are separate. Removing
a path repository and running `composer update --lock` does not convert retained
path-package locks into ZIP locks. Resolve a fresh consumer fixture against the
ZIP index and assert every selected Sous lock entry has the expected immutable
ZIP URL/reference, with no path distribution or monorepo source fallback.

Updating packages does not replay recipe configuration or migrate field data.
Existing sites adopt the archive source and configuration changes explicitly,
using backups, disposable copies, database updates and reviewed configuration
migrations. Existing `field_content_body` data is not renamed implicitly.
[Drupal recipe application](https://www.drupal.org/docs/extending-drupal/drupal-recipes/how-to-download-and-apply-drupal-recipes)

### Recipe unpacking and clean application clones

Use the native root setting `extra.drupal-recipe-unpack.ignore` with the exact
nine Sous package names. Verify this against the retained Drupal/unpacker
baseline during implementation. The currently pinned unpacker supports exact names, not wildcard
patterns. Listing nine names here does not require or install all nine. Keep
these recipes in Composer requirements/lock metadata and let Composer restore
their selected files. Other third-party recipes retain their
normal unpacking behavior. This avoids an application-specific provenance
ledger or manually committing Composer-managed recipe source.

The root `extra` setting is:

```json
{
  "drupal-recipe-unpack": {
    "ignore": [
      "fourkitchens/sous-admin",
      "fourkitchens/sous-content-types",
      "fourkitchens/sous-emulsify",
      "fourkitchens/sous-formats",
      "fourkitchens/sous-media",
      "fourkitchens/sous-paragraph-types",
      "fourkitchens/sous-paragraphs",
      "fourkitchens/sous-core-layout-builder",
      "fourkitchens/sous-config-split"
    ]
  }
}
```

Remove blanket Sous `drupal:recipe-unpack` calls and change the harness's old
assertion that all Sous package records disappear from the lock. After a normal
install, assert that the chosen recipes remain locked to package-specific ZIPs,
their nested paths exist, and no local `path` distribution or full-monorepo
`source` definition entered the generated lockfile. Versions, commit references
and the release checksum manifest supply provenance.

This changes package bookkeeping, not Drupal configuration lifecycle: requiring
or updating a retained recipe must not apply it again. Configuration still
changes only through explicit recipe application or reviewed site migrations.

Keep the selected recipe directories ignored as Composer-managed dependencies.
Add a true clean-clone test: commit the generated application's intended source,
complete configuration and lockfiles into a disposable repository; clone into a
fresh directory with no `vendor`, recipe files or shared caches; run locked
installs and restore its disposable database/configuration. Composer must
restore exactly the selected recipes from their ZIPs. The current harness
leaves `web/recipes` intact during dependency reinstallation, so that check does
not establish this boundary.

Existing applications already unpacked their recipes. Their upgrade requires
explicit root requirements and the native exclusion list. Review existing recipe
folders for project customizations before Composer takes ownership; preserve any
custom recipe copies in a project-owned location. Never overwrite/delete those
folders blindly or assume the old lock can restore them.

## Execution phases and completion gates

| Phase | Work | Exit gate |
| --- | --- | --- |
| 1. Freeze and inventory | Record reviewed immutable source SHAs, manifests, tags, published metadata, licenses and open PR links; audit name collisions and select the release commit set | Every import has an approved source and provenance; legacy recovery artifacts recorded |
| 2. Consolidate source | Import seven recipes into the nine-package layout; fold bundled layers; centralize docs/tests and preserve internal installed paths | Source/package validation and dependency closure pass; common packages have no optional-composition dependency |
| 3. Build distributions | Implement ZIP/index generation and release serialization; configure a candidate-only local HTTP index; test bad/partial publishes and index rollback | Every archive contains exactly one package; candidate installs use extracted ZIPs rather than path repositories |
| 4. Thin the starter | Add scoped index/version constraints; require only Admin/Emulsify base initially; select Content Types, core layouts or Paragraphs on demand; retain selected recipe packages through the native unpack exclusion list | No optional package ships in the starter artifact; all four normal installer entry points pass |
| 5. Validate the product | Run the full matrix below from packaged bytes and a real clean generated clone; check migrations and compatible/legacy client behavior | All required checks pass, artifacts are reproducible, and rollback is exercised |
| 6. Publish and cut over | Publish packages/index first; verify public downloads; publish the compatible starter second; update contributor and downstream guides | Cold-cache public installs work without source checkout/path overrides or added client credentials |
| 7. Retire old development homes | Link catalog/issues to the new source; reconcile old PRs and pending reviews; stop duplicate release jobs; archive only after compatibility checks | Two active contributor homes; historical tags/dist URLs remain accessible and no second publisher can race the new one |

Do not import stale `main` branches and lose the recent work. Candidate sources
are Admin `905ccf7`, Content Types `855729b`, Emulsify `b054eae`, Formats
`fb5b042`, Media `2a7dac0`, Paragraph Types `9dedd3f`, Paragraphs `e623b84`, and
starter `dfbf8e3`. Resolve each to its full SHA and confirm it still represents
the reviewed PR before importing. Record original repository/commit/license in
a migration provenance file; keep the old history accessible. Pending PRs can
be reviewed through the migration rather than merged into legacy `main` solely
to move their files.

Implement phases as reviewable commits: source import; package boundary changes;
archive/index tooling; starter integration; acceptance coverage; release/docs
cutover. Planning does not authorize silently publishing tags, changing service
settings, closing PRs, or archiving repositories.

## Acceptance matrix

Run consumer tests from candidate/public ZIPs with an empty Composer cache and
no contributor path repositories. Also run contributor tests against one local
packages checkout. Preserve production build, security, authoring and release
checks; verify archive failures stop publication and installer failures stop
success/cleanup.

| Selection | Required behavior | Required absence |
| --- | --- | --- |
| Default | Page/Frontpage Body, SEO/teasers/aliases, native management Views, media and administrator access | Paragraphs packages/modules/recipes/types/host fields; core-layout composition and enabled Layout Builder state |
| Core Layout Builder | Both types' saved Body layouts, per-node overrides and Basic inline blocks | Any Paragraphs-family contrib package/module directory, Paragraphs recipe ZIP/file, Paragraph type or Paragraphs-dependent active config |
| Paragraphs | Four component types; both host fields and Body tabs; create/edit/save/render/revisions/media | Core-layout composition ZIP/files and enabled Layout Builder state |
| Admin only | Gin/Navigation/Superuser/native management defaults and theme baseline | Page/Frontpage, Sous Media bundle defaults, either composition and their recipe files |
| Config Split off | Ordinary complete configuration workflow | Config Split package/module and optional definition YAML/active configuration |
| Config Split on | `_splits` definition storage, local/staging/production exports/imports, fresh import discovery and safe production transitions | Stored active environment flags, development settings leaking into production, missing/public split paths being accepted |

Core Layout Builder code already ships with Drupal core. Paragraphs selection
can exclude its recipe and enabled configuration, but cannot claim that core's
PHP files are absent. Conversely, the Layout Builder path must not download
Paragraphs ZIPs even temporarily and delete them afterward.

Test both `--prefer-dist` and `--prefer-source` against the dist-only index,
archive extraction without sibling files, real HTTPS authoring, module/config
round trips, incomplete/missing ZIP URLs, checksum/version mismatches, stale
index publication, clean-clone restoration and legacy locked installs. Package
names, license files and nested migration/Linkit paths are explicit assertions.

## Release blockers and operational ownership

The license audit has one concrete mismatch to resolve: the unchanged
`sous-paragraphs/LICENSE` contains MIT text while its Composer metadata declares
`GPL-2.0-or-later`. Reconcile the imported package with the confirmed Sous GPL
intent and preserve required original notices before publishing its ZIP. Do not
assume that every current source license file already agrees with its metadata.

The previous configuration-only diagnostic passed all four compositions and six
environment cases. The full source gate still fails at generated-theme lint
(303 errors involving compiled output and source formatting). That remains a
release blocker: resolve the upstream/tooling issue in a separate reviewed
change before declaring this migration releasable. A passing packaging proof
or configuration-only diagnostic does not replace the full gate.

Assign one release maintainer for protected tags, index/asset publication and
rollback; one product maintainer for package boundaries/catalog/PR review; and
a starter maintainer for scaffold compatibility and the integration matrix.
Use normal PR review for source/CI changes, pin reviewed Action versions, keep
PR jobs read-only, and grant release/Pages writes only to their publishing jobs.

The first implementation deliverables are the provenance inventory, package
catalog/contributor guide, nine validated manifests, ZIP/index tooling, scoped
starter changes, acceptance checks and downstream migration instructions. The
migration is complete only when generated sites have passed the absence and
clean-clone checks through the public index and legacy consumers remain able
to retrieve their locked versions.
