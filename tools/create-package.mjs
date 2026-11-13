#!/usr/bin/env node

import { cp, mkdir, readFile, readdir, rm, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const [name, ...extra] = process.argv.slice(2);

if (extra.length || !/^sous-[a-z0-9]+(?:-[a-z0-9]+)*$/.test(name ?? '')) {
  console.error(
    'Usage: npm run create-package sous-event (lowercase letters, numbers and hyphens).'
  );
  process.exit(1);
}

const destination = join(root, 'packages', name);
const title = `Sous ${name
  .slice(5)
  .split('-')
  .map((word) => word[0].toUpperCase() + word.slice(1))
  .join(' ')}`;
let created = false;

try {
  // mkdir refuses an existing package before we copy or customize any files.
  await mkdir(destination);
  created = true;
  const template = join(root, 'recipe-template');
  for (const file of await readdir(template)) {
    await cp(join(template, file), join(destination, file), {
      recursive: true,
      force: false,
      errorOnExist: true,
    });
  }
  for (const file of ['composer.json', 'recipe.yml', 'README.md']) {
    const path = join(destination, file);
    const template = await readFile(path, 'utf8');
    await writeFile(
      path,
      template
        .replaceAll('__PACKAGE_NAME__', name)
        .replaceAll('__PACKAGE_TITLE__', title)
    );
  }
  console.log(
    `Created packages/${name}. Add configuration and Composer dependencies, then run npm test.`
  );
} catch (error) {
  if (created) await rm(destination, { recursive: true, force: true });
  console.error(
    error.code === 'EEXIST'
      ? `Package packages/${name} already exists; no files changed.`
      : error.message
  );
  process.exitCode = 1;
}
