<?php

/**
 * @file
 * Verify Event authoring and dated embed results in a fresh recipe fixture.
 */

use Drupal\field\Entity\FieldConfig;
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
$other_vocabulary = Vocabulary::create(['vid' => 'listing_other', 'name' => 'Other']);
$other_vocabulary->save();
$other_term = Term::create(['vid' => 'listing_other', 'name' => 'Other category']);
$other_term->save();
$nodes = [];
$rules = [];
try {
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
    $nodes[$title] = Node::create(['type' => 'event', 'uid' => 1, 'title' => $title, 'status' => !str_starts_with($title, 'Unpublished'), 'field_event_type' => $term->id(), 'field_event_date' => $items, 'field_teaser_text' => "$title teaser"]);
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
  $nodes['All-day event'] = Node::create(['type' => 'event', 'title' => 'All-day event', 'status' => 1, 'field_event_type' => $term->id(), 'field_event_date' => [['value' => $start, 'end_value' => $end, 'duration' => (int) (($end - $start) / 60), 'timezone' => 'UTC']]]);
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
  $nodes['Recurring dates'] = Node::create(['type' => 'event', 'title' => 'Recurring dates', 'status' => 1, 'field_event_type' => $term->id(), 'field_event_date' => $items]);
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

  // A recurring event can occur once in each list when it has past and future dates.
  $expected = [
    'embed_upcoming' => ['All-day event', 'Ongoing', 'Recurring dates', 'Upcoming near', 'Mixed dates', 'Upcoming far', 'Many future'],
    'embed_past' => ['Recent past', 'Many past', 'Mixed dates', 'Recurring dates', 'Old past'],
  ];
  foreach ($expected as $display_id => $titles) {
    $view = Views::getView('events');
    $view->execute($display_id);
    $actual = array_map(static fn($row) => $row->_entity->label(), $view->result);
    if ($actual !== $titles) {
      throw new \RuntimeException("$display_id returned incorrect, unpublished or duplicate events: " . json_encode($actual));
    }
    $build = $view->render();
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    foreach ($titles as $title) {
      if (!str_contains($html, $title)) {
        throw new \RuntimeException("$display_id failed to render $title.");
      }
    }
    if (!str_contains($html, 'A keynote about archives') && $display_id === 'embed_upcoming') {
      throw new \RuntimeException('Event teasers did not render.');
    }
  }
  $filtered = [
    'embed_upcoming' => [
      [['news_search' => 'not an event match'], $expected['embed_upcoming']],
      [['event_search' => 'coming ne'], ['Upcoming near']],
      [['event_search' => 'scope'], ['Upcoming far']],
      [['event_type' => $workshop->id()], ['Upcoming far']],
      [['event_type' => $term->id(), 'event_search' => 'scope'], []],
      [['event_type' => $workshop->id(), 'event_search' => 'scope'], ['Upcoming far']],
      [['event_search' => '%'], []],
      [['event_search' => 'Unpublished'], []],
      [['event_type' => 'All', 'event_search' => ''], $expected['embed_upcoming']],
    ],
    'embed_past' => [
      [['event_search' => 'cent pa'], ['Recent past']],
      [['event_search' => 'scope'], ['Old past']],
      [['event_type' => $workshop->id()], ['Recent past']],
      [['event_type' => $term->id(), 'event_search' => 'scope'], ['Old past']],
      [['event_type' => $workshop->id(), 'event_search' => 'scope'], []],
      [['event_type' => 'All', 'event_search' => ''], $expected['embed_past']],
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
    $exposed = $view->display_handler->getPlugin('exposed_form')->renderExposedForm();
    if ($view->rowPlugin->getPluginId() !== 'fields' || $view->field['rendered_entity']->getPluginId() !== 'rendered_entity' || $view->field['rendered_entity']->options['view_mode'] !== 'teaser' || !isset($exposed['event_search'], $exposed['event_type']) || isset($exposed['event_type']['#options'][$other_term->id()])) {
      throw new \RuntimeException("$display_id did not build its rendered teaser field and vocabulary-limited exposed controls.");
    }
  }
  echo "$case Event recipe verified.\n";
}
finally {
  $entities->getStorage('node')->delete($nodes);
  foreach ($rules as $id) {
    SmartDateRule::load($id)?->delete();
  }
  $term->delete();
  $workshop->delete();
  $other_term->delete();
  $other_vocabulary->delete();
}
