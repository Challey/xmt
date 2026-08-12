<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Trusted content listing pages.
 */
class TrustFeedController extends ControllerBase {

  /**
   * Renders a filtered trust feed.
   */
  public function feed(string $filter = 'all'): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range(0, 30);

    if ($filter !== 'all') {
      $query->condition('field_trust_level', $filter);
    }
    else {
      $query->condition('field_trust_level', ['l1_official', 'l2_enterprise'], 'IN');
    }

    $nids = $query->execute();
    $items = [];
    if ($nids) {
      $nodes = $storage->loadMultiple($nids);
      foreach ($nodes as $node) {
        $level = $node->hasField('field_trust_level') ? ($node->get('field_trust_level')->value ?? 'l0_aggregate') : 'l0_aggregate';
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
    }

    $nav = [
      '#type' => 'container',
      '#attributes' => ['class' => ['xmt-trust-feed__nav']],
      'all' => Link::fromTextAndUrl($this->t('All trusted'), Url::fromRoute('xmt_trust_ui.feed'))->toRenderable(),
      'official' => Link::fromTextAndUrl($this->t('Official (L1)'), Url::fromRoute('xmt_trust_ui.feed_official'))->toRenderable(),
      'enterprise' => Link::fromTextAndUrl($this->t('Enterprise (L2)'), Url::fromRoute('xmt_trust_ui.feed_enterprise'))->toRenderable(),
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

}
