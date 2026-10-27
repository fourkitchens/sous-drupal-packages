This is a disposable consumer, not a second starter project. `tests/consumer.mjs`
adds only the selected Sous recipes and a scoped localhost Composer repository,
then installs Drupal 11.4.11 with SQLite. Every resolve, clean clone and
`--prefer-source` recipe reinstall uses an isolated Composer cache/home. Public
Drupal, Packagist and GitHub dependencies still require network access.

The exact nine native `drupal-recipe-unpack.ignore` names retain selected recipes
in `composer.json`/`composer.lock`; exclusions do not require unselected packages.
The root Dropzone definition and the two patches reproduce consumer-owned
prerequisites from `sous-drupal-project` at
`dfbf8e36ee8c0b55bdd32058f6f27c9c811abf28`. Patch hashes remain in patches.json.
They are fixture files, excluded from every package ZIP. The Embed patch is
retained for parity with that starter and applies only if Embed is installed.

Runtime settings here select native Config Split flags only for these tests.
Actual environment detection, settings, config directories, complete exports,
CI providers and theme builds stay owned by the consuming project. These checks
do not replace the starter's frontend build/lint acceptance gate.
