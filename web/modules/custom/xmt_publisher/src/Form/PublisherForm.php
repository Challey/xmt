<?php

namespace Drupal\xmt_publisher\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for creating/editing publishers (admin).
 */
class PublisherForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $entity = $this->getEntity();
    $this->messenger()->addStatus($this->t('Saved publisher %label.', ['%label' => $entity->label()]));
    $form_state->setRedirectUrl($entity->toUrl('collection'));
    return $result;
  }

}
