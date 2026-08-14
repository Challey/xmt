<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\xmt_publisher\Entity\Publisher;
use Drupal\xmt_trust_ui\Service\TrustedFeedBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Per-publisher RSS and JSON article feeds.
 */
class PublisherFeedFormatController extends ControllerBase {

  public function __construct(
    protected TrustedFeedBuilder $feedBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('xmt_trust_ui.trusted_feed_builder'),
    );
  }

  /**
   * Returns an RSS feed for one publisher's articles.
   */
  public function rss(Publisher $xmt_publisher, Request $request): CacheableResponse {
    $limit = $this->limitFromRequest($request);
    $items = $this->feedBuilder->itemsForPublisher((int) $xmt_publisher->id(), $limit);
    $site_name = $this->config('system.site')->get('name') ?: 'XMT';
    $feed_url = $this->feedUrl($xmt_publisher, 'rss');
    $publisher_url = $xmt_publisher->toUrl('canonical', ['absolute' => TRUE])->toString();
    $channel_title = $site_name . ' — ' . $xmt_publisher->label();
    $description = (string) $this->t('Articles by @name', ['@name' => $xmt_publisher->label()]);

    $xml = $this->feedBuilder->toRssChannel($channel_title, $description, $items, $feed_url, $publisher_url);
    $response = new CacheableResponse($xml, 200, [
      'Content-Type' => 'application/rss+xml; charset=utf-8',
    ]);
    $this->attachCache($response, $xmt_publisher, $limit);
    return $response;
  }

  /**
   * Returns a JSON feed for one publisher's articles.
   */
  public function json(Publisher $xmt_publisher, Request $request): CacheableJsonResponse {
    $limit = $this->limitFromRequest($request);
    $items = $this->feedBuilder->itemsForPublisher((int) $xmt_publisher->id(), $limit);
    $site_name = $this->config('system.site')->get('name') ?: 'XMT';
    $feed_url = $this->feedUrl($xmt_publisher, 'json');
    $title = $site_name . ' — ' . $xmt_publisher->label();

    $payload = $this->feedBuilder->toJsonChannel($title, $feed_url, $items, [
      'publisher_id' => (int) $xmt_publisher->id(),
      'publisher' => $xmt_publisher->label(),
    ]);
    $response = new CacheableJsonResponse($payload);
    $this->attachCache($response, $xmt_publisher, $limit);
    return $response;
  }

  /**
   * Reads optional limit query parameter (1–100, default 30).
   */
  protected function limitFromRequest(Request $request): int {
    return min(100, max(1, (int) $request->query->get('limit', 30)));
  }

  /**
   * Absolute URL for a publisher feed endpoint.
   */
  protected function feedUrl(Publisher $publisher, string $format): string {
    $route = $format === 'json' ? 'xmt_trust_ui.publisher_feed_json' : 'xmt_trust_ui.publisher_feed_rss';
    return Url::fromRoute($route, ['xmt_publisher' => $publisher->id()], ['absolute' => TRUE])->toString();
  }

  /**
   * Adds cache metadata for a publisher feed response.
   */
  protected function attachCache(CacheableResponse|CacheableJsonResponse $response, Publisher $publisher, int $limit): void {
    $cache = new CacheableMetadata();
    $cache->setCacheMaxAge(300);
    $cache->addCacheTags(['node_list', 'xmt_publisher:' . $publisher->id()]);
    $cache->addCacheContexts(['url.query_args:limit']);
    $response->addCacheableDependency($cache);
    $response->addCacheableDependency($publisher);
  }

}
