<?php

namespace Drupal\xmt_trust_ui\Service;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Loads and formats provenance/trust metadata for trusted articles.
 */
class ProvenanceAuditService {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Loads accessible articles in most-recently-updated order.
   *
   * @param int|null $limit
   *   The maximum number to load, or NULL to load all matching articles.
   *
   * @return \Drupal\node\NodeInterface[]
   *   The loaded articles, keyed by node ID.
   */
  public function loadArticles(?int $limit = NULL): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->sort('changed', 'DESC');
    if ($limit !== NULL) {
      $query->range(0, $limit);
    }
    $nids = $query->execute();

    return $nids ? $storage->loadMultiple($nids) : [];
  }

  /**
   * Builds a render array table row for one article.
   */
  public function buildRow(NodeInterface $node): array {
    $title = [
      '#type' => 'link',
      '#title' => $node->label(),
      '#url' => $node->toUrl(),
    ];

    $level = $node->hasField('field_trust_level') && !$node->get('field_trust_level')->isEmpty()
      ? xmt_trust_badge_label($node->get('field_trust_level')->value)
      : '—';

    $publisher = '—';
    if ($node->hasField('field_publisher') && !$node->get('field_publisher')->isEmpty()) {
      $entity = $node->get('field_publisher')->entity;
      $publisher = $entity ? $entity->label() : $node->get('field_publisher')->target_id;
    }

    $provenance = $node->hasField('field_provenance_hash') && !$node->get('field_provenance_hash')->isEmpty()
      ? $node->get('field_provenance_hash')->value
      : '—';

    $source = '—';
    $source_uri = $this->extractSourceUrl($node);
    if ($source_uri !== NULL) {
      $source = [
        '#type' => 'link',
        '#title' => $source_uri,
        '#url' => Url::fromUri($source_uri),
      ];
    }

    $updated = $this->dateFormatter->format($node->getChangedTime(), 'short');

    return [
      ['data' => $title],
      $level,
      $publisher,
      $provenance,
      ['data' => $source],
      $updated,
    ];
  }

  /**
   * Builds a machine-readable audit record for one article.
   */
  public function buildExportRecord(NodeInterface $node): array {
    $level = $node->hasField('field_trust_level') && !$node->get('field_trust_level')->isEmpty()
      ? $node->get('field_trust_level')->value
      : NULL;

    $publisher = NULL;
    if ($node->hasField('field_publisher') && !$node->get('field_publisher')->isEmpty()) {
      $entity = $node->get('field_publisher')->entity;
      $publisher = $entity ? $entity->label() : (string) $node->get('field_publisher')->target_id;
    }

    return [
      'id' => (int) $node->id(),
      'title' => $node->label(),
      'trust_level' => $level,
      'publisher' => $publisher,
      'provenance_hash' => $node->hasField('field_provenance_hash') && !$node->get('field_provenance_hash')->isEmpty()
        ? $node->get('field_provenance_hash')->value
        : NULL,
      'source_url' => $this->extractSourceUrl($node),
      'updated' => gmdate(DATE_ATOM, $node->getChangedTime()),
    ];
  }

  /**
   * Extracts the source URL for an article, if present.
   */
  protected function extractSourceUrl(NodeInterface $node): ?string {
    if (!$node->hasField('field_source_url') || $node->get('field_source_url')->isEmpty()) {
      return NULL;
    }
    $uri = $node->get('field_source_url')->uri ?? $node->get('field_source_url')->value ?? NULL;
    return $uri !== '' ? $uri : NULL;
  }

  /**
   * Writes CSV rows (including header) for the given articles to a handle.
   *
   * @param resource $handle
   *   An open, writable stream resource.
   * @param \Drupal\node\NodeInterface[] $nodes
   *   The articles to export.
   */
  public function writeCsv($handle, array $nodes): void {
    // Byte-order mark helps spreadsheet applications recognize UTF-8.
    fwrite($handle, "\xEF\xBB\xBF");
    fputcsv($handle, [
      'ID',
      'Title',
      'Trust level',
      'Publisher',
      'Provenance hash',
      'Source URL',
      'Updated',
    ], ',', '"', '');

    foreach ($nodes as $node) {
      fputcsv($handle, array_values($this->buildExportRecord($node)), ',', '"', '');
    }
  }

}
