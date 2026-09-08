<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\xmt_trust_ui\DomainCatalog;
use Drupal\xmt_trust_ui\Service\ShortSummaryGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hybrid short-news reader: browse feed + immersive vertical mode.
 */
class ShortReadController extends ControllerBase {


  public function __construct(
    protected ShortSummaryGenerator $summarizer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('xmt_trust_ui.short_summary'),
    );
  }

  /**
   * Feed: 信流 (default) or 速览.
   */
  public function feed(Request $request): array {
    $level = (string) $request->query->get('level', 'trusted');
    // Default to 信流 (immerse) — higher stickiness than list browse.
    $mode = $request->query->get('mode', 'immerse') === 'browse' ? 'browse' : 'immerse';
    $domain = trim((string) $request->query->get('domain', ''));
    $source = trim((string) $request->query->get('source', ''));
    $focus = (int) $request->query->get('focus', 0);

    $cards = [];
    foreach ($this->loadNodes($level, 0, $mode === 'immerse' ? 24 : 30, $domain, $source) as $node) {
      $cards[] = $this->buildCard($node, FALSE, $level, $domain);
    }

    $query_base = array_filter([
      'level' => $level === 'trusted' ? NULL : $level,
      'domain' => $domain !== '' ? $domain : NULL,
      'source' => $source !== '' ? $source : NULL,
    ]);

    $official_media_url = '';
    if ($domain !== '' && \Drupal\xmt_trust_ui\OfficialMediaChannels::isValid($domain)) {
      $official_media_url = Url::fromRoute('xmt_trust_ui.official_media_channel', [
        'channel' => $domain,
      ])->toString();
    }
    elseif (in_array($level, ['official', 'l1', 'l1_official'], TRUE)) {
      $official_media_url = Url::fromRoute('xmt_trust_ui.official_media')->toString();
    }

    $build = [
      '#theme' => 'xmt_short_read_feed',
      '#cards' => $cards,
      '#level' => $level,
      '#mode' => $mode,
      '#domain' => $domain,
      '#source' => $source,
      '#focus' => $focus,
      '#tagline' => $source !== ''
        ? (string) $this->t('信源：@s · 短平快可信阅读。', ['@s' => $source])
        : (string) $this->t('短平快 · 可核验 · 信流连读——可信短内容的第三种打开方式。'),
      '#filters' => $this->filters($level, $mode, $domain, $source),
      '#domain_filters' => $this->domainFilters($level, $mode, $domain, $source),
      '#domain_groups' => $this->domainGroups($level, $mode, $domain, $source),
      '#domain_rail' => $this->domainRail($level, $mode, $domain, $source),
      '#browse_url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
        'query' => $query_base + ['mode' => 'browse'],
      ])->toString(),
      '#immerse_url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
        'query' => $query_base + ($focus ? ['focus' => $focus] : []),
      ])->toString(),
      '#api_url' => Url::fromRoute('xmt_trust_ui.short_read_api', [], [
        'query' => array_filter([
          'level' => $level,
          'domain' => $domain !== '' ? $domain : NULL,
          'source' => $source !== '' ? $source : NULL,
        ]),
        'absolute' => TRUE,
      ])->toString(),
      '#rss_url' => Url::fromRoute('xmt_trust_ui.short_news_rss', [], [
        'query' => array_filter([
          'level' => $level === 'trusted' ? NULL : $level,
          'domain' => $domain !== '' ? $domain : NULL,
          'source' => $source !== '' ? $source : NULL,
        ]),
      ])->toString(),
      '#today_url' => Url::fromRoute('xmt_trust_ui.short_read_today', [], [
        'query' => array_filter([
          'level' => $level === 'trusted' ? NULL : $level,
          'domain' => $domain !== '' ? $domain : NULL,
        ]),
      ])->toString(),
      '#official_media_url' => $official_media_url,
      '#clear_source_url' => $source !== ''
        ? Url::fromRoute('xmt_trust_ui.short_read', [], [
          'query' => array_filter([
            'level' => $level === 'trusted' ? NULL : $level,
            'domain' => $domain !== '' ? $domain : NULL,
            'mode' => $mode === 'browse' ? 'browse' : NULL,
          ]),
        ])->toString()
        : '',
      '#empty_state' => $cards === [] ? $this->emptyState($level, $mode, $domain, $source) : NULL,
      '#attached' => [
        'library' => ['xmt_trust_ui/short_read'],
        'html_head' => array_merge(
          $this->ogHead(
            (string) $this->t('短闻'),
            (string) $this->t('短平快可信阅读：速览摘要，信流连读，官方原文直达。'),
            Url::fromRoute('xmt_trust_ui.short_read', [], ['absolute' => TRUE])->toString(),
          ),
          $this->rssAlternateHead($query_base),
        ),
        'drupalSettings' => [
          'xmtShortRead' => [
            'shareUrl' => Url::fromRoute('xmt_trust_ui.short_read', [], [
              'query' => $query_base,
              'absolute' => TRUE,
            ])->toString(),
            'wechatHelpUrl' => Url::fromRoute('xmt_trust_ui.help_wechat')->toString(),
            'progressUrl' => Url::fromRoute('xmt_trust_ui.short_read_progress')->toString(),
            'laterSyncUrl' => Url::fromRoute('xmt_trust_ui.short_read_later_sync')->toString(),
            'uid' => (int) $this->currentUser()->id(),
            'csrfToken' => \Drupal::csrfToken()->get('rest'),
          ],
        ],
      ],
      '#cache' => [
        'contexts' => [
          'url.query_args:level',
          'url.query_args:mode',
          'url.query_args:domain',
          'url.query_args:source',
          'url.query_args:focus',
          'user.permissions',
          'user',
        ],
        'tags' => ['node_list:article'],
        'max-age' => 60,
      ],
    ];

    if ($mode === 'immerse') {
      $build['#attributes'] = ['class' => ['xmt-short-page', 'xmt-short-page--immerse']];
    }

    return $build;
  }

  /**
   * Article detail for short-news.
   */
  public function detail(NodeInterface $node, Request $request): array {
    if ($node->bundle() !== 'article' || !$node->isPublished() || !$node->access('view')) {
      throw new NotFoundHttpException();
    }
    $level = (string) $request->query->get('level', 'trusted');
    $domain = trim((string) $request->query->get('domain', ''));
    $card = $this->buildCard($node, TRUE, $level, $domain);
    $feed_query = array_filter([
      'level' => $level === 'trusted' ? NULL : $level,
      'domain' => $domain !== '' ? $domain : NULL,
    ]);
    $immerse_query = $feed_query + ['mode' => 'immerse', 'focus' => (int) $node->id()];

    $absolute = Url::fromRoute('xmt_trust_ui.short_read_detail', ['node' => $node->id()], ['absolute' => TRUE])->toString();
    $share_img = Url::fromRoute('xmt_trust_ui.short_read_share', ['node' => $node->id()], ['absolute' => TRUE])->toString();
    $head = $this->ogHead(
      $node->label(),
      $card['brief'] !== '' ? $card['brief'] : (string) $this->t('可信短闻'),
      $absolute,
      $share_img,
    );
    $head = array_merge($head, $this->jsonLdNews($card + ['share_image' => $share_img], $absolute));
    $head = array_merge($head, $this->rssAlternateHead($feed_query));

    $related = [];
    foreach ($this->loadRelated($node, $level, 6) as $related_node) {
      $related[] = $this->buildCard($related_node, FALSE, $level, $domain);
    }

    $neighbors = $this->loadNeighbors($node, $level, $domain);
    $prev_card = $neighbors['prev'] ? $this->buildCard($neighbors['prev'], FALSE, $level, $domain) : NULL;
    $next_card = $neighbors['next'] ? $this->buildCard($neighbors['next'], FALSE, $level, $domain) : NULL;

    $help_url = Url::fromRoute('xmt_trust_ui.help_wechat', [], [
      'query' => ['u' => $absolute],
    ])->toString();

    $official_media_url = '';
    if ($domain !== '' && \Drupal\xmt_trust_ui\OfficialMediaChannels::isValid($domain)) {
      $official_media_url = Url::fromRoute('xmt_trust_ui.official_media_channel', [
        'channel' => $domain,
      ])->toString();
    }
    elseif (in_array($level, ['official', 'l1', 'l1_official'], TRUE)) {
      $official_media_url = Url::fromRoute('xmt_trust_ui.official_media')->toString();
    }

    return [
      '#theme' => 'xmt_short_read_detail',
      '#card' => $card,
      '#related' => $related,
      '#prev' => $prev_card,
      '#next' => $next_card,
      '#feed_url' => Url::fromRoute('xmt_trust_ui.short_read', [], ['query' => $feed_query])->toString(),
      '#immerse_url' => Url::fromRoute('xmt_trust_ui.short_read', [], ['query' => $immerse_query])->toString(),
      '#rss_url' => Url::fromRoute('xmt_trust_ui.short_news_rss', [], ['query' => $feed_query])->toString(),
      '#today_url' => Url::fromRoute('xmt_trust_ui.short_read_today', [], ['query' => $feed_query])->toString(),
      '#official_media_url' => $official_media_url,
      '#share_url' => $absolute,
      '#share_image_url' => $share_img,
      '#wechat_help_url' => $help_url,
      '#attached' => [
        'library' => ['xmt_trust_ui/short_read'],
        'html_head' => $head,
        'drupalSettings' => [
          'xmtShortRead' => [
            'shareUrl' => $absolute,
            'wechatHelpUrl' => $help_url,
            'progressUrl' => Url::fromRoute('xmt_trust_ui.short_read_progress')->toString(),
            'laterSyncUrl' => Url::fromRoute('xmt_trust_ui.short_read_later_sync')->toString(),
            'uid' => (int) $this->currentUser()->id(),
            'csrfToken' => \Drupal::csrfToken()->get('rest'),
          ],
        ],
      ],
      '#cache' => [
        'tags' => array_merge($node->getCacheTags(), ['node_list:article']),
        'contexts' => ['user.permissions', 'url.query_args', 'user'],
      ],
    ];
  }

  /**
   * Title callback for detail route.
   */
  public function detailTitle(NodeInterface $node): string {
    return $node->label();
  }

  /**
   * One-screen daily brief of recent trusted shorts.
   */
  public function today(Request $request): array {
    $level = (string) $request->query->get('level', 'trusted');
    $domain = trim((string) $request->query->get('domain', ''));
    $tz = new \DateTimeZone('Asia/Shanghai');
    $start = (new \DateTimeImmutable('today', $tz))->getTimestamp();
    $nodes = $this->loadNodesSince($level, $start, 12, $domain);
    $is_fallback = FALSE;
    if (!$nodes) {
      // Fallback: latest 10 if nothing landed today yet.
      $nodes = $this->loadNodes($level, 0, 10, $domain);
      $is_fallback = (bool) $nodes;
    }
    $cards = [];
    foreach ($nodes as $node) {
      $cards[] = $this->buildCard($node, FALSE, $level, $domain);
    }
    $query = array_filter([
      'level' => $level === 'trusted' ? NULL : $level,
      'domain' => $domain !== '' ? $domain : NULL,
    ]);
    $date_label = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
    return [
      '#theme' => 'xmt_short_read_today',
      '#cards' => $cards,
      '#date_label' => $date_label,
      '#level' => $level,
      '#domain' => $domain,
      '#is_fallback' => $is_fallback,
      '#feed_url' => Url::fromRoute('xmt_trust_ui.short_read', [], ['query' => $query])->toString(),
      '#immerse_url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
        'query' => $query + ['mode' => 'immerse'],
      ])->toString(),
      '#rss_url' => Url::fromRoute('xmt_trust_ui.short_news_rss', [], ['query' => $query])->toString(),
      '#search_url' => Url::fromRoute('xmt_trust_ui.search', [], [
        'query' => array_filter(['level' => $level === 'trusted' ? NULL : $level]),
      ])->toString(),
      '#filters' => $this->todayFilters($level, $domain),
      '#domain_filters' => $this->todayDomainFilters($level, $domain),
      '#domain_rail' => $this->todayDomainRail($level, $domain),
      '#attached' => [
        'library' => ['xmt_trust_ui/short_read'],
        'html_head' => $this->ogHead(
          (string) $this->t('今日简报 · @d', ['@d' => $date_label]),
          (string) $this->t('今日可信短闻一屏速览'),
          Url::fromRoute('xmt_trust_ui.short_read_today', [], [
            'query' => $query,
            'absolute' => TRUE,
          ])->toString(),
        ),
      ],
      '#cache' => [
        'contexts' => ['url.query_args:level', 'url.query_args:domain', 'user.permissions'],
        'tags' => ['node_list:article'],
        'max-age' => 120,
      ],
    ];
  }

  /**
   * @return list<\Drupal\node\NodeInterface>
   */
  protected function loadNodesSince(string $level, int $since, int $limit, string $domain = ''): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $levels = match ($level) {
      'l0', 'l0_aggregate', 'aggregate' => ['l0_aggregate'],
      'l1', 'l1_official', 'official' => ['l1_official'],
      'l2', 'l2_enterprise', 'enterprise' => ['l2_enterprise'],
      'official_media' => ['l1_official'],
      default => ['l1_official', 'l2_enterprise', 'l0_aggregate'],
    };
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', 1)
      ->condition('field_trust_level', $levels, 'IN')
      ->condition('created', $since, '>=')
      ->sort('created', 'DESC')
      ->range(0, $limit);
    $this->applyDomainCondition($query, $domain);
    $nids = $query->execute();
    return $nids ? array_values($storage->loadMultiple($nids)) : [];
  }

  /**
   * JSON feed for infinite scroll (browse + immerse).
   */
  public function api(Request $request): JsonResponse {
    $level = (string) $request->query->get('level', 'trusted');
    $domain = trim((string) $request->query->get('domain', ''));
    $source = trim((string) $request->query->get('source', ''));
    $after = (int) $request->query->get('after', 0);
    $mode = $request->query->get('mode', 'immerse') === 'browse' ? 'browse' : 'immerse';
    $nodes = $this->loadNodes($level, $after, 12, $domain, $source);
    $items = [];
    $last = $after;
    foreach ($nodes as $node) {
      $items[] = $this->buildCard($node, FALSE, $level, $domain);
      $last = (int) $node->id();
    }
    $official_media_url = '';
    if ($domain !== '' && \Drupal\xmt_trust_ui\OfficialMediaChannels::isValid($domain)) {
      $official_media_url = Url::fromRoute('xmt_trust_ui.official_media_channel', [
        'channel' => $domain,
      ], ['absolute' => TRUE])->toString();
    }
    elseif (in_array($level, ['official', 'l1', 'l1_official'], TRUE)) {
      $official_media_url = Url::fromRoute('xmt_trust_ui.official_media', [], ['absolute' => TRUE])->toString();
    }
    return new JsonResponse([
      'schema' => 'xmt.short_read.v1',
      'level' => $level,
      'domain' => $domain,
      'source' => $source,
      'mode' => $mode,
      'meta' => [
        'rss_url' => Url::fromRoute('xmt_trust_ui.short_news_rss', [], [
          'query' => array_filter([
            'level' => $level === 'trusted' ? NULL : $level,
            'domain' => $domain !== '' ? $domain : NULL,
            'source' => $source !== '' ? $source : NULL,
          ]),
          'absolute' => TRUE,
        ])->toString(),
        'feed_url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
          'query' => array_filter([
            'level' => $level === 'trusted' ? NULL : $level,
            'domain' => $domain !== '' ? $domain : NULL,
            'source' => $source !== '' ? $source : NULL,
          ]),
          'absolute' => TRUE,
        ])->toString(),
        'search_url' => Url::fromRoute('xmt_trust_ui.search', [], [
          'query' => array_filter([
            'level' => $level === 'trusted' ? NULL : $level,
          ]),
          'absolute' => TRUE,
        ])->toString(),
        'later_url' => Url::fromRoute('xmt_trust_ui.short_read_later', [], ['absolute' => TRUE])->toString(),
        'official_media_url' => $official_media_url !== '' ? $official_media_url : NULL,
        'help_wechat_url' => Url::fromRoute('xmt_trust_ui.help_wechat', [], ['absolute' => TRUE])->toString(),
        'publishers_url' => Url::fromRoute('xmt_trust_ui.publishers_directory', [], ['absolute' => TRUE])->toString(),
        'stats_url' => Url::fromRoute('xmt_trust_ui.stats', [], ['absolute' => TRUE])->toString(),
        'sitemap_url' => Url::fromRoute('xmt_trust_ui.trust_sitemap', [], ['absolute' => TRUE])->toString(),
        'official_media_index_url' => Url::fromRoute('xmt_trust_ui.official_media', [], ['absolute' => TRUE])->toString(),
        'today_url' => Url::fromRoute('xmt_trust_ui.short_read_today', [], [
          'query' => array_filter([
            'level' => $level === 'trusted' ? NULL : $level,
            'domain' => $domain !== '' ? $domain : NULL,
          ]),
          'absolute' => TRUE,
        ])->toString(),
      ],
      'items' => $items,
      'next_after' => $items ? $last : NULL,
    ]);
  }

  /**
   * @return \Drupal\node\NodeInterface[]
   */
  protected function loadNodes(string $level, int $after, int $limit, string $domain = '', string $source = ''): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range(0, $limit);

    $levels = match ($level) {
      'l0', 'l0_aggregate', 'aggregate' => ['l0_aggregate'],
      'l1', 'l1_official', 'official' => ['l1_official'],
      'l2', 'l2_enterprise', 'enterprise' => ['l2_enterprise'],
      'official_media' => ['l1_official'],
      default => ['l1_official', 'l2_enterprise', 'l0_aggregate'],
    };
    $query->condition('field_trust_level', $levels, 'IN');

    $this->applyDomainCondition($query, $domain);
    if ($source !== '') {
      $query->condition('field_source_name', $source);
    }

    if ($after > 0) {
      $after_node = $storage->load($after);
      if ($after_node instanceof NodeInterface) {
        $query->condition('created', $after_node->getCreatedTime(), '<');
      }
    }

    $nids = $query->execute();
    return $nids ? array_values($storage->loadMultiple($nids)) : [];
  }

  /**
   * Related short-news: same source first, then domain, then trust pool.
   *
   * @return list<\Drupal\node\NodeInterface>
   */
  protected function loadRelated(NodeInterface $node, string $level, int $limit = 6): array {
    $exclude = [(int) $node->id()];
    $domain = '';
    if ($node->hasField('field_domain') && !$node->get('field_domain')->isEmpty()) {
      $domain = (string) $node->get('field_domain')->value;
    }
    $source = '';
    if ($node->hasField('field_source_name') && !$node->get('field_source_name')->isEmpty()) {
      $source = trim((string) $node->get('field_source_name')->value);
    }

    $picked = [];
    $add = function (array $candidates) use (&$picked, $exclude, $limit): bool {
      foreach ($candidates as $candidate) {
        $nid = (int) $candidate->id();
        if (in_array($nid, $exclude, TRUE) || isset($picked[$nid])) {
          continue;
        }
        $picked[$nid] = $candidate;
        if (count($picked) >= $limit) {
          return TRUE;
        }
      }
      return FALSE;
    };

    if ($source !== '') {
      if ($add($this->loadNodesBySource($level, $source, $limit + 4))) {
        return array_values($picked);
      }
    }
    if ($domain !== '') {
      if ($add($this->loadNodes($level, 0, $limit + 4, $domain))) {
        return array_values($picked);
      }
    }
    $add($this->loadNodes($level, 0, $limit + 12, ''));
    return array_values($picked);
  }

  /**
   * @return list<\Drupal\node\NodeInterface>
   */
  protected function loadNodesBySource(string $level, string $source, int $limit): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $levels = match ($level) {
      'l0', 'l0_aggregate', 'aggregate' => ['l0_aggregate'],
      'l1', 'l1_official', 'official' => ['l1_official'],
      'l2', 'l2_enterprise', 'enterprise' => ['l2_enterprise'],
      'official_media' => ['l1_official'],
      default => ['l1_official', 'l2_enterprise', 'l0_aggregate'],
    };
    $nids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', 1)
      ->condition('field_trust_level', $levels, 'IN')
      ->condition('field_source_name', $source)
      ->sort('created', 'DESC')
      ->range(0, $limit)
      ->execute();
    return $nids ? array_values($storage->loadMultiple($nids)) : [];
  }

  /**
   * Adjacent items in feed order (created DESC): next = older, prev = newer.
   *
   * @return array{prev: ?\Drupal\node\NodeInterface, next: ?\Drupal\node\NodeInterface}
   */
  protected function loadNeighbors(NodeInterface $node, string $level, string $domain = ''): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $levels = match ($level) {
      'l0', 'l0_aggregate', 'aggregate' => ['l0_aggregate'],
      'l1', 'l1_official', 'official' => ['l1_official'],
      'l2', 'l2_enterprise', 'enterprise' => ['l2_enterprise'],
      'official_media' => ['l1_official'],
      default => ['l1_official', 'l2_enterprise', 'l0_aggregate'],
    };
    $created = (int) $node->getCreatedTime();
    $nid = (int) $node->id();

    $base = function () use ($storage, $levels, $domain) {
      $q = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'article')
        ->condition('status', 1)
        ->condition('field_trust_level', $levels, 'IN')
        ->range(0, 1);
      $this->applyDomainCondition($q, $domain);
      return $q;
    };

    // Older in feed (next article when scrolling down the list).
    $next_q = $base()->sort('created', 'DESC')->condition('created', $created, '<');
    $next_ids = $next_q->execute();
    if (!$next_ids) {
      $next_q = $base()->sort('created', 'DESC')
        ->condition('created', $created, '=')
        ->condition('nid', $nid, '<');
      $next_ids = $next_q->execute();
    }

    // Newer in feed (previous when reading chronologically down).
    $prev_q = $base()->sort('created', 'ASC')->condition('created', $created, '>');
    $prev_ids = $prev_q->execute();
    if (!$prev_ids) {
      $prev_q = $base()->sort('created', 'ASC')
        ->condition('created', $created, '=')
        ->condition('nid', $nid, '>');
      $prev_ids = $prev_q->execute();
    }

    $next = $next_ids ? $storage->load(reset($next_ids)) : NULL;
    $prev = $prev_ids ? $storage->load(reset($prev_ids)) : NULL;

    return [
      'prev' => $prev instanceof NodeInterface ? $prev : NULL,
      'next' => $next instanceof NodeInterface ? $next : NULL,
    ];
  }

  /**
   * Trust filters for /read/today.
   */
  protected function todayFilters(string $active, string $domain): array {
    $map = [
      'trusted' => $this->t('综合'),
      'official' => $this->t('官方'),
      'enterprise' => $this->t('企业'),
      'aggregate' => $this->t('汇聚'),
    ];
    $out = [];
    foreach ($map as $key => $label) {
      $query = array_filter([
        'level' => $key === 'trusted' ? NULL : $key,
        'domain' => $domain !== '' ? $domain : NULL,
      ]);
      $out[] = [
        'key' => $key,
        'label' => (string) $label,
        'active' => $active === $key,
        'url' => Url::fromRoute('xmt_trust_ui.short_read_today', [], ['query' => $query])->toString(),
      ];
    }
    return $out;
  }

  /**
   * Compact domain rail: pinned hot chips + collapsed “更多”.
   *
   * @return array{all: ?array, primary: list<array>, more: list<array>, more_open: bool}
   */
  protected function domainRail(string $level, string $mode, string $active_domain, string $source = ''): array {
    return $this->buildDomainRail(
      $this->domainFilters($level, $mode, $active_domain, $source),
      $active_domain,
    );
  }

  /**
   * Today page domain rail (same collapse rules, today URLs).
   *
   * @return array{all: ?array, primary: list<array>, more: list<array>, more_open: bool}
   */
  protected function todayDomainRail(string $level, string $active_domain): array {
    return $this->buildDomainRail(
      $this->todayDomainFilters($level, $active_domain),
      $active_domain,
    );
  }

  /**
   * @param list<array<string, mixed>> $flat
   *
   * @return array{all: ?array, primary: list<array>, more: list<array>, more_open: bool}
   */
  protected function buildDomainRail(array $flat, string $active_domain): array {
    $all = NULL;
    $by_key = [];
    foreach ($flat as $item) {
      $key = (string) ($item['key'] ?? '');
      if ($key === '') {
        $all = $item;
        continue;
      }
      $by_key[$key] = $item;
    }

    $primary = [];
    $used = [];
    foreach (DomainCatalog::PINNED_FILTERS as $slug) {
      if (!isset($by_key[$slug])) {
        continue;
      }
      $primary[] = $by_key[$slug];
      $used[$slug] = TRUE;
    }

    $active_canon = $active_domain !== '' ? DomainCatalog::canonicalize($active_domain) : '';
    if ($active_canon !== '' && empty($used[$active_canon]) && isset($by_key[$active_canon])) {
      array_unshift($primary, $by_key[$active_canon]);
      $used[$active_canon] = TRUE;
    }
    elseif ($active_domain !== '' && empty($used[$active_domain]) && isset($by_key[$active_domain])) {
      array_unshift($primary, $by_key[$active_domain]);
      $used[$active_domain] = TRUE;
    }

    $more = [];
    foreach ($by_key as $slug => $item) {
      if (!empty($used[$slug])) {
        continue;
      }
      $more[] = $item;
    }
    usort($more, static function (array $a, array $b): int {
      return ((int) ($b['count'] ?? 0)) <=> ((int) ($a['count'] ?? 0));
    });

    return [
      'all' => $all,
      'primary' => $primary,
      'more' => $more,
      'more_open' => FALSE,
    ];
  }

  /**
   * Grouped domain chips for 短闻 UI (legacy shape; prefer domainRail).
   *
   * @return list<array{id: string, label: string, items: list<array<string, mixed>>}>
   */
  protected function domainGroups(string $level, string $mode, string $active_domain, string $source = ''): array {
    $rail = $this->domainRail($level, $mode, $active_domain, $source);
    $ordered = [];
    if (!empty($rail['all'])) {
      $ordered[] = [
        'id' => 'all',
        'label' => '',
        'items' => [$rail['all']],
      ];
    }
    if (!empty($rail['primary'])) {
      $ordered[] = [
        'id' => 'hot',
        'label' => (string) $this->t('热门'),
        'items' => $rail['primary'],
      ];
    }
    if (!empty($rail['more'])) {
      $ordered[] = [
        'id' => 'more',
        'label' => (string) $this->t('更多'),
        'items' => $rail['more'],
      ];
    }
    return $ordered;
  }

  /**
   * Applies domain filter including aliases (e.g. robot ↔ ai_robot).
   */
  protected function applyDomainCondition($query, string $domain): void {
    if ($domain === '') {
      return;
    }
    $values = DomainCatalog::expandFilter($domain);
    if (count($values) === 1) {
      $query->condition('field_domain', $values[0]);
      return;
    }
    $query->condition('field_domain', $values, 'IN');
  }

  /**
   * Count articles matching a domain slug (with aliases).
   */
  protected function countDomainArticles(string $slug): int {
    $storage = $this->entityTypeManager()->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', 1);
    $this->applyDomainCondition($query, $slug);
    return (int) $query->count()->execute();
  }

  /**
   * Domain filters for /read/today.
   */
  protected function todayDomainFilters(string $level, string $active_domain): array {
    $out = [[
      'key' => '',
      'label' => (string) $this->t('全部'),
      'active' => $active_domain === '',
      'group' => '',
      'url' => Url::fromRoute('xmt_trust_ui.short_read_today', [], [
        'query' => array_filter([
          'level' => $level === 'trusted' ? NULL : $level,
        ]),
      ])->toString(),
    ]];

    $active_canon = $active_domain !== '' ? DomainCatalog::canonicalize($active_domain) : '';
    foreach (DomainCatalog::groupedForFilters() as $gid => $items) {
      foreach ($items as $item) {
        $slug = $item['slug'];
        $count = $this->countDomainArticles($slug);
        $is_active = $active_canon === $slug || $active_domain === $slug;
        if (!$is_active && $count < 1) {
          continue;
        }
        $hint = trim((string) ($item['hint'] ?? ''));
        if ($count > 0) {
          $hint = ($hint !== '' ? $hint . ' · ' : '') . $this->t('@n 篇', ['@n' => $count]);
        }
        $out[] = [
          'key' => $slug,
          'label' => $item['label'],
          'hint' => (string) $hint,
          'count' => $count,
          'group' => $gid,
          'group_label' => DomainCatalog::GROUPS[$gid] ?? $gid,
          'active' => $is_active,
          'url' => Url::fromRoute('xmt_trust_ui.short_read_today', [], [
            'query' => array_filter([
              'level' => $level === 'trusted' ? NULL : $level,
              'domain' => $slug,
            ]),
          ])->toString(),
        ];
      }
    }
    return $out;
  }

  protected function filters(string $active, string $mode, string $domain, string $source = ''): array {
    $map = [
      'trusted' => $this->t('综合'),
      'official' => $this->t('官方'),
      'enterprise' => $this->t('企业'),
      'aggregate' => $this->t('汇聚'),
    ];
    $out = [];
    foreach ($map as $key => $label) {
      $query = array_filter([
        'level' => $key === 'trusted' ? NULL : $key,
        'mode' => $mode === 'browse' ? 'browse' : NULL,
        'domain' => $domain !== '' ? $domain : NULL,
        'source' => $source !== '' ? $source : NULL,
      ]);
      $out[] = [
        'key' => $key,
        'label' => (string) $label,
        'active' => $active === $key,
        'url' => Url::fromRoute('xmt_trust_ui.short_read', [], ['query' => $query])->toString(),
      ];
    }
    return $out;
  }

  /**
   * Empty-feed helpers: related domains + ops shortcuts (R429).
   *
   * @return array{title: string, hint: string, related: list<array{label: string, url: string, hint: string}>, ops_url: string, all_url: string}|null
   */
  protected function emptyState(string $level, string $mode, string $domain, string $source = ''): ?array {
    $canon = $domain !== '' ? DomainCatalog::canonicalize($domain) : '';
    $label = $canon !== '' ? DomainCatalog::label($canon) : '';
    $meta = NULL;
    if ($canon !== '' && isset(DomainCatalog::DOMAINS[$canon])) {
      $meta = DomainCatalog::DOMAINS[$canon];
    }
    elseif ($canon !== '' && isset(DomainCatalog::BUNDLES[$canon])) {
      $meta = DomainCatalog::BUNDLES[$canon];
    }
    $hint = is_array($meta) ? (string) ($meta['hint'] ?? '') : '';
    if ($source !== '') {
      $title = (string) $this->t('信源「@s」暂无短闻', ['@s' => $source]);
    }
    elseif ($label !== '') {
      $title = (string) $this->t('「@d」暂无短闻', ['@d' => $label]);
    }
    else {
      $title = (string) $this->t('暂无短闻');
    }

    $related = [];
    if ($canon !== '' && is_array($meta)) {
      $gid = (string) ($meta['group'] ?? '');
      foreach (DomainCatalog::DOMAINS as $slug => $dmeta) {
        if ($slug === $canon) {
          continue;
        }
        if ((string) ($dmeta['group'] ?? '') !== $gid) {
          continue;
        }
        $count = $this->countDomainArticles($slug);
        if ($count < 1) {
          continue;
        }
        $related[] = [
          'label' => (string) $dmeta['label'],
          'hint' => (string) ($dmeta['hint'] ?? ''),
          'count' => $count,
          'url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
            'query' => array_filter([
              'level' => $level === 'trusted' ? NULL : $level,
              'mode' => $mode === 'browse' ? 'browse' : NULL,
              'domain' => $slug,
            ]),
          ])->toString(),
        ];
      }
      usort($related, static fn(array $a, array $b): int => ((int) ($b['count'] ?? 0)) <=> ((int) ($a['count'] ?? 0)));
      $related = array_slice($related, 0, 6);
    }

    $ops_url = '';
    $rss_url = Url::fromRoute('xmt_trust_ui.short_news_rss', [], [
      'query' => array_filter([
        'level' => $level === 'trusted' ? NULL : $level,
        'domain' => $domain !== '' ? $domain : NULL,
        'source' => $source !== '' ? $source : NULL,
      ]),
      'absolute' => TRUE,
    ])->toString();
    if ($this->currentUser()->hasPermission('administer xmt trust')) {
      $hot_group = $canon !== '' ? 'hot_' . $canon : '';
      // Prefer group probe when hot_* exists in agent catalog.
      $ops_query = ['status' => 'failed'];
      if ($hot_group !== '' && $hot_group !== 'hot_') {
        $ops_query = ['group' => $hot_group];
      }
      $ops_url = Url::fromRoute('xmt_trust_ui.source_ops', [], [
        'query' => $ops_query,
      ])->toString();
    }

    return [
      'title' => $title,
      'hint' => $hint,
      'related' => $related,
      'ops_url' => $ops_url,
      'rss_url' => $rss_url,
      'all_url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
        'query' => array_filter([
          'level' => $level === 'trusted' ? NULL : $level,
          'mode' => $mode === 'browse' ? 'browse' : NULL,
        ]),
      ])->toString(),
    ];
  }

  /**
   * Domain filters from hot-industry catalog (only with content).
   */
  protected function domainFilters(string $level, string $mode, string $active_domain, string $source = ''): array {
    $out = [[
      'key' => '',
      'label' => (string) $this->t('全部'),
      'active' => $active_domain === '',
      'group' => '',
      'group_label' => '',
      'url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
        'query' => array_filter([
          'level' => $level === 'trusted' ? NULL : $level,
          'mode' => $mode === 'browse' ? 'browse' : NULL,
          'source' => $source !== '' ? $source : NULL,
        ]),
      ])->toString(),
    ]];

    $active_canon = $active_domain !== '' ? DomainCatalog::canonicalize($active_domain) : '';
    $seen = [];
    foreach (DomainCatalog::groupedForFilters() as $gid => $items) {
      foreach ($items as $item) {
        $slug = $item['slug'];
        $count = $this->countDomainArticles($slug);
        $is_active = $active_canon === $slug || $active_domain === $slug;
        if (!$is_active && $count < 1) {
          continue;
        }
        $seen[$slug] = TRUE;
        $hint = trim((string) ($item['hint'] ?? ''));
        if ($count > 0) {
          $hint = ($hint !== '' ? $hint . ' · ' : '') . $this->t('@n 篇', ['@n' => $count]);
        }
        $out[] = [
          'key' => $slug,
          'label' => $item['label'],
          'hint' => (string) $hint,
          'count' => $count,
          'group' => $gid,
          'group_label' => DomainCatalog::GROUPS[$gid] ?? $gid,
          'active' => $is_active,
          'url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
            'query' => array_filter([
              'level' => $level === 'trusted' ? NULL : $level,
              'mode' => $mode === 'browse' ? 'browse' : NULL,
              'domain' => $slug,
              'source' => $source !== '' ? $source : NULL,
            ]),
          ])->toString(),
        ];
      }
    }
    // Active alias/official channel not in catalog filter list.
    if ($active_domain !== '' && $active_canon !== '' && empty($seen[$active_canon]) && empty($seen[$active_domain])) {
      $gid = DomainCatalog::DOMAINS[$active_canon]['group'] ?? 'other';
      $count = $this->countDomainArticles($active_canon);
      $hint = trim((string) (DomainCatalog::DOMAINS[$active_canon]['hint'] ?? ''));
      if ($count > 0) {
        $hint = ($hint !== '' ? $hint . ' · ' : '') . $this->t('@n 篇', ['@n' => $count]);
      }
      $out[] = [
        'key' => $active_canon,
        'label' => DomainCatalog::label($active_canon),
        'hint' => (string) $hint,
        'count' => $count,
        'group' => $gid,
        'group_label' => DomainCatalog::GROUPS[$gid] ?? $gid,
        'active' => TRUE,
        'url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
          'query' => array_filter([
            'level' => $level === 'trusted' ? NULL : $level,
            'mode' => $mode === 'browse' ? 'browse' : NULL,
            'domain' => $active_canon,
            'source' => $source !== '' ? $source : NULL,
          ]),
        ])->toString(),
      ];
    }
    return $out;
  }

  /**
   * Card payload used by Twig and JSON.
   */
  protected function buildCard(NodeInterface $node, bool $detailed = FALSE, string $level_filter = 'trusted', string $domain = ''): array {
    $level = $node->hasField('field_trust_level') && !$node->get('field_trust_level')->isEmpty()
      ? (string) $node->get('field_trust_level')->value
      : 'l0_aggregate';
    $summary = $this->summarizer->forNode($node);

    $source_name = '';
    if ($node->hasField('field_source_name') && !$node->get('field_source_name')->isEmpty()) {
      $source_name = (string) $node->get('field_source_name')->value;
    }
    $source_url = '';
    if ($node->hasField('field_source_url') && !$node->get('field_source_url')->isEmpty()) {
      $source_url = (string) ($node->get('field_source_url')->uri ?? '');
    }
    $publisher = '';
    $publisher_url = '';
    if ($node->hasField('field_publisher') && !$node->get('field_publisher')->isEmpty() && $node->get('field_publisher')->entity) {
      $p = $node->get('field_publisher')->entity;
      $publisher = $p->label();
      $publisher_url = $p->toUrl('canonical')->toString();
    }
    $domain_slug = '';
    if ($node->hasField('field_domain') && !$node->get('field_domain')->isEmpty()) {
      $domain_slug = (string) $node->get('field_domain')->value;
    }

    $body_html = '';
    if ($detailed && $node->hasField('body') && !$node->get('body')->isEmpty()) {
      $raw = (string) ($node->get('body')->value ?? '');
      $format = (string) ($node->get('body')->format ?? 'basic_html');
      if ($format !== '' && \Drupal::moduleHandler()->moduleExists('filter')) {
        $body_html = (string) check_markup($raw, $format);
      }
      else {
        $body_html = Xss::filterAdmin($raw);
      }
    }

    $ctx_query = array_filter([
      'level' => $level_filter === 'trusted' ? NULL : $level_filter,
      'domain' => $domain !== '' ? $domain : NULL,
    ]);

    return [
      'nid' => (int) $node->id(),
      'title' => $node->label(),
      'trust_level' => $level,
      'badge_label' => xmt_trust_badge_label($level),
      'badge_class' => xmt_trust_badge_class($level),
      'brief' => $summary['brief'],
      'keypoints' => $summary['keypoints'],
      'body_text' => $summary['body_text'],
      'body_html' => $body_html,
      'reading_seconds' => $summary['reading_seconds'],
      'engine' => $summary['engine'],
      'source_name' => $source_name,
      'source_feed_url' => $source_name !== ''
        ? Url::fromRoute('xmt_trust_ui.short_read', [], [
          'query' => array_filter([
            'level' => $level_filter === 'trusted' ? NULL : $level_filter,
            'domain' => $domain !== '' ? $domain : NULL,
            'source' => $source_name,
          ]),
        ])->toString()
        : '',
      'source_url' => $source_url,
      'publisher' => $publisher,
      'publisher_url' => $publisher_url,
      'domain' => $domain_slug,
      'domain_label' => DomainCatalog::label($domain_slug),
      'provenance_note' => (string) $this->t('RSS 官方收录 · 可信分层展示'),
      'detail_url' => Url::fromRoute('xmt_trust_ui.short_read_detail', ['node' => $node->id()], [
        'query' => $ctx_query,
      ])->toString(),
      'immerse_url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
        'query' => $ctx_query + ['mode' => 'immerse', 'focus' => (int) $node->id()],
      ])->toString(),
      'share_image_url' => Url::fromRoute('xmt_trust_ui.short_read_share', ['node' => $node->id()], ['absolute' => TRUE])->toString(),
      'node_url' => $node->toUrl('canonical')->toString(),
      'created' => (int) $node->getCreatedTime(),
      'created_label' => \Drupal::service('date.formatter')->format($node->getCreatedTime(), 'short'),
    ];
  }

  /**
   * Open Graph / Twitter card head elements.
   *
   * @return array<int, array>
   */
  protected function ogHead(string $title, string $description, string $url, string $image = ''): array {
    $desc = mb_substr(trim(preg_replace('/\s+/u', ' ', $description) ?? $description), 0, 160);
    $tags = [
      'og:type' => ['property' => 'og:type', 'content' => 'article'],
      'og:title' => ['property' => 'og:title', 'content' => $title],
      'og:description' => ['property' => 'og:description', 'content' => $desc],
      'og:url' => ['property' => 'og:url', 'content' => $url],
      'og:site_name' => ['property' => 'og:site_name', 'content' => 'XMT 短闻'],
      'twitter:card' => ['name' => 'twitter:card', 'content' => $image !== '' ? 'summary_large_image' : 'summary'],
      'twitter:title' => ['name' => 'twitter:title', 'content' => $title],
      'twitter:description' => ['name' => 'twitter:description', 'content' => $desc],
    ];
    if ($image !== '') {
      $tags['og:image'] = ['property' => 'og:image', 'content' => $image];
      $tags['og:image:type'] = ['property' => 'og:image:type', 'content' => 'image/svg+xml'];
      $tags['og:image:width'] = ['property' => 'og:image:width', 'content' => '1200'];
      $tags['og:image:height'] = ['property' => 'og:image:height', 'content' => '630'];
      $tags['twitter:image'] = ['name' => 'twitter:image', 'content' => $image];
    }
    $out = [];
    foreach ($tags as $key => $attrs) {
      $out[] = [
        [
          '#type' => 'html_tag',
          '#tag' => 'meta',
          '#attributes' => $attrs,
        ],
        $key,
      ];
    }
    return $out;
  }

  /**
   * <link rel="alternate" type="application/rss+xml"> for current filters.
   *
   * @param array<string, string> $query_base
   *   Filtered query args (level/domain).
   *
   * @return array<int, array>
   */
  protected function rssAlternateHead(array $query_base): array {
    $href = Url::fromRoute('xmt_trust_ui.short_news_rss', [], [
      'query' => $query_base,
      'absolute' => TRUE,
    ])->toString();
    $title = 'XMT 短闻 RSS';
    if (!empty($query_base['level']) || !empty($query_base['domain'])) {
      $bits = array_filter([
        $query_base['level'] ?? NULL,
        $query_base['domain'] ?? NULL,
      ]);
      $title .= ' · ' . implode('/', $bits);
    }
    return [
      [
        [
          '#type' => 'html_tag',
          '#tag' => 'link',
          '#attributes' => [
            'rel' => 'alternate',
            'type' => 'application/rss+xml',
            'title' => $title,
            'href' => $href,
          ],
        ],
        'xmt_short_rss_alternate',
      ],
    ];
  }

  /**
   * Schema.org NewsArticle JSON-LD for short-news detail.
   *
   * @return array<int, array>
   */
  protected function jsonLdNews(array $card, string $url): array {
    $data = [
      '@context' => 'https://schema.org',
      '@type' => 'NewsArticle',
      'headline' => $card['title'] ?? '',
      'description' => $card['brief'] ?? '',
      'datePublished' => !empty($card['created']) ? gmdate('c', (int) $card['created']) : NULL,
      'url' => $url,
      'mainEntityOfPage' => $url,
      'author' => [
        '@type' => 'Organization',
        'name' => $card['source_name'] ?: ($card['publisher'] ?: 'XMT'),
      ],
      'publisher' => [
        '@type' => 'Organization',
        'name' => 'XMT',
        'url' => Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString(),
      ],
      'image' => !empty($card['share_image']) ? $card['share_image'] : NULL,
      'isAccessibleForFree' => TRUE,
    ];
    if (!empty($card['source_url'])) {
      $data['isBasedOn'] = $card['source_url'];
    }
    $json = json_encode(array_filter($data, static fn($v) => $v !== NULL && $v !== ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return [[
      [
        '#type' => 'html_tag',
        '#tag' => 'script',
        '#value' => $json,
        '#attributes' => ['type' => 'application/ld+json'],
      ],
      'xmt_jsonld_news',
    ]];
  }

}
