<?php

namespace Drupal\Tests\xmt_trust_ui\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\xmt_trust_ui\Controller\ProvenanceAuditController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests provenance CSV cell sanitization.
 */
#[Group('xmt_trust_ui')]
class ProvenanceAuditControllerTest extends UnitTestCase {

  /**
   * Tests that spreadsheet formulas cannot execute from exported cells.
   */
  #[DataProvider('csvCellProvider')]
  public function testSanitizeCsvCell(string $value, string $expected): void {
    $method = new \ReflectionMethod(ProvenanceAuditController::class, 'sanitizeCsvCell');
    $this->assertSame($expected, $method->invoke(NULL, $value));
  }

  /**
   * Provides CSV cell values and their safe equivalents.
   */
  public static function csvCellProvider(): array {
    return [
      'formula' => ['=HYPERLINK("https://example.com")', '\'=HYPERLINK("https://example.com")'],
      'addition' => ['+1+1', '\'+1+1'],
      'command' => ['-1+cmd', '\'-1+cmd'],
      'mention' => ['@SUM(A1:A2)', '\'@SUM(A1:A2)'],
      'leading whitespace' => ['  =1+1', '\'  =1+1'],
      'leading tab' => ["\tmalicious", "'\tmalicious"],
      'plain text' => ['Trusted publisher', 'Trusted publisher'],
      'empty value' => ['', ''],
    ];
  }

}
