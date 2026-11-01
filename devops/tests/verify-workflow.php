<?php

/**
 * @file
 * Verify real moderation revisions and scheduled transitions in a fresh fixture.
 */

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
    $form = \Drupal::service('entity.form_builder')->getForm($node);
    $group = $form['publish_on']['#group'];
    if (($form[$group]['#group'] ?? NULL) !== 'advanced' || $form['unpublish_on']['#group'] !== $group || !isset($form['moderation_state']['widget'][0]['state'])) {
      throw new \RuntimeException("$bundle scheduling controls are missing from the sidebar.");
    }
    foreach (['publish_state' => 'published', 'unpublish_state' => 'archived'] as $field => $state) {
      $widget = $form[$field]['widget'][0];
      $options = array_values(array_diff(array_keys($widget['#options']), ['_none', '']));
      if ($options !== [$state] || ($widget['#access'] ?? TRUE) === FALSE || ($form[$field]['#access'] ?? TRUE) === FALSE) {
        throw new \RuntimeException("$bundle $field did not offer $state.");
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
    $node->set('moderation_state', 'draft')->set('publish_on', $now + 3600)->set('publish_state', 'published')->save();
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
    $node->set('unpublish_on', $now - 60)->set('unpublish_state', 'archived')->save();
    $scheduled_revision = $node->getRevisionId();
    if (!$node->isPublished()) {
      throw new \RuntimeException("$bundle unpublished before cron.");
    }
    $scheduler->runLightweightCron(['nolog' => TRUE]);
    $node = $storage->loadUnchanged($node->id());
    if ($node->isPublished() || !$node->isDefaultRevision() || $node->moderation_state->value !== 'archived' || $node->getRevisionId() <= $scheduled_revision || !$node->unpublish_on->isEmpty() || !$node->unpublish_state->isEmpty()) {
      throw new \RuntimeException("$bundle scheduled Archive did not create and clear an unpublished revision.");
    }
    echo "$bundle Draft, Publish, scheduled Publish and scheduled Archive verified.\n";
  }
}
finally {
  $switcher->switchBack();
  $storage->delete($nodes);
  foreach ($terms as $term) {
    $term->delete();
  }
}
