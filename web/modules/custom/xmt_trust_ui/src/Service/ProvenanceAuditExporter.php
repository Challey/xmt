<?php

namespace Drupal\xmt_trust_ui\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Collects provenance audit rows for admin listing and CSV export.
 */
class ProvenanceAuditExporter {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * CSV column headers (machine key => human label).
   *
   * @return array<string, string>
   */
  public function headers(): array {
    return [
      'nid' => (string) t('Node ID'),
      'title' => (string) t('Title'),
      'trust_level' => (string) t('Trust level'),
      'publisher' => (string) t('Publisher'),
      'provenance_hash' => (string) t('Provenance hash'),
      'source_url' => (string) t('Source URL'),
      'source_name' => (string) t('Source name'),
      'domain' => (string) t('Domain'),
      'created' => (string) t('Created'),
      'changed' => (string) t('Updated'),
    ];
  }

  /**
   * Loads recent article audit records.
   *
   * @return array<int, array<string, string>>
   *   Rows keyed by column machine name.
   */
  public function records(int $limit = 500, ?string $trust_level = NULL): array {
    $limit = max(1, min($limit, 5000));
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->sort('changed', 'DESC')
      ->range(0, $limit);

    if ($trust_level !== NULL && $trust_level !== '') {
      $query->condition('field_trust_level', $trust_level);
    }

    $nids = $query->execute();
    if (!$nids) {
      return [];
    }

    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $storage->loadMultiple($nids);
    $rows = [];
    foreach ($nodes as $node) {
      $rows[] = $this->recordFromNode($node);
    }
    return $rows;
  }

  /**
   * Builds one audit record from a node.
   *
   * @return array<string, string>
   */
  public function recordFromNode(NodeInterface $node): array {
    $level = '';
    if ($node->hasField('field_trust_level') && !$node->get('field_trust_level')->isEmpty()) {
      $code = $node->get('field_trust_level')->value;
      $level = $code ? xmt_trust_badge_label($code) . ' (' . $code . ')' : '';
    }

    $publisher = '';
    if ($node->hasField('field_publisher') && !$node->get('field_publisher')->isEmpty()) {
      $entity = $node->get('field_publisher')->entity;
      $publisher = $entity ? $entity->label() : (string) $node->get('field_publisher')->target_id;
    }

    $provenance = '';
    if ($node->hasField('field_provenance_hash') && !$node->get('field_provenance_hash')->isEmpty()) {
      $provenance = (string) $node->get('field_provenance_hash')->value;
    }

    $source_url = '';
    if ($node->hasField('field_source_url') && !$node->get('field_source_url')->isEmpty()) {
      $source_url = $node->get('field_source_url')->uri ?? $node->get('field_source_url')->value ?? '';
    }

    $source_name = '';
    if ($node->hasField('field_source_name') && !$node->get('field_source_name')->isEmpty()) {
      $source_name = (string) $node->get('field_source_name')->value;
    }

    $domain = '';
    if ($node->hasField('field_domain') && !$node->get('field_domain')->isEmpty()) {
      $domain = (string) $node->get('field_domain')->value;
    }

    $formatter = \Drupal::service('date.formatter');
    return [
      'nid' => (string) $node->id(),
      'title' => $node->label(),
      'trust_level' => $level,
      'publisher' => $publisher,
      'provenance_hash' => $provenance,
      'source_url' => $source_url,
      'source_name' => $source_name,
      'domain' => $domain,
      'created' => $formatter->format($node->getCreatedTime(), 'custom', 'Y-m-d H:i:s'),
      'changed' => $formatter->format($node->getChangedTime(), 'custom', 'Y-m-d H:i:s'),
    ];
  }

  /**
   * Writes audit records as CSV to a stream resource.
   *
   * @param resource $handle
   *   Writable stream (e.g. php://output).
   * @param array<int, array<string, string>> $records
   *   Rows from ::records().
   */
  public function writeCsv($handle, array $records): void {
    $keys = array_keys($this->headers());
    fputcsv($handle, array_values($this->headers()));
    foreach ($records as $record) {
      $row = [];
      foreach ($keys as $key) {
        $row[] = $record[$key] ?? '';
      }
      fputcsv($handle, $row);
    }
  }

}
