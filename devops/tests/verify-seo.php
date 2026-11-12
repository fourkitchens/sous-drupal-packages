<?php

/**
 * @file
 * Verify native alias redirects, sitemap generation and inactive tracking.
 */

use Drupal\Core\Routing\RouteMatch;
use Drupal\node\Entity\Node;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\simple_sitemap\Queue\QueueWorker;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

$entities = \Drupal::entityTypeManager();
foreach (['redirect', 'easy_breadcrumb', 'google_tag', 'schema_metatag', 'simple_sitemap'] as $module) {
  if (!\Drupal::moduleHandler()->moduleExists($module)) {
    throw new \RuntimeException("SEO module $module is not enabled.");
  }
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
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo(User::load(1));
try {
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
  $switcher->switchBack();
}

print getenv('COMPOSITION') . " SEO redirects, current page titles, XML sitemap and inactive tracking verified.\n";
