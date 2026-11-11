<?php

/**
 * @file
 * Verify native redirects, sitemap, tracking and the Missing pages dashboard.
 */

use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\UserSession;
use Drupal\dashboard\Entity\Dashboard;
use Drupal\node\Entity\Node;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\simple_sitemap\Queue\QueueWorker;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

$entities = \Drupal::entityTypeManager();
foreach (['redirect', 'redirect_404', 'easy_breadcrumb', 'google_tag', 'schema_metatag', 'simple_sitemap'] as $module) {
  if (!\Drupal::moduleHandler()->moduleExists($module)) {
    throw new \RuntimeException("SEO module $module is not enabled.");
  }
}
if (\Drupal::config('redirect_404.settings')->get('row_limit') !== 10000) {
  throw new \RuntimeException('Redirect 404 must retain its native ten-thousand-row limit.');
}
$matches = [];
foreach (Dashboard::loadMultiple() as $dashboard) {
  foreach ($dashboard->getSections() as $delta => $section) {
    foreach ($section->getComponents() as $component) {
      if ($component->getUuid() === '3a28906e-a6d0-4a86-a8b7-2e0580609cd3' || $component->getPluginId() === 'views_block:dashboard_missing_pages-block_top') {
        $matches[] = [$dashboard->id(), $delta, $component];
      }
    }
  }
}
if (count($matches) !== 1) {
  throw new \RuntimeException('Dashboards must contain exactly one Missing pages component.');
}
[$dashboard_id, $delta, $component] = $matches[0];
$components = array_values(Dashboard::load('site_admin')->getSection(1)->getComponentsByRegion('second'));
$uuids = array_map(static fn($item) => $item->getUuid(), $components);
if ($dashboard_id !== 'site_admin' || $delta !== 1 || $component->getRegion() !== 'second' || $component->getUuid() !== '3a28906e-a6d0-4a86-a8b7-2e0580609cd3' || $component->getPluginId() !== 'views_block:dashboard_missing_pages-block_top' || $components[0]->getPluginId() !== 'views_block:dashboard_sign_ins-block_recent' || array_search($component->getUuid(), $uuids, TRUE) < 1) {
  throw new \RuntimeException('Missing pages must follow Recent sign-ins in Site admin section 1 second.');
}
$attachments = [];
\Drupal::moduleHandler()->invoke('google_tag', 'page_attachments', [&$attachments]);
if ($entities->getStorage('google_tag_container')->loadMultiple() || \Drupal::service('google_tag.tag_container_resolver')->resolve() !== NULL || !empty($attachments['#attached'])) {
  throw new \RuntimeException('Google Tag ships tracking configuration or attachments.');
}
$generator = \Drupal::service('simple_sitemap.generator')->setSitemaps('default');
$sitemap = $generator->getDefaultSitemap();
if (!$sitemap || !$sitemap->isEnabled() || $sitemap->id() !== 'default') {
  throw new \RuntimeException('The native default sitemap is not available.');
}
$indexer = $generator->entityManager()->setSitemaps('default');
foreach ($entities->getStorage('node_type')->loadMultiple() as $type) {
  if ($indexer->bundleIsIndexed('node', $type->id())) {
    throw new \RuntimeException('SEO preselected a content bundle for sitemap indexing.');
  }
}
$settings_name = 'simple_sitemap.bundle_settings.default.node.profile';
$had_settings = !\Drupal::config($settings_name)->isNew();
$old_settings = $indexer->getBundleSettings('node', 'profile')['default'];
$nodes = [];
$aliases = [];
$not_found = \Drupal::service('redirect.not_found_storage');
$langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
$logged_paths = ['/sous-seo-missing-top' => 3, '/sous-seo-missing-other' => 1, '/sous-seo-resolved' => 4];
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo(User::load(1));
try {
  foreach ($logged_paths as $path => $count) {
    for ($i = 0; $i < $count; $i++) {
      $not_found->logRequest($path, $langcode);
    }
  }
  $not_found->resolveLogRequest('/sous-seo-resolved', $langcode);
  foreach ([['uid' => 105, 'roles' => ['authenticated', 'superuser']], ['uid' => 106, 'roles' => ['authenticated', 'site_editor']], ['uid' => 0, 'roles' => ['anonymous']]] as $values) {
    $account = new UserSession($values);
    $switcher->switchTo($account);
    try {
      $block = $component->getPlugin();
      $allowed = $account->hasRole('superuser');
      if ($block->access($account) !== $allowed) {
        throw new \RuntimeException('Missing pages must allow Superusers and deny Site Editors and anonymous users.');
      }
      $view = $block->getViewExecutable();
      $handler = $view->display_handler;
      $cache = $handler->getOption('cache');
      if ($cache['type'] !== 'time' || $cache['options']['results_lifespan'] !== 300 || $cache['options']['output_lifespan'] !== 300 || $handler->getOption('access')['options']['perm'] !== 'administer redirects') {
        throw new \RuntimeException('Missing pages requires redirect administration access and five-minute caching.');
      }
      // Run the query even if an earlier dashboard render cached an empty list.
      $handler->setOption('cache', ['type' => 'none', 'options' => []]);
      $build = $block->build();
      $output = (string) \Drupal::service('renderer')->renderInIsolation($build);
      if (!$allowed) {
        if (trim($output) !== '') {
          throw new \RuntimeException('Site Editors and anonymous users must receive no Missing pages output.');
        }
        continue;
      }
      $paths = array_map(static fn($row) => $view->field['path']->getValue($row), $view->result);
      $top = array_search('/sous-seo-missing-top', $paths, TRUE);
      $other = array_search('/sous-seo-missing-other', $paths, TRUE);
      if ($top === FALSE || $other === FALSE || $top >= $other || in_array('/sous-seo-resolved', $paths, TRUE) || !str_contains($output, '/sous-seo-missing-top') || !str_contains($output, '/sous-seo-missing-other') || str_contains($output, '/sous-seo-resolved') || array_keys($view->field) !== ['path', 'count', 'timestamp'] || $view->getPager()->getPluginId() !== 'some' || $view->getItemsPerPage() !== 10 || $handler->validate()) {
        throw new \RuntimeException('Missing pages must render unresolved paths only, highest count first, with Path, Count and Last seen.');
      }
      foreach ([$top, $other] as $index) {
        $row = $view->result[$index];
        if (trim(strip_tags((string) $view->field['count']->advancedRender($row))) !== (string) $logged_paths[$paths[$index]] || trim(strip_tags((string) $view->field['timestamp']->advancedRender($row))) !== \Drupal::service('date.formatter')->format(\Drupal::time()->getRequestTime(), 'short')) {
          throw new \RuntimeException('Missing pages did not preserve native request counts and last-seen timestamps.');
        }
      }
    }
    finally {
      $switcher->switchBack();
    }
  }
  foreach (['published', 'draft'] as $state) {
    $nodes[$state] = Node::create(['type' => 'profile', 'uid' => 1, 'moderation_state' => $state, 'field_first_name' => 'SEO', 'field_last_name' => ucfirst($state)]);
    if (count($violations = $nodes[$state]->validate())) {
      throw new \RuntimeException('SEO Profile fixture is invalid: ' . (string) $violations);
    }
    $nodes[$state]->save();
    $aliases[$state] = PathAlias::create(['path' => '/node/' . $nodes[$state]->id(), 'alias' => '/seo-' . $state, 'langcode' => $nodes[$state]->language()->getId()]);
    $aliases[$state]->save();
  }
  $aliases['published']->setAlias('/seo-current')->save();
  $path = '/node/' . $nodes['published']->id();
  \Drupal::service('path_alias.manager')->cacheClear($path);
  $redirect = \Drupal::service('redirect.repository')->findMatchingRedirect('/seo-published', [], $nodes['published']->language()->getId());
  if (!$redirect || (int) $redirect->getStatusCode() !== 301 || $redirect->getRedirect()['uri'] !== 'internal:' . $path || $redirect->getRedirectUrl()->toString() !== '/seo-current') {
    throw new \RuntimeException('Changing an alias did not preserve its old URL with a native permanent redirect.');
  }
  $route = \Drupal::service('router.route_provider')->getRouteByName('entity.node.canonical');
  $request = Request::create('/seo-current');
  $request->attributes->add(['_route' => 'entity.node.canonical', '_route_object' => $route, 'node' => $nodes['published']]);
  $request->attributes->set('_raw_variables', new ParameterBag(['node' => $nodes['published']->id()]));
  $requests = \Drupal::requestStack();
  $requests->push($request);
  try {
    $links = \Drupal::service('easy_breadcrumb.breadcrumb')->build(RouteMatch::createFromRequest($request))->getLinks();
    if (!$links || (string) end($links)->getText() !== $nodes['published']->label()) {
      throw new \RuntimeException('Easy Breadcrumb did not end with the current entity page title.');
    }
  }
  finally {
    $requests->pop();
  }

  // Index this fixture temporarily; content selection belongs to the site.
  $indexer->setBundleSettings('node', 'profile', ['index' => TRUE]);
  $generator->rebuildQueue()->generate(QueueWorker::GENERATE_TYPE_BACKEND);
  $xml = $sitemap->fromPublished()->toString();
  $document = new \DOMDocument();
  if (!$document->loadXML($xml)) {
    throw new \RuntimeException('Simple Sitemap did not generate valid published XML.');
  }
  $xpath = new \DOMXPath($document);
  $xpath->registerNamespace('sitemap', 'http://www.sitemaps.org/schemas/sitemap/0.9');
  $paths = [];
  foreach ($xpath->query('/sitemap:urlset/sitemap:url/sitemap:loc') as $location) {
    $paths[] = parse_url($location->textContent, PHP_URL_PATH);
  }
  if (!in_array('/seo-current', $paths, TRUE) || in_array('/seo-published', $paths, TRUE) || in_array('/seo-draft', $paths, TRUE) || in_array($path, $paths, TRUE) || \Drupal::service('simple_sitemap.queue_worker')->generationInProgress()) {
    throw new \RuntimeException('The generated sitemap did not select the published canonical alias and exclude old or Draft URLs.');
  }
}
finally {
  \Drupal::service('simple_sitemap.queue_worker')->deleteQueue();
  $sitemap->deleteContent();
  if ($had_settings) {
    $indexer->setBundleSettings('node', 'profile', $old_settings);
  }
  else {
    $indexer->removeBundleSettings('node', 'profile');
  }
  foreach ($nodes as $node) {
    if (!$node->isNew()) {
      $redirects = \Drupal::service('redirect.repository')->findByDestinationUri(['internal:/node/' . $node->id()]);
      $entities->getStorage('redirect')->delete($redirects);
    }
  }
  foreach ($aliases as $alias) {
    $alias->delete();
  }
  $entities->getStorage('node')->delete($nodes);
  \Drupal::database()->delete('redirect_404')->condition('path', array_keys($logged_paths), 'IN')->condition('langcode', $langcode)->execute();
  $switcher->switchBack();
}

print getenv('COMPOSITION') . " SEO redirects, current page titles, XML sitemap, inactive tracking and Missing pages dashboard verified.\n";
