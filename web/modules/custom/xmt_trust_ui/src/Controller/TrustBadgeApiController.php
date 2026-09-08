<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\xmt_publisher\Entity\Publisher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public JSON badge endpoints for embed / verification.
 */
class TrustBadgeApiController extends ControllerBase {

  /**
   * Badge payload for a published article node.
   */
  public function node(int $nid): JsonResponse {
    $node = $this->entityTypeManager()->getStorage('node')->load($nid);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'article' || !$node->isPublished()) {
      return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
    }
    if (!$node->access('view')) {
      return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
    }

    $level = $node->hasField('field_trust_level') && !$node->get('field_trust_level')->isEmpty()
      ? (string) $node->get('field_trust_level')->value
      : 'l0_aggregate';

    $publisher = NULL;
    $publisher_id = NULL;
    $publisher_name = NULL;
    $publisher_status = NULL;
    if ($node->hasField('field_publisher') && !$node->get('field_publisher')->isEmpty()) {
      $entity = $node->get('field_publisher')->entity;
      if ($entity instanceof Publisher) {
        $publisher = $entity;
        $publisher_id = (int) $entity->id();
        $publisher_name = $entity->label();
        $publisher_status = $entity->get('status')->value;
      }
    }

    $provenance = NULL;
    if ($node->hasField('field_provenance_hash') && !$node->get('field_provenance_hash')->isEmpty()) {
      $provenance = (string) $node->get('field_provenance_hash')->value;
    }

    $source_url = NULL;
    if ($node->hasField('field_source_url') && !$node->get('field_source_url')->isEmpty()) {
      $source_url = $node->get('field_source_url')->uri ?? NULL;
    }

    $payload = [
      'schema' => 'xmt.badge.v1',
      'type' => 'article',
      'nid' => (int) $node->id(),
      'title' => $node->label(),
      'url' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
      'short_read_url' => Url::fromRoute('xmt_trust_ui.short_read_detail', ['node' => $node->id()], ['absolute' => TRUE])->toString(),
      'share_image_url' => Url::fromRoute('xmt_trust_ui.short_read_share', ['node' => $node->id()], ['absolute' => TRUE])->toString(),
      'trust_level' => $level,
      'badge_label' => xmt_trust_badge_label($level),
      'badge_class' => xmt_trust_badge_class($level),
      'publisher_id' => $publisher_id,
      'publisher_name' => $publisher_name,
      'publisher_status' => $publisher_status,
      'publisher_url' => $publisher ? $publisher->toUrl('canonical', ['absolute' => TRUE])->toString() : NULL,
      'provenance_hash' => $provenance,
      'source_url' => $source_url,
      'changed' => (int) $node->getChangedTime(),
    ];

    $response = new JsonResponse($payload);
    $response->headers->set('Cache-Control', 'public, max-age=60');
    $response->headers->set('Access-Control-Allow-Origin', '*');
    return $response;
  }

  /**
   * Badge payload for a publisher entity.
   */
  public function publisher(int $xmt_publisher): JsonResponse {
    $entity = $this->entityTypeManager()->getStorage('xmt_publisher')->load($xmt_publisher);
    if (!$entity instanceof Publisher) {
      return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
    }
    if (!$entity->access('view')) {
      return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
    }

    $status = (string) $entity->get('status')->value;
    $type = (string) $entity->get('type')->value;
    $level = $type === 'official' ? 'l1_official' : 'l2_enterprise';
    if ($status !== 'approved') {
      $level = 'l0_aggregate';
    }

    $payload = [
      'schema' => 'xmt.badge.v1',
      'type' => 'publisher',
      'publisher_id' => (int) $entity->id(),
      'name' => $entity->label(),
      'publisher_type' => $type,
      'status' => $status,
      'url' => $entity->toUrl('canonical', ['absolute' => TRUE])->toString(),
      'trust_level' => $level,
      'badge_label' => xmt_trust_badge_label($level),
      'badge_class' => xmt_trust_badge_class($level),
      'credit_code' => $entity->get('credit_code')->value ?: NULL,
      'website' => $entity->get('website')->value ?: NULL,
      'dx_developer_id' => $entity->get('dx_developer_id')->value ?: NULL,
    ];

    $response = new JsonResponse($payload);
    $response->headers->set('Cache-Control', 'public, max-age=60');
    $response->headers->set('Access-Control-Allow-Origin', '*');
    return $response;
  }

}
