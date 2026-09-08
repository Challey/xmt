<?php

namespace Drupal\xmt_trust_ui\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;

/**
 * Compact short-news entry for vertical front pages.
 *
 * @Block(
 *   id = "xmt_short_news_entry",
 *   admin_label = @Translation("XMT Short News Entry"),
 *   category = @Translation("XMT")
 * )
 */
class ShortNewsEntryBlock extends BlockBase {

  /**
   * Host => default domain slug for /read?domain=.
   */
  protected const HOST_DOMAIN = [
    'zhubao.pub' => 'jewelry',
    'www.zhubao.pub' => 'jewelry',
    'airobotor.com' => 'robot',
    'www.airobotor.com' => 'robot',
    'hm-os.com' => 'harmonyos',
    'www.hm-os.com' => 'harmonyos',
    'hm-os.cn' => 'harmonyos',
    'www.hm-os.cn' => 'harmonyos',
    'kstudy.com.cn' => 'ai_edu',
    'www.kstudy.com.cn' => 'ai_edu',
    'drupal.org.cn' => 'drupal',
    'www.drupal.org.cn' => 'drupal',
    'itra.com.cn' => 'itra',
    'www.itra.com.cn' => 'itra',
  ];

  /**
   * Domain-specific pitch for vertical fronts.
   */
  protected const DOMAIN_PITCH = [
    'harmonyos' => '鸿蒙与 OpenHarmony 开放动态 · 开发与应用一线信息，信流连刷、来源可核。',
    'robot' => 'AI 与机器人前沿 · 工业/人形/具身智能与行业资讯，短平快可读。',
    'jewelry' => '珠宝产业可信快读 · 默认进入本领域信流。',
    'ai_edu' => 'AI 教育前沿 · 本站领域短闻信流。',
    'drupal' => 'Drupal 生态动态 · 本站领域短闻信流。',
    'itra' => '越野跑与耐力资讯 · 本站领域短闻信流。',
  ];

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $host = \Drupal::request()->getHost();
    $domain = self::HOST_DOMAIN[$host] ?? '';
    $query = array_filter(['domain' => $domain !== '' ? $domain : NULL]);
    $brand = (string) $this->t('短闻');
    if ($domain !== '' && isset(self::DOMAIN_PITCH[$domain])) {
      $text = self::DOMAIN_PITCH[$domain];
    }
    elseif ($domain !== '') {
      $text = (string) $this->t('本站领域可信快读：默认进入信流连刷；需要时切速览。来源可核、原文直达。');
    }
    else {
      $text = (string) $this->t('默认信流连刷——短平快、可核验，xmt.pub 最粘的阅读方式。');
    }

    $official_media_url = Url::fromRoute('xmt_trust_ui.official_media')->toString();
    if ($domain !== '' && \Drupal\xmt_trust_ui\OfficialMediaChannels::isValid($domain)) {
      $official_media_url = Url::fromRoute('xmt_trust_ui.official_media_channel', [
        'channel' => $domain,
      ])->toString();
    }

    $hot_links = [];
    if ($domain === '') {
      foreach ([
        ['domain' => 'finance', 'label' => '财经'],
        ['domain' => 'tech', 'label' => '科技'],
        ['domain' => 'domestic', 'label' => '国内'],
        ['domain' => 'world', 'label' => '国际'],
      ] as $hot) {
        $hot_links[] = [
          'label' => $hot['label'],
          'url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
            'query' => ['domain' => $hot['domain']],
          ])->toString(),
        ];
      }
    }
    elseif ($domain === 'harmonyos') {
      $hot_links[] = [
        'label' => '进入鸿蒙信流',
        'url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
          'query' => ['domain' => 'harmonyos'],
        ])->toString(),
      ];
    }
    elseif ($domain === 'robot') {
      $hot_links[] = [
        'label' => '进入机器人信流',
        'url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
          'query' => ['domain' => 'robot'],
        ])->toString(),
      ];
    }

    return [
      '#theme' => 'xmt_short_news_entry',
      '#brand' => $brand,
      '#text' => $text,
      '#hot_links' => $hot_links,
      '#browse_url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
        'query' => $query + ['mode' => 'browse'],
      ])->toString(),
      '#immerse_url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
        'query' => $query,
      ])->toString(),
      '#later_url' => Url::fromRoute('xmt_trust_ui.short_read_later')->toString(),
      '#today_url' => Url::fromRoute('xmt_trust_ui.short_read_today', [], ['query' => $query])->toString(),
      '#rss_url' => Url::fromRoute('xmt_trust_ui.short_news_rss', [], ['query' => $query])->toString(),
      '#search_url' => Url::fromRoute('xmt_trust_ui.search')->toString(),
      '#official_media_url' => $official_media_url,
      '#attached' => ['library' => ['xmt_trust_ui/short_read']],
      '#cache' => [
        'contexts' => ['url.site'],
        'max-age' => 3600,
      ],
    ];
  }

}
