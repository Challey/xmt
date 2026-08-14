<?php

namespace Drupal\xmt_trust_ui\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;

/**
 * Builds an XML sitemap for trusted content pages and feeds.
 */
class TrustSitemapBuilder {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Builds sitemap XML for trust hub pages, publishers, and recent articles.
   */
  public function buildXml(int $article_limit = 100): string {
    $entries = array_merge(
      $this->staticPages(),
      $this->publisherPages(),
      $this->recentArticles($article_limit),
    );

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($entries as $entry) {
      $xml .= "  <url>\n";
      $xml .= '    <loc>' . $this->xml($entry['loc']) . "</loc>\n";
      if (!empty($entry['lastmod'])) {
        $xml .= '    <lastmod>' . $this->xml($entry['lastmod']) . "</lastmod>\n";
      }
      if (!empty($entry['changefreq'])) {
        $xml .= '    <changefreq>' . $this->xml($entry['changefreq']) . "</changefreq>\n";
      }
      if (isset($entry['priority'])) {
        $xml .= '    <priority>' . $this->xml((string) $entry['priority']) . "</priority>\n";
      }
      $xml .= "  </url>\n";
    }
    $xml .= "</urlset>\n";
    return $xml;
  }

  /**
   * Static trust hub routes.
   *
   * @return array<int, array<string, string|float>>
   */
  protected function staticPages(): array {
    $routes = [
      ['route' => 'xmt_trust_ui.feed', 'priority' => '0.9', 'changefreq' => 'hourly'],
      ['route' => 'xmt_trust_ui.feed_official', 'priority' => '0.8', 'changefreq' => 'hourly'],
      ['route' => 'xmt_trust_ui.feed_enterprise', 'priority' => '0.8', 'changefreq' => 'hourly'],
      ['route' => 'xmt_trust_ui.feed_aggregate', 'priority' => '0.7', 'changefreq' => 'hourly'],
      ['route' => 'xmt_trust_ui.publishers_directory', 'priority' => '0.8', 'changefreq' => 'daily'],
      ['route' => 'xmt_publisher.apply', 'priority' => '0.5', 'changefreq' => 'monthly'],
    ];
    $entries = [];
    foreach ($routes as $item) {
      $entries[] = [
        'loc' => Url::fromRoute($item['route'], [], ['absolute' => TRUE])->toString(),
        'changefreq' => $item['changefreq'],
        'priority' => $item['priority'],
        'lastmod' => gmdate('Y-m-d'),
      ];
    }
    return $entries;
  }

  /**
   * Approved publisher canonical URLs.
   *
   * @return array<int, array<string, string|float>>
   */
  protected function publisherPages(): array {
    $storage = $this->entityTypeManager->getStorage('xmt_publisher');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 'approved')
      ->sort('changed', 'DESC')
      ->execute();

    if (!$ids) {
      return [];
    }

    $entries = [];
    foreach ($storage->loadMultiple($ids) as $publisher) {
      $entries[] = [
        'loc' => $publisher->toUrl('canonical', ['absolute' => TRUE])->toString(),
        'lastmod' => gmdate('Y-m-d', $publisher->getChangedTime()),
        'changefreq' => 'weekly',
        'priority' => '0.7',
      ];
    }
    return $entries;
  }

  /**
   * Recent trusted articles (L1 + L2).
   *
   * @return array<int, array<string, string|float>>
   */
  protected function recentArticles(int $limit): array {
    $limit = max(1, min($limit, 500));
    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'article')
      ->condition('status', 1)
      ->condition('field_trust_level', ['l1_official', 'l2_enterprise'], 'IN')
      ->sort('changed', 'DESC')
      ->range(0, $limit)
      ->execute();

    if (!$nids) {
      return [];
    }

    $entries = [];
    foreach ($storage->loadMultiple($nids) as $node) {
      $entries[] = [
        'loc' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
        'lastmod' => gmdate('Y-m-d', $node->getChangedTime()),
        'changefreq' => 'weekly',
        'priority' => '0.6',
      ];
    }
    return $entries;
  }

  /**
   * Escapes text for XML output.
   */
  protected function xml(string $value): string {
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
  }

}
