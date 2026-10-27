# Releasing the collection

A merge to `main` runs syntax, code-sniffing and formatting checks, then
semantic-release publishes the collection. Review branches and pull requests
run checks without publishing. There is one version for the repository and all
hosted packages, starting at `1.0.0`.

## Version rules

Use Conventional Commits for commits merged into `main`, including squash titles:

| Change                                                 | Example                            | Increment |
| ------------------------------------------------------ | ---------------------------------- | --------- |
| Incompatible public behavior or dependency requirement | `feat!: change the content model`  | Major     |
| New package or capability                              | `feat: add an Event recipe`        | Minor     |
| Correction                                             | `fix: correct a recipe dependency` | Patch     |
| Other changes, including docs and CI                   | `docs: improve the package guide`  | Patch     |

A `BREAKING CHANGE:` footer also requests a major release. semantic-release
selects the largest increment since the previous release. It produces `1.0.0`
when no earlier collection release exists; contributors do not manually edit
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
Never delete or move an already published release tag. Rerun a failed Pages
deployment to restore the public index from the published release manifests.
If package source needs a correction, merge a new Conventional Commit and let
semantic-release create the next version. Preserve already published asset URLs
and checksums so existing project locks remain usable.
