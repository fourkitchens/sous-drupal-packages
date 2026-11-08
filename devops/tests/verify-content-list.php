<?php

/**
 * @file
 * Verify Content List recipe composition and anonymous rendering.
 */

use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\field\Entity\FieldConfig;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\viewsreference_sift\Selection\Selection;

$case = getenv('COMPOSITION');
$entity_type = match ($case) {
  'paragraphs' => 'paragraph',
  'core-layout-builder' => 'block_content',
  default => NULL,
};
$entities = \Drupal::entityTypeManager();
$storage = \Drupal::service('config.storage');
$bundles = ['content_list', 'content_list_item'];
$checks = [
  'browser' => $storage->exists('entity_browser.browser.content_list_browser'),
  'browser View' => $storage->exists('views.view.content_list_browser'),
  'editor browser access' => Role::load('site_editor')->hasPermission('access content_list_browser entity browser pages'),
];
foreach (['paragraphs.paragraphs_type' => 'paragraph', 'block_content.type' => 'block_content'] as $prefix => $type) {
  $checks["$type uses a single item bundle"] = !$storage->exists("$prefix.content_list_teaser");
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
  $list_children = FieldConfig::loadByName($entity_type, 'content_list', 'field_content')->getSetting('handler_settings')['target_bundles'];
  $checks['list teaser bundle'] = $list_children === ['content_list_item' => 'content_list_item'];
  $listing_widget = $list_form->getRenderer('field_view');
  $checks['list View widget'] = $listing_widget?->getPluginId() === 'viewsreference_select';
  $checks['item browser widget'] = $item_form->getRenderer('field_related_content')?->getPluginId() === 'entity_browser_entity_reference';
  $checks['item type widget'] = $item_form->getRenderer('field_item_type')?->getPluginId() === 'options_select';
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
      && !in_array('content_list_item', $allowed, TRUE);
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
    'field_heading' => 'Inactive existing item heading',
  ] + $block_values);
  $item->save();
  $component_ids['item'] = $item->id();
  $teaser = $component_storage->create([
    'type' => 'content_list_item',
    'info' => 'Custom teaser heading',
    'status' => TRUE,
    'field_item_type' => 'custom_content',
    'field_heading' => 'Custom teaser heading',
    'field_related_content' => $nodes['Content List untagged post']->id(),
  ] + $block_values);
  $teaser->save();
  $component_ids['teaser'] = $teaser->id();
  $item_widgets = ['#parents' => ['field_content', 'subform']];
  $item_form->buildForm($item, $item_widgets, new FormState());
  $selector = 'select[name="field_content[subform][field_item_type]"]';
  foreach (['field_related_content' => 'existing_content', 'field_heading' => 'custom_content', 'field_image' => 'custom_content', 'field_link' => 'custom_content', 'field_text' => 'custom_content'] as $field => $source) {
    if (($item_widgets[$field]['#states']['visible'][$selector]['value'] ?? NULL) !== $source) {
      throw new \RuntimeException("$case Content List Item has the wrong visibility rule for $field.");
    }
  }
  $list = $component_storage->create([
    'type' => 'content_list',
    'info' => 'Latest',
    'status' => TRUE,
    'field_heading' => 'Latest',
    'field_content' => [['entity' => $item], ['entity' => $teaser]],
  ] + ($entity_type === 'block_content' ? ['reusable' => FALSE] : []));
  $host = $node_storage->create([
    'type' => 'page',
    'title' => 'Content List verifier page',
    'moderation_state' => 'published',
  ]);

  $settings = \Drupal::service('plugin.manager.viewsreference.setting');
  if ((string) $settings->getDefinition('sift_terms')['label'] !== 'Sift filters') {
    throw new \RuntimeException("$case Content List did not expose the Sift filters field setting.");
  }
  foreach ([
    ['events', 'embed_upcoming', 'Event Type', 'event_types'],
    ['events', 'embed_past', 'Event Type', 'event_types'],
    ['news', 'embed_recent', 'News Categories', 'news_categories'],
    ['profiles', 'embed_profiles', NULL, NULL],
  ] as [$view, $display, $label, $vocabulary]) {
    $list->set('field_view', ['target_id' => $view, 'display_id' => $display]);
    $form = [];
    $element = $listing_widget->formElement($list->get('field_view'), 0, ['#required' => FALSE], $form, new FormState());
    $options = $element['options'];
    $sift = $options['sift_terms'];
    if ($label === NULL) {
      if (($sift['#access'] ?? TRUE) !== FALSE || !isset($options['limit'])) {
        throw new \RuntimeException("$case Content List Profiles did not offer the item limit without a Sift control.");
      }
      continue;
    }
    $filters = $sift['filters'] ?? [];
    $filter = $filters['tid'] ?? [];
    if (($sift['#access'] ?? TRUE) === FALSE || (string) ($sift['#title'] ?? '') !== 'Filter this list'
      || array_keys($filters) !== ['tid'] || (string) ($filter['#title'] ?? '') !== $label
      || ($filter['ids']['#selection_settings']['target_bundles'] ?? []) !== [$vocabulary]
      || array_keys($filter['match']['#options'] ?? []) !== ['any', 'all']) {
      throw new \RuntimeException("$case Content List $view:$display did not offer the expected Sift control.");
    }
  }

  $term_ids = array_values(array_map(static fn($term) => (int) $term->id(), $terms));
  $titles = array_keys($news);
  $scenarios = [
    'match any' => [$term_ids, 'any', 3, array_slice($titles, 0, 3)],
    'match all' => [$term_ids, 'all', 3, [$titles[2]]],
    'no terms' => [[], 'any', 2, array_slice($titles, -2)],
  ];
  foreach ($scenarios as $scenario => [$selection, $match, $limit, $expected]) {
    $list->set('field_view', [
      'target_id' => 'news',
      'display_id' => 'embed_recent',
      'data' => serialize([
        'limit' => $limit,
        'sift_terms' => [
          'version' => Selection::VERSION,
          'filters' => ['tid' => ['ids' => $selection, 'match' => $match]],
        ],
      ]),
    ])->save();
    $component_ids['list'] = $list->id();
    if ($entity_type === 'paragraph') {
      $host->set('field_content', ['entity' => $list]);
    }
    else {
      $component = new SectionComponent(\Drupal::service('uuid')->generate(), 'content', [
        'id' => 'inline_block:content_list',
        'block_revision_id' => $list->getRevisionId(),
        'label_display' => FALSE,
      ]);
      $host->set(OverridesSectionStorage::FIELD_NAME, ['section' => new Section('layout_onecol', [], [$component])]);
    }
    $host->save();
    $nodes['host'] = $host;
    $parent = $node_storage->loadUnchanged($host->id());
    $switcher->switchTo(new AnonymousUserSession());
    try {
      if (!$parent->access('view')) {
        throw new \RuntimeException("$case Content List is inaccessible to anonymous visitors.");
      }
      $build = $entities->getViewBuilder('node')->view($parent);
      $html = (string) $renderer->renderInIsolation($build);
    }
    finally {
      $switcher->switchBack();
    }
    foreach (array_merge(['Latest', 'Custom teaser heading', 'Picked page'], $expected) as $marker) {
      if (!str_contains($html, $marker)) {
        throw new \RuntimeException("$case Content List $scenario did not render $marker.");
      }
    }
    if (str_contains($html, 'Inactive existing item heading')) {
      throw new \RuntimeException("$case Content List displayed the inactive custom fields of an existing item.");
    }
    foreach (array_diff($titles, $expected) as $title) {
      if (str_contains($html, $title)) {
        throw new \RuntimeException("$case Content List $scenario unexpectedly rendered $title.");
      }
    }
    if (strpos($html, 'Custom teaser heading') > strpos($html, $expected[0]) || strpos($html, 'Picked page') > strpos($html, $expected[0])) {
      throw new \RuntimeException("$case Content List did not render hand-picked teasers before the listing.");
    }
    if (str_contains($html, 'href="/news"')) {
      throw new \RuntimeException("$case Content List still links to the removed News route.");
    }
  }
  echo "$case Content List Sift controls, anonymous mixed sources, Any and All matching and item limit verified.\n";
}
finally {
  $node_storage->delete($nodes);
  $component_storage->delete($component_storage->loadMultiple(array_values($component_ids)));
  $entities->getStorage('taxonomy_term')->delete($terms);
  $switcher->switchBack();
}
