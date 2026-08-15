<?php

namespace Drupal\xmt_trust_ui\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;

/**
 * Footer about widget using Sancy block title styles.
 *
 * @Block(
 *   id = "xmt_trust_footer_about",
 *   admin_label = @Translation("XMT Footer About"),
 *   category = @Translation("XMT")
 * )
 */
class TrustFooterAboutBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $name = \Drupal::config('system.site')->get('name') ?: '信媒体';
    $slogan = \Drupal::config('system.site')->get('slogan') ?: 'AI 时代的可信媒体源';
    return [
      '#markup' => '<div class="about-widget"><p>' . $this->t('@name · @slogan', [
        '@name' => $name,
        '@slogan' => $slogan,
      ]) . '</p><p><a href="' . Url::fromRoute('xmt_trust_ui.short_read')->toString() . '">' . $this->t('短闻') . '</a> · <a href="' . Url::fromRoute('xmt_trust_ui.search')->toString() . '">' . $this->t('可信搜索') . '</a> · <a href="' . Url::fromRoute('xmt_trust_ui.feed')->toString() . '">' . $this->t('可信分区') . '</a></p></div>',
      '#cache' => [
        'tags' => ['config:system.site'],
      ],
    ];
  }

}
