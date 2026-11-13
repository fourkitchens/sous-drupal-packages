<?php

/**
 * @file
 * Verify Profile authoring, teasers, listing and Editor dashboard integration.
 */

use Drupal\Core\File\FileExists;
use Drupal\Core\Session\UserSession;
use Drupal\dashboard\Entity\Dashboard;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\workflows\Entity\Workflow;
use Drupal\views\Views;

$site_admin = Role::load('site_admin');
foreach (['create url aliases', 'access profile_content_browser entity browser pages', 'create profile content', 'edit any profile content', 'delete any profile content', 'view profile revisions', 'revert profile revisions', 'delete profile revisions'] as $permission) {
  if (!$site_admin->hasPermission($permission)) {
    throw new \RuntimeException("Site Admin is missing Profile permission: $permission.");
  }
}
$entities = \Drupal::entityTypeManager();
$storage = $entities->getStorage('node');
$display = $entities->getStorage('entity_form_display')->load('node.profile.default');
$definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'profile');
$file = NULL;
$media = NULL;
$node = NULL;
$listing_nodes = [];
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
  $dashboard = Dashboard::load('editor');
  $profile_components = [];
  foreach ($dashboard->getSections() as $delta => $section) {
    foreach ($section->getComponents() as $component) {
      if ($component->getUuid() === '97eca01d-afc7-48ad-bbf5-e5b7196de5d9' || $component->getPluginId() === 'views_block:dashboard_profiles-block_recent') {
        $profile_components[] = [$delta, $component];
      }
    }
  }
  if (count($profile_components) !== 1) {
    throw new \RuntimeException('Editor must contain exactly one Profile dashboard component.');
  }
  [$delta, $component] = $profile_components[0];
  $components = array_values($dashboard->getSection(3)->getComponentsByRegion('first'));
  $uuids = array_map(static fn($item) => $item->getUuid(), $components);
  if ($delta !== 3 || $component->getRegion() !== 'first' || $component->getUuid() !== '97eca01d-afc7-48ad-bbf5-e5b7196de5d9' || $component->getPluginId() !== 'views_block:dashboard_profiles-block_recent' || $components[0]->getPluginId() !== 'views_block:dashboard_editorial-block_recent' || array_search($component->getUuid(), $uuids, TRUE) < 1) {
    throw new \RuntimeException('Profile dashboard component must follow Recently updated in Editor section 3 first.');
  }
  foreach ([['uid' => 103, 'roles' => ['authenticated', 'site_admin']], ['uid' => 0, 'roles' => ['anonymous']]] as $values) {
    $account = new UserSession($values);
    $switcher->switchTo($account);
    try {
      $block = $component->getPlugin();
      $allowed = $account->isAuthenticated();
      if ($block->access($account) !== $allowed) {
        throw new \RuntimeException('Profile dashboard block must allow Site Admins and deny anonymous users.');
      }
      $build = $block->build();
      $output = (string) \Drupal::service('renderer')->renderInIsolation($build);
      if ($allowed ? !str_contains($output, $node->label()) : trim($output) !== '') {
        throw new \RuntimeException('Profile dashboard block must show the generated name only to its editorial audience.');
      }
    }
    finally {
      $switcher->switchBack();
    }
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

  $samples = [
    'zoe' => ['Zoe', 'Adams', 'Alpha mathematics research', 'Zoe listing teaser'],
    'bea' => ['Bea', 'Lovelace', 'Physics and instruments', 'Bea listing teaser'],
    'ada' => ['Ada', 'Lovelace', 'Shared laboratory history', 'Ada listing teaser'],
    'mira' => ['Mira', 'Zimmerman', 'Oceanography and ecology', 'Mira listing teaser'],
    'ada_second' => ['Ada', 'Lovelace', 'Botanical illustration', 'Another Ada listing teaser'],
  ];
  foreach ($samples as $key => [$first, $last, $description, $teaser_text]) {
    $listing_nodes[$key] = Node::create(['type' => 'profile', 'uid' => 1, 'moderation_state' => 'published', 'field_first_name' => $first, 'field_last_name' => $last, 'field_description' => ['value' => $description, 'format' => 'basic_html'], 'field_teaser_text' => $teaser_text]);
    $listing_nodes[$key]->save();
  }
  $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $listing_nodes['zoe']->id());
  if ($alias !== '/profile/zoe-adams') {
    throw new \RuntimeException("Profile alias did not use the generated name: $alias");
  }
  $listing_nodes['other'] = Node::create(['type' => 'news_post', 'uid' => 1, 'moderation_state' => 'published', 'title' => 'Unrelated listing content', 'field_teaser_text' => 'Not a Profile teaser']);
  $listing_nodes['other']->save();
  $all = ['zoe', 'ada', 'ada_second', 'bea', 'mira'];
  $searches = [
    [[], $all],
    [['profile_search' => 'Ada'], ['zoe', 'ada', 'ada_second']],
    [['profile_search' => 'velace'], ['ada', 'ada_second', 'bea']],
    [['profile_search' => 'ZOE'], ['zoe']],
    [['profile_search' => 'PHYS'], ['bea']],
    [['profile_search' => 'Shared laboratory'], ['ada']],
    [['profile_search' => 'Botanical'], ['ada_second']],
    [['profile_search' => 'Grace'], []],
    [['profile_search' => 'Unrelated'], []],
    [['profile_search' => 'No such profile'], []],
  ];
  foreach ($searches as [$input, $expected]) {
    $view = Views::getView('profiles');
    $view->setExposedInput($input);
    $view->execute('block_profiles');
    $actual = array_map(static fn($row) => (string) $row->_entity->id(), $view->result);
    $expected_ids = array_map(static fn($key) => (string) $listing_nodes[$key]->id(), $expected);
    if ($actual !== $expected_ids) {
      throw new \RuntimeException('Profiles listing or search failed for ' . json_encode($input) . ': ' . json_encode($actual));
    }
  }
  $view = Views::getView('profiles');
  $view->setDisplay('block_profiles');
  $view->initHandlers();
  if ($view->getPager()->getPluginId() !== 'full' || $view->getItemsPerPage() !== 24) {
    throw new \RuntimeException('Profiles did not configure a full twenty-four-item pager.');
  }
  $exposed = $view->display_handler->getPlugin('exposed_form')->renderExposedForm();
  $exposed_html = (string) \Drupal::service('renderer')->renderInIsolation($exposed);
  if (($exposed['profile_search']['#type'] ?? NULL) !== 'textfield' || !str_contains($exposed_html, 'name="profile_search"')) {
    throw new \RuntimeException('Profiles did not expose its name and Bio search input.');
  }

  $renderer = \Drupal::service('renderer');
  $view = Views::getView('profiles');
  $build = $view->buildRenderable('embed_profiles', [], FALSE);
  $html = (string) $renderer->renderInIsolation($build);
  if ($view->getPager()->getPluginId() !== 'some' || $view->getItemsPerPage() !== 12 || $view->display_handler->usesExposed() || $view->rowPlugin->getPluginId() !== 'fields' || $view->field['rendered_entity']->getPluginId() !== 'rendered_entity' || $view->field['rendered_entity']->options['view_mode'] !== 'teaser' || str_contains($html, 'laboratory') || str_contains($html, 'Profile teaser summary') || str_contains($html, 'Not a Profile teaser')) {
    throw new \RuntimeException('Profiles did not render a filter-free, published-only list of teaser entities.');
  }
  foreach ($samples as [, , , $teaser_text]) {
    if (!str_contains($html, $teaser_text)) {
      throw new \RuntimeException('Profiles did not render every published Profile teaser.');
    }
  }
  $view = Views::getView('profiles');
  $view->setItemsPerPage(2);
  $build = $view->buildRenderable('embed_profiles', [], FALSE);
  $html = (string) $renderer->renderInIsolation($build);
  if (count($view->result) !== 2 || $view->display_handler->isMoreEnabled()) {
    throw new \RuntimeException('Profiles did not honor the two-item limit or still has a More link.');
  }
}
finally {
  $storage->delete($listing_nodes);
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

print getenv('COMPOSITION') . " Profile fields, automatic names and aliases, Basic HTML Bio, searchable teasers and Editor dashboard block verified.\n";
