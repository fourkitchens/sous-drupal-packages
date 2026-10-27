# Migration implementation status

This branch implements the package-repository portion of
[MIGRATION_PLAN.md](../MIGRATION_PLAN.md). It imports reviewed source commits
into nine independently installable recipe directories. The seven existing
Composer names remain, with Core Layout Builder and Config Split added.

Implemented here:

- Source imports and explicit provenance, GPL licensing and preserved notices.
- Content Types owns Page/Frontpage and shared defaults. Admin owns management
  Views and Superuser. Paragraphs owns both host fields/displays. Nested Linkit
  and Admin migration paths remain intact.
- Stable `^2.0` sibling requirements, bounded contrib dependencies, no common
  package dependency on either composition.
- Deterministic package-only ZIPs, dist-only static Composer index, immutable
  per-release metadata/history and duplicate mutation rejection.
- Offline distribution/publication tests and disposable real Composer/Drupal
  consumer tests. PR Actions have read-only repository permissions.
- An opt-in future publishing workflow with main/manual/review gates, immutable
  release preflight, uploaded/public byte verification and index deployment last.
- A concrete Composer root fragment and starter adoption instructions.

Intentionally awaiting manual review and separate cutover:

1. Initialize `main` and review/merge the release branch. Only the release branch
   is pushed by this implementation, as requested.
2. Confirm package-name availability, index hosting and native immutable release
   settings. Enable Pages through Actions and protect publishing environments.
3. Complete the full starter acceptance gate. The reviewed source starter
   `dfbf8e36ee8c0b55bdd32058f6f27c9c811abf28` had a known generated-theme lint
   failure; configuration-only acceptance does not clear that release blocker.
4. Publish packages `2.0.0`, verify public index/ZIP cold-cache installation,
   then adopt them in a separately reviewed starter release (candidate `8.0.0`).
5. Retain legacy repository/tag/Packagist history. Archival and old PR disposition
   remain separate human decisions; no legacy repositories are changed here.

No tags, releases, Pages settings/deployments, legacy repository settings or
starter application files are modified by preparing/pushing this branch.
`main` links in package guides become available once the review is merged.

## Acceptance adjustment from runtime evidence

The original plan expected the core Layout Builder module to be disabled on the
default/Paragraphs paths. Native Drupal 11.4 testing showed that Navigation in
Admin enables it as a dependency. Retaining that administration experience means
the module is shared. Tests instead enforce that default/Paragraphs installs
have no Core Layout Builder composition ZIP/recipe, no Layout Builder display
settings and no per-node layout override fields. The Layout Builder path still
must never download any Paragraphs package/module/recipe/configuration.

## Dependency details

Consuming roots must keep their existing patch/plugin policies and the custom
Dropzone `6.3.5` package repository. Composer ignores repositories declared by
dependencies, so they are absent from the new package manifests. The root also
explicitly permits the existing Login Destination beta; the overall stability
floor remains stable. See the [Composer fragment](../examples/starter-composer.fragment.json).

The two fixture patches are copied from the reviewed starter and apply only to
disposable test applications. Their upstream replacements can be reviewed in
the starter without packaging application-root policy into these recipes.
