#!/usr/bin/env node

// Build one deterministic archive per recipe and a dist-only Composer index.
// No network requests, tags, release uploads or Pages deployments happen here.
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { realpathSync } from 'node:fs';
import {
  chmod,
  copyFile,
  lstat,
  mkdir,
  mkdtemp,
  readFile,
  readdir,
  rm,
  utimes,
  writeFile,
} from 'node:fs/promises';
import { tmpdir } from 'node:os';
import {
  basename,
  dirname,
  isAbsolute,
  join,
  relative,
  resolve,
  sep,
} from 'node:path';
import { fileURLToPath } from 'node:url';

const permittedDirectories = new Set([
  'assets',
  'config',
  'content',
  'recipes',
]);
const excluded = new Set([
  '.git',
  '.github',
  '.DS_Store',
  '.gitkeep',
  'node_modules',
  'tests',
  'devops',
  'package.json',
  'package-lock.json',
  '.nvmrc',
  '.gitignore',
  'release.config.js',
]);
const permittedFile = (name) =>
  ['composer.json', 'recipe.yml', 'README.md', 'NOTICE', 'NOTICE.md'].includes(
    name
  ) || /^LICENSE(?:[-.][A-Za-z0-9_.-]+)?$/.test(name);
const epoch = new Date('1980-01-01T00:00:00Z');
const sha = (algorithm, data) =>
  createHash(algorithm).update(data).digest('hex');
const json = (data) => `${JSON.stringify(data, null, 2)}\n`;
const check = (condition, message) => {
  if (!condition) throw new Error(message);
};
const stableVersion = (value) =>
  typeof value === 'string' &&
  /^[1-9]\d*\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/.test(value);
const referenceValid = (value) =>
  typeof value === 'string' && /^[0-9a-f]{40}$/.test(value);
const isInside = (parent, child) => {
  const path = relative(parent, child);
  return (
    path !== '' &&
    path !== '..' &&
    !path.startsWith(`..${sep}`) &&
    !isAbsolute(path)
  );
};

function archiveBase(value) {
  const url = new URL(value);
  const loopback = ['localhost', '127.0.0.1', '[::1]'].includes(url.hostname);
  check(
    url.protocol === 'https:' || (url.protocol === 'http:' && loopback),
    'Archive URLs require HTTPS (HTTP is allowed only for loopback test servers).'
  );
  check(
    !url.username && !url.password && !url.search && !url.hash,
    'Archive base URL must not contain credentials, query or fragment.'
  );
  url.pathname = `${url.pathname.replace(/\/+$/, '')}/`;
  return url.href;
}

async function packageFiles(directory) {
  const files = [];
  async function visit(folder, prefix = '') {
    const entries = (await readdir(folder)).sort();
    for (const name of entries) {
      const path = join(folder, name);
      const info = await lstat(path);
      check(
        !info.isSymbolicLink(),
        `Package symlinks are not permitted: ${path}`
      );
      check(
        info.isDirectory() || info.isFile(),
        `Unsupported package entry: ${path}`
      );
      if (excluded.has(name)) continue;
      const file = prefix ? `${prefix}/${name}` : name;
      check(
        // eslint-disable-next-line no-control-regex -- Reject unsafe archive filenames.
        !/[\x00-\x1f\x7f\\]/.test(file),
        `Unsupported archive filename: ${file}`
      );
      if (!prefix)
        check(
          info.isDirectory()
            ? permittedDirectories.has(name)
            : permittedFile(name),
          `Unexpected package entry: ${path}`
        );
      if (info.isDirectory()) await visit(path, file);
      else files.push(file);
    }
  }
  await visit(directory);
  return files.sort();
}

function validateManifest(manifest, name) {
  check(
    /^fourkitchens\/sous-[a-z0-9]+(?:-[a-z0-9]+)*$/.test(name),
    `Invalid package name: ${name}`
  );
  check(
    manifest.name === name,
    `Expected Composer name ${name}, got ${manifest.name}.`
  );
  check(manifest.type === 'drupal-recipe', `${name} must be a drupal-recipe.`);
  check(
    manifest.license === 'GPL-2.0-or-later',
    `${name} must declare GPL-2.0-or-later.`
  );
  check(
    typeof manifest.description === 'string' && manifest.description.length > 0,
    `${name} needs a description.`
  );
  check(
    (manifest['minimum-stability'] ?? 'stable') === 'stable',
    `${name} must use stable minimum stability.`
  );
  check(
    !['source', 'dist', 'version'].some((key) => Object.hasOwn(manifest, key)),
    `${name} source manifests must not hardcode version/source/dist metadata.`
  );
  check(
    !manifest.extra?.['installer-name'] ||
      manifest.extra['installer-name'] === basename(name),
    `${name} must preserve its installed recipe basename.`
  );
  for (const [dependency, constraint] of Object.entries(
    manifest.require ?? {}
  )) {
    check(
      typeof constraint === 'string' &&
        /^(?:\^|~|\d)/.test(constraint) &&
        !/\*|dev(?:-|\b)|(?:^|[\s|])>=?/.test(constraint),
      `Unbounded/development constraint: ${name} requires ${dependency} ${constraint}.`
    );
  }
}

export async function validatePackages(root) {
  const packages = new Map();
  const entries = (await readdir(join(root, 'packages')))
    .filter((name) => !name.startsWith('.'))
    .sort();
  check(entries.length > 0, 'Add at least one recipe under packages/.');
  for (const folder of entries) {
    const name = `fourkitchens/${folder}`;
    const directory = join(root, 'packages', folder);
    check(
      (await lstat(directory)).isDirectory() &&
        !(await lstat(directory)).isSymbolicLink(),
      `Invalid package directory: ${directory}`
    );
    const files = await packageFiles(directory);
    for (const required of [
      'composer.json',
      'recipe.yml',
      'README.md',
      'LICENSE',
    ])
      check(files.includes(required), `${name} is missing ${required}.`);
    const manifest = JSON.parse(
      await readFile(join(directory, 'composer.json'), 'utf8')
    );
    validateManifest(manifest, name);
    const recipe = await readFile(join(directory, 'recipe.yml'), 'utf8');
    check(
      /^name:\s*\S/m.test(recipe) && /^type:\s*\S/m.test(recipe),
      `${name} needs recipe name/type metadata.`
    );
    for (const file of [
      ...(typeof manifest.bin === 'string'
        ? [manifest.bin]
        : (manifest.bin ?? [])),
      ...Object.values(manifest.autoload?.files ?? {}),
    ]) {
      check(
        typeof file === 'string' && files.includes(file),
        `${name} refers to a missing/outside archive file: ${file}`
      );
    }
    packages.set(name, { directory, manifest, files });
  }
  for (const [name, source] of packages) {
    for (const dependency of Object.keys(source.manifest.require ?? {})) {
      if (dependency.startsWith('fourkitchens/sous-'))
        check(
          packages.has(dependency),
          `Unknown package dependency: ${name} requires ${dependency}.`
        );
    }
  }
  return packages;
}

export function validateRelease(manifest) {
  check(
    manifest?.schemaVersion === 1 &&
      stableVersion(manifest.version) &&
      referenceValid(manifest.reference),
    'Invalid release manifest version/reference/schema.'
  );
  const base = archiveBase(manifest.distBaseUrl);
  check(
    manifest.packages && Object.keys(manifest.packages).length > 0,
    'Release manifest needs package records.'
  );
  for (const [name, entry] of Object.entries(manifest.packages)) {
    check(
      entry &&
        entry.metadata &&
        entry.metadata.name === name &&
        entry.metadata.version === manifest.version,
      `Invalid release metadata: ${name}`
    );
    const expected = `${basename(name)}-${manifest.version}.zip`;
    check(
      entry.filename === expected &&
        Number.isSafeInteger(entry.bytes) &&
        entry.bytes > 0 &&
        /^[0-9a-f]{64}$/.test(entry.sha256),
      `Invalid release archive record: ${name}`
    );
    const metadata = { ...entry.metadata };
    delete metadata.version;
    delete metadata.dist;
    validateManifest(metadata, name);
    check(
      !entry.metadata.source &&
        entry.metadata.dist?.type === 'zip' &&
        entry.metadata.dist.url === new URL(expected, base).href &&
        entry.metadata.dist.reference === manifest.reference &&
        /^[0-9a-f]{40}$/.test(entry.metadata.dist.shasum),
      `Invalid dist-only release metadata: ${name}`
    );
  }
  return manifest;
}

export function makeIndex(manifests) {
  const packages = {};
  const records = new Map();
  for (const manifest of manifests) {
    validateRelease(manifest);
    for (const [name, entry] of Object.entries(manifest.packages)) {
      const key = `${name}@${manifest.version}`;
      check(
        !records.has(key) || json(records.get(key)) === json(entry),
        `Published package/version changed: ${key}`
      );
      records.set(key, entry);
    }
  }
  const names = [
    ...new Set(manifests.flatMap((manifest) => Object.keys(manifest.packages))),
  ].sort();
  for (const name of names) {
    packages[name] = {};
    const versions = [...records]
      .filter(([key]) => key.startsWith(`${name}@`))
      .sort(([a], [b]) => a.localeCompare(b, 'en', { numeric: true }));
    for (const [key, entry] of versions)
      packages[name][key.slice(name.length + 1)] = entry.metadata;
  }
  return { packages };
}

export function catalog(manifest) {
  const escape = (value) =>
    String(value).replace(
      /[&<>"']/g,
      (char) =>
        ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#39;',
        })[char]
    );
  const rows = Object.entries(manifest.packages)
    .map(
      ([name, entry]) =>
        `<tr><th scope="row"><code>${escape(name)}</code></th><td>${escape(entry.metadata.description)}</td><td><a href="${escape(entry.metadata.dist.url)}">${escape(manifest.version)} ZIP</a></td></tr>`
    )
    .join('\n');
  return `<!doctype html>\n<html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sous Drupal packages</title><style>body{font:1rem/1.6 system-ui;max-width:75rem;margin:2rem auto;padding:0 1rem}table{border-collapse:collapse;width:100%}th,td{padding:.75rem;text-align:left;vertical-align:top;border-bottom:1px solid #bbb}code{overflow-wrap:anywhere}a{color:#0645ad}:focus-visible{outline:3px solid #0645ad;outline-offset:3px}</style><main><h1>Sous Drupal packages</h1><p>Install the shared defaults and the composition your site needs. Core Layout Builder and Paragraphs are independent optional packages.</p><p><a href="packages.json">Composer index</a> · <a href="https://github.com/fourkitchens/sous-drupal-packages">Source and contribution guide</a></p><table><caption>Current package collection ${escape(manifest.version)}</caption><thead><tr><th scope="col">Composer name</th><th scope="col">Purpose</th><th scope="col">Download</th></tr></thead><tbody>${rows}</tbody></table></main></html>\n`;
}

export async function build({
  root,
  version,
  reference,
  output,
  distBaseUrl,
  previous = [],
}) {
  check(
    stableVersion(version),
    'Use a stable collection version, such as 1.0.0.'
  );
  check(
    referenceValid(reference),
    'Use the reviewed full lowercase 40-character commit reference.'
  );
  root = resolve(root);
  output = resolve(output);
  check(
    !isInside(join(root, 'packages'), output) &&
      output !== root &&
      output !== join(root, 'packages'),
    'Build output must be outside package source directories.'
  );
  const base = archiveBase(distBaseUrl);
  const packages = await validatePackages(root);
  const history = [];
  for (const file of previous)
    history.push(validateRelease(JSON.parse(await readFile(file, 'utf8'))));
  const manifest = {
    schemaVersion: 1,
    version,
    reference,
    distBaseUrl: base,
    packages: {},
  };
  const work = await mkdtemp(join(tmpdir(), 'sous-archives-'));
  try {
    await mkdir(join(output, 'dist'), { recursive: true });
    await mkdir(join(output, 'site'), { recursive: true });
    for (const [name, source] of packages) {
      const stage = join(work, basename(name));
      await mkdir(stage);
      for (const file of source.files) {
        const destination = join(stage, file);
        await mkdir(dirname(destination), { recursive: true });
        if (file === 'composer.json') {
          const manifest = structuredClone(source.manifest);
          for (const dependency of Object.keys(manifest.require ?? {})) {
            if (packages.has(dependency))
              manifest.require[dependency] = `^${version}`;
          }
          await writeFile(destination, json(manifest));
        } else await copyFile(join(source.directory, file), destination);
        await chmod(destination, 0o644);
        await utimes(destination, epoch, epoch);
      }
      const filename = `${basename(name)}-${version}.zip`;
      const archive = join(output, 'dist', filename);
      // ZIP adds to existing files by default. Build a fresh archive, then
      // refuse to replace a previous output whose immutable bytes differ.
      const candidate = join(work, filename);
      execFileSync('zip', ['-X', '-q', candidate, ...source.files], {
        cwd: stage,
        env: { ...process.env, TZ: 'UTC' },
        stdio: 'pipe',
      });
      const bytes = await readFile(candidate);
      try {
        check(
          sha('sha256', await readFile(archive)) === sha('sha256', bytes),
          `Existing archive bytes changed: ${filename}`
        );
      } catch (error) {
        if (error.code !== 'ENOENT') throw error;
      }
      await copyFile(candidate, archive);
      const requirements = { ...source.manifest.require };
      for (const dependency of Object.keys(requirements)) {
        if (packages.has(dependency)) requirements[dependency] = `^${version}`;
      }
      const metadata = {
        ...source.manifest,
        require: requirements,
        version,
        dist: {
          type: 'zip',
          url: new URL(filename, base).href,
          reference,
          shasum: sha('sha1', bytes),
        },
      };
      manifest.packages[name] = {
        filename,
        bytes: bytes.length,
        sha256: sha('sha256', bytes),
        metadata,
      };
    }
    const index = makeIndex([...history, manifest]);
    await writeFile(join(output, 'release-manifest.json'), json(manifest));
    await writeFile(join(output, 'site', 'packages.json'), json(index));
    await writeFile(join(output, 'site', 'index.html'), catalog(manifest));
    return manifest;
  } finally {
    await rm(work, { recursive: true, force: true });
  }
}

const direct =
  process.argv[1] &&
  realpathSync(process.argv[1]) === fileURLToPath(import.meta.url);
if (direct) {
  try {
    const args = process.argv.slice(2);
    const options = {
      root: resolve(dirname(fileURLToPath(import.meta.url)), '..'),
      previous: [],
    };
    for (let index = 0; index < args.length; index++) {
      const flag = args[index];
      if (flag === '--check') {
        options.check = true;
        continue;
      }
      const key = {
        '--version': 'version',
        '--reference': 'reference',
        '--output': 'output',
        '--dist-base-url': 'distBaseUrl',
        '--previous': 'previous',
      }[flag];
      check(
        key && args[index + 1] && !args[index + 1].startsWith('--'),
        `Unknown or incomplete argument: ${flag}`
      );
      if (key === 'previous') options.previous.push(resolve(args[++index]));
      else {
        check(options[key] === undefined, `Duplicate argument: ${flag}`);
        options[key] = args[++index];
      }
    }
    if (options.check) {
      check(args.length === 1, '--check does not accept build arguments.');
      console.log(
        `Validated ${(await validatePackages(options.root)).size} independently installable recipe packages.`
      );
    } else {
      check(
        options.output && options.distBaseUrl,
        'Required: --version --reference --output --dist-base-url; optionally --previous FILE.'
      );
      const result = await build(options);
      console.log(
        `Built ${Object.keys(result.packages).length} package ZIPs and Composer index for ${result.version}.`
      );
    }
  } catch (error) {
    console.error(error.message);
    process.exitCode = 1;
  }
}
