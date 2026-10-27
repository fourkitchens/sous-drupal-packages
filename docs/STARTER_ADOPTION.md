# Starter adoption after publication

These changes belong in a separate reviewed `sous-drupal-project` release.
The current starter and historical consumers continue to work against their
existing repositories while this package branch is reviewed.

## Composer root

Merge [the fragment](../examples/starter-composer.fragment.json) after verifying
the public index. Preserve existing root scaffold, installer paths, patches,
libraries, scripts and plugin allowlist. It is a fragment, not a replacement
Composer project. Keep `web/recipes/{$name}` for `type:drupal-recipe`, the native
`drupal/core-recipe-unpack` plugin, and exact nine-name exclusions. Repositories
and stability exceptions must be declared at the root; nested repositories are
ignored by Composer. Keep the root Dropzone package metadata and the existing
explicit Login Destination beta exception.

Remove legacy Sous VCS overrides for the selected 2.x names and all contributor
path overrides before resolving release locks. The scoped canonical index owns
only these nine names. A project still requiring `^1` or `dev-main` should keep
its legacy settings until it deliberately upgrades; the new index does not
contain those versions and canonical resolution will not fall through.

Retain selected recipe requirements/lock entries. Remove blanket
`drupal:recipe-unpack` invocations and assumptions that all Sous recipe records
disappear. Update post-create, restore and clean-clone checks to use the native
exclusion behavior. Add Composer-managed Sous directories to generated-site
ignores; do not commit downloaded recipe payloads. Review customized/unpacked
folders before allowing Composer to take ownership of them.

## Installer choices

Require Admin + Emulsify for the base. Require Content Types for default content,
Core Layout Builder for that option, Paragraphs for that option, or neither for
administration only. Transitive dependencies install shared defaults. Apply
Admin and Emulsify once, then only the selected composition. Content Types does
not reapply Admin.

Delete the now redundant bundled `sous-starter-content`,
`sous-starter-paragraphs`, `sous-layout-builder` and `sous-config-split` recipe
payloads from the future starter artifact. Replace their installer calls with
the published package basenames. `sous-starter-content` becomes
`sous-content-types`; `sous-starter-paragraphs` becomes `sous-paragraphs`;
`sous-layout-builder` becomes `sous-core-layout-builder`.

The root only adds `fourkitchens/sous-config-split:^2.0` when selected, then
explicitly applies its recipe. For that choice create `config/_splits`,
`config/local` and `config/staging`. Keep `_splits` active as the bootstrap split
and project-owned environment definitions under `config/_splits`. Local/staging
value exports belong to their respective folders. Use the existing starter
runtime policy to activate `sous_local` for local, `sous_staging` for dev/test/
staging/multidev, and neither for production/live. Never turn environment-specific
status into a universal active recipe default. Keep settings overrides and
configuration export helpers in the application.

The package fixture checks native split defaults and export/import behavior.
The full starter gate must also exercise its actual environment policy across
local, dev/test/staging/multidev and production/live. A packages-only fixture
does not verify a generated site's platform detection or frontend tooling.

## Acceptance and cutover

Adapt the starter's existing harness source map to `packages/*` for explicit
contributor testing. Published consumers must use ZIPs, with no floating branch
or path source in their lock. Reuse its environment/theme acceptance checks;
keep the candidate fixture distinct from the complete release gate.

Test all four content choices with and without Config Split. In particular:

- Core Layout Builder never downloads/copies Paragraphs packages, modules,
  recipes, paragraph types or host fields.
- Paragraphs never requires the Core Layout Builder composition or enables
  Layout Builder displays/override fields on Page or Frontpage. Core Navigation
  in Admin enables the shared `layout_builder` module in Drupal 11.4; its PHP
  files also naturally ship with Drupal. Test bundle configuration independently
  of that shared module state.
- Admin only creates no Page/Frontpage or Sous media bundles. Admin itself may
  enable core media modules for management views.
- A clean application clone recovers selected recipe directories using only its
  root Composer manifest/lock and public ZIPs; `--prefer-source` still uses ZIPs.
- Full generated-theme lint/build, authoring/save/render/alias probes, settings
  policies and configuration round trips pass without weakening existing gates.

Fix the known source starter's frontend lint failure before attesting a full
pass. After the immutable package release and public index are verified, run a
cold-cache consumer test against those public URLs, resolve the final starter
lock without candidate overrides, then review/publish its new major release.
