<?php

/**
 * @file
 * Verify real moderation revisions and scheduled transitions in a fresh fixture.
 */

use Drupal\Core\Form\FormState;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use Drupal\workflows\Entity\Workflow;

$entities = \Drupal::entityTypeManager();
$storage = $entities->getStorage('node');
$workflow = Workflow::load('editorial')->getTypePlugin();
$bundles = array_keys($entities->getStorage('node_type')->loadMultiple());
$moderated = $workflow->getBundlesForEntityType('node');
sort($bundles);
sort($moderated);
if ($moderated !== $bundles || $workflow->getConfiguration()['default_moderation_state'] !== 'draft') {
  throw new \RuntimeException('Editorial must default to Draft and cover every installed node bundle.');
}
$nodes = [];
$terms = [];
$now = \Drupal::time()->getRequestTime();
$scheduler = \Drupal::service('scheduler.manager');
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo(User::load(1));
try {
  foreach ($bundles as $bundle) {
    $node = Node::create(['type' => $bundle, 'uid' => 1, 'title' => "Workflow $bundle"]);
    if (in_array($bundle, ['event', 'news_post'], TRUE)) {
      $vocabulary = $bundle === 'event' ? 'event_types' : 'news_categories';
      $terms[$bundle] = Term::create(['vid' => $vocabulary, 'name' => "Workflow $bundle"]);
      $terms[$bundle]->save();
      $node->set($bundle === 'event' ? 'field_event_type' : 'field_news_categories', $terms[$bundle]->id());
    }
    if ($bundle === 'profile') {
      $node->set('field_first_name', 'Workflow')->set('field_last_name', $bundle);
    }
    if ($bundle === 'event') {
      $node->set('field_event_date', [['value' => $now + 3600, 'end_value' => $now + 7200, 'duration' => 60, 'timezone' => 'UTC']]);
    }
    if ($node->hasField('field_content')) {
      $paragraph = $entities->getStorage('paragraph')->create(['type' => 'text', 'field_text' => ['value' => 'Workflow content', 'format' => 'basic_html']]);
      $node->set('field_content', ['entity' => $paragraph]);
    }
    if ($node->moderation_state->value !== 'draft') {
      throw new \RuntimeException("$bundle did not begin in Draft.");
    }
    $new_form = \Drupal::service('entity.form_builder')->getForm($node);
    if ((string) $new_form['moderation_state']['widget'][0]['state']['#title'] !== 'Save as' || $new_form['moderation_state']['widget'][0]['state']['#default_value'] !== 'draft') {
      throw new \RuntimeException("$bundle new form did not default to Save as Draft.");
    }
    $violations = $node->validate();
    if (count($violations)) {
      throw new \RuntimeException("$bundle workflow fixture is invalid: " . (string) $violations);
    }
    $node->save();
    $nodes[$bundle] = $node;
    $node = $storage->loadUnchanged($node->id());
    if ($node->isPublished() || $node->moderation_state->value !== 'draft') {
      throw new \RuntimeException("$bundle saved its initial Draft as published.");
    }
    $display = $entities->getStorage('entity_form_display')->load("node.$bundle.default");
    foreach (['moderation_state' => 'moderation_state_default', 'publish_on' => 'datetime_timestamp_no_default', 'unpublish_on' => 'datetime_timestamp_no_default', 'publish_state' => 'scheduler_moderation', 'unpublish_state' => 'scheduler_moderation'] as $field => $plugin) {
      if ($display->getRenderer($field)?->getPluginId() !== $plugin) {
        throw new \RuntimeException("$bundle has no native $field widget.");
      }
    }
    $form_state = new FormState();
    $form_object = $entities->getFormObject('node', 'default')->setEntity($node);
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $group = $form['publish_on']['#group'];
    if (($form[$group]['#group'] ?? NULL) !== 'advanced' || $form['unpublish_on']['#group'] !== $group || !isset($form['moderation_state']['widget'][0]['state']) || ($form['publish_on']['#access'] ?? TRUE) === FALSE || ($form['unpublish_on']['#access'] ?? TRUE) === FALSE || isset($form['published_at']) || (in_array($bundle, ['event', 'news_post'], TRUE) && isset($form['menu']))) {
      throw new \RuntimeException("$bundle scheduling controls are missing from the sidebar.");
    }
    foreach (['publish_state' => 'published', 'unpublish_state' => 'archived'] as $field => $state) {
      $widget = $form[$field]['widget'][0];
      $build = $form[$field];
      $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
      if ($widget['#type'] !== 'hidden' || $widget['#value'] !== $state || str_contains($html, '<select') || str_contains($html, '<label') || !str_contains($html, 'type="hidden"')) {
        throw new \RuntimeException("$bundle $field did not render only the fixed hidden $state.");
      }
      $display->getRenderer($field)::validateElement($widget, $form_state);
      $display->getRenderer($field)->extractFormValues($node->get($field), $form, $form_state);
      if ($node->get($field)->value !== $state) {
        throw new \RuntimeException("$bundle $field hidden value did not bind through its native widget.");
      }
    }
    $draft_revision = $node->getRevisionId();
    $node->set('moderation_state', 'published')->save();
    $node = $storage->loadUnchanged($node->id());
    if (!$node->isPublished() || !$node->isDefaultRevision() || $node->getRevisionId() <= $draft_revision) {
      throw new \RuntimeException("$bundle did not publish a new default revision.");
    }
    $live_title = $node->label();
    $node->setTitle("Revised $bundle");
    if ($bundle === 'profile') {
      $node->set('field_first_name', 'Revised')->set('field_last_name', $bundle);
    }
    $node->set('moderation_state', 'draft')->set('publish_on', $now + 3600)->save();
    $pending_revision = $node->getRevisionId();
    $live = $storage->loadUnchanged($node->id());
    $pending = $storage->loadRevision($pending_revision);
    if (!$live->isPublished() || $live->label() !== $live_title || $pending->isDefaultRevision() || $pending->isPublished() || $pending->moderation_state->value !== 'draft') {
      throw new \RuntimeException("$bundle pending Draft replaced the live revision.");
    }
    // Move this stored schedule past due without triggering publish-on-save.
    foreach (['node_field_data', 'node_field_revision'] as $table) {
      \Drupal::database()->update($table)->fields(['publish_on' => $now - 60])->condition('vid', $pending_revision)->execute();
    }
    $storage->resetCache([$node->id()]);
    $scheduler->runLightweightCron(['nolog' => TRUE]);
    $node = $storage->loadUnchanged($node->id());
    if (!$node->isPublished() || !$node->isDefaultRevision() || $node->moderation_state->value !== 'published' || $node->label() !== "Revised $bundle" || $node->getRevisionId() <= $pending_revision || !$node->publish_on->isEmpty() || !$node->publish_state->isEmpty()) {
      throw new \RuntimeException("$bundle scheduled Publish did not promote and clear the pending revision.");
    }
    $form_state = new FormState();
    $form_object = $entities->getFormObject('node', 'default')->setEntity($node);
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $display->getRenderer('publish_state')::validateElement($form['publish_state']['widget'][0], $form_state);
    $display->getRenderer('publish_state')->extractFormValues($node->get('publish_state'), $form, $form_state);
    if ($node->publish_state->value !== 'published') {
      throw new \RuntimeException("$bundle editing form did not restore the cleared Publish state.");
    }
    $node->set('unpublish_on', $now - 60)->save();
    $scheduled_revision = $node->getRevisionId();
    if (!$node->isPublished()) {
      throw new \RuntimeException("$bundle unpublished before cron.");
    }
    $scheduler->runLightweightCron(['nolog' => TRUE]);
    $node = $storage->loadUnchanged($node->id());
    if ($node->isPublished() || !$node->isDefaultRevision() || $node->moderation_state->value !== 'archived' || $node->getRevisionId() <= $scheduled_revision || !$node->unpublish_on->isEmpty() || !$node->unpublish_state->isEmpty()) {
      throw new \RuntimeException("$bundle scheduled Archive did not create and clear an unpublished revision.");
    }
    $form_state = new FormState();
    $form_object = $entities->getFormObject('node', 'default')->setEntity($node);
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $display->getRenderer('unpublish_state')::validateElement($form['unpublish_state']['widget'][0], $form_state);
    $display->getRenderer('unpublish_state')->extractFormValues($node->get('unpublish_state'), $form, $form_state);
    if ($node->unpublish_state->value !== 'archived') {
      throw new \RuntimeException("$bundle editing form did not restore the cleared Archive state.");
    }
    $node->set('publish_on', $now + 3600)->save();
    foreach (['node_field_data', 'node_field_revision'] as $table) {
      \Drupal::database()->update($table)->fields(['publish_on' => $now - 60])->condition('vid', $node->getRevisionId())->execute();
    }
    $storage->resetCache([$node->id()]);
    $scheduler->runLightweightCron(['nolog' => TRUE]);
    $node = $storage->loadUnchanged($node->id());
    if (!$node->isPublished() || $node->moderation_state->value !== 'published' || !$node->publish_on->isEmpty() || !$node->publish_state->isEmpty()) {
      throw new \RuntimeException("$bundle could not schedule Publish again after cron cleared its state.");
    }
    echo "$bundle Draft, hidden scheduling controls, Publish, Archive and re-scheduling verified.\n";
  }
}
finally {
  $switcher->switchBack();
  $storage->delete($nodes);
  foreach ($terms as $term) {
    $term->delete();
  }
}
