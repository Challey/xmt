<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\xmt_trust_ui\DomainCatalog;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Admin trust / short-news statistics.
 */
class TrustStatsController extends ControllerBase {

  /**
   * Dashboard.
   */
  public function dashboard(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $levels = [
      'l1_official' => (string) $this->t('官方可信'),
      'l2_enterprise' => (string) $this->t('企业可信'),
      'l0_aggregate' => (string) $this->t('领域汇聚'),
    ];

    $rows = [];
    $total = 0;
    foreach ($levels as $key => $label) {
      $n = (int) $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'article')
        ->condition('status', 1)
        ->condition('field_trust_level', $key)
        ->count()
        ->execute();
      $total += $n;
      $level_q = match ($key) {
        'l1_official' => 'official',
        'l2_enterprise' => 'enterprise',
        'l0_aggregate' => 'aggregate',
        default => NULL,
      };
      $rows[] = [
        [
          'data' => [
            '#type' => 'link',
            '#title' => $label,
            '#url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
              'query' => array_filter(['level' => $level_q]),
            ]),
          ],
        ],
        $key,
        $n,
      ];
    }

    $publishers = 0;
    try {
      $publishers = (int) $this->entityTypeManager()->getStorage('xmt_publisher')->getQuery()
        ->accessCheck(FALSE)
        ->condition('status', 'approved')
        ->count()
        ->execute();
    }
    catch (\Throwable $e) {
      $publishers = 0;
    }

    $week_ago = strtotime('-7 days');
    $recent = (int) $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'article')
      ->condition('status', 1)
      ->condition('created', $week_ago, '>=')
      ->count()
      ->execute();

    $domains = [];
    $nids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'article')
      ->condition('status', 1)
      ->exists('field_domain')
      ->range(0, 500)
      ->execute();
    foreach ($storage->loadMultiple($nids ?: []) as $node) {
      if (!$node instanceof NodeInterface || $node->get('field_domain')->isEmpty()) {
        continue;
      }
      $d = (string) $node->get('field_domain')->value;
      $domains[$d] = ($domains[$d] ?? 0) + 1;
    }
    arsort($domains);
    $domain_rows = [];
    foreach (array_slice($domains, 0, 12, TRUE) as $d => $c) {
      $domain_rows[] = [
        [
          'data' => [
            '#type' => 'link',
            '#title' => DomainCatalog::label($d),
            '#url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
              'query' => ['domain' => DomainCatalog::canonicalize($d)],
            ]),
          ],
        ],
        $c,
      ];
    }

    return [
      'intro' => [
        '#markup' => '<p>' . $this->t('Published trusted articles: @total. Last 7 days: @recent. Approved publishers: @pub.', [
          '@total' => $total,
          '@recent' => $recent,
          '@pub' => $publishers,
        ]) . ' '
          . Link::fromTextAndUrl($this->t('全部短闻'), Url::fromRoute('xmt_trust_ui.short_read'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('近 7 日短闻'), Url::fromRoute('xmt_trust_ui.short_read'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('出版社目录'), Url::fromRoute('xmt_trust_ui.publishers_directory'))->toString()
          . '</p><p>'
          . Link::fromTextAndUrl($this->t('短闻'), Url::fromRoute('xmt_trust_ui.short_read'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('运营台'), Url::fromRoute('xmt_trust_ui.source_ops'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('健康'), Url::fromRoute('xmt_trust_ui.source_health'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('静默'), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => ['status' => 'silent'],
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('空源'), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => ['status' => 'empty'],
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('过期'), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => ['status' => 'stale'],
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('今日简报'), Url::fromRoute('xmt_trust_ui.short_read_today'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('稍后'), Url::fromRoute('xmt_trust_ui.short_read_later'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('搜索'), Url::fromRoute('xmt_trust_ui.search'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('溯源'), Url::fromRoute('xmt_trust_ui.provenance_audit'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('Sitemap'), Url::fromRoute('xmt_trust_ui.trust_sitemap'))->toString()
          . '</p>',
      ],
      'levels' => [
        '#type' => 'table',
        '#caption' => $this->t('By trust level'),
        '#header' => [$this->t('Label'), $this->t('Key'), $this->t('Count')],
        '#rows' => $rows,
      ],
      'domains' => [
        '#type' => 'table',
        '#caption' => $this->t('By domain (sample up to 500 nodes)'),
        '#header' => [$this->t('Domain'), $this->t('Count')],
        '#rows' => $domain_rows,
        '#empty' => $this->t('No domain data') . ' · '
          . Link::fromTextAndUrl($this->t('运营台'), Url::fromRoute('xmt_trust_ui.source_ops'))->toString(),
      ],
      '#cache' => [
        'tags' => ['node_list:article'],
        'max-age' => 120,
      ],
    ];
  }

}
