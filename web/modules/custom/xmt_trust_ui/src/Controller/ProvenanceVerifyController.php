<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Public provenance verification: look up an article by its provenance hash.
 */
class ProvenanceVerifyController extends ControllerBase {

  /**
   * Renders the verification form and, when a hash is given, the result.
   */
  public function page(Request $request): array {
    $hash = trim((string) $request->query->get('hash', ''));

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['xmt-trust-verify']],
      '#cache' => ['contexts' => ['url.query_args:hash']],
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Enter a provenance hash to verify whether it matches a trusted article on this platform.'),
      ],
      'form' => [
        '#type' => 'inline_template',
        '#template' => '<form method="get" action="{{ action }}" class="xmt-trust-verify__form"><input type="text" name="hash" value="{{ hash }}" size="72" maxlength="128" placeholder="{{ placeholder }}" /> <button type="submit" class="button button--primary">{{ submit }}</button></form>',
        '#context' => [
          'action' => Url::fromRoute('xmt_trust_ui.verify')->toString(),
          'hash' => $hash,
          'placeholder' => $this->t('Provenance hash, e.g. sha256 or dx:...'),
          'submit' => $this->t('Verify'),
        ],
      ],
    ];

    if ($hash === '') {
      return $build;
    }

    $node = $this->lookup($hash);
    if (!$node) {
      $build['result'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('No published article matches this provenance hash. The content is not certified by this platform.'),
        '#attributes' => ['class' => ['xmt-trust-verify__result', 'xmt-trust-verify__result--miss']],
      ];
      return $build;
    }

    $level = $node->hasField('field_trust_level') ? ($node->get('field_trust_level')->value ?? 'l0_aggregate') : 'l0_aggregate';
    $publisher = NULL;
    if ($node->hasField('field_publisher') && !$node->get('field_publisher')->isEmpty()) {
      $publisher = $node->get('field_publisher')->entity;
    }

    $rows = [
      [$this->t('Title'), [
        'data' => [
          '#type' => 'link',
          '#title' => $node->label(),
          '#url' => $node->toUrl(),
        ],
      ],
      ],
      [$this->t('Trust level'), [
        'data' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => xmt_trust_badge_label($level),
          '#attributes' => ['class' => ['xmt-trust-badge', xmt_trust_badge_class($level)]],
        ],
      ],
      ],
      [$this->t('Publisher'), $publisher ? $publisher->label() : $this->t('—')],
      [
        $this->t('Published'),
        \Drupal::service('date.formatter')->format($node->getCreatedTime(), 'short'),
      ],
      [
        $this->t('Last updated'),
        \Drupal::service('date.formatter')->format($node->getChangedTime(), 'short'),
      ],
    ];

    $build['result'] = [
      'status' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Verified: this hash matches a published article on this platform.'),
        '#attributes' => ['class' => ['xmt-trust-verify__result', 'xmt-trust-verify__result--hit']],
      ],
      'details' => [
        '#type' => 'table',
        '#rows' => $rows,
        '#attributes' => ['class' => ['xmt-trust-verify__details']],
      ],
      '#attached' => ['library' => ['xmt_trust_ui/trust_feed']],
    ];

    return $build;
  }

  /**
   * Finds a published article by exact provenance hash.
   */
  protected function lookup(string $hash): ?NodeInterface {
    $storage = $this->entityTypeManager()->getStorage('node');
    $nids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', 1)
      ->condition('field_provenance_hash', $hash)
      ->range(0, 1)
      ->execute();
    if (!$nids) {
      return NULL;
    }
    $node = $storage->load(reset($nids));
    return $node instanceof NodeInterface ? $node : NULL;
  }

}
