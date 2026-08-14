<?php

namespace Drupal\xmt_trust_ui\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Homepage three-column trusted content summary.
 *
 * @Block(
 *   id = "xmt_trust_home_columns",
 *   admin_label = @Translation("XMT Trust Home Columns"),
 *   category = @Translation("XMT")
 * )
 */
class TrustHomeColumnsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Column definitions: trust level, header, more route.
   */
  private const COLUMNS = [
    [
      'level' => 'l1_official',
      'title' => '官方可信',
      'route' => 'xmt_trust_ui.feed_official',
    ],
    [
      'level' => 'l2_enterprise',
      'title' => '企业可信',
      'route' => 'xmt_trust_ui.feed_enterprise',
    ],
    [
      'level' => 'l0_aggregate',
      'title' => '领域汇聚',
      'route' => 'xmt_trust_ui.feed_aggregate',
    ],
  ];

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
    $columns = [];

    foreach (self::COLUMNS as $column) {
      $nids = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'article')
        ->condition('status', 1)
        ->condition('field_trust_level', $column['level'])
        ->sort('created', 'DESC')
        ->range(0, 5)
        ->execute();

      $items = [];
      if ($nids) {
        foreach ($storage->loadMultiple($nids) as $node) {
          $level = $node->get('field_trust_level')->value ?? 'l0_aggregate';
          $items[] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['xmt-trust-home__item']],
            'link' => [
              '#type' => 'link',
              '#title' => $node->label(),
              '#url' => $node->toUrl(),
            ],
            'badge' => [
              '#type' => 'html_tag',
              '#tag' => 'span',
              '#value' => xmt_trust_badge_label($level),
              '#attributes' => [
                'class' => ['xmt-trust-badge', xmt_trust_badge_class($level)],
              ],
            ],
          ];
        }
      }

      $columns[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['xmt-trust-home__column']],
        'header' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['xmt-trust-home__header']],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $column['title'],
          ],
          'more' => [
            '#type' => 'link',
            '#title' => $this->t('更多'),
            '#url' => Url::fromRoute($column['route']),
            '#attributes' => ['class' => ['xmt-trust-home__more']],
          ],
        ],
        'list' => $items === [] ? [
          '#markup' => '<p class="xmt-trust-home__empty">' . $this->t('暂无内容') . '</p>',
        ] : [
          '#theme' => 'item_list',
          '#items' => $items,
          '#attributes' => ['class' => ['xmt-trust-home__list']],
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['xmt-trust-home']],
      'columns' => $columns,
      'footer' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['xmt-trust-home__footer']],
        'trusted' => [
          '#type' => 'link',
          '#title' => $this->t('All trusted content'),
          '#url' => Url::fromRoute('xmt_trust_ui.feed'),
        ],
        'publishers' => [
          '#type' => 'link',
          '#title' => $this->t('Certified publishers'),
          '#url' => Url::fromRoute('xmt_trust_ui.publishers_directory'),
        ],
        'apply' => [
          '#type' => 'link',
          '#title' => $this->t('Apply for certification'),
          '#url' => Url::fromRoute('xmt_publisher.apply'),
        ],
        'sitemap' => [
          '#type' => 'link',
          '#title' => $this->t('Sitemap'),
          '#url' => Url::fromRoute('xmt_trust_ui.trust_sitemap'),
        ],
      ],
      '#attached' => ['library' => ['xmt_trust_ui/trust_feed']],
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
