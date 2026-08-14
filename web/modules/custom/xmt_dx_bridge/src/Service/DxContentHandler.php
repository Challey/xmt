<?php

namespace Drupal\xmt_dx_bridge\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\Entity\Node;

/**
 * Handles verified DrupalX trusted content pushes.
 */
class DxContentHandler {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected DxClaimHandler $claimHandler,
    protected DxNonceGuard $nonceGuard,
  ) {}

  /**
   * Process a trusted-content payload and return node ID.
   */
  public function processContent(array $data): int {
    $required = ['title', 'body', 'dx_developer_id'];
    foreach ($required as $key) {
      if (!isset($data[$key]) || $data[$key] === '') {
        throw new \InvalidArgumentException("Missing content field: $key");
      }
    }
    if (!empty($data['exp']) && time() > (int) $data['exp']) {
      throw new \InvalidArgumentException('Content payload expired.');
    }
    $this->nonceGuard->consume(
      (string) $data['dx_developer_id'],
      isset($data['nonce']) ? (string) $data['nonce'] : NULL,
      isset($data['exp']) ? (int) $data['exp'] : NULL,
    );

    $publisher = $this->loadApprovedPublisher((string) $data['dx_developer_id']);

    $node = $this->findExistingNode($data);
    if (!$node) {
      $node = Node::create([
        'type' => 'article',
        'uid' => 1,
      ]);
    }

    $format = !empty($data['format']) ? (string) $data['format'] : 'full_html';
    $node->setTitle((string) $data['title']);
    $node->set('body', [
      'value' => (string) $data['body'],
      'format' => $format,
    ]);
    $node->set('status', 1);
    $node->set('field_trust_level', 'l2_enterprise');
    $node->set('field_publisher', $publisher->id());

    if (!empty($data['source_url'])) {
      $node->set('field_source_url', ['uri' => (string) $data['source_url']]);
    }
    if (!empty($data['source_name'])) {
      $node->set('field_source_name', (string) $data['source_name']);
    }
    if (!empty($data['domain'])) {
      $node->set('field_domain', (string) $data['domain']);
    }

    if (!empty($data['external_id'])) {
      $node->set('field_provenance_hash', 'dx:' . (string) $data['external_id']);
    }

    $node->xmt_skip_syndicate = TRUE;
    $node->save();

    $this->loggerFactory->get('xmt_dx_bridge')->notice('Processed DX content @nid for developer @id', [
      '@nid' => $node->id(),
      '@id' => $data['dx_developer_id'],
    ]);

    return (int) $node->id();
  }

  /**
   * Load an approved publisher by DrupalX developer ID.
   */
  protected function loadApprovedPublisher(string $dxDeveloperId) {
    $storage = $this->entityTypeManager->getStorage('xmt_publisher');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('dx_developer_id', $dxDeveloperId)
      ->condition('status', 'approved')
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      throw new \InvalidArgumentException("No approved publisher for dx_developer_id: $dxDeveloperId");
    }
    return $storage->load(reset($ids));
  }

  /**
   * Find existing article for idempotent create/update.
   */
  protected function findExistingNode(array $data): ?Node {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'article');

    if (!empty($data['external_id'])) {
      $query->condition('field_provenance_hash', 'dx:' . (string) $data['external_id']);
    }
    elseif (!empty($data['source_url'])) {
      $query->condition('field_source_url.uri', (string) $data['source_url']);
    }
    else {
      return NULL;
    }

    $ids = $query->range(0, 1)->execute();
    if (!$ids) {
      return NULL;
    }
    $node = $storage->load(reset($ids));
    return $node instanceof Node ? $node : NULL;
  }

}
