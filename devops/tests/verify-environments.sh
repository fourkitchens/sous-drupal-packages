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
echo "$environment imports, exports and module states verified.\n";
PHP
)

# Use the same settings overrides as hosts while leaving saved split statuses false.
set_environment() {
  environment=$1
  local_status=FALSE
  staging_status=FALSE
  if [ "$environment" = local ]; then
    local_status=TRUE
  fi
  if [ "$environment" = staging ]; then
    staging_status=TRUE
  fi
  cat > "$work_dir/environment.php" <<PHP
<?php
\$config['config_split.config_split.local']['status'] = $local_status;
\$config['config_split.config_split.staging']['status'] = $staging_status;
PHP
  vendor/bin/drush cache:rebuild
}

set_environment production
vendor/bin/drush config:export --yes
SOUS_TEST_SPLIT=production vendor/bin/drush php:eval "$environment_checks"

for environment in local production staging production; do
  set_environment "$environment"
  if [ "$environment" = local ]; then
    vendor/bin/drush config-split:import local --yes
  else
    vendor/bin/drush config:import --yes
  fi
  vendor/bin/drush config:export --yes
  SOUS_TEST_SPLIT="$environment" vendor/bin/drush php:eval "$environment_checks"
done
