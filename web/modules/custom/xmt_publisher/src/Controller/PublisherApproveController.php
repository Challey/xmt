<?php

namespace Drupal\xmt_publisher\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xmt_publisher\Entity\Publisher;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Approve or reject publisher applications.
 */
class PublisherApproveController extends ControllerBase {

  /**
   * Approve a pending publisher.
   */
  public function approve(Publisher $xmt_publisher): RedirectResponse {
    $xmt_publisher->set('status', 'approved');
    $xmt_publisher->save();
    $uid = (int) $xmt_publisher->getOwnerId();
    if ($uid) {
      $user = $this->entityTypeManager()->getStorage('user')->load($uid);
      if ($user && !$user->hasRole('xmt_enterprise_publisher')) {
        $user->addRole('xmt_enterprise_publisher');
        $user->save();
      }
    }
    $this->messenger()->addStatus($this->t('Publisher @name approved.', ['@name' => $xmt_publisher->label()]));
    return new RedirectResponse($xmt_publisher->toUrl('collection')->toString());
  }

  /**
   * Reject a pending publisher.
   */
  public function reject(Publisher $xmt_publisher): RedirectResponse {
    $xmt_publisher->set('status', 'rejected');
    $xmt_publisher->save();
    $this->messenger()->addWarning($this->t('Publisher @name rejected.', ['@name' => $xmt_publisher->label()]));
    return new RedirectResponse($xmt_publisher->toUrl('collection')->toString());
  }

}
