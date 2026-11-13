import js from '@eslint/js';
import globals from 'globals';

export default [
  {
    ignores: [
      '**/node_modules/**',
      '**/vendor/**',
      'build/**',
      '.local-reference/**',
      '.git/**',
    ],
  },
  js.configs.recommended,
  {
    files: ['**/*.{js,cjs,mjs}'],
    languageOptions: { globals: globals.node },
  },
  {
    files: ['**/*.cjs'],
    languageOptions: { sourceType: 'commonjs' },
  },
];
