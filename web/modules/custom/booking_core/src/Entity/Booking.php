<?php

namespace Drupal\booking_core\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[ContentEntityType(
  id: 'booking',
  label: new TranslatableMarkup('Booking'),
  label_collection: new TranslatableMarkup('Bookings'),
  handlers: [
    'storage' => 'Drupal\Core\Entity\Sql\SqlContentEntityStorage',
  ],
  base_table: 'booking',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'name',
  ],
  links: [
    'canonical' => '/admin/bookings/{booking}',
    'delete-form' => '/admin/bookings/{booking}/delete',
    'collection' => '/admin/bookings',
  ],
)]
class Booking extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Full name'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['email'] = BaseFieldDefinition::create('email')
      ->setLabel(new TranslatableMarkup('Email'))
      ->setRequired(TRUE);

    $fields['phone'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Phone'))
      ->setSetting('max_length', 50);

    $fields['date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Appointment date'))
      ->setRequired(TRUE);

    $fields['service'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Service'))
      ->setSetting('max_length', 255);

    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Notes'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'));

    return $fields;
  }

}
