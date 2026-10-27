<?php

/**
 * @file
 * Exercise all four paragraph types in an explicitly disposable Drupal site.
 *
 * ddev exec env SOUS_DISPOSABLE_TEST=1 vendor/bin/drush php:script /path/to/sous-drupal-packages/tests/recipes/sous-paragraph-types/verify-paragraph-types.php
 * Set SOUS_KEEP_TEST_CONTENT=1 to retain a browser fixture after successful checks.
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\File\FileExists;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\OEmbed\Provider;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\user\Entity\User;
use Symfony\Component\Yaml\Yaml;

if (getenv('SOUS_DISPOSABLE_TEST') !== '1') {
  throw new RuntimeException('Set SOUS_DISPOSABLE_TEST=1 only for a disposable installation.');
}
function sous_paragraph_check(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}
foreach (['paragraphs', 'paragraphs_ee', 'paragraphs_collapsible', 'text', 'options', 'media', 'media_library', 'node'] as $module) {
  sous_paragraph_check(\Drupal::moduleHandler()->moduleExists($module), 'Missing module: ' . $module);
}
$recipe_path = DRUPAL_ROOT . '/recipes/sous-paragraph-types';
foreach (glob($recipe_path . '/config/*.yml') as $file) {
  $data = Yaml::parseFile($file);
  sous_paragraph_check(!array_key_exists('uuid', $data) && !isset($data['_core']['default_config_hash']), 'Export metadata in ' . $file);
}
sous_paragraph_check(\Drupal::entityTypeManager()->getStorage('entity_view_mode')->load('paragraph.preview') !== NULL, 'Missing native Paragraphs preview mode.');

$marker = bin2hex(random_bytes(6));
$paragraphs = $media = [];
$host = $host_type = $storage = $file = NULL;
$success = FALSE;
$keep = getenv('SOUS_KEEP_TEST_CONTENT') === '1';
$cache_ids = [];
$cache = \Drupal::cache();
$providers = \Drupal::keyValue('media');
$original_providers = $providers->get('oembed_providers');
$account_switcher = \Drupal::service('account_switcher');
$account_switcher->switchTo(User::load(1));
try {
  foreach (['image', 'text', 'text_with_media', 'video'] as $bundle) {
    $source = Yaml::parseFile($recipe_path . '/config/paragraphs.paragraphs_type.' . $bundle . '.yml');
    $type = ParagraphsType::load($bundle);
    sous_paragraph_check($type !== NULL, 'Missing paragraph type: ' . $bundle);
    $icon = $type->getIconFile();
    sous_paragraph_check($icon && $icon->uuid() === $source['icon_uuid'], 'Portable icon was not restored: ' . $bundle);
    $bytes = base64_decode(substr($source['icon_default'], strlen('data:image/png;base64,')), TRUE);
    sous_paragraph_check($bytes !== FALSE && getimagesizefromstring($bytes) !== FALSE, 'Invalid embedded icon: ' . $bundle);
    sous_paragraph_check(file_get_contents($icon->getFileUri()) === $bytes, 'Restored icon bytes differ: ' . $bundle);
    // Simulate an absent managed file, then exercise Paragraphs' own restoration.
    $icon->delete();
    \Drupal::entityTypeManager()->getStorage('file')->resetCache();
    $icon = $type->getIconFile();
    sous_paragraph_check($icon && $icon->uuid() === $source['icon_uuid'] && file_get_contents($icon->getFileUri()) === $bytes, 'Icon recreation failed: ' . $bundle);
    $usage = \Drupal::service('file.usage')->listUsage($icon);
    sous_paragraph_check(($usage['paragraphs']['paragraphs_type'][$bundle] ?? 0) > 0, 'Restored icon lacks file usage: ' . $bundle);
    $form = \Drupal::service('entity_display.repository')->getFormDisplay('paragraph', $bundle);
    $view = \Drupal::service('entity_display.repository')->getViewDisplay('paragraph', $bundle);
    foreach (match ($bundle) {
      'image' => ['field_image'], 'text' => ['field_text'],
      'video' => ['field_video'], 'text_with_media' => ['field_text', 'field_media', 'field_media_alignment'],
    } as $field) {
      sous_paragraph_check($form->getComponent($field) !== NULL, 'Missing paragraph widget: ' . $bundle . '.' . $field);
      if ($field !== 'field_media_alignment') {
        sous_paragraph_check($view->getComponent($field) !== NULL, 'Missing paragraph formatter: ' . $bundle . '.' . $field);
      }
    }
  }
  $image_source = Yaml::parseFile($recipe_path . '/config/paragraphs.paragraphs_type.image.yml');
  $image_bytes = base64_decode(substr($image_source['icon_default'], strlen('data:image/png;base64,')), TRUE);
  $uri = \Drupal::service('file_system')->saveData($image_bytes, 'public://sous-paragraph-test-' . $marker . '.png', FileExists::Error);
  $file = File::create(['uri' => $uri, 'status' => 1, 'uid' => 1]);
  $file->save();
  $image = Media::create(['bundle' => 'image', 'name' => 'Sous image ' . $marker, 'status' => 1, 'uid' => 1, 'field_media_image' => ['target_id' => $file->id(), 'alt' => 'Sous paragraph image ' . $marker]]);
  $media[] = $image;
  sous_paragraph_check(count($image->validate()) === 0, 'Image media is invalid.');
  $image->save();

  // Deterministic oEmbed data exercises native validation and iframe rendering
  // without depending on a remote provider's availability during a regression.
  $video_url = 'https://www.youtube.com/watch?v=sous_' . $marker;
  $providers->set('oembed_providers', [
    'data' => ['YouTube' => new Provider('YouTube', 'https://www.youtube.com/', [['schemes' => ['https://www.youtube.com/watch?v=*'], 'url' => 'https://www.youtube.com/oembed']])],
    'expires' => time() + 3600,
  ]);
  $resource_url = \Drupal::service('media.oembed.url_resolver')->getResourceUrl($video_url);
  foreach (['::', ':0:0'] as $suffix) {
    $cache_ids[] = 'media:oembed_resource_url:' . $video_url . $suffix;
    $cache->set(end($cache_ids), $resource_url);
  }
  $cache_ids[] = 'media:oembed_resource:' . $resource_url;
  $cache->set(end($cache_ids), ['version' => '1.0', 'type' => 'video', 'title' => 'Sous video ' . $marker, 'width' => 640, 'height' => 360, 'html' => '<iframe src="about:blank" title="Sous video fixture"></iframe>']);
  $video = Media::create(['bundle' => 'video', 'name' => 'Sous video ' . $marker, 'status' => 1, 'uid' => 1, 'field_media_oembed_video' => $video_url]);
  $media[] = $video;
  sous_paragraph_check(count($video->validate()) === 0, 'Video media is invalid.');
  $video->save();

  $paragraphs = [
    Paragraph::create(['type' => 'image', 'field_image' => ['target_id' => $image->id()]]),
    Paragraph::create(['type' => 'text', 'field_text' => ['value' => '<p>Text ' . $marker . '</p>', 'format' => 'basic_html']]),
    Paragraph::create(['type' => 'text_with_media', 'field_text' => ['value' => '<p>Text with media ' . $marker . '</p>', 'format' => 'basic_html'], 'field_media' => ['target_id' => $image->id()], 'field_media_alignment' => 'right']),
    Paragraph::create(['type' => 'video', 'field_video' => ['target_id' => $video->id()]]),
  ];
  foreach ($paragraphs as $paragraph) {
    $form = \Drupal::service('entity.form_builder')->getForm($paragraph);
    sous_paragraph_check(!empty($form), 'Cannot build paragraph form: ' . $paragraph->bundle());
    sous_paragraph_check(count($paragraph->validate()) === 0, 'Invalid paragraph: ' . $paragraph->bundle());
    $paragraph->save();
  }

  $host_field = 'field_content';
  $host_bundle = 'page';
  if (!FieldConfig::load('node.page.field_content')) {
    $host_bundle = 'sous_paragraph_test_' . substr($marker, 0, 8);
    $host_field = 'field_sous_test_' . substr($marker, 0, 8);
    $host_type = NodeType::create(['type' => $host_bundle, 'name' => 'Disposable paragraph host']);
    $host_type->save();
    $storage = FieldStorageConfig::create(['field_name' => $host_field, 'entity_type' => 'node', 'type' => 'entity_reference_revisions', 'settings' => ['target_type' => 'paragraph'], 'cardinality' => -1]);
    $storage->save();
    FieldConfig::create(['field_storage' => $storage, 'bundle' => $host_bundle, 'label' => 'Content', 'settings' => ['handler' => 'default:paragraph', 'handler_settings' => ['target_bundles' => array_combine(['image', 'text', 'text_with_media', 'video'], ['image', 'text', 'text_with_media', 'video'])]]])->save();
    EntityFormDisplay::create(['targetEntityType' => 'node', 'bundle' => $host_bundle, 'mode' => 'default', 'status' => TRUE])->setComponent('title', ['type' => 'string_textfield'])->setComponent($host_field, ['type' => 'paragraphs'])->save();
    EntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => $host_bundle, 'mode' => 'default', 'status' => TRUE])->setComponent($host_field, ['type' => 'entity_reference_revisions_entity_view', 'label' => 'hidden'])->save();
  }
  $references = fn () => array_map(fn ($item) => ['target_id' => $item->id(), 'target_revision_id' => $item->getRevisionId()], $paragraphs);
  $host = Node::create(['type' => $host_bundle, 'title' => 'Sous paragraph authoring ' . $marker, 'uid' => 1, 'status' => 1, $host_field => $references()]);
  if ($host->hasField('field_seo_title')) $host->set('field_seo_title', 'Sous paragraph authoring ' . $marker);
  if ($host->hasField('body')) $host->set('body', ['value' => '<p>Body ' . $marker . '</p>', 'format' => 'basic_html']);
  sous_paragraph_check(count($host->validate()) === 0, 'Invalid composed host.');
  $host->save();
  $form = \Drupal::service('entity.form_builder')->getForm($host);
  sous_paragraph_check(isset($form[$host_field]['widget']), 'Host Paragraphs widget did not build.');
  $paragraphs[1]->set('field_text', ['value' => '<p>Edited text ' . $marker . '</p>', 'format' => 'basic_html']);
  $paragraphs[1]->setNewRevision(TRUE);
  $paragraphs[1]->save();
  $host->set($host_field, $references());
  $host->save();
  \Drupal::entityTypeManager()->getStorage('node')->resetCache([$host->id()]);
  $host = Node::load($host->id());
  sous_paragraph_check(count($host->get($host_field)) === 4, 'The host lost a paragraph reference.');
  sous_paragraph_check($host->get($host_field)[2]->entity->field_media_alignment->value === 'right', 'Media alignment did not reload.');
  sous_paragraph_check($host->get($host_field)[1]->entity->field_text->value === '<p>Edited text ' . $marker . '</p>', 'Edited paragraph revision did not reload through its host.');
  $build = \Drupal::entityTypeManager()->getViewBuilder('node')->view($host);
  $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
  foreach (['Edited text ' . $marker, 'Text with media ' . $marker, 'Sous paragraph image ' . $marker, 'media-oembed-content'] as $expected) {
    sous_paragraph_check(str_contains($html, $expected), 'Composed output is missing: ' . $expected);
  }
  if ($host->hasField('body')) sous_paragraph_check(str_contains($html, 'Body ' . $marker), 'Composition lost its existing Body output.');
  $success = TRUE;
  print "PASS: four paragraph forms, validation, save/edit/reload, media rendering, portable icon recreation/usage, and host composition.\n";
  if ($keep) print json_encode(['node_id' => $host->id(), 'node_path' => '/node/' . $host->id(), 'host_field' => $host_field, 'paragraph_ids' => array_map(fn ($item) => $item->id(), $paragraphs), 'media_ids' => array_map(fn ($item) => $item->id(), $media), 'marker' => $marker], JSON_THROW_ON_ERROR) . PHP_EOL;
}
finally {
  if (!($success && $keep)) {
    if ($host && !$host->isNew()) $host->delete();
    foreach ($paragraphs as $paragraph) if (!$paragraph->isNew() && Paragraph::load($paragraph->id())) $paragraph->delete();
    foreach ($media as $item) if (!$item->isNew()) $item->delete();
    if ($file && !$file->isNew()) $file->delete();
    if ($host_type) $host_type->delete();
    if ($storage && ($active_storage = FieldStorageConfig::load($storage->id()))) $active_storage->delete();
    foreach ($cache_ids as $id) $cache->delete($id);
  }
  if ($original_providers === NULL) $providers->delete('oembed_providers');
  else $providers->set('oembed_providers', $original_providers);
  $account_switcher->switchBack();
}
