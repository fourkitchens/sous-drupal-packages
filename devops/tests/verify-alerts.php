<?php

/**
 * @file
 * Verify site-wide alerts, public delivery and live dashboard blocks.
 */

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Session\UserSession;
use Drupal\dashboard\Entity\Dashboard;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\HttpFoundation\Request;

foreach (['sitewide_alert', 'sitewide_alert_block', 'menu_link_content'] as $module) {
  if (!\Drupal::moduleHandler()->moduleExists($module)) {
    throw new \RuntimeException("Alert module $module is not enabled.");
  }
}

$settings = \Drupal::config('sitewide_alert.settings');
if ($settings->get('display_order') !== 'descending' || $settings->get('automatic_refresh') !== FALSE || $settings->get('server_side_render') !== FALSE) {
  throw new \RuntimeException('Alerts must display newest first with automatic refresh and server-side rendering disabled.');
}

$entities = \Drupal::entityTypeManager();
$blocks = $entities->getStorage('block');
$block = $blocks->load('sous_sitewide_alert');
$theme = \Drupal::config('system.theme')->get('default');
if (!$block || !$block->status() || $block->getPluginId() !== 'sitewide_alert_block'
    || $block->getTheme() !== $theme || $block->getRegion() !== 'content_top'
    || $block->get('settings')['label_display'] !== '0') {
  throw new \RuntimeException('The default theme must have an enabled alert block in Content Top with its label hidden.');
}
foreach ($blocks->loadByProperties(['theme' => $theme, 'region' => 'content_top']) as $other) {
  if ($other->id() !== $block->id() && $other->getWeight() <= $block->getWeight()) {
    throw new \RuntimeException('The alert block must have the lowest weight in Content Top.');
  }
}

$storage = $entities->getStorage('sitewide_alert');

$editor = new UserSession(['uid' => 1234, 'roles' => ['authenticated', 'site_admin']]);
$alert_access = $entities->getAccessControlHandler('sitewide_alert');
$alert = $storage->create(['status' => FALSE, 'user_id' => 1]);
if (!$alert_access->createAccess(NULL, $editor) || !$alert->access('update', $editor)
    || !$alert->access('delete', $editor) || !$alert->access('view', $editor)
    || $editor->hasPermission('administer sitewide alert')) {
  throw new \RuntimeException('Site Admin must be able to manage alerts without access to global alert settings.');
}
$menu_links = [
  '983b3206-1451-4bf9-9a43-ae71ca31a276' => ['Alert', 'dashboard-create', '', 'entity.sitewide_alert.add_form', 'admin/content/sitewide_alert/add'],
  '903fb065-214e-4dca-8645-8f225ab19ac2' => ['Alert', 'content', 'navigation.create', 'entity.sitewide_alert.add_form', 'admin/content/sitewide_alert/add'],
  '0b42bddc-e89a-44e8-9e8d-7727628e19f0' => ['Alerts', 'content', '', 'entity.sitewide_alert.collection', 'admin/content/sitewide_alert'],
];
$menu_manager = \Drupal::service('plugin.manager.menu.link');
foreach ($menu_links as $uuid => [$title, $menu, $parent, $route, $path]) {
  $link = \Drupal::service('entity.repository')->loadEntityByUuid('menu_link_content', $uuid);
  if (!$link || $link->getTitle() !== $title || $link->getMenuName() !== $menu || $link->getParentId() !== $parent || (int) $link->getWeight() !== 10 || !$link->isEnabled() || $link->getUrlObject()->getRouteName() !== $route || $link->getUrlObject()->getInternalPath() !== $path || count($menu_manager->loadLinksByRoute($route, $link->getUrlObject()->getRouteParameters(), $menu)) !== 1) {
    throw new \RuntimeException("Alert menu link $uuid has incorrect metadata or is duplicated.");
  }
  if ($title === 'Alerts' && $link->getUrlObject()->getOption('icon') !== ['pack_id' => 'navigation', 'icon_id' => 'announcements-feed-announcement']) {
    throw new \RuntimeException('The top-level Alerts link must use the Navigation announcement icon.');
  }
}
$switcher = \Drupal::service('account_switcher');
$menu_tree = \Drupal::menuTree();
foreach (['site_admin' => $editor, 'superuser' => new UserSession(['uid' => 1235, 'roles' => ['authenticated', 'superuser']]), 'authenticated' => new UserSession(['uid' => 1236, 'roles' => ['authenticated']])] as $role => $account) {
  $switcher->switchTo($account);
  try {
    foreach (['dashboard-create', 'content'] as $menu) {
      $tree = $menu_tree->load($menu, (new MenuTreeParameters())->onlyEnabledLinks());
      $tree = $menu_tree->transform($tree, [['callable' => 'menu.default_tree_manipulators:checkAccess'], ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort']]);
      $items = [];
      foreach ($tree as $item) {
        $items[$item->link->getPluginId()] = $item;
      }
      $create_tree = $menu === 'dashboard-create' ? $tree : ($items['navigation.create']->subtree ?? []);
      $create_items = [];
      $create_paths = [];
      foreach ($create_tree as $item) {
        $create_items[$item->link->getPluginId()] = $item;
        if ($item->access->isAllowed()) {
          $create_paths[] = $item->link->getUrlObject()->getInternalPath();
        }
      }
      foreach ($menu_links as $uuid => [$title, $link_menu, $parent]) {
        if ($link_menu !== $menu) {
          continue;
        }
        $allowed = $role !== 'authenticated' && ($parent === '' || $role === 'superuser' || $items['navigation.create']->access->isAllowed());
        $link_items = $parent === '' ? $items : $create_items;
        $accessible = isset($link_items["menu_link_content:$uuid"]) && $link_items["menu_link_content:$uuid"]->access->isAllowed();
        if ($accessible !== $allowed) {
          throw new \RuntimeException("The $role account has incorrect access to Alert menu link $uuid.");
        }
      }
      if ($role === 'site_admin' && ($menu === 'dashboard-create' || getenv('COMPOSITION') !== 'admin')) {
        $position = array_search('admin/content/sitewide_alert/add', $create_paths, TRUE);
        $nodes = array_keys(array_filter($create_paths, static fn($path) => str_starts_with($path, 'node/add/')));
        if ($position === FALSE || ($nodes && max($nodes) >= $position) || ($menu === 'dashboard-create' && $position >= array_search('media/add/file', $create_paths, TRUE))) {
          throw new \RuntimeException("Site Admin's $menu Alert link must follow content types and precede media links.");
        }
      }
    }
  }
  finally {
    $switcher->switchBack();
  }
}
$dashboard_components = [];
foreach (['site_admin' => '4110daa8-d05b-4008-9ad5-e6e9ebbcd960', 'editor' => '593fc280-4e25-4884-bd75-95c9c5299a2a'] as $id => $uuid) {
  $dashboard = Dashboard::load($id);
  $matches = [];
  foreach ($dashboard->getSections() as $delta => $section) {
    foreach ($section->getComponents() as $component) {
      if ($component->getUuid() === $uuid || $component->getPluginId() === 'sitewide_alert_block') {
        $matches[] = [$delta, $component];
      }
    }
  }
  if (count($matches) !== 1) {
    throw new \RuntimeException("Dashboard $id must contain exactly one live Site alerts component.");
  }
  [$delta, $component] = $matches[0];
  $components = array_values($dashboard->getSection(0)->getComponentsByRegion('content'));
  if ($delta !== 0 || $component->getRegion() !== 'content' || $component->getUuid() !== $uuid || $component->getPluginId() !== 'sitewide_alert_block' || $component->getPlugin()->getConfiguration()['label_display'] !== '0' || $components[0]->getUuid() !== $uuid) {
    throw new \RuntimeException("Live Site alerts must be first in Dashboard $id section 0 content with a hidden label.");
  }
  if ($id === 'site_admin' && $components[1]->getPluginId() !== 'dashboard_site_status') {
    throw new \RuntimeException('Site status must follow live Site alerts in Site admin.');
  }
  $dashboard_components[$id] = $component;
}
$tomorrow = new \DateTimeImmutable('+1 day', new \DateTimeZone('UTC'));
$fixtures = [];
$switcher->switchTo(new AnonymousUserSession());
try {
  foreach ([
    'published' => [],
    'unpublished' => ['status' => FALSE],
    'scheduled' => [
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $tomorrow->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $tomorrow->modify('+1 day')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ],
  ] as $state => $values) {
    $alert = $storage->create($values + [
      'status' => TRUE,
      'user_id' => 1,
      'changed' => \Drupal::time()->getRequestTime() + count($fixtures),
      'name' => "Sous CI $state alert",
      'style' => 'primary',
      'message' => ['value' => "Sous CI $state alert.", 'format' => 'plain_text'],
    ]);
    $alert->save();
    $fixtures[$state] = $alert;
  }

  $response = \Drupal::service('http_kernel')->handle(Request::create('/sitewide_alert/load'));
  if (!$response->isSuccessful()) {
    throw new \RuntimeException('Anonymous alert request returned HTTP ' . $response->getStatusCode() . '.');
  }
  $payload = json_decode($response->getContent(), TRUE, flags: JSON_THROW_ON_ERROR);
  $alerts = array_column($payload['sitewideAlerts'], NULL, 'uuid');
  if (!str_contains($alerts[$fixtures['published']->uuid()]['renderedAlert'] ?? '', 'Sous CI published alert.')
      || isset($alerts[$fixtures['unpublished']->uuid()]) || isset($alerts[$fixtures['scheduled']->uuid()])) {
    throw new \RuntimeException('Anonymous alert response must contain the published message and omit unpublished and future alerts.');
  }
  $switcher->switchTo($editor);
  try {
    foreach ($dashboard_components as $id => $component) {
      $plugin = $component->getPlugin();
      if (!$plugin->access($editor)) {
        throw new \RuntimeException("Site Admins must be able to build the live Site alerts block for $id.");
      }
      $build = $plugin->build();
      if (($build['#attributes']['data-sitewide-alert'] ?? NULL) !== TRUE || !in_array('sitewide_alert/init', $build['#attached']['library'] ?? [], TRUE)) {
        throw new \RuntimeException("Dashboard $id must use Sitewide Alert client-side rendering.");
      }
    }
  }
  finally {
    $switcher->switchBack();
  }
}
finally {
  $switcher->switchBack();
  $storage->delete($fixtures);
}

print getenv('COMPOSITION') . " alert settings, menu links, block placement, anonymous delivery and live dashboard blocks verified.\n";
