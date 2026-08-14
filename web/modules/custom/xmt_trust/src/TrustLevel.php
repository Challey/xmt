<?php

namespace Drupal\xmt_trust;

/**
 * Trust level codes stored in field_trust_level.
 */
final class TrustLevel {

  /**
   * Aggregated by the agent, no certified subject behind it.
   */
  public const L0_AGGREGATE = 'l0_aggregate';

  /**
   * Published by XMT itself as the accountable subject.
   */
  public const L1_OFFICIAL = 'l1_official';

  /**
   * Published by a certified enterprise subject.
   */
  public const L2_ENTERPRISE = 'l2_enterprise';

  /**
   * Levels that carry an accountable publisher.
   */
  public const ATTRIBUTED = [self::L1_OFFICIAL, self::L2_ENTERPRISE];

}
