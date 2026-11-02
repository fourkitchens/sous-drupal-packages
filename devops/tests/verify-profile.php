<?php

/**
 * @file
 * Verify Profile fields, generated titles, authoring and rendered teasers.
 */

use Drupal\Core\File\FileExists;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\workflows\Entity\Workflow;

$entities = \Drupal::entityTypeManager();
$storage = $entities->getStorage('node');
$display = $entities->getStorage('entity_form_display')->load('node.profile.default');
$definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'profile');
$file = NULL;
$media = NULL;
$node = NULL;
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo(User::load(1));
try {
  $file = \Drupal::service('file.repository')->writeData(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aDxcAAAAASUVORK5CYII='), 'public://profile-verifier.png', FileExists::Rename);
  $media = Media::create(['bundle' => 'image', 'name' => 'Profile portrait', 'status' => 1, 'field_media_image' => ['target_id' => $file->id(), 'alt' => 'Profile portrait']]);
  $media->save();
  $node = Node::create([
    'type' => 'profile',
    'uid' => 1,
    'field_first_name' => 'Ada',
    'field_last_name' => 'Lovelace',
    'field_image' => $media->id(),
    'field_description' => ['value' => '<p>Profile&nbsp;<strong>biography</strong><script>alert(1)</script></p>', 'format' => 'basic_html'],
    'field_teaser_image' => $media->id(),
    'field_teaser_text' => 'Profile teaser summary',
  ]);
  foreach (['field_first_name' => 'Ada', 'field_last_name' => 'Lovelace'] as $field => $value) {
    $node->set($field, '');
    if (!$definitions[$field]->isRequired() || !count($node->validate()->getByField($field))) {
      throw new \RuntimeException("Profile $field is not required by native validation.");
    }
    $node->set($field, $value);
  }
  $image = $definitions['field_image'];
  if ($image->getType() !== 'entity_reference' || $image->getSetting('target_type') !== 'media' || $image->getSetting('handler_settings')['target_bundles'] !== ['image' => 'image'] || $image->getFieldStorageDefinition()->getCardinality() !== 1 || $display->getRenderer('field_image')->getPluginId() !== 'media_library_widget') {
    throw new \RuntimeException('Profile Image must select one Image media entity through Media Library.');
  }
  $form = \Drupal::service('entity.form_builder')->getForm($node);
  $title = $form['title']['widget'][0]['value'];
  if ($title['#type'] !== 'hidden' || $title['#required'] !== FALSE || ($form['field_metadata']['widget'][0]['#group'] ?? NULL) !== 'advanced') {
    throw new \RuntimeException('Profile title is visible or its Metatag sidebar is missing.');
  }
  $bio = $form['field_description']['widget'][0];
  if ($definitions['field_description']->getType() !== 'text_long' || $definitions['field_description']->getSetting('allowed_formats') !== ['basic_html'] || $bio['#allowed_formats'] !== ['basic_html'] || array_keys($bio['format']['format']['#options']) !== ['basic_html'] || $bio['format']['format']['#default_value'] !== 'basic_html') {
    throw new \RuntimeException('Profile Bio does not restrict its editor to Basic HTML.');
  }
  if ($node->moderation_state->value !== 'draft' || $form['moderation_state']['widget'][0]['state']['#default_value'] !== 'draft' || !in_array('profile', Workflow::load('editorial')->getTypePlugin()->getBundlesForEntityType('node'), TRUE)) {
    throw new \RuntimeException('Profile does not begin in the shared Draft workflow.');
  }
  $violations = $node->validate();
  if (count($violations)) {
    throw new \RuntimeException('Profile fixture is invalid: ' . (string) $violations);
  }
  $node->save();
  $node = $storage->loadUnchanged($node->id());
  if ($node->label() !== 'Ada Lovelace' || $node->isPublished()) {
    throw new \RuntimeException('Profile did not generate its full name before its first Draft save.');
  }
  $node->set('field_first_name', 'Grace')->set('field_last_name', 'Hopper')->save();
  $node = $storage->loadUnchanged($node->id());
  if ($node->label() !== 'Grace Hopper') {
    throw new \RuntimeException('Profile title did not update when its name fields changed.');
  }
  $bio_output = $node->field_description->processed;
  if (!str_contains($bio_output, '<strong>biography</strong>') || str_contains($bio_output, '<script') || str_contains($bio_output, '&nbsp;')) {
    throw new \RuntimeException('Profile Bio did not render through Basic HTML filters.');
  }
  $teaser = $entities->getViewBuilder('node')->view($node, 'teaser');
  $teaser_output = (string) \Drupal::service('renderer')->renderInIsolation($teaser);
  if (!str_contains($teaser_output, 'Profile teaser summary') || !str_contains($teaser_output, 'Profile portrait') || str_contains($teaser_output, 'biography')) {
    throw new \RuntimeException('Profile teaser does not render its shared teaser text and image.');
  }
}
finally {
  if ($node && !$node->isNew()) {
    $node->delete();
  }
  if ($media) {
    $media->delete();
  }
  if ($file) {
    $file->delete();
  }
  $switcher->switchBack();
}

print getenv('COMPOSITION') . " Profile fields, automatic names, Basic HTML Bio and teasers verified.\n";
