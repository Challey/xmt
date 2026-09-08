<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Front page controller (chrome + blocks render the magazine UI).
 */
class TrustHomeController extends ControllerBase {

  /**
   * Empty main content; Sancy regions hold branding, ticker, and columns.
   */
  public function home(): array {
    return [
      '#markup' => '',
      '#cache' => [
        'contexts' => ['url.path'],
        'tags' => ['node_list'],
      ],
    ];
  }

}
