<?php

namespace Drupal\xmt_trust_ui\Commands;

use Drupal\xmt_trust_ui\Service\ProvenanceAuditService;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the XMT provenance audit export.
 */
class ProvenanceAuditCommands extends DrushCommands {

  public function __construct(
    protected readonly ProvenanceAuditService $provenanceAudit,
  ) {
    parent::__construct();
  }

  /**
   * Exports trusted-article provenance metadata as CSV.
   *
   * @param array $options
   *   Command options.
   *
   * @command xmt:provenance-export
   * @option limit Maximum number of articles to export (0 = all).
   * @option file Destination file path; defaults to stdout.
   * @usage drush xmt:provenance-export --file=/tmp/audit.csv
   * @usage drush xmt:provenance-export --limit=500 > audit.csv
   */
  public function export(array $options = [
    'limit' => 0,
    'file' => '',
  ]): void {
    $limit = (int) $options['limit'];
    $nodes = $this->provenanceAudit->loadArticles($limit > 0 ? $limit : NULL);

    $destination = $options['file'] !== '' ? $options['file'] : 'php://output';
    $handle = fopen($destination, 'w');
    if ($handle === FALSE) {
      throw new \RuntimeException("Unable to open '$destination' for writing.");
    }
    $this->provenanceAudit->writeCsv($handle, $nodes);
    fclose($handle);

    if ($destination !== 'php://output') {
      $this->logger()->success(sprintf('Exported %d article(s) to %s.', count($nodes), $destination));
    }
  }

}
