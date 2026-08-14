<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin listing of recent articles with provenance metadata.
 */
class ProvenanceAuditController extends ControllerBase {

  /**
   * Lists recent trusted articles for audit.
   */
  public function list(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $nids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'article')
      ->sort('changed', 'DESC')
      ->range(0, 50)
      ->execute();

    $header = [
      $this->t('Title'),
      $this->t('Trust level'),
      $this->t('Publisher'),
      $this->t('Provenance hash'),
      $this->t('Source URL'),
      $this->t('Updated'),
    ];

    $rows = [];
    if ($nids) {
      /** @var \Drupal\node\NodeInterface[] $nodes */
      $nodes = $storage->loadMultiple($nids);
      foreach ($nodes as $node) {
        $rows[] = $this->buildRow($node);
      }
    }

    return [
      'export' => [
        '#type' => 'link',
        '#title' => $this->t('Download CSV'),
        '#url' => Url::fromRoute('xmt_trust_ui.provenance_audit_export'),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
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
   * Downloads all article provenance records as a CSV audit export.
   */
  public function export(): StreamedResponse {
    $response = new StreamedResponse(function (): void {
      $output = fopen('php://output', 'w');
      fputcsv($output, [
        'Node ID',
        'Title',
        'Trust level',
        'Publisher ID',
        'Publisher',
        'Provenance hash',
        'Source URL',
        'Created (UTC)',
        'Updated (UTC)',
      ]);

      $storage = $this->entityTypeManager()->getStorage('node');
      $nids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'article')
        ->sort('nid', 'ASC')
        ->execute();

      foreach (array_chunk($nids, 100) as $chunk) {
        /** @var \Drupal\node\NodeInterface[] $nodes */
        $nodes = $storage->loadMultiple($chunk);
        foreach ($chunk as $nid) {
          if (isset($nodes[$nid])) {
            fputcsv($output, $this->buildExportRow($nodes[$nid]));
          }
        }
      }
      fclose($output);
    });
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="xmt-provenance-audit.csv"');
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->headers->set('X-Content-Type-Options', 'nosniff');

    return $response;
  }

  /**
   * Builds a table row for one article.
   */
  protected function buildRow(NodeInterface $node): array {
    $title = Link::fromTextAndUrl($node->label(), $node->toUrl())->toString();

    $level = $node->hasField('field_trust_level') && !$node->get('field_trust_level')->isEmpty()
      ? xmt_trust_badge_label($node->get('field_trust_level')->value)
      : $this->t('—');

    $publisher = $this->t('—');
    if ($node->hasField('field_publisher') && !$node->get('field_publisher')->isEmpty()) {
      $entity = $node->get('field_publisher')->entity;
      $publisher = $entity ? $entity->label() : $node->get('field_publisher')->target_id;
    }

    $provenance = $node->hasField('field_provenance_hash') && !$node->get('field_provenance_hash')->isEmpty()
      ? $node->get('field_provenance_hash')->value
      : '—';

    $source = '—';
    if ($node->hasField('field_source_url') && !$node->get('field_source_url')->isEmpty()) {
      $uri = $node->get('field_source_url')->uri ?? $node->get('field_source_url')->value ?? '';
      if ($uri !== '') {
        $source = Link::fromTextAndUrl($uri, Url::fromUri($uri))->toString();
      }
    }

    $updated = \Drupal::service('date.formatter')->format($node->getChangedTime(), 'short');

    return [$title, $level, $publisher, $provenance, ['data' => ['#markup' => $source]], $updated];
  }

  /**
   * Builds one spreadsheet-safe row for the CSV audit export.
   */
  protected function buildExportRow(NodeInterface $node): array {
    $publisher_id = '';
    $publisher = '';
    if ($node->hasField('field_publisher') && !$node->get('field_publisher')->isEmpty()) {
      $publisher_id = (string) $node->get('field_publisher')->target_id;
      $entity = $node->get('field_publisher')->entity;
      $publisher = $entity ? $entity->label() : '';
    }

    $source = '';
    if ($node->hasField('field_source_url') && !$node->get('field_source_url')->isEmpty()) {
      $source = $node->get('field_source_url')->uri ?? $node->get('field_source_url')->value ?? '';
    }

    $provenance = $node->hasField('field_provenance_hash') && !$node->get('field_provenance_hash')->isEmpty()
      ? $node->get('field_provenance_hash')->value
      : '';
    $level = $node->hasField('field_trust_level') && !$node->get('field_trust_level')->isEmpty()
      ? $node->get('field_trust_level')->value
      : '';

    return array_map(
      [$this, 'escapeSpreadsheetValue'],
      [
        (string) $node->id(),
        $node->label(),
        $level,
        $publisher_id,
        $publisher,
        $provenance,
        $source,
        gmdate('c', $node->getCreatedTime()),
        gmdate('c', $node->getChangedTime()),
      ],
    );
  }

  /**
   * Prevents spreadsheet applications from interpreting a value as a formula.
   */
  protected function escapeSpreadsheetValue(string $value): string {
    return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
  }

}
