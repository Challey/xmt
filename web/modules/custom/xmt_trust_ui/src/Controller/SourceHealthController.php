<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\xmt_trust_ui\Service\SourceCatalog;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Weekly-style summary of failed probes / paused feeds.
 */
class SourceHealthController extends ControllerBase {

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
   * Dashboard of unhealthy sources.
   */
  public function report(Request $request): array {
    $group = trim((string) $request->query->get('group', ''));
    $date = \Drupal::service('date.formatter');
    $probe = $this->catalog->probeCacheMap();
    $paused = $this->catalog->pausedUrls();
    $failed_rows = [];
    $paused_rows = [];
    $stale_rows = [];
    $silent_rows = [];
    $empty_rows = [];
    $never = [];
    $stale = 0;
    $week = time() - 7 * 86400;
    $groups = [];
    $dest = Url::fromRoute('xmt_trust_ui.source_health', [], [
      'query' => array_filter(['group' => $group ?: NULL]),
    ])->toString();

    foreach ($this->catalog->feeds() as $f) {
      $groups[$f['group']] = TRUE;
      if ($group !== '' && $f['group'] !== $group) {
        continue;
      }
      $url = $f['url'];
      if (!empty($paused[$url])) {
        $paused_rows[] = [
          $f['group'],
          $this->sourceNameCell((string) ($f['name'] ?? '')),
          $f['site'],
          [
            'data' => [
              '#markup' => Link::fromTextAndUrl($this->t('恢复'), Url::fromRoute('xmt_trust_ui.source_ops_toggle', [], [
                'query' => [
                  'url' => $url,
                  'token' => \Drupal::csrfToken()->get('xmt_source_ops_toggle:' . $url),
                  'destination' => $dest,
                ],
              ]))->toString()
                . $this->copyFeedButton($url)
                . $this->copyShortButton(trim((string) ($f['name'] ?? '')))
                . (trim((string) ($f['name'] ?? '')) !== ''
                  ? ' · ' . Link::fromTextAndUrl($this->t('短闻'), Url::fromRoute('xmt_trust_ui.short_read', [], [
                    'query' => ['source' => trim((string) $f['name'])],
                  ]))->toString()
                  : ''),
            ],
          ],
        ];
      }
      if (!isset($probe[$url])) {
        $never[] = [
          $f['group'],
          $this->sourceNameCell((string) ($f['name'] ?? '')),
          $f['site'],
          [
            'data' => [
              '#markup' => Link::fromTextAndUrl($this->t('探测'), Url::fromRoute('xmt_trust_ui.source_ops_probe', [], [
                'query' => [
                  'url' => $url,
                  'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe:' . $url),
                ],
              ]))->toString()
                . $this->copyFeedButton($url)
                . $this->copyShortButton(trim((string) ($f['name'] ?? '')))
                . (trim((string) ($f['name'] ?? '')) !== ''
                  ? ' · ' . Link::fromTextAndUrl($this->t('短闻'), Url::fromRoute('xmt_trust_ui.short_read', [], [
                    'query' => ['source' => trim((string) $f['name'])],
                  ]))->toString()
                  : ''),
            ],
          ],
        ];
        continue;
      }
      $p = $probe[$url];
      if (($p['checked'] ?? 0) < $week) {
        $stale++;
        $src_name = trim((string) ($f['name'] ?? ''));
        $stale_probe_label = !empty($p['ok']) ? $this->t('通') : $this->t('败');
        if (isset($p['item_count'])) {
          $stale_probe_label .= ' · ' . $this->t('@n条', ['@n' => (int) $p['item_count']]);
        }
        $stale_rows[] = [
          'checked' => (int) ($p['checked'] ?? 0),
          'row' => [
            $f['group'],
            $this->sourceNameCell($src_name),
            $p['checked'] ? $date->format($p['checked'], 'short') : '—',
            $stale_probe_label,
            [
              'data' => [
                '#markup' => Link::fromTextAndUrl($this->t('探测'), Url::fromRoute('xmt_trust_ui.source_ops_probe', [], [
                  'query' => [
                    'url' => $url,
                    'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe:' . $url),
                    'fresh' => 1,
                  ],
                ]))->toString()
                  . ' · '
                  . Link::fromTextAndUrl($this->t('暂停'), Url::fromRoute('xmt_trust_ui.source_ops_toggle', [], [
                    'query' => [
                      'url' => $url,
                      'token' => \Drupal::csrfToken()->get('xmt_source_ops_toggle:' . $url),
                      'destination' => $dest,
                    ],
                  ]))->toString()
                  . $this->copyFeedButton($url)
                  . $this->copyShortButton($src_name)
                  . ($src_name !== ''
                    ? ' · ' . Link::fromTextAndUrl($this->t('短闻'), Url::fromRoute('xmt_trust_ui.short_read', [], [
                      'query' => ['source' => $src_name],
                    ]))->toString()
                    : ''),
              ],
            ],
          ],
        ];
      }
      if (!empty($p['ok'])) {
        $src_name = trim((string) ($f['name'] ?? ''));
        $ops_links = Link::fromTextAndUrl($this->t('暂停'), Url::fromRoute('xmt_trust_ui.source_ops_toggle', [], [
          'query' => [
            'url' => $url,
            'token' => \Drupal::csrfToken()->get('xmt_source_ops_toggle:' . $url),
            'destination' => $dest,
          ],
        ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('探测'), Url::fromRoute('xmt_trust_ui.source_ops_probe', [], [
            'query' => [
              'url' => $url,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe:' . $url),
              'fresh' => 1,
            ],
          ]))->toString()
          . ($src_name !== ''
            ? ' · ' . Link::fromTextAndUrl($this->t('短闻'), Url::fromRoute('xmt_trust_ui.short_read', [], [
              'query' => ['source' => $src_name],
            ]))->toString()
            : '');
        if ($this->catalog->isEmptyRssFeed($f, $probe)) {
          $empty_rows[] = [
            'sort' => (int) ($p['checked'] ?? 0),
            'row' => [
              $f['group'],
              $this->sourceNameCell($src_name),
              $this->t('@n条', ['@n' => (int) ($p['item_count'] ?? 0)]),
              $p['checked'] ? $date->format($p['checked'], 'short') : '—',
              [
                'data' => [
                  '#markup' => $ops_links
                    . ' · '
                    . Link::fromTextAndUrl($this->t('运营台'), Url::fromRoute('xmt_trust_ui.source_ops', [], [
                      'query' => array_filter([
                        'group' => $f['group'] ?: NULL,
                        'status' => 'empty',
                        'q' => $f['name'],
                      ]),
                    ]))->toString(),
                ],
              ],
            ],
          ];
        }
        if ($this->catalog->isSilentFeed($f, $probe)) {
          $silent_rows[] = [
            'sort' => (int) ($f['last_created'] ?? 0),
            'row' => [
              $f['group'],
              $this->sourceNameCell($src_name),
              (int) $f['article_count'],
              isset($p['item_count']) ? $this->t('@n条', ['@n' => (int) $p['item_count']]) : '—',
              $f['last_created'] ? $date->format($f['last_created'], 'short') : '—',
              [
                'data' => [
                  '#markup' => $ops_links
                    . ' · '
                    . Link::fromTextAndUrl($this->t('运营台'), Url::fromRoute('xmt_trust_ui.source_ops', [], [
                      'query' => array_filter([
                        'group' => $f['group'] ?: NULL,
                        'status' => 'silent',
                        'q' => $f['name'],
                      ]),
                    ]))->toString(),
                ],
              ],
            ],
          ];
        }
        continue;
      }
      $fail_msg = trim((string) ($p['message'] ?? ''));
      $fail_msg_cell = $fail_msg !== ''
        ? [
          'data' => mb_strlen($fail_msg) > 64 ? (mb_substr($fail_msg, 0, 64) . '…') : $fail_msg,
          'title' => $fail_msg,
        ]
        : '—';
      $failed_rows[] = [
        'checked' => (int) ($p['checked'] ?? 0),
        'row' => [
          $f['group'],
          $this->sourceNameCell((string) ($f['name'] ?? '')),
          $fail_msg_cell,
          $p['checked'] ? $date->format($p['checked'], 'short') : '—',
          !empty($paused[$url]) ? $this->t('已暂停') : $this->t('采集中'),
          [
            'data' => [
              '#markup' => (!empty($paused[$url])
                ? Link::fromTextAndUrl($this->t('恢复'), Url::fromRoute('xmt_trust_ui.source_ops_toggle', [], [
                  'query' => [
                    'url' => $url,
                    'token' => \Drupal::csrfToken()->get('xmt_source_ops_toggle:' . $url),
                    'destination' => $dest,
                  ],
                ]))->toString()
                : Link::fromTextAndUrl($this->t('暂停'), Url::fromRoute('xmt_trust_ui.source_ops_toggle', [], [
                  'query' => [
                    'url' => $url,
                    'token' => \Drupal::csrfToken()->get('xmt_source_ops_toggle:' . $url),
                    'destination' => $dest,
                  ],
                ]))->toString())
                  . ' · '
                  . Link::fromTextAndUrl($this->t('探测'), Url::fromRoute('xmt_trust_ui.source_ops_probe', [], [
                    'query' => [
                      'url' => $url,
                      'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe:' . $url),
                      'fresh' => 1,
                    ],
                  ]))->toString()
                  . $this->copyFeedButton($url)
                  . $this->copyShortButton(trim((string) ($f['name'] ?? '')))
                  . (trim((string) ($f['name'] ?? '')) !== ''
                    ? ' · ' . Link::fromTextAndUrl($this->t('短闻'), Url::fromRoute('xmt_trust_ui.short_read', [], [
                      'query' => ['source' => trim((string) $f['name'])],
                    ]))->toString()
                    : ''),
            ],
          ],
        ],
      ];
    }

    usort($stale_rows, static fn(array $a, array $b): int => ($a['checked'] ?? 0) <=> ($b['checked'] ?? 0));
    $stale_total = count($stale_rows);
    $stale_table_rows = array_map(static fn(array $x) => $x['row'], array_slice($stale_rows, 0, 50));
    usort($never, static function (array $a, array $b): int {
      $name = static function ($cell): string {
        if (is_array($cell) && isset($cell['data']['#title'])) {
          return (string) $cell['data']['#title'];
        }
        return (string) ($cell ?? '');
      };
      return [$a[0] ?? '', $name($a[1] ?? '')] <=> [$b[0] ?? '', $name($b[1] ?? '')];
    });
    usort($failed_rows, static fn(array $a, array $b): int => ($a['checked'] ?? 0) <=> ($b['checked'] ?? 0));
    $failed_table_rows = array_map(static fn(array $x) => $x['row'], array_slice($failed_rows, 0, 50));
    $failed_total = count($failed_rows);
    $paused_total = count($paused_rows);
    $paused_table_rows = array_slice($paused_rows, 0, 50);
    usort($silent_rows, static fn(array $a, array $b): int => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));
    $silent_total = count($silent_rows);
    $silent_table_rows = array_map(static fn(array $x) => $x['row'], array_slice($silent_rows, 0, 50));
    usort($empty_rows, static fn(array $a, array $b): int => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));
    $empty_total = count($empty_rows);
    $empty_table_rows = array_map(static fn(array $x) => $x['row'], array_slice($empty_rows, 0, 50));

    ksort($groups);
    $group_chips = '<div class="xmt-source-ops-filter-block"><strong>' . $this->t('分组') . '</strong> ';
    $all_url = Url::fromRoute('xmt_trust_ui.source_health')->toString();
    $group_chips .= '<a href="' . htmlspecialchars($all_url, ENT_QUOTES, 'UTF-8') . '"'
      . ($group === '' ? ' class="is-active"' : '') . '>' . $this->t('全部') . '</a> ';
    foreach (array_keys($groups) as $g) {
      $u = Url::fromRoute('xmt_trust_ui.source_health', [], ['query' => ['group' => $g]])->toString();
      $group_chips .= '<a href="' . htmlspecialchars($u, ENT_QUOTES, 'UTF-8') . '"'
        . ($group === $g ? ' class="is-active"' : '') . '>'
        . htmlspecialchars($g, ENT_QUOTES, 'UTF-8') . '</a> ';
    }
    $group_chips .= '</div>';

    // Hot-industry shortcuts → 运营台分组（R434）.
    $hot_chips = '<div class="xmt-source-ops-filter-block"><strong>' . $this->t('热点') . '</strong> ';
    $hot_groups = array_values(array_filter(array_keys($groups), static fn(string $g): bool => str_starts_with($g, 'hot_')));
    sort($hot_groups);
    foreach ($hot_groups as $hg) {
      $u = Url::fromRoute('xmt_trust_ui.source_ops', [], ['query' => ['group' => $hg]])->toString();
      $hot_chips .= '<a href="' . htmlspecialchars($u, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($hg, ENT_QUOTES, 'UTF-8') . '</a> ';
    }
    $hot_chips .= '</div>';
    $group_chips .= $hot_chips;

    $pause_token_suffix = $group !== '' ? $group : '*';
    $snap = $this->catalog->agentSnapshot();
    $agent_last = '';
    if ($snap['last_published'] !== NULL || $snap['last_errors'] !== NULL) {
      $agent_last = ' · ' . $this->t('上次 Agent published=@p errors=@e@mode', [
        '@p' => $snap['last_published'] ?? '—',
        '@e' => $snap['last_errors'] ?? '—',
        '@mode' => !empty($snap['last_run_mode'])
          ? (' [' . $snap['last_run_mode'] . (!empty($snap['last_run_stamp']) ? ' ' . $snap['last_run_stamp'] : '') . ']')
          : '',
      ]);
    }
    $date = \Drupal::service('date.formatter');
    if (!empty($snap['last_ingest_at'])) {
      $agent_last .= ' · ' . $this->t('限流入库 @g @t@ok', [
        '@g' => $snap['last_ingest_group'] !== '' ? $snap['last_ingest_group'] : '—',
        '@t' => $date->format((int) $snap['last_ingest_at'], 'short'),
        '@ok' => !empty($snap['last_ingest_ok']) ? ' ✓' : ' ✗',
      ]);
      $agent_last .= ' · ' . Link::fromTextAndUrl(
        $this->t('查看上次入库'),
        Url::fromRoute('xmt_trust_ui.source_ops', [], [
          'query' => array_filter(['group' => $group ?: NULL]),
          'fragment' => 'xmt-last-ingest',
        ])
      )->toString();
    }

    $agent_alert = '';
    if (!empty($snap['log_tail']) && preg_match('/\b(FAIL|ERR|error|Exception|Traceback)\b/i', $snap['log_tail'])) {
      $agent_alert = '<div class="messages messages--warning"><p>'
        . $this->t('Agent 最近日志含失败关键字，请检查运营台日志或服务器 cron。')
        . ' '
        . Link::fromTextAndUrl($this->t('运营台'), Url::fromRoute('xmt_trust_ui.source_ops', [], [
          'query' => array_filter(['group' => $group ?: NULL]),
        ]))->toString()
        . '</p></div>';
    }
    elseif (!empty($snap['log_mtime']) && (time() - (int) $snap['log_mtime']) > 2 * 86400) {
      $agent_alert = '<div class="messages messages--warning"><p>'
        . $this->t('Agent last_run.log 超过 48 小时未更新，采集可能已停。')
        . ' '
        . Link::fromTextAndUrl($this->t('运营台'), Url::fromRoute('xmt_trust_ui.source_ops', [], [
          'query' => array_filter(['group' => $group ?: NULL]),
        ]))->toString()
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
          'query' => array_filter([
            'group' => $group ?: NULL,
            'status' => 'silent',
          ]),
        ]))->toString()
        . ' · '
        . Link::fromTextAndUrl($this->t('运营台'), Url::fromRoute('xmt_trust_ui.source_ops', [], [
          'query' => array_filter(['group' => $group ?: NULL]),
        ]))->toString()
        . (!empty($snap['last_ingest_at'])
          ? ' · ' . Link::fromTextAndUrl($this->t('上次入库'), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter(['group' => $group ?: NULL]),
            'fragment' => 'xmt-last-ingest',
          ]))->toString()
          : '')
        . '</p></div>';
    }

    return [
      'agent_alert' => [
        '#markup' => $agent_alert,
      ],
      'intro' => [
        '#markup' => '<p>' . $this->t('失败探测 @fail · 已暂停 @paused · 超 7 天 @stale · 从未探测 @never · 静默 @silent · 空源 @empty', [
          '@fail' => $failed_total,
          '@paused' => $paused_total,
          '@stale' => $stale_total,
          '@never' => count($never),
          '@silent' => $silent_total,
          '@empty' => $empty_total,
        ]) . $agent_last
          . ($group !== '' ? ' · ' . $this->t('当前分组 @g（导出 JSON 同范围）', ['@g' => $group]) : '')
          . ' · '
          . Link::fromTextAndUrl($this->t('运营台'), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter(['group' => $group ?: NULL]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('统计'), Url::fromRoute('xmt_trust_ui.stats'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('短闻'), Url::fromRoute('xmt_trust_ui.short_read'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('今日简报'), Url::fromRoute('xmt_trust_ui.short_read_today'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('稍后'), Url::fromRoute('xmt_trust_ui.short_read_later'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('搜索'), Url::fromRoute('xmt_trust_ui.search'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('官媒'), Url::fromRoute('xmt_trust_ui.official_media'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('Sitemap'), Url::fromRoute('xmt_trust_ui.trust_sitemap'))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('静默（@n）', ['@n' => $silent_total]), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'status' => 'silent',
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('空源（@n）', ['@n' => $empty_total]), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'status' => 'empty',
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('重测空源'), Url::fromRoute('xmt_trust_ui.source_ops_probe_empty', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe_empty:' . $pause_token_suffix),
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('暂停空源'), Url::fromRoute('xmt_trust_ui.source_ops_pause_empty', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_empty:' . $pause_token_suffix),
              'destination' => $dest,
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('重测静默'), Url::fromRoute('xmt_trust_ui.source_ops_probe_silent', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe_silent:' . $pause_token_suffix),
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('重测过期'), Url::fromRoute('xmt_trust_ui.source_ops_probe_stale', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe_stale:' . $pause_token_suffix),
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('过期（@n）', ['@n' => $stale_total]), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'status' => 'stale',
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('暂停过期'), Url::fromRoute('xmt_trust_ui.source_ops_pause_stale', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_stale:' . $pause_token_suffix),
              'destination' => $dest,
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('恢复暂停'), Url::fromRoute('xmt_trust_ui.source_ops_resume_paused', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_resume_paused:' . $pause_token_suffix),
              'destination' => $dest,
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('暂停静默'), Url::fromRoute('xmt_trust_ui.source_ops_pause_silent', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_silent:' . $pause_token_suffix),
              'destination' => $dest,
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('失败（@n）', ['@n' => $failed_total]), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'status' => 'fail',
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('重测失败'), Url::fromRoute('xmt_trust_ui.source_ops_probe_failed', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe_failed:' . $pause_token_suffix),
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('从未（@n）', ['@n' => count($never)]), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'status' => 'never',
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('暂停探测失败'), Url::fromRoute('xmt_trust_ui.source_ops_pause_failed', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_failed:' . $pause_token_suffix),
              'destination' => $dest,
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('首测从未'), Url::fromRoute('xmt_trust_ui.source_ops_probe_never', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe_never:' . $pause_token_suffix),
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('暂停从未探测'), Url::fromRoute('xmt_trust_ui.source_ops_pause_never', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_never:' . $pause_token_suffix),
              'destination' => $dest,
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('导出 CSV'), Url::fromRoute('xmt_trust_ui.source_ops_export', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'status' => $failed_total > 0 ? 'fail' : NULL,
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl(
            $group !== ''
              ? $this->t('导出 JSON（@g）', ['@g' => $group])
              : $this->t('导出 JSON'),
            Url::fromRoute('xmt_trust_ui.source_health_export', [], [
              'query' => array_filter(['group' => $group ?: NULL]),
            ])
          )->toString()
          . '</p>' . $group_chips,
        '#attached' => ['library' => ['xmt_trust_ui/source_ops']],
      ],
      'failed' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['xmt-source-ops-table']],
        '#prefix' => '<div class="xmt-source-ops-table-wrap">',
        '#suffix' => '</div>',
        '#caption' => $this->t('探测失败（缓存，最久失败优先，前 50；总数 @n）', ['@n' => $failed_total]),
        '#header' => [
          $this->t('分组'),
          $this->t('信源'),
          $this->t('说明'),
          $this->t('检测时间'),
          $this->t('状态'),
          $this->t('操作'),
        ],
        '#rows' => $failed_table_rows,
        '#empty' => $this->t('暂无失败记录（可先到运营台分组「探测」刷新缓存）'),
      ],
      'failed_more' => [
        '#markup' => '<p>'
          . Link::fromTextAndUrl($this->t('重测失败（最多 15）'), Url::fromRoute('xmt_trust_ui.source_ops_probe_failed', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe_failed:' . $pause_token_suffix),
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('暂停失败'), Url::fromRoute('xmt_trust_ui.source_ops_pause_failed', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_failed:' . $pause_token_suffix),
              'destination' => $dest,
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('在运营台查看全部探测失败（@n）', ['@n' => $failed_total]), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'status' => 'fail',
            ]),
          ]))->toString() . '</p>',
      ],
      'paused' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['xmt-source-ops-table']],
        '#prefix' => '<div class="xmt-source-ops-table-wrap">',
        '#suffix' => '</div>',
        '#caption' => $this->t('当前暂停（前 50；总数 @n）', ['@n' => $paused_total]),
        '#header' => [
          $this->t('分组'),
          $this->t('信源'),
          $this->t('站点'),
          $this->t('操作'),
        ],
        '#rows' => $paused_table_rows,
        '#empty' => $this->t('无暂停信源'),
      ],
      'paused_more' => [
        '#markup' => '<p>'
          . Link::fromTextAndUrl($this->t('恢复暂停'), Url::fromRoute('xmt_trust_ui.source_ops_resume_paused', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_resume_paused:' . $pause_token_suffix),
              'destination' => $dest,
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('在运营台查看全部已暂停（@n）', ['@n' => $paused_total]), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'status' => 'paused',
            ]),
          ]))->toString() . '</p>',
      ],
      'stale' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['xmt-source-ops-table']],
        '#prefix' => '<div class="xmt-source-ops-table-wrap">',
        '#suffix' => '</div>',
        '#caption' => $this->t('超过 7 天未再探测（最久未探测优先，前 50；总数 @n）', ['@n' => $stale_total]),
        '#header' => [
          $this->t('分组'),
          $this->t('信源'),
          $this->t('上次检测'),
          $this->t('结果'),
          $this->t('操作'),
        ],
        '#rows' => $stale_table_rows,
        '#empty' => $this->t('无过期探测（可到运营台分组「探测」刷新缓存）'),
      ],
      'stale_more' => [
        '#markup' => '<p>'
          . Link::fromTextAndUrl($this->t('重测过期（最多 20）'), Url::fromRoute('xmt_trust_ui.source_ops_probe_stale', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe_stale:' . $pause_token_suffix),
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('暂停过期'), Url::fromRoute('xmt_trust_ui.source_ops_pause_stale', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_stale:' . $pause_token_suffix),
              'destination' => $dest,
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('打开运营台过期视图（@n）', ['@n' => $stale_total]), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'status' => 'stale',
            ]),
          ]))->toString() . '</p>',
      ],
      'never' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['xmt-source-ops-table']],
        '#prefix' => '<div class="xmt-source-ops-table-wrap">',
        '#suffix' => '</div>',
        '#caption' => $this->t('从未探测（按分组+名称；前 40；总数 @n）', ['@n' => count($never)]),
        '#header' => [
          $this->t('分组'),
          $this->t('信源'),
          $this->t('站点'),
          $this->t('操作'),
        ],
        '#rows' => array_slice($never, 0, 40),
        '#empty' => $this->t('均已探测过'),
      ],
      'never_more' => [
        '#markup' => '<p>'
          . Link::fromTextAndUrl($this->t('首测从未（最多 20）'), Url::fromRoute('xmt_trust_ui.source_ops_probe_never', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe_never:' . $pause_token_suffix),
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('暂停从未'), Url::fromRoute('xmt_trust_ui.source_ops_pause_never', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_never:' . $pause_token_suffix),
              'destination' => $dest,
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('在运营台查看全部从未探测（@n）', ['@n' => count($never)]), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'status' => 'never',
            ]),
          ]))->toString() . '</p>',
      ],
      'silent' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['xmt-source-ops-table']],
        '#prefix' => '<div class="xmt-source-ops-table-wrap">',
        '#suffix' => '</div>',
        '#caption' => $this->t('静默信源（探测通但 ≥7 天无入库 / 零入库；最久未入库优先，前 50；总数 @n）', ['@n' => $silent_total]),
        '#header' => [
          $this->t('分组'),
          $this->t('信源'),
          $this->t('入库数'),
          $this->t('RSS条数'),
          $this->t('最近入库'),
          $this->t('操作'),
        ],
        '#rows' => $silent_table_rows,
        '#empty' => $this->t('无静默信源（需先探测为通）'),
      ],
      'silent_more' => [
        '#markup' => '<p>'
          . Link::fromTextAndUrl($this->t('重测静默'), Url::fromRoute('xmt_trust_ui.source_ops_probe_silent', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe_silent:' . $pause_token_suffix),
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('暂停静默'), Url::fromRoute('xmt_trust_ui.source_ops_pause_silent', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_silent:' . $pause_token_suffix),
              'destination' => $dest,
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('在运营台查看全部静默（@n）', ['@n' => $silent_total]), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'status' => 'silent',
            ]),
          ]))->toString() . '</p>',
      ],
      'empty' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['xmt-source-ops-table']],
        '#prefix' => '<div class="xmt-source-ops-table-wrap">',
        '#suffix' => '</div>',
        '#caption' => $this->t('空源（探测通但 RSS/Atom 0 条；最久探测优先，前 50；总数 @n）', ['@n' => $empty_total]),
        '#header' => [
          $this->t('分组'),
          $this->t('信源'),
          $this->t('条目'),
          $this->t('探测时间'),
          $this->t('操作'),
        ],
        '#rows' => $empty_table_rows,
        '#empty' => $this->t('无空源（需探测缓存含 item_count=0）'),
      ],
      'empty_more' => [
        '#markup' => '<p>'
          . Link::fromTextAndUrl($this->t('重测空源'), Url::fromRoute('xmt_trust_ui.source_ops_probe_empty', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_probe_empty:' . $pause_token_suffix),
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('暂停空源'), Url::fromRoute('xmt_trust_ui.source_ops_pause_empty', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'token' => \Drupal::csrfToken()->get('xmt_source_ops_pause_empty:' . $pause_token_suffix),
              'destination' => $dest,
            ]),
          ]))->toString()
          . ' · '
          . Link::fromTextAndUrl($this->t('在运营台查看全部空源（@n）', ['@n' => $empty_total]), Url::fromRoute('xmt_trust_ui.source_ops', [], [
            'query' => array_filter([
              'group' => $group ?: NULL,
              'status' => 'empty',
            ]),
          ]))->toString() . '</p>',
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * JSON export of health snapshot.
   */
  public function export(Request $request): Response {
    $group = trim((string) $request->query->get('group', ''));
    $probe = $this->catalog->probeCacheMap();
    $paused = $this->catalog->pausedUrls();
    $failed = [];
    $paused_list = [];
    $never = [];
    $stale = [];
    $silent = [];
    $empty = [];
    $week = time() - 7 * 86400;
    foreach ($this->catalog->feeds() as $f) {
      if ($group !== '' && $f['group'] !== $group) {
        continue;
      }
      $url = $f['url'];
      if (!empty($paused[$url])) {
        $paused_list[] = [
          'group' => $f['group'],
          'name' => $f['name'],
          'url' => $url,
          'site' => $f['site'],
          'short_read_url' => $this->shortReadAbs((string) ($f['name'] ?? '')),
        ];
      }
      if (!isset($probe[$url])) {
        $never[] = [
          'group' => $f['group'],
          'name' => $f['name'],
          'url' => $url,
          'site' => $f['site'],
          'short_read_url' => $this->shortReadAbs((string) ($f['name'] ?? '')),
        ];
        continue;
      }
      $p = $probe[$url];
      if (($p['checked'] ?? 0) < $week) {
        $stale[] = [
          'group' => $f['group'],
          'name' => $f['name'],
          'url' => $url,
          'checked' => $p['checked'] ?? 0,
          'ok' => !empty($p['ok']),
          'message' => $p['message'] ?? '',
          'item_count' => $p['item_count'] ?? NULL,
          'short_read_url' => $this->shortReadAbs((string) ($f['name'] ?? '')),
        ];
      }
      if (!empty($p['ok'])) {
        if ($this->catalog->isEmptyRssFeed($f, $probe)) {
          $empty[] = [
            'group' => $f['group'],
            'name' => $f['name'],
            'url' => $url,
            'checked' => $p['checked'] ?? 0,
            'item_count' => 0,
            'short_read_url' => $this->shortReadAbs((string) ($f['name'] ?? '')),
          ];
        }
        if ($this->catalog->isSilentFeed($f, $probe)) {
          $silent[] = [
            'group' => $f['group'],
            'name' => $f['name'],
            'url' => $url,
            'article_count' => (int) $f['article_count'],
            'last_created' => (int) $f['last_created'],
            'item_count' => $p['item_count'] ?? NULL,
            'short_read_url' => $this->shortReadAbs((string) ($f['name'] ?? '')),
          ];
        }
        continue;
      }
      $failed[] = [
        'group' => $f['group'],
        'name' => $f['name'],
        'url' => $url,
        'message' => $p['message'] ?? '',
        'checked' => $p['checked'] ?? 0,
        'paused' => !empty($paused[$url]),
        'item_count' => $p['item_count'] ?? NULL,
        'short_read_url' => $this->shortReadAbs((string) ($f['name'] ?? '')),
      ];
    }
    $ingest = \Drupal::state()->get('xmt_source_ops.last_ingest', []);
    $ingest_export = NULL;
    if (is_array($ingest) && !empty($ingest['at'])) {
      $tail = (string) ($ingest['tail'] ?? '');
      if (mb_strlen($tail) > 2000) {
        $tail = mb_substr($tail, -2000);
      }
      $ingest_export = [
        'at' => (int) $ingest['at'],
        'group' => (string) ($ingest['group'] ?? ''),
        'ok' => !empty($ingest['ok']),
        'code' => $ingest['code'] ?? NULL,
        'max_per_feed' => $ingest['max_per_feed'] ?? NULL,
        'tail' => $tail,
      ];
    }
    $payload = [
      'schema' => 'xmt.source_health.v2.8',
      'generated' => time(),
      'group' => $group !== '' ? $group : NULL,
      'notes' => 'Pass ?group= to filter; omit for full catalog. Deep-links: /admin/xmt/sources?status=fail|never|paused|active|silent|empty|stale. Each triage row may include short_read_url (?source=). last_ingest mirrors ops limited ingest cache. agent.last_run_mode/stamp from last_run.log header.',
      'links' => [
        'ops' => Url::fromRoute('xmt_trust_ui.source_ops', [], [
          'query' => array_filter(['group' => $group ?: NULL]),
          'absolute' => TRUE,
        ])->toString(),
        'ops_last_ingest' => Url::fromRoute('xmt_trust_ui.source_ops', [], [
          'query' => array_filter(['group' => $group ?: NULL]),
          'fragment' => 'xmt-last-ingest',
          'absolute' => TRUE,
        ])->toString(),
        'health' => Url::fromRoute('xmt_trust_ui.source_health', [], [
          'query' => array_filter(['group' => $group ?: NULL]),
          'absolute' => TRUE,
        ])->toString(),
        'short_read' => Url::fromRoute('xmt_trust_ui.short_read', [], ['absolute' => TRUE])->toString(),
        'today' => Url::fromRoute('xmt_trust_ui.short_read_today', [], ['absolute' => TRUE])->toString(),
        'later' => Url::fromRoute('xmt_trust_ui.short_read_later', [], ['absolute' => TRUE])->toString(),
        'search' => Url::fromRoute('xmt_trust_ui.search', [], ['absolute' => TRUE])->toString(),
      ],
      'failed_count' => count($failed),
      'paused_count' => count($paused_list),
      'never_count' => count($never),
      'stale_count' => count($stale),
      'silent_count' => count($silent),
      'empty_count' => count($empty),
      'failed' => $failed,
      'paused' => $paused_list,
      'never' => $never,
      'stale' => $stale,
      'silent' => $silent,
      'empty' => $empty,
      'last_ingest' => $ingest_export,
      'agent' => $this->catalog->agentSnapshot(),
    ];
    // Strip large log from export agent snapshot.
    unset($payload['agent']['log_tail']);
    $fname = 'xmt-source-health-' . gmdate('Ymd') . ($group !== '' ? '-' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', $group) : '') . '.json';
    return new JsonResponse($payload, 200, [
      'Content-Disposition' => 'attachment; filename="' . $fname . '"',
    ]);
  }

  /**
   * Absolute short-news URL filtered by source name (or hub feed if empty).
   */
  protected function shortReadAbs(string $name): string {
    $name = trim($name);
    return Url::fromRoute('xmt_trust_ui.short_read', [], [
      'query' => array_filter(['source' => $name !== '' ? $name : NULL]),
      'absolute' => TRUE,
    ])->toString();
  }

  /**
   * One-click copy Feed URL button markup.
   */
  protected function copyFeedButton(string $url): string {
    $url = trim($url);
    if ($url === '') {
      return '';
    }
    return ' · <button type="button" class="button button--small xmt-source-ops-copy-feed" data-xmt-copy-feed="'
      . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
      . '">' . $this->t('复制 Feed') . '</button>';
  }

  /**
   * One-click copy absolute short-read ?source= URL (R428).
   */
  protected function copyShortButton(string $name): string {
    $abs = $this->shortReadAbs($name);
    if ($abs === '') {
      return '';
    }
    return ' · <button type="button" class="button button--small xmt-source-ops-copy-short" data-xmt-copy-short="'
      . htmlspecialchars($abs, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
      . '" title="' . htmlspecialchars((string) $this->t('复制 /read?source=… 绝对短链'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
      . '">' . $this->t('复制短链') . '</button>';
  }

  /**
   * Source name cell linking to short-news ?source=.
   */
  protected function sourceNameCell(string $name): array|string {
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

}
