<?php

/**
 * @file
 * Verify Content List recipe composition and anonymous rendering.
 */

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\field\Entity\FieldConfig;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

$case = getenv('COMPOSITION');
$entity_type = match ($case) {
  'paragraphs' => 'paragraph',
  'core-layout-builder' => 'block_content',
  default => NULL,
};
$entities = \Drupal::entityTypeManager();
$storage = \Drupal::service('config.storage');
$bundles = ['content_list', 'content_list_item', 'content_list_card'];
$checks = [
  'browser' => $storage->exists('entity_browser.browser.content_list_browser'),
  'browser View' => $storage->exists('views.view.content_list_browser'),
  'editor browser access' => Role::load('site_editor')->hasPermission('access content_list_browser entity browser pages'),
];
foreach (['paragraphs.paragraphs_type' => 'paragraph', 'block_content.type' => 'block_content'] as $prefix => $type) {
  foreach ($bundles as $bundle) {
    $checks["$type.$bundle"] = $storage->exists("$prefix.$bundle") === ($type === $entity_type);
  }
}
if ($entity_type) {
  $form_storage = $entities->getStorage('entity_form_display');
  $list_form = $form_storage->load("$entity_type.content_list.default");
  $item_form = $form_storage->load("$entity_type.content_list_item.default");
  $children_widget = $entity_type === 'paragraph' ? 'paragraphs' : 'inline_entity_form_complex';
  $checks['list children widget'] = $list_form->getRenderer('field_content')?->getPluginId() === $children_widget;
  $checks['list View widget'] = $list_form->getRenderer('field_view')?->getPluginId() === 'viewsreference_select';
  $checks['item browser widget'] = $item_form->getRenderer('field_related_content')?->getPluginId() === 'entity_browser_entity_reference';
  $enabled_settings = FieldConfig::loadByName($entity_type, 'content_list', 'field_view')->getSetting('enabled_settings');
  $checks['list View settings'] = in_array('limit', $enabled_settings, TRUE) && in_array('sift_terms', $enabled_settings, TRUE);
  $hosts = [[$entity_type, 'tabs_item']];
  if ($entity_type === 'paragraph') {
    foreach (['page', 'frontpage', 'event', 'news_post'] as $bundle) {
      $hosts[] = ['node', $bundle];
    }
    foreach (['anonymous', 'authenticated'] as $role_id) {
      $role = Role::load($role_id);
      foreach ($bundles as $bundle) {
        $checks["view permission.$role_id.$bundle"] = $role->hasPermission("view paragraph content $bundle");
      }
    }
  }
  foreach ($hosts as [$type, $bundle]) {
    $allowed = FieldConfig::loadByName($type, $bundle, 'field_content')->getSetting('handler_settings')['target_bundles'];
    $checks["list placement.$type.$bundle"] = in_array('content_list', $allowed, TRUE)
      && !array_intersect(['content_list_item', 'content_list_card'], $allowed);
  }
}
foreach ($checks as $check => $passed) {
  if (!$passed) {
    throw new \RuntimeException("$case Content List $check failed.");
  }
}
if (!$entity_type) {
  echo "$case Content List browser and component isolation verified.\n";
  return;
}

$node_storage = $entities->getStorage('node');
$component_storage = $entities->getStorage($entity_type);
$renderer = \Drupal::service('renderer');
$switcher = \Drupal::service('account_switcher');
$nodes = [];
$terms = [];
$component_ids = [];
$switcher->switchTo(User::load(1));
try {
  foreach (['A', 'B'] as $label) {
    $terms[$label] = $entities->getStorage('taxonomy_term')->create(['vid' => 'news_categories', 'name' => $label]);
    $terms[$label]->save();
  }
  $news = [
    'Content List alpha post' => ['A'],
    'Content List beta post' => ['B'],
    'Content List both post' => ['A', 'B'],
    'Content List untagged post' => [],
  ];
  $created = \Drupal::time()->getRequestTime() - count($news);
  foreach ($news as $title => $categories) {
    $nodes[$title] = $node_storage->create([
      'type' => 'news_post',
      'title' => $title,
      'moderation_state' => 'published',
      'created' => $created++,
      'field_news_categories' => array_map(static fn($category) => $terms[$category]->id(), $categories),
    ]);
    $nodes[$title]->save();
  }
  $picked = $node_storage->create(['type' => 'page', 'title' => 'Picked page', 'moderation_state' => 'published']);
  $picked->save();
  $nodes['picked'] = $picked;

  $block_values = $entity_type === 'block_content' ? ['reusable' => TRUE] : [];
  $item = $component_storage->create([
    'type' => 'content_list_item',
    'info' => 'Picked content',
    'status' => TRUE,
    'field_related_content' => $picked->id(),
  ] + $block_values);
  $item->save();
  $component_ids['item'] = $item->id();
  $card = $component_storage->create([
    'type' => 'content_list_card',
    'info' => 'Card heading',
    'status' => TRUE,
    'field_heading' => 'Card heading',
  ] + $block_values);
  $card->save();
  $component_ids['card'] = $card->id();
  $list = $component_storage->create([
    'type' => 'content_list',
    'info' => 'Latest',
    'status' => TRUE,
    'field_heading' => 'Latest',
    'field_content' => [['entity' => $item], ['entity' => $card]],
  ] + $block_values);
  $host = $entity_type === 'paragraph' ? $node_storage->create([
    'type' => 'page',
    'title' => 'Content List verifier page',
    'moderation_state' => 'published',
  ]) : NULL;

  $term_values = array_values(array_map(static fn($term) => ['target_id' => $term->id()], $terms));
  $titles = array_keys($news);
  $scenarios = [
    'match any' => [$term_values, 'any', 3, array_slice($titles, 0, 3)],
    'match all' => [$term_values, 'all', 3, [$titles[2]]],
    'no terms' => [[], 'any', 2, array_slice($titles, -2)],
  ];
  foreach ($scenarios as $scenario => [$selection, $match, $limit, $expected]) {
    $list->set('field_view', [
      'target_id' => 'news',
      'display_id' => 'embed_recent',
      'data' => serialize(['limit' => $limit, 'sift_terms' => ['terms' => $selection, 'match' => $match]]),
    ])->save();
    $component_ids['list'] = $list->id();
    if ($host) {
      $host->set('field_content', ['entity' => $list])->save();
      $nodes['host'] = $host;
    }
    $parent = $host ?? $list;
    $parent_type = $parent->getEntityTypeId();
    $parent = $entities->getStorage($parent_type)->loadUnchanged($parent->id());
    $switcher->switchTo(new AnonymousUserSession());
    try {
      if (!$parent->access('view')) {
        throw new \RuntimeException("$case Content List is inaccessible to anonymous visitors.");
      }
      $build = $entities->getViewBuilder($parent_type)->view($parent);
      $html = (string) $renderer->renderInIsolation($build);
    }
    finally {
      $switcher->switchBack();
    }
    foreach (array_merge(['Latest', 'Card heading', 'Picked page'], $expected) as $marker) {
      if (!str_contains($html, $marker)) {
        throw new \RuntimeException("$case Content List $scenario did not render $marker.");
      }
    }
    foreach (array_diff($titles, $expected) as $title) {
      if (str_contains($html, $title)) {
        throw new \RuntimeException("$case Content List $scenario unexpectedly rendered $title.");
      }
    }
    if (strpos($html, 'Card heading') > strpos($html, $expected[0]) || strpos($html, 'Picked page') > strpos($html, $expected[0])) {
      throw new \RuntimeException("$case Content List did not render hand-picked items before the listing.");
    }
    if ($scenario === 'no terms' && !str_contains($html, 'href="/news"')) {
      throw new \RuntimeException("$case Content List did not link to /news.");
    }
  }
  echo "$case Content List mixed sources, term matching and teaser limit verified.\n";
}
finally {
  $node_storage->delete($nodes);
  $component_storage->delete($component_storage->loadMultiple(array_values($component_ids)));
  $entities->getStorage('taxonomy_term')->delete($terms);
  $switcher->switchBack();
}
