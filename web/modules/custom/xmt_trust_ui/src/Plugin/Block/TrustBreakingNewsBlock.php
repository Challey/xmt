<?php

namespace Drupal\xmt_trust_ui\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sancy-style breaking news ticker of latest trusted articles.
 *
 * @Block(
 *   id = "xmt_trust_breaking_news",
 *   admin_label = @Translation("XMT Breaking News"),
 *   category = @Translation("XMT")
 * )
 */
class TrustBreakingNewsBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
      ->sort('created', 'DESC')
      ->range(0, 8)
      ->execute();

    $items = [];
    if ($nids) {
      foreach ($storage->loadMultiple($nids) as $node) {
        $items[] = [
          'url' => Url::fromRoute('xmt_trust_ui.short_read_detail', ['node' => $node->id()])->toString(),
          'title' => $node->label(),
        ];
      }
    }

    return [
      '#theme' => 'xmt_trust_breaking',
      '#items' => $items,
      '#short_news_url' => Url::fromRoute('xmt_trust_ui.short_read')->toString(),
      '#today_url' => Url::fromRoute('xmt_trust_ui.short_read_today')->toString(),
      '#attached' => ['library' => ['xmt_trust_ui/short_read']],
      '#cache' => [
        'tags' => ['node_list'],
        'contexts' => ['languages:language_interface'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return array_merge(parent::getCacheTags(), ['node_list']);
  }

}
