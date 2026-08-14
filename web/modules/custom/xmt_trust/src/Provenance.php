<?php

namespace Drupal\xmt_trust;

use Drupal\node\NodeInterface;

/**
 * Computes and verifies article provenance hashes.
 *
 * The hash is written once, on the first save that has a provenance field, and
 * is never rewritten afterwards. Verification recomputes the expected hash from
 * the current field values, so any later change to source URL, publisher, or
 * creation time surfaces as a mismatch instead of silently rewriting history.
 */
final class Provenance {

  /**
   * Prefix of hashes attested by the DrupalX bridge instead of computed here.
   */
  public const BRIDGE_PREFIX = 'dx:';

  /**
   * Stored hash equals the hash recomputed from current field values.
   */
  public const STATUS_VERIFIED = 'verified';

  /**
   * Stored hash disagrees with current field values.
   */
  public const STATUS_MISMATCH = 'mismatch';

  /**
   * Hash was issued by DrupalX; it cannot be recomputed locally.
   */
  public const STATUS_BRIDGE = 'bridge';

  /**
   * No hash recorded.
   */
  public const STATUS_MISSING = 'missing';

  /**
   * Builds the canonical string that provenance hashes are computed over.
   *
   * @param string $source_url
   *   Source URL, or an empty string for originally authored content.
   * @param int $publisher_id
   *   Publisher entity ID, or 0 when unattributed.
   * @param int $created
   *   Article creation timestamp.
   *
   * @return string
   *   Canonical payload.
   */
  public static function payload(string $source_url, int $publisher_id, int $created): string {
    return $source_url . '|' . $publisher_id . '|' . $created;
  }

  /**
   * Hashes a provenance payload.
   *
   * @param string $source_url
   *   Source URL, or an empty string for originally authored content.
   * @param int $publisher_id
   *   Publisher entity ID, or 0 when unattributed.
   * @param int $created
   *   Article creation timestamp.
   *
   * @return string
   *   Lowercase hex sha256 digest.
   */
  public static function hash(string $source_url, int $publisher_id, int $created): string {
    return hash('sha256', static::payload($source_url, $publisher_id, $created));
  }

  /**
   * Reads the source URL of an article.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Article node.
   *
   * @return string
   *   Source URL, or an empty string when unset.
   */
  public static function sourceUrl(NodeInterface $node): string {
    if (!$node->hasField('field_source_url') || $node->get('field_source_url')->isEmpty()) {
      return '';
    }
    $value = $node->get('field_source_url')->first()->getValue();
    return (string) ($value['uri'] ?? $value['value'] ?? '');
  }

  /**
   * Reads the publisher ID of an article.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Article node.
   *
   * @return int
   *   Publisher entity ID, or 0 when unattributed.
   */
  public static function publisherId(NodeInterface $node): int {
    if (!$node->hasField('field_publisher') || $node->get('field_publisher')->isEmpty()) {
      return 0;
    }
    $value = $node->get('field_publisher')->first()->getValue();
    return (int) ($value['target_id'] ?? 0);
  }

  /**
   * Reads the stored provenance hash of an article.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Article node.
   *
   * @return string
   *   Stored hash, or an empty string when unset.
   */
  public static function storedHash(NodeInterface $node): string {
    if (!$node->hasField('field_provenance_hash') || $node->get('field_provenance_hash')->isEmpty()) {
      return '';
    }
    $value = $node->get('field_provenance_hash')->first()->getValue();
    return (string) ($value['value'] ?? '');
  }

  /**
   * Computes the hash an article should carry for its current field values.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Article node.
   *
   * @return string
   *   Lowercase hex sha256 digest.
   */
  public static function expectedHash(NodeInterface $node): string {
    return static::hash(
      static::sourceUrl($node),
      static::publisherId($node),
      (int) $node->getCreatedTime(),
    );
  }

  /**
   * Verifies the stored provenance hash of an article.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Article node.
   *
   * @return array
   *   Associative array with keys:
   *   - status: one of the STATUS_* constants.
   *   - stored: the recorded hash, or an empty string.
   *   - expected: the recomputed hash, or an empty string for bridge hashes.
   *   - source_url: the source URL used for recomputation.
   *   - publisher_id: the publisher ID used for recomputation.
   *   - created: the creation timestamp used for recomputation.
   */
  public static function verify(NodeInterface $node): array {
    $stored = static::storedHash($node);
    $result = [
      'status' => static::STATUS_MISSING,
      'stored' => $stored,
      'expected' => '',
      'source_url' => static::sourceUrl($node),
      'publisher_id' => static::publisherId($node),
      'created' => (int) $node->getCreatedTime(),
    ];

    if ($stored === '') {
      return $result;
    }
    if (str_starts_with($stored, static::BRIDGE_PREFIX)) {
      $result['status'] = static::STATUS_BRIDGE;
      return $result;
    }

    $result['expected'] = static::expectedHash($node);
    $result['status'] = hash_equals($result['expected'], $stored)
      ? static::STATUS_VERIFIED
      : static::STATUS_MISMATCH;

    return $result;
  }

}
