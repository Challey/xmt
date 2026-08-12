<?php

namespace Drupal\xmt_trust_ui\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Recent L1/L2 trusted articles block.
 *
 * @Block(
 *   id = "xmt_trust_feed_block",
 *   admin_label = @Translation("XMT Trust Feed"),
 *   category = @Translation("XMT")
 * )
 */
class TrustFeedBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', 1)
      ->condition('field_trust_level', ['l1_official', 'l2_enterprise'], 'IN')
      ->sort('created', 'DESC')
      ->range(0, 5)
      ->execute();

    $links = [];
    if ($nids) {
      foreach ($storage->loadMultiple($nids) as $node) {
        $level = $node->get('field_trust_level')->value ?? 'l0_aggregate';
        $links[] = Link::fromTextAndUrl(
          $node->label() . ' [' . xmt_trust_badge_label($level) . ']',
          $node->toUrl()
        )->toRenderable();
      }
    }

    return [
      '#theme' => 'item_list',
      '#items' => $links ?: [$this->t('No trusted articles yet.')],
      '#attached' => ['library' => ['xmt_trust_ui/trust_feed']],
      'more' => [
        '#type' => 'link',
        '#title' => $this->t('View all trusted content'),
        '#url' => Url::fromRoute('xmt_trust_ui.feed'),
      ],
    ];
  }

}
