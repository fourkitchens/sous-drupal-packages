// semantic-release prepares ZIPs; GitHub hosts them; Pages serves their index.
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { realpathSync } from 'node:fs';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { build, makeIndex, catalog, validateRelease } from './build.mjs';

const repository = 'fourkitchens/sous-drupal-packages';
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const output = join(root, 'build');
// This tag records release history only; hosted package versions begin at 2.0.0.
const baselineTag = '1.0.0';
const baselineReference = 'd3dcd219d251e322a3b206a6bf12889712b2b3f0';

function verifyBaseline(cwd) {
  const reference = execFileSync(
    'git',
    ['rev-parse', `refs/tags/${baselineTag}^{commit}`],
    { cwd, encoding: 'utf8' }
  ).trim();
  if (reference !== baselineReference)
    throw new Error(
      `Release baseline ${baselineTag} must point to ${baselineReference}.`
    );
}

export function verifyConditions(_config, { cwd }) {
  verifyBaseline(cwd);
}
const stable = /^[1-9]\d*\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/;
const baseURL = (version) =>
  `https://github.com/${repository}/releases/download/${version}/`;
const sha256 = (bytes) => createHash('sha256').update(bytes).digest('hex');

async function download(url) {
  const response = await fetch(url, { signal: AbortSignal.timeout(30_000) });
  if (!response.ok)
    throw new Error(
      `Cannot read published asset ${url}: HTTP ${response.status}`
    );
  return Buffer.from(await response.arrayBuffer());
}

async function history() {
  const releases = JSON.parse(
    execFileSync(
      'gh',
      [
        'api',
        '--paginate',
        '--slurp',
        `repos/${repository}/releases?per_page=100`,
      ],
      { encoding: 'utf8', maxBuffer: 20 * 1024 * 1024 }
    )
  ).flat();
  const manifests = [];
  await mkdir(join(output, 'history'), { recursive: true });
  for (const release of releases.filter(
    (entry) =>
      !entry.draft &&
      !entry.prerelease &&
      entry.tag_name !== baselineTag &&
      stable.test(entry.tag_name)
  )) {
    const manifest = validateRelease(
      JSON.parse(
        await download(`${baseURL(release.tag_name)}release-manifest.json`)
      )
    );
    if (
      manifest.version !== release.tag_name ||
      manifest.distBaseUrl !== baseURL(release.tag_name)
    )
      throw new Error(`Invalid published collection: ${release.tag_name}`);
    manifests.push(manifest);
    await writeFile(
      join(output, 'history', `${manifest.version}.json`),
      `${JSON.stringify(manifest, null, 2)}\n`
    );
  }
  return manifests;
}

// A merge always produces at least a patch; Conventional Commits can raise it.
export function analyzeCommits(_config, { commits, lastRelease }) {
  if (!commits.length) return null;
  return lastRelease.gitTag === baselineTag ? 'major' : 'patch';
}

// Called before semantic-release creates its tag and publishes all release assets.
export async function prepare(_config, { nextRelease, logger }) {
  await rm(output, { recursive: true, force: true });
  const previous = await history();
  if (previous.some((manifest) => manifest.version === nextRelease.version))
    throw new Error(`Release ${nextRelease.version} already exists.`);
  await build({
    root,
    version: nextRelease.version,
    reference: nextRelease.gitHead,
    output,
    distBaseUrl: baseURL(nextRelease.version),
    previous: previous.map((manifest) =>
      join(output, 'history', `${manifest.version}.json`)
    ),
  });
  logger.log(
    'Prepared recipe ZIPs for shared version %s.',
    nextRelease.version
  );
}

// Always rebuild from published manifests, including when a Pages deploy is retried.
async function index() {
  verifyBaseline(root);
  const manifests = await history();
  if (!manifests.length)
    throw new Error(
      'No published collection is available for the Composer index.'
    );
  manifests.sort((a, b) => {
    const aa = a.version.split('.').map(Number),
      bb = b.version.split('.').map(Number);
    for (let part = 0; part < 3; part++)
      if (aa[part] !== bb[part]) return aa[part] - bb[part];
    return 0;
  });
  const tags = execFileSync('git', ['tag', '--merged', 'HEAD'], {
    cwd: root,
    encoding: 'utf8',
  })
    .trim()
    .split('\n')
    .filter((tag) => stable.test(tag));
  const missing = tags.filter(
    (tag) =>
      tag !== baselineTag &&
      !manifests.some((manifest) => manifest.version === tag)
  );
  if (missing.length)
    throw new Error(
      `Tags have no published collection; inspect the failed release before retrying: ${missing.join(', ')}`
    );
  const latest = manifests.at(-1);
  for (const entry of Object.values(latest.packages)) {
    const bytes = await download(entry.metadata.dist.url);
    if (bytes.length !== entry.bytes || sha256(bytes) !== entry.sha256)
      throw new Error(
        `Published ZIP differs from its manifest: ${entry.filename}`
      );
  }
  await mkdir(join(output, 'site'), { recursive: true });
  await writeFile(
    join(output, 'site', 'packages.json'),
    `${JSON.stringify(makeIndex(manifests), null, 2)}\n`
  );
  await writeFile(join(output, 'site', 'index.html'), catalog(latest));
  console.log(
    `Composer index contains ${manifests.length} published collection versions.`
  );
}

if (
  process.argv[1] &&
  realpathSync(process.argv[1]) === fileURLToPath(import.meta.url)
) {
  try {
    if (process.argv.length !== 3 || process.argv[2] !== 'index')
      throw new Error('Usage: node tools/release.mjs index');
    await index();
  } catch (error) {
    console.error(error.message);
    process.exitCode = 1;
  }
}
