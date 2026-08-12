<?php

namespace Drupal\xmt_publisher\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Enterprise publisher certification application form.
 */
class PublisherApplyForm extends FormBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'xmt_publisher_apply_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Organization name'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $form['credit_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Unified social credit / registration code'),
      '#required' => TRUE,
      '#maxlength' => 64,
    ];
    $form['website'] = [
      '#type' => 'url',
      '#title' => $this->t('Website'),
    ];
    $form['contact_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact name'),
      '#required' => TRUE,
    ];
    $form['contact_mail'] = [
      '#type' => 'email',
      '#title' => $this->t('Contact email'),
      '#required' => TRUE,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit application'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $storage = $this->entityTypeManager->getStorage('xmt_publisher');
    /** @var \Drupal\xmt_publisher\Entity\Publisher $publisher */
    $publisher = $storage->create([
      'name' => $form_state->getValue('name'),
      'type' => 'enterprise',
      'status' => 'pending',
      'credit_code' => $form_state->getValue('credit_code'),
      'website' => $form_state->getValue('website') ?: '',
      'contact_name' => $form_state->getValue('contact_name'),
      'contact_mail' => $form_state->getValue('contact_mail'),
      'uid' => $this->currentUser->id(),
    ]);
    $publisher->save();
    $this->messenger()->addStatus($this->t('Your application has been submitted and is pending review.'));
    $form_state->setRedirect('<front>');
  }

}
