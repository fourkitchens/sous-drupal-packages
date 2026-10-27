<?php

/**
 * @file
 * Verify Paragraphs or core Layout Builder composition in a disposable site.
 *
 * Run after applying Sous Paragraphs, or enabling core Layout Builder:
 * ddev drush php:script /path/to/sous-drupal-packages/tests/recipes/sous-content-types/verify-composition.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\User;

function sous_composition_check(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'page');
$paragraphs = isset($fields['field_content']) && \Drupal::moduleHandler()->moduleExists('paragraphs');
$layout_builder = \Drupal::moduleHandler()->moduleExists('layout_builder');
sous_composition_check($paragraphs || $layout_builder, 'Apply Sous Paragraphs or enable core Layout Builder first.');
sous_composition_check(FieldConfig::loadByName('node', 'page', 'body') !== NULL, 'Composition removed the original Body field.');
$form = \Drupal::service('entity_display.repository')->getFormDisplay('node', 'page');
sous_composition_check($form->getComponent('body')['type'] === 'text_textarea_with_summary', 'Composition removed the Body widget.');
$display_storage = \Drupal::entityTypeManager()->getStorage('entity_view_display');
$display = $display_storage->load('node.page.default');
$original_display = NULL;
$page = $paragraph = NULL;
$account_switcher = \Drupal::service('account_switcher');
$account_switcher->switchTo(User::load(1));
try {
  $marker = 'Sous composition ' . bin2hex(random_bytes(6));
  if (!$paragraphs) {
    $original_display = clone $display;
    $formatter = $display->getComponent('body');
    $display->enableLayoutBuilder()->setOverridable()->save();
    $component = new SectionComponent(\Drupal::service('uuid')->generate(), 'content', [
      'id' => 'field_block:node:page:body',
      'label_display' => '0',
      'provider' => 'layout_builder',
      'formatter' => $formatter,
      'context_mapping' => ['entity' => 'layout_builder.entity'],
    ]);
    $display->removeAllSections()->appendSection(new Section('layout_onecol', [], [$component]))->save();
  }
  else {
    sous_composition_check($display->getComponent('field_content')['type'] === 'entity_reference_revisions_entity_view', 'Paragraphs has no visible formatter.');
    $paragraph = Paragraph::create([
      'type' => 'text',
      'field_text' => ['value' => "<p>Paragraph $marker</p>", 'format' => 'basic_html'],
    ]);
    $violations = $paragraph->validate();
    sous_composition_check(count($violations) === 0, 'Paragraph failed validation: ' . (string) $violations);
    $paragraph->save();
  }
  $page = Node::create([
    'type' => 'page',
    'title' => $marker,
    'field_seo_title' => $marker,
    'body' => ['value' => "<p>Body $marker</p>", 'format' => 'basic_html'],
    'uid' => 1,
    'status' => 1,
  ]);
  if ($paragraphs) {
    $page->set('field_content', ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()]);
  }
  $violations = $page->validate();
  sous_composition_check(count($violations) === 0, 'Composed Page failed validation: ' . (string) $violations);
  $page->save();
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $storage->resetCache([$page->id()]);
  $page = $storage->load($page->id());
  sous_composition_check($page->body->value === "<p>Body $marker</p>", 'Composed Page lost its Body after saving.');
  if ($paragraphs) {
    sous_composition_check($page->field_content->entity->field_text->value === "<p>Paragraph $marker</p>", 'Saved Paragraph reference did not resolve.');
  }
  $build = \Drupal::entityTypeManager()->getViewBuilder('node')->view($page);
  $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
  sous_composition_check(str_contains($html, "<p>Body $marker</p>"), 'Composed Page Body did not render.');
  sous_composition_check(!$paragraphs || str_contains($html, "<p>Paragraph $marker</p>"), 'Paragraph content did not render alongside Body.');
  print 'PASS: Body field/widget, save/load, and rendering with ' . ($paragraphs ? 'Sous Paragraphs' : 'core Layout Builder') . ".\n";
}
finally {
  if ($page !== NULL && !$page->isNew()) {
    $page->delete();
  }
  if ($paragraph !== NULL && !$paragraph->isNew() && Paragraph::load($paragraph->id())) {
    $paragraph->delete();
  }
  if ($original_display !== NULL) {
    $original_display->setOriginal($display_storage->loadUnchanged('node.page.default'));
    $original_display->save();
  }
  $account_switcher->switchBack();
}
