<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;

/**
 * Help pages: WeChat open / ICP notes.
 */
class TrustHelpController extends ControllerBase {

  /**
   * WeChat link interception help.
   */
  public function wechat(): array {
    $beian = (string) (Settings::get('xmt_beian') ?: '');
    $target = trim((string) \Drupal::request()->query->get('u', ''));
    if ($target !== '' && !preg_match('#^https?://#i', $target)) {
      $target = '';
    }
    // Only allow same-host absolute URLs as copy target.
    if ($target !== '') {
      $host = \Drupal::request()->getHost();
      $parts = parse_url($target);
      if (($parts['host'] ?? '') !== $host) {
        $target = '';
      }
    }
    $read = Url::fromRoute('xmt_trust_ui.short_read', [], ['absolute' => TRUE])->toString();
    $today = Url::fromRoute('xmt_trust_ui.short_read_today', [], ['absolute' => TRUE])->toString();
    return [
      '#theme' => 'xmt_help_wechat',
      '#read_url' => $read,
      '#today_url' => $today,
      '#later_url' => Url::fromRoute('xmt_trust_ui.short_read_later', [], ['absolute' => TRUE])->toString(),
      '#search_url' => Url::fromRoute('xmt_trust_ui.search', [], ['absolute' => TRUE])->toString(),
      '#home_url' => Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString(),
      '#beian' => $beian,
      '#target_url' => $target !== '' ? $target : $read,
      '#attached' => [
        'library' => ['xmt_trust_ui/trust_feed', 'xmt_trust_ui/short_read'],
      ],
      '#cache' => [
        'contexts' => ['url.query_args:u'],
        'max-age' => 600,
      ],
    ];
  }

}
