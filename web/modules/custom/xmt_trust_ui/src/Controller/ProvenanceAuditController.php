<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\xmt_trust_ui\Form\ProvenanceAuditFilterForm;
use Drupal\xmt_trust_ui\Service\ProvenanceAuditService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin listing and export of articles with provenance metadata.
 */
class ProvenanceAuditController extends ControllerBase {

  public const LIST_LIMIT = 50;

  public const EXPORT_LIMIT = 500;

  public function __construct(
    protected ProvenanceAuditService $auditService,
    protected FormBuilderInterface $formBuilderService,
    protected PagerManagerInterface $pagerManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('xmt_trust_ui.provenance_audit'),
      $container->get('form_builder'),
      $container->get('pager.manager'),
    );
  }

  /**
   * Lists recent trusted articles for audit.
   */
  public function list(Request $request): array {
    $filters = $this->filtersFromRequest($request);
    $total = $this->auditService->countArticles($filters);
    $this->pagerManager->createPager($total, self::LIST_LIMIT);
    $page = $this->pagerManager->findPage();
    $nodes = $this->auditService->loadArticles(self::LIST_LIMIT, $page * self::LIST_LIMIT, $filters);

    $header = [
      $this->t('Title'),
      $this->t('Trust level'),
      $this->t('Publisher'),
      $this->t('Provenance hash'),
      $this->t('Source URL'),
      $this->t('Updated'),
    ];

    $rows = [];
    foreach ($nodes as $node) {
      $rows[] = $this->buildTableRow($node);
    }

    $export_query = array_filter([
      'trust_level' => $filters['trust_level'] ?? '',
      'publisher_id' => $filters['publisher_id'] ?? '',
    ], static fn ($value) => $value !== '' && $value !== NULL);

    return [
      'filters' => $this->formBuilderService->getForm(ProvenanceAuditFilterForm::class),
      'summary' => [
        '#markup' => '<p class="xmt-provenance-audit__summary">' . $this->t('@count articles match.', [
          '@count' => $total,
        ]) . '</p>',
      ],
      'actions' => [
        '#type' => 'actions',
        'csv' => [
          '#type' => 'link',
          '#title' => $this->t('Export CSV'),
          '#url' => Url::fromRoute('xmt_trust_ui.provenance_audit_csv', [], ['query' => $export_query]),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
        'json' => [
          '#type' => 'link',
          '#title' => $this->t('Export JSON'),
          '#url' => Url::fromRoute('xmt_trust_ui.provenance_audit_json', [], ['query' => $export_query]),
          '#attributes' => ['class' => ['button']],
        ],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No articles found.'),
        '#attributes' => ['class' => ['xmt-provenance-audit']],
      ],
      'pager' => [
        '#type' => 'pager',
      ],
    ];
  }

  /**
   * Streams a CSV download of provenance audit records.
   */
  public function exportCsv(Request $request): StreamedResponse {
    $filters = $this->filtersFromRequest($request);
    $nodes = $this->auditService->loadArticles(self::EXPORT_LIMIT, 0, $filters);
    $filename = 'xmt-provenance-audit-' . gmdate('Y-m-d') . '.csv';

    $response = new StreamedResponse(function () use ($nodes): void {
      $handle = fopen('php://output', 'w');
      if ($handle === FALSE) {
        return;
      }
      $this->auditService->writeCsv($handle, $nodes);
      fclose($handle);
    });

    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
    $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $filename
    ));
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->headers->set('X-Content-Type-Options', 'nosniff');

    return $response;
  }

  /**
   * Returns a JSON download of provenance audit records.
   */
  public function exportJson(Request $request): JsonResponse {
    $filters = $this->filtersFromRequest($request);
    $nodes = $this->auditService->loadArticles(self::EXPORT_LIMIT, 0, $filters);
    $filename = 'xmt-provenance-audit-' . gmdate('Y-m-d') . '.json';

    $response = new JsonResponse($this->auditService->buildJsonPayload($nodes));
    $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $filename
    ));
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->headers->set('X-Content-Type-Options', 'nosniff');

    return $response;
  }

  /**
   * Reads supported audit filters from the request query string.
   *
   * @return array{trust_level?: string, publisher_id?: string}
   *   Supported audit filters.
   */
  protected function filtersFromRequest(Request $request): array {
    return [
      'trust_level' => (string) $request->query->get('trust_level', ''),
      'publisher_id' => (string) $request->query->get('publisher_id', ''),
    ];
  }

  /**
   * Builds a table row for one article.
   */
  protected function buildTableRow(NodeInterface $node): array {
    $record = $this->auditService->buildRecord($node);
    $title = Link::fromTextAndUrl($node->label(), $node->toUrl())->toString();

    $source = '—';
    if ($record['source_url'] !== '') {
      $source = UrlHelper::isValid($record['source_url'], TRUE)
        ? Link::fromTextAndUrl($record['source_url'], Url::fromUri($record['source_url']))->toString()
        : $record['source_url'];
    }

    return [
      $title,
      $record['trust_label'] !== '' ? $record['trust_label'] : $this->t('—'),
      $record['publisher'] !== '' ? $record['publisher'] : $this->t('—'),
      $record['provenance_hash'] !== '' ? $record['provenance_hash'] : '—',
      ['data' => ['#markup' => $source]],
      $record['changed'],
    ];
  }

}
