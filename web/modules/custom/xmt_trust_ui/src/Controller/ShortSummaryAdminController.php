<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\xmt_trust_ui\Service\ShortSummaryGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Admin actions for short-news summaries.
 */
class ShortSummaryAdminController extends ControllerBase {

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
   * Regenerate extractive/AI summary cache for one article.
   */
  public function regenerate(NodeInterface $node): RedirectResponse {
    if ($node->bundle() !== 'article') {
      $this->messenger()->addError($this->t('Not an article.'));
    }
    else {
      $payload = $this->summarizer->regenerate($node);
      $this->messenger()->addStatus($this->t('Summary regenerated (@engine): @brief', [
        '@engine' => $payload['engine'],
        '@brief' => mb_substr($payload['brief'], 0, 80),
      ]));
    }
    return new RedirectResponse(Url::fromRoute('xmt_trust_ui.provenance_audit')->toString());
  }

}
