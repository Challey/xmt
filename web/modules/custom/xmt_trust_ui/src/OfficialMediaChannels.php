<?php

namespace Drupal\xmt_trust_ui;

/**
 * Allowlisted 官媒报道 channel codes (stored in field_domain).
 */
final class OfficialMediaChannels {

  /**
   * Channel code => Chinese label.
   */
  public const CHANNELS = [
    'society' => '社会',
    'finance' => '财经',
    'tech' => '科技',
    'entertainment' => '娱乐',
    'fashion' => '时尚',
    'military' => '军事',
    'sports' => '体育',
    'gov' => '政务',
    'auto' => '汽车',
    'property' => '房产',
  ];

  /**
   * Returns TRUE if code is a known channel.
   */
  public static function isValid(?string $code): bool {
    return $code !== NULL && $code !== '' && isset(self::CHANNELS[$code]);
  }

  /**
   * Label for a channel code.
   */
  public static function label(string $code): string {
    return self::CHANNELS[$code] ?? $code;
  }

}
