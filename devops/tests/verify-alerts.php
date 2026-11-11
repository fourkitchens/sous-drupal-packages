<?php

/**
 * @file
 * Verify site-wide alerts, public delivery and Editor dashboard integration.
 */

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Session\UserSession;
use Drupal\dashboard\Entity\Dashboard;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\HttpFoundation\Request;

foreach (['sitewide_alert', 'sitewide_alert_block', 'views'] as $module) {
  if (!\Drupal::moduleHandler()->moduleExists($module)) {
    throw new \RuntimeException("Alert module $module is not enabled.");
  }
}

$settings = \Drupal::config('sitewide_alert.settings');
if ($settings->get('display_order') !== 'descending' || $settings->get('automatic_refresh') !== FALSE) {
  throw new \RuntimeException('Alerts must display newest first with automatic refresh disabled.');
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

$editor = new UserSession(['uid' => 1234, 'roles' => ['authenticated', 'site_editor']]);
$alert_access = $entities->getAccessControlHandler('sitewide_alert');
$alert = $storage->create(['status' => FALSE, 'user_id' => 1]);
if (!$alert_access->createAccess(NULL, $editor) || !$alert->access('update', $editor)
    || !$alert->access('delete', $editor) || !$alert->access('view', $editor)
    || $editor->hasPermission('administer sitewide alert')) {
  throw new \RuntimeException('Site Editor must be able to manage alerts without access to global alert settings.');
}
$matches = [];
foreach (Dashboard::loadMultiple() as $dashboard) {
  foreach ($dashboard->getSections() as $delta => $section) {
    foreach ($section->getComponents() as $component) {
      if ($component->getUuid() === '593fc280-4e25-4884-bd75-95c9c5299a2a' || $component->getPluginId() === 'views_block:dashboard_alerts-block_active') {
        $matches[] = [$dashboard, $delta, $component];
      }
    }
  }
}
if (count($matches) !== 1) {
  throw new \RuntimeException('Dashboards must contain exactly one Site alerts component.');
}
[$dashboard, $delta, $component] = $matches[0];
$components = array_values($dashboard->getSection(1)->getComponentsByRegion('second'));
$uuids = array_map(static fn($item) => $item->getUuid(), $components);
if ($dashboard->id() !== 'editor' || $delta !== 1 || $component->getRegion() !== 'second' || $component->getUuid() !== '593fc280-4e25-4884-bd75-95c9c5299a2a' || $component->getPluginId() !== 'views_block:dashboard_alerts-block_active' || $components[0]->getPluginId() !== 'views_block:dashboard_editorial-block_scheduled' || array_search($component->getUuid(), $uuids, TRUE) < 1) {
  throw new \RuntimeException('Site alerts must follow Scheduled changes in Editor section 1 second.');
}
$tomorrow = new \DateTimeImmutable('+1 day', new \DateTimeZone('UTC'));
$fixtures = [];
$switcher = \Drupal::service('account_switcher');
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
  foreach ([$editor, new AnonymousUserSession()] as $account) {
    $switcher->switchTo($account);
    try {
      $plugin = $component->getPlugin();
      $allowed = $account->isAuthenticated();
      if ($plugin->access($account) !== $allowed) {
        throw new \RuntimeException('Site alerts dashboard block must allow Site Editors and deny anonymous users.');
      }
      $build = $plugin->build();
      $output = (string) \Drupal::service('renderer')->renderInIsolation($build);
      if (!$allowed) {
        if (trim($output) !== '') {
          throw new \RuntimeException('Anonymous users must receive no Site alerts dashboard output.');
        }
        continue;
      }
      if (!str_contains($output, $fixtures['published']->label()) || !str_contains($output, $fixtures['scheduled']->label()) || str_contains($output, $fixtures['unpublished']->label()) || strpos($output, $fixtures['scheduled']->label()) > strpos($output, $fixtures['published']->label()) || !str_contains($output, $fixtures['published']->toUrl('edit-form')->toString()) || !str_contains($output, $tomorrow->format('Y-m-d')) || !str_contains($output, $tomorrow->modify('+1 day')->format('Y-m-d'))) {
        throw new \RuntimeException('Site alerts dashboard must show active and future scheduled alerts newest first, with edit links and schedule dates.');
      }
    }
    finally {
      $switcher->switchBack();
    }
  }
}
finally {
  $switcher->switchBack();
  $storage->delete($fixtures);
}

print getenv('COMPOSITION') . " alert settings, block placement, anonymous delivery and Editor dashboard block verified.\n";
