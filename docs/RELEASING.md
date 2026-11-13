# Releasing the collection

On pushes to `main`, CI runs `npm test` and applies the `admin`, `content-types`,
`core-layout-builder`, and `paragraphs` compositions before semantic-release
determines the next version. The repository and its hosted packages share one
version, starting at `2.0.0`.

Every Monday at 07:23 UTC, CI applies all four recipe compositions with the
newest compatible Sift beta. The weekly run never publishes a release or
deploys the Composer index. If it fails, check the locked versions in the run
summary and either fix Content List in a normal PR or report the regression in
the [Sift repository](https://github.com/fourkitchens/viewsreference_sift/issues).

## Version rules

Use Conventional Commits for commits merged into `main`, including squash
titles. The default commit analyzer assigns the following increments.

| Change                                                                    | Example                            | Increment |
| ------------------------------------------------------------------------- | ---------------------------------- | --------- |
| Breaking change                                                           | `feat!: change the content model`  | Major     |
| Feature (`feat`)                                                          | `feat: add an Event recipe`        | Minor     |
| Correction or performance improvement (`fix`, `perf`)                     | `fix: correct a recipe dependency` | Patch     |
| Documentation and maintenance (`docs`, `ci`, `refactor`, `test`, `chore`) | `docs: improve the package guide`  | None      |

A `BREAKING CHANGE:` footer also counts as a major change. The collection's
release plugin sets a patch minimum after `2.0.0` for commits eligible for
release, including docs-only commits. semantic-release selects the largest
increment from both analyzers since the previous release.

The `1.0.0` tag at `d3dcd219d251e322a3b206a6bf12889712b2b3f0` records the
release baseline on `main` and predates the hosted packages. The collection's
release plugin sets a major increment at that baseline so the first collection
release is `2.0.0`. The workflow verifies the baseline commit and excludes only
that tag from its published-asset checks. Contributors do not manually edit
package versions or create release tags.

## Repository setup

Configure GitHub Pages to use **GitHub Actions** as its source. The public
Composer index is hosted at
`https://fourkitchens.github.io/sous-drupal-packages/`.

The workflow uses the repository's `GITHUB_TOKEN` to create releases and upload
assets, and Pages deployment uses the workflow's Pages and OIDC permissions.
Repository rules must allow these operations. Keep `main` protected with the
lint check required before merging. No additional publication confirmation or
version input is required.

Enable GitHub's immutable releases setting to protect published tags and assets.
Do not replace a released ZIP or move a release tag: Composer locks and caches
retain its URL and checksum.

## Published files

For each release, the workflow:

1. Calculates the next semantic version and generates release notes.
2. Builds one deterministic ZIP for every directory under `packages/`, with the
   same version in its Composer metadata and coordinated sibling dependencies.
3. Publishes those ZIPs and `release-manifest.json` as GitHub release assets.
4. Deploys the static Composer index and catalog through GitHub Pages.
5. Waits up to five minutes for the public `packages.json` to list every package
   at the run's published version, then verifies that every ZIP URL returns
   HTTP 200 after redirects.

The index includes earlier releases using their saved manifests, preserving each
version's dependency metadata and download checksums. A new package becomes
available when its first collection release is published; it is not added to
older versions. No npm package or Git source fallback is published.

Local preview builds are described in [CONTRIBUTING.md](../CONTRIBUTING.md).
`npm run release:dry-run` previews semantic-release's version decision without
publishing; it requires repository access and does not run publication hooks.

## Recovery

Inspect a failed workflow before merging another release. If an upload fails after
semantic-release creates its tag, a rerun cannot finish that release automatically.
A maintainer must inspect and repair the unpublished draft/tag before retrying;
the index refuses to deploy while a reachable release tag has no published collection.
If Pages deployment or verification fails, check the log for the index URL,
missing package versions, or failing ZIP URLs. In the workflow run's job list,
rerun `deploy-index` to redeploy the index and repeat the public check. If the
Pages artifact has expired, rerun the workflow to rebuild it from the published
release manifests. Never move or delete a published release tag.
If package source needs a correction, merge a new Conventional Commit and let
semantic-release create the next version. Preserve already published asset URLs
and checksums so existing project locks remain usable.
