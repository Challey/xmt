<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\xmt_publisher\Entity\Publisher;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Public directory of certified publishers.
 */
class PublisherDirectoryController extends ControllerBase {

  /**
   * Publisher types that may be filtered on.
   */
  public const TYPES = ['official', 'enterprise'];

  /**
   * Publishers listed per page.
   */
  protected const PAGE_LIMIT = 25;

  public function __construct(
    protected DateFormatterInterface $dateFormatter,
    protected RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('date.formatter'),
      $container->get('request_stack'),
    );
  }

  /**
   * Restricts a requested type filter to the known publisher types.
   *
   * @param string|null $type
   *   Raw filter value from the query string.
   *
   * @return string|null
   *   A known publisher type, or NULL for no filtering.
   */
  public static function normalizeType(?string $type): ?string {
    $type = strtolower(trim((string) $type));
    return in_array($type, self::TYPES, TRUE) ? $type : NULL;
  }

  /**
   * Lists approved publishers.
   */
  public function page(): array {
    $type = static::normalizeType(
      $this->requestStack->getCurrentRequest()?->query->getString('type')
    );

    $storage = $this->entityTypeManager()->getStorage('xmt_publisher');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 'approved')
      ->sort('name', 'ASC')
      ->pager(static::PAGE_LIMIT);
    if ($type !== NULL) {
      $query->condition('type', $type);
    }

    $rows = [];
    $ids = $query->execute();
    if ($ids) {
      /** @var \Drupal\xmt_publisher\Entity\Publisher $publisher */
      foreach ($storage->loadMultiple($ids) as $publisher) {
        $rows[] = $this->buildRow($publisher);
      }
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['xmt-publisher-directory']],
      'filter' => $this->buildFilter($type),
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('主体'),
          $this->t('类型'),
          $this->t('认证时间'),
          $this->t('可信发文'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('暂无已认证主体。'),
      ],
      'pager' => ['#type' => 'pager'],
      'apply' => [
        '#type' => 'link',
        '#title' => $this->t('申请企业可信发布认证'),
        '#url' => Url::fromRoute('xmt_publisher.apply'),
        '#attributes' => ['class' => ['xmt-publisher-directory__apply']],
        '#access' => $this->currentUser()->hasPermission('apply xmt publisher'),
      ],
      '#attached' => ['library' => ['xmt_trust_ui/trust_feed']],
      '#cache' => [
        'tags' => ['xmt_publisher_list', 'node_list'],
        'contexts' => ['url.query_args:type', 'user.permissions'],
      ],
    ];
  }

  /**
   * Builds the type filter links.
   */
  protected function buildFilter(?string $active): array {
    $links = [
      'all' => ['title' => $this->t('全部'), 'type' => NULL],
      'official' => ['title' => $this->t('官方主体'), 'type' => 'official'],
      'enterprise' => ['title' => $this->t('企业主体'), 'type' => 'enterprise'],
    ];

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['xmt-publisher-directory__filter']],
    ];
    foreach ($links as $key => $link) {
      $options = $link['type'] === NULL ? [] : ['query' => ['type' => $link['type']]];
      $classes = ['xmt-publisher-directory__filter-link'];
      if ($link['type'] === $active) {
        $classes[] = 'is-active';
      }
      $build[$key] = [
        '#type' => 'link',
        '#title' => $link['title'],
        '#url' => Url::fromRoute('xmt_trust_ui.publisher_directory', [], $options),
        '#attributes' => ['class' => $classes],
      ];
    }

    return $build;
  }

  /**
   * Builds a directory row for one publisher.
   */
  protected function buildRow(Publisher $publisher): array {
    $name = $publisher->access('view')
      ? ['data' => Link::fromTextAndUrl($publisher->label(), $publisher->toUrl())->toRenderable()]
      : ['data' => ['#plain_text' => $publisher->label()]];

    $type = $publisher->get('type')->value === 'official'
      ? $this->t('官方')
      : $this->t('企业');

    return [
      $name,
      $type,
      $this->dateFormatter->format((int) $publisher->get('created')->value, 'custom', 'Y-m-d'),
      $this->countTrustedArticles((int) $publisher->id()),
    ];
  }

  /**
   * Counts published trusted articles attributed to a publisher.
   */
  protected function countTrustedArticles(int $publisher_id): int {
    // Vertical sites may run the UI without the trust fields bootstrapped.
    if (!xmt_trust_ui_article_trust_fields_exist()) {
      return 0;
    }
    return (int) xmt_trust_ui_publisher_article_query($publisher_id)
      ->count()
      ->execute();
  }

}
