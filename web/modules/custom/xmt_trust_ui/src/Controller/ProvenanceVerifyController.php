<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\xmt_trust\Provenance;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Public provenance verification for trusted articles.
 */
class ProvenanceVerifyController extends ControllerBase {

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
   * Page title for a verification page.
   */
  public function title(NodeInterface $node): string {
    return (string) $this->t('核验：@title', ['@title' => $node->label()]);
  }

  /**
   * Renders the human-readable verification page.
   */
  public function page(NodeInterface $node): array {
    $result = Provenance::verify($node);
    $level = $node->hasField('field_trust_level') && !$node->get('field_trust_level')->isEmpty()
      ? $node->get('field_trust_level')->value
      : 'l0_aggregate';

    $rows = [
      [$this->t('文章'), ['data' => Link::fromTextAndUrl($node->label(), $node->toUrl())->toRenderable()]],
      [$this->t('信任等级'), xmt_trust_badge_label($level)],
      [$this->t('发布主体'), ['data' => $this->publisherCell($node)]],
      [$this->t('来源'), ['data' => $this->sourceCell($result['source_url'])]],
      [$this->t('创建时间'), $this->dateFormatter->format($result['created'], 'long')],
      [$this->t('已记录哈希'), $result['stored'] !== '' ? $result['stored'] : $this->t('—')],
      [$this->t('重算哈希'), $result['expected'] !== '' ? $result['expected'] : $this->t('—')],
    ];

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['xmt-provenance-verify']],
      'verdict' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => xmt_trust_provenance_status_label($result['status']),
        '#attributes' => [
          'class' => ['xmt-provenance-verdict', xmt_trust_provenance_status_class($result['status'])],
        ],
      ],
      'summary' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->verdictSummary($result['status']),
        '#attributes' => ['class' => ['xmt-provenance-summary']],
      ],
      'table' => [
        '#type' => 'table',
        '#rows' => $rows,
        '#attributes' => ['class' => ['xmt-provenance-table']],
      ],
      'algorithm' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('哈希算法：<code>sha256(来源URL|主体ID|创建时间戳)</code>，主体缺失时主体ID为 <code>0</code>。'),
        '#attributes' => ['class' => ['xmt-provenance-algorithm']],
      ],
      'json' => [
        '#type' => 'link',
        '#title' => $this->t('机器可读结果（JSON）'),
        '#url' => Url::fromRoute('xmt_trust_ui.provenance_verify_json', ['node' => $node->id()]),
        '#attributes' => ['class' => ['xmt-provenance-json-link']],
      ],
      '#attached' => ['library' => ['xmt_trust_ui/trust_feed']],
      '#cache' => [
        'tags' => $node->getCacheTags(),
        'contexts' => ['url.path'],
      ],
    ];
  }

  /**
   * Returns the verification result as JSON for third-party checks.
   */
  public function json(NodeInterface $node): CacheableJsonResponse {
    $result = Provenance::verify($node);
    $publisher = $node->hasField('field_publisher') && !$node->get('field_publisher')->isEmpty()
      ? $node->get('field_publisher')->entity
      : NULL;

    $data = [
      'node' => [
        'id' => (int) $node->id(),
        'uuid' => $node->uuid(),
        'title' => $node->label(),
        'url' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
        'created' => $result['created'],
      ],
      'trust_level' => $node->hasField('field_trust_level') && !$node->get('field_trust_level')->isEmpty()
        ? $node->get('field_trust_level')->value
        : NULL,
      'publisher' => $publisher ? [
        'id' => (int) $publisher->id(),
        'name' => $publisher->label(),
        'type' => $publisher->get('type')->value,
        'status' => $publisher->get('status')->value,
      ] : NULL,
      'source_url' => $result['source_url'] !== '' ? $result['source_url'] : NULL,
      'provenance' => [
        'algorithm' => 'sha256(source_url|publisher_id|created)',
        'stored' => $result['stored'] !== '' ? $result['stored'] : NULL,
        'expected' => $result['expected'] !== '' ? $result['expected'] : NULL,
        'status' => $result['status'],
      ],
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray([
      '#cache' => [
        'tags' => $node->getCacheTags(),
        'contexts' => ['url.path'],
      ],
    ]));
    if ($publisher) {
      $response->addCacheableDependency($publisher);
    }

    return $response;
  }

  /**
   * Builds the publisher cell for the verification table.
   */
  protected function publisherCell(NodeInterface $node): array {
    if (!$node->hasField('field_publisher') || $node->get('field_publisher')->isEmpty()) {
      return ['#markup' => '—'];
    }
    $publisher = $node->get('field_publisher')->entity;
    if (!$publisher) {
      return ['#markup' => '—'];
    }
    if ($publisher->access('view')) {
      return Link::fromTextAndUrl($publisher->label(), $publisher->toUrl())->toRenderable();
    }
    return ['#plain_text' => $publisher->label()];
  }

  /**
   * Builds the source cell for the verification table.
   */
  protected function sourceCell(string $source_url): array {
    if ($source_url === '') {
      return ['#markup' => '—'];
    }
    try {
      $link = Link::fromTextAndUrl($source_url, Url::fromUri($source_url))->toRenderable();
      $link['#attributes']['rel'] = 'nofollow noopener';
      return $link;
    }
    catch (\InvalidArgumentException) {
      return ['#plain_text' => $source_url];
    }
  }

  /**
   * Explains a verification verdict to readers.
   */
  protected function verdictSummary(string $status): string {
    return match ($status) {
      Provenance::STATUS_VERIFIED => (string) $this->t('来源、主体与时间与首次落库记录一致。'),
      Provenance::STATUS_MISMATCH => (string) $this->t('当前字段与首次落库的溯源记录不一致，请核对修改记录。'),
      Provenance::STATUS_BRIDGE => (string) $this->t('该内容由 DrupalX 认证主体推送，溯源标识由对方签发，无法在本站重算。'),
      default => (string) $this->t('该文章尚未记录溯源哈希。'),
    };
  }

}
