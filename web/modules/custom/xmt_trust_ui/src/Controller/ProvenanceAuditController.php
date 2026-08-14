<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin listing of recent articles with provenance metadata.
 */
class ProvenanceAuditController extends ControllerBase {

  /**
   * The node storage.
   */
  protected EntityStorageInterface $nodeStorage;

  /**
   * Constructs a provenance audit controller.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    protected DateFormatterInterface $dateFormatter,
  ) {
    $this->nodeStorage = $entity_type_manager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Lists recent trusted articles for audit.
   */
  public function list(): array {
    $nids = $this->trustedArticleQuery()
      ->pager(50)
      ->execute();

    $header = [
      $this->t('Title'),
      $this->t('Trust level'),
      $this->t('Publisher'),
      $this->t('Provenance hash'),
      $this->t('Source URL'),
      $this->t('Updated'),
    ];

    $rows = [];
    if ($nids) {
      /** @var \Drupal\node\NodeInterface[] $nodes */
      $nodes = $this->nodeStorage->loadMultiple($nids);
      foreach ($nodes as $node) {
        $rows[] = $this->buildRow($node);
      }
    }

    return [
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['xmt-provenance-audit__actions']],
        'export' => [
          '#type' => 'link',
          '#title' => $this->t('Export CSV'),
          '#url' => Url::fromRoute('xmt_trust_ui.provenance_export'),
          '#attributes' => [
            'class' => ['button', 'button--primary'],
            'download' => TRUE,
          ],
        ],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No trusted articles found.'),
        '#attributes' => ['class' => ['xmt-provenance-audit']],
      ],
      'pager' => [
        '#type' => 'pager',
      ],
    ];
  }

  /**
   * Exports all accessible trusted articles as CSV.
   */
  public function export(): StreamedResponse {
    $nids = $this->trustedArticleQuery()->execute();

    $response = new StreamedResponse(function () use ($nids): void {
      $output = fopen('php://output', 'wb');
      if ($output === FALSE) {
        return;
      }

      // Help spreadsheet applications detect UTF-8 without changing cell data.
      fwrite($output, "\xEF\xBB\xBF");
      fputcsv($output, [
        'Node ID',
        'Title',
        'Trust level',
        'Publisher',
        'Provenance hash',
        'Source URL',
        'Updated (UTC)',
      ], ',', '"', '');

      foreach (array_chunk($nids, 100) as $chunk) {
        /** @var \Drupal\node\NodeInterface[] $nodes */
        $nodes = $this->nodeStorage->loadMultiple($chunk);
        foreach ($chunk as $nid) {
          if (!isset($nodes[$nid])) {
            continue;
          }
          fputcsv(
            $output,
            array_map(
              static fn(mixed $value): string => static::sanitizeCsvCell($value),
              $this->buildExportRow($nodes[$nid]),
            ),
            ',',
            '"',
            '',
          );
        }
        $this->nodeStorage->resetCache($chunk);
      }

      fclose($output);
    });

    $filename = 'xmt-provenance-' . gmdate('Y-m-d') . '.csv';
    $disposition = HeaderUtils::makeDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $filename,
    );
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', $disposition);
    $response->headers->set('Cache-Control', 'private, no-store');

    return $response;
  }

  /**
   * Builds the common query for accessible L1 and L2 articles.
   */
  protected function trustedArticleQuery(): QueryInterface {
    return $this->nodeStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('field_trust_level', ['l1_official', 'l2_enterprise'], 'IN')
      ->sort('changed', 'DESC')
      ->sort('nid', 'DESC');
  }

  /**
   * Builds a table row for one article.
   */
  protected function buildRow(NodeInterface $node): array {
    $title = Link::fromTextAndUrl($node->label(), $node->toUrl())->toRenderable();

    $level = $node->hasField('field_trust_level') && !$node->get('field_trust_level')->isEmpty()
      ? xmt_trust_badge_label($node->get('field_trust_level')->value)
      : $this->t('—');

    $publisher = $this->t('—');
    if ($node->hasField('field_publisher') && !$node->get('field_publisher')->isEmpty()) {
      $entity = $node->get('field_publisher')->entity;
      $publisher = $entity ? $entity->label() : $node->get('field_publisher')->target_id;
    }

    $provenance = $node->hasField('field_provenance_hash') && !$node->get('field_provenance_hash')->isEmpty()
      ? $node->get('field_provenance_hash')->value
      : '—';

    $source = '—';
    if ($node->hasField('field_source_url') && !$node->get('field_source_url')->isEmpty()) {
      $uri = $node->get('field_source_url')->uri ?? $node->get('field_source_url')->value ?? '';
      if ($uri !== '') {
        $source = UrlHelper::isValid($uri, TRUE)
          ? Link::fromTextAndUrl($uri, Url::fromUri($uri))->toRenderable()
          : $uri;
      }
    }

    $updated = $this->dateFormatter->format($node->getChangedTime(), 'short');

    return [$title, $level, $publisher, $provenance, $source, $updated];
  }

  /**
   * Builds a raw CSV row for one article.
   */
  protected function buildExportRow(NodeInterface $node): array {
    $level = $node->hasField('field_trust_level') && !$node->get('field_trust_level')->isEmpty()
      ? $node->get('field_trust_level')->value
      : '';

    $publisher = '';
    if ($node->hasField('field_publisher') && !$node->get('field_publisher')->isEmpty()) {
      $entity = $node->get('field_publisher')->entity;
      $publisher = $entity ? $entity->label() : (string) $node->get('field_publisher')->target_id;
    }

    $provenance = $node->hasField('field_provenance_hash') && !$node->get('field_provenance_hash')->isEmpty()
      ? (string) $node->get('field_provenance_hash')->value
      : '';

    $source = '';
    if ($node->hasField('field_source_url') && !$node->get('field_source_url')->isEmpty()) {
      $source = (string) ($node->get('field_source_url')->uri ?? $node->get('field_source_url')->value ?? '');
    }

    return [
      (string) $node->id(),
      $node->label(),
      $level,
      $publisher,
      $provenance,
      $source,
      gmdate(DATE_ATOM, $node->getChangedTime()),
    ];
  }

  /**
   * Prevents spreadsheet formula execution in exported text cells.
   */
  protected static function sanitizeCsvCell(mixed $value): string {
    $value = (string) $value;
    if ($value !== '' && preg_match('/^[=+\-@]/', $value)) {
      return "'" . $value;
    }
    return $value;
  }

}
