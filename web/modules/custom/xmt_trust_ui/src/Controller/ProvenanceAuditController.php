<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\xmt_trust_ui\Service\ProvenanceAuditService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin listing and export of articles with provenance metadata.
 */
class ProvenanceAuditController extends ControllerBase {

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
   * Lists recent trusted articles for audit.
   */
  public function list(): array {
    $nodes = $this->auditService->loadArticles(50);

    $header = [
      $this->t('Title'),
      $this->t('Trust level'),
      $this->t('Publisher'),
      $this->t('Provenance hash'),
      $this->t('Source URL'),
      $this->t('Updated'),
    ];

    $rows = [];
    foreach ($nodes as $node) {
      $rows[] = $this->buildTableRow($node);
    }

    return [
      'actions' => [
        '#type' => 'actions',
        'csv' => [
          '#type' => 'link',
          '#title' => $this->t('Export CSV'),
          '#url' => Url::fromRoute('xmt_trust_ui.provenance_audit_csv'),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
        'json' => [
          '#type' => 'link',
          '#title' => $this->t('Export JSON'),
          '#url' => Url::fromRoute('xmt_trust_ui.provenance_audit_json'),
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
   * Streams a CSV download of provenance audit records.
   */
  public function exportCsv(): StreamedResponse {
    $nodes = $this->auditService->loadArticles(500);
    $filename = 'xmt-provenance-audit-' . gmdate('Y-m-d') . '.csv';

    $response = new StreamedResponse(function () use ($nodes): void {
      $handle = fopen('php://output', 'w');
      if ($handle === FALSE) {
        return;
      }
      $this->auditService->writeCsv($handle, $nodes);
      fclose($handle);
    });

    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
    $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $filename
    ));

    return $response;
  }

  /**
   * Returns a JSON download of provenance audit records.
   */
  public function exportJson(): JsonResponse {
    $nodes = $this->auditService->loadArticles(500);
    $filename = 'xmt-provenance-audit-' . gmdate('Y-m-d') . '.json';

    $response = new JsonResponse($this->auditService->buildJsonPayload($nodes));
    $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $filename
    ));

    return $response;
  }

  /**
   * Builds a table row for one article.
   */
  protected function buildTableRow(NodeInterface $node): array {
    $record = $this->auditService->buildRecord($node);
    $title = Link::fromTextAndUrl($node->label(), $node->toUrl())->toString();

    $source = '—';
    if ($record['source_url'] !== '') {
      $source = Link::fromTextAndUrl($record['source_url'], Url::fromUri($record['source_url']))->toString();
    }

    return [
      $title,
      $record['trust_label'] !== '' ? $record['trust_label'] : $this->t('—'),
      $record['publisher'] !== '' ? $record['publisher'] : $this->t('—'),
      $record['provenance_hash'] !== '' ? $record['provenance_hash'] : '—',
      ['data' => ['#markup' => $source]],
      $record['changed'],
    ];
  }

}
