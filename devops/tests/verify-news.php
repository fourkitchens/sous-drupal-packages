<?php

/**
 * @file
 * Verify News Post authoring, aliases and searchable rendered teaser listings.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\views\Views;

$case = getenv('COMPOSITION');
$entities = \Drupal::entityTypeManager();
$form = $entities->getStorage('entity_form_display')->load('node.news_post.default');
$display = $entities->getStorage('entity_view_display')->load('node.news_post.default');
$byline = FieldConfig::loadByName('node', 'news_post', 'field_byline');
$checks = [
  'plain byline' => $byline->getType() === 'string' && $form->getRenderer('field_byline')->getPluginId() === 'string_textfield',
  'categories' => $form->getRenderer('field_news_categories')->getPluginId() === 'tagify_select_widget',
  'teaser widgets' => $form->getRenderer('field_teaser_image')->getPluginId() === 'media_library_widget' && $form->getRenderer('field_teaser_text')->getPluginId() === 'string_textarea',
  'paragraph field' => (bool) FieldConfig::loadByName('node', 'news_post', 'field_content') === ($case === 'paragraphs'),
  'layout builder' => (bool) $display->getThirdPartySetting('layout_builder', 'enabled', FALSE) === ($case === 'core-layout-builder'),
  'no body' => FieldConfig::loadByName('node', 'news_post', 'body') === NULL,
];
if ($case === 'paragraphs') {
  $checks['paragraph widget'] = $form->getRenderer('field_content')->getPluginId() === 'layout_paragraphs' && $display->getRenderer('field_content')->getPluginId() === 'layout_paragraphs';
}
foreach ($checks as $name => $passed) {
  if (!$passed) {
    throw new \RuntimeException("$case news $name failed.");
  }
}
$campus = Term::create(['vid' => 'news_categories', 'name' => 'Campus News']);
$campus->save();
$research = Term::create(['vid' => 'news_categories', 'name' => 'Research']);
$research->save();
$other_vocabulary = Vocabulary::create(['vid' => 'listing_other', 'name' => 'Other']);
$other_vocabulary->save();
$other_term = Term::create(['vid' => 'listing_other', 'name' => 'Other category']);
$other_term->save();
$nodes = [];
try {
  $now = time();
  $samples = [
    'Old campus' => [-90000, [$campus->id()], 'Earlier campus story'],
    'Research dispatch' => [-300, [$research->id()], 'New telescope instruments'],
    'Archive report' => [-200, [$campus->id()], 'Highlights from the collection'],
    'Latest story' => [-100, [$campus->id(), $research->id()], 'A new round of telescope discoveries'],
    'Unpublished story' => [-1, [$campus->id()], 'An unpublished telescope story'],
  ];
  foreach ($samples as $title => [$offset, $categories, $teaser]) {
    $nodes[$title] = Node::create(['type' => 'news_post', 'uid' => 1, 'title' => $title, 'status' => $title !== 'Unpublished story', 'created' => $now + $offset, 'field_byline' => 'Jane <em>Reporter</em>', 'field_news_categories' => $categories, 'field_teaser_text' => $teaser]);
    $nodes[$title]->save();
  }
  $violations = $nodes['Latest story']->validate();
  if (count($violations)) {
    throw new \RuntimeException('A simple News Post is invalid: ' . (string) $violations);
  }
  $path = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $nodes['Latest story']->id());
  if ($path !== '/news/campus-news/latest-story') {
    throw new \RuntimeException("The News alias did not use its first category: $path");
  }
  $news_form = \Drupal::service('entity.form_builder')->getForm($nodes['Latest story']);
  if (!isset($news_form['field_byline']['widget'], $news_form['field_news_categories']['widget'])) {
    throw new \RuntimeException('The News authoring form did not build.');
  }
  $build = $entities->getViewBuilder('node')->view($nodes['Latest story']);
  $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
  if (!str_contains($html, 'Jane &lt;em&gt;Reporter&lt;/em&gt;') || str_contains($html, '<em>Reporter</em>')) {
    throw new \RuntimeException('The News byline did not render as plain text.');
  }
  $all = ['Latest story', 'Archive report', 'Research dispatch', 'Old campus'];
  $filters = [
    [[], $all],
    [['event_search' => 'not a news match'], $all],
    [['news_search' => 'rchive rep'], ['Archive report']],
    [['news_search' => 'scope'], ['Latest story', 'Research dispatch']],
    [['news_categories' => [$campus->id()]], ['Latest story', 'Archive report', 'Old campus']],
    [['news_categories' => [$research->id()]], ['Latest story', 'Research dispatch']],
    [['news_categories' => [$campus->id(), $research->id()]], $all],
    [['news_categories' => [$campus->id()], 'news_search' => 'scope'], ['Latest story']],
    [['news_categories' => [$research->id()], 'news_search' => 'rchive'], []],
    [['news_search' => 'report Highlights'], []],
    [['news_search' => '%'], []],
    [['news_search' => 'Unpublished'], []],
    [['news_categories' => [], 'news_search' => ''], $all],
  ];
  foreach ($filters as [$input, $expected]) {
    $view = Views::getView('news');
    $view->setExposedInput($input);
    $view->execute('embed_recent');
    $actual = array_map(static fn($row) => $row->_entity->label(), $view->result);
    if ($actual !== $expected) {
      throw new \RuntimeException('Recent News filter failed for ' . json_encode($input) . ': ' . json_encode($actual));
    }
    if (!$input) {
      $build = $view->render();
      $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
      if (!str_contains($html, 'A new round of telescope discoveries') || str_contains($html, 'Unpublished story')) {
        throw new \RuntimeException('Recent News did not render published teasers.');
      }
    }
  }
  $view = Views::getView('news');
  $view->setDisplay('embed_recent');
  $view->initHandlers();
  $view->initStyle();
  $exposed = $view->display_handler->getPlugin('exposed_form')->renderExposedForm();
  if ($view->rowPlugin->getPluginId() !== 'fields' || $view->field['rendered_entity']->getPluginId() !== 'rendered_entity' || $view->field['rendered_entity']->options['view_mode'] !== 'teaser' || !isset($exposed['news_search'], $exposed['news_categories']) || isset($exposed['news_categories']['#options'][$other_term->id()])) {
    throw new \RuntimeException('Recent News did not build its rendered teaser field and vocabulary-limited exposed controls.');
  }
  // Filtering must happen before the ten-item Recent News limit.
  for ($index = 0; $index < 12; $index++) {
    $title = "Limit story $index";
    $nodes[$title] = Node::create(['type' => 'news_post', 'uid' => 1, 'title' => $title, 'status' => 1, 'created' => $now - 50 + $index, 'field_news_categories' => [$campus->id()]]);
    $nodes[$title]->save();
  }
  foreach ([[[], array_map(static fn($index) => "Limit story $index", range(11, 2))], [['news_search' => 'Old campus'], ['Old campus']], [['news_categories' => [$research->id()]], ['Latest story', 'Research dispatch']], [['news_search' => 'Limit story 3'], ['Limit story 3']]] as [$input, $expected]) {
    $view = Views::getView('news');
    $view->setExposedInput($input);
    $view->execute('embed_recent');
    $actual = array_map(static fn($row) => $row->_entity->label(), $view->result);
    if ($actual !== $expected) {
      throw new \RuntimeException('Recent News did not apply filtering before its ten-item limit.');
    }
  }
  echo "$case News Post recipe verified.\n";
}
finally {
  $entities->getStorage('node')->delete($nodes);
  $campus->delete();
  $research->delete();
  $other_term->delete();
  $other_vocabulary->delete();
}
