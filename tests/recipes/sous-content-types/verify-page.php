<?php

/**
 * @file
 * Verify Page authoring and rendering in an expendable Drupal installation.
 *
 * Run after applying Sous Content Types, before a composition recipe:
 * ddev drush php:script /path/to/sous-drupal-packages/tests/recipes/sous-content-types/verify-page.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

function sous_page_check(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

foreach (['node', 'text', 'field_group', 'media_library', 'metatag', 'publication_date', 'scheduler', 'tagify'] as $module) {
  sous_page_check(\Drupal::moduleHandler()->moduleExists($module), "Missing module: $module");
}

$field = FieldConfig::loadByName('node', 'page', 'body');
sous_page_check($field !== NULL && $field->getType() === 'text_with_summary', 'Page must have the core Body field.');
$displays = \Drupal::service('entity_display.repository');
$form_display = $displays->getFormDisplay('node', 'page');
sous_page_check($form_display->getComponent('body')['type'] === 'text_textarea_with_summary', 'Missing Body widget.');
$groups = $form_display->getThirdPartySettings('field_group');
sous_page_check(in_array('body', $groups['group_content']['children'] ?? [], TRUE), 'Body must appear in the Content tab.');
sous_page_check($displays->getViewDisplay('node', 'page')->getComponent('body')['type'] === 'text_default', 'Body must have a visible core formatter.');

$account_switcher = \Drupal::service('account_switcher');
$account_switcher->switchTo(User::load(1));
$page = NULL;
try {
  $marker = 'Sous Page regression ' . bin2hex(random_bytes(6));
  $page = Node::create([
    'type' => 'page',
    'title' => $marker,
    'field_seo_title' => $marker,
    'field_teaser_text' => 'A usable Page teaser.',
    'body' => ['value' => "<p>$marker</p>", 'format' => 'basic_html'],
    'uid' => 1,
    'status' => 1,
  ]);
  $form = \Drupal::service('entity.form_builder')->getForm($page);
  foreach (['title', 'body', 'field_seo_title', 'field_teaser_image', 'field_tags'] as $widget) {
    sous_page_check(isset($form[$widget]['widget']), "Page form cannot build widget: $widget");
  }
  $violations = $page->validate();
  sous_page_check(count($violations) === 0, 'New Page failed validation: ' . (string) $violations);
  $page->save();

  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $storage->resetCache([$page->id()]);
  $page = $storage->load($page->id());
  sous_page_check($page->isPublished() && $page->body->value === "<p>$marker</p>", 'Page body did not survive saving and loading.');
  $build = \Drupal::entityTypeManager()->getViewBuilder('node')->view($page);
  $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
  sous_page_check(str_contains($html, "<p>$marker</p>"), 'Saved Page body did not render.');

  $form = \Drupal::service('entity.form_builder')->getForm($page);
  sous_page_check(isset($form['body']['widget'][0]), 'Saved Page cannot build an edit form.');
  $edited = 'Edited ' . $marker;
  $page->setTitle($edited);
  $page->set('field_seo_title', $edited);
  $page->set('body', ['value' => "<p>$edited</p>", 'format' => 'basic_html']);
  $violations = $page->validate();
  sous_page_check(count($violations) === 0, 'Edited Page failed validation: ' . (string) $violations);
  $page->save();
  $storage->resetCache([$page->id()]);
  $page = $storage->load($page->id());
  sous_page_check($page->body->value === "<p>$edited</p>", 'Edited Page body was not saved.');
  $build = \Drupal::entityTypeManager()->getViewBuilder('node')->view($page);
  $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
  sous_page_check(str_contains($html, "<p>$edited</p>"), 'Edited Page body did not render.');
  print "PASS: Page widgets, validation, creation, editing, saving, and body rendering.\n";
}
finally {
  if ($page !== NULL && !$page->isNew()) {
    $page->delete();
  }
  $account_switcher->switchBack();
}
