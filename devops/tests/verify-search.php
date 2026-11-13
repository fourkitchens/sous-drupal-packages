<?php

/**
 * @file
 * Verify anonymous search results in a block placed on an existing page.
 */

use Drupal\block\Entity\Block;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\node\Entity\Node;
use Drupal\search_api\Entity\Index;
use Drupal\user\Entity\User;
use Drupal\views\Views;
use Symfony\Component\HttpFoundation\Request;

$view = Views::getView('search');
$plugin = 'views_block:search-block_results';
if (!$view->setDisplay('block_results') || $view->display_handler->getPluginId() !== 'block' || $view->hasUrl() || !$view->display_handler->ajaxEnabled()) {
  throw new \RuntimeException('Search results must be an AJAX block without a route.');
}
if (Block::load('sous_search_form') || \Drupal::entityTypeManager()->getStorage('block')->loadByProperties(['plugin' => $plugin])) {
  throw new \RuntimeException('Search blocks should be unplaced by default.');
}
$theme = \Drupal::config('system.theme')->get('default');
$block = Block::create(['id' => 'sous_search_probe', 'theme' => $theme, 'region' => 'content', 'plugin' => $plugin]);

$index = Index::load('content');
if (!$index || !$index->status()) {
  throw new \RuntimeException('The Content search index is missing or disabled.');
}
$nodes = [];
$switcher = \Drupal::service('account_switcher');
try {
  $switcher->switchTo(User::load(1));
  try {
    foreach ([
      'published' => ['Meridian', 'Rivera', 'published', 'Published search fixture teaser.'],
      'draft' => ['Meridian', 'Ruiz', 'draft', 'Draft search fixture teaser.'],
      'unrelated' => ['Horizon', 'Patel', 'published', 'Unrelated search fixture teaser.'],
    ] as $key => [$first, $last, $state, $teaser]) {
      $node = Node::create([
        'type' => 'profile',
        'uid' => 1,
        'moderation_state' => $state,
        'field_first_name' => $first,
        'field_last_name' => $last,
        'field_teaser_text' => $teaser,
      ]);
      $node->save();
      $nodes[$key] = $node;
    }
    $index->indexItems();
    $block->save();
  }
  finally {
    $switcher->switchBack();
  }

  $switcher->switchTo(new AnonymousUserSession());
  try {
    $kernel = \Drupal::service('http_kernel');
    $path = $nodes['published']->toUrl()->toString();
    $results = $kernel->handle(Request::create($path, 'GET', ['keys' => 'Meridian']));
    $html = $results->getContent();
    if (!$results->isSuccessful() || !str_contains($html, 'Published search fixture teaser.') || str_contains($html, 'Draft search fixture teaser.') || str_contains($html, 'Unrelated search fixture teaser.')) {
      throw new \RuntimeException('Anonymous search must return the matching published Profile teaser only.');
    }

    $document = new \DOMDocument();
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new \DOMXPath($document);
    $form = $xpath->query('//*[@id="block-sous-search-probe"]//form[starts-with(@id, "views-exposed-form-search-block-results")]')->item(0);
    $option = $form ? $xpath->query('.//select[@name="type"]/option[@value="profile"]', $form)->item(0) : NULL;
    if (!$form || $form->getAttribute('action') !== $path || !$xpath->query('.//input[@name="keys"]', $form)->length || !$option || trim($option->textContent) !== 'Profile') {
      throw new \RuntimeException('The search block must expose keyword and Content type filters on its host page.');
    }
  }
  finally {
    $switcher->switchBack();
  }
}
finally {
  if (!$block->isNew()) {
    $block->delete();
  }
  \Drupal::entityTypeManager()->getStorage('node')->delete($nodes);
  $index->clear();
}

print getenv('COMPOSITION') . " search results block and Content type facet verified on an existing page.\n";
