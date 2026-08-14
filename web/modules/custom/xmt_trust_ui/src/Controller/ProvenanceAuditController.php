<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\xmt_trust\Provenance;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin listing and CSV export of article provenance metadata.
 */
class ProvenanceAuditController extends ControllerBase {

  /**
   * Number of articles shown on the audit page.
   */
  protected const LIST_LIMIT = 50;

  /**
   * Number of articles loaded per batch while exporting.
   */
  protected const EXPORT_BATCH = 100;

  public function __construct(
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('date.formatter'));
  }

  /**
   * Lists recent trusted articles for audit.
   */
  public function list(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $nids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->sort('changed', 'DESC')
      ->range(0, static::LIST_LIMIT)
      ->execute();

    $header = [
      $this->t('Title'),
      $this->t('Trust level'),
      $this->t('Publisher'),
      $this->t('Provenance hash'),
      $this->t('Verification'),
      $this->t('Source URL'),
      $this->t('Updated'),
    ];

    $rows = [];
    if ($nids) {
      /** @var \Drupal\node\NodeInterface[] $nodes */
      $nodes = $storage->loadMultiple($nids);
      foreach ($nodes as $node) {
        $rows[] = $this->buildRow($node);
      }
    }

    return [
      '#type' => 'container',
      'export' => [
        '#type' => 'link',
        '#title' => $this->t('Export CSV'),
        '#url' => Url::fromRoute('xmt_trust_ui.provenance_audit_export'),
        '#attributes' => ['class' => ['button', 'xmt-provenance-export']],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No articles found.'),
        '#attributes' => ['class' => ['xmt-provenance-audit']],
      ],
      '#attached' => ['library' => ['xmt_trust_ui/trust_feed']],
      '#cache' => ['tags' => ['node_list']],
    ];
  }

  /**
   * Streams the full provenance audit trail as CSV.
   */
  public function export(): StreamedResponse {
    $response = new StreamedResponse(function (): void {
      $handle = fopen('php://output', 'w');
      // BOM so spreadsheet tools detect UTF-8 for Chinese titles.
      fwrite($handle, "\xEF\xBB\xBF");
      fputcsv($handle, [
        'nid',
        'title',
        'trust_level',
        'publisher_id',
        'publisher_name',
        'source_url',
        'provenance_stored',
        'provenance_expected',
        'verification',
        'created',
        'changed',
        'verify_url',
      ]);
      foreach ($this->loadArticles() as $node) {
        fputcsv($handle, $this->buildCsvRow($node));
      }
      fclose($handle);
    });

    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
    $response->headers->set(
      'Content-Disposition',
      'attachment; filename="xmt-provenance-' . date('Ymd-His') . '.csv"'
    );

    return $response;
  }

  /**
   * Yields every accessible article, oldest first, in batches.
   *
   * @return \Generator|\Drupal\node\NodeInterface[]
   *   Article nodes.
   */
  protected function loadArticles(): \Generator {
    $storage = $this->entityTypeManager()->getStorage('node');
    $last_nid = 0;

    while (TRUE) {
      $nids = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'article')
        ->condition('nid', $last_nid, '>')
        ->sort('nid', 'ASC')
        ->range(0, static::EXPORT_BATCH)
        ->execute();

      if (!$nids) {
        break;
      }

      foreach ($storage->loadMultiple($nids) as $node) {
        $last_nid = max($last_nid, (int) $node->id());
        yield $node;
      }

      if (count($nids) < static::EXPORT_BATCH) {
        break;
      }
      $storage->resetCache($nids);
    }
  }

  /**
   * Builds a table row for one article.
   */
  protected function buildRow(NodeInterface $node): array {
    $title = Link::fromTextAndUrl($node->label(), $node->toUrl())->toString();

    $level = $node->hasField('field_trust_level') && !$node->get('field_trust_level')->isEmpty()
      ? xmt_trust_badge_label($node->get('field_trust_level')->value)
      : $this->t('—');

    $publisher = $this->t('—');
    if ($node->hasField('field_publisher') && !$node->get('field_publisher')->isEmpty()) {
      $entity = $node->get('field_publisher')->entity;
      $publisher = $entity ? $entity->label() : $node->get('field_publisher')->target_id;
    }

    $result = Provenance::verify($node);
    $provenance = $result['stored'] !== '' ? $result['stored'] : '—';

    $verification = [
      'data' => [
        '#type' => 'link',
        '#title' => xmt_trust_provenance_status_label($result['status']),
        '#url' => Url::fromRoute('xmt_trust_ui.provenance_verify', ['node' => $node->id()]),
        '#attributes' => [
          'class' => ['xmt-provenance-status', xmt_trust_provenance_status_class($result['status'])],
        ],
      ],
    ];

    $source = '—';
    if ($result['source_url'] !== '') {
      try {
        $source = Link::fromTextAndUrl($result['source_url'], Url::fromUri($result['source_url']))->toString();
      }
      catch (\InvalidArgumentException) {
        $source = $result['source_url'];
      }
    }

    $updated = $this->dateFormatter->format($node->getChangedTime(), 'short');

    return [
      ['data' => ['#markup' => $title]],
      $level,
      $publisher,
      $provenance,
      $verification,
      ['data' => ['#markup' => $source]],
      $updated,
    ];
  }

  /**
   * Builds a CSV record for one article.
   */
  protected function buildCsvRow(NodeInterface $node): array {
    $result = Provenance::verify($node);

    $publisher_name = '';
    if ($result['publisher_id'] !== 0 && $node->hasField('field_publisher')) {
      $publisher = $node->get('field_publisher')->entity;
      $publisher_name = $publisher ? (string) $publisher->label() : '';
    }

    return [
      (int) $node->id(),
      (string) $node->label(),
      $node->hasField('field_trust_level') ? (string) $node->get('field_trust_level')->value : '',
      $result['publisher_id'] ?: '',
      $publisher_name,
      $result['source_url'],
      $result['stored'],
      $result['expected'],
      $result['status'],
      date('c', $result['created']),
      date('c', $node->getChangedTime()),
      Url::fromRoute('xmt_trust_ui.provenance_verify', ['node' => $node->id()], ['absolute' => TRUE])->toString(),
    ];
  }

}
