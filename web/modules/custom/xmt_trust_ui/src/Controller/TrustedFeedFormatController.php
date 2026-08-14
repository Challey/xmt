<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\xmt_trust_ui\Service\TrustedFeedBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Machine-readable trusted content feeds (RSS and JSON).
 */
class TrustedFeedFormatController extends ControllerBase {

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
   * Returns an RSS 2.0 feed.
   */
  public function rss(string $filter, Request $request): CacheableResponse {
    $limit = $this->limitFromRequest($request);
    $items = $this->feedBuilder->items($filter, $limit);
    $feed_url = $this->currentFeedUrl($filter, 'rss');
    $site_name = $this->config('system.site')->get('name') ?: 'XMT';
    $site_url = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();

    $xml = $this->feedBuilder->toRss($filter, $items, $feed_url, $site_name, $site_url);
    $response = new CacheableResponse($xml, 200, [
      'Content-Type' => 'application/rss+xml; charset=utf-8',
    ]);
    $this->attachCache($response, $limit);
    return $response;
  }

  /**
   * Returns a JSON feed document.
   */
  public function json(string $filter, Request $request): CacheableJsonResponse {
    $limit = $this->limitFromRequest($request);
    $items = $this->feedBuilder->items($filter, $limit);
    $feed_url = $this->currentFeedUrl($filter, 'json');
    $site_name = $this->config('system.site')->get('name') ?: 'XMT';

    $payload = $this->feedBuilder->toJson($filter, $items, $feed_url, $site_name);
    $response = new CacheableJsonResponse($payload);
    $this->attachCache($response, $limit);
    return $response;
  }

  /**
   * Reads optional limit query parameter (1–100, default 30).
   */
  protected function limitFromRequest(Request $request): int {
    return min(100, max(1, (int) $request->query->get('limit', 30)));
  }

  /**
   * Absolute URL for the current feed endpoint.
   */
  protected function currentFeedUrl(string $filter, string $format): string {
    $route = $this->feedBuilder->formatRouteSuffix($filter, $format);
    return Url::fromRoute('xmt_trust_ui.' . $route, [], ['absolute' => TRUE])->toString();
  }

  /**
   * Adds shared cache metadata to a feed response.
   */
  protected function attachCache(CacheableResponse|CacheableJsonResponse $response, int $limit): void {
    $cache = new CacheableMetadata();
    $cache->setCacheMaxAge(300);
    $cache->addCacheTags(['node_list']);
    $cache->addCacheContexts(['url.query_args:limit']);
    $response->addCacheableDependency($cache);
  }

}
