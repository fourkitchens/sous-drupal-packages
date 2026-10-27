#!/usr/bin/env node

// Future, manually approved publishing only. prepare is read-only on GitHub.
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { realpathSync } from 'node:fs';
import { mkdir, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { build, validateRelease } from './build.mjs';

export const repository = 'fourkitchens/sous-drupal-packages';
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const check = (value, message) => { if (!value) throw new Error(message); };
const digest = (bytes) => createHash('sha256').update(bytes).digest('hex');
const versionValid = (version) => /^2\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/.test(version ?? '');
const git = (...args) => execFileSync('git', args, { cwd: root, encoding: 'utf8' }).trim();
const gh = (...args) => execFileSync('gh', args, { cwd: root, encoding: 'utf8', maxBuffer: 20 * 1024 * 1024 }).trim();
const api = (path, token) => JSON.parse(execFileSync('gh', ['api', '-H', 'X-GitHub-Api-Version: 2026-03-10', path], {
  cwd: root, encoding: 'utf8', maxBuffer: 20 * 1024 * 1024,
  env: { ...process.env, ...(token ? { GH_TOKEN: token } : {}) },
}));
const baseURL = (version) => `https://github.com/${repository}/releases/download/${version}/`;

export function assertPublishContext(env, version) {
  check(versionValid(version), 'Publish a stable 2.x version.');
  check(env.GITHUB_REPOSITORY === repository, 'Publishing is restricted to the canonical repository.');
  check(env.GITHUB_EVENT_NAME === 'workflow_dispatch' && env.GITHUB_REF === 'refs/heads/main', 'Publishing requires a manual run on reviewed main.');
  check(env.SOUS_PUBLISHING_ENABLED === 'true', 'Publishing is disabled; repository maintainers must explicitly enable it.');
  check(env.SOUS_CONFIRM === `publish ${version}`, `Confirmation must equal "publish ${version}".`);
  check(Boolean(env.SOUS_IMMUTABILITY_READ_TOKEN), 'Provide a repository-scoped Administration:read token for the immutable-release preflight. GITHUB_TOKEN cannot read this setting.');
  check(/^[0-9a-f]{40}$/.test(env.GITHUB_SHA ?? ''), 'Missing reviewed package commit.');
  check(env.SOUS_FULL_STARTER_GATE_PASSED === 'true' && /^[0-9a-f]{40}$/.test(env.SOUS_STARTER_ACCEPTANCE_SHA ?? ''), 'A full starter acceptance pass and its reviewed commit are required. Configuration-only results do not qualify.');
}

export function assertCollection(manifest, version = manifest.version) {
  validateRelease(manifest);
  check(manifest.version === version && manifest.distBaseUrl === baseURL(version), 'Release manifest version or canonical asset URL differs.');
  return manifest;
}

export async function verifyFiles(manifest, directory) {
  for (const entry of Object.values(manifest.packages)) {
    const bytes = await readFile(join(directory, entry.filename));
    check(bytes.length === entry.bytes && digest(bytes) === entry.sha256, `Asset bytes differ: ${entry.filename}`);
  }
}

function compareVersions(a, b) {
  const aa = a.split('.').map(Number), bb = b.split('.').map(Number);
  for (let index = 0; index < 3; index++) if (aa[index] !== bb[index]) return aa[index] - bb[index];
  return 0;
}

function releases() {
  return JSON.parse(gh('api', '--paginate', '--slurp', `repos/${repository}/releases?per_page=100`)).flat();
}

async function prepare(version, output, reference) {
  check(versionValid(version), 'Use a stable 2.x collection version.');
  const history = await mkdtemp(join(tmpdir(), 'sous-release-history-'));
  try {
    const previous = [];
    for (const release of releases().filter((item) => !item.draft && !item.prerelease && versionValid(item.tag_name))) {
      check(compareVersions(release.tag_name, version) <= 0, `Refusing a stale index: ${release.tag_name} is already published.`);
      const details = JSON.parse(gh('release', 'view', release.tag_name, '--repo', repository, '--json', 'isImmutable'));
      check(details.isImmutable, `Historical release ${release.tag_name} must be immutable.`);
      const destination = join(history, release.tag_name);
      await mkdir(destination);
      gh('release', 'download', release.tag_name, '--repo', repository, '--pattern', 'release-manifest.json', '--dir', destination);
      const file = join(destination, 'release-manifest.json');
      const manifest = assertCollection(JSON.parse(await readFile(file, 'utf8')), release.tag_name);
      check(api(`repos/${repository}/commits/refs/tags/${release.tag_name}`).sha === manifest.reference, `Tag reference changed: ${release.tag_name}`);
      previous.push(file);
    }
    return await build({ root, version, reference, output, distBaseUrl: baseURL(version), previous });
  }
  finally { await rm(history, { recursive: true, force: true }); }
}

function assertCurrentMain(reference) {
  check(git('rev-parse', 'HEAD') === reference, 'Checkout differs from the reviewed workflow commit.');
  check(!git('status', '--porcelain', '--untracked-files=normal'), 'Publishing requires a clean source checkout.');
  check(api(`repos/${repository}/git/ref/heads/main`).object.sha === reference, 'Main advanced; rerun validation against its current commit.');
  check(api(`repos/${repository}/immutable-releases`, process.env.SOUS_IMMUTABILITY_READ_TOKEN).enabled === true, 'Enable native immutable releases before publishing.');
}

function assertCandidateTag(version, reference) {
  const tags = api(`repos/${repository}/git/matching-refs/tags/${version}`);
  if (tags.some((tag) => tag.ref === `refs/tags/${version}`)) {
    check(api(`repos/${repository}/commits/refs/tags/${version}`).sha === reference, 'Existing candidate tag points to a different commit; stop before publication.');
  }
}

async function compareUploaded(version, output, manifest) {
  const downloaded = await mkdtemp(join(tmpdir(), 'sous-upload-check-'));
  try {
    gh('release', 'download', version, '--repo', repository, '--dir', downloaded);
    await verifyFiles(manifest, downloaded);
    check(digest(await readFile(join(downloaded, 'release-manifest.json'))) === digest(await readFile(join(output, 'release-manifest.json'))), 'Uploaded provenance manifest differs.');
  }
  finally { await rm(downloaded, { recursive: true, force: true }); }
}

async function publish(version, output) {
  assertPublishContext(process.env, version);
  const reference = process.env.GITHUB_SHA;
  assertCurrentMain(reference);
  assertCandidateTag(version, reference);
  const manifest = await prepare(version, output, reference);
  const files = [...Object.values(manifest.packages).map((entry) => join(output, 'dist', entry.filename)), join(output, 'release-manifest.json')];
  let existing = releases().find((release) => release.tag_name === version);
  if (!existing) {
    const notes = join(output, 'release-notes.md');
    await writeFile(notes, `Nine independent Drupal recipes. See the package catalog and migration guide.\n\nPackage commit: ${reference}\nFull starter acceptance: ${process.env.SOUS_STARTER_ACCEPTANCE_SHA}\n\nUse the static Composer index only after its deployment succeeds.\n`);
    gh('release', 'create', version, '--repo', repository, '--draft', '--target', reference, '--title', `Sous packages ${version}`, '--notes-file', notes);
    existing = releases().find((release) => release.tag_name === version);
  }
  check(existing && !existing.prerelease, 'Unexpected or missing candidate release.');
  // A retry may only finish the same candidate, never replace bytes or a tag.
  if (existing.draft) {
    check(existing.target_commitish === reference, 'Draft belongs to a different source commit.');
    const expectedNames = files.map((file) => file.split('/').at(-1));
    check(existing.assets.every((asset) => expectedNames.includes(asset.name)), 'Draft contains unexpected assets.');
    for (const file of files) {
      if (!existing.assets.some((asset) => asset.name === file.split('/').at(-1))) gh('release', 'upload', version, file, '--repo', repository);
    }
    await compareUploaded(version, output, manifest);
    assertCurrentMain(reference);
    assertCandidateTag(version, reference);
    gh('release', 'edit', version, '--repo', repository, '--draft=false');
  }
  const published = JSON.parse(gh('release', 'view', version, '--repo', repository, '--json', 'isImmutable,isDraft,isPrerelease'));
  check(published.isImmutable && !published.isDraft && !published.isPrerelease, 'Release is not public and immutable. Stop before deploying the index.');
  check(api(`repos/${repository}/commits/refs/tags/${version}`).sha === reference, 'Published tag does not match the reviewed commit.');
  await compareUploaded(version, output, manifest);
  for (const [url, expected] of [
    ...Object.values(manifest.packages).map((entry) => [entry.metadata.dist.url, entry.sha256]),
    [`${baseURL(version)}release-manifest.json`, digest(await readFile(join(output, 'release-manifest.json')))],
  ]) {
    const response = await fetch(url, { signal: AbortSignal.timeout(120_000) });
    check(response.ok && digest(Buffer.from(await response.arrayBuffer())) === expected, `Public asset verification failed: ${url}`);
  }
  // Refresh the full historical snapshot immediately before the Pages upload.
  await prepare(version, output, reference);
  console.log(`Verified immutable release ${version}; ${join(output, 'site')} is ready for Pages deployment.`);
}

if (process.argv[1] && realpathSync(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    const [command, version, ...extra] = process.argv.slice(2);
    check(['prepare', 'publish'].includes(command) && versionValid(version) && extra.length === 0, 'Usage: node tools/release.mjs prepare|publish 2.0.0');
    const output = join(root, 'build');
    if (command === 'prepare') await prepare(version, output, git('rev-parse', 'HEAD'));
    else await publish(version, output);
  }
  catch (error) { console.error(error.message); process.exitCode = 1; }
}
