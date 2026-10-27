<?php

declare(strict_types=1);

/** Runtime acceptance for the isolated SQLite consumer created by consumer.mjs. */

use Drupal\Core\Config\DatabaseStorage;
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\Recipe\ConfigConfigurator;
use Drupal\Core\Recipe\RecipePreExistingConfigException;
use Drupal\node\Entity\NodeType;
use Drupal\views\Entity\View;
use Symfony\Component\Yaml\Yaml;

if (getenv('SOUS_DISPOSABLE_TEST') !== '1') {
  throw new RuntimeException('This probe requires an explicitly disposable test installation.');
}
$check = static function (bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
};
$case = getenv('SOUS_INTEGRATION_COMBINATION');
$stage = getenv('SOUS_TEST_STAGE') ?: 'verify';
$storage = new DatabaseStorage(\Drupal::database(), 'config');
$normalize = static function (mixed $value) use (&$normalize): mixed {
  if (is_array($value)) {
    $value = array_map($normalize, $value);
    if (!array_is_list($value)) {
      ksort($value);
    }
  }
  return $value;
};
$snapshot = static function () use ($storage, $normalize): array {
  $result = [];
  foreach (array_merge([''], $storage->getAllCollectionNames()) as $collection) {
    $data = $storage->createCollection($collection);
    $result[$collection] = $normalize($data->readMultiple($data->listAll()));
  }
  ksort($result);
  return $result;
};

if ($stage === 'views-prepare') {
  $before = [];
  foreach (['media', 'media_library'] as $id) {
    $view = View::load($id);
    $view->set('label', 'Project-owned ' . $id)->save();
    $name = 'views.view.' . $id;
    $before[$name] = $storage->read($name);
  }
  \Drupal::state()->set('sous.test.media_views', $before);
}
elseif ($stage === 'views-verify') {
  foreach (\Drupal::state()->get('sous.test.media_views') as $name => $data) {
    $check($storage->read($name) === $data, 'Applying Media overwrote a project-owned management View: ' . $name);
  }
  $recipe = DRUPAL_ROOT . '/recipes/sous-media';
  $definition = Yaml::parseFile($recipe . '/recipe.yml')['config'];
  $strict = $definition['strict'];
  $check(is_array($strict), 'Media must preserve explicit strict validation.');
  $defaults = (new ConfigConfigurator($definition, $recipe, new MemoryStorage()))->getConfigStorage();
  $expected = array_values(array_diff($defaults->listAll(), ['views.view.media', 'views.view.media_library']));
  sort($expected);
  sort($strict);
  $check($strict === $expected, 'Media must protect every owned/imported default except the two native Views.');
  $custom = new MemoryStorage();
  $data = $defaults->read('media.type.image');
  $data['label'] = 'Conflicting project image bundle';
  $custom->write('media.type.image', $data);
  try {
    new ConfigConfigurator($definition, $recipe, $custom);
    throw new RuntimeException('Media accepted a conflicting owned bundle.');
  }
  catch (RecipePreExistingConfigException $error) {
    $check(str_contains($error->getMessage(), 'media.type.image'), 'Unexpected Media strict conflict.');
  }
}
elseif ($stage === 'snapshot') {
  \Drupal::state()->set('sous.test.config_snapshot', $snapshot());
}
elseif ($stage === 'mutate') {
  \Drupal::configFactory()->getEditable('system.site')->set('name', 'Deliberate disposable mutation')->save();
}
elseif ($stage === 'roundtrip') {
  $check($snapshot() === \Drupal::state()->get('sous.test.config_snapshot'), 'Native export/import did not restore every active configuration collection.');
}
elseif ($stage === 'split-prepare') {
  foreach (['local', 'staging'] as $name) {
    \Drupal::configFactory()->getEditable('config_split.config_split.sous_' . $name)->set('module', ['contact' => 0])->save();
  }
}
elseif ($stage === 'split-seed') {
  \Drupal::service('module_installer')->install(['contact']);
  \Drupal::configFactory()->getEditable('contact.form.personal')->set('label', 'Sous ' . getenv('SOUS_TEST_ENVIRONMENT'))->save();
}
elseif ($stage === 'split-verify') {
  $environment = getenv('SOUS_TEST_ENVIRONMENT');
  $enabled = $environment !== 'production';
  $check(\Drupal::moduleHandler()->moduleExists('contact') === $enabled, 'Contact did not follow the selected environment split.');
  if ($enabled) {
    $check(\Drupal::config('contact.form.personal')->get('label') === 'Sous ' . $environment, 'The environment-specific Contact configuration did not import.');
  }
  $base = DRUPAL_ROOT . '/../config/default';
  $extension = Yaml::parseFile($base . '/core.extension.yml');
  $check(!isset($extension['module']['contact']) && !is_file($base . '/contact.form.personal.yml'), 'Contact leaked into the production base export.');
  $check(is_file($base . '/config_split.config_split._splits.yml'), 'The base export lost its split-definition bootstrap.');
  foreach (['local', 'staging'] as $name) {
    $id = 'config_split.config_split.sous_' . $name;
    $check(!is_file($base . '/' . $id . '.yml') && is_file(DRUPAL_ROOT . '/../config/_splits/' . $id . '.yml'), 'Split definitions must live in config/_splits.');
    $check(($storage->read($id)['status'] ?? NULL) === FALSE, 'Runtime selection contaminated a stored split status.');
  }
}
elseif ($stage === 'verify') {
  $modules = \Drupal::moduleHandler();
  $paragraphs = $case === 'paragraphs';
  $layout = $case === 'core-layout-builder';
  foreach (['paragraphs', 'paragraphs_ee', 'paragraphs_collapsible'] as $module) {
    $check($modules->moduleExists($module) === $paragraphs, 'Unexpected optional module: ' . $module);
  }
  // Core Navigation itself enables layout_builder in Drupal 11.4. Test the
  // optional authoring composition, rather than that shared core dependency.
  if ($case !== 'admin-only') {
    foreach (['page', 'frontpage'] as $bundle) {
      $display = \Drupal::service('entity_display.repository')->getViewDisplay('node', $bundle);
      $check((bool) $display->getThirdPartySetting('layout_builder', 'enabled', FALSE) === $layout, 'Unexpected Layout Builder display composition: ' . $bundle);
      $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);
      $check(isset($fields['layout_builder__layout']) === $layout, 'Unexpected per-node Layout Builder override field: ' . $bundle);
    }
  }
  $check($modules->moduleExists('config_split') === (getenv('SOUS_TEST_CONFIG_SPLIT') === '1'), 'Config Split must remain optional.');
  if (!$paragraphs) {
    foreach (['paragraphs.', 'paragraphs_ee.', 'field.storage.paragraph.', 'field.field.paragraph.'] as $prefix) {
      $check(!$storage->listAll($prefix), 'Unselected Paragraphs configuration was installed: ' . $prefix);
    }
    $check(!$storage->exists('field.storage.node.field_content'), 'Unselected Paragraphs host field was installed.');
  }
  foreach (['block_content', 'content', 'glossary', 'files', 'media', 'media_library', 'taxonomy_term', 'who_s_new', 'who_s_online', 'login_history'] as $id) {
    $view = View::load($id);
    $check($view !== NULL && $view->getExecutable()->initDisplay(), 'Missing or unusable Admin-owned native View: ' . $id);
  }
  $check(\Drupal::config('system.theme')->get('admin') === 'gin', 'Gin must be the administrative theme.');
  $check(\Drupal::config('system.theme')->get('default') === 'emulsify', 'Emulsify must be the frontend theme.');
  $root = getenv('SOUS_TEST_REPOSITORY') . '/tests/recipes/';
  $probes = ['sous-admin/verify-navigation.php', 'sous-admin/verify-authorization.php'];
  if ($case === 'admin-only') {
    $check(NodeType::load('page') === NULL && NodeType::load('frontpage') === NULL, 'Admin-only installed starter content types.');
    foreach (['file', 'image', 'video'] as $bundle) {
      $check(!\Drupal::entityTypeManager()->getStorage('media_type')->load($bundle), 'Admin-only installed a Media recipe bundle: ' . $bundle);
    }
  }
  else {
    $probes[] = 'sous-formats/verify-formats.php';
    $probes[] = 'sous-content-types/verify-starter-defaults.php';
    if ($paragraphs) {
      $probes[] = 'sous-paragraph-types/verify-paragraph-types.php';
    }
  }
  if (getenv('SOUS_TEST_CONFIG_SPLIT') === '1' && getenv('SOUS_TEST_SPLIT_CUSTOMIZED') !== '1') {
    $probes[] = 'sous-config-split/verify-defaults.php';
  }
  foreach ($probes as $probe) {
    // Keep imported probe-local variables from changing this runner's state.
    (static function (string $file): void { require $file; })($root . $probe);
  }
}
else {
  throw new InvalidArgumentException('Unknown integration probe stage: ' . $stage);
}
print 'PASS: package consumer ' . $case . ' / ' . $stage . ".\n";
