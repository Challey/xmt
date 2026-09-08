<?php

namespace Drupal\xmt_trust_ui\Plugin\Block;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\xmt_trust_ui\DomainCatalog;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Homepage hot-news sections for 短闻 (finance / tech / domestic / world).
 *
 * @Block(
 *   id = "xmt_trust_home_columns",
 *   admin_label = @Translation("XMT Trust Home Columns"),
 *   category = @Translation("XMT")
 * )
 */
class TrustHomeColumnsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Hot sections shown on the hub homepage.
   *
   * @var list<array{domain: string, title: string}>
   */
  private const HOT_SECTIONS = [
    [
      'domain' => 'finance',
      'title' => '财经',
    ],
    [
      'domain' => 'tech',
      'title' => '科技',
    ],
    [
      'domain' => 'domestic',
      'title' => '国内',
    ],
    [
      'domain' => 'world',
      'title' => '国际',
    ],
  ];

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DateFormatterInterface $dateFormatter,
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
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $columns = [];

    foreach (self::HOT_SECTIONS as $section) {
      $domain = $section['domain'];
      $values = DomainCatalog::expandFilter($domain);
      $query = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'article')
        ->condition('status', 1)
        ->sort('created', 'DESC')
        ->range(0, 5);
      if (count($values) === 1) {
        $query->condition('field_domain', $values[0]);
      }
      elseif ($values !== []) {
        $query->condition('field_domain', $values, 'IN');
      }
      $nids = $query->execute();

      $items = [];
      if ($nids) {
        foreach ($storage->loadMultiple($nids) as $node) {
          $items[] = $this->buildItem($node);
        }
      }

      $columns[] = [
        'title' => $section['title'],
        'more_url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
          'query' => ['domain' => $domain],
        ])->toString(),
        'items' => $items,
      ];
    }

    $host = \Drupal::request()->getHost();
    $domain_map = [
      'zhubao.pub' => 'jewelry',
      'www.zhubao.pub' => 'jewelry',
      'airobotor.com' => 'robot',
      'www.airobotor.com' => 'robot',
      'hm-os.com' => 'harmonyos',
      'www.hm-os.com' => 'harmonyos',
      'kstudy.com.cn' => 'ai_edu',
      'www.kstudy.com.cn' => 'ai_edu',
      'drupal.org.cn' => 'drupal',
      'www.drupal.org.cn' => 'drupal',
      'itra.com.cn' => 'itra',
      'www.itra.com.cn' => 'itra',
    ];
    $domain = $domain_map[$host] ?? '';
    $query = array_filter(['domain' => $domain !== '' ? $domain : NULL]);

    $hot_links = [];
    foreach (self::HOT_SECTIONS as $section) {
      $hot_links[] = [
        'label' => $section['title'],
        'url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
          'query' => array_filter([
            'domain' => $section['domain'],
          ] + $query),
        ])->toString(),
      ];
    }

    return [
      '#theme' => 'xmt_trust_home',
      '#columns' => $columns,
      '#short_news' => [
        'brand' => (string) $this->t('短闻'),
        'text' => (string) $this->t('财经 · 科技 · 国内 · 国际——当前最热可信短闻，信流连刷，来源可核。'),
        'browse_url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
          'query' => $query + ['mode' => 'browse'],
        ])->toString(),
        'immerse_url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
          'query' => $query,
        ])->toString(),
        'today_url' => Url::fromRoute('xmt_trust_ui.short_read_today', [], ['query' => $query])->toString(),
        'later_url' => Url::fromRoute('xmt_trust_ui.short_read_later')->toString(),
        'search_url' => Url::fromRoute('xmt_trust_ui.search')->toString(),
        'rss_url' => Url::fromRoute('xmt_trust_ui.short_news_rss', [], ['query' => $query])->toString(),
        'official_media_url' => Url::fromRoute('xmt_trust_ui.official_media')->toString(),
        'hot_links' => $hot_links,
      ],
      '#attached' => ['library' => ['xmt_trust_ui/trust_feed', 'xmt_trust_ui/short_read']],
      '#cache' => [
        'tags' => ['node_list'],
        'contexts' => ['languages:language_interface', 'url.site'],
      ],
    ];
  }

  /**
   * Builds one magazine item from a node.
   */
  protected function buildItem(NodeInterface $node): array {
    $level = $node->hasField('field_trust_level')
      ? ($node->get('field_trust_level')->value ?? 'l0_aggregate')
      : 'l0_aggregate';
    $excerpt = '';
    if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
      $raw = $node->get('body')->summary ?: $node->get('body')->value;
      $excerpt = Unicode::truncate(trim(html_entity_decode(strip_tags((string) $raw), ENT_QUOTES, 'UTF-8')), 140, TRUE, TRUE);
    }

    return [
      'url' => $node->toUrl()->toString(),
      'short_url' => Url::fromRoute('xmt_trust_ui.short_read_detail', ['node' => $node->id()])->toString(),
      'title' => $node->label(),
      'date' => $this->dateFormatter->format($node->getCreatedTime(), 'custom', 'M d, Y'),
      'excerpt' => $excerpt,
      'badge' => xmt_trust_badge_label($level),
      'badge_class' => xmt_trust_badge_class($level),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return array_merge(parent::getCacheTags(), ['node_list']);
  }

}
