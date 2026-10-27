import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { chmod, cp, mkdir, mkdtemp, readFile, realpath, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import { build } from '../tools/build.mjs';

const source = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const repository = 'fourkitchens/sous-drupal-packages';
const oldReference = 'a'.repeat(40);
const baseURL = (version) => `https://github.com/${repository}/releases/download/${version}/`;

// This executable replaces gh in PATH. Unknown calls fail; no real client or
// network request can be reached. State/assets live outside the fake checkout.
const mockGH = `#!/usr/bin/env node
import { appendFileSync, copyFileSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { basename, join } from 'node:path';
const args = process.argv.slice(2), stateFile = process.env.SOUS_MOCK_STATE;
const state = JSON.parse(readFileSync(stateFile));
appendFileSync(process.env.SOUS_MOCK_LOG, JSON.stringify(args) + '\\n');
const output = (value) => process.stdout.write(JSON.stringify(value));
const save = () => writeFileSync(stateFile, JSON.stringify(state));
const option = (name) => args[args.indexOf(name) + 1];
if (args[0] === 'api') {
  const path = args.at(-1);
  if (path.includes('/releases?')) output([state.releases]);
  else if (path.endsWith('/immutable-releases')) output({ enabled: true });
  else if (path.endsWith('/git/ref/heads/main')) output({ object: { sha: state.reference } });
  else if (path.includes('/git/matching-refs/tags/')) {
    const version = path.split('/').at(-1);
    output(state.tags[version] ? [{ ref: 'refs/tags/' + version }] : []);
  }
  else if (path.includes('/commits/')) output({ sha: state.tags[path.split('/').at(-1)] });
  else throw new Error('Unexpected mock API: ' + path);
}
else if (args[0] === 'release') {
  const command = args[1], version = args[2];
  let release = state.releases.find((item) => item.tag_name === version);
  if (command === 'create') {
    if (release) throw new Error('Release already exists');
    state.releases.push({ tag_name: version, draft: true, prerelease: false, immutable: false, target_commitish: option('--target'), assets: [] });
    save();
  }
  else if (!release) throw new Error('Missing mock release: ' + version);
  else if (command === 'view') output({ isImmutable: release.immutable, isDraft: release.draft, isPrerelease: release.prerelease });
  else if (command === 'upload') {
    const file = args[3], name = basename(file);
    if (release.assets.some((asset) => asset.name === name)) throw new Error('Attempted asset replacement');
    const directory = join(state.assets, version);
    mkdirSync(directory, { recursive: true });
    copyFileSync(file, join(directory, name));
    release.assets.push({ name });
    save();
  }
  else if (command === 'download') {
    const directory = option('--dir');
    mkdirSync(directory, { recursive: true });
    const pattern = args.includes('--pattern') ? option('--pattern') : undefined;
    for (const asset of release.assets.filter((item) => !pattern || item.name === pattern)) copyFileSync(join(state.assets, version, asset.name), join(directory, asset.name));
  }
  else if (command === 'edit' && args.includes('--draft=false')) {
    release.draft = false; release.immutable = true;
    state.tags[version] ??= release.target_commitish;
    save();
  }
  else throw new Error('Unexpected mock release command: ' + args.join(' '));
}
else throw new Error('Unexpected mock gh command: ' + args.join(' '));
`;

const mockFetch = `import { appendFileSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
globalThis.fetch = async (input) => {
  const url = new URL(input);
  if (url.origin !== 'https://github.com' || !url.pathname.startsWith('/fourkitchens/sous-drupal-packages/releases/download/')) throw new Error('Unexpected public URL');
  const [version, filename] = url.pathname.split('/').slice(-2);
  const state = JSON.parse(readFileSync(process.env.SOUS_MOCK_STATE));
  const release = state.releases.find((item) => item.tag_name === version);
  if (release?.draft || !release?.immutable) throw new Error('Public fetch preceded immutable publication');
  appendFileSync(process.env.SOUS_MOCK_LOG, JSON.stringify(['fetch', url.href]) + '\\n');
  return new Response(readFileSync(join(state.assets, version, filename)));
};
`;

async function fixture(t, historical = false) {
  const work = await realpath(await mkdtemp(join(tmpdir(), 'sous-publisher-test-')));
  t.after(() => rm(work, { recursive: true, force: true }));
  const root = join(work, 'checkout'), assets = join(work, 'assets'), bin = join(work, 'bin');
  await mkdir(join(root, 'tools'), { recursive: true });
  await mkdir(bin);
  await mkdir(assets);
  for (const filename of ['build.mjs', 'release.mjs']) await cp(join(source, 'tools', filename), join(root, 'tools', filename));
  await cp(join(source, 'packages'), join(root, 'packages'), { recursive: true });
  await writeFile(join(root, '.gitignore'), '/build/\n');
  const state = { releases: [], tags: {}, assets };
  let old;
  if (historical) {
    old = await build({ root, version: '2.0.0', reference: oldReference, output: join(work, 'old'), distBaseUrl: baseURL('2.0.0') });
    await mkdir(join(assets, '2.0.0'));
    const filenames = [...Object.values(old.packages).map((entry) => entry.filename), 'release-manifest.json'];
    for (const name of filenames) await cp(join(work, 'old', name.endsWith('.zip') ? 'dist' : '', name), join(assets, '2.0.0', name));
    state.releases.push({ tag_name: '2.0.0', draft: false, prerelease: false, immutable: true, assets: filenames.map((name) => ({ name })) });
    state.tags['2.0.0'] = oldReference;
    const file = join(root, 'packages/sous-media/composer.json');
    const manifest = JSON.parse(await readFile(file, 'utf8'));
    manifest.description = 'Different current metadata must never replace historical metadata';
    await writeFile(file, JSON.stringify(manifest, null, 2));
  }
  const git = (...args) => execFileSync('git', args, { cwd: root, encoding: 'utf8' }).trim();
  git('init', '--quiet'); git('add', '.');
  git('-c', 'user.name=Sous test', '-c', 'user.email=sous-tests@example.invalid', 'commit', '--quiet', '-m', 'Disposable publication fixture');
  state.reference = git('rev-parse', 'HEAD');
  const stateFile = join(work, 'state.json'), logFile = join(work, 'calls.jsonl'), preload = join(work, 'mock-fetch.mjs');
  await writeFile(stateFile, JSON.stringify(state));
  await writeFile(logFile, '');
  await writeFile(preload, mockFetch);
  await writeFile(join(bin, 'gh'), mockGH);
  await chmod(join(bin, 'gh'), 0o755);
  const invoke = () => spawnSync(process.execPath, ['--import', preload, join(root, 'tools/release.mjs'), 'publish', '2.0.1'], {
    cwd: root, encoding: 'utf8', timeout: 30_000,
    env: { ...process.env, NODE_OPTIONS: '', PATH: bin + ':' + process.env.PATH, GH_TOKEN: 'fake-test-token',
      SOUS_MOCK_STATE: stateFile, SOUS_MOCK_LOG: logFile,
      GITHUB_REPOSITORY: repository, GITHUB_EVENT_NAME: 'workflow_dispatch', GITHUB_REF: 'refs/heads/main',
      GITHUB_SHA: state.reference, SOUS_PUBLISHING_ENABLED: 'true', SOUS_CONFIRM: 'publish 2.0.1',
      SOUS_FULL_STARTER_GATE_PASSED: 'true', SOUS_STARTER_ACCEPTANCE_SHA: 'b'.repeat(40),
      SOUS_IMMUTABILITY_READ_TOKEN: 'fake-admin-read-token',
    },
  });
  const calls = async () => (await readFile(logFile, 'utf8')).trim().split('\n').filter(Boolean).map(JSON.parse);
  return { root, state, stateFile, assets, old, invoke, calls };
}

test('mocked publication verifies uploaded/public bytes and preserves old metadata', async (t) => {
  const f = await fixture(t, true);
  const result = f.invoke();
  assert.equal(result.status, 0, result.stderr);
  const calls = await f.calls();
  const edit = calls.findIndex((call) => call[0] === 'release' && call[1] === 'edit');
  assert(edit > 0);
  assert(calls.slice(0, edit).some((call) => call[0] === 'release' && call[1] === 'download' && call[2] === '2.0.1'));
  assert.equal(calls.filter((call) => call[0] === 'release' && call[1] === 'upload').length, 10);
  assert.equal(calls.filter((call) => call[0] === 'fetch').length, 10);
  assert(calls.every((call, index) => call[0] !== 'fetch' || index > edit));
  const index = JSON.parse(await readFile(join(f.root, 'build/site/packages.json')));
  for (const [name, entry] of Object.entries(f.old.packages)) assert.deepEqual(index.packages[name]['2.0.0'], entry.metadata);
  const before = calls.length;
  const retry = f.invoke();
  assert.equal(retry.status, 0, retry.stderr);
  assert(!(await f.calls()).slice(before).some((call) => call[0] === 'release' && ['create', 'upload', 'edit'].includes(call[1])), 'Same-version retry must not mutate an immutable release.');
});

test('conflicting candidate tag is rejected before any mock release mutation', async (t) => {
  const f = await fixture(t);
  f.state.tags['2.0.1'] = 'c'.repeat(40);
  await writeFile(f.stateFile, JSON.stringify(f.state));
  const result = f.invoke();
  assert.equal(result.status, 1);
  assert.match(result.stderr, /Existing candidate tag points to a different commit/);
  assert(!(await f.calls()).some((call) => call[0] === 'release' && ['create', 'upload', 'edit'].includes(call[1])));
});

test('an existing corrupted draft archive stops before publication and public fetch', async (t) => {
  const f = await fixture(t);
  const name = 'sous-admin-2.0.1.zip';
  await mkdir(join(f.assets, '2.0.1'));
  await writeFile(join(f.assets, '2.0.1', name), 'corrupted draft archive');
  f.state.releases.push({ tag_name: '2.0.1', draft: true, prerelease: false, immutable: false, target_commitish: f.state.reference, assets: [{ name }] });
  await writeFile(f.stateFile, JSON.stringify(f.state));
  const result = f.invoke();
  assert.equal(result.status, 1);
  assert.match(result.stderr, /Asset bytes differ/);
  assert(!(await f.calls()).some((call) => call[0] === 'fetch' || (call[0] === 'release' && call[1] === 'edit')));
});
