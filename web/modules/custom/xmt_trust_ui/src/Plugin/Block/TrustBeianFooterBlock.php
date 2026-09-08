<?php

namespace Drupal\xmt_trust_ui\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;

/**
 * Site footer strip: ICP / beian + short-news + WeChat help.
 *
 * @Block(
 *   id = "xmt_trust_beian_footer",
 *   admin_label = @Translation("XMT Beian Footer"),
 *   category = @Translation("XMT")
 * )
 */
class TrustBeianFooterBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $beian = (string) (Settings::get('xmt_beian') ?: '');
    $beian_url = (string) (Settings::get('xmt_beian_url') ?: 'https://beian.miit.gov.cn/');
    $intl = Settings::get('xmt_site_profile') === 'international';
    // International sites: no Chinese ICP / WeChat-help strip.
    if ($intl) {
      $beian = '';
    }
    return [
      '#theme' => 'xmt_beian_footer',
      '#beian' => $beian,
      '#beian_url' => $beian_url,
      '#international' => $intl,
      '#read_url' => Url::fromRoute('xmt_trust_ui.short_read')->toString(),
      '#today_url' => Url::fromRoute('xmt_trust_ui.short_read_today')->toString(),
      '#later_url' => Url::fromRoute('xmt_trust_ui.short_read_later')->toString(),
      '#search_url' => Url::fromRoute('xmt_trust_ui.search')->toString(),
      '#wechat_help_url' => $intl ? '' : Url::fromRoute('xmt_trust_ui.help_wechat')->toString(),
      '#cache' => [
        'max-age' => 3600,
      ],
    ];
  }

}
