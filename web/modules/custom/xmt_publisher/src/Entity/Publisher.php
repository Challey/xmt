<?php

namespace Drupal\xmt_publisher\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\views\EntityViewsData;
use Drupal\xmt_publisher\Form\PublisherDeleteForm;
use Drupal\xmt_publisher\Form\PublisherForm;
use Drupal\xmt_publisher\PublisherAccessControlHandler;
use Drupal\xmt_publisher\PublisherListBuilder;

/**
 * Trusted publisher (official or enterprise).
 */
#[ContentEntityType(
  id: 'xmt_publisher',
  label: new TranslatableMarkup('Publisher'),
  label_collection: new TranslatableMarkup('Publishers'),
  label_singular: new TranslatableMarkup('publisher'),
  label_plural: new TranslatableMarkup('publishers'),
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'name',
    'owner' => 'uid',
    'uid' => 'uid',
    'created' => 'created',
    'changed' => 'changed',
  ],
  handlers: [
    'storage' => SqlContentEntityStorage::class,
    'list_builder' => PublisherListBuilder::class,
    'views_data' => EntityViewsData::class,
    'access' => PublisherAccessControlHandler::class,
    'form' => [
      'default' => PublisherForm::class,
      'add' => PublisherForm::class,
      'edit' => PublisherForm::class,
      'delete' => PublisherDeleteForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
      'default' => DefaultHtmlRouteProvider::class,
    ],
    'view_builder' => 'Drupal\Core\Entity\EntityViewBuilder',
  ],
  links: [
    'collection' => '/admin/xmt/publishers',
    'add-form' => '/admin/xmt/publishers/add',
    'edit-form' => '/admin/xmt/publishers/{xmt_publisher}/edit',
    'delete-form' => '/admin/xmt/publishers/{xmt_publisher}/delete',
    'canonical' => '/publisher/{xmt_publisher}',
  ],
  admin_permission: 'administer xmt publishers',
  base_table: 'xmt_publisher',
  label_count: [
    'singular' => '@count publisher',
    'plural' => '@count publishers',
  ],
)]
class Publisher extends ContentEntityBase implements EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Name'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'string', 'weight' => -10])
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => -10])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Type'))
      ->setRequired(TRUE)
      ->setDefaultValue('enterprise')
      ->setSetting('allowed_values', [
        'official' => 'Official',
        'enterprise' => 'Enterprise',
      ])
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'list_default', 'weight' => -5])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => -5])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Status'))
      ->setRequired(TRUE)
      ->setDefaultValue('pending')
      ->setSetting('allowed_values', [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'suspended' => 'Suspended',
      ])
      ->setDisplayOptions('view', ['label' => 'inline', 'type' => 'list_default', 'weight' => 0])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['credit_code'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Credit / registration code'))
      ->setSetting('max_length', 64)
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 5])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['website'] = BaseFieldDefinition::create('uri')
      ->setLabel(new TranslatableMarkup('Website'))
      ->setDisplayOptions('form', ['type' => 'uri', 'weight' => 6])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['contact_name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Contact name'))
      ->setSetting('max_length', 128)
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 7])
      ->setDisplayConfigurable('form', TRUE);

    $fields['contact_mail'] = BaseFieldDefinition::create('email')
      ->setLabel(new TranslatableMarkup('Contact email'))
      ->setDisplayOptions('form', ['type' => 'email_default', 'weight' => 8])
      ->setDisplayConfigurable('form', TRUE);

    $fields['dx_developer_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('DrupalX developer ID'))
      ->setSetting('max_length', 64)
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 9])
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'));

    return $fields;
  }

}
