# Sous coding agent brief

## What lives here

Drupal recipes live in `packages/*`.
Release tooling manages package releases.
Recipe CI verifies recipes.

## Simplicity rules

- Prefer deleting code to adding it. A change should leave the repo the same
  size or smaller unless it adds a requested feature.
- No new abstraction layers, environment frameworks or "policy" files.
- Don't work around upstream bugs (Emulsify, contrib modules) with scripts
  here. Use a Composer patch with an issue link, or fix the upstream project.
- No defensive checks for situations the scripts can't reach.
- Comments: at most two lines, plain English, explaining why.
  Don't narrate edge cases.
- Docs: edit an existing file rather than adding one.

## Size

Keep each PR under about 400 changed lines, excluding lockfiles and exported
config. Split bigger work.

## Commits

Use Conventional Commits: feat, fix, refactor, docs, ci, test or chore.
Use `!` for breaking changes.

## Verify

Run from the repo root:

```sh
npm test
```
