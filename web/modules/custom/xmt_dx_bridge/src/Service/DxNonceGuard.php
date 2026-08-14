<?php

namespace Drupal\xmt_dx_bridge\Service;

use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;

/**
 * Rejects reused DrupalX bridge nonces within an expiry window.
 */
class DxNonceGuard {

  public const COLLECTION = 'xmt_dx_bridge_nonces';

  /**
   * Default TTL when payload has no exp (2 hours).
   */
  public const DEFAULT_TTL = 7200;

  /**
   * Maximum TTL for stored nonces (24 hours).
   */
  public const MAX_TTL = 86400;

  public function __construct(
    protected KeyValueExpirableFactoryInterface $keyValueExpirableFactory,
  ) {}

  /**
   * Asserts the payload nonce is present and has not been used recently.
   *
   * @param array $payload
   *   Decoded JSON claim or content body.
   *
   * @throws \InvalidArgumentException
   *   When nonce is missing, invalid, or already consumed.
   */
  public function assertFresh(array $payload): void {
    if (empty($payload['nonce']) || !is_string($payload['nonce'])) {
      throw new \InvalidArgumentException('Missing or invalid nonce.');
    }
    $nonce = trim($payload['nonce']);
    if (strlen($nonce) < 8 || strlen($nonce) > 128) {
      throw new \InvalidArgumentException('Nonce length must be between 8 and 128 characters.');
    }

    $key = hash('sha256', $nonce);
    $store = $this->keyValueExpirableFactory->get(self::COLLECTION);
    if ($store->has($key)) {
      throw new \InvalidArgumentException('Nonce already used.');
    }

    $ttl = self::DEFAULT_TTL;
    if (!empty($payload['exp'])) {
      $remaining = (int) $payload['exp'] - time();
      if ($remaining > 0) {
        $ttl = $remaining;
      }
    }
    $ttl = max(60, min(self::MAX_TTL, $ttl));
    $store->setWithExpire($key, ['used_at' => time()], $ttl);
  }

}
