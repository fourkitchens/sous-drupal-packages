<?php

/**
 * @file
 * Verify authoring forms, format processing, icons and account access natively.
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\Component\Utility\Html;
use Drupal\filter\Entity\FilterFormat;
use Drupal\layout_paragraphs\Controller\ChooseComponentController;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\node\Entity\Node;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;

$case = getenv('COMPOSITION');
$entities = \Drupal::entityTypeManager();
$modules = \Drupal::moduleHandler();
foreach (['no_nbsp', 'login_destination', 'metatag'] as $module) {
  if (!$modules->moduleExists($module)) {
    throw new \RuntimeException("$module is missing from the authoring baseline.");
  }
}
$users = [];
$node = NULL;
$alias = NULL;
$role = NULL;
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo(User::load(1));
try {
  $role = Role::create(['id' => 'authoring_probe', 'label' => 'Authoring probe']);
  $role->save();
  foreach (['authenticated' => [], 'custom' => [$role->id()], 'superuser' => ['superuser']] as $name => $roles) {
    $users[$name] = User::create(['name' => "authoring_$name", 'status' => 1, 'roles' => $roles]);
    $users[$name]->save();
    $destination = \Drupal::service('login_destination.manager')->findDestination('login', $users[$name]);
    if (!$destination || $destination->getDestination() !== 'internal:/admin/content') {
      throw new \RuntimeException("Login Destination does not cover $name users.");
    }
  }
  $bundles = array_keys($entities->getStorage('node_type')->loadMultiple());
  $config = \Drupal::service('config.storage');
  foreach (['field.field.node.page.field_tags', 'field.storage.node.field_tags', 'taxonomy.vocabulary.tags'] as $name) {
    if ($config->exists($name)) {
      throw new \RuntimeException("Page Tags config $name is still installed.");
    }
  }
  foreach ($bundles as $bundle) {
    $display = $entities->getStorage('entity_form_display')->load("node.$bundle.default");
    $widget = $display->getRenderer('field_metadata');
    $form = \Drupal::service('entity.form_builder')->getForm(Node::create(['type' => $bundle, 'uid' => 1, 'title' => 'Authoring probe']));
    if ($widget?->getPluginId() !== 'metatag_firehose' || !$widget->getSetting('sidebar') || ($form['field_metadata']['widget'][0]['#group'] ?? NULL) !== 'advanced' || ($form['field_metadata']['#access'] ?? TRUE) === FALSE) {
      throw new \RuntimeException("$bundle is missing its accessible Metatag sidebar widget.");
    }
    $groups = $display->getThirdPartySettings('field_group');
    $tabs = array_intersect_key($groups, array_flip($groups['group_main']['children']));
    uasort($tabs, static fn($a, $b) => $a['weight'] <=> $b['weight']);
    if (array_key_last($tabs) !== 'group_teaser' || $groups['group_teaser']['children'] !== ['field_teaser_image', 'field_teaser_text'] || ($form['#group_children']['field_teaser_image'] ?? NULL) !== 'group_teaser' || ($form['#group_children']['field_teaser_text'] ?? NULL) !== 'group_teaser') {
      throw new \RuntimeException("$bundle must end with the Teaser tab and its image and text fields.");
    }
    if ($bundle === 'page' && (isset($groups['group_taxonomy']) || isset($form['field_tags']))) {
      throw new \RuntimeException('Page still has a Taxonomy group or Tags widget.');
    }
    if (in_array($bundle, ['event', 'news_post'], TRUE)) {
      $field = $bundle === 'event' ? 'field_event_type' : 'field_news_categories';
      $organization = $groups['group_organization'];
      if ($groups['group_main']['format_settings']['direction'] !== 'horizontal' || !in_array('group_organization', $groups['group_main']['children'], TRUE) || $organization['format_type'] !== 'tab' || $organization['parent_name'] !== 'group_main' || $organization['children'] !== [$field] || in_array($field, $groups['group_content']['children'], TRUE) || ($form['#group_children'][$field] ?? NULL) !== 'group_organization' || !isset($form[$field])) {
        throw new \RuntimeException("$bundle taxonomy is not in the Organization tab.");
      }
    }
  }

  $plain = FilterFormat::load('plain_text');
  $enabled = array_keys(array_filter($plain->filters()->getAll(), static fn($filter) => $filter->status));
  sort($enabled);
  if ($enabled !== ['filter_autop', 'filter_html_escape', 'filter_url']) {
    throw new \RuntimeException('Plain text filters changed.');
  }
  $plain_output = (string) check_markup("<strong>Keep&nbsp;\u{00a0}</strong>", 'plain_text');
  if (!str_contains($plain_output, '&lt;strong&gt;Keep&amp;nbsp;') || !str_contains($plain_output, "\u{00a0}")) {
    throw new \RuntimeException('Plain text no longer preserves escaped markup and nonbreaking spaces.');
  }
  $formats = FilterFormat::loadMultiple(['basic_html', 'full_html', 'limited_html', 'restricted_html']);
  if ($case !== 'admin' && count($formats) !== 4) {
    throw new \RuntimeException('A provided HTML format is missing.');
  }
  if ($formats) {
    $node = Node::create(['type' => $bundles[0], 'uid' => 1, 'title' => 'Authoring filter target', 'moderation_state' => 'published']);
    $node->save();
    $alias = PathAlias::create(['path' => '/node/' . $node->id(), 'alias' => '/authoring-filter-target', 'langcode' => $node->language()->getId()]);
    $alias->save();
    \Drupal::service('path_alias.manager')->cacheClear('/node/' . $node->id());
    $source = '<p>Literal&nbsp;space and Unicode' . "\u{00a0}" . 'space <a href="/stale-target" data-entity-type="node" data-entity-uuid="' . $node->uuid() . '">linked content</a></p>';
    foreach ($formats as $id => $format) {
      $output = (string) check_markup($source, $id);
      $dom = Html::load($output);
      $links = $dom->getElementsByTagName('a');
      if (!$format->filters('filter_no_nbsp')->status || !$format->filters('linkit')->status || str_contains($output, '&nbsp;') || str_contains($output, "\u{00a0}") || !str_contains($dom->textContent, 'Literal space and Unicode space') || $links->length !== 1 || $links->item(0)->getAttribute('href') !== '/authoring-filter-target') {
        throw new \RuntimeException("$id failed nonbreaking-space removal or Linkit alias conversion.");
      }
    }
  }

  if ($case === 'paragraphs') {
    $chooser = ChooseComponentController::create(\Drupal::getContainer());
    $expected_order = ['text', 'text_with_media', 'image', 'video', 'gallery', 'quote', 'table', 'accordion', 'tabs', 'content_list', 'from_library', 'embed'];
    foreach (['page', 'frontpage', 'event', 'news_post'] as $bundle) {
      $host = Node::create(['type' => $bundle]);
      $layout = new LayoutParagraphsLayout($host->get('field_content'));
      $menu = $chooser->list(new Request(), $layout);
      if (array_keys($menu['#types']['content']) !== $expected_order || array_keys($menu['#types']['layout']) !== ['layout']) {
        throw new \RuntimeException("$bundle component chooser is out of order.");
      }
    }

    $display = $entities->getStorage('entity_form_display')->load('paragraph.accordion_item.default');
    $widget = $display->getRenderer('field_content');
    if (array_keys($widget->getAllowedTypes()) !== ['text', 'image', 'video', 'gallery']) {
      throw new \RuntimeException('Accordion Item paragraph component chooser is out of order.');
    }
    $tabs_item = $entities->getStorage('paragraph')->create(['type' => 'tabs_item']);
    $menu = $chooser->list(new Request(), new LayoutParagraphsLayout($tabs_item->get('field_content')));
    $tabs_order = ['text', 'text_with_media', 'image', 'video', 'gallery', 'quote', 'table', 'accordion', 'accordion_item', 'tabs', 'tabs_item', 'content_list', 'from_library', 'embed'];
    if (array_keys($menu['#types']['content']) !== $tabs_order) {
      throw new \RuntimeException('Tabs Item paragraph component chooser is out of order.');
    }

    foreach (['text', 'text_with_media', 'image', 'video', 'accordion', 'accordion_item', 'quote', 'embed', 'table', 'tabs', 'tabs_item', 'from_library', 'layout'] as $bundle) {
      $type = $entities->getStorage('paragraphs_type')->load($bundle);
      $icon = $type->getIconFile();
      $data = $type->get('icon_default');
      $bytes = str_starts_with($data, 'data:image/png;base64,') ? base64_decode(substr($data, strlen('data:image/png;base64,')), TRUE) : FALSE;
      if (!$icon || !$icon->isPermanent() || !$bytes || file_get_contents($icon->getFileUri()) !== $bytes || !str_starts_with($bytes, "\x89PNG\r\n\x1a\n") || !$type->getIconUrl()) {
        throw new \RuntimeException("$bundle has no permanent native PNG icon.");
      }
    }
  }
  elseif ($case === 'core-layout-builder') {
    $nested = [
      'accordion_item' => ['text', 'image', 'video', 'gallery'],
      'tabs_item' => ['text', 'text_with_media', 'image', 'video', 'gallery', 'quote', 'table', 'accordion', 'accordion_item', 'tabs', 'tabs_item', 'content_list', 'embed'],
    ];
    foreach ($nested as $bundle => $order) {
      $form = \Drupal::service('entity.form_builder')->getForm(BlockContent::create(['type' => $bundle]));
      $options = $form['field_content']['widget']['actions']['bundle']['#options'] ?? [];
      if (array_keys($options) !== $order) {
        throw new \RuntimeException("$bundle inline block component chooser is out of order.");
      }
    }
  }
}
finally {
  if ($alias) {
    $alias->delete();
  }
  if ($node && !$node->isNew()) {
    $node->delete();
  }
  foreach ($users as $user) {
    $user->delete();
  }
  if ($role) {
    $role->delete();
  }
  $switcher->switchBack();
}

print "$case authoring forms, text filters, paragraph icons, component order and account access verified.\n";
