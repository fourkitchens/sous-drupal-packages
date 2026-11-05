<?php

/**
 * @file
 * Verify site-wide alert settings, block placement and anonymous delivery.
 */

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Session\UserSession;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\HttpFoundation\Request;

foreach (['sitewide_alert', 'sitewide_alert_block'] as $module) {
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
}
finally {
  $switcher->switchBack();
  $storage->delete($fixtures);
}

print getenv('COMPOSITION') . " alert settings, block placement and anonymous delivery verified.\n";
