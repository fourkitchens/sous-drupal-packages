<?php

/**
 * @file
 * Verify Event authoring, dated embeds and the Editor dashboard integration.
 */

use Drupal\Core\Session\UserSession;
use Drupal\dashboard\Entity\Dashboard;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\smart_date_recur\Entity\SmartDateRule;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;
use Drupal\views\Views;

$case = getenv('COMPOSITION');
$entities = \Drupal::entityTypeManager();
$form = $entities->getStorage('entity_form_display')->load('node.event.default');
$display = $entities->getStorage('entity_view_display')->load('node.event.default');
$date = FieldConfig::loadByName('node', 'event', 'field_event_date');
$checks = [
  'recurring dates' => $date->getType() === 'smartdate' && $date->getFieldStorageDefinition()->getCardinality() === -1 && $date->getThirdPartySetting('smart_date_recur', 'allow_recurring'),
  'all-day widget' => $form->getRenderer('field_event_date')->getPluginId() === 'smartdate_inline' && $form->getRenderer('field_event_date')->getSetting('allday'),
  'event type widget' => $form->getRenderer('field_event_type')->getPluginId() === 'tagify_select_widget',
  'teaser widgets' => $form->getRenderer('field_teaser_image')->getPluginId() === 'media_library_widget' && $form->getRenderer('field_teaser_text')->getPluginId() === 'string_textarea',
  'paragraph field' => (bool) FieldConfig::loadByName('node', 'event', 'field_content') === ($case === 'paragraphs'),
  'layout builder' => (bool) $display->getThirdPartySetting('layout_builder', 'enabled', FALSE) === ($case === 'core-layout-builder'),
  'no body' => FieldConfig::loadByName('node', 'event', 'body') === NULL,
];
if ($case === 'paragraphs') {
  $checks['paragraph widget'] = $form->getRenderer('field_content')->getPluginId() === 'layout_paragraphs' && $display->getRenderer('field_content')->getPluginId() === 'layout_paragraphs';
}
foreach ($checks as $name => $passed) {
  if (!$passed) {
    throw new \RuntimeException("$case event $name failed.");
  }
}

$term = Term::create(['vid' => 'event_types', 'name' => 'Conference']);
$term->save();
$workshop = Term::create(['vid' => 'event_types', 'name' => 'Workshop']);
$workshop->save();
$lecture = Term::create(['vid' => 'event_types', 'name' => 'Lecture']);
$lecture->save();
$other_vocabulary = Vocabulary::create(['vid' => 'listing_other', 'name' => 'Other']);
$other_vocabulary->save();
$other_term = Term::create(['vid' => 'listing_other', 'name' => 'Other category']);
$other_term->save();
$nodes = [];
$rules = [];
$listing_tags = NULL;
$account_switcher = \Drupal::service('account_switcher');
$account_switcher->switchTo(User::load(1));
try {
  // Index a second term without changing the single-value Event Type field.
  $tags_storage = FieldStorageConfig::create([
    'entity_type' => 'node',
    'field_name' => 'field_listing_test_tag',
    'type' => 'entity_reference',
    'settings' => ['target_type' => 'taxonomy_term'],
  ]);
  $tags_storage->save();
  $listing_tags = FieldConfig::create(['field_storage' => $tags_storage, 'bundle' => 'event']);
  $listing_tags->save();
  $now = time();
  $samples = [
    'Old past' => [[-100000, -90000]],
    'Recent past' => [[-300, -100]],
    'Ongoing' => [[-60, 3600]],
    'Upcoming near' => [[4000, 4100]],
    'Upcoming far' => [[8000, 9000]],
    'Many future' => [[10000, 10100], [11000, 11100], [12000, 12100]],
    'Mixed dates' => [[-2000, -1800], [5000, 6000], [7000, 8000]],
    'Many past' => [[-500000, -499000], [-1000, -900]],
    'Unpublished future' => [[60, 120]],
    'Unpublished past' => [[-60, -30]],
  ];
  foreach ($samples as $title => $dates) {
    $items = array_map(static fn($range) => ['value' => $now + $range[0], 'end_value' => $now + $range[1], 'duration' => (int) (($range[1] - $range[0]) / 60), 'timezone' => 'UTC'], $dates);
    $nodes[$title] = Node::create(['type' => 'event', 'uid' => 1, 'title' => $title, 'moderation_state' => str_starts_with($title, 'Unpublished') ? 'draft' : 'published', 'field_event_type' => $term->id(), 'field_event_date' => $items, 'field_teaser_text' => "$title teaser"]);
    $nodes[$title]->save();
  }
  foreach (['Upcoming far', 'Recent past'] as $title) {
    $nodes[$title]->set('field_event_type', $workshop->id())->save();
  }
  $nodes['Upcoming near']->set('field_teaser_text', 'A keynote about archives')->save();
  $nodes['Upcoming far']->set('field_teaser_text', 'Discover telescopes with local astronomers')->save();
  $nodes['Old past']->set('field_teaser_text', 'Past telescope discoveries')->save();
  $violations = $nodes['Upcoming near']->validate();
  if (count($violations)) {
    throw new \RuntimeException('A simple Event is invalid: ' . (string) $violations);
  }
  $start = strtotime('yesterday UTC');
  $end = strtotime('tomorrow UTC') + 86340;
  $nodes['All-day event'] = Node::create(['type' => 'event', 'title' => 'All-day event', 'moderation_state' => 'published', 'field_event_type' => $term->id(), 'field_event_date' => [['value' => $start, 'end_value' => $end, 'duration' => (int) (($end - $start) / 60), 'timezone' => 'UTC']]]);
  $nodes['All-day event']->save();

  // Generate real rule instances so repeated dates exercise the same Views join.
  $rule = SmartDateRule::create(['entity_type' => 'node', 'bundle' => 'event', 'field_name' => 'field_event_date', 'freq' => 'DAILY', 'limit' => 'COUNT=3', 'start' => $now - 86400, 'end' => $now - 84600]);
  $rule->setTimezone('UTC');
  $instances = $rule->getRuleInstances();
  $rule->set('instances', ['data' => $instances]);
  $rule->save();
  $rules[] = $rule->id();
  $items = [];
  foreach ($instances as $index => $instance) {
    $items[] = $instance + ['duration' => 30, 'timezone' => 'UTC', 'rrule' => $rule->id(), 'rrule_index' => $index];
  }
  $nodes['Recurring dates'] = Node::create(['type' => 'event', 'title' => 'Recurring dates', 'moderation_state' => 'published', 'field_event_type' => $term->id(), 'field_event_date' => $items]);
  $nodes['Recurring dates']->save();
  if (count($instances) !== 3) {
    throw new \RuntimeException('The Event recurrence did not generate three instances.');
  }

  $switcher = \Drupal::service('account_switcher');
  $switcher->switchTo(User::load(1));
  try {
    $event_form = \Drupal::service('entity.form_builder')->getForm($nodes['Recurring dates']);
    $widget = $event_form['field_event_date']['widget'][0];
    if (!isset($widget['repeat'], $widget['repeat-end']) || (int) $widget['duration']['#attributes']['data-allday'] !== 1 || !isset($event_form['field_event_type']['widget'])) {
      throw new \RuntimeException('The recurring, all-day Event editing form did not build.');
    }
  }
  finally {
    $switcher->switchBack();
  }
  $path = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $nodes['Upcoming near']->id());
  $date_path = \Drupal::service('date.formatter')->format($now + 4000, 'custom', 'm-Y', 'UTC');
  if ($path !== "/event/$date_path/conference/upcoming-near") {
    throw new \RuntimeException("The Event alias is incorrect: $path");
  }

  $build = $entities->getViewBuilder('node')->view($nodes['Upcoming near']);
  $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
  if (!str_contains($html, 'Conference') || !str_contains($html, 'datetime=')) {
    throw new \RuntimeException('The full Event page did not render its date and type.');
  }

  $dashboard_components = [];
  foreach (Dashboard::loadMultiple() as $dashboard) {
    foreach ($dashboard->getSections() as $delta => $section) {
      foreach ($section->getComponents() as $component) {
        if ($component->getUuid() === '8d154da9-bc87-4a1b-ac37-503078f598ec' || $component->getPluginId() === 'views_block:dashboard_events-block_upcoming') {
          $dashboard_components[] = [$dashboard->id(), $delta, $component];
        }
      }
    }
  }
  if (count($dashboard_components) !== 1) {
    throw new \RuntimeException('Dashboards must contain exactly one Events dashboard component.');
  }
  [$dashboard_id, $delta, $component] = $dashboard_components[0];
  $dashboard = Dashboard::load('editor');
  $components = array_values($dashboard->getSection(3)->getComponentsByRegion('second'));
  $uuids = array_map(static fn($item) => $item->getUuid(), $components);
  if ($dashboard_id !== 'editor' || $delta !== 3 || $component->getRegion() !== 'second' || $component->getUuid() !== '8d154da9-bc87-4a1b-ac37-503078f598ec' || $component->getPluginId() !== 'views_block:dashboard_events-block_upcoming' || $components[0]->getPluginId() !== 'views_block:dashboard_accessibility-block_alerts' || array_search($component->getUuid(), $uuids, TRUE) < 1) {
    throw new \RuntimeException('Events dashboard component must follow Accessibility alerts in Editor section 3 second.');
  }
  foreach ([['uid' => 104, 'roles' => ['authenticated', 'site_editor']], ['uid' => 0, 'roles' => ['anonymous']]] as $values) {
    $account = new UserSession($values);
    $account_switcher->switchTo($account);
    try {
      $block = $component->getPlugin();
      $allowed = $account->isAuthenticated();
      if ($block->access($account) !== $allowed) {
        throw new \RuntimeException('Events dashboard block must allow Site Editors and deny anonymous users.');
      }
      $build = $block->build();
      $output = (string) \Drupal::service('renderer')->renderInIsolation($build);
      if (!$allowed) {
        if (trim($output) !== '') {
          throw new \RuntimeException('Anonymous users must not see the Events dashboard block.');
        }
        continue;
      }
      $view = $block->getViewExecutable();
      $titles = array_map(static fn($row) => $row->_entity->label(), $view->result);
      if ($titles !== ['All-day event', 'Ongoing', 'Recurring dates', 'Unpublished future', 'Upcoming near', 'Mixed dates', 'Upcoming far', 'Many future'] || !str_contains($output, 'Unpublished future') || $view->getPager()->getPluginId() !== 'some' || $view->getItemsPerPage() !== 10 || $view->display_handler->validate()) {
        throw new \RuntimeException('Events dashboard must list each not-ended event once, including unpublished events, soonest first: ' . json_encode($titles));
      }
      foreach ($view->result as $row) {
        $dates = array_filter($row->_entity->field_event_date->getValue(), static fn($range) => $range['end_value'] >= $now);
        $next = (int) min(array_column($dates, 'value'));
        $formatted = \Drupal::service('date.formatter')->format($next, 'short');
        if ((int) $view->field['field_event_date_value']->getValue($row) !== $next || (string) $view->style_plugin->getField($row->index, 'field_event_date_value') !== $formatted) {
          throw new \RuntimeException('Events dashboard Next date must render only the minimum upcoming or ongoing occurrence.');
        }
      }
    }
    finally {
      $account_switcher->switchBack();
    }
  }

  // A recurring event can occur once in each list when it has past and future dates.
  $expected = [
    'block_upcoming' => ['All-day event', 'Ongoing', 'Recurring dates', 'Upcoming near', 'Mixed dates', 'Upcoming far', 'Many future'],
    'block_past' => ['Recent past', 'Many past', 'Mixed dates', 'Recurring dates', 'Old past'],
  ];
  $listings = [
    'embed_upcoming' => ['block_upcoming', 'Upcoming near'],
    'embed_past' => ['block_past', 'Old past'],
  ];
  $renderer = \Drupal::service('renderer');
  foreach ($listings as $embed_id => [$block_id]) {
    $view = Views::getView('events');
    $build = $view->buildRenderable($embed_id, [], FALSE);
    $html = (string) $renderer->renderInIsolation($build);
    $titles = array_slice($expected[$block_id], 0, 6);
    $actual = array_map(static fn($row) => $row->_entity->label(), $view->result);
    if ($actual !== $titles || $view->display_handler->usesExposed() || $view->getPager()->getPluginId() !== 'some' || $view->getItemsPerPage() !== 6) {
      throw new \RuntimeException("$embed_id did not return the expected filter-free events: " . json_encode($actual));
    }
    foreach ($titles as $title) {
      if (!str_contains($html, $title)) {
        throw new \RuntimeException("$embed_id failed to render $title.");
      }
    }
    if (!str_contains($html, 'A keynote about archives') && $embed_id === 'embed_upcoming') {
      throw new \RuntimeException('Event teasers did not render.');
    }

    $view = Views::getView('events');
    $view->setItemsPerPage(2);
    $build = $view->buildRenderable($embed_id, [], FALSE);
    $html = (string) $renderer->renderInIsolation($build);
    if (count($view->result) !== 2 || $view->display_handler->isMoreEnabled()) {
      throw new \RuntimeException("$embed_id did not honor the two-item limit or still has a More link.");
    }
  }
  $filtered = [
    'block_upcoming' => [
      [['news_search' => 'not an event match'], $expected['block_upcoming']],
      [['event_search' => 'coming ne'], ['Upcoming near']],
      [['event_search' => 'scope'], ['Upcoming far']],
      [['event_type' => $workshop->id()], ['Upcoming far']],
      [['event_type' => $term->id(), 'event_search' => 'scope'], []],
      [['event_type' => $workshop->id(), 'event_search' => 'scope'], ['Upcoming far']],
      [['event_search' => '%'], []],
      [['event_search' => 'Unpublished'], []],
      [['event_type' => 'All', 'event_search' => ''], $expected['block_upcoming']],
    ],
    'block_past' => [
      [['event_search' => 'cent pa'], ['Recent past']],
      [['event_search' => 'scope'], ['Old past']],
      [['event_type' => $workshop->id()], ['Recent past']],
      [['event_type' => $term->id(), 'event_search' => 'scope'], ['Old past']],
      [['event_type' => $workshop->id(), 'event_search' => 'scope'], []],
      [['event_type' => 'All', 'event_search' => ''], $expected['block_past']],
    ],
  ];
  foreach ($filtered as $display_id => $samples) {
    foreach ($samples as [$input, $titles]) {
      $view = Views::getView('events');
      $view->setExposedInput($input);
      $view->execute($display_id);
      $actual = array_map(static fn($row) => $row->_entity->label(), $view->result);
      if ($actual !== $titles) {
        throw new \RuntimeException("$display_id exposed filter failed for " . json_encode($input) . ': ' . json_encode($actual));
      }
    }
    $view = Views::getView('events');
    $view->setDisplay($display_id);
    $view->initHandlers();
    $view->initStyle();
    if ($view->getPager()->getPluginId() !== 'full' || $view->getItemsPerPage() !== 12) {
      throw new \RuntimeException("$display_id did not configure a full twelve-item pager.");
    }
    $exposed = $view->display_handler->getPlugin('exposed_form')->renderExposedForm();
    if ($view->rowPlugin->getPluginId() !== 'fields' || $view->field['rendered_entity']->getPluginId() !== 'rendered_entity' || $view->field['rendered_entity']->options['view_mode'] !== 'teaser' || !isset($exposed['event_search'], $exposed['event_type']) || isset($exposed['event_type']['#options'][$other_term->id()])) {
      throw new \RuntimeException("$display_id did not build its rendered teaser field and vocabulary-limited exposed controls.");
    }
  }
  $nodes['Mixed dates']->set('field_listing_test_tag', $workshop->id())->save();

  foreach ($listings as $embed_id => [$block_id, $unmatched]) {
    $nodes[$unmatched]->set('field_event_type', $lecture->id())->save();
    $all = $expected[$block_id];
    $arguments = [
      $term->id() . '+' . $workshop->id() => array_values(array_diff($all, [$unmatched])),
      $term->id() . ',' . $workshop->id() => ['Mixed dates'],
      'all' => $all,
      $other_term->id() => [],
    ];
    foreach ([$embed_id, $block_id] as $display_id) {
      foreach ($arguments as $argument => $titles) {
        $view = Views::getView('events');
        $view->setArguments([(string) $argument]);
        $view->execute($display_id);
        $actual = array_map(static fn($row) => $row->_entity->label(), $view->result);
        $expected_titles = $display_id === $embed_id ? array_slice($titles, 0, 6) : $titles;
        if ($actual !== $expected_titles || !empty($view->build_info['fail']) !== ((string) $argument === (string) $other_term->id())) {
          throw new \RuntimeException("$display_id term argument $argument returned unexpected events: " . json_encode($actual));
        }
      }
    }
  }
  echo "$case Event recipe and Editor dashboard block verified.\n";
}
finally {
  $account_switcher->switchBack();
  $entities->getStorage('node')->delete($nodes);
  $listing_tags?->delete();
  foreach ($rules as $id) {
    SmartDateRule::load($id)?->delete();
  }
  $term->delete();
  $workshop->delete();
  $lecture->delete();
  $other_term->delete();
  $other_vocabulary->delete();
}
