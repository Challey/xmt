<?php

namespace Drupal\xmt_syndicate\Commands;

use Drupal\xmt_syndicate\Service\SyndicateService;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for XMT agent publishing.
 */
class XmtCommands extends DrushCommands {

  public function __construct(
    protected SyndicateService $syndicate,
  ) {
    parent::__construct();
  }

  /**
   * Import an article from a JSON file payload.
   *
   * @command xmt:import-article
   * @param string $file Path to JSON payload.
   * @usage xmt:import-article /tmp/article.json
   */
  public function importArticle(string $file): void {
    if (!is_readable($file)) {
      throw new \InvalidArgumentException("Cannot read $file");
    }
    $data = json_decode(file_get_contents($file), TRUE, 512, JSON_THROW_ON_ERROR);
    $nid = $this->syndicate->importArticle($data);
    $this->logger()->success("Imported/updated node $nid");
    $this->output()->writeln((string) $nid);
  }

}
