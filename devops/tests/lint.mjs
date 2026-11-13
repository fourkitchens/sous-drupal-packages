#!/usr/bin/env node

import { spawnSync } from 'node:child_process';
import { globSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const options = {
  cwd: root,
  exclude: [
    '**/node_modules/**',
    '**/vendor/**',
    'build/**',
    '.local-reference/**',
    '.git/**',
  ],
};
const javascript = globSync(
  [
    '*.{js,cjs,mjs}',
    '{tools,devops,packages,recipe-template}/**/*.{js,cjs,mjs}',
  ],
  options
).sort();
const php = globSync(
  '{tools,devops,packages,recipe-template}/**/*.{php,inc,module,install,theme,profile,engine}',
  options
).sort();

for (const [files, command, args] of [
  [javascript, process.execPath, ['--check']],
  [php, 'php', ['-l']],
]) {
  for (const file of files) {
    const result = spawnSync(command, [...args, file], {
      cwd: root,
      stdio: 'inherit',
    });
    if (result.error || result.status !== 0) {
      console.error(
        `Syntax check failed: ${file}${result.error ? `: ${result.error.message}` : result.signal ? ` (${result.signal})` : ''}`
      );
      process.exit(result.status ?? 1);
    }
  }
}

console.log(
  `Syntax checked ${javascript.length} JavaScript and ${php.length} PHP files.`
);
