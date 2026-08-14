<?php

namespace Drupal\xmt_trust_ui\Service;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\node\NodeInterface;

/**
 * Queries articles and builds provenance audit records for export.
 */
class ProvenanceAuditService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Loads article nodes for audit, newest first.
   *
   * @param int $limit
   *   Maximum number of articles to load.
   * @param int $offset
   *   Number of matching articles to skip.
   * @param array{trust_level?: string, publisher_id?: int|string} $filters
   *   Optional filters.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Article nodes keyed by nid.
   */
  public function loadArticles(int $limit = 50, int $offset = 0, array $filters = []): array {
    $nids = $this->buildQuery($filters)
      ->range($offset, $limit)
      ->execute();

    if ($nids === []) {
      return [];
    }

    return $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
  }

  /**
   * Counts articles matching audit filters.
   *
   * @param array{trust_level?: string, publisher_id?: int|string} $filters
   *   Optional filters.
   */
  public function countArticles(array $filters = []): int {
    return (int) $this->buildQuery($filters)->count()->execute();
  }

  /**
   * Builds a filtered article query sorted by changed DESC.
   *
   * @param array{trust_level?: string, publisher_id?: int|string} $filters
   *   Optional filters.
   */
  protected function buildQuery(array $filters = []): QueryInterface {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->sort('changed', 'DESC');

    $level = trim((string) ($filters['trust_level'] ?? ''));
    if ($level !== '' && array_key_exists($level, xmt_trust_level_allowed_values())) {
      $query->condition('field_trust_level', $level);
    }

    $publisher_id = $filters['publisher_id'] ?? '';
    if ($publisher_id !== '' && $publisher_id !== NULL && (int) $publisher_id > 0) {
      $query->condition('field_publisher', (int) $publisher_id);
    }

    return $query;
  }

  /**
   * Approved publishers for filter dropdowns, keyed by id.
   *
   * @return array<int|string, string>
   *   Publisher labels keyed by publisher ID.
   */
  public function publisherOptions(): array {
    $storage = $this->entityTypeManager->getStorage('xmt_publisher');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 'approved')
      ->sort('name', 'ASC')
      ->execute();
    if ($ids === []) {
      return [];
    }
    $options = [];
    foreach ($storage->loadMultiple($ids) as $publisher) {
      $options[$publisher->id()] = $publisher->label();
    }
    return $options;
  }

  /**
   * Builds a flat audit record for one article.
   *
   * @return array<string, string|int>
   *   Keys: nid, title, trust_level, trust_label, publisher, provenance_hash,
   *   source_url, created, changed, changed_iso.
   */
  public function buildRecord(NodeInterface $node): array {
    $level = $node->hasField('field_trust_level') && !$node->get('field_trust_level')->isEmpty()
      ? (string) $node->get('field_trust_level')->value
      : '';

    $publisher = '';
    if ($node->hasField('field_publisher') && !$node->get('field_publisher')->isEmpty()) {
      $entity = $node->get('field_publisher')->entity;
      $publisher = $entity ? $entity->label() : (string) $node->get('field_publisher')->target_id;
    }

    $provenance = '';
    if ($node->hasField('field_provenance_hash') && !$node->get('field_provenance_hash')->isEmpty()) {
      $provenance = (string) $node->get('field_provenance_hash')->value;
    }

    $source = '';
    if ($node->hasField('field_source_url') && !$node->get('field_source_url')->isEmpty()) {
      $source = (string) ($node->get('field_source_url')->uri ?? $node->get('field_source_url')->value ?? '');
    }

    return [
      'nid' => (int) $node->id(),
      'title' => $node->label(),
      'trust_level' => $level,
      'trust_label' => $level !== '' ? xmt_trust_badge_label($level) : '',
      'publisher' => $publisher,
      'provenance_hash' => $provenance,
      'source_url' => $source,
      'created' => $this->dateFormatter->format($node->getCreatedTime(), 'short'),
      'changed' => $this->dateFormatter->format($node->getChangedTime(), 'short'),
      'changed_iso' => gmdate(DATE_ATOM, $node->getChangedTime()),
    ];
  }

  /**
   * CSV column headers for export.
   *
   * @return string[]
   *   Machine-readable CSV column names.
   */
  public function csvHeaders(): array {
    return [
      'nid',
      'title',
      'trust_level',
      'trust_label',
      'publisher',
      'provenance_hash',
      'source_url',
      'created',
      'changed',
    ];
  }

  /**
   * Writes audit records as CSV to a stream.
   *
   * @param resource $handle
   *   Writable stream (e.g. php://output).
   * @param \Drupal\node\NodeInterface[] $nodes
   *   Article nodes to export.
   */
  public function writeCsv($handle, array $nodes): void {
    // Help spreadsheet applications recognize UTF-8.
    fwrite($handle, "\xEF\xBB\xBF");
    fputcsv($handle, $this->csvHeaders(), ',', '"', '');
    foreach ($nodes as $node) {
      $record = $this->buildRecord($node);
      $row = [];
      foreach ($this->csvHeaders() as $key) {
        $row[] = $this->csvSafeValue($record[$key] ?? '');
      }
      fputcsv($handle, $row, ',', '"', '');
    }
  }

  /**
   * Builds a JSON-friendly payload for a set of articles.
   *
   * @param \Drupal\node\NodeInterface[] $nodes
   *   Article nodes to export.
   *
   * @return array{generated_at: string, count: int, items: list<array<string, mixed>>}
   *   Export metadata and article audit records.
   */
  public function buildJsonPayload(array $nodes): array {
    $items = [];
    foreach ($nodes as $node) {
      $record = $this->buildRecord($node);
      $items[] = [
        'id' => $record['nid'],
        'title' => $record['title'],
        'trust_level' => $record['trust_level'] !== '' ? $record['trust_level'] : NULL,
        'trust_label' => $record['trust_label'] !== '' ? $record['trust_label'] : NULL,
        'publisher' => $record['publisher'] !== '' ? $record['publisher'] : NULL,
        'provenance_hash' => $record['provenance_hash'] !== '' ? $record['provenance_hash'] : NULL,
        'source_url' => $record['source_url'] !== '' ? $record['source_url'] : NULL,
        'updated' => $record['changed_iso'],
      ];
    }

    return [
      'generated_at' => gmdate(DATE_ATOM),
      'count' => count($items),
      'items' => $items,
    ];
  }

  /**
   * Prevents spreadsheets from interpreting imported cells as formulas.
   */
  public function csvSafeValue(mixed $value): string {
    $value = (string) $value;
    if (
      preg_match('/^[\t\r\n]/', $value)
      || preg_match('/^[=+\-@]/', ltrim($value))
    ) {
      return "'" . $value;
    }
    return $value;
  }

}
