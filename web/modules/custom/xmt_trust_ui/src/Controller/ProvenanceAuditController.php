<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\xmt_trust_ui\Service\ProvenanceAuditService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin listing of recent articles with provenance metadata.
 */
class ProvenanceAuditController extends ControllerBase {

  public function __construct(
    protected readonly ProvenanceAuditService $provenanceAudit,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xmt_trust_ui.provenance_audit'));
  }

  /**
   * Lists recent trusted articles for audit.
   */
  public function list(): array {
    $header = [
      $this->t('Title'),
      $this->t('Trust level'),
      $this->t('Publisher'),
      $this->t('Provenance hash'),
      $this->t('Source URL'),
      $this->t('Updated'),
    ];

    $rows = [];
    foreach ($this->provenanceAudit->loadArticles(50) as $node) {
      $rows[] = $this->provenanceAudit->buildRow($node);
    }

    return [
      'actions' => [
        '#type' => 'actions',
        'csv' => [
          '#type' => 'link',
          '#title' => $this->t('Export CSV'),
          '#url' => Url::fromRoute('xmt_trust_ui.provenance_audit_export_csv'),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
        'json' => [
          '#type' => 'link',
          '#title' => $this->t('Export JSON'),
          '#url' => Url::fromRoute('xmt_trust_ui.provenance_audit_export_json'),
          '#attributes' => ['class' => ['button']],
        ],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No articles found.'),
        '#attributes' => ['class' => ['xmt-provenance-audit']],
      ],
    ];
  }

  /**
   * Exports all accessible articles and their provenance metadata as CSV.
   */
  public function exportCsv(): StreamedResponse {
    $nodes = $this->provenanceAudit->loadArticles();
    $response = new StreamedResponse(function () use ($nodes): void {
      $handle = fopen('php://output', 'w');
      if ($handle === FALSE) {
        return;
      }
      $this->provenanceAudit->writeCsv($handle, $nodes);
      fclose($handle);
    });
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      'xmt-provenance-audit-' . gmdate('Y-m-d') . '.csv'
    ));

    return $response;
  }

  /**
   * Exports all accessible articles and their provenance metadata as JSON.
   */
  public function exportJson(): JsonResponse {
    $records = [];
    foreach ($this->provenanceAudit->loadArticles() as $node) {
      $records[] = $this->provenanceAudit->buildExportRecord($node);
    }

    $response = new JsonResponse([
      'generated_at' => gmdate(DATE_ATOM),
      'count' => count($records),
      'items' => $records,
    ]);
    $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      'xmt-provenance-audit-' . gmdate('Y-m-d') . '.json'
    ));

    return $response;
  }

}
