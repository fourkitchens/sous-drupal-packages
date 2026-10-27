import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { chmod, copyFile, mkdir, mkdtemp, readFile, rm, symlink, utimes, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { basename, join } from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { build, makeIndex, packageNames, validatePackages, validateRelease } from '../tools/build.mjs';

const reference = '0123456789abcdef0123456789abcdef01234567';
const base = 'https://github.com/fourkitchens/sous-drupal-packages/releases/download/2.0.0/';
const dependencies = {
  'sous-admin': { 'fourkitchens/sous-formats': '^2.0' },
  'sous-config-split': { 'drupal/config_split': '^2.0.2' },
  'sous-content-types': { 'fourkitchens/sous-formats': '^2.0', 'fourkitchens/sous-media': '^2.0' },
  'sous-core-layout-builder': { 'fourkitchens/sous-content-types': '^2.0' },
  'sous-paragraph-types': { 'drupal/paragraphs': '^1.23', 'fourkitchens/sous-media': '^2.0' },
  'sous-paragraphs': { 'fourkitchens/sous-content-types': '^2.0', 'fourkitchens/sous-paragraph-types': '^2.0' },
};

async function fixture(t) {
  const root = await mkdtemp(join(tmpdir(), 'sous-distribution-test-'));
  t.after(() => rm(root, { recursive: true, force: true }));
  for (const name of packageNames) {
    const slug = basename(name);
    const directory = join(root, 'packages', slug);
    await mkdir(join(directory, 'config'), { recursive: true });
    await writeFile(join(directory, 'composer.json'), `${JSON.stringify({ name, description: `${slug} defaults`, type: 'drupal-recipe', license: 'GPL-2.0-or-later', require: { 'drupal/core': '^11.4.11', ...dependencies[slug] } }, null, 2)}\n`);
    await writeFile(join(directory, 'recipe.yml'), `name: '${slug}'\ntype: Site\n`);
    await writeFile(join(directory, 'config', `example.${slug}.yml`), `label: '${slug}'\n`);
    await writeFile(join(directory, 'README.md'), `# ${slug}\n`);
    await writeFile(join(directory, 'LICENSE'), 'GPL version 2 or later\n');
  }
  return root;
}

async function changeManifest(root, slug, change) {
  const path = join(root, 'packages', slug, 'composer.json');
  const manifest = JSON.parse(await readFile(path, 'utf8'));
  change(manifest);
  await writeFile(path, `${JSON.stringify(manifest, null, 2)}\n`);
}

const run = (root, output, extra = {}) => build({ root, output: join(root, output), version: '2.0.0', reference, distBaseUrl: base, ...extra });

test('ZIP bytes ignore source mtimes and permissions and contain only one recipe', async (t) => {
  const root = await fixture(t);
  const media = join(root, 'packages', 'sous-media');
  await mkdir(join(media, 'tests'));
  await writeFile(join(media, 'tests', 'not-distributed.php'), '<?php // fixture');
  await mkdir(join(media, '.github'));
  await writeFile(join(media, '.github', 'ci.yml'), 'name: not-distributed\n');
  await mkdir(join(media, 'assets'));
  await writeFile(join(media, 'assets', 'example.svg'), '<svg/>\n');
  await mkdir(join(media, 'recipes', 'nested'), { recursive: true });
  await writeFile(join(media, 'recipes', 'nested', 'recipe.yml'), 'name: Nested\ntype: Site\n');
  const first = await run(root, 'first');
  const file = join(media, 'config', 'example.sous-media.yml');
  await chmod(file, 0o777);
  await utimes(file, new Date('2025-05-01'), new Date('2025-05-01'));
  const second = await run(root, 'second');
  assert.deepEqual(first, second);
  for (const name of packageNames) {
    const entry = first.packages[name];
    assert.equal(entry.metadata.source, undefined);
    assert.equal(entry.metadata.dist.type, 'zip');
    assert.deepEqual(await readFile(join(root, 'first', 'dist', entry.filename)), await readFile(join(root, 'second', 'dist', entry.filename)));
    const files = execFileSync('unzip', ['-Z1', join(root, 'first', 'dist', entry.filename)], { encoding: 'utf8' }).trim().split('\n');
    assert(files.includes('composer.json') && files.includes('recipe.yml') && files.includes('LICENSE'));
    assert(files.every((path) => !/^(packages|tests|\.github)\//.test(path)));
    assert(files.every((path) => !path.includes('..')));
    const composer = JSON.parse(execFileSync('unzip', ['-p', join(root, 'first', 'dist', entry.filename), 'composer.json'], { encoding: 'utf8' }));
    assert.equal(composer.name, name);
    assert.equal(composer.version, undefined);
  }
  const mediaFiles = execFileSync('unzip', ['-Z1', join(root, 'first', 'dist', first.packages['fourkitchens/sous-media'].filename)], { encoding: 'utf8' });
  assert.match(mediaFiles, /assets\/example\.svg/);
  assert.match(mediaFiles, /recipes\/nested\/recipe\.yml/);
  const index = JSON.parse(await readFile(join(root, 'first', 'site', 'packages.json'), 'utf8'));
  assert.deepEqual(Object.keys(index.packages), packageNames);
  assert.match(await readFile(join(root, 'first', 'site', 'index.html'), 'utf8'), /Composer index/);
});

test('historical metadata remains exact and a changed published version is rejected', async (t) => {
  const root = await fixture(t);
  const old = await run(root, 'old');
  const previous = [join(root, 'old', 'release-manifest.json')];
  await changeManifest(root, 'sous-media', (manifest) => { manifest.description = 'Updated defaults'; manifest.require['drupal/core'] = '^11.5'; });
  const current = await run(root, 'current', { version: '2.0.1', distBaseUrl: base.replace('2.0.0', '2.0.1'), previous });
  const index = JSON.parse(await readFile(join(root, 'current', 'site', 'packages.json'), 'utf8'));
  for (const name of packageNames) {
    assert.deepEqual(index.packages[name]['2.0.0'], old.packages[name].metadata);
    assert.deepEqual(index.packages[name]['2.0.1'], current.packages[name].metadata);
  }
  assert.deepEqual(makeIndex([old, current, old]), index);
  await assert.rejects(run(root, 'conflict', { previous }), /Published package\/version changed/);
  await assert.rejects(run(root, 'old'), /Existing archive bytes changed/);
  const corrupt = structuredClone(old);
  corrupt.packages['fourkitchens/sous-media'].metadata.source = { type: 'git', url: 'https://example.com/monorepo.git', reference };
  assert.throws(() => validateRelease(corrupt), /must not hardcode/);
  const changed = structuredClone(old);
  changed.packages['fourkitchens/sous-media'].sha256 = '0'.repeat(64);
  assert.throws(() => makeIndex([old, changed]), /Published package\/version changed/);
});

test('validation blocks accidental packages, missing files, graph leaks and symlinks', async (t) => {
  const root = await fixture(t);
  const media = join(root, 'packages', 'sous-media');
  await mkdir(join(media, 'src'));
  await assert.rejects(validatePackages(root), /Unexpected package entry/);
  await rm(join(media, 'src'), { recursive: true });
  await symlink(join(media, 'README.md'), join(media, 'LICENSE.link'));
  await assert.rejects(validatePackages(root), /symlinks are not permitted/);
  await rm(join(media, 'LICENSE.link'));
  await changeManifest(root, 'sous-core-layout-builder', (manifest) => { manifest.require['fourkitchens/sous-paragraphs'] = '^2.0'; });
  await assert.rejects(validatePackages(root), /Paragraphs leaked/);
  await changeManifest(root, 'sous-core-layout-builder', (manifest) => { delete manifest.require['fourkitchens/sous-paragraphs']; });
  await changeManifest(root, 'sous-admin', (manifest) => { manifest.require['fourkitchens/sous-core-layout-builder'] = '^2.0'; });
  await assert.rejects(validatePackages(root), /Core Layout Builder leaked/);
  await changeManifest(root, 'sous-admin', (manifest) => { delete manifest.require['fourkitchens/sous-core-layout-builder']; });
  await writeFile(join(media, 'config', 'example.sous-media.yml'), 'dependencies:\n  module:\n    - layout_builder\n');
  await assert.rejects(validatePackages(root), /Core Layout Builder configuration leaked/);
  await writeFile(join(media, 'config', 'example.sous-media.yml'), 'dependencies:\n  module:\n    - paragraphs\n');
  await assert.rejects(validatePackages(root), /Paragraphs configuration leaked/);
  await writeFile(join(media, 'config', 'example.sous-media.yml'), 'label: Media\n');
  await changeManifest(root, 'sous-media', (manifest) => { manifest.require['drupal/core'] = 'dev-main'; });
  await assert.rejects(validatePackages(root), /Unbounded\/development constraint/);
  await changeManifest(root, 'sous-media', (manifest) => { manifest.require['drupal/core'] = '^11.4.11'; });
  await rm(join(media, 'recipe.yml'));
  await assert.rejects(validatePackages(root), /missing recipe.yml/);
});

test('build inputs require stable releases, full references and safe archive locations', async (t) => {
  const root = await fixture(t);
  await assert.rejects(run(root, 'invalid', { version: '2.0.0-beta1' }), /stable 2.x/);
  await assert.rejects(run(root, 'invalid', { reference: 'HEAD' }), /full lowercase/);
  await assert.rejects(run(root, 'invalid', { distBaseUrl: 'http://example.com/dist' }), /HTTPS/);
  await assert.rejects(run(root, 'invalid', { distBaseUrl: 'https://user:password@example.com/dist' }), /credentials/);
  await assert.rejects(run(root, 'packages/sous-media/output'), /outside package source/);
  const local = await run(root, 'local', { distBaseUrl: 'http://127.0.0.1:1234/dist' });
  assert.equal(local.packages['fourkitchens/sous-media'].metadata.dist.url, 'http://127.0.0.1:1234/dist/sous-media-2.0.0.zip');
  const incomplete = structuredClone(local);
  delete incomplete.packages['fourkitchens/sous-media'];
  assert.throws(() => validateRelease(incomplete), /exact nine package IDs/);
});

test('invoking the CLI through a symlink executes validation', async (t) => {
  const root = await fixture(t);
  await mkdir(join(root, 'tools'));
  await copyFile(fileURLToPath(new URL('../tools/build.mjs', import.meta.url)), join(root, 'tools/build.mjs'));
  const alias = join(root, 'build-alias.mjs');
  await symlink(join(root, 'tools/build.mjs'), alias);
  const output = execFileSync(process.execPath, [alias, '--check'], { encoding: 'utf8' });
  assert.match(output, /Validated 9 independently installable recipe packages/);
});
