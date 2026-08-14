<?php

namespace Drupal\xmt_dx_bridge\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Handles verified DrupalX publisher claims.
 */
class DxClaimHandler {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected DxNonceGuard $nonceGuard,
  ) {}

  /**
   * Returns the shared bridge secret.
   */
  public function getSecret(): string {
    $secret = \Drupal::service('settings')->get('xmt_dx_bridge_secret');
    if ($secret) {
      return (string) $secret;
    }
    $env = getenv('XMT_DX_BRIDGE_SECRET');
    return $env !== FALSE ? (string) $env : '';
  }

  /**
   * Verify HMAC signature for raw body.
   */
  public function verifySignature(string $body, ?string $signature): bool {
    $secret = $this->getSecret();
    if ($secret === '' || $signature === NULL || $signature === '') {
      return FALSE;
    }
    $expected = hash_hmac('sha256', $body, $secret);
    return hash_equals($expected, $signature);
  }

  /**
   * Process a claim payload and return publisher ID.
   */
  public function processClaim(array $claim): int {
    $required = ['publisher_name', 'dx_developer_id'];
    foreach ($required as $key) {
      if (empty($claim[$key])) {
        throw new \InvalidArgumentException("Missing claim field: $key");
      }
    }
    if (!empty($claim['exp']) && time() > (int) $claim['exp']) {
      throw new \InvalidArgumentException('Claim expired.');
    }
    $this->nonceGuard->assertFresh($claim);

    $storage = $this->entityTypeManager->getStorage('xmt_publisher');
    $existing = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('dx_developer_id', $claim['dx_developer_id'])
      ->range(0, 1)
      ->execute();

    if ($existing) {
      /** @var \Drupal\xmt_publisher\Entity\Publisher $publisher */
      $publisher = $storage->load(reset($existing));
    }
    else {
      $publisher = $storage->create([
        'type' => 'enterprise',
        'uid' => 1,
      ]);
    }

    $publisher->set('name', $claim['publisher_name']);
    $publisher->set('status', 'approved');
    $publisher->set('dx_developer_id', $claim['dx_developer_id']);
    if (!empty($claim['credit_code'])) {
      $publisher->set('credit_code', $claim['credit_code']);
    }
    if (!empty($claim['website'])) {
      $publisher->set('website', $claim['website']);
    }
    $publisher->save();

    $this->loggerFactory->get('xmt_dx_bridge')->notice('Processed DX claim for @id publisher @pid', [
      '@id' => $claim['dx_developer_id'],
      '@pid' => $publisher->id(),
    ]);

    return (int) $publisher->id();
  }

}
