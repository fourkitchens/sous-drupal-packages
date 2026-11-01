<?php

/**
 * @file
 * Verify recipe compositions through Drupal's installed entities and plugins.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\user\Entity\Role;

$case = getenv('COMPOSITION');
$content = in_array($case, ['content-types', 'core-layout-builder', 'paragraphs'], TRUE);
$paragraphs = $case === 'paragraphs';
$modules = \Drupal::moduleHandler();
$storage = \Drupal::service('config.storage');
$entities = \Drupal::entityTypeManager();
$checks = [
  'admin theme' => \Drupal::config('system.theme')->get('admin') === 'gin',
  'default theme' => \Drupal::config('system.theme')->get('default') === 'emulsify',
];
foreach (['page', 'frontpage'] as $type) {
  $checks[$type] = $storage->exists("node.type.$type") === $content;
  $display = $entities->getStorage('entity_view_display')->load("node.$type.default");
  $checks["layout.$type"] = (bool) $display?->getThirdPartySetting('layout_builder', 'enabled', FALSE) === ($case === 'core-layout-builder');
  if (!$content) {
    continue;
  }
  $form = $entities->getStorage('entity_form_display')->load("node.$type.default");
  $groups = $form->getThirdPartySettings('field_group');
  $checks["title.$type"] = $form->getRenderer('title')->getPluginId() === 'string_textfield' && $form->getComponent('title')['weight'] < $groups['group_main']['weight'];
  $checks["seo.$type"] = FieldConfig::loadByName('node', $type, 'field_seo_title') === NULL && !isset($groups['group_info']);
  $checks["body.$type"] = (bool) FieldConfig::loadByName('node', $type, 'body') === !$paragraphs;
  if ($paragraphs) {
    $checks["widget.$type"] = $form->getRenderer('field_content')->getPluginId() === 'layout_paragraphs' && $form->getRenderer('field_content')->getSetting('widget_settings_source') === 'default';
    $checks["content.$type"] = $groups['group_content']['children'] === ['field_content'] && $display->getRenderer('field_content')->getPluginId() === 'layout_paragraphs';
  }
}
foreach (['linkit' => $content, 'emulsify_tools' => TRUE, 'ckeditor5' => $content, 'paragraphs' => $paragraphs, 'config_split' => $case === 'config-split'] as $module => $enabled) {
  $checks[$module] = $modules->moduleExists($module) === $enabled;
}
foreach (['layout_paragraphs', 'layout_paragraphs_library', 'layout_paragraphs_permissions', 'layout_paragraphs_limit', 'paragraphs_type_permissions'] as $module) {
  $checks[$module] = $modules->moduleExists($module) === $paragraphs;
}
foreach (['local', 'staging'] as $id) {
  $name = "config_split.config_split.$id";
  $checks[$name] = $storage->exists($name) === ($case === 'config-split') && ($case !== 'config-split' || \Drupal::config($name)->get('status') === FALSE);
}
if ($paragraphs) {
  $widget_settings = \Drupal::config('layout_paragraphs.widget_settings');
  $checks['component menu'] = $widget_settings->get('display_mode') === 'grid' && $widget_settings->get('show_search') && $widget_settings->get('show_icons') && $widget_settings->get('show_description');
  $modal = \Drupal::config('layout_paragraphs.modal_settings');
  $checks['component modal'] = $modal->get('width') === '80%' && $modal->get('height') === '80%' && $modal->get('autoresize');
  $checks['component menu patch'] = class_exists(\Drupal\layout_paragraphs\Form\LayoutParagraphsWidgetSettingsForm::class);
  foreach (['anonymous', 'authenticated'] as $role_id) {
    foreach (['text', 'text_with_media', 'video', 'image', 'from_library'] as $bundle) {
      $checks["view.$role_id.$bundle"] = Role::load($role_id)->hasPermission("view paragraph content $bundle");
    }
  }
}

// The same portable field and authoring contracts apply to Paragraphs and blocks.
$common = ['field_heading' => ['string', 'string_textfield'], 'field_text' => ['text_long', 'text_textarea']];
$components = [
  'text' => $common,
  'text_with_media' => $common + [
    'field_media' => ['entity_reference', 'media_library_widget'],
    'field_show_captions' => ['boolean', 'boolean_checkbox'],
    'field_text_with_media_display' => ['style_selector_css_class', 'style_selector_tile_widget'],
    'field_component_focus' => ['style_selector_css_class', 'style_selector_tile_widget'],
    'field_style_media_alignment' => ['style_selector_css_class', 'style_selector_tile_widget'],
  ],
];
foreach (['video', 'image'] as $bundle) {
  $components[$bundle] = $common + [
    "field_$bundle" => ['entity_reference', 'media_library_widget'],
    'field_style_layout_width' => ['style_selector_css_class', 'style_selector_tile_widget'],
    'field_show_captions' => ['boolean', 'boolean_checkbox'],
  ];
}
foreach (['paragraph' => $paragraphs, 'block_content' => $case === 'core-layout-builder'] as $entity_type => $enabled) {
  foreach ($components as $bundle => $fields) {
    $config_name = $entity_type === 'paragraph' ? "paragraphs.paragraphs_type.$bundle" : "block_content.type.$bundle";
    $checks["$entity_type.$bundle"] = $storage->exists($config_name) === $enabled;
    if (!$enabled) {
      continue;
    }
    $form = $entities->getStorage('entity_form_display')->load("$entity_type.$bundle.default");
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type, $bundle);
    $actual = array_keys(array_filter($definitions, static fn($field) => $field instanceof FieldConfig));
    $expected = array_keys($fields);
    sort($actual);
    sort($expected);
    $checks["fields.$entity_type.$bundle"] = $actual === $expected;
    foreach ($fields as $name => [$field_type, $widget]) {
      $checks["widget.$entity_type.$bundle.$name"] = $definitions[$name]->getType() === $field_type && $form->getRenderer($name)->getPluginId() === $widget;
      if ($field_type === 'entity_reference') {
        $checks["media.$entity_type.$bundle.$name"] = $definitions[$name]->getSetting('target_type') === 'media' && $form->getComponent($name)['third_party_settings']['media_library_edit']['show_edit'];
      }
    }
    $groups = $form->getThirdPartySettings('field_group');
    $content_fields = array_values(array_intersect(['field_heading', 'field_text', 'field_media', 'field_video', 'field_image'], array_keys($fields)));
    $options = array_values(array_diff(array_keys($fields), $content_fields));
    $checks["groups.$entity_type.$bundle"] = $bundle === 'text' ? $groups === [] : $groups['group_tabs']['format_settings']['direction'] === 'horizontal' && $groups['group_content']['children'] === $content_fields && $groups['group_display_options']['children'] === $options;
    if ($entity_type === 'block_content') {
      $checks["inline.$bundle"] = \Drupal::service('plugin.manager.block')->hasDefinition("inline_block:$bundle");
    }
  }
}
foreach ($checks as $name => $passed) {
  if (!$passed) {
    throw new \RuntimeException("$case: $name failed");
  }
}
echo "$case composition verified.\n";
