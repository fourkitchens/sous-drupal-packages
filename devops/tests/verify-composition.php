<?php

/**
 * @file
 * Verify recipe compositions through Drupal's installed entities and plugins.
 */

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Session\UserSession;
use Drupal\field\Entity\FieldConfig;
use Drupal\user\Entity\Role;
use Drupal\views\Views;

$case = getenv('COMPOSITION');
$content = in_array($case, ['content-types', 'core-layout-builder', 'paragraphs'], TRUE);
$paragraphs = $case === 'paragraphs';
$modules = \Drupal::moduleHandler();
$storage = \Drupal::service('config.storage');
$entities = \Drupal::entityTypeManager();
$formats = $content || $storage->exists('node.type.profile');
$checks = [
  'admin theme' => \Drupal::config('system.theme')->get('admin') === 'gin',
  'default theme' => \Drupal::config('system.theme')->get('default') === 'emulsify',
  'DropzoneJS disabled' => !$modules->moduleExists('dropzonejs'),
];
$uploaded_video = $entities->getStorage('media_type')->load('uploaded_video');
$video_source = $uploaded_video?->getSource();
$video_field = $video_source?->getSourceFieldDefinition($uploaded_video);
$video_form = $entities->getStorage('entity_form_display')->load('media.uploaded_video.default');
$video_display = $entities->getStorage('entity_view_display')->load('media.uploaded_video.default');
$checks['uploaded video source'] = $video_source?->getPluginId() === 'video_file' && $video_field?->getName() === 'field_media_file' && $video_field->getSetting('file_extensions') === 'mp4 webm';
$checks['uploaded video widgets'] = $video_form?->getRenderer('field_media_file')?->getPluginId() === 'file_generic' && $video_display?->getRenderer('field_media_file')?->getPluginId() === 'file_video';

$breakpoints = \Drupal::service('breakpoint.manager')->getBreakpointsByGroup('emulsify');
foreach (['landscape', 'portrait', 'square'] as $aspect) {
  $id = "content_teaser_$aspect";
  $responsive = $entities->getStorage('responsive_image_style')->load($id);
  $media_display = $entities->getStorage('entity_view_display')->load("media.image.$id");
  $formatter = $media_display?->getRenderer('field_media_image');
  $checks["responsive formatter.$aspect"] = $formatter?->getPluginId() === 'responsive_image' && $formatter->getSetting('responsive_image_style') === $id && $responsive?->getFallbackImageStyle() === "{$id}_medium";
  foreach (['xsmall', 'small', 'medium'] as $size) {
    $style_id = "{$id}_$size";
    $style = $entities->getStorage('image_style')->load($style_id);
    $effects = $style ? array_values(iterator_to_array($style->getEffects())) : [];
    $checks["responsive style.$aspect.$size"] = isset($breakpoints["emulsify.$size"]) && ($responsive?->getImageStyleMapping("emulsify.$size", '1x')['image_mapping'] ?? NULL) === $style_id;
    $checks["image effects.$aspect.$size"] = count($effects) === 2 && $effects[0]->getPluginId() === 'focal_point_scale_and_crop' && $effects[1]->getPluginId() === 'image_convert' && $effects[1]->getConfiguration()['data']['extension'] === 'webp';
  }
}

foreach (['editoria11y', 'gin_login', 'twig_tweak', 'role_delegation'] as $module) {
  $checks["baseline.$module"] = $modules->moduleExists($module);
}

$editor = Role::load('site_editor');
$account = new UserSession(['uid' => 1234, 'roles' => ['authenticated', 'site_editor']]);
$checks['Site Editor role'] = $editor?->label() === 'Site Editor' && !$editor->isAdmin();
foreach (['administer modules', 'administer site configuration', 'administer users', 'administer permissions', 'administer content types', 'administer workflows', 'assign all roles', 'assign superuser role'] as $permission) {
  $checks["editor denied $permission"] = !$account->hasPermission($permission);
}
$delegation = \Drupal::service('delegatable_roles');
$superuser = new UserSession(['uid' => 1235, 'roles' => ['authenticated', 'superuser']]);
$checks['editor can only delegate Site Editor'] = array_keys($delegation->getAssignableRoles($account)) === ['site_editor'];
$checks['superuser can delegate all custom roles'] = array_keys($delegation->getAssignableRoles($superuser)) === array_keys($delegation->getAllRoles());
$checks['editor can use the Roles tab'] = \Drupal::service('access_check.role_delegation')->access($account)->isAllowed();
foreach (['archive_unpublish', 'create_new_draft', 'publish'] as $transition) {
  $checks["editor transition.$transition"] = $account->hasPermission("use editorial transition $transition");
}
foreach (['node', 'media', 'taxonomy_term', 'block_content'] as $entity_type) {
  $access = $entities->getAccessControlHandler($entity_type);
  $entity_storage = $entities->getStorage($entity_type);
  foreach (array_keys(\Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type)) as $bundle) {
    $entity = $entity_storage->create([$entities->getDefinition($entity_type)->getKey('bundle') => $bundle]);
    $checks["editor create.$entity_type.$bundle"] = $access->createAccess($bundle, $account);
    foreach (['update', 'delete'] as $operation) {
      $checks["editor $operation.$entity_type.$bundle"] = $entity->access($operation, $account);
    }
  }
}
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
foreach (['linkit' => $formats, 'emulsify_tools' => TRUE, 'ckeditor5' => $formats, 'paragraphs' => $paragraphs, 'config_split' => TRUE] as $module => $enabled) {
  $checks[$module] = $modules->moduleExists($module) === $enabled;
}
foreach (['layout_paragraphs', 'layout_paragraphs_library', 'layout_paragraphs_permissions', 'layout_paragraphs_limit', 'paragraphs_type_permissions'] as $module) {
  $checks[$module] = $modules->moduleExists($module) === $paragraphs;
}
foreach (['tablefield' => $paragraphs || $case === 'core-layout-builder', 'paragraphs_features' => $paragraphs, 'inline_entity_form' => $case === 'core-layout-builder'] as $module => $enabled) {
  $checks[$module] = $modules->moduleExists($module) === $enabled;
}
$browsers = [
  'event' => ['event_content_browser', 'events', 'entity_browser'],
  'news_post' => ['news_content_browser', 'news', 'entity_browser'],
  'profile' => ['profile_content_browser', 'profiles', 'entity_browser'],
];
if ($content) {
  $browsers['page'] = ['page_content_browser', 'pages', 'entity_browser_page'];
  $browsers['frontpage'] = ['frontpage_content_browser', 'pages', 'entity_browser_frontpage'];
  $checks['Pages/Frontpage view'] = $entities->getStorage('view')->load('pages')?->label() === 'Pages/Frontpage'
    && !$storage->exists('views.view.page_browsers');
}
$browsers = array_intersect_key($browsers, $entities->getStorage('node_type')->loadMultiple());
$checks['entity_browser'] = $modules->moduleExists('entity_browser') === ($browsers !== [] || $paragraphs);
foreach ($browsers as $bundle => [$browser_id, $view_id, $display_id]) {
  $modes = \Drupal::service('entity_display.repository')->getViewModeOptionsByBundle('node', $bundle);
  $checks["view modes.$bundle"] = ($modes['full'] ?? NULL) === 'Full'
    && ($modes['teaser'] ?? NULL) === 'Teaser'
    && ($modes['admin_preview'] ?? NULL) === 'Admin Preview';
  $displays = $entities->getStorage('entity_view_display');
  $node = $entities->getStorage('node')->create(['type' => $bundle]);
  $full = EntityViewDisplay::collectRenderDisplay($node, 'full');
  $default = $displays->load("node.$bundle.default");
  $checks["full display.$bundle"] = $full->getMode() === 'full'
    && $full->getComponents() === $default->getComponents()
    && $full->getThirdPartySetting('layout_builder', 'allow_custom') === $default->getThirdPartySetting('layout_builder', 'allow_custom');
  $teaser = $displays->load("node.$bundle.teaser");
  $checks["teaser image.$bundle"] = $teaser?->getRenderer('field_teaser_image')?->getSetting('view_mode') === 'content_teaser_landscape';
  $browser = $entities->getStorage('entity_browser')->load($browser_id);
  $widgets = $browser?->getWidgets()->getConfiguration() ?? [];
  $widget = reset($widgets);
  $checks["browser.$bundle"] = $browser?->getDisplay()->getPluginId() === 'modal'
    && ($widget['settings']['view'] ?? NULL) === $view_id
    && ($widget['settings']['view_display'] ?? NULL) === $display_id;
  $view = Views::getView($view_id);
  $checks["browser display.$bundle"] = $view?->setDisplay($display_id) && $view->display_handler->getPluginId() === 'entity_browser';
  if (!$checks["browser display.$bundle"]) {
    continue;
  }
  $view->initHandlers();
  $checks["browser preview.$bundle"] = array_keys($view->field) === ['entity_browser_select', 'rendered_entity']
    && $view->field['rendered_entity']->getPluginId() === 'rendered_entity'
    && $view->field['rendered_entity']->options['view_mode'] === 'admin_preview';
  $checks["browser selection.$bundle"] = ($view->field['entity_browser_select'] ?? NULL)?->getPluginId() === 'entity_browser_select'
    && ($view->filter['type']->options['value'] ?? NULL) === [$bundle => $bundle]
    && ($view->filter['status']->options['value'] ?? NULL) === '1'
    && ($view->filter['title'] ?? NULL)?->isExposed();
  $checks["browser access.$bundle"] = $view->access($display_id, $account) && !$view->access($display_id, new UserSession());
}
foreach (['devel', 'field_ui', 'masquerade', 'stage_file_proxy', 'syslog', 'views_ui'] as $module) {
  $checks["production.$module"] = !$modules->moduleExists($module);
}
foreach (['local', 'staging'] as $id) {
  $name = "config_split.config_split.$id";
  $checks[$name] = $storage->exists($name) && \Drupal::config($name)->get('status') === FALSE;
}
if ($paragraphs) {
  $widget_settings = \Drupal::config('layout_paragraphs.widget_settings');
  $checks['component menu'] = $widget_settings->get('display_mode') === 'grid' && $widget_settings->get('show_search') && $widget_settings->get('show_icons') && $widget_settings->get('show_description');
  $modal = \Drupal::config('layout_paragraphs.modal_settings');
  $checks['component modal'] = $modal->get('width') === '80%' && $modal->get('height') === '80%' && $modal->get('autoresize');
  $checks['component menu patch'] = class_exists(\Drupal\layout_paragraphs\Form\LayoutParagraphsWidgetSettingsForm::class);
  foreach (['anonymous', 'authenticated'] as $role_id) {
    foreach (['text', 'text_with_media', 'video', 'image', 'accordion', 'accordion_item', 'quote', 'embed', 'table', 'tabs', 'tabs_item', 'from_library', 'layout'] as $bundle) {
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

// Exercise nested editing and rendering, including the patched rich-text table.
$extra_components = [
  'accordion' => $common + ['field_content' => ['nested', 'paragraphs']],
  'accordion_item' => ['field_heading' => ['string', 'string_textfield'], 'field_label' => ['string', 'string_textfield'], 'field_content' => ['nested', 'paragraphs']],
  'quote' => ['field_text' => ['text_long', 'text_textarea'], 'field_label' => ['string', 'string_textfield'], 'field_image' => ['entity_reference', 'media_library_widget']],
  'embed' => $common + ['field_embed_code' => ['string_long', 'string_textarea'], 'field_show_texture' => ['boolean', 'boolean_checkbox'], 'field_style_layout_width' => ['style_selector_css_class', 'style_selector_tile_widget']],
  'table' => $common + ['field_label' => ['string', 'string_textfield'], 'field_table' => ['tablefield', 'tablefield']],
  'tabs' => $common + ['field_style_layout_width' => ['style_selector_css_class', 'style_selector_tile_widget'], 'field_content' => ['nested', 'paragraphs']],
  'tabs_item' => ['field_label' => ['string', 'string_textfield'], 'field_content' => ['nested', 'layout_paragraphs']],
];
foreach (['paragraph' => $paragraphs, 'block_content' => $case === 'core-layout-builder'] as $entity_type => $enabled) {
  foreach ($extra_components as $bundle => $fields) {
    $name = $entity_type === 'paragraph' ? "paragraphs.paragraphs_type.$bundle" : "block_content.type.$bundle";
    $checks["$entity_type.$bundle"] = $storage->exists($name) === $enabled;
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
      if ($field_type === 'nested') {
        $field_type = 'entity_reference_revisions';
        $widget = $entity_type === 'paragraph' ? $widget : 'inline_entity_form_complex';
        $checks["target.$entity_type.$bundle"] = $definitions[$name]->getSetting('target_type') === $entity_type;
      }
      $checks["widget.$entity_type.$bundle.$name"] = $definitions[$name]->getType() === $field_type && $form->getRenderer($name)->getPluginId() === $widget;
    }
    $groups = $form->getThirdPartySettings('field_group');
    $expected_groups = match ($bundle) {
      'accordion_item' => [['field_heading', 'field_content'], ['field_label']],
      'embed' => [['field_heading', 'field_text', 'field_embed_code'], ['field_show_texture', 'field_style_layout_width']],
      'tabs' => [['field_heading', 'field_text', 'field_content'], ['field_style_layout_width']],
      default => [],
    };
    $checks["groups.$entity_type.$bundle"] = !$expected_groups ? $groups === [] : $groups['group_tabs']['format_settings']['direction'] === 'horizontal' && $groups['group_content']['children'] === $expected_groups[0] && $groups['group_display_options']['children'] === $expected_groups[1];
    if ($entity_type === 'block_content') {
      $checks["inline.$bundle"] = \Drupal::service('plugin.manager.block')->hasDefinition("inline_block:$bundle");
    }
  }
  if (!$enabled) {
    continue;
  }
  foreach (['accordion' => ['accordion_item'], 'accordion_item' => ['image', 'text', 'video'], 'tabs' => ['tabs_item'], 'tabs_item' => array_merge(array_keys($components), array_keys($extra_components), $paragraphs ? ['from_library'] : [])] as $bundle => $allowed) {
    $actual = array_values(FieldConfig::loadByName($entity_type, $bundle, 'field_content')->getSetting('handler_settings')['target_bundles']);
    sort($actual);
    sort($allowed);
    $checks["children.$entity_type.$bundle"] = $actual === $allowed;
  }
  $table = $entities->getStorage($entity_type)->create(['type' => 'table', 'info' => 'Table']);
  $defaults = $table->get('field_table')->getValue()[0];
  $checks["table defaults.$entity_type"] = (int) $defaults['rebuild']['rows'] === 2 && (int) $defaults['rebuild']['cols'] === 2 && $defaults['value'][0][0]['format'] === 'limited_html';
  $table_form = \Drupal::service('entity.form_builder')->getForm($table);
  $checks["table form.$entity_type"] = isset($table_form['field_table']['widget'][0]);
  $defaults['value'][0][0]['value'] = "Table cell $entity_type content";
  $defaults['caption'] = 'Table caption';
  $table->set('field_table', [$defaults]);
  $table->save();
  $table = $entities->getStorage($entity_type)->loadUnchanged($table->id());
  $build = $entities->getViewBuilder($entity_type)->view($table);
  $checks["table render.$entity_type"] = str_contains((string) \Drupal::service('renderer')->renderInIsolation($build), "Table cell $entity_type content");
  foreach (['accordion' => 'accordion_item', 'tabs' => 'tabs_item'] as $parent_bundle => $item_bundle) {
    $marker = "Nested $entity_type $parent_bundle content";
    $child = $entities->getStorage($entity_type)->create(['type' => 'text', 'info' => 'Text', 'field_text' => ['value' => $marker, 'format' => 'limited_html']]);
    $child->save();
    $item = $entities->getStorage($entity_type)->create(['type' => $item_bundle, 'info' => 'Item', 'field_label' => 'Item', 'field_content' => ['entity' => $child]]);
    if ($item->hasField('field_heading')) {
      $item->set('field_heading', 'Item');
    }
    $item->save();
    $parent = $entities->getStorage($entity_type)->create(['type' => $parent_bundle, 'info' => 'Container', 'field_content' => ['entity' => $item]]);
    $parent->save();
    $parent_form = \Drupal::service('entity.form_builder')->getForm($parent);
    $checks["nested form.$entity_type.$parent_bundle"] = isset($parent_form['field_content']['widget']);
    $child->setNewRevision(TRUE);
    $child->set('field_text', ['value' => $marker . ' revised', 'format' => 'limited_html']);
    $child->save();
    $parent = $entities->getStorage($entity_type)->loadUnchanged($parent->id());
    $build = $entities->getViewBuilder($entity_type)->view($parent);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $checks["nested render.$entity_type.$parent_bundle"] = str_contains($html, $marker) && !str_contains($html, $marker . ' revised');
  }
}
if ($paragraphs) {
  $access_manager = \Drupal::service('access_manager');
  $library_items = $entities->getStorage('paragraphs_library_item');
  $library_item = $library_items->create(['label' => 'Site Editor access check']);
  $checks['editor library item create'] = $entities->getAccessControlHandler('paragraphs_library_item')->createAccess(NULL, $account);
  $checks['editor library item update'] = $library_item->access('update', $account);
  $checks['editor library item delete'] = $library_item->access('delete', $account);
  $checks['editor library settings denied'] = !$access_manager->checkNamedRoute('paragraphs_library_item.settings', [], $account);
  $checks['superuser library settings allowed'] = $access_manager->checkNamedRoute('paragraphs_library_item.settings', [], $superuser);
  $library_form = $entities->getStorage('entity_form_display')->load('paragraph.from_library.default');
  $checks['library browser'] = $library_form->getRenderer('field_reusable_paragraph')->getPluginId() === 'entity_browser_entity_reference' && $library_form->getRenderer('field_reusable_paragraph')->getSetting('entity_browser') === 'component_library' && $entities->getStorage('entity_browser')->load('component_library')->getDisplay()->getPluginId() === 'modal';
  $layout = $entities->getStorage('paragraphs_type')->load('layout');
  $layouts = array_keys($layout->getBehaviorPlugin('layout_paragraphs')->getConfiguration()['available_layouts']);
  $checks['layout choices'] = $layouts === ['layout_onecol', 'layout_twocol_section', 'layout_threecol_section', 'layout_fourcol_section'];
  $checks['layout fields'] = array_filter(\Drupal::service('entity_field.manager')->getFieldDefinitions('paragraph', 'layout'), static fn($field) => $field instanceof FieldConfig) === [];
  foreach ($layouts as $layout_id) {
    $checks["layout plugin.$layout_id"] = \Drupal::service('plugin.manager.core.layout')->hasDefinition($layout_id);
  }
  foreach (['page', 'frontpage'] as $bundle) {
    $checks["layout allowed.$bundle"] = in_array('layout', FieldConfig::loadByName('node', $bundle, 'field_content')->getSetting('handler_settings')['target_bundles'], TRUE);
  }
}
foreach ($checks as $name => $passed) {
  if (!$passed) {
    throw new \RuntimeException("$case: $name failed");
  }
}
echo "$case composition verified.\n";
