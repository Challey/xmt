<?php

namespace Drupal\xmt_trust_ui\Service;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\node\NodeInterface;

/**
 * Builds trusted content feed items and serializes RSS/JSON payloads.
 */
class TrustedFeedBuilder {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Feed filter metadata.
   *
   * @return array<string, array{title: string, levels: string[]}>
   */
  public function filters(): array {
    return [
      'all' => [
        'title' => (string) $this->t('Trusted content'),
        'levels' => ['l1_official', 'l2_enterprise'],
      ],
      'l1_official' => [
        'title' => (string) $this->t('Official trusted (L1)'),
        'levels' => ['l1_official'],
      ],
      'l2_enterprise' => [
        'title' => (string) $this->t('Enterprise trusted (L2)'),
        'levels' => ['l2_enterprise'],
      ],
      'l0_aggregate' => [
        'title' => (string) $this->t('Domain aggregate (L0)'),
        'levels' => ['l0_aggregate'],
      ],
    ];
  }

  /**
   * Loads feed items for a filter.
   *
   * @return array<int, array<string, mixed>>
   */
  public function items(string $filter, int $limit = 30): array {
    if (!isset($this->filters()[$filter])) {
      return [];
    }

    $limit = max(1, min($limit, 100));
    $levels = $this->filters()[$filter]['levels'];
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range(0, $limit);

    if (count($levels) === 1) {
      $query->condition('field_trust_level', $levels[0]);
    }
    else {
      $query->condition('field_trust_level', $levels, 'IN');
    }

    $nids = $query->execute();
    if (!$nids) {
      return [];
    }

    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $storage->loadMultiple($nids);
    $items = [];
    foreach ($nodes as $node) {
      $items[] = $this->itemFromNode($node);
    }
    return $items;
  }

  /**
   * Builds one feed item from a node.
   *
   * @return array<string, mixed>
   */
  public function itemFromNode(NodeInterface $node): array {
    $level = 'l0_aggregate';
    if ($node->hasField('field_trust_level') && !$node->get('field_trust_level')->isEmpty()) {
      $level = $node->get('field_trust_level')->value ?: 'l0_aggregate';
    }

    $publisher_id = NULL;
    $publisher = NULL;
    if ($node->hasField('field_publisher') && !$node->get('field_publisher')->isEmpty()) {
      $publisher_id = (int) $node->get('field_publisher')->target_id;
      $entity = $node->get('field_publisher')->entity;
      $publisher = $entity ? $entity->label() : (string) $publisher_id;
    }

    $provenance = NULL;
    if ($node->hasField('field_provenance_hash') && !$node->get('field_provenance_hash')->isEmpty()) {
      $provenance = $node->get('field_provenance_hash')->value;
    }

    $source_url = NULL;
    if ($node->hasField('field_source_url') && !$node->get('field_source_url')->isEmpty()) {
      $source_url = $node->get('field_source_url')->uri ?? $node->get('field_source_url')->value ?? NULL;
    }

    $source_name = NULL;
    if ($node->hasField('field_source_name') && !$node->get('field_source_name')->isEmpty()) {
      $source_name = $node->get('field_source_name')->value;
    }

    $domain = NULL;
    if ($node->hasField('field_domain') && !$node->get('field_domain')->isEmpty()) {
      $domain = $node->get('field_domain')->value;
    }

    $summary = '';
    if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
      $raw = $node->get('body')->summary ?: $node->get('body')->value;
      $summary = Html::decodeEntities(strip_tags((string) $raw));
      if (mb_strlen($summary) > 300) {
        $summary = mb_substr($summary, 0, 297) . '...';
      }
    }

    $published = (int) $node->getCreatedTime();
    return [
      'nid' => (int) $node->id(),
      'title' => $node->label(),
      'url' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
      'trust_level' => $level,
      'trust_label' => xmt_trust_badge_label($level),
      'publisher' => $publisher,
      'publisher_id' => $publisher_id,
      'provenance_hash' => $provenance,
      'source_url' => $source_url,
      'source_name' => $source_name,
      'domain' => $domain,
      'summary' => $summary,
      'published' => $published,
      'published_iso' => gmdate('c', $published),
    ];
  }

  /**
   * Serializes feed items as RSS 2.0 XML.
   */
  public function toRss(string $filter, array $items, string $feed_url, string $site_name, string $site_url): string {
    $meta = $this->filters()[$filter];
    $updated = $items[0]['published'] ?? time();
    $channel_title = $site_name . ' — ' . $meta['title'];

    $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
    $xml .= '<rss version="2.0" xmlns:xmt="https://xmt.pub/ns/trust-feed">' . "\n";
    $xml .= "  <channel>\n";
    $xml .= '    <title>' . $this->xml($channel_title) . "</title>\n";
    $xml .= '    <link>' . $this->xml($site_url) . "</link>\n";
    $xml .= '    <description>' . $this->xml($meta['title']) . "</description>\n";
    $xml .= '    <lastBuildDate>' . gmdate('r', $updated) . "</lastBuildDate>\n";
    $xml .= '    <atom:link href="' . $this->xml($feed_url) . '" rel="self" type="application/rss+xml" xmlns:atom="http://www.w3.org/2005/Atom" />' . "\n";

    foreach ($items as $item) {
      $xml .= "    <item>\n";
      $xml .= '      <title>' . $this->xml($item['title']) . "</title>\n";
      $xml .= '      <link>' . $this->xml($item['url']) . "</link>\n";
      $xml .= '      <guid isPermaLink="true">' . $this->xml($item['url']) . "</guid>\n";
      $xml .= '      <pubDate>' . gmdate('r', $item['published']) . "</pubDate>\n";
      if ($item['summary'] !== '') {
        $xml .= '      <description>' . $this->xml($item['summary']) . "</description>\n";
      }
      $xml .= '      <category>' . $this->xml($item['trust_level']) . "</category>\n";
      $xml .= '      <xmt:trust_level>' . $this->xml($item['trust_level']) . "</xmt:trust_level>\n";
      $xml .= '      <xmt:trust_label>' . $this->xml($item['trust_label']) . "</xmt:trust_label>\n";
      if ($item['publisher'] !== NULL) {
        $xml .= '      <xmt:publisher>' . $this->xml($item['publisher']) . "</xmt:publisher>\n";
      }
      if ($item['provenance_hash'] !== NULL) {
        $xml .= '      <xmt:provenance_hash>' . $this->xml($item['provenance_hash']) . "</xmt:provenance_hash>\n";
      }
      if ($item['source_url'] !== NULL) {
        $xml .= '      <xmt:source_url>' . $this->xml($item['source_url']) . "</xmt:source_url>\n";
      }
      $xml .= "    </item>\n";
    }

    $xml .= "  </channel>\n</rss>\n";
    return $xml;
  }

  /**
   * Serializes feed items as a JSON document.
   *
   * @return array<string, mixed>
   */
  public function toJson(string $filter, array $items, string $feed_url, string $site_name): array {
    $meta = $this->filters()[$filter];
    $updated = $items[0]['published_iso'] ?? gmdate('c');
    return [
      'feed' => [
        'title' => $site_name . ' — ' . $meta['title'],
        'filter' => $filter,
        'self' => $feed_url,
        'updated' => $updated,
        'count' => count($items),
        'items' => $items,
      ],
    ];
  }

  /**
   * Resolves the route name for a filter's HTML page.
   */
  public function htmlRouteName(string $filter): string {
    return match ($filter) {
      'l1_official' => 'xmt_trust_ui.feed_official',
      'l2_enterprise' => 'xmt_trust_ui.feed_enterprise',
      'l0_aggregate' => 'xmt_trust_ui.feed_aggregate',
      default => 'xmt_trust_ui.feed',
    };
  }

  /**
   * Resolves RSS/JSON route suffix for a filter.
   */
  public function formatRouteSuffix(string $filter, string $format): string {
    $base = match ($filter) {
      'l1_official' => 'feed_official',
      'l2_enterprise' => 'feed_enterprise',
      'l0_aggregate' => 'feed_aggregate',
      default => 'feed',
    };
    return $format === 'json' ? $base . '_json' : $base . '_rss';
  }

  /**
   * Escapes text for XML output.
   */
  protected function xml(string $value): string {
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
  }

}
