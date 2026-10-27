#!/usr/bin/env node

// Resolve real Drupal packages; never bootstrap an existing developer site.
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { createServer } from 'node:http';
import { chmod, cp, mkdir, mkdtemp, readFile, readdir, rm, stat, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { basename, dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { build, packageNames } from '../tools/build.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const args = process.argv.slice(2);
const cases = ['default', 'core-layout-builder', 'paragraphs', 'admin-only', 'config-split'];
let selectedCase;
let splitRequested = false;
let keep = false;
for (let i = 0; i < args.length; i++) {
  if (args[i] === '--keep') keep = true;
  else if (args[i] === '--config-split') splitRequested = true;
  else if (args[i] === '--case' && cases.includes(args[i + 1])) selectedCase = args[++i];
  else throw new Error('Usage: node tests/consumer.mjs [--case default|core-layout-builder|paragraphs|admin-only|config-split] [--config-split] [--keep]');
}
const selectedCases = (selectedCase ? [selectedCase === 'config-split' ? 'default' : selectedCase] : cases.slice(0, 4))
  .flatMap((selection) => (selectedCase || splitRequested ? [splitRequested || selectedCase === 'config-split'] : [false, true])
    .map((split) => ({ selection, split })));
const work = await mkdtemp(join(tmpdir(), 'sous-package-consumer-'));
const output = join(work, 'publication');
const requests = [];
const server = createServer(async (request, response) => {
  const path = new URL(request.url, 'http://localhost').pathname;
  requests.push(path);
  try {
    assert.match(path, /^\/(?:dist\/[a-z0-9.-]+\.zip|site\/packages\.json)$/);
    const data = await readFile(join(output, path));
    response.writeHead(200, { 'content-type': path.endsWith('.zip') ? 'application/zip' : 'application/json' });
    response.end(data);
  }
  catch {
    response.writeHead(404).end();
  }
});
await new Promise((done, fail) => { server.once('error', fail); server.listen(0, '127.0.0.1', done); });
const url = `http://127.0.0.1:${server.address().port}`;
let step = 0;

async function run(command, argv, cwd, env = {}) {
  const log = join(work, `${String(++step).padStart(3, '0')}-${basename(command)}.log`);
  let data = '';
  const result = await new Promise((done, fail) => {
    const child = spawn(command, argv, { cwd, env: { ...process.env, ...env }, stdio: ['ignore', 'pipe', 'pipe'] });
    child.stdout.on('data', (part) => { data += part; });
    child.stderr.on('data', (part) => { data += part; });
    child.on('error', fail);
    child.on('close', done);
  });
  await writeFile(log, data);
  if (result !== 0) throw new Error(`${command} ${argv.join(' ')} failed (${result}); ${log}\n${data.slice(-14000)}`);
  return data;
}

async function exists(path) {
  try { await stat(path); return true; }
  catch (error) { if (error.code === 'ENOENT') return false; throw error; }
}

const manifests = new Map(await Promise.all(packageNames.map(async (name) => [name, JSON.parse(await readFile(join(root, 'packages', basename(name), 'composer.json')))])));
function closure(names) {
  const result = new Set(names);
  for (const name of result) {
    for (const dependency of Object.keys(manifests.get(name).require ?? {})) {
      if (packageNames.includes(dependency)) result.add(dependency);
    }
  }
  return [...result].sort();
}

async function verifyInstall(project, expected, requestStart) {
  const manifest = JSON.parse(await readFile(join(project, 'composer.json')));
  const lock = JSON.parse(await readFile(join(project, 'composer.lock')));
  const own = lock.packages.filter((item) => packageNames.includes(item.name));
  assert.deepEqual(own.map((item) => item.name).sort(), expected, 'Composer resolved an unselected recipe or lost a selected recipe.');
  assert.deepEqual(closure(Object.keys(manifest.require).filter((name) => packageNames.includes(name))), expected, 'Native recipe unpacking removed the retained root requirements.');
  assert.deepEqual(manifest.extra['drupal-recipe-unpack'].ignore.toSorted(), packageNames.toSorted());
  const split = expected.includes('fourkitchens/sous-config-split');
  assert.equal(lock.packages.some((item) => item.name === 'drupal/config_split'), split, 'Config Split module must remain optional.');
  assert.equal(await exists(join(project, 'web/modules/contrib/config_split')), split, 'Unexpected optional Config Split module files.');
  for (const item of own) {
    assert.equal(item.version, '2.0.0');
    assert.equal(item.dist.type, 'zip');
    assert.ok(item.dist.url.startsWith(`${url}/dist/`));
    assert.match(item.dist.shasum, /^[a-f0-9]{40}$/);
    assert.ok(!item.source, `${item.name} unexpectedly has a source fallback.`);
    assert.ok(await exists(join(project, 'web/recipes', basename(item.name), 'recipe.yml')));
    assert.ok(!(await exists(join(project, 'web/recipes', basename(item.name), 'tests'))));
  }
  assert.deepEqual((await readdir(join(project, 'web/recipes'))).sort(), expected.map((name) => basename(name)).sort(), 'An unselected recipe directory was installed.');
  const zipRequests = requests.slice(requestStart).filter((path) => path.startsWith('/dist/'));
  assert.deepEqual([...new Set(zipRequests)].sort(), expected.map((name) => `/dist/${basename(name)}-2.0.0.zip`).sort(), 'Cold-cache Composer did not fetch exactly the selected recipe ZIPs.');
  if (!expected.includes('fourkitchens/sous-paragraphs')) {
    assert.ok(!lock.packages.some((item) => /^drupal\/paragraphs(?:_|$)/.test(item.name)), 'Paragraphs module was resolved without the Paragraphs composition.');
    for (const name of ['paragraphs', 'paragraphs_ee', 'paragraphs_collapsible', 'paragraphs_features']) {
      assert.ok(!(await exists(join(project, 'web/modules/contrib', name))), `Unselected ${name} module directory exists.`);
    }
  }
}

async function settings(project, database) {
  const directory = join(project, 'web/sites/default');
  await mkdir(directory, { recursive: true });
  await chmod(directory, 0o755);
  await mkdir(join(directory, 'files'), { recursive: true });
  const php = (value) => `'${value.replaceAll('\\', '\\\\').replaceAll("'", "\\'")}'`;
  await writeFile(join(directory, 'settings.php'), `<?php
$settings['hash_salt'] = 'disposable-sous-package-fixture';
$settings['config_sync_directory'] = ${php(join(project, 'config/default'))};
$settings['file_private_path'] = ${php(join(project, 'private'))};
$settings['trusted_host_patterns'] = ['^localhost$', '^127\\\\.0\\\\.0\\\\.1$'];
$databases['default']['default'] = ['driver' => 'sqlite', 'database' => ${php(database)}, 'prefix' => '', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite', 'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/'];
// Fixture-only policy: packages supply native definitions, projects choose flags.
$environment = getenv('SOUS_TEST_ENVIRONMENT') ?: 'production';
$settings['config_split_priorities']['_splits'] = -100;
foreach (['local', 'staging'] as $name) {
  $config['config_split.config_split.sous_' . $name]['status'] = $environment === $name;
}
`);
}

try {
  await build({ root, version: '2.0.0', reference: '0'.repeat(40), output, distBaseUrl: `${url}/dist/`, previous: [] });
  for (const { selection, split } of selectedCases) {
    const label = selection + (split ? '-config-split' : '');
    console.log(`Consumer ${label}: resolving real Drupal dependencies from a cold Composer cache.`);
    const project = join(work, label);
    await mkdir(project);
    const manifest = JSON.parse(await readFile(join(root, 'tests/fixtures/composer.json')));
    manifest.repositories.unshift({ type: 'composer', url: `${url}/site`, only: ['fourkitchens/sous-*'] });
    manifest.config['secure-http'] = false; // Only the disposable loopback index uses HTTP.
    const composition = { default: 'sous-content-types', 'core-layout-builder': 'sous-core-layout-builder', paragraphs: 'sous-paragraphs' }[selection];
    const required = ['fourkitchens/sous-admin', 'fourkitchens/sous-emulsify'];
    if (composition) required.push(`fourkitchens/${composition}`);
    if (split) required.push('fourkitchens/sous-config-split');
    for (const name of required) manifest.require[name] = '^2.0';
    await writeFile(join(project, 'composer.json'), `${JSON.stringify(manifest, null, 2)}\n`);
    await cp(join(root, 'tests/fixtures/patches'), join(project, 'patches'), { recursive: true });
    for (const folder of ['default', '_splits', 'local', 'staging']) await mkdir(join(project, 'config', folder), { recursive: true });
    const env = {
      COMPOSER_HOME: join(work, `${label}-composer-home`),
      COMPOSER_CACHE_DIR: join(work, `${label}-composer-cache`),
      COMPOSER_AUTH: '{}', COMPOSER_NO_INTERACTION: '1',
      SOUS_DISPOSABLE_TEST: '1', SOUS_TEST_REPOSITORY: root,
      SOUS_INTEGRATION_COMBINATION: selection,
      SOUS_TEST_CONFIG_SPLIT: split ? '1' : '0',
      SOUS_TEST_ENVIRONMENT: 'production',
    };
    let requestStart = requests.length;
    await run('composer', ['update', '--prefer-dist', '--no-progress', '--no-interaction'], project, env);
    const expected = closure(required);
    await verifyInstall(project, expected, requestStart);
    await run('php', [join(root, 'tests/integration-yaml.php'), join(project, 'vendor/autoload.php'), join(root, 'packages'), join(project, 'web')], project, env);
    const database = join(project, 'site.sqlite');
    await settings(project, database);
    const drush = async (argv, overrides = {}, cwd = project) => run('php', [join(cwd, 'vendor/drush/drush/drush.php'), '--root=' + join(cwd, 'web'), '--uri=http://localhost', ...argv], cwd, { ...env, ...overrides });
    const probe = (stage, overrides = {}, cwd = project) => drush(['php:script', join(root, 'tests/integration-probe.php')], { SOUS_TEST_STAGE: stage, ...overrides }, cwd);
    await drush(['site:install', 'minimal', `--db-url=sqlite://localhost/${database}?module=sqlite`, '--account-name=integration_admin', '--account-pass=disposable-only', '--site-name=Sous package consumer', '--yes']);
    for (const name of required) {
      if (name === `fourkitchens/${composition}`) await probe('views-prepare');
      await drush(['recipe', join(project, 'web/recipes', basename(name))]);
      if (name === `fourkitchens/${composition}`) await probe('views-verify');
    }
    console.log(`Consumer ${label}: installed selected ZIPs; checking native recipe configuration and authoring.`);
    await probe('verify');
    await probe('snapshot');
    await drush(['config:export', '--yes']);
    await probe('mutate');
    await drush(['config:import', '--yes']);
    await probe('roundtrip');

    if (split) {
      await probe('split-prepare');
      for (const environment of ['local', 'staging']) {
        const overrides = { SOUS_TEST_ENVIRONMENT: environment };
        await probe('split-seed', overrides);
        await drush(['config:export', '--yes'], overrides);
        await drush(['config:import', '--yes']);
        await probe('split-verify');
        await drush(['config:import', '--yes'], overrides);
        await probe('split-verify', overrides);
        await drush(['config:import', '--yes']);
      }
    }

    console.log(`Consumer ${label}: cloning only tracked manifests/config, then rebuilding from locked ZIPs with another empty cache.`);
    await writeFile(join(project, '.gitignore'), '/web/\n/vendor/\n/private/\n/site.sqlite*\n');
    await run('git', ['init', '--quiet'], project);
    await run('git', ['add', '.gitignore', 'composer.json', 'composer.lock', 'patches.lock.json', 'patches', 'config'], project);
    await run('git', ['-c', 'user.name=Sous integration', '-c', 'user.email=sous-tests@example.invalid', 'commit', '--quiet', '-m', 'Disposable locked consumer fixture'], project);
    const clone = join(work, `${label}-clean-clone`);
    await run('git', ['clone', '--quiet', '--no-hardlinks', project, clone], work);
    assert.ok(!(await exists(join(clone, 'vendor'))) && !(await exists(join(clone, 'web'))), 'The clone retained Composer-managed files.');
    const cloneEnv = { ...env, COMPOSER_HOME: join(work, `${label}-clone-home`), COMPOSER_CACHE_DIR: join(work, `${label}-clone-cache`) };
    requestStart = requests.length;
    await run('composer', ['install', '--prefer-dist', '--no-progress', '--no-interaction'], clone, cloneEnv);
    await verifyInstall(clone, expected, requestStart);
    // Test prefer-source for our packages without needlessly cloning Drupal's dependencies.
    requestStart = requests.length;
    await run('composer', ['reinstall', ...expected, '--prefer-source', '--no-progress', '--no-interaction'], clone, { ...cloneEnv, COMPOSER_CACHE_DIR: join(work, `${label}-source-cache`) });
    await verifyInstall(clone, expected, requestStart);
    for (const folder of ['default', '_splits', 'local', 'staging']) await mkdir(join(clone, 'config', folder), { recursive: true });
    await cp(database, join(clone, 'site.sqlite'));
    await settings(clone, join(clone, 'site.sqlite'));
    await drush(['cache:rebuild'], {}, clone);
    await drush(['config:import', '--yes'], {}, clone);
    await probe('verify', { SOUS_TEST_SPLIT_CUSTOMIZED: split ? '1' : '0' }, clone);
    if (split) await probe('split-verify', {}, clone);
    console.log(`PASS: ${label} cold ZIP resolution, native Drupal application, export/import, clean clone and prefer-source fallback.`);
  }
  console.log(`PASS: all ${selectedCases.length} selected consumer case(s). No existing site was modified.`);
  if (!keep) await rm(work, { recursive: true, force: true });
}
catch (error) {
  keep = true;
  console.error(error.stack);
  process.exitCode = 1;
}
finally {
  await new Promise((done) => server.close(done));
  if (keep) console.log(`Disposable fixture/logs retained at ${work}`);
}
