<?php

namespace Drupal\booking_core;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;

/**
 * View builder for Booking entities.
 */
class BookingViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL): array {
    /** @var \Drupal\booking_core\BookingInterface $entity */
    $site_tz = \Drupal::config('system.date')->get('timezone.default') ?: 'UTC';
    $dt      = new DrupalDateTime($entity->getDate(), 'UTC');
    $dt->setTimezone(new \DateTimeZone($site_tz));
    $formatted = $dt->format('D d M Y \a\t H:i');

    $rows = [
      [['data' => t('Name'), 'header' => TRUE], $entity->getName()],
      [['data' => t('Email'), 'header' => TRUE], $entity->getEmail()],
      [['data' => t('Phone'), 'header' => TRUE], $entity->getPhone() ?: '—'],
      [['data' => t('Service'), 'header' => TRUE], $entity->getService() ?: '—'],
      [['data' => t('Date'), 'header' => TRUE], $formatted],
      [['data' => t('Notes'), 'header' => TRUE], $entity->getNotes() ?: '—'],
    ];

    return [
      '#type'  => 'table',
      '#rows'  => $rows,
    ];
  }

}
