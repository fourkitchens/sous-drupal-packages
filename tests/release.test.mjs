import assert from 'node:assert/strict';
import { mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import { assertPublishContext, repository, verifyFiles } from '../tools/release.mjs';

const approved = {
  GITHUB_REPOSITORY: repository, GITHUB_EVENT_NAME: 'workflow_dispatch', GITHUB_REF: 'refs/heads/main',
  SOUS_PUBLISHING_ENABLED: 'true', SOUS_CONFIRM: 'publish 2.0.0', GITHUB_SHA: 'a'.repeat(40),
  SOUS_IMMUTABILITY_READ_TOKEN: 'fake-test-token',
  SOUS_FULL_STARTER_GATE_PASSED: 'true', SOUS_STARTER_ACCEPTANCE_SHA: 'b'.repeat(40),
};

test('publication fails closed outside an explicitly approved main dispatch', () => {
  assert.doesNotThrow(() => assertPublishContext(approved, '2.0.0'));
  for (const [key, value] of Object.entries({
    GITHUB_REPOSITORY: 'someone/fork', GITHUB_EVENT_NAME: 'pull_request', GITHUB_REF: 'refs/heads/codex/release',
    SOUS_PUBLISHING_ENABLED: '', SOUS_CONFIRM: 'publish', GITHUB_SHA: 'main',
    SOUS_IMMUTABILITY_READ_TOKEN: '',
    SOUS_FULL_STARTER_GATE_PASSED: 'false', SOUS_STARTER_ACCEPTANCE_SHA: '',
  })) assert.throws(() => assertPublishContext({ ...approved, [key]: value }, '2.0.0'), key);
  for (const version of ['2.0.0-rc1', 'dev-main', '1.0.0', '02.0.0', '../2.0.0']) assert.throws(() => assertPublishContext(approved, version));
});

test('asset readback rejects modified or missing uploaded bytes', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'sous-release-test-'));
  try {
    const bytes = Buffer.from('candidate archive');
    const entry = { filename: 'recipe.zip', bytes: bytes.length, sha256: createHash('sha256').update(bytes).digest('hex') };
    const manifest = { packages: { recipe: entry } };
    await writeFile(join(directory, entry.filename), bytes);
    await verifyFiles(manifest, directory);
    await writeFile(join(directory, entry.filename), Buffer.from('tampered archive!'));
    await assert.rejects(verifyFiles(manifest, directory), /Asset bytes differ/);
    await rm(join(directory, entry.filename));
    await assert.rejects(verifyFiles(manifest, directory), /ENOENT/);
  }
  finally { await rm(directory, { recursive: true, force: true }); }
});

test('workflow publication is opt-in, serialized, and uses the verified site snapshot', async () => {
  const workflow = await readFile(new URL('../.github/workflows/publish.yml', import.meta.url), 'utf8');
  assert.match(workflow, /SOUS_PUBLISHING_ENABLED == 'true'/);
  assert.match(workflow, /github\.ref == 'refs\/heads\/main'/);
  assert.match(workflow, /cancel-in-progress: false/);
  assert.match(workflow, /node tools\/release\.mjs publish/);
  assert.match(workflow, /path: build\/site/);
});
