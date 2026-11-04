<?php

/**
 * @file
 * Verify anonymous search results and the header search form.
 */

use Drupal\block\Entity\Block;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\node\Entity\Node;
use Drupal\search_api\Entity\Index;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;

$block = Block::load('sous_search_form');
$theme = \Drupal::config('system.theme')->get('default');
if (!$block || !$block->status() || $block->getTheme() !== $theme || $block->getRegion() !== 'header' || $block->getPluginId() !== 'views_exposed_filter_block:search-block_1' || $block->get('settings')['label_display'] !== '0') {
  throw new \RuntimeException('The default theme header is missing the search exposed form block.');
}

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
  }
  finally {
    $switcher->switchBack();
  }

  $switcher->switchTo(new AnonymousUserSession());
  try {
    $kernel = \Drupal::service('http_kernel');
    // Use a different route so a form pointing to the current page fails.
    $page = $kernel->handle(Request::create($nodes['published']->toUrl()->toString()));
    if (!$page->isSuccessful()) {
      throw new \RuntimeException('Could not load a public page to verify the header search form.');
    }
    $document = new \DOMDocument();
    $document->loadHTML($page->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new \DOMXPath($document);
    $form = $xpath->query('//*[@id="block-sous-search-form"]//form[@action="/search"]')->item(0);
    if (!$form || !$xpath->query('.//input[@name="keys"]', $form)->length || $xpath->query('.//*[@name="type" or starts-with(@name, "type[")]', $form)->length) {
      throw new \RuntimeException('The header search box must send only the keys filter to /search.');
    }

    $results = $kernel->handle(Request::create('/search', 'GET', ['keys' => 'Meridian']));
    $html = $results->getContent();
    if (!$results->isSuccessful() || !str_contains($html, 'Published search fixture teaser.') || str_contains($html, 'Draft search fixture teaser.') || str_contains($html, 'Unrelated search fixture teaser.')) {
      throw new \RuntimeException('Anonymous search must return the matching published Profile teaser only.');
    }

    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new \DOMXPath($document);
    $option = $xpath->query('//form[starts-with(@id, "views-exposed-form-search-page-1")]//select[@name="type"]/option[@value="profile"]')->item(0);
    if (!$option || trim($option->textContent) !== 'Profile') {
      throw new \RuntimeException('The search page must expose the Content type facet.');
    }
  }
  finally {
    $switcher->switchBack();
  }
}
finally {
  \Drupal::entityTypeManager()->getStorage('node')->delete($nodes);
  $index->clear();
}

print getenv('COMPOSITION') . " search results, Content type facet and header search form verified.\n";
