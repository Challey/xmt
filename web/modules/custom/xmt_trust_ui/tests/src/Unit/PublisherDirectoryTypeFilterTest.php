<?php

namespace Drupal\Tests\xmt_trust_ui\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\xmt_trust_ui\Controller\PublisherDirectoryController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the publisher directory type filter.
 */
#[CoversClass(PublisherDirectoryController::class)]
#[Group('xmt_trust_ui')]
class PublisherDirectoryTypeFilterTest extends UnitTestCase {

  /**
   * Only known publisher types may reach the entity query.
   */
  #[DataProvider('typeProvider')]
  public function testNormalizeType(?string $input, ?string $expected): void {
    $this->assertSame($expected, PublisherDirectoryController::normalizeType($input));
  }

  /**
   * Provides filter inputs and the type they normalize to.
   */
  public static function typeProvider(): array {
    return [
      'official' => ['official', 'official'],
      'enterprise' => ['enterprise', 'enterprise'],
      'mixed case' => ['Enterprise', 'enterprise'],
      'padded' => ["  official\n", 'official'],
      'unknown type' => ['suspended', NULL],
      'empty' => ['', NULL],
      'null' => [NULL, NULL],
      'injection attempt' => ['official OR 1=1', NULL],
    ];
  }

}
