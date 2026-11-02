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
$modules = ['devel', 'field_ui', 'stage_file_proxy', 'syslog', 'views_ui'];
$module_map = array_fill_keys($modules, 0);
$active = \Drupal::service('config.storage');
$sync = \Drupal::service('config.storage.sync');
$available = \Drupal::service('extension.list.module')->getList();
$exported_modules = $sync->read('core.extension')['module'];
foreach ($modules as $module) {
  if (!isset($available[$module]) || \Drupal::moduleHandler()->moduleExists($module) !== ($environment !== 'production')) {
    throw new \RuntimeException("$module has the wrong availability or enabled state in $environment.");
  }
  if (isset($exported_modules[$module])) {
    throw new \RuntimeException("$module leaked into production's exported core.extension.");
  }
}
foreach (['local', 'staging'] as $id) {
  $name = "config_split.config_split.$id";
  foreach ([$active, $sync] as $storage) {
    $definition = $storage->read($name);
    if ($definition['status'] !== FALSE || $definition['module'] !== $module_map) {
      throw new \RuntimeException("$name must remain inactive with exactly the five development modules at weight zero.");
    }
  }
  if (\Drupal::config($name)->get('status') !== ($id === $environment)) {
    throw new \RuntimeException("$id has the wrong effective status in $environment.");
  }
}
foreach (['devel', 'field_ui', 'stage_file_proxy', 'syslog'] as $module) {
  $name = "$module.settings";
  if ($sync->exists($name) || $active->exists($name) !== ($environment !== 'production')) {
    throw new \RuntimeException("$name has the wrong production or active storage.");
  }
  if ($environment === 'production') {
    continue;
  }
  $defaults = (new \Drupal\Core\Config\FileStorage($available[$module]->getPath() . '/config/install'))->read($name);
  if ($module === 'devel') {
    $defaults['devel_dumper'] = \Drupal::config($name)->get('devel_dumper');
    if (!is_string($defaults['devel_dumper']) || !\Drupal::service('plugin.manager.devel_dumper')->isPluginSupported($defaults['devel_dumper'])) {
      throw new \RuntimeException('Devel has no supported default dumper.');
    }
  }
  if ($module === 'syslog') {
    $defaults['facility'] = defined('LOG_LOCAL0') ? LOG_LOCAL0 : LOG_USER;
  }
  $storages = [$active];
  foreach (['local', 'staging'] as $id) {
    $folder = $active->read("config_split.config_split.$id")['folder'];
    if (realpath($folder) !== dirname(\Drupal::root()) . "/config/$id") {
      throw new \RuntimeException("$id resolves outside the expected project config folder.");
    }
    $storages[] = new \Drupal\Core\Config\FileStorage($folder);
  }
  foreach ($storages as $storage) {
    $data = $storage->read($name);
    foreach ($defaults as $key => $value) {
      if (($data[$key] ?? NULL) !== $value) {
        throw new \RuntimeException("$name is missing usable $key defaults in active or split storage.");
      }
    }
  }
}
if ($environment !== 'production') {
  $dumper = \Drupal::service('plugin.manager.devel_dumper')->createInstance(\Drupal::config('devel.settings')->get('devel_dumper'));
  if (!str_contains((string) $dumper->export('environment-check'), 'environment-check')) {
    throw new \RuntimeException('The configured Devel dumper could not render a value.');
  }
}
echo "$environment imports, exports, module defaults and Devel output verified.\n";
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

# Enable normally once so the first export saves defaults into both splits.
set_environment local
vendor/bin/drush pm:enable devel field_ui stage_file_proxy syslog views_ui --yes
vendor/bin/drush config-split:status-override staging active
vendor/bin/drush config:export --yes
vendor/bin/drush config-split:status-override staging default
SOUS_TEST_SPLIT=local vendor/bin/drush php:eval "$environment_checks"

for environment in production staging production; do
  set_environment "$environment"
  vendor/bin/drush config:import --yes
  vendor/bin/drush config:export --yes
  SOUS_TEST_SPLIT="$environment" vendor/bin/drush php:eval "$environment_checks"
done
