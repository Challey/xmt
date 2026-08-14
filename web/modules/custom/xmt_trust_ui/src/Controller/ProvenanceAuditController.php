<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\xmt_trust_ui\Service\ProvenanceAuditExporter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin listing and CSV export of articles with provenance metadata.
 */
class ProvenanceAuditController extends ControllerBase {

  public function __construct(
    protected ProvenanceAuditExporter $exporter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('xmt_trust_ui.provenance_exporter'),
    );
  }

  /**
   * Lists recent trusted articles for audit.
   */
  public function list(Request $request): array {
    $trust_level = $request->query->get('trust_level');
    $records = $this->exporter->records(50, $trust_level ?: NULL);

    $header = [
      $this->t('Title'),
      $this->t('Trust level'),
      $this->t('Publisher'),
      $this->t('Provenance hash'),
      $this->t('Source URL'),
      $this->t('Updated'),
    ];

    $rows = [];
    $storage = $this->entityTypeManager()->getStorage('node');
    foreach ($records as $record) {
      $node = $storage->load($record['nid']);
      if ($node instanceof NodeInterface) {
        $rows[] = $this->buildTableRow($node, $record);
      }
    }

    $export_url = Url::fromRoute('xmt_trust_ui.provenance_audit_export', [], [
      'query' => array_filter(['trust_level' => $trust_level]),
    ]);

    return [
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['xmt-provenance-audit-actions']],
        'export' => [
          '#type' => 'link',
          '#title' => $this->t('Export CSV'),
          '#url' => $export_url,
          '#attributes' => ['class' => ['button', 'button--primary']],
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
  public function exportCsv(Request $request): StreamedResponse {
    $trust_level = $request->query->get('trust_level');
    $limit = min(5000, max(1, (int) $request->query->get('limit', 500)));
    $records = $this->exporter->records($limit, $trust_level ?: NULL);

    $filename = 'xmt-provenance-audit-' . date('Ymd-His') . '.csv';
    $response = new StreamedResponse(function () use ($records) {
      $handle = fopen('php://output', 'wb');
      if ($handle !== FALSE) {
        $this->exporter->writeCsv($handle, $records);
        fclose($handle);
      }
    });
    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    return $response;
  }

  /**
   * Builds a table row for one article.
   *
   * @param array<string, string> $record
   *   Audit record from the exporter.
   */
  protected function buildTableRow(NodeInterface $node, array $record): array {
    $title = Link::fromTextAndUrl($node->label(), $node->toUrl())->toString();

    $level = $record['trust_level'] !== '' ? $record['trust_level'] : $this->t('—');
    $publisher = $record['publisher'] !== '' ? $record['publisher'] : $this->t('—');
    $provenance = $record['provenance_hash'] !== '' ? $record['provenance_hash'] : '—';

    $source = '—';
    if ($record['source_url'] !== '') {
      $source = Link::fromTextAndUrl($record['source_url'], Url::fromUri($record['source_url']))->toString();
    }

    $updated = Drupal::service('date.formatter')->format($node->getChangedTime(), 'short');

    return [$title, $level, $publisher, $provenance, ['data' => ['#markup' => $source]], $updated];
  }

}
