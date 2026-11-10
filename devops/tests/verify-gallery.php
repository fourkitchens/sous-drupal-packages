<?php

/**
 * @file
 * Verify Gallery configuration in each recipe composition.
 */

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\user\Entity\Role;

$case = getenv('COMPOSITION');
$entities = \Drupal::entityTypeManager();
$config = \Drupal::service('config.storage');
foreach (['paragraphs' => 'paragraphs.paragraphs_type.gallery', 'core-layout-builder' => 'block_content.type.gallery'] as $composition => $bundle) {
  if ($config->exists($bundle) !== ($case === $composition)) {
    throw new \RuntimeException("$case has incorrect Gallery bundle availability.");
  }
}

$type = match ($case) {
  'paragraphs' => 'paragraph',
  'core-layout-builder' => 'block_content',
  default => NULL,
};
if ($type) {
  $form = $entities->getStorage('entity_form_display')->load("$type.gallery.default");
  $view = $entities->getStorage('entity_view_display')->load("$type.gallery.default");
  foreach (['field_heading', 'field_text', 'field_style_layout_width'] as $name) {
    $field = FieldConfig::loadByName($type, 'gallery', $name);
    $image_field = FieldConfig::loadByName($type, 'image', $name);
    if ($field?->getFieldStorageDefinition()->id() !== $image_field->getFieldStorageDefinition()->id() || $field->getDefaultValueLiteral() !== $image_field->getDefaultValueLiteral() || !$form->getRenderer($name) || !$view->getRenderer($name)) {
      throw new \RuntimeException("$case Gallery does not reuse the Image field $name in its form and view.");
    }
  }
  $display = FieldConfig::loadByName($type, 'gallery', 'field_gallery_display');
  $display_widget = $form->getRenderer('field_gallery_display');
  $width = $form->getComponent('field_style_layout_width');
  if ($display?->getSetting('allowed_values') !== ['gallery-slider' => 'Slider', 'gallery-grid' => 'Grid'] || $display->getDefaultValueLiteral() !== [['value' => 'gallery-slider']]) {
    throw new \RuntimeException("$case Gallery Display must offer Slider and Grid, with Slider selected by default.");
  }
  if ($display_widget?->getPluginId() !== 'style_selector_tile_widget' || $display_widget->getSetting('size') !== 'large' || $display_widget->getSettings() !== $form->getRenderer('field_style_layout_width')->getSettings() || $form->getComponent('field_gallery_display')['weight'] !== $width['weight'] + 1 || $view->getRenderer('field_gallery_display')?->getPluginId() !== 'style_selector_css_class_formatter') {
    throw new \RuntimeException("$case Gallery Display must use matching large tiles under Gallery Width and provide its selected CSS class.");
  }
  $media = FieldConfig::loadByName($type, 'gallery', 'field_media_multiple');
  if ($media?->getFieldStorageDefinition()->getCardinality() !== FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED || $media->getSetting('target_type') !== 'media' || $media->getSetting('handler_settings')['target_bundles'] !== ['image' => 'image', 'video' => 'video']) {
    throw new \RuntimeException("$case Gallery media must allow unlimited Image and Video references.");
  }
  $formatter = $view->getRenderer('field_media_multiple');
  if ($form->getRenderer('field_media_multiple')?->getPluginId() !== 'media_library_widget' || $formatter?->getPluginId() !== 'entity_reference_entity_view' || $formatter->getSetting('view_mode') !== 'default') {
    throw new \RuntimeException("$case Gallery lacks its media widget or default media formatter.");
  }
  if ($type === 'paragraph') {
    foreach (['anonymous', 'authenticated'] as $role) {
      if (!Role::load($role)->hasPermission('view paragraph content gallery')) {
        throw new \RuntimeException("$role cannot view Gallery paragraphs.");
      }
    }
  }
  elseif (!\Drupal::service('plugin.manager.block')->hasDefinition('inline_block:gallery')) {
    throw new \RuntimeException('Gallery is unavailable in Layout Builder.');
  }
}

echo "$case Gallery bundle, media and display configuration verified.\n";
