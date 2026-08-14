<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trusted content listing pages and machine-readable feeds.
 */
class TrustFeedController extends ControllerBase {

  public const FEED_LIMIT = 30;

  /**
   * Renders a filtered trust feed (HTML).
   */
  public function feed(string $filter = 'all'): array {
    $nodes = $this->loadFeedNodes($filter);
    $items = [];
    foreach ($nodes as $node) {
      $level = $this->nodeTrustLevel($node);
      $items[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['xmt-trust-feed__item']],
        'title' => [
          '#type' => 'link',
          '#title' => $node->label(),
          '#url' => $node->toUrl(),
          '#prefix' => '<h3>',
          '#suffix' => '</h3>',
        ],
        'badge' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => xmt_trust_badge_label($level),
          '#attributes' => [
            'class' => ['xmt-trust-badge', xmt_trust_badge_class($level)],
          ],
        ],
        'meta' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->t('Published @date', [
            '@date' => \Drupal::service('date.formatter')->format($node->getCreatedTime(), 'short'),
          ]),
          '#attributes' => ['class' => ['xmt-trust-feed__meta']],
        ],
      ];
    }

    $nav = [
      '#type' => 'container',
      '#attributes' => ['class' => ['xmt-trust-feed__nav']],
      'all' => Link::fromTextAndUrl($this->t('All trusted'), Url::fromRoute('xmt_trust_ui.feed'))->toRenderable(),
      'official' => Link::fromTextAndUrl($this->t('Official (L1)'), Url::fromRoute('xmt_trust_ui.feed_official'))->toRenderable(),
      'enterprise' => Link::fromTextAndUrl($this->t('Enterprise (L2)'), Url::fromRoute('xmt_trust_ui.feed_enterprise'))->toRenderable(),
      'aggregate' => Link::fromTextAndUrl($this->t('Aggregate (L0)'), Url::fromRoute('xmt_trust_ui.feed_aggregate'))->toRenderable(),
      'api' => Link::fromTextAndUrl(
        $this->t('JSON'),
        Url::fromRoute('xmt_trust_ui.api_trusted', ['filter' => $filter])
      )->toRenderable(),
      'rss' => Link::fromTextAndUrl(
        $this->t('RSS'),
        Url::fromRoute('xmt_trust_ui.rss_trusted', ['filter' => $filter])
      )->toRenderable(),
    ];

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['xmt-trust-feed']],
      'nav' => $nav,
      'list' => $items === [] ? [
        '#markup' => '<p>' . $this->t('No trusted articles yet.') . '</p>',
      ] : [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
      '#attached' => ['library' => ['xmt_trust_ui/trust_feed']],
    ];
  }

  /**
   * Machine-readable JSON feed for trusted articles.
   */
  public function jsonFeed(string $filter = 'all'): JsonResponse {
    $filter = $this->normalizeFilter($filter);
    $nodes = $this->loadFeedNodes($filter, self::FEED_LIMIT);
    $items = [];
    foreach ($nodes as $node) {
      $items[] = $this->buildApiItem($node);
    }

    return new JsonResponse([
      'generated_at' => gmdate(DATE_ATOM),
      'filter' => $filter,
      'count' => count($items),
      'items' => $items,
    ]);
  }

  /**
   * RSS 2.0 feed for trusted articles.
   */
  public function rssFeed(string $filter = 'all'): Response {
    $filter = $this->normalizeFilter($filter);
    $nodes = $this->loadFeedNodes($filter, self::FEED_LIMIT);
    $channel_link = Url::fromRoute('xmt_trust_ui.feed', [], ['absolute' => TRUE])->toString();
    $title = match ($filter) {
      'l1_official' => 'XMT Official trusted',
      'l2_enterprise' => 'XMT Enterprise trusted',
      'l0_aggregate' => 'XMT Aggregate',
      default => 'XMT Trusted content',
    };

    $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"/>');
    $channel = $xml->addChild('channel');
    $channel->addChild('title', $title);
    $channel->addChild('link', $channel_link);
    $channel->addChild('description', 'XMT trusted articles feed');
    $channel->addChild('lastBuildDate', gmdate(DATE_RSS));

    foreach ($nodes as $node) {
      $item = $channel->addChild('item');
      $item->addChild('title', htmlspecialchars($node->label(), ENT_XML1 | ENT_QUOTES, 'UTF-8'));
      $item->addChild('link', $node->toUrl('canonical', ['absolute' => TRUE])->toString());
      $item->addChild('guid', $node->toUrl('canonical', ['absolute' => TRUE])->toString())->addAttribute('isPermaLink', 'true');
      $item->addChild('pubDate', gmdate(DATE_RSS, $node->getCreatedTime()));
      $level = $this->nodeTrustLevel($node);
      $item->addChild('category', xmt_trust_badge_label($level));
      $desc = $this->nodeSummary($node);
      if ($desc !== '') {
        $item->addChild('description', htmlspecialchars($desc, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
      }
    }

    $response = new Response($xml->asXML());
    $response->headers->set('Content-Type', 'application/rss+xml; charset=utf-8');
    return $response;
  }

  /**
   * Maps short aliases to trust level filter codes.
   */
  protected function normalizeFilter(string $filter): string {
    return match ($filter) {
      'official' => 'l1_official',
      'enterprise' => 'l2_enterprise',
      'aggregate' => 'l0_aggregate',
      default => $filter,
    };
  }

  /**
   * Loads published articles for a trust filter.
   *
   * @return \Drupal\node\NodeInterface[]
   */
  protected function loadFeedNodes(string $filter, int $limit = self::FEED_LIMIT): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range(0, $limit);

    if ($filter !== 'all') {
      $query->condition('field_trust_level', $filter);
    }
    else {
      $query->condition('field_trust_level', ['l1_official', 'l2_enterprise'], 'IN');
    }

    $nids = $query->execute();
    return $nids ? $storage->loadMultiple($nids) : [];
  }

  /**
   * Builds one API item for JSON consumers.
   *
   * @return array<string, mixed>
   */
  protected function buildApiItem(NodeInterface $node): array {
    $level = $this->nodeTrustLevel($node);
    $publisher = NULL;
    $publisher_id = NULL;
    if ($node->hasField('field_publisher') && !$node->get('field_publisher')->isEmpty()) {
      $entity = $node->get('field_publisher')->entity;
      $publisher_id = (int) $node->get('field_publisher')->target_id;
      $publisher = $entity ? $entity->label() : (string) $publisher_id;
    }
    $source = NULL;
    if ($node->hasField('field_source_url') && !$node->get('field_source_url')->isEmpty()) {
      $source = $node->get('field_source_url')->uri ?? $node->get('field_source_url')->value ?? NULL;
    }
    $provenance = NULL;
    if ($node->hasField('field_provenance_hash') && !$node->get('field_provenance_hash')->isEmpty()) {
      $provenance = $node->get('field_provenance_hash')->value;
    }

    return [
      'id' => (int) $node->id(),
      'title' => $node->label(),
      'url' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
      'trust_level' => $level,
      'trust_label' => xmt_trust_badge_label($level),
      'publisher_id' => $publisher_id,
      'publisher' => $publisher,
      'provenance_hash' => $provenance,
      'source_url' => $source,
      'summary' => $this->nodeSummary($node),
      'published_at' => gmdate(DATE_ATOM, $node->getCreatedTime()),
      'updated_at' => gmdate(DATE_ATOM, $node->getChangedTime()),
    ];
  }

  /**
   * Trust level code for a node.
   */
  protected function nodeTrustLevel(NodeInterface $node): string {
    if ($node->hasField('field_trust_level') && !$node->get('field_trust_level')->isEmpty()) {
      return (string) $node->get('field_trust_level')->value;
    }
    return 'l0_aggregate';
  }

  /**
   * Plain-text summary from body if present.
   */
  protected function nodeSummary(NodeInterface $node): string {
    if (!$node->hasField('body') || $node->get('body')->isEmpty()) {
      return '';
    }
    $summary = (string) ($node->get('body')->summary ?: '');
    if ($summary === '') {
      $summary = (string) ($node->get('body')->value ?: '');
    }
    $summary = trim(html_entity_decode(strip_tags($summary), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if (mb_strlen($summary) > 280) {
      $summary = mb_substr($summary, 0, 277) . '...';
    }
    return $summary;
  }

}
