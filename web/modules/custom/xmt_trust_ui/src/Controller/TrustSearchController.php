<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Search across trusted articles (title + brief body).
 */
class TrustSearchController extends ControllerBase {

  /**
   * Search form + results.
   */
  public function search(Request $request): array {
    $q = trim((string) $request->query->get('q', ''));
    $level = (string) $request->query->get('level', 'trusted');
    $items = [];
    if ($q !== '' && mb_strlen($q) >= 2) {
      $items = $this->find($q, $level, 40);
    }

    return [
      '#theme' => 'xmt_trust_search',
      '#q' => $q,
      '#level' => $level,
      '#items' => $items,
      '#filters' => $this->filters($q, $level),
      '#form_action' => Url::fromRoute('xmt_trust_ui.search')->toString(),
      '#short_news_url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
        'query' => array_filter([
          'level' => $level === 'trusted' ? NULL : $level,
        ]),
      ])->toString(),
      '#immerse_url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
        'query' => array_filter([
          'level' => $level === 'trusted' ? NULL : $level,
          'mode' => 'immerse',
        ]),
      ])->toString(),
      '#today_url' => Url::fromRoute('xmt_trust_ui.short_read_today', [], [
        'query' => array_filter([
          'level' => $level === 'trusted' ? NULL : $level,
        ]),
      ])->toString(),
      '#rss_url' => Url::fromRoute('xmt_trust_ui.short_news_rss', [], [
        'query' => array_filter([
          'level' => $level === 'trusted' ? NULL : $level,
        ]),
      ])->toString(),
      '#attached' => [
        'library' => ['xmt_trust_ui/trust_feed', 'xmt_trust_ui/short_read'],
      ],
      '#cache' => [
        'contexts' => ['url.query_args:q', 'url.query_args:level', 'user.permissions'],
        'tags' => ['node_list:article'],
        'max-age' => 30,
      ],
    ];
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  protected function find(string $q, string $level, int $limit): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $levels = match ($level) {
      'official' => ['l1_official'],
      'enterprise' => ['l2_enterprise'],
      'aggregate' => ['l0_aggregate'],
      default => ['l1_official', 'l2_enterprise', 'l0_aggregate'],
    };

    // Title match first.
    $nids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', 1)
      ->condition('field_trust_level', $levels, 'IN')
      ->condition('title', '%' . $this->escapeLike($q) . '%', 'LIKE')
      ->sort('created', 'DESC')
      ->range(0, $limit)
      ->execute();

    $found = $nids ? array_values($nids) : [];

    // Supplement with body match if under limit (DB LIKE; RSS bodies are short).
    if (count($found) < $limit) {
      $more = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'article')
        ->condition('status', 1)
        ->condition('field_trust_level', $levels, 'IN')
        ->condition('body.value', '%' . $this->escapeLike($q) . '%', 'LIKE')
        ->sort('created', 'DESC')
        ->range(0, $limit)
        ->execute();
      foreach ($more ?: [] as $nid) {
        if (!in_array($nid, $found, TRUE)) {
          $found[] = $nid;
        }
        if (count($found) >= $limit) {
          break;
        }
      }
    }

    $items = [];
    foreach ($storage->loadMultiple($found) as $node) {
      if (!$node instanceof NodeInterface) {
        continue;
      }
      $tl = $node->hasField('field_trust_level') && !$node->get('field_trust_level')->isEmpty()
        ? (string) $node->get('field_trust_level')->value
        : 'l0_aggregate';
      $excerpt = '';
      if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
        $raw = (string) ($node->get('body')->summary ?: $node->get('body')->value);
        $excerpt = mb_substr(trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, 160);
      }
      $source = '';
      if ($node->hasField('field_source_name') && !$node->get('field_source_name')->isEmpty()) {
        $source = (string) $node->get('field_source_name')->value;
      }
      $items[] = [
        'nid' => (int) $node->id(),
        'title' => $node->label(),
        'excerpt' => $excerpt,
        'source_name' => $source,
        'badge_label' => xmt_trust_badge_label($tl),
        'badge_class' => xmt_trust_badge_class($tl),
        'detail_url' => Url::fromRoute('xmt_trust_ui.short_read_detail', ['node' => $node->id()])->toString(),
        'immerse_url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
          'query' => ['mode' => 'immerse', 'focus' => (int) $node->id()],
        ])->toString(),
        'created_label' => \Drupal::service('date.formatter')->format($node->getCreatedTime(), 'short'),
      ];
    }
    return $items;
  }

  protected function escapeLike(string $q): string {
    return addcslashes($q, '\\%_');
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  protected function filters(string $q, string $active): array {
    $map = [
      'trusted' => $this->t('综合'),
      'official' => $this->t('官方'),
      'enterprise' => $this->t('企业'),
      'aggregate' => $this->t('汇聚'),
    ];
    $out = [];
    foreach ($map as $key => $label) {
      $query = array_filter([
        'q' => $q !== '' ? $q : NULL,
        'level' => $key === 'trusted' ? NULL : $key,
      ]);
      $out[] = [
        'key' => $key,
        'label' => (string) $label,
        'active' => $active === $key,
        'url' => Url::fromRoute('xmt_trust_ui.search', [], ['query' => $query])->toString(),
      ];
    }
    return $out;
  }

}
