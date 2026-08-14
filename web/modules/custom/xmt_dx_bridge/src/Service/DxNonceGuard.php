<?php

namespace Drupal\xmt_dx_bridge\Service;

use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;

/**
 * Rejects replayed HMAC-signed DrupalX bridge payloads by nonce.
 *
 * A valid signed payload is otherwise reusable by anyone who observes it
 * (e.g. on the wire or in logs) until its `exp` timestamp. Recording each
 * nonce we've already processed closes that replay window.
 */
class DxNonceGuard {

  protected const COLLECTION = 'xmt_dx_bridge.nonce';

  /**
   * Replay-protection window, in seconds, used when a payload has no `exp`.
   */
  protected const DEFAULT_TTL = 600;

  public function __construct(
    protected readonly KeyValueExpirableFactoryInterface $keyValueExpirableFactory,
  ) {}

  /**
   * Records a nonce for a developer, rejecting it if already seen.
   *
   * Payloads without a nonce are allowed through unchecked for backward
   * compatibility with callers that predate this guard; new integrations
   * should always send one.
   *
   * @param string $developerId
   *   The DrupalX developer ID the payload claims to be from.
   * @param string|null $nonce
   *   The payload's nonce, if any.
   * @param int|null $exp
   *   The payload's Unix expiry timestamp, if any. Used to size the replay
   *   window so we don't remember nonces longer than the payload is valid.
   *
   * @throws \InvalidArgumentException
   *   If this nonce was already consumed for this developer.
   */
  public function consume(string $developerId, ?string $nonce, ?int $exp): void {
    if ($nonce === NULL || $nonce === '') {
      return;
    }

    $store = $this->keyValueExpirableFactory->get(self::COLLECTION);
    // Scope by developer so different callers can't collide on nonce values.
    $key = hash('sha256', $developerId . ':' . $nonce);
    if ($store->has($key)) {
      throw new \InvalidArgumentException('Nonce already used; possible replay.');
    }

    $ttl = $exp !== NULL && $exp > time() ? ($exp - time()) : self::DEFAULT_TTL;
    $store->setWithExpire($key, TRUE, max($ttl, 60));
  }

}
