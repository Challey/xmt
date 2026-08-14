<?php

namespace Drupal\xmt_trust\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\xmt_trust\Provenance;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for auditing XMT provenance records.
 */
class TrustCommands extends DrushCommands {

  /**
   * Number of articles loaded per batch.
   */
  protected const BATCH = 100;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Verify stored provenance hashes against current article field values.
   *
   * @param array $options
   *   Command options.
   *
   * @command xmt:provenance-verify
   * @option limit Maximum number of articles to check; 0 checks all.
   * @option status Only report this verification status (verified, mismatch,
   *   bridge, missing, or all).
   * @usage drush --uri=xmt.pub xmt:provenance-verify
   * @usage drush --uri=xmt.pub xmt:provenance-verify --status=mismatch
   */
  public function provenanceVerify(
    array $options = [
      'limit' => 0,
      'status' => 'all',
    ],
  ): int {
    $limit = (int) $options['limit'];
    $filter = (string) $options['status'];
    $counts = [
      Provenance::STATUS_VERIFIED => 0,
      Provenance::STATUS_MISMATCH => 0,
      Provenance::STATUS_BRIDGE => 0,
      Provenance::STATUS_MISSING => 0,
    ];
    $checked = 0;

    foreach ($this->loadArticles($limit) as $node) {
      $result = Provenance::verify($node);
      $counts[$result['status']]++;
      $checked++;

      if ($filter === 'all' ? $result['status'] === Provenance::STATUS_MISMATCH : $result['status'] === $filter) {
        $this->output()->writeln(sprintf(
          '%s  nid=%d  %s',
          str_pad($result['status'], 9),
          $node->id(),
          $node->label()
        ));
      }
    }

    $this->output()->writeln(sprintf(
      'Checked %d articles: %d verified, %d mismatch, %d bridge, %d missing.',
      $checked,
      $counts[Provenance::STATUS_VERIFIED],
      $counts[Provenance::STATUS_MISMATCH],
      $counts[Provenance::STATUS_BRIDGE],
      $counts[Provenance::STATUS_MISSING]
    ));

    if ($counts[Provenance::STATUS_MISMATCH] > 0) {
      $this->logger()->warning('Provenance mismatches found; review /admin/xmt/provenance.');
      return self::EXIT_FAILURE;
    }

    return self::EXIT_SUCCESS;
  }

  /**
   * Yields articles oldest first, in batches.
   *
   * @param int $limit
   *   Maximum number of articles to yield; 0 yields all.
   *
   * @return \Generator|\Drupal\node\NodeInterface[]
   *   Article nodes.
   */
  protected function loadArticles(int $limit): \Generator {
    $storage = $this->entityTypeManager->getStorage('node');
    $last_nid = 0;
    $yielded = 0;

    while (TRUE) {
      $batch = $limit > 0 ? min(static::BATCH, $limit - $yielded) : static::BATCH;
      if ($batch < 1) {
        break;
      }

      $nids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'article')
        ->condition('nid', $last_nid, '>')
        ->sort('nid', 'ASC')
        ->range(0, $batch)
        ->execute();

      if (!$nids) {
        break;
      }

      foreach ($storage->loadMultiple($nids) as $node) {
        $last_nid = max($last_nid, (int) $node->id());
        $yielded++;
        yield $node;
      }

      if (count($nids) < $batch) {
        break;
      }
      $storage->resetCache($nids);
    }
  }

}
