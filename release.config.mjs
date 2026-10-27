export default {
  branches: ['main'],
  repositoryUrl: 'https://github.com/fourkitchens/sous-drupal-packages.git',
  tagFormat: '${version}',
  plugins: [
    [
      '@semantic-release/commit-analyzer',
      {
        preset: 'conventionalcommits',
      },
    ],
    [
      '@semantic-release/release-notes-generator',
      { preset: 'conventionalcommits' },
    ],
    './tools/release.mjs',
    [
      '@semantic-release/github',
      {
        assets: ['build/dist/*.zip', 'build/release-manifest.json'],
        successCommentCondition: false,
        failCommentCondition: false,
        labels: false,
        releasedLabels: false,
        addReleases: false,
      },
    ],
  ],
};
