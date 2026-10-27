# Releasing the collection

No publication happens on merge, tag push or a normal CI run. The manual
`publish.yml` workflow is disabled unless `SOUS_PUBLISHING_ENABLED=true` is set
on the canonical repository. This migration only prepares that workflow.

## Prerequisites before the first release

1. Initialize and review `main`, merge the candidate, and review package names,
   source provenance and licenses. Do not repurpose old tags or publish 2.x
   metadata into the legacy package repositories.
2. Resolve the starter's known generated-theme lint failure and run its **full**
   acceptance gate against this package commit. Record the starter commit and
   successful run. Configuration-only success is insufficient. The workflow's
   starter SHA and checkbox are maintainer attestations; this repository does
   not automatically prove another repository's full acceptance run.
3. Enable native immutable releases in repository Settings → Releases. The
   publisher checks the read-only API and fails if immutability is unavailable.
   No script changes that setting. Add `SOUS_IMMUTABILITY_READ_TOKEN` as a protected
   environment secret: a fine-grained token scoped to this repository with
   **Administration: read**. GitHub's normal `GITHUB_TOKEN` cannot read this
   setting. The extra token is used only for this read; release writes use
   `GITHUB_TOKEN`. See the [official endpoint permissions](https://docs.github.com/en/rest/repos/repos#check-if-immutable-releases-are-enabled-for-a-repository).
4. Configure GitHub Pages to use GitHub Actions and verify the intended public
   HTTPS index URL. Create/protect the `package-release` environment with required
   reviewers and a main-only deployment rule; protect `github-pages` too.
   Protect `main` and require Package validation. Confirm repository permissions
   permit release creation and Pages deployment.
5. Only after these checks enable the repository variable above. It defaults
   absent/disabled. No settings changes are performed by this implementation.

## Build and review without publishing

`npm run check`, `npm test` and `npm run test:consumer` run the package gates.
The consumer matrix exercises real ZIP distributions in disposable Drupal
applications. It does not build a generated starter theme.

For an offline candidate build use the command in [CONTRIBUTING.md](../CONTRIBUTING.md).
For a read-only GitHub history-aware preparation in a clean committed checkout:

```sh
npm run release:prepare -- 2.0.0
```

This reads every published stable 2.x immutable release manifest, verifies its
tag reference and canonical URLs, and builds `build/dist`,
`build/release-manifest.json`, and `build/site`. It never creates a tag/release,
uploads assets or deploys Pages. It requires authenticated `gh` reads. Every
release manifest stores complete metadata for its own version, so later builds
retain old dependency metadata without reconstructing it from new source.

## Reviewed publication

Dispatch **Publish reviewed package collection** on `main`, with the stable
version, exact confirmation `publish VERSION`, full starter acceptance SHA and
the full-gate attestation. The protected environment provides the final review.
The publisher independently checks the canonical repository, manual event,
reviewed main commit, opt-in and attestation, even if invoked outside YAML.

The workflow reruns package/consumer gates, then:

1. Verifies current main and native immutable releases; rejects conflicting
   existing tags or a version older than an already published collection.
2. Builds deterministic ZIPs and an index containing all historical versions.
3. Creates a draft for this exact commit and uploads only missing assets.
   Existing bytes are downloaded and compared; nothing is overwritten.
4. Downloads all assets using authenticated access and verifies their SHA-256,
   sizes and provenance manifest before publishing the draft.
5. Confirms the public release is immutable and its tag matches, then downloads
   every public ZIP/manifest URL and checks bytes again.
6. Refreshes release history and uploads/deploys the complete Pages snapshot.
   Publication runs are serialized, including index deployment.

After deployment, verify `packages.json` and a cold-cache public Composer install
before cutting over the starter. Record the package SHA, starter acceptance run,
release asset hashes, Pages deployment and public consumer result in the release
review. Never advertise the future index before its asset verification succeeds.

## Failure and recovery

Before index deployment, failure can leave a draft or an unadvertised immutable
release. A retry on the same source commit/version verifies identical bytes and
finishes missing steps. Do not manually replace assets or repoint tags. If main
has advanced, review recovery from the original commit before dispatching;
the main-head guard intentionally stops stale publication. Reusing a version
from a changed source is rejected; correct source with a new version instead.

A failed Pages deployment after release publication can be retried from the
same reviewed main commit. History is rebuilt from all immutable manifests,
so a normal deployment cannot silently omit a newer stable release. An explicit
emergency withdrawal is a separate reviewed index-only operation, not this
normal publisher. Preserve all asset URLs; cached metadata and existing locks
may still select a withdrawn version. Recover applications with a deliberate
known-good constraint/lock or a new fixed release.

Legacy repositories remain available for old tags/download URLs and lockfiles.
This workflow does not create split mirrors, update Packagist for old packages,
archive repositories or merge starter changes.
