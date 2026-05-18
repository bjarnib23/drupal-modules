<?php

namespace Drupal\booking_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\booking_core\Entity\Booking;

/**
 * Controller for booking admin pages and the thank-you page.
 */
class BookingController extends ControllerBase {

  public function thankYou(): array {
    return [
      '#markup' => $this->t('Your booking has been received. We will be in touch shortly.'),
    ];
  }

  public function adminList(): array {
    $ids = $this->entityTypeManager()->getStorage('booking')->getQuery()
      ->sort('date', 'ASC')
      ->accessCheck(FALSE)
      ->execute();

    $bookings = $this->entityTypeManager()->getStorage('booking')->loadMultiple($ids);
    $rows     = [];

    foreach ($bookings as $booking) {
      $formatted = $this->formatDate($booking->get('date')->value);

      $rows[] = [
        $booking->get('name')->value,
        $booking->get('email')->value,
        $booking->get('service')->value ?: '—',
        $formatted,
        [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'view' => [
                'title' => $this->t('View'),
                'url' => Url::fromRoute('booking_core.admin_view', ['booking' => $booking->id()]),
              ],
              'delete' => [
                'title' => $this->t('Delete'),
                'url' => Url::fromRoute('booking_core.admin_delete', ['booking' => $booking->id()]),
              ],
            ],
          ],
        ],
      ];
    }

    return [
      '#type'   => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Email'),
        $this->t('Service'),
        $this->t('Date'),
        $this->t('Actions'),
      ],
      '#rows'   => $rows,
      '#empty'  => $this->t('No bookings yet.'),
    ];
  }

  public function adminView(Booking $booking): array {
    $fields = [
      $this->t('Name')    => $booking->get('name')->value,
      $this->t('Email')   => $booking->get('email')->value,
      $this->t('Phone')   => $booking->get('phone')->value ?: '—',
      $this->t('Service') => $booking->get('service')->value ?: '—',
      $this->t('Date')    => $this->formatDate($booking->get('date')->value),
      $this->t('Notes')   => $booking->get('notes')->value ?: '—',
    ];

    $rows = [];
    foreach ($fields as $label => $value) {
      $rows[] = [
        ['data' => $label, 'header' => TRUE],
        $value,
      ];
    }

    return [
      'details' => [
        '#type'    => 'table',
        '#rows'    => $rows,
        '#caption' => $this->t('Booking details'),
      ],
      'actions' => [
        '#type'  => 'container',
        'back'   => Link::fromTextAndUrl(
          $this->t('← All bookings'),
          Url::fromRoute('booking_core.admin_list')
        )->toRenderable(),
        'delete' => Link::fromTextAndUrl(
          $this->t('Delete booking'),
          Url::fromRoute('booking_core.admin_delete', ['booking' => $booking->id()])
        )->toRenderable(),
      ],
    ];
  }

  private function formatDate(?string $date): string {
    if (!$date) {
      return '—';
    }
    $site_tz = $this->config('system.date')->get('timezone.default') ?: 'UTC';
    $dt      = new DrupalDateTime($date, 'UTC');
    $dt->setTimezone(new \DateTimeZone($site_tz));
    return $dt->format('D d M Y \a\t H:i');
  }

}
