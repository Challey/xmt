<?php

namespace Drupal\Tests\xmt_trust\Unit;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\node\NodeInterface;
use Drupal\xmt_trust\Provenance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests provenance hashing and verification.
 */
#[CoversClass(Provenance::class)]
#[Group('xmt_trust')]
class ProvenanceTest extends UnitTestCase {

  /**
   * Creation timestamp used across the test cases.
   */
  protected const CREATED = 1750000000;

  /**
   * The hash algorithm published on the verification page must not drift.
   */
  public function testHashFollowsPublishedAlgorithm(): void {
    $this->assertSame(
      'https://example.com/a|7|' . self::CREATED,
      Provenance::payload('https://example.com/a', 7, self::CREATED)
    );
    $this->assertSame(
      hash('sha256', 'https://example.com/a|7|' . self::CREATED),
      Provenance::hash('https://example.com/a', 7, self::CREATED)
    );
  }

  /**
   * Unattributed originals hash over an empty source and publisher 0.
   */
  public function testHashWithoutSourceOrPublisher(): void {
    $this->assertSame(
      hash('sha256', '|0|' . self::CREATED),
      Provenance::hash('', 0, self::CREATED)
    );
  }

  /**
   * Field readers return the stored source, publisher, and hash.
   */
  public function testFieldReaders(): void {
    $node = $this->mockNode([
      'field_source_url' => ['uri' => 'https://example.com/a'],
      'field_publisher' => ['target_id' => '7'],
      'field_provenance_hash' => ['value' => 'abc'],
    ]);

    $this->assertSame('https://example.com/a', Provenance::sourceUrl($node));
    $this->assertSame(7, Provenance::publisherId($node));
    $this->assertSame('abc', Provenance::storedHash($node));
  }

  /**
   * Missing and empty fields must not raise errors.
   */
  public function testFieldReadersTolerateAbsentValues(): void {
    $absent = $this->mockNode([]);
    $this->assertSame('', Provenance::sourceUrl($absent));
    $this->assertSame(0, Provenance::publisherId($absent));
    $this->assertSame('', Provenance::storedHash($absent));

    $empty = $this->mockNode([
      'field_source_url' => NULL,
      'field_publisher' => NULL,
      'field_provenance_hash' => NULL,
    ]);
    $this->assertSame('', Provenance::sourceUrl($empty));
    $this->assertSame(0, Provenance::publisherId($empty));
    $this->assertSame('', Provenance::storedHash($empty));
  }

  /**
   * A string source field is read from the value property.
   */
  public function testSourceUrlFallsBackToValueProperty(): void {
    $node = $this->mockNode([
      'field_source_url' => ['value' => 'https://example.com/plain'],
    ]);
    $this->assertSame('https://example.com/plain', Provenance::sourceUrl($node));
  }

  /**
   * The expected hash is recomputed from current field values.
   */
  public function testExpectedHashUsesCurrentFieldValues(): void {
    $node = $this->mockNode([
      'field_source_url' => ['uri' => 'https://example.com/a'],
      'field_publisher' => ['target_id' => '7'],
    ]);

    $this->assertSame(
      Provenance::hash('https://example.com/a', 7, self::CREATED),
      Provenance::expectedHash($node)
    );
  }

  /**
   * An untouched record verifies and reports the values it hashed.
   */
  public function testVerifyReportsVerified(): void {
    $stored = Provenance::hash('https://example.com/a', 7, self::CREATED);
    $node = $this->mockNode([
      'field_source_url' => ['uri' => 'https://example.com/a'],
      'field_publisher' => ['target_id' => '7'],
      'field_provenance_hash' => ['value' => $stored],
    ]);

    $result = Provenance::verify($node);
    $this->assertSame(Provenance::STATUS_VERIFIED, $result['status']);
    $this->assertSame($stored, $result['stored']);
    $this->assertSame($stored, $result['expected']);
    $this->assertSame('https://example.com/a', $result['source_url']);
    $this->assertSame(7, $result['publisher_id']);
    $this->assertSame(self::CREATED, $result['created']);
  }

  /**
   * Editing the source after the hash was recorded must be detectable.
   */
  public function testVerifyReportsMismatchAfterSourceEdit(): void {
    $stored = Provenance::hash('https://example.com/original', 7, self::CREATED);
    $node = $this->mockNode([
      'field_source_url' => ['uri' => 'https://example.com/rewritten'],
      'field_publisher' => ['target_id' => '7'],
      'field_provenance_hash' => ['value' => $stored],
    ]);

    $result = Provenance::verify($node);
    $this->assertSame(Provenance::STATUS_MISMATCH, $result['status']);
    $this->assertSame($stored, $result['stored']);
    $this->assertNotSame($stored, $result['expected']);
  }

  /**
   * Swapping the publisher must be detectable.
   */
  public function testVerifyReportsMismatchAfterPublisherSwap(): void {
    $node = $this->mockNode([
      'field_source_url' => ['uri' => 'https://example.com/a'],
      'field_publisher' => ['target_id' => '9'],
      'field_provenance_hash' => [
        'value' => Provenance::hash('https://example.com/a', 7, self::CREATED),
      ],
    ]);

    $this->assertSame(Provenance::STATUS_MISMATCH, Provenance::verify($node)['status']);
  }

  /**
   * Bridge hashes are attested by DrupalX and never recomputed.
   */
  public function testVerifyReportsBridgeHashes(): void {
    $node = $this->mockNode([
      'field_provenance_hash' => ['value' => Provenance::BRIDGE_PREFIX . 'external-123'],
    ]);

    $result = Provenance::verify($node);
    $this->assertSame(Provenance::STATUS_BRIDGE, $result['status']);
    $this->assertSame('', $result['expected']);
  }

  /**
   * Articles without a recorded hash report as missing.
   */
  public function testVerifyReportsMissingHash(): void {
    $result = Provenance::verify($this->mockNode(['field_provenance_hash' => NULL]));
    $this->assertSame(Provenance::STATUS_MISSING, $result['status']);
    $this->assertSame('', $result['stored']);
    $this->assertSame('', $result['expected']);
  }

  /**
   * Builds an article node stub.
   *
   * @param array $fields
   *   Field name keyed values; NULL marks a present but empty field, and
   *   omitted keys mark fields that do not exist on the bundle.
   *
   * @return \Drupal\node\NodeInterface
   *   Node stub.
   */
  protected function mockNode(array $fields): NodeInterface {
    $lists = [];
    foreach ($fields as $name => $value) {
      $lists[$name] = $this->mockFieldList($value);
    }

    $node = $this->createMock(NodeInterface::class);
    $node->method('getCreatedTime')->willReturn(self::CREATED);
    $node->method('hasField')
      ->willReturnCallback(static fn (string $name): bool => array_key_exists($name, $lists));
    $node->method('get')
      ->willReturnCallback(static fn (string $name): ?FieldItemListInterface => $lists[$name] ?? NULL);

    return $node;
  }

  /**
   * Builds a single-value field item list stub.
   *
   * @param array|null $value
   *   Raw item value, or NULL for an empty field.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   Field item list stub.
   */
  protected function mockFieldList(?array $value): FieldItemListInterface {
    $list = $this->createMock(FieldItemListInterface::class);
    $list->method('isEmpty')->willReturn($value === NULL);

    if ($value === NULL) {
      $list->method('first')->willReturn(NULL);
      return $list;
    }

    $item = $this->createMock(FieldItemInterface::class);
    $item->method('getValue')->willReturn($value);
    $list->method('first')->willReturn($item);

    return $list;
  }

}
