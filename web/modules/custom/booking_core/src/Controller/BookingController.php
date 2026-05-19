<?php

namespace Drupal\booking_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;
use Drupal\booking_core\BookingInterface;

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
      $rows[] = [
        $booking->getName(),
        $booking->getEmail(),
        $booking->getService() ?: '—',
        $this->formatDate($booking->getDate()),
        [
          'data' => [
            '#type'  => 'operations',
            '#links' => [
              'view' => [
                'title' => $this->t('View'),
                'url'   => Url::fromRoute('booking_core.admin_view', ['booking' => $booking->id()]),
              ],
              'delete' => [
                'title' => $this->t('Delete'),
                'url'   => Url::fromRoute('booking_core.admin_delete', ['booking' => $booking->id()]),
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

  public function adminView(BookingInterface $booking): array {
    $rows = [
      [['data' => $this->t('Name'),    'header' => TRUE], $booking->getName()],
      [['data' => $this->t('Email'),   'header' => TRUE], $booking->getEmail()],
      [['data' => $this->t('Phone'),   'header' => TRUE], $booking->getPhone() ?: '—'],
      [['data' => $this->t('Service'), 'header' => TRUE], $booking->getService() ?: '—'],
      [['data' => $this->t('Date'),    'header' => TRUE], $this->formatDate($booking->getDate())],
      [['data' => $this->t('Notes'),   'header' => TRUE], $booking->getNotes() ?: '—'],
    ];

    return [
      'details' => [
        '#type'    => 'table',
        '#rows'    => $rows,
        '#caption' => $this->t('Booking details'),
      ],
      'actions' => [
        '#type'   => 'container',
        'back'    => [
          '#type'       => 'link',
          '#title'      => $this->t('← All bookings'),
          '#url'        => Url::fromRoute('booking_core.admin_list'),
          '#attributes' => ['class' => ['button']],
          '#suffix'     => ' ',
        ],
        'delete'  => [
          '#type'       => 'link',
          '#title'      => $this->t('Delete booking'),
          '#url'        => Url::fromRoute('booking_core.admin_delete', ['booking' => $booking->id()]),
          '#attributes' => ['class' => ['button', 'button--danger']],
        ],
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
