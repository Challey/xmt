<?php

namespace Drupal\xmt_publisher;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * List builder for publishers.
 */
class PublisherListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name'),
      'type' => $this->t('Type'),
      'status' => $this->t('Status'),
      'uid' => $this->t('Owner'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\xmt_publisher\Entity\Publisher $entity */
    $row['id'] = $entity->id();
    $row['name'] = Link::fromTextAndUrl($entity->label(), $entity->toUrl('canonical'));
    $row['type'] = $entity->get('type')->value;
    $row['status'] = $entity->get('status')->value;
    $owner = $entity->getOwner();
    $row['uid'] = $owner ? $owner->getAccountName() : '-';
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity): array {
    $operations = parent::getDefaultOperations($entity);
    if ($entity->get('status')->value === 'pending') {
      $operations['approve'] = [
        'title' => $this->t('Approve'),
        'weight' => 5,
        'url' => Url::fromRoute('xmt_publisher.approve', ['xmt_publisher' => $entity->id()]),
      ];
      $operations['reject'] = [
        'title' => $this->t('Reject'),
        'weight' => 6,
        'url' => Url::fromRoute('xmt_publisher.reject', ['xmt_publisher' => $entity->id()]),
      ];
    }
    return $operations;
  }

}
