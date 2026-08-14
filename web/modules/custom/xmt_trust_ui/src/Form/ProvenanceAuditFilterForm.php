<?php

namespace Drupal\xmt_trust_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\xmt_trust_ui\Service\ProvenanceAuditService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter form for the provenance audit listing.
 */
class ProvenanceAuditFilterForm extends FormBase {

  public function __construct(
    protected ProvenanceAuditService $auditService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('xmt_trust_ui.provenance_audit'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'xmt_provenance_audit_filter';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $request = $this->getRequest();
    $levels = ['' => $this->t('- Any -')] + xmt_trust_level_allowed_values();
    $publishers = ['' => $this->t('- Any -')] + $this->auditService->publisherOptions();

    $form['#attributes'] = ['class' => ['xmt-provenance-audit-filters']];

    $form['trust_level'] = [
      '#type' => 'select',
      '#title' => $this->t('Trust level'),
      '#options' => $levels,
      '#default_value' => $request->query->get('trust_level', ''),
    ];
    $form['publisher_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Publisher'),
      '#options' => $publishers,
      '#default_value' => $request->query->get('publisher_id', ''),
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Filter'),
      ],
      'reset' => [
        '#type' => 'link',
        '#title' => $this->t('Reset'),
        '#url' => Url::fromRoute('xmt_trust_ui.provenance_audit'),
        '#attributes' => ['class' => ['button']],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $query = array_filter([
      'trust_level' => (string) $form_state->getValue('trust_level'),
      'publisher_id' => (string) $form_state->getValue('publisher_id'),
    ], static fn ($value) => $value !== '');
    $form_state->setRedirect('xmt_trust_ui.provenance_audit', [], ['query' => $query]);
  }

}
