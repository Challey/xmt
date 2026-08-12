<?php

namespace Drupal\xmt_publisher;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control for publishers.
 */
class PublisherAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\xmt_publisher\Entity\Publisher $entity */
    if ($account->hasPermission('administer xmt publishers')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    switch ($operation) {
      case 'view':
        if ($entity->get('status')->value === 'approved') {
          return AccessResult::allowedIfHasPermission($account, 'view xmt publisher');
        }
        return AccessResult::forbidden();

      case 'update':
      case 'delete':
        return AccessResult::allowedIf($entity->getOwnerId() === (int) $account->id() && $entity->get('status')->value === 'pending');

      default:
        return AccessResult::neutral();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'administer xmt publishers');
  }

}
