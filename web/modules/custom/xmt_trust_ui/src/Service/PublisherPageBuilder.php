<?php

namespace Drupal\xmt_trust_ui\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\xmt_publisher\Entity\Publisher;

/**
 * Builds public publisher page sections (trust meta + article list).
 */
class PublisherPageBuilder {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Trust metadata block for an approved publisher.
   */
  public function buildMeta(Publisher $publisher): array {
    $type = $publisher->get('type')->value;
    $level = $type === 'official' ? 'l1_official' : 'l2_enterprise';

    $items = [
      [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => xmt_trust_badge_label($level),
        '#attributes' => [
          'class' => ['xmt-trust-badge', xmt_trust_badge_class($level)],
        ],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t('Certified publisher'),
        '#attributes' => ['class' => ['xmt-publisher-meta__status']],
      ],
    ];

    if (!$publisher->get('website')->isEmpty()) {
      $uri = $publisher->get('website')->uri ?? $publisher->get('website')->value ?? '';
      if ($uri !== '') {
        $items[] = Link::fromTextAndUrl($this->t('Website'), Url::fromUri($uri))->toRenderable();
      }
    }

    if ($type === 'enterprise' && !$publisher->get('credit_code')->isEmpty()) {
      $items[] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Registration: @code', [
          '@code' => $publisher->get('credit_code')->value,
        ]),
        '#attributes' => ['class' => ['xmt-publisher-meta__credit']],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['xmt-publisher-meta']],
      'items' => [
        '#theme' => 'item_list',
        '#items' => $items,
        '#attributes' => ['class' => ['xmt-publisher-meta__list']],
      ],
    ];
  }

  /**
   * Recent published articles for a publisher.
   */
  public function buildArticles(Publisher $publisher, int $limit = 20): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', 1)
      ->condition('field_publisher', $publisher->id())
      ->sort('created', 'DESC')
      ->range(0, max(1, min($limit, 50)))
      ->execute();

    if (!$nids) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['xmt-publisher-articles', 'xmt-publisher-articles--empty']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->t('Published articles'),
        ],
        'empty' => [
          '#markup' => '<p>' . $this->t('No published articles yet.') . '</p>',
        ],
      ];
    }

    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $storage->loadMultiple($nids);
    $items = [];
    foreach ($nodes as $node) {
      $level = 'l0_aggregate';
      if ($node->hasField('field_trust_level') && !$node->get('field_trust_level')->isEmpty()) {
        $level = $node->get('field_trust_level')->value ?: 'l0_aggregate';
      }

      $items[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['xmt-publisher-articles__item']],
        'link' => Link::fromTextAndUrl($node->label(), $node->toUrl())->toRenderable(),
        'badge' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => xmt_trust_badge_label($level),
          '#attributes' => [
            'class' => ['xmt-trust-badge', xmt_trust_badge_class($level)],
          ],
        ],
        'date' => [
          '#type' => 'html_tag',
          '#tag' => 'time',
          '#value' => \Drupal::service('date.formatter')->format($node->getCreatedTime(), 'short'),
          '#attributes' => [
            'class' => ['xmt-publisher-articles__date'],
            'datetime' => gmdate('c', $node->getCreatedTime()),
          ],
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['xmt-publisher-articles']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Published articles'),
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $items,
        '#attributes' => ['class' => ['xmt-publisher-articles__list']],
      ],
    ];
  }

  /**
   * Loads approved publishers, optionally filtered by type.
   *
   * @return \Drupal\xmt_publisher\Entity\Publisher[]
   */
  public function loadApproved(?string $type = NULL): array {
    $storage = $this->entityTypeManager->getStorage('xmt_publisher');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 'approved')
      ->sort('name', 'ASC');
    if ($type !== NULL && $type !== '') {
      $query->condition('type', $type);
    }
    $ids = $query->execute();
    return $ids ? $storage->loadMultiple($ids) : [];
  }

  /**
   * Builds the public publishers directory page.
   */
  public function buildDirectory(): array {
    $official = $this->loadApproved('official');
    $enterprise = $this->loadApproved('enterprise');

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['xmt-publishers-directory']],
      'intro' => [
        '#markup' => '<p>' . $this->t('Certified publishers on the XMT trust network.') . '</p>',
      ],
      'official' => $this->buildDirectorySection(
        $this->t('Official publishers (L1)'),
        $official,
        'l1_official',
      ),
      'enterprise' => $this->buildDirectorySection(
        $this->t('Enterprise publishers (L2)'),
        $enterprise,
        'l2_enterprise',
      ),
      'apply' => [
        '#type' => 'link',
        '#title' => $this->t('Apply for enterprise certification'),
        '#url' => Url::fromRoute('xmt_publisher.apply'),
        '#attributes' => ['class' => ['xmt-publishers-directory__apply']],
      ],
      '#attached' => ['library' => ['xmt_trust_ui/publisher_page']],
    ];
  }

  /**
   * RSS/JSON subscription links for a publisher page.
   */
  public function buildFeedLinks(Publisher $publisher): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['xmt-publisher-feed-links']],
      'rss' => Link::fromTextAndUrl($this->t('RSS'), Url::fromRoute('xmt_trust_ui.publisher_feed_rss', [
        'xmt_publisher' => $publisher->id(),
      ]))->toRenderable(),
      'json' => Link::fromTextAndUrl($this->t('JSON'), Url::fromRoute('xmt_trust_ui.publisher_feed_json', [
        'xmt_publisher' => $publisher->id(),
      ]))->toRenderable(),
    ];
  }

  /**
   * Builds one directory section for a publisher type.
   *
   * @param \Drupal\xmt_publisher\Entity\Publisher[] $publishers
   */
  protected function buildDirectorySection(string $title, array $publishers, string $level): array {
    if ($publishers === []) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['xmt-publishers-directory__section', 'xmt-publishers-directory__section--empty']],
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $title,
        ],
        'empty' => [
          '#markup' => '<p>' . $this->t('No publishers yet.') . '</p>',
        ],
      ];
    }

    $items = [];
    foreach ($publishers as $publisher) {
      $row = [
        '#type' => 'container',
        '#attributes' => ['class' => ['xmt-publishers-directory__item']],
        'link' => Link::fromTextAndUrl($publisher->label(), $publisher->toUrl())->toRenderable(),
        'badge' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => xmt_trust_badge_label($level),
          '#attributes' => [
            'class' => ['xmt-trust-badge', xmt_trust_badge_class($level)],
          ],
        ],
      ];
      if (!$publisher->get('website')->isEmpty()) {
        $uri = $publisher->get('website')->uri ?? $publisher->get('website')->value ?? '';
        if ($uri !== '') {
          $row['website'] = Link::fromTextAndUrl($this->t('Website'), Url::fromUri($uri))->toRenderable();
        }
      }
      $items[] = $row;
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['xmt-publishers-directory__section']],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $title,
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $items,
        '#attributes' => ['class' => ['xmt-publishers-directory__list']],
      ],
    ];
  }

}
