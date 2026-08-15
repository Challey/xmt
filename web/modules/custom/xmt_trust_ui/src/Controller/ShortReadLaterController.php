<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Client-side "read later" shelf for short news.
 */
class ShortReadLaterController extends ControllerBase {

  /**
   * Page shell; list filled from localStorage.
   */
  public function page(): array {
    return [
      '#theme' => 'xmt_short_read_later',
      '#feed_url' => Url::fromRoute('xmt_trust_ui.short_read')->toString(),
      '#immerse_url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
        'query' => ['mode' => 'immerse'],
      ])->toString(),
      '#rss_url' => Url::fromRoute('xmt_trust_ui.short_news_rss')->toString(),
      '#attached' => [
        'library' => ['xmt_trust_ui/short_read'],
        'drupalSettings' => [
          'xmtShortRead' => [
            'laterSyncUrl' => Url::fromRoute('xmt_trust_ui.short_read_later_sync')->toString(),
            'progressUrl' => Url::fromRoute('xmt_trust_ui.short_read_progress')->toString(),
            'uid' => (int) $this->currentUser()->id(),
            'csrfToken' => \Drupal::csrfToken()->get('rest'),
          ],
        ],
      ],
      '#cache' => [
        'contexts' => ['url.path', 'user'],
        'max-age' => 0,
      ],
    ];
  }

}
