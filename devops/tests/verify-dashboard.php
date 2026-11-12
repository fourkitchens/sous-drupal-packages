<?php

/**
 * @file
 * Verify Dashboard layouts, role access, navigation and native sign-in redirects.
 */

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Session\UserSession;
use Drupal\dashboard\Entity\Dashboard;
use Drupal\filter\Entity\FilterFormat;
use Drupal\system\Entity\Menu;
use Drupal\views\Views;
use Symfony\Component\HttpFoundation\Request;

foreach (['dashboard', 'dblog', 'editoria11y', 'login_history', 'sous_component_support'] as $module) {
  if (!\Drupal::moduleHandler()->moduleExists($module)) {
    throw new \RuntimeException("Dashboard module $module is not enabled.");
  }
}
if (\Drupal::moduleHandler()->moduleExists('login_destination') || Views::getView('dashboard_content') || !FilterFormat::load('basic_html')) {
  throw new \RuntimeException('Dashboard requires Basic HTML without Login Destination or the default content View.');
}
$dashboards = Dashboard::loadMultiple(['site_admin', 'editor']);
$layouts = [
  'site_admin' => [
    ['layout_onecol', 'Status', NULL, ['content' => ['dashboard_site_status']]],
    ['layout_twocol_section', 'Activity', '50-50', ['first' => ['views_block:dashboard_log-block_recent', 'views_block:dashboard_accounts-block_new'], 'second' => ['views_block:dashboard_sign_ins-block_recent']]],
    ['layout_twocol_section', 'Top errors', '50-50', ['first' => ['views_block:dashboard_top_errors-block_page_not_found'], 'second' => ['views_block:dashboard_top_errors-block_access_denied']]],
  ],
  'editor' => [
    ['layout_twocol_section', 'Start', '67-33', ['first' => ['dashboard_text_block'], 'second' => ['system_menu_block:dashboard-create']]],
    ['layout_twocol_section', 'Your work', '50-50', ['first' => ['views_block:dashboard_editorial-block_drafts'], 'second' => ['views_block:dashboard_editorial-block_scheduled']]],
    ['layout_twocol_section', 'Site content', '50-50', ['first' => ['views_block:dashboard_editorial-block_recent'], 'second' => ['views_block:dashboard_accessibility-block_alerts']]],
  ],
];
foreach (['site_admin' => ['Site admin', -10, 'Site health and accounts for Superusers.'], 'editor' => ['Editor', 0, 'Content work for Site Editors.']] as $id => [$label, $weight, $description]) {
  $dashboard = $dashboards[$id] ?? NULL;
  if (!$dashboard || !$dashboard->status() || $dashboard->label() !== $label || $dashboard->getWeight() !== $weight || $dashboard->get('description') !== $description) {
    throw new \RuntimeException("Dashboard $id is missing its enabled baseline metadata.");
  }
  foreach ($layouts[$id] as $delta => [$layout, $label, $widths, $regions]) {
    $section = $dashboard->getSection($delta);
    $settings = $section->getLayoutSettings();
    if ($section->getLayoutId() !== $layout || $settings['label'] !== $label || ($widths && $settings['column_widths'] !== $widths)) {
      throw new \RuntimeException("Dashboard $id section $delta has changed its baseline layout.");
    }
    foreach ($regions as $region => $expected) {
      $components = array_values($section->getComponentsByRegion($region));
      $plugins = array_map(static fn($component) => $component->getPluginId(), $components);
      if (array_slice($plugins, 0, count($expected)) !== $expected) {
        throw new \RuntimeException("Dashboard $id section $delta region $region has changed its baseline blocks.");
      }
    }
  }
}
$start = $dashboards['editor']->getSection(0);
$welcome = array_values($start->getComponentsByRegion('first'))[0]->getPlugin()->getConfiguration();
$menu = array_values($start->getComponentsByRegion('second'))[0]->getPlugin()->getConfiguration();
if ($welcome['text']['format'] !== 'basic_html' || !$welcome['text']['value'] || $menu['label'] !== 'Create' || !Menu::load('dashboard-create') || $menu['level'] !== 1 || $menu['depth'] !== 2 || $menu['expand_all_items'] !== TRUE) {
  throw new \RuntimeException('Editor welcome text or Create menu settings have changed.');
}
$navigation = [];
foreach (\Drupal::config('navigation.block_layout')->get('sections') as $section) {
  foreach ($section['components'] as $component) {
    if ($component['configuration']['id'] === 'navigation_dashboard') {
      $navigation[] = $component;
    }
  }
}
if (count($navigation) !== 1) {
  throw new \RuntimeException('Navigation must contain exactly one native Dashboard block.');
}
$links = [
  ['dashboard_editorial', 'block_drafts', '/admin/content/moderated?moderation_state=editorial-draft'],
  ['dashboard_editorial', 'block_scheduled', '/admin/content/scheduled'],
  ['dashboard_editorial', 'block_recent', '/admin/content'],
  ['dashboard_log', 'block_recent', '/admin/reports/dblog'],
  ['dashboard_top_errors', 'block_page_not_found', '/admin/reports/page-not-found'],
  ['dashboard_top_errors', 'block_access_denied', '/admin/reports/access-denied'],
  ['dashboard_sign_ins', 'block_recent', '/admin/reports/login-history'],
  ['dashboard_accounts', 'block_new', '/admin/people'],
  ['dashboard_accessibility', 'block_alerts', NULL],
];
foreach ($links as [$id, $display, $path]) {
  $view = Views::getView($id);
  if (!$view || !$view->setDisplay($display)) {
    throw new \RuntimeException("Dashboard View $id display $display is missing.");
  }
  $handler = $view->display_handler;
  if ($path ? (!$handler->isMoreEnabled() || $handler->getOption('link_display') !== 'custom_url' || '/' . ltrim($handler->getOption('link_url'), '/') !== $path || !\Drupal::pathValidator()->getUrlIfValidWithoutAccessCheck($path)) : $handler->isMoreEnabled()) {
    throw new \RuntimeException("Dashboard View $id display $display has an invalid more link.");
  }
}
$switcher = \Drupal::service('account_switcher');
$requests = \Drupal::requestStack();
$dashboard_url = \Drupal::urlGenerator()->generateFromRoute('dashboard');
$fixtures = ['page not found' => ['block_page_not_found', '/sous-verify-missing'], 'access denied' => ['block_access_denied', '/sous-verify-denied']];
$wids = [];
try {
  foreach ($fixtures as $channel => [$display, $path]) {
    $request = Request::create($path);
    $previous_wid = \Drupal::database()->query('SELECT COALESCE(MAX(wid), 0) FROM {watchdog}')->fetchField();
    $requests->push($request);
    try {
      \Drupal::logger($channel)->warning('@uri', ['@uri' => $path]);
    }
    finally {
      $requests->pop();
      $wids = array_merge($wids, \Drupal::database()->select('watchdog', 'w')->fields('w', ['wid'])->condition('wid', $previous_wid, '>')->condition('type', $channel)->condition('location', $request->getUri())->execute()->fetchCol());
    }
  }
  foreach (['superuser' => 'site_admin', 'site_editor' => 'editor', 'authenticated' => NULL] as $role => $default) {
    $account = new UserSession(['uid' => 100 + array_search($role, ['superuser', 'site_editor', 'authenticated'], TRUE), 'roles' => array_unique(['authenticated', $role])]);
    $switcher->switchTo($account);
    try {
      if (\Drupal::service('dashboard.manager')->getDefaultDashboard()?->id() !== $default || ($role === 'site_editor' && $account->hasPermission('administer dashboard'))) {
        throw new \RuntimeException("The $role dashboard selection or administration permission is incorrect.");
      }
      foreach ($dashboards as $id => $dashboard) {
        $allowed = $role === 'superuser' || ($role === 'site_editor' && $id === 'editor');
        if ($dashboard->access('view', $account) !== $allowed || \Drupal::service('access_manager')->checkNamedRoute('entity.dashboard.canonical', ['dashboard' => $id], $account) !== $allowed) {
          throw new \RuntimeException("The $role account has incorrect access to $id.");
        }
      }
      $menu_tree = \Drupal::menuTree();
      $tree = $menu_tree->load('dashboard-create', (new MenuTreeParameters())->onlyEnabledLinks()->setMaxDepth(2));
      $tree = $menu_tree->transform($tree, [['callable' => 'menu.default_tree_manipulators:checkAccess'], ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort']]);
      $actual = [];
      foreach ($tree as $item) {
        if ($item->subtree || in_array($item->link->getRouteName(), ['entity.node.add_page', 'entity.media.add_page'], TRUE)) {
          throw new \RuntimeException('Create must list types directly without Content or Media groups.');
        }
        if ($item->access->isAllowed()) {
          $actual[] = [(string) $item->link->getTitle(), $item->link->getRouteName(), $item->link->getRouteParameters()];
        }
      }
      $expected = [];
      $entities = \Drupal::entityTypeManager();
      foreach ([['node', 'node_type', 'entity.node.add_form'], ['media', 'media_type', 'entity.media.add_form']] as [$entity_type, $bundle_type, $route]) {
        $types = $entities->getStorage($bundle_type)->loadMultiple();
        uasort($types, [$entities->getDefinition($bundle_type)->getClass(), 'sort']);
        foreach ($types as $bundle => $type) {
          if ($role === 'superuser' || ($role === 'site_editor' && $entities->getAccessControlHandler($entity_type)->createAccess($bundle, $account))) {
            $expected[] = [(string) $type->label(), $route, [$bundle_type => $bundle]];
          }
        }
      }
      if ($actual !== $expected) {
        throw new \RuntimeException("The $role Create menu must list only accessible content and media types in label order.");
      }
      if ($role === 'superuser') {
        foreach ($links as [$id, $display]) {
          $view = Views::getView($id);
          $view->setDisplay($display);
          $handler = $view->display_handler;
          $pager = $handler->getOption('pager');
          $cache = $handler->getOption('cache');
          $timed = in_array($id, ['dashboard_log', 'dashboard_top_errors', 'dashboard_sign_ins', 'dashboard_accessibility'], TRUE);
          if (!$view->access($display, $account) || $handler->validate() || $pager['type'] !== 'some' || $pager['options']['items_per_page'] !== 10 || $cache['type'] !== ($timed ? 'time' : 'tag') || ($timed && ($cache['options']['results_lifespan'] !== 300 || $cache['options']['output_lifespan'] !== 300))) {
            throw new \RuntimeException("Dashboard View $id display $display has invalid plugins, access, limits or caching.");
          }
          // Always run the query, including on repeated verification runs.
          $handler->setOption('cache', ['type' => 'none', 'options' => []]);
          $build = $view->executeDisplay($display);
          $output = (string) \Drupal::service('renderer')->renderInIsolation($build);
          if (!$view->executed || count($view->result) > 10 || !$output) {
            throw new \RuntimeException("Dashboard View $id display $display failed to execute and render.");
          }
          foreach ($fixtures as [$fixture_display, $path]) {
            if ($id === 'dashboard_log' && str_contains($output, $path)) {
              throw new \RuntimeException('Recent errors and warnings must exclude page-not-found and access-denied logs.');
            }
            if ($id === 'dashboard_top_errors' && $display === $fixture_display) {
              $location = Request::create($path)->getUri();
              $matches = array_filter($view->result, static fn($row) => $view->field['location']->getValue($row) === $location && (int) $view->field['wid']->getValue($row) >= 1);
              if (!$matches || !str_contains($output, $location)) {
                throw new \RuntimeException("Dashboard display $display must render its logged URL and count.");
              }
            }
          }
        }
      }
      if ($role === 'site_editor') {
        foreach ($fixtures as [$display]) {
          if (Views::getView('dashboard_top_errors')->access($display, $account)) {
            throw new \RuntimeException("Site Editors must not access Dashboard display $display.");
          }
        }
      }
      foreach ([NULL, '/admin/content'] as $destination) {
        $request = Request::create('/user/login', 'GET', $destination ? ['destination' => $destination] : []);
        $requests->push($request);
        try {
          \Drupal::service('dashboard.user_redirect')->userLogin($account);
          $expected = $destination ?? ($default ? $dashboard_url : NULL);
          if ($request->query->get('destination') !== $expected) {
            throw new \RuntimeException("The $role sign-in redirect ignored dashboard access or the existing destination.");
          }
        }
        finally {
          $requests->pop();
        }
      }
    }
    finally {
      $switcher->switchBack();
    }
  }
}
finally {
  if ($wids) {
    \Drupal::database()->delete('watchdog')->condition('wid', $wids, 'IN')->execute();
  }
}

print getenv('COMPOSITION') . " Dashboard layouts, roles, navigation, links and sign-in redirects verified.\n";
