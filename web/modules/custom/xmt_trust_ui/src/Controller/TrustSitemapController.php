<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\xmt_trust_ui\Service\TrustSitemapBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * XML sitemap for trusted content discovery.
 */
class TrustSitemapController extends ControllerBase {

  public function __construct(
    protected TrustSitemapBuilder $sitemapBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('xmt_trust_ui.trust_sitemap_builder'),
    );
  }

  /**
   * Returns the trust platform sitemap XML.
   */
  public function sitemap(Request $request): CacheableResponse {
    $limit = min(500, max(10, (int) $request->query->get('limit', 100)));
    $include_l0 = $request->query->get('include_l0', '1') !== '0';
    $l0_limit = max(0, (int) $request->query->get('l0_limit', 0));
    $xml = $this->sitemapBuilder->buildXml($limit, $include_l0, $l0_limit);
    $response = new CacheableResponse($xml, 200, [
      'Content-Type' => 'application/xml; charset=utf-8',
    ]);

    $cache = new CacheableMetadata();
    $cache->setCacheMaxAge(3600);
    $cache->addCacheTags(['node_list', 'xmt_publisher_list']);
    $cache->addCacheContexts(['url.query_args:limit', 'url.query_args:include_l0', 'url.query_args:l0_limit']);
    $response->addCacheableDependency($cache);

    return $response;
  }

}
