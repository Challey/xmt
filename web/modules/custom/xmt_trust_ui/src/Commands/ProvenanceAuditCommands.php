<?php

namespace Drupal\xmt_trust_ui\Commands;

use Drupal\xmt_trust_ui\Service\ProvenanceAuditExporter;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for provenance audit export.
 */
class ProvenanceAuditCommands extends DrushCommands {

  public function __construct(
    protected ProvenanceAuditExporter $exporter,
  ) {
    parent::__construct();
  }

  /**
   * Export provenance audit records to CSV.
   *
   * @param array $options
   *   Command options.
   *
   * @command xmt:provenance-export
   * @option limit Maximum rows (default 500, max 5000).
   * @option trust-level Filter by trust level code (l0_aggregate, l1_official, l2_enterprise).
   * @option output Write CSV to this file path (default stdout).
   * @usage drush xmt:provenance-export --limit=200 --output=/tmp/xmt-provenance.csv
   */
  public function export(array $options = [
    'limit' => 500,
    'trust-level' => '',
    'output' => '',
  ]): void {
    $limit = (int) $options['limit'];
    $trust_level = $options['trust-level'] !== '' ? $options['trust-level'] : NULL;
    $records = $this->exporter->records($limit, $trust_level);

    $handle = $options['output'] !== ''
      ? fopen($options['output'], 'wb')
      : STDOUT;
    if ($handle === FALSE) {
      throw new \RuntimeException('Cannot open output: ' . $options['output']);
    }

    $this->exporter->writeCsv($handle, $records);

    if ($options['output'] !== '') {
      fclose($handle);
      $this->logger()->success(dt('Exported @count rows to @path.', [
        '@count' => count($records),
        '@path' => $options['output'],
      ]));
    }
    else {
      $this->logger()->success(dt('Exported @count rows.', ['@count' => count($records)]));
    }
  }

}
