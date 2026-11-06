#!/usr/bin/env bash
# Verify real config imports keep development modules out of production.
# Run from a disposable installed recipe site's root after composition checks.
set -euo pipefail

work_dir=$(mktemp -d /tmp/sous-environments.XXXXXX)
settings_file="$PWD/web/sites/default/settings.php"
cp -p "$settings_file" "$work_dir/settings.php"

# Restore the fixture's settings and permissions even when an import fails.
finish() {
  exit_code=$?
  trap - EXIT
  vendor/bin/drush config-split:status-override staging default >/dev/null
  chmod u+w "$settings_file"
  cp -p "$work_dir/settings.php" "$settings_file"
  rm -rf "$work_dir"
  exit "$exit_code"
}

trap finish EXIT
chmod u+w "$settings_file"
printf "\nrequire '%s/environment.php';\n" "$work_dir" >> "$settings_file"
mkdir -p config/local config/staging

environment_checks=$(cat <<'PHP'
$environment = getenv('SOUS_TEST_SPLIT');
$dev_tools = getenv('SOUS_TEST_DEV_TOOLS') === '1';
$core = ['field_ui', 'syslog', 'views_ui'];
$optional = ['devel', 'masquerade', 'stage_file_proxy'];
$split_modules = ['local' => $dev_tools ? array_merge($core, $optional) : $core, 'staging' => $core];
$enabled = $split_modules[$environment] ?? [];
$active = \Drupal::service('config.storage');
$sync = \Drupal::service('config.storage.sync');
$available = \Drupal::service('extension.list.module')->getList();
$exported_modules = $sync->read('core.extension')['module'];
foreach (array_merge($core, $optional) as $module) {
  if (!isset($available[$module]) || \Drupal::moduleHandler()->moduleExists($module) !== in_array($module, $enabled, TRUE)) {
    throw new \RuntimeException("$module has the wrong availability or enabled state in $environment.");
  }
  if (isset($exported_modules[$module])) {
    throw new \RuntimeException("$module leaked into production's exported core.extension.");
  }
}
$split_storage = [];
foreach ($split_modules as $id => $modules) {
  $name = "config_split.config_split.$id";
  $expected = array_fill_keys($modules, 0);
  ksort($expected);
  foreach ([$active, $sync] as $storage) {
    $definition = $storage->read($name);
    $actual = $definition['module'];
    ksort($actual);
    if ($definition['status'] !== FALSE || $actual !== $expected) {
      throw new \RuntimeException("$name must remain inactive with the expected modules at weight zero.");
    }
  }
  if (\Drupal::config($name)->get('status') !== ($id === $environment)) {
    throw new \RuntimeException("$id has the wrong effective status in $environment.");
  }
  $folder = $definition['folder'];
  if (realpath($folder) !== dirname(\Drupal::root()) . "/config/$id") {
    throw new \RuntimeException("$id resolves outside the expected project config folder.");
  }
  $split_storage[$id] = new \Drupal\Core\Config\FileStorage($folder);
}
foreach (['devel', 'field_ui', 'masquerade', 'stage_file_proxy', 'syslog'] as $module) {
  $name = "$module.settings";
  $defaults = (new \Drupal\Core\Config\FileStorage($available[$module]->getPath() . '/config/install'))->read($name);
  if ($module === 'devel') {
    unset($defaults['devel_dumper']);
  }
  if ($module === 'syslog') {
    $defaults['facility'] = defined('LOG_LOCAL0') ? LOG_LOCAL0 : LOG_USER;
  }
  $storages = [
    'active' => [$active, in_array($module, $enabled, TRUE)],
    'sync' => [$sync, FALSE],
    'local' => [$split_storage['local'], in_array($module, $split_modules['local'], TRUE)],
    'staging' => [$split_storage['staging'], in_array($module, $split_modules['staging'], TRUE)],
  ];
  foreach ($storages as $id => [$storage, $expected]) {
    $data = $storage->read($name);
    if (($data !== FALSE) !== $expected) {
      throw new \RuntimeException("$name has the wrong state in $id storage.");
    }
    if (!$expected) {
      continue;
    }
    foreach ($defaults as $key => $value) {
      if (($data[$key] ?? NULL) !== $value) {
        throw new \RuntimeException("$name is missing usable $key defaults in active or split storage.");
      }
    }
  }
}
if ($dev_tools && $environment === 'local') {
  $manager = \Drupal::service('plugin.manager.devel_dumper');
  $id = \Drupal::config('devel.settings')->get('devel_dumper');
  if (!is_string($id) || !$manager->isPluginSupported($id)) {
    throw new \RuntimeException('Devel has no supported default dumper.');
  }
  $dumper = $manager->createInstance($id);
  if (!str_contains((string) $dumper->export('environment-check'), 'environment-check')) {
    throw new \RuntimeException('The configured Devel dumper could not render a value.');
  }
  $permissions = \Drupal::service('masquerade')->getPermissions();
  $access = \Drupal::service('access_check.masquerade.switch');
  foreach (\Drupal\user\Entity\Role::loadMultiple() as $role) {
    $allowed = $role->id() === 'superuser';
    $account = new \Drupal\Core\Session\UserSession(['uid' => 2, 'roles' => ['authenticated', $role->id()]]);
    if ((!$allowed && ($role->isAdmin() || array_intersect($permissions, $role->getPermissions()))) || $access->access($account)->isAllowed() !== $allowed) {
      throw new \RuntimeException($role->id() . ' has incorrect Masquerade access.');
    }
  }
}
$selection = $dev_tools ? 'on' : 'off';
echo "$environment imports, exports and module behavior verified (Dev Tools $selection).\n";
PHP
)

# Use the same settings overrides as hosts while leaving saved split statuses false.
set_environment() {
  environment=$1
  cat > "$work_dir/environment.php" <<'PHP'
<?php
unset($config['config_split.config_split.local']['status'], $config['config_split.config_split.staging']['status']);
PHP
  if [ "$environment" != production ]; then
    printf "\$config['config_split.config_split.%s']['status'] = TRUE;\n" "$environment" >> "$work_dir/environment.php"
  fi
  vendor/bin/drush cache:rebuild
}

# Enable core tools once to populate both split exports with their defaults.
set_environment local
vendor/bin/drush pm:enable field_ui syslog views_ui --yes
vendor/bin/drush config-split:status-override staging active
vendor/bin/drush config:export --yes
vendor/bin/drush config-split:status-override staging default
# Import each environment before and after the optional recipe is selected.
for dev_tools in 0 1; do
  if [ "$dev_tools" = 1 ]; then
    vendor/bin/drush recipe "$PWD/recipes/sous-dev-tools" --yes
    vendor/bin/drush config:export --yes
  fi
  for environment in local staging production local; do
    set_environment "$environment"
    vendor/bin/drush config:import --yes
    vendor/bin/drush config:export --yes
    SOUS_TEST_SPLIT="$environment" SOUS_TEST_DEV_TOOLS="$dev_tools" \
      vendor/bin/drush php:eval "$environment_checks"
  done
done
