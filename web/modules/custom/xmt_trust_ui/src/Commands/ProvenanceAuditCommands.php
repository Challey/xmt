<?php

namespace Drupal\xmt_trust_ui\Commands;

use Drupal\xmt_trust_ui\Service\ProvenanceAuditService;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for provenance audit export.
 */
class ProvenanceAuditCommands extends DrushCommands {

  public function __construct(
    protected ProvenanceAuditService $auditService,
  ) {
    parent::__construct();
  }

  /**
   * Export provenance audit records to CSV or JSON.
   *
   * @param array $options
   *   Command options.
   *
   * @command xmt:provenance-export
   * @option limit Maximum number of articles to export (default 500).
   * @option output Output file path (default stdout).
   * @option format Export format: csv or json (default csv).
   * @option trust-level Filter by trust level code (l0_aggregate, l1_official, l2_enterprise).
   * @option publisher Filter by publisher entity ID.
   * @usage drush xmt:provenance-export --limit=100 --output=/tmp/audit.csv
   * @usage drush xmt:provenance-export --format=json --trust-level=l2_enterprise
   */
  public function export(array $options = [
    'limit' => 500,
    'output' => '',
    'format' => 'csv',
    'trust-level' => '',
    'publisher' => '',
  ]): void {
    $limit = max(1, (int) $options['limit']);
    $format = strtolower((string) $options['format']);
    if (!in_array($format, ['csv', 'json'], TRUE)) {
      throw new \InvalidArgumentException('Format must be csv or json.');
    }

    $filters = [
      'trust_level' => (string) $options['trust-level'],
      'publisher_id' => (string) $options['publisher'],
    ];
    $nodes = $this->auditService->loadArticles($limit, 0, $filters);
    $output = (string) $options['output'];

    if ($format === 'json') {
      $payload = json_encode(
        $this->auditService->buildJsonPayload($nodes),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
      );
      if ($payload === FALSE) {
        throw new \RuntimeException('Failed to encode JSON.');
      }
      $payload .= "\n";

      if ($output !== '') {
        if (file_put_contents($output, $payload) === FALSE) {
          throw new \RuntimeException("Cannot write to $output");
        }
        $this->logger()->success(dt('Exported @count records to @file.', [
          '@count' => count($nodes),
          '@file' => $output,
        ]));
        return;
      }

      $this->output()->write($payload);
      return;
    }

    if ($output !== '') {
      $handle = fopen($output, 'w');
      if ($handle === FALSE) {
        throw new \RuntimeException("Cannot write to $output");
      }
      $this->auditService->writeCsv($handle, $nodes);
      fclose($handle);
      $this->logger()->success(dt('Exported @count records to @file.', [
        '@count' => count($nodes),
        '@file' => $output,
      ]));
      return;
    }

    $handle = fopen('php://output', 'w');
    if ($handle === FALSE) {
      throw new \RuntimeException('Cannot open stdout.');
    }
    $this->auditService->writeCsv($handle, $nodes);
    fclose($handle);
  }

}
