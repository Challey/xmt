<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Admin listing of recent articles with provenance metadata.
 */
class ProvenanceAuditController extends ControllerBase {

  /**
   * Lists recent trusted articles for audit.
   */
  public function list(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $nids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->sort('changed', 'DESC')
      ->range(0, 50)
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
      $nodes = $storage->loadMultiple($nids);
      foreach ($nodes as $node) {
        $rows[] = $this->buildRow($node);
      }
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No articles found.'),
      '#attributes' => ['class' => ['xmt-provenance-audit']],
    ];
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

    $provenance = $node->hasField('field_provenance_hash') && !$node->get('field_provenance_hash')->isEmpty()
      ? $node->get('field_provenance_hash')->value
      : '—';

    $source = '—';
    if ($node->hasField('field_source_url') && !$node->get('field_source_url')->isEmpty()) {
      $uri = $node->get('field_source_url')->uri ?? $node->get('field_source_url')->value ?? '';
      if ($uri !== '') {
        $source = Link::fromTextAndUrl($uri, Url::fromUri($uri))->toString();
      }
    }

    $updated = Drupal::service('date.formatter')->format($node->getChangedTime(), 'short');

    return [$title, $level, $publisher, $provenance, ['data' => ['#markup' => $source]], $updated];
  }

}
