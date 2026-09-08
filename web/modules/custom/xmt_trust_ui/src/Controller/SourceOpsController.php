<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\xmt_trust_ui\Service\SourceCatalog;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Admin console for RSS/Atom allowlist operations.
 */
class SourceOpsController extends ControllerBase {

  public function __construct(
    protected SourceCatalog $catalog,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('xmt_trust_ui.source_catalog'),
    );
  }

  /**
   * Dashboard.
   */
  public function dashboard(Request $request): array {
    $group = trim((string) $request->query->get('group', ''));
    $trust = trim((string) $request->query->get('trust', ''));
    $status = trim((string) $request->query->get('status', 'all'));
    $q = trim((string) $request->query->get('q', ''));

    $feeds = $this->catalog->feeds();
    $probe_map_filter = $this->catalog->probeCacheMap();
    $filtered = array_values(array_filter($feeds, function (array $f) use ($group, $trust, $status, $q, $probe_map_filter): bool {
      if ($group !== '' && $f['group'] !== $group) {
        return FALSE;
      }
      if ($trust !== '' && $f['trust_level'] !== $trust) {
        return FALSE;
      }
      if ($status === 'paused' && !$f['paused']) {
        return FALSE;
      }
      if ($status === 'active' && $f['paused']) {
        return FALSE;
      }
      if ($status === 'never') {
        if (isset($probe_map_filter[$f['url']])) {
          return FALSE;
        }
      }
      if ($status === 'fail') {
        if (!isset($probe_map_filter[$f['url']]) || !empty($probe_map_filter[$f['url']]['ok'])) {
          return FALSE;
        }
      }
      if ($status === 'silent' && !$this->catalog->isSilentFeed($f, $probe_map_filter)) {
        return FALSE;
      }
      if ($status === 'empty' && !$this->catalog->isEmptyRssFeed($f, $probe_map_filter)) {
        return FALSE;
      }
      if ($status === 'stale' && !$this->catalog->isStaleProbe($f, $probe_map_filter)) {
        return FALSE;
      }
      if ($q !== '') {
        $hay = mb_strtolower($f['name'] . ' ' . $f['url'] . ' ' . $f['group'] . ' ' . $f['domain']);
        if (!str_contains($hay, mb_strtolower($q))) {
          return FALSE;
        }
      }
      return TRUE;
    }));

    $snap = $this->catalog->agentSnapshot();
    $orphans = $this->catalog->orphans($feeds);
    $groups = [];
    foreach ($feeds as $f) {
      $groups[$f['group']] = ($groups[$f['group']] ?? 0) + 1;
    }
    ksort($groups);

    $paused_n = count(array_filter($feeds, static fn($f) => $f['paused']));
    $with_content = count(array_filter($feeds, static fn($f) => $f['article_count'] > 0));

    $status_scope = array_values(array_filter($feeds, function (array $f) use ($group, $trust, $q): bool {
      if ($group !== '' && $f['group'] !== $group) {
        return FALSE;
      }
      if ($trust !== '' && $f['trust_level'] !== $trust) {
        return FALSE;
      }
      if ($q !== '') {
        $hay = mb_strtolower($f['name'] . ' ' . $f['url'] . ' ' . $f['group'] . ' ' . $f['domain']);
        if (!str_contains($hay, mb_strtolower($q))) {
          return FALSE;
        }
      }
      return TRUE;
    }));
    $status_counts = [
      'all' => count($status_scope),
      'active' => 0,
      'paused' => 0,
      'never' => 0,
      'fail' => 0,
      'silent' => 0,
      'empty' => 0,
      'stale' => 0,
    ];
    foreach ($status_scope as $f) {
      if ($f['paused']) {
        $status_counts['paused']++;
      }
      else {
        $status_counts['active']++;
      }
      if (!isset($probe_map_filter[$f['url']])) {
        $status_counts['never']++;
      }
      elseif (empty($probe_map_filter[$f['url']]['ok'])) {
        $status_counts['fail']++;
      }
      if ($this->catalog->isSilentFeed($f, $probe_map_filter)) {
        $status_counts['silent']++;
      }
      if ($this->catalog->isEmptyRssFeed($f, $probe_map_filter)) {
        $status_counts['empty']++;
      }
      if ($this->catalog->isStaleProbe($f, $probe_map_filter)) {
        $status_counts['stale']++;
      }
    }

    $filter_markup = $this->buildFilterMarkup($groups, $group, $trust, $status, $q, $status_counts);

    $rows = [];
    $date = \Drupal::service('date.formatter');
    $probe_map = $this->catalog->probeCacheMap();
    foreach ($filtered as $f) {
      $toggle = Url::fromRoute('xmt_trust_ui.source_ops_toggle', [], [
        'query' => [
          'url' => $f['url'],
          'destination' => Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'trust' => $trust ?: NULL,
              'status' => $status !== 'all' ? $status : NULL,
              'q' => $q !== '' ? $q : NULL,
            ]),
          ])->toString(),
          'token' => \Drupal::csrfToken()->get('xmt_source_ops_toggle:' . $f['url']),
        ],
      ]);
      $probe = Url::fromRoute('xmt_trust_ui.source_ops_probe', [], [
        'query' => [
          'url' => $f['url'],
          'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe:' . $f['url']),
        ],
      ]);
      $dry_feed = Url::fromRoute('xmt_trust_ui.source_ops_dryrun', [], [
        'query' => [
          'url' => $f['url'],
          'group' => $f['group'],
          'token' => \Drupal::csrfToken()->get('xmt_source_ops_dryrun_feed:' . $f['url']),
        ],
      ]);
      $search = Url::fromRoute('xmt_trust_ui.search', [], [
        'query' => ['q' => $f['name']],
      ]);
      $short_src = Url::fromRoute('xmt_trust_ui.short_read', [], [
        'query' => ['source' => $f['name']],
        'absolute' => TRUE,
      ]);
      $health = Url::fromRoute('xmt_trust_ui.source_health', [], [
        'query' => array_filter(['group' => $f['group'] ?: NULL]),
      ]);
      $ops = [
        Link::fromTextAndUrl($f['paused'] ? $this->t('恢复') : $this->t('暂停'), $toggle)->toString(),
        Link::fromTextAndUrl($this->t('探测'), $probe)->toString(),
        Link::fromTextAndUrl($this->t('预览'), $dry_feed)->toString(),
        Link::fromTextAndUrl($this->t('短闻'), $short_src)->toString(),
        '<button type="button" class="button button--small xmt-source-ops-copy-short" data-xmt-copy-short="'
          . htmlspecialchars($short_src->toString(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
          . '" title="' . htmlspecialchars((string) $this->t('复制 /read?source=… 绝对短链'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
          . '">' . $this->t('复制短链') . '</button>',
        Link::fromTextAndUrl($this->t('搜索'), $search)->toString(),
        Link::fromTextAndUrl($this->t('健康'), $health)->toString(),
      ];
      $probe_map = $probe_map ?? [];
      $probe_cell = '—';
      if (isset($probe_map[$f['url']])) {
        $p = $probe_map[$f['url']];
        $age = '';
        if (!empty($p['checked'])) {
          $secs = max(0, time() - (int) $p['checked']);
          if ($secs < 60) {
            $age = $this->t('@n秒前', ['@n' => $secs]);
          }
          elseif ($secs < 3600) {
            $age = $this->t('@n分钟前', ['@n' => (int) floor($secs / 60)]);
          }
          elseif ($secs < 86400) {
            $age = $this->t('@n小时前', ['@n' => (int) floor($secs / 3600)]);
          }
          else {
            $age = $this->t('@n天前', ['@n' => (int) floor($secs / 86400)]);
          }
        }
        $items_label = isset($p['item_count'])
          ? ' · ' . $this->t('@n条', ['@n' => (int) $p['item_count']])
          : '';
        $label = ($p['ok'] ? $this->t('通') : $this->t('败'))
          . $items_label
          . ($age !== '' ? ' · ' . $age : '')
          . ' · ' . $date->format($p['checked'], 'short');
        if (empty($p['ok'])) {
          $msg = trim((string) ($p['message'] ?? ''));
          if ($msg !== '') {
            $short_msg = mb_strlen($msg) > 48 ? (mb_substr($msg, 0, 48) . '…') : $msg;
            $label .= ' · ' . $short_msg;
          }
          $probe_cell = [
            'data' => [
              '#type' => 'link',
              '#title' => $label,
              '#url' => Url::fromRoute('xmt_trust_ui.source_ops', [], [
                'query' => array_filter([
                  'group' => $group ?: NULL,
                  'trust' => $trust ?: NULL,
                  'status' => 'fail',
                  'q' => $q !== '' ? $q : NULL,
                ]),
              ]),
              '#attributes' => $msg !== '' ? ['title' => $msg] : [],
            ],
          ];
        }
        else {
          $probe_status = NULL;
          if ($this->catalog->isEmptyRssFeed($f, $probe_map)) {
            $probe_status = 'empty';
          }
          elseif ($this->catalog->isSilentFeed($f, $probe_map)) {
            $probe_status = 'silent';
          }
          elseif ($this->catalog->isStaleProbe($f, $probe_map)) {
            $probe_status = 'stale';
          }
          if ($probe_status !== NULL) {
            $probe_cell = [
              'data' => [
                '#type' => 'link',
                '#title' => $label,
                '#url' => Url::fromRoute('xmt_trust_ui.source_ops', [], [
                  'query' => array_filter([
                    'group' => $group ?: NULL,
                    'trust' => $trust ?: NULL,
                    'status' => $probe_status,
                    'q' => $q !== '' ? $q : NULL,
                  ]),
                ]),
              ],
            ];
          }
          else {
            $probe_cell = $label;
          }
        }
      }
      else {
        $probe_cell = [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('从未'),
            '#url' => Url::fromRoute('xmt_trust_ui.source_ops', [], [
              'query' => array_filter([
                'group' => $group ?: NULL,
                'trust' => $trust ?: NULL,
                'status' => 'never',
                'q' => $q !== '' ? $q : NULL,
              ]),
            ]),
          ],
        ];
      }
      $probe_failed = isset($probe_map[$f['url']]) && empty($probe_map[$f['url']]['ok']);
      $probe_never = !isset($probe_map[$f['url']]);
      $probe_ok = isset($probe_map[$f['url']]) && !empty($probe_map[$f['url']]['ok']);
      $row = [
        $f['group'],
        $this->probeResultNameCell((string) ($f['name'] ?? '')),
        [
          'data' => [
            '#markup' => Link::fromTextAndUrl(
              mb_strlen($f['url']) > 56 ? mb_substr($f['url'], 0, 53) . '…' : $f['url'],
              Url::fromUri($f['url']),
              ['attributes' => ['target' => '_blank', 'rel' => 'noopener noreferrer', 'title' => $f['url']]]
            )->toString()
              . ' <button type="button" class="button button--small xmt-source-ops-copy-feed" data-xmt-copy-feed="'
              . htmlspecialchars($f['url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
              . '">' . $this->t('复制') . '</button>',
          ],
        ],
        $f['site'],
        $f['domain'],
        $f['trust_level'],
        $f['paused'] ? $this->t('已暂停') : $this->t('采集中'),
        $probe_cell,
        $f['article_count'],
        $f['last_created'] ? $date->format($f['last_created'], 'short') : '—',
        ['data' => ['#markup' => implode(' · ', $ops)]],
      ];
      if ($probe_failed) {
        $rows[] = [
          'data' => $row,
          'class' => ['xmt-source-ops-row--fail'],
        ];
      }
      elseif ($probe_never) {
        $rows[] = [
          'data' => $row,
          'class' => ['xmt-source-ops-row--never'],
        ];
      }
      elseif ($probe_ok) {
        $rows[] = [
          'data' => $row,
          'class' => ['xmt-source-ops-row--ok'],
        ];
      }
      else {
        $rows[] = $row;
      }
    }

    $orphan_rows = [];
    foreach (array_slice($orphans, 0, 30) as $o) {
      $search = Url::fromRoute('xmt_trust_ui.search', [], [
        'query' => ['q' => $o['name']],
      ]);
      $short = Url::fromRoute('xmt_trust_ui.short_read', [], [
        'query' => ['source' => $o['name']],
      ]);
      $orphan_rows[] = [
        [
          'data' => [
            '#type' => 'link',
            '#title' => $o['name'],
            '#url' => $search,
          ],
        ],
        $o['count'],
        $o['last'] ? $date->format($o['last'], 'short') : '—',
        [
          'data' => [
            '#markup' => Link::fromTextAndUrl($this->t('搜索'), $search)->toString()
              . ' · '
              . Link::fromTextAndUrl($this->t('短闻'), $short)->toString(),
          ],
        ],
      ];
    }

    $log_pre = $snap['log_tail'] !== ''
      ? '<pre class="xmt-source-ops-log">' . htmlspecialchars($snap['log_tail'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>'
      : '<p>' . $this->t('暂无 last_run.log') . '</p>';

    $dry_last = \Drupal::state()->get('xmt_source_ops.last_dry_run', []);
    $dry_preview = '';
    if (is_array($dry_last) && !empty($dry_last['at'])) {
      $dry_preview = '<h3 id="xmt-last-dry-run">' . $this->t('上次 dry-run') . '</h3><p>'
        . $this->t('分组 @g · @ok · 退出码 @c · @t', [
          '@g' => $dry_last['group'] ?? '—',
          '@ok' => !empty($dry_last['ok']) ? $this->t('完成') : $this->t('异常'),
          '@c' => $dry_last['code'] ?? '—',
          '@t' => $date->format((int) $dry_last['at'], 'short'),
        ]) . '</p><pre class="xmt-source-ops-log">'
        . htmlspecialchars((string) ($dry_last['tail'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</pre>';
    }

    $snap = $this->catalog->agentSnapshot();
    $agent_alert = '';
    if (!empty($snap['log_tail']) && preg_match('/\b(FAIL|ERR|error|Exception|Traceback)\b/i', $snap['log_tail'])) {
      $agent_alert = '<div class="messages messages--warning"><p>'
        . $this->t('Agent 最近日志含失败关键字，请检查下方日志或服务器 cron。')
        . ' '
        . Link::fromTextAndUrl($this->t('健康'), Url::fromRoute('xmt_trust_ui.source_health'))->toString()
        . '</p></div>';
    }
    elseif (!empty($snap['log_mtime']) && (time() - (int) $snap['log_mtime']) > 2 * 86400) {
      $agent_alert = '<div class="messages messages--warning"><p>'
        . $this->t('Agent last_run.log 超过 48 小时未更新，采集可能已停。')
        . ' '
        . Link::fromTextAndUrl($this->t('健康'), Url::fromRoute('xmt_trust_ui.source_health'))->toString()
        . '</p></div>';
    }
    elseif (
      $snap['last_published'] !== NULL
      && (int) $snap['last_published'] === 0
      && ($snap['last_run_mode'] ?? '') !== 'dry-run'
      && !empty($snap['log_mtime'])
      && (time() - (int) $snap['log_mtime']) < 6 * 3600
    ) {
      $agent_alert = '<div class="messages messages--warning"><p>'
        . $this->t('最近一次 Agent 运行 published=0（errors=@e）。可查看静默信源或做分组 dry-run。', [
          '@e' => $snap['last_errors'] ?? '—',
        ])
        . ' '
        . Link::fromTextAndUrl($this->t('静默'), Url::fromRoute('xmt_trust_ui.source_ops', [], [
          'query' => ['status' => 'silent'],
        ]))->toString()
        . ' · '
        . Link::fromTextAndUrl($this->t('健康'), Url::fromRoute('xmt_trust_ui.source_health'))->toString()
        . (!empty($snap['last_ingest_at'])
          ? ' · ' . Link::fromTextAndUrl($this->t('上次入库'), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'fragment' => 'xmt-last-ingest',
          ]))->toString()
          : '')
        . '</p></div>';
    }

    $group_actions = '<div class="xmt-source-ops-filter-block"><strong>' . $this->t('分组操作') . '</strong> ';
    $shown = 0;
    foreach (array_keys($groups) as $g) {
      if ($group !== '' && $group !== $g) {
        continue;
      }
      $dry = Url::fromRoute('xmt_trust_ui.source_ops_dryrun', [], [
        'query' => [
          'group' => $g,
          'token' => \Drupal::csrfToken()->get('xmt_source_ops_dryrun:' . $g),
        ],
      ])->toString();
      $probe_g = Url::fromRoute('xmt_trust_ui.source_ops_probe_group', [], [
        'query' => [
          'group' => $g,
          'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe_group:' . $g),
        ],
      ])->toString();
      $pause = Url::fromRoute('xmt_trust_ui.source_ops_group_pause', [], [
        'query' => [
          'group' => $g,
          'op' => 'pause',
          'token' => \Drupal::csrfToken()->get('xmt_source_ops_group:' . $g . ':pause'),
          'destination' => Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter(['group' => $g]),
          ])->toString(),
        ],
      ])->toString();
      $resume = Url::fromRoute('xmt_trust_ui.source_ops_group_pause', [], [
        'query' => [
          'group' => $g,
          'op' => 'resume',
          'token' => \Drupal::csrfToken()->get('xmt_source_ops_group:' . $g . ':resume'),
          'destination' => Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter(['group' => $g]),
          ])->toString(),
        ],
      ])->toString();
      $g_esc = htmlspecialchars($g, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      $group_actions .= '<span class="xmt-source-ops-group">' . $g_esc . ': '
        . '<a class="button" href="' . htmlspecialchars($dry, ENT_QUOTES, 'UTF-8') . '">dry-run</a> '
        . '<a class="button" href="' . htmlspecialchars($probe_g, ENT_QUOTES, 'UTF-8') . '">' . $this->t('探测') . '</a> '
        . '<a class="button" href="' . htmlspecialchars($pause, ENT_QUOTES, 'UTF-8') . '">' . $this->t('整组暂停') . '</a> '
        . '<a class="button" href="' . htmlspecialchars($resume, ENT_QUOTES, 'UTF-8') . '">' . $this->t('整组恢复') . '</a></span> ';
      if ($group === '') {
        $shown++;
        if ($shown >= 6) {
          $group_actions .= '<span class="description">' . $this->t('（筛选分组后显示该组全部操作）') . '</span>';
          break;
        }
      }
    }
    $group_actions .= '</div>';

    $fail_n = (int) ($status_counts['fail'] ?? 0);
    $never_n = (int) ($status_counts['never'] ?? 0);
    $silent_n = (int) ($status_counts['silent'] ?? 0);
    $empty_n = (int) ($status_counts['empty'] ?? 0);
    $stale_n = (int) ($status_counts['stale'] ?? 0);
    $paused_bulk_n = (int) ($status_counts['paused'] ?? 0);
    $dest_ops = Url::fromRoute('xmt_trust_ui.source_ops', [], [
      'query' => array_filter([
        'group' => $group ?: NULL,
        'trust' => $trust ?: NULL,
        'status' => $status !== 'all' ? $status : NULL,
        'q' => $q !== '' ? $q : NULL,
      ]),
    ])->toString();
    $token_suffix = $group !== '' ? $group : '*';
    $pause_failed = Url::fromRoute('xmt_trust_ui.source_ops_pause_failed', [], [
      'query' => array_filter([
        'group' => $group !== '' ? $group : NULL,
        'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_failed:' . $token_suffix),
        'destination' => $dest_ops,
      ]),
    ])->toString();
    $pause_never = Url::fromRoute('xmt_trust_ui.source_ops_pause_never', [], [
      'query' => array_filter([
        'group' => $group !== '' ? $group : NULL,
        'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_never:' . $token_suffix),
        'destination' => $dest_ops,
      ]),
    ])->toString();
    $pause_silent = Url::fromRoute('xmt_trust_ui.source_ops_pause_silent', [], [
      'query' => array_filter([
        'group' => $group !== '' ? $group : NULL,
        'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_silent:' . $token_suffix),
        'destination' => $dest_ops,
      ]),
    ])->toString();
    $pause_empty = Url::fromRoute('xmt_trust_ui.source_ops_pause_empty', [], [
      'query' => array_filter([
        'group' => $group !== '' ? $group : NULL,
        'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_empty:' . $token_suffix),
        'destination' => $dest_ops,
      ]),
    ])->toString();
    $pause_stale = Url::fromRoute('xmt_trust_ui.source_ops_pause_stale', [], [
      'query' => array_filter([
        'group' => $group !== '' ? $group : NULL,
        'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_stale:' . $token_suffix),
        'destination' => $dest_ops,
      ]),
    ])->toString();
    $resume_paused = Url::fromRoute('xmt_trust_ui.source_ops_resume_paused', [], [
      'query' => array_filter([
        'group' => $group !== '' ? $group : NULL,
        'token' => \Drupal::csrfToken()->get('xmt_source_ops_resume_paused:' . $token_suffix),
        'destination' => $dest_ops,
      ]),
    ])->toString();
    $bulk = '<div class="xmt-source-ops-filter-block"><strong>' . $this->t('批量') . '</strong> '
      . '<a class="button button--danger" href="' . htmlspecialchars($pause_failed, ENT_QUOTES, 'UTF-8') . '">'
      . $this->t('暂停失败（@n）', ['@n' => $fail_n])
      . '</a> '
      . '<a class="button" href="' . htmlspecialchars($pause_never, ENT_QUOTES, 'UTF-8') . '">'
      . $this->t('暂停从未（@n）', ['@n' => $never_n])
      . '</a> '
      . '<a class="button" href="' . htmlspecialchars($pause_silent, ENT_QUOTES, 'UTF-8') . '">'
      . $this->t('暂停静默（@n）', ['@n' => $silent_n])
      . '</a> '
      . '<a class="button" href="' . htmlspecialchars($pause_empty, ENT_QUOTES, 'UTF-8') . '">'
      . $this->t('暂停空源（@n）', ['@n' => $empty_n])
      . '</a> '
      . '<a class="button" href="' . htmlspecialchars($pause_stale, ENT_QUOTES, 'UTF-8') . '">'
      . $this->t('暂停过期（@n）', ['@n' => $stale_n])
      . '</a> '
      . '<a class="button" href="' . htmlspecialchars($resume_paused, ENT_QUOTES, 'UTF-8') . '">'
      . $this->t('恢复暂停（@n）', ['@n' => $paused_bulk_n])
      . '</a> <span class="description">' . $this->t('依据缓存探测结果，不重新出网；当前筛选范围') . '</span></div>';

    return [
      'intro' => [
        '#markup' => '<p>' . $this->t('信源运营台：allowlist（仅 RSS/Atom）与入库对照。暂停写入 agent/ops_paused.json，Agent 会跳过。') . '</p>'
          . '<p>' . $this->t('合计 @total 路 · 暂停 @paused · 有入库 @with · 分组 @groups · 探测失败 @fail · 从未探测 @never · 静默 @silent · 空源 @empty · 过期 @stale', [
            '@total' => count($feeds),
            '@paused' => $paused_n,
            '@with' => $with_content,
            '@groups' => count($groups),
            '@fail' => $fail_n,
            '@never' => $never_n,
            '@silent' => $silent_n,
            '@empty' => $empty_n,
            '@stale' => $stale_n,
          ]) . ($status === 'fail' ? ' · <strong>' . $this->t('当前：探测失败视图') . '</strong>'
              . ' · '
              . Link::fromTextAndUrl($this->t('重测失败'), Url::fromRoute('xmt_trust_ui.source_ops_probe_failed', [], [
                'query' => array_filter([
                  'group' => $group ?: NULL,
                  'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe_failed:' . ($group !== '' ? $group : '*')),
                ]),
              ]))->toString()
              . ' · '
              . Link::fromTextAndUrl($this->t('暂停失败'), Url::fromRoute('xmt_trust_ui.source_ops_pause_failed', [], [
                'query' => array_filter([
                  'group' => $group ?: NULL,
                  'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_failed:' . ($group !== '' ? $group : '*')),
                ]),
              ]))->toString()
              : '')
            . ($status === 'never' ? ' · <strong>' . $this->t('当前：从未探测视图') . '</strong>'
              . ' · '
              . Link::fromTextAndUrl($this->t('首测从未'), Url::fromRoute('xmt_trust_ui.source_ops_probe_never', [], [
                'query' => array_filter([
                  'group' => $group ?: NULL,
                  'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe_never:' . ($group !== '' ? $group : '*')),
                ]),
              ]))->toString()
              . ' · '
              . Link::fromTextAndUrl($this->t('暂停从未'), Url::fromRoute('xmt_trust_ui.source_ops_pause_never', [], [
                'query' => array_filter([
                  'group' => $group ?: NULL,
                  'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_never:' . ($group !== '' ? $group : '*')),
                ]),
              ]))->toString()
              : '')
            . ($status === 'paused' ? ' · <strong>' . $this->t('当前：已暂停视图') . '</strong>'
              . ' · '
              . Link::fromTextAndUrl($this->t('恢复暂停'), Url::fromRoute('xmt_trust_ui.source_ops_resume_paused', [], [
                'query' => array_filter([
                  'group' => $group ?: NULL,
                  'token' => \Drupal::csrfToken()->get('xmt_source_ops_resume_paused:' . ($group !== '' ? $group : '*')),
                ]),
              ]))->toString()
              : '')
            . ($status === 'active' ? ' · <strong>' . $this->t('当前：采集中视图') . '</strong>' : '')
            . ($status === 'silent' ? ' · <strong>' . $this->t('当前：静默/零入库视图') . '</strong>'
              . ' · '
              . Link::fromTextAndUrl($this->t('重测静默'), Url::fromRoute('xmt_trust_ui.source_ops_probe_silent', [], [
                'query' => array_filter([
                  'group' => $group ?: NULL,
                  'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe_silent:' . ($group !== '' ? $group : '*')),
                ]),
              ]))->toString()
              . ' · '
              . Link::fromTextAndUrl($this->t('暂停静默'), Url::fromRoute('xmt_trust_ui.source_ops_pause_silent', [], [
                'query' => array_filter([
                  'group' => $group ?: NULL,
                  'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_silent:' . ($group !== '' ? $group : '*')),
                ]),
              ]))->toString()
              : '')
            . ($status === 'empty' ? ' · <strong>' . $this->t('当前：RSS 空源（0 条）视图') . '</strong>'
              . ' · '
              . Link::fromTextAndUrl($this->t('重测空源'), Url::fromRoute('xmt_trust_ui.source_ops_probe_empty', [], [
                'query' => array_filter([
                  'group' => $group ?: NULL,
                  'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe_empty:' . ($group !== '' ? $group : '*')),
                ]),
              ]))->toString()
              . ' · '
              . Link::fromTextAndUrl($this->t('暂停空源'), Url::fromRoute('xmt_trust_ui.source_ops_pause_empty', [], [
                'query' => array_filter([
                  'group' => $group ?: NULL,
                  'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_empty:' . ($group !== '' ? $group : '*')),
                ]),
              ]))->toString()
              : '')
            . ($status === 'stale' ? ' · <strong>' . $this->t('当前：探测过期（≥7 天）视图') . '</strong>'
              . ' · '
              . Link::fromTextAndUrl($this->t('重测过期'), Url::fromRoute('xmt_trust_ui.source_ops_probe_stale', [], [
                'query' => array_filter([
                  'group' => $group ?: NULL,
                  'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe_stale:' . ($group !== '' ? $group : '*')),
                ]),
              ]))->toString()
              . ' · '
              . Link::fromTextAndUrl($this->t('暂停过期'), Url::fromRoute('xmt_trust_ui.source_ops_pause_stale', [], [
                'query' => array_filter([
                  'group' => $group ?: NULL,
                  'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_stale:' . ($group !== '' ? $group : '*')),
                ]),
              ]))->toString()
              : '')
          . ' · '
          . Link::fromTextAndUrl($this->t('导出 CSV'), Url::fromRoute('xmt_trust_ui.source_ops_export', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'trust' => $trust ?: NULL,
              'status' => $status !== 'all' ? $status : NULL,
              'q' => $q !== '' ? $q : NULL,
            ]),
          ]))->toString()
          . ' <span class="description">(' . $this->t('当前筛选 @n 条', ['@n' => count($filtered)]) . ')</span>'
          . ' · '
          . Link::fromTextAndUrl($this->t('健康'), Url::fromRoute('xmt_trust_ui.source_health', [], [
            'query' => array_filter(['group' => $group ?: NULL]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('重测过期'), Url::fromRoute('xmt_trust_ui.source_ops_probe_stale', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe_stale:' . ($group !== '' ? $group : '*')),
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('信任统计'), Url::fromRoute('xmt_trust_ui.stats'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('短闻'), Url::fromRoute('xmt_trust_ui.short_read'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('今日简报'), Url::fromRoute('xmt_trust_ui.short_read_today'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('稍后'), Url::fromRoute('xmt_trust_ui.short_read_later'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('搜索'), Url::fromRoute('xmt_trust_ui.search'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('溯源审计'), Url::fromRoute('xmt_trust_ui.provenance_audit'))->toString()
          . '</p>'
          . '<p>' . $this->t('Agent：seen=@seen · state=@state · log=@log · yaml=@yaml · last=@last · dry-run=@dry · ingest=@ing', [
            '@seen' => $snap['seen_count'],
            '@state' => $snap['state_mtime'] ? $date->format($snap['state_mtime'], 'short') : '—',
            '@log' => $snap['log_mtime'] ? $date->format($snap['log_mtime'], 'short') : '—',
            '@yaml' => $snap['sources_readable'] ? $this->t('可读') : $this->t('不可读'),
            '@last' => ($snap['last_published'] !== NULL || $snap['last_errors'] !== NULL)
              ? $this->t('published=@p errors=@e@mode', [
                '@p' => $snap['last_published'] ?? '—',
                '@e' => $snap['last_errors'] ?? '—',
                '@mode' => !empty($snap['last_run_mode'])
                  ? (' [' . $snap['last_run_mode'] . (!empty($snap['last_run_stamp']) ? ' ' . $snap['last_run_stamp'] : '') . ']')
                  : '',
              ])
              : '—',
            '@dry' => !empty($snap['last_dry_run_at'])
              ? ($snap['last_dry_run_group'] . ' @ ' . $date->format((int) $snap['last_dry_run_at'], 'short')
                . (!empty($snap['last_dry_run_ok']) ? ' ✓' : ' ✗'))
              : '—',
            '@ing' => !empty($snap['last_ingest_at'])
              ? ($snap['last_ingest_group'] . ' @ ' . $date->format((int) $snap['last_ingest_at'], 'short')
                . (!empty($snap['last_ingest_ok']) ? ' ✓' : ' ✗'))
              : '—',
          ])
          . (!empty($snap['last_dry_run_at'])
            ? ' · <a href="#xmt-last-dry-run">' . $this->t('查看上次 dry-run') . '</a>'
            : '')
          . (!empty($snap['last_ingest_at'])
            ? ' · <a href="#xmt-last-ingest">' . $this->t('查看上次入库') . '</a>'
            : '')
          . '</p>',
      ],
      'filters' => [
        '#prefix' => '<div class="xmt-source-ops-sticky-bar">',
        '#suffix' => '</div>',
        '#markup' => $agent_alert . $filter_markup . $group_actions . $bulk
          . '<div class="xmt-source-ops-filter-block"><strong>' . $this->t('快捷') . '</strong> '
          . Link::fromTextAndUrl($this->t('健康'), Url::fromRoute('xmt_trust_ui.source_health', [], [
            'query' => array_filter(['group' => $group ?: NULL]),
          ]))->toString()
          . (!empty($snap['last_ingest_at'])
            ? ' · ' . Link::fromTextAndUrl($this->t('上次入库'), Url::fromRoute('xmt_trust_ui.source_ops', [], [
              'query' => array_filter(['group' => $group ?: NULL]),
              'fragment' => 'xmt-last-ingest',
            ]))->toString()
            : '')
          . ' · '
          . Link::fromTextAndUrl($this->t('失败（@n）', ['@n' => $fail_n]), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'status' => 'fail',
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('从未（@n）', ['@n' => $never_n]), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'status' => 'never',
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('静默（@n）', ['@n' => $silent_n]), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'status' => 'silent',
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('空源（@n）', ['@n' => $empty_n]), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'status' => 'empty',
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('过期（@n）', ['@n' => $stale_n]), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'status' => 'stale',
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('暂停（@n）', ['@n' => $paused_bulk_n]), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'status' => 'paused',
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('重测失败'), Url::fromRoute('xmt_trust_ui.source_ops_probe_failed', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe_failed:' . $token_suffix),
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('首测从未'), Url::fromRoute('xmt_trust_ui.source_ops_probe_never', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe_never:' . $token_suffix),
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('重测静默'), Url::fromRoute('xmt_trust_ui.source_ops_probe_silent', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe_silent:' . $token_suffix),
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('重测空源'), Url::fromRoute('xmt_trust_ui.source_ops_probe_empty', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe_empty:' . $token_suffix),
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('重测过期'), Url::fromRoute('xmt_trust_ui.source_ops_probe_stale', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe_stale:' . $token_suffix),
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('CSV'), Url::fromRoute('xmt_trust_ui.source_ops_export', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'trust' => $trust ?: NULL,
              'status' => $status !== 'all' ? $status : NULL,
              'q' => $q !== '' ? $q : NULL,
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('统计'), Url::fromRoute('xmt_trust_ui.stats'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('溯源'), Url::fromRoute('xmt_trust_ui.provenance_audit'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('短闻'), Url::fromRoute('xmt_trust_ui.short_read'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('今日'), Url::fromRoute('xmt_trust_ui.short_read_today'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('稍后'), Url::fromRoute('xmt_trust_ui.short_read_later'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('搜索'), Url::fromRoute('xmt_trust_ui.search'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('官媒'), Url::fromRoute('xmt_trust_ui.official_media'))->toString()
          . '</div>',
        '#attached' => ['library' => ['xmt_trust_ui/source_ops']],
      ],
      'agent_log' => [
        '#markup' => '<h2>' . $this->t('Agent 最近日志') . '</h2>' . $log_pre . $dry_preview . $ingest_preview,
      ],
      'table' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['xmt-source-ops-table']],
        '#prefix' => '<div class="xmt-source-ops-table-wrap">',
        '#suffix' => '</div>',
        '#caption' => $this->t('Allowlist 信源（当前筛选 @n 条）', ['@n' => count($filtered)]),
        '#header' => [
          $this->t('分组'),
          $this->t('信源名'),
          $this->t('Feed URL'),
          $this->t('站点'),
          $this->t('领域'),
          $this->t('信任级'),
          $this->t('状态'),
          $this->t('探测'),
          $this->t('入库数'),
          $this->t('最近入库'),
          $this->t('操作'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('无匹配信源'),
      ],
      'orphans' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['xmt-source-ops-table']],
        '#prefix' => '<div class="xmt-source-ops-table-wrap">',
        '#suffix' => '</div>',
        '#caption' => $this->t('库内有内容但不在 allowlist（前 30，按入库数；总数 @n）', ['@n' => count($orphans)]),
        '#header' => [$this->t('信源名'), $this->t('入库数'), $this->t('最近'), $this->t('操作')],
        '#rows' => $orphan_rows,
        '#empty' => $this->t('无孤儿信源'),
      ],
      'orphans_note' => [
        '#markup' => '<p class="description">' . $this->t('孤儿信源：库内有文章但不在 allowlist。请核对 sources YAML 后决定是否补录或清理。')
          . ' · '
          . Link::fromTextAndUrl($this->t('打开可信搜索'), Url::fromRoute('xmt_trust_ui.search'))->toString()
          . '</p>',
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Pause / resume a feed URL.
   */
  public function toggle(Request $request): RedirectResponse {
    $url = trim((string) $request->query->get('url', ''));
    $token = (string) $request->query->get('token', '');
    if ($url === '' || !\Drupal::csrfToken()->validate($token, 'xmt_source_ops_toggle:' . $url)) {
      throw new AccessDeniedHttpException();
    }
    try {
      $paused = $this->catalog->togglePaused($url);
      $this->messenger()->addStatus($paused
        ? $this->t('已暂停：@url', ['@url' => $url])
        : $this->t('已恢复：@url', ['@url' => $url]));
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('写入失败：@m', ['@m' => $e->getMessage()]));
    }
    $dest = (string) $request->query->get('destination', '');
    if ($dest !== '' && str_starts_with($dest, '/')) {
      return new RedirectResponse($dest);
    }
    return $this->redirect('xmt_trust_ui.source_ops');
  }

  /**
   * Pause feeds with failed probe cache.
   */
  public function pauseFailed(Request $request): RedirectResponse {
    $group = trim((string) $request->query->get('group', ''));
    $token = (string) $request->query->get('token', '');
    $token_key = 'xmt_source_ops_pause_failed:' . ($group !== '' ? $group : '*');
    if (!\Drupal::csrfToken()->validate($token, $token_key)) {
      throw new AccessDeniedHttpException();
    }
    try {
      $result = $this->catalog->pauseFailedProbes($group !== '' ? $group : NULL);
      if ($result['changed'] < 1) {
        $this->messenger()->addStatus($this->t('没有需要新暂停的失败信源（候选 @n）', [
          '@n' => $result['candidates'],
        ]));
      }
      else {
        $this->messenger()->addStatus($this->t('已暂停 @n 路探测失败信源：@names', [
          '@n' => $result['changed'],
          '@names' => implode('、', $result['names']),
        ]));
      }
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('操作失败：@m', ['@m' => $e->getMessage()]));
    }
    $dest = (string) $request->query->get('destination', '');
    if ($dest !== '' && str_starts_with($dest, '/')) {
      return new RedirectResponse($dest);
    }
    return $this->redirect('xmt_trust_ui.source_ops', [], [
      'query' => array_filter(['group' => $group ?: NULL]),
    ]);
  }

  /**
   * Pause feeds that have never been probed.
   */
  public function pauseNever(Request $request): RedirectResponse {
    $group = trim((string) $request->query->get('group', ''));
    $token = (string) $request->query->get('token', '');
    $token_key = 'xmt_source_ops_pause_never:' . ($group !== '' ? $group : '*');
    if (!\Drupal::csrfToken()->validate($token, $token_key)) {
      throw new AccessDeniedHttpException();
    }
    try {
      $result = $this->catalog->pauseNeverProbed($group !== '' ? $group : NULL);
      if ($result['changed'] < 1) {
        $this->messenger()->addStatus($this->t('没有需要新暂停的从未探测信源（候选 @n）', [
          '@n' => $result['candidates'],
        ]));
      }
      else {
        $this->messenger()->addStatus($this->t('已暂停 @n 路从未探测信源：@names', [
          '@n' => $result['changed'],
          '@names' => implode('、', $result['names']),
        ]));
      }
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('操作失败：@m', ['@m' => $e->getMessage()]));
    }
    $dest = (string) $request->query->get('destination', '');
    if ($dest !== '' && str_starts_with($dest, '/')) {
      return new RedirectResponse($dest);
    }
    return $this->redirect('xmt_trust_ui.source_ops', [], [
      'query' => array_filter(['group' => $group ?: NULL]),
    ]);
  }

  /**
   * Pause stale-probe feeds (≥7d).
   */
  public function pauseStale(Request $request): RedirectResponse {
    $group = trim((string) $request->query->get('group', ''));
    $token = (string) $request->query->get('token', '');
    $token_key = 'xmt_source_ops_pause_stale:' . ($group !== '' ? $group : '*');
    if (!\Drupal::csrfToken()->validate($token, $token_key)) {
      throw new AccessDeniedHttpException();
    }
    try {
      $result = $this->catalog->pauseStaleFeeds($group !== '' ? $group : NULL);
      if ($result['changed'] < 1) {
        $this->messenger()->addStatus($this->t('没有需要新暂停的过期信源（候选 @n）', [
          '@n' => $result['candidates'],
        ]));
      }
      else {
        $this->messenger()->addStatus($this->t('已暂停 @n 路过期信源：@names', [
          '@n' => $result['changed'],
          '@names' => implode('、', $result['names']),
        ]));
      }
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('操作失败：@m', ['@m' => $e->getMessage()]));
    }
    $dest = (string) $request->query->get('destination', '');
    if ($dest !== '' && str_starts_with($dest, '/')) {
      return new RedirectResponse($dest);
    }
    return $this->redirect('xmt_trust_ui.source_ops', [], [
      'query' => array_filter([
        'group' => $group ?: NULL,
        'status' => 'stale',
      ]),
    ]);
  }

  /**
   * Pause empty-RSS feeds (probe ok, 0 items).
   */
  public function pauseEmpty(Request $request): RedirectResponse {
    $group = trim((string) $request->query->get('group', ''));
    $token = (string) $request->query->get('token', '');
    $token_key = 'xmt_source_ops_pause_empty:' . ($group !== '' ? $group : '*');
    if (!\Drupal::csrfToken()->validate($token, $token_key)) {
      throw new AccessDeniedHttpException();
    }
    try {
      $result = $this->catalog->pauseEmptyFeeds($group !== '' ? $group : NULL);
      if ($result['changed'] < 1) {
        $this->messenger()->addStatus($this->t('没有需要新暂停的空源（候选 @n）', [
          '@n' => $result['candidates'],
        ]));
      }
      else {
        $this->messenger()->addStatus($this->t('已暂停 @n 路空源：@names', [
          '@n' => $result['changed'],
          '@names' => implode('、', $result['names']),
        ]));
      }
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('操作失败：@m', ['@m' => $e->getMessage()]));
    }
    $dest = (string) $request->query->get('destination', '');
    if ($dest !== '' && str_starts_with($dest, '/')) {
      return new RedirectResponse($dest);
    }
    return $this->redirect('xmt_trust_ui.source_ops', [], [
      'query' => array_filter([
        'group' => $group ?: NULL,
        'status' => 'empty',
      ]),
    ]);
  }

  /**
   * Pause silent feeds (probe ok but no recent ingest).
   */
  public function pauseSilent(Request $request): RedirectResponse {
    $group = trim((string) $request->query->get('group', ''));
    $token = (string) $request->query->get('token', '');
    $token_key = 'xmt_source_ops_pause_silent:' . ($group !== '' ? $group : '*');
    if (!\Drupal::csrfToken()->validate($token, $token_key)) {
      throw new AccessDeniedHttpException();
    }
    try {
      $result = $this->catalog->pauseSilentFeeds($group !== '' ? $group : NULL);
      if ($result['changed'] < 1) {
        $this->messenger()->addStatus($this->t('没有需要新暂停的静默信源（候选 @n）', [
          '@n' => $result['candidates'],
        ]));
      }
      else {
        $this->messenger()->addStatus($this->t('已暂停 @n 路静默信源：@names', [
          '@n' => $result['changed'],
          '@names' => implode('、', $result['names']),
        ]));
      }
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('操作失败：@m', ['@m' => $e->getMessage()]));
    }
    $dest = (string) $request->query->get('destination', '');
    if ($dest !== '' && str_starts_with($dest, '/')) {
      return new RedirectResponse($dest);
    }
    return $this->redirect('xmt_trust_ui.source_ops', [], [
      'query' => array_filter([
        'group' => $group ?: NULL,
        'status' => 'silent',
      ]),
    ]);
  }

  /**
   * Pause / resume all feeds in a group.
   */
  public function groupPause(Request $request): RedirectResponse {
    $group = trim((string) $request->query->get('group', ''));
    $op = (string) $request->query->get('op', 'pause');
    $token = (string) $request->query->get('token', '');
    if ($group === '' || !in_array($op, ['pause', 'resume'], TRUE)
      || !\Drupal::csrfToken()->validate($token, 'xmt_source_ops_group:' . $group . ':' . $op)) {
      throw new AccessDeniedHttpException();
    }
    try {
      $result = $this->catalog->setGroupPaused($group, $op === 'pause');
      $this->messenger()->addStatus($op === 'pause'
        ? $this->t('已暂停分组 @g（@n 路）', ['@g' => $group, '@n' => $result['changed']])
        : $this->t('已恢复分组 @g（@n 路）', ['@g' => $group, '@n' => $result['changed']]));
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('操作失败：@m', ['@m' => $e->getMessage()]));
    }
    $dest = (string) $request->query->get('destination', '');
    if ($dest !== '' && str_starts_with($dest, '/')) {
      return new RedirectResponse($dest);
    }
    return $this->redirect('xmt_trust_ui.source_ops', [], ['query' => ['group' => $group]]);
  }

  /**
   * Probe one feed URL.
   */
  public function probe(Request $request): array {
    $url = trim((string) $request->query->get('url', ''));
    $token = (string) $request->query->get('token', '');
    if ($url === '' || !\Drupal::csrfToken()->validate($token, 'xmt_source_ops_probe:' . $url)) {
      throw new AccessDeniedHttpException();
    }
    $fresh = $request->query->get('fresh') === '1';
    $result = $this->catalog->probeCached($url, 3600, $fresh);
    return [
      'back' => [
        '#markup' => '<p>' . Link::fromTextAndUrl($this->t('← 返回信源运营台'), Url::fromRoute('xmt_trust_ui.source_ops'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('强制重测'), Url::fromRoute('xmt_trust_ui.source_ops_probe', [], [
            'query' => [
              'url' => $url,
              'fresh' => '1',
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe:' . $url),
            ],
          ]))->toString()
          . '</p>',
      ],
      'result' => [
        '#type' => 'table',
        '#header' => [$this->t('项'), $this->t('值')],
        '#rows' => [
          [$this->t('URL'), $url],
          [$this->t('结果'), $result['ok'] ? $this->t('通过') : $this->t('失败')],
          [$this->t('缓存'), !empty($result['cached']) ? $this->t('是') : $this->t('否')],
          [$this->t('HTTP'), (string) $result['http']],
          [$this->t('Content-Type'), $result['ctype'] ?: '—'],
          [$this->t('采样字节'), (string) $result['bytes']],
          [$this->t('说明'), $result['message']],
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Batch probe a group.
   */
  public function probeGroup(Request $request): array {
    $group = trim((string) $request->query->get('group', ''));
    $token = (string) $request->query->get('token', '');
    if ($group === '' || !\Drupal::csrfToken()->validate($token, 'xmt_source_ops_probe_group:' . $group)) {
      throw new AccessDeniedHttpException();
    }
    $result = $this->catalog->probeGroup($group, 20);
    $rows = [];
    foreach ($result['results'] as $row) {
      $rows[] = [
        $this->probeResultNameCell((string) ($row['name'] ?? '')),
        $row['ok'] ? $this->t('通过') : $this->t('失败'),
        $row['message'],
      ];
    }
    return [
      'back' => [
        '#markup' => '<p>' . Link::fromTextAndUrl($this->t('← 返回信源运营台'), Url::fromRoute('xmt_trust_ui.source_ops', [], [
          'query' => ['group' => $group],
        ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('暂停本范围探测失败'), Url::fromRoute('xmt_trust_ui.source_ops_pause_failed', [], [
            'query' => [
              'group' => $group,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_failed:' . $group),
              'destination' => Url::fromRoute('xmt_trust_ui.source_ops', [], [
                'query' => ['group' => $group],
              ])->toString(),
            ],
          ]))->toString()
          . '</p>'
          . '<p>' . $this->t('分组 @g · 通过 @ok · 失败 @fail · 未测 @skip（每组最多 20）', [
            '@g' => $group,
            '@ok' => $result['ok'],
            '@fail' => $result['fail'],
            '@skip' => $result['skipped'],
          ]) . '</p>',
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('信源'), $this->t('结果'), $this->t('说明')],
        '#rows' => $rows,
        '#empty' => $this->t('该组无信源'),
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Re-probe failed feeds (cap 15).
   */
  public function probeFailed(Request $request): array {
    $group = trim((string) $request->query->get('group', ''));
    $token = (string) $request->query->get('token', '');
    $token_suffix = $group !== '' ? $group : '*';
    if (!\Drupal::csrfToken()->validate($token, 'xmt_source_ops_probe_failed:' . $token_suffix)) {
      throw new AccessDeniedHttpException();
    }
    $result = $this->catalog->probeFailed($group, 15);
    $rows = [];
    foreach ($result['results'] as $row) {
      $name = trim((string) ($row['name'] ?? ''));
      $rows[] = [
        $this->probeResultNameCell($name),
        $row['ok'] ? $this->t('通过') : $this->t('失败'),
        $row['message'],
      ];
    }
    return [
      'back' => [
        '#markup' => '<p>' . Link::fromTextAndUrl($this->t('← 返回失败视图'), Url::fromRoute('xmt_trust_ui.source_ops', [], [
          'query' => array_filter([
            'group' => $group ?: NULL,
            'status' => 'fail',
          ]),
        ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('健康'), Url::fromRoute('xmt_trust_ui.source_health', [], [
            'query' => array_filter(['group' => $group ?: NULL]),
          ]))->toString()
          . '</p>'
          . '<p>' . $this->t('失败重测 · 通过 @ok · 失败 @fail · 未测 @skip（最多 15）', [
            '@ok' => $result['ok'],
            '@fail' => $result['fail'],
            '@skip' => $result['skipped'],
          ]) . ' · '
          . Link::fromTextAndUrl($this->t('暂停仍失败'), Url::fromRoute('xmt_trust_ui.source_ops_pause_failed', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_failed:' . ($group !== '' ? $group : '*')),
            ]),
          ]))->toString()
          . '</p>',
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('信源'), $this->t('结果'), $this->t('说明')],
        '#rows' => $rows,
        '#empty' => $this->t('当前范围无探测失败信源'),
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Resume paused feeds in scope.
   */
  public function resumePaused(Request $request): RedirectResponse {
    $group = trim((string) $request->query->get('group', ''));
    $token = (string) $request->query->get('token', '');
    $token_key = 'xmt_source_ops_resume_paused:' . ($group !== '' ? $group : '*');
    if (!\Drupal::csrfToken()->validate($token, $token_key)) {
      throw new AccessDeniedHttpException();
    }
    try {
      $result = $this->catalog->resumePausedFeeds($group !== '' ? $group : NULL);
      if ($result['changed'] < 1) {
        $this->messenger()->addStatus($this->t('没有需要恢复的暂停信源（候选 @n）', [
          '@n' => $result['candidates'],
        ]));
      }
      else {
        $this->messenger()->addStatus($this->t('已恢复 @n 路暂停信源：@names', [
          '@n' => $result['changed'],
          '@names' => implode('、', $result['names']),
        ]));
      }
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('操作失败：@m', ['@m' => $e->getMessage()]));
    }
    $dest = (string) $request->query->get('destination', '');
    if ($dest !== '' && str_starts_with($dest, '/')) {
      return new RedirectResponse($dest);
    }
    return $this->redirect('xmt_trust_ui.source_ops', [], [
      'query' => array_filter([
        'group' => $group ?: NULL,
        'status' => 'paused',
      ]),
    ]);
  }

  /**
   * Re-probe silent feeds (cap 15).
   */
  public function probeSilent(Request $request): array {
    $group = trim((string) $request->query->get('group', ''));
    $token = (string) $request->query->get('token', '');
    $token_suffix = $group !== '' ? $group : '*';
    if (!\Drupal::csrfToken()->validate($token, 'xmt_source_ops_probe_silent:' . $token_suffix)) {
      throw new AccessDeniedHttpException();
    }
    $result = $this->catalog->probeSilent($group, 15);
    $rows = [];
    foreach ($result['results'] as $row) {
      $rows[] = [
        $this->probeResultNameCell((string) ($row['name'] ?? '')),
        $row['ok'] ? $this->t('通过') : $this->t('失败'),
        $row['message'],
      ];
    }
    $back_query = array_filter(['group' => $group ?: NULL, 'status' => 'silent']);
    return [
      'back' => [
        '#markup' => '<p>' . Link::fromTextAndUrl($this->t('← 返回运营台静默视图'), Url::fromRoute('xmt_trust_ui.source_ops', [], [
          'query' => $back_query,
        ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('健康'), Url::fromRoute('xmt_trust_ui.source_health', [], [
            'query' => array_filter(['group' => $group ?: NULL]),
          ]))->toString()
          . '</p>'
          . '<p>' . $this->t('静默重测 · 通过 @ok · 失败 @fail · 未测 @skip（最多 15）', [
            '@ok' => $result['ok'],
            '@fail' => $result['fail'],
            '@skip' => $result['skipped'],
          ]) . ' · '
          . Link::fromTextAndUrl($this->t('暂停仍静默'), Url::fromRoute('xmt_trust_ui.source_ops_pause_silent', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_silent:' . ($group !== '' ? $group : '*')),
            ]),
          ]))->toString()
          . '</p>',
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('信源'), $this->t('结果'), $this->t('说明')],
        '#rows' => $rows,
        '#empty' => $this->t('当前范围无静默信源'),
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Re-probe empty-RSS feeds (cap 15).
   */
  public function probeEmpty(Request $request): array {
    $group = trim((string) $request->query->get('group', ''));
    $token = (string) $request->query->get('token', '');
    $token_suffix = $group !== '' ? $group : '*';
    if (!\Drupal::csrfToken()->validate($token, 'xmt_source_ops_probe_empty:' . $token_suffix)) {
      throw new AccessDeniedHttpException();
    }
    $result = $this->catalog->probeEmpty($group, 15);
    $rows = [];
    foreach ($result['results'] as $row) {
      $rows[] = [
        $this->probeResultNameCell((string) ($row['name'] ?? '')),
        $row['ok'] ? $this->t('通过') : $this->t('失败'),
        $row['message'],
      ];
    }
    return [
      'back' => [
        '#markup' => '<p>' . Link::fromTextAndUrl($this->t('← 返回空源视图'), Url::fromRoute('xmt_trust_ui.source_ops', [], [
          'query' => array_filter([
            'group' => $group ?: NULL,
            'status' => 'empty',
          ]),
        ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('健康'), Url::fromRoute('xmt_trust_ui.source_health', [], [
            'query' => array_filter(['group' => $group ?: NULL]),
          ]))->toString()
          . '</p>'
          . '<p>' . $this->t('空源重测 · 通过 @ok · 失败 @fail · 未测 @skip（最多 15）', [
            '@ok' => $result['ok'],
            '@fail' => $result['fail'],
            '@skip' => $result['skipped'],
          ]) . ' · '
          . Link::fromTextAndUrl($this->t('暂停仍空源'), Url::fromRoute('xmt_trust_ui.source_ops_pause_empty', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_empty:' . ($group !== '' ? $group : '*')),
            ]),
          ]))->toString()
          . '</p>',
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('信源'), $this->t('结果'), $this->t('说明')],
        '#rows' => $rows,
        '#empty' => $this->t('当前范围无空源（需探测缓存含 item_count=0）'),
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * First-probe never-probed feeds (cap 20).
   */
  public function probeNever(Request $request): array {
    $group = trim((string) $request->query->get('group', ''));
    $token = (string) $request->query->get('token', '');
    $token_suffix = $group !== '' ? $group : '*';
    if (!\Drupal::csrfToken()->validate($token, 'xmt_source_ops_probe_never:' . $token_suffix)) {
      throw new AccessDeniedHttpException();
    }
    $result = $this->catalog->probeNever($group, 20);
    $rows = [];
    foreach ($result['results'] as $row) {
      $rows[] = [
        $this->probeResultNameCell((string) ($row['name'] ?? '')),
        $row['ok'] ? $this->t('通过') : $this->t('失败'),
        $row['message'],
      ];
    }
    return [
      'back' => [
        '#markup' => '<p>' . Link::fromTextAndUrl($this->t('← 返回从未探测'), Url::fromRoute('xmt_trust_ui.source_ops', [], [
          'query' => array_filter([
            'group' => $group ?: NULL,
            'status' => 'never',
          ]),
        ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('健康'), Url::fromRoute('xmt_trust_ui.source_health', [], [
            'query' => array_filter(['group' => $group ?: NULL]),
          ]))->toString()
          . '</p>'
          . '<p>' . $this->t('从未首测 · 通过 @ok · 失败 @fail · 未测 @skip（最多 20）', [
            '@ok' => $result['ok'],
            '@fail' => $result['fail'],
            '@skip' => $result['skipped'],
          ]) . ' · '
          . Link::fromTextAndUrl($this->t('暂停仍从未'), Url::fromRoute('xmt_trust_ui.source_ops_pause_never', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_never:' . ($group !== '' ? $group : '*')),
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('暂停仍失败'), Url::fromRoute('xmt_trust_ui.source_ops_pause_failed', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_failed:' . ($group !== '' ? $group : '*')),
            ]),
          ]))->toString()
          . '</p>',
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('信源'), $this->t('结果'), $this->t('说明')],
        '#rows' => $rows,
        '#empty' => $this->t('当前范围无从未探测信源'),
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Re-probe stale probe cache (older than 7d, cap 20).
   */
  public function probeStale(Request $request): array {
    $group = trim((string) $request->query->get('group', ''));
    $token = (string) $request->query->get('token', '');
    $token_suffix = $group !== '' ? $group : '*';
    if (!\Drupal::csrfToken()->validate($token, 'xmt_source_ops_probe_stale:' . $token_suffix)) {
      throw new AccessDeniedHttpException();
    }
    $result = $this->catalog->probeStale($group, 20);
    $rows = [];
    foreach ($result['results'] as $row) {
      $rows[] = [
        $this->probeResultNameCell((string) ($row['name'] ?? '')),
        $row['ok'] ? $this->t('通过') : $this->t('失败'),
        $row['message'],
      ];
    }
    return [
      'back' => [
        '#markup' => '<p>' . Link::fromTextAndUrl($this->t('← 返回健康'), Url::fromRoute('xmt_trust_ui.source_health', [], [
          'query' => array_filter(['group' => $group ?: NULL]),
        ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('运营台'), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'status' => 'stale',
            ]),
          ]))->toString()
          . '</p>'
          . '<p>' . $this->t('过期重测 · 通过 @ok · 失败 @fail · 未测 @skip（最多 20，最久未测优先）', [
            '@ok' => $result['ok'],
            '@fail' => $result['fail'],
            '@skip' => $result['skipped'],
          ]) . ' · '
          . Link::fromTextAndUrl($this->t('暂停仍过期'), Url::fromRoute('xmt_trust_ui.source_ops_pause_stale', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_stale:' . ($group !== '' ? $group : '*')),
            ]),
          ]))->toString()
          . '</p>',
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('信源'), $this->t('结果'), $this->t('说明')],
        '#rows' => $rows,
        '#empty' => $this->t('当前范围无超过 7 天的探测缓存'),
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Dry-run agent for one group, or a single feed when ?url= is set.
   */
  public function dryRun(Request $request): array {
    $group = trim((string) $request->query->get('group', ''));
    $url = trim((string) $request->query->get('url', ''));
    $token = (string) $request->query->get('token', '');
    if ($url !== '') {
      if (!\Drupal::csrfToken()->validate($token, 'xmt_source_ops_dryrun_feed:' . $url)) {
        throw new AccessDeniedHttpException();
      }
      $result = $this->catalog->dryRunFeed($url, $group);
      $back_query = array_filter(['group' => $group ?: NULL]);
      return [
        'back' => [
          '#markup' => '<p>' . Link::fromTextAndUrl($this->t('← 返回信源运营台'), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => $back_query,
          ]))->toString()
            . ' · '
            . Link::fromTextAndUrl($this->t('健康'), Url::fromRoute('xmt_trust_ui.source_health', [], [
              'query' => $back_query,
            ]))->toString()
            . '</p>',
        ],
        'summary' => [
          '#markup' => '<p>' . $this->t('单源预览 · @url · 退出码 @c · @ok', [
            '@url' => $url,
            '@c' => $result['code'],
            '@ok' => $result['ok'] ? $this->t('完成') : $this->t('异常'),
          ]) . '</p>',
        ],
        'log' => [
          '#markup' => '<pre class="xmt-source-ops-log">' . htmlspecialchars($result['output'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>',
        ],
        '#cache' => ['max-age' => 0],
      ];
    }
    if ($group === '' || !\Drupal::csrfToken()->validate($token, 'xmt_source_ops_dryrun:' . $group)) {
      throw new AccessDeniedHttpException();
    }
    $result = $this->catalog->dryRunGroup($group);
    return [
      'back' => [
        '#markup' => '<p>' . Link::fromTextAndUrl($this->t('← 返回信源运营台（分组 @g）', ['@g' => $group]), Url::fromRoute('xmt_trust_ui.source_ops', [], [
          'query' => array_filter(['group' => $group]),
        ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('健康'), Url::fromRoute('xmt_trust_ui.source_health', [], [
            'query' => array_filter(['group' => $group]),
          ]))->toString()
          . '</p>',
      ],
      'summary' => [
        '#markup' => '<p>' . $this->t('分组 @g · 退出码 @c · @ok · 已写入上次 dry-run 缓存', [
          '@g' => $group,
          '@c' => $result['code'],
          '@ok' => $result['ok'] ? $this->t('完成') : $this->t('异常'),
        ]) . '</p>',
      ],
      'log' => [
        '#markup' => '<pre class="xmt-source-ops-log">' . htmlspecialchars($result['output'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>',
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Export allowlist CSV (respects current filters via query args).
   */
  public function export(Request $request): Response {
    $group = trim((string) $request->query->get('group', ''));
    $trust = trim((string) $request->query->get('trust', ''));
    $status = trim((string) $request->query->get('status', 'all'));
    $q = trim((string) $request->query->get('q', ''));
    $probe = $this->catalog->probeCacheMap();
    $feeds = array_values(array_filter($this->catalog->feeds(), function (array $f) use ($group, $trust, $status, $q, $probe): bool {
      if ($group !== '' && $f['group'] !== $group) {
        return FALSE;
      }
      if ($trust !== '' && $f['trust_level'] !== $trust) {
        return FALSE;
      }
      if ($status === 'paused' && !$f['paused']) {
        return FALSE;
      }
      if ($status === 'active' && $f['paused']) {
        return FALSE;
      }
      if ($status === 'never' && isset($probe[$f['url']])) {
        return FALSE;
      }
      if ($status === 'fail' && (!isset($probe[$f['url']]) || !empty($probe[$f['url']]['ok']))) {
        return FALSE;
      }
      if ($status === 'silent' && !$this->catalog->isSilentFeed($f, $probe)) {
        return FALSE;
      }
      if ($status === 'empty' && !$this->catalog->isEmptyRssFeed($f, $probe)) {
        return FALSE;
      }
      if ($status === 'stale' && !$this->catalog->isStaleProbe($f, $probe)) {
        return FALSE;
      }
      if ($q !== '') {
        $hay = mb_strtolower($f['name'] . ' ' . $f['url'] . ' ' . $f['group'] . ' ' . $f['domain']);
        if (!str_contains($hay, mb_strtolower($q))) {
          return FALSE;
        }
      }
      return TRUE;
    }));
    $response = new StreamedResponse(function () use ($feeds, $probe) {
      $out = fopen('php://output', 'w');
      fputcsv($out, [
        'group', 'name', 'url', 'site', 'domain', 'trust_level', 'publisher',
        'paused', 'article_count', 'last_created',
        'probe_ok', 'probe_checked', 'probe_message', 'probe_item_count',
        'never_probed', 'probe_age_sec', 'silent', 'empty_rss', 'stale_probe',
        'short_read_url',
      ]);
      foreach ($feeds as $f) {
        $p = $probe[$f['url']] ?? NULL;
        $age = ($p !== NULL && !empty($p['checked'])) ? max(0, time() - (int) $p['checked']) : '';
        $src = trim((string) ($f['name'] ?? ''));
        $short = Url::fromRoute('xmt_trust_ui.short_read', [], [
          'query' => array_filter(['source' => $src !== '' ? $src : NULL]),
          'absolute' => TRUE,
        ])->toString();
        fputcsv($out, [
          $f['group'],
          $f['name'],
          $f['url'],
          $f['site'],
          $f['domain'],
          $f['trust_level'],
          $f['publisher'],
          $f['paused'] ? 1 : 0,
          $f['article_count'],
          $f['last_created'],
          $p === NULL ? '' : (!empty($p['ok']) ? 1 : 0),
          $p['checked'] ?? '',
          $p['message'] ?? '',
          $p['item_count'] ?? '',
          $p === NULL ? 1 : 0,
          $age,
          $this->catalog->isSilentFeed($f, $probe) ? 1 : 0,
          $this->catalog->isEmptyRssFeed($f, $probe) ? 1 : 0,
          $this->catalog->isStaleProbe($f, $probe) ? 1 : 0,
          $short,
        ]);
      }
      fclose($out);
    });
    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
    $suffix = gmdate('Ymd');
    if ($status !== 'all' && $status !== '') {
      $suffix .= '-' . preg_replace('/[^a-z0-9_-]+/i', '_', $status);
    }
    if ($group !== '') {
      $suffix .= '-' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', $group);
    }
    $response->headers->set('Content-Disposition', 'attachment; filename="xmt-sources-' . $suffix . '.csv"');
    return $response;
  }

  /**
   * Filter strip HTML.
   */
  protected function buildFilterMarkup(array $groups, string $group, string $trust, string $status, string $q, array $status_counts = []): string {
    $base = Url::fromRoute('xmt_trust_ui.source_ops')->toString();
    $chips = [];
    $chips[] = $this->chip($base, [], $group === '', (string) $this->t('全部分组'));
    foreach ($groups as $g => $n) {
      $chips[] = $this->chip($base, ['group' => $g, 'trust' => $trust ?: NULL, 'status' => $status !== 'all' ? $status : NULL, 'q' => $q ?: NULL], $group === $g, $g . " ($n)");
    }
    $trust_chips = [
      $this->chip($base, ['group' => $group ?: NULL, 'status' => $status !== 'all' ? $status : NULL, 'q' => $q ?: NULL], $trust === '', (string) $this->t('全部信任级')),
      $this->chip($base, ['group' => $group ?: NULL, 'trust' => 'l1_official', 'status' => $status !== 'all' ? $status : NULL, 'q' => $q ?: NULL], $trust === 'l1_official', 'L1'),
      $this->chip($base, ['group' => $group ?: NULL, 'trust' => 'l0_aggregate', 'status' => $status !== 'all' ? $status : NULL, 'q' => $q ?: NULL], $trust === 'l0_aggregate', 'L0'),
    ];
    $n_all = (int) ($status_counts['all'] ?? 0);
    $n_active = (int) ($status_counts['active'] ?? 0);
    $n_paused = (int) ($status_counts['paused'] ?? 0);
    $n_never = (int) ($status_counts['never'] ?? 0);
    $n_fail = (int) ($status_counts['fail'] ?? 0);
    $n_silent = (int) ($status_counts['silent'] ?? 0);
    $n_empty = (int) ($status_counts['empty'] ?? 0);
    $n_stale = (int) ($status_counts['stale'] ?? 0);
    $status_chips = [
      $this->chip($base, ['group' => $group ?: NULL, 'trust' => $trust ?: NULL, 'q' => $q ?: NULL], $status === 'all', (string) $this->t('全部状态（@n）', ['@n' => $n_all])),
      $this->chip($base, ['group' => $group ?: NULL, 'trust' => $trust ?: NULL, 'status' => 'active', 'q' => $q ?: NULL], $status === 'active', (string) $this->t('采集中（@n）', ['@n' => $n_active])),
      $this->chip($base, ['group' => $group ?: NULL, 'trust' => $trust ?: NULL, 'status' => 'paused', 'q' => $q ?: NULL], $status === 'paused', (string) $this->t('已暂停（@n）', ['@n' => $n_paused])),
      $this->chip($base, ['group' => $group ?: NULL, 'trust' => $trust ?: NULL, 'status' => 'never', 'q' => $q ?: NULL], $status === 'never', (string) $this->t('从未探测（@n）', ['@n' => $n_never])),
      $this->chip($base, ['group' => $group ?: NULL, 'trust' => $trust ?: NULL, 'status' => 'fail', 'q' => $q ?: NULL], $status === 'fail', (string) $this->t('探测失败（@n）', ['@n' => $n_fail])),
      $this->chip($base, ['group' => $group ?: NULL, 'trust' => $trust ?: NULL, 'status' => 'silent', 'q' => $q ?: NULL], $status === 'silent', (string) $this->t('静默（@n）', ['@n' => $n_silent])),
      $this->chip($base, ['group' => $group ?: NULL, 'trust' => $trust ?: NULL, 'status' => 'empty', 'q' => $q ?: NULL], $status === 'empty', (string) $this->t('空源（@n）', ['@n' => $n_empty])),
      $this->chip($base, ['group' => $group ?: NULL, 'trust' => $trust ?: NULL, 'status' => 'stale', 'q' => $q ?: NULL], $status === 'stale', (string) $this->t('过期（@n）', ['@n' => $n_stale])),
    ];
    $q_esc = htmlspecialchars($q, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $hidden = '';
    foreach (array_filter(['group' => $group, 'trust' => $trust, 'status' => $status !== 'all' ? $status : '']) as $k => $v) {
      if ($v === '') {
        continue;
      }
      $hidden .= '<input type="hidden" name="' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '">';
    }
    $clear = '';
    if ($group !== '' || $trust !== '' || ($status !== '' && $status !== 'all') || $q !== '') {
      $clear = '<div class="xmt-source-ops-filter-block">'
        . '<a class="button" href="' . htmlspecialchars($base, ENT_QUOTES, 'UTF-8') . '">'
        . $this->t('清空筛选') . '</a></div>';
    }
    return '<div class="xmt-source-ops-filter-block"><strong>' . $this->t('分组') . '</strong> ' . implode(' ', $chips) . '</div>'
      . '<div class="xmt-source-ops-filter-block"><strong>' . $this->t('信任') . '</strong> ' . implode(' ', $trust_chips) . '</div>'
      . '<div class="xmt-source-ops-filter-block"><strong>' . $this->t('状态') . '</strong> ' . implode(' ', $status_chips) . '</div>'
      . $clear
      . '<form method="get" action="' . htmlspecialchars($base, ENT_QUOTES, 'UTF-8') . '" class="xmt-source-ops-search">'
      . $hidden
      . '<label>' . $this->t('搜索') . ' <input type="search" name="q" value="' . $q_esc . '" placeholder="名称 / URL / 领域；状态筛可「从未探测」"></label> '
      . '<button type="submit" class="button">' . $this->t('筛选') . '</button></form>';
  }

  /**
   * Probe-result name cell linking to short-news.
   */
  protected function probeResultNameCell(string $name): array|string {
    $name = trim($name);
    if ($name === '') {
      return '—';
    }
    return [
      'data' => [
        '#type' => 'link',
        '#title' => $name,
        '#url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
          'query' => ['source' => $name],
        ]),
      ],
    ];
  }

  /**
   * One filter chip link.
   */
  protected function chip(string $base, array $query, bool $active, string $label): string {
    $query = array_filter($query, static fn($v) => $v !== NULL && $v !== '');
    $href = $base . ($query ? ('?' . http_build_query($query)) : '');
    $cls = $active ? 'button button--primary' : 'button';
    return '<a class="' . $cls . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a>';
  }

}
