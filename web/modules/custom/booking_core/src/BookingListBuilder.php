<?php

namespace Drupal\booking_core;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines the list builder for Booking entities.
 */
class BookingListBuilder extends EntityListBuilder {

  public function buildHeader(): array {
    $header['name']    = $this->t('Name');
    $header['email']   = $this->t('Email');
    $header['service'] = $this->t('Service');
    $header['date']    = $this->t('Date');
    return $header + parent::buildHeader();
  }

  public function getDefaultOperations(EntityInterface $entity): array {
    $operations = [];

    if ($entity->hasLinkTemplate('canonical')) {
      $operations['view'] = [
        'title'  => $this->t('View'),
        'weight' => 0,
        'url'    => $entity->toUrl('canonical'),
      ];
    }

    if ($entity->access('delete') && $entity->hasLinkTemplate('delete-form')) {
      $operations['delete'] = [
        'title'  => $this->t('Delete'),
        'weight' => 100,
        'url'    => $this->ensureDestination($entity->toUrl('delete-form')),
      ];
    }

    return $operations;
  }

  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\booking_core\BookingInterface $entity */
    $row['name']    = $entity->getName();
    $row['email']   = $entity->getEmail();
    $row['service'] = $entity->getService() ?: '—';
    $row['date']    = $entity->getDate() ? $this->formatDate($entity->getDate()) : '—';
    return $row + parent::buildRow($entity);
  }

  protected function getEntityIds(): array {
    return $this->getStorage()->getQuery()
      ->sort('date', 'ASC')
      ->accessCheck(FALSE)
      ->execute();
  }

  private function formatDate(string $date): string {
    $site_tz = \Drupal::config('system.date')->get('timezone.default') ?: 'UTC';
    $dt      = new DrupalDateTime($date, 'UTC');
    $dt->setTimezone(new \DateTimeZone($site_tz));
    return $dt->format('D d M Y \a\t H:i');
  }

}
