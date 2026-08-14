<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\xmt_trust_ui\Service\TrustedFeedBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Trusted content listing pages.
 */
class TrustFeedController extends ControllerBase {

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
   * Renders a filtered trust feed.
   */
  public function feed(string $filter = 'all'): array {
    $items = [];
    foreach ($this->feedBuilder->items($filter, 30) as $item) {
      $level = $item['trust_level'];
      $items[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['xmt-trust-feed__item']],
        'title' => [
          '#type' => 'link',
          '#title' => $item['title'],
          '#url' => Url::fromUri($item['url']),
          '#prefix' => '<h3>',
          '#suffix' => '</h3>',
        ],
        'badge' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $item['trust_label'],
          '#attributes' => [
            'class' => ['xmt-trust-badge', xmt_trust_badge_class($level)],
          ],
        ],
        'meta' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->t('Published @date', [
            '@date' => \Drupal::service('date.formatter')->format($item['published'], 'short'),
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
    ];

    $formats = [
      '#type' => 'container',
      '#attributes' => ['class' => ['xmt-trust-feed__formats']],
      'rss' => Link::fromTextAndUrl($this->t('RSS'), Url::fromRoute('xmt_trust_ui.' . $this->feedBuilder->formatRouteSuffix($filter, 'rss')))->toRenderable(),
      'json' => Link::fromTextAndUrl($this->t('JSON'), Url::fromRoute('xmt_trust_ui.' . $this->feedBuilder->formatRouteSuffix($filter, 'json')))->toRenderable(),
    ];

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['xmt-trust-feed']],
      'nav' => $nav,
      'formats' => $formats,
      'list' => $items === [] ? [
        '#markup' => '<p>' . $this->t('No trusted articles yet.') . '</p>',
      ] : [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
      '#attached' => [
        'library' => ['xmt_trust_ui/trust_feed'],
        'html_head_link' => [
          [
            [
              'rel' => 'alternate',
              'type' => 'application/rss+xml',
              'title' => $this->t('RSS'),
              'href' => Url::fromRoute('xmt_trust_ui.' . $this->feedBuilder->formatRouteSuffix($filter, 'rss'), [], ['absolute' => TRUE])->toString(),
            ],
            TRUE,
          ],
          [
            [
              'rel' => 'alternate',
              'type' => 'application/json',
              'title' => $this->t('JSON'),
              'href' => Url::fromRoute('xmt_trust_ui.' . $this->feedBuilder->formatRouteSuffix($filter, 'json'), [], ['absolute' => TRUE])->toString(),
            ],
            TRUE,
          ],
        ],
      ],
    ];

    return $build;
  }

}
