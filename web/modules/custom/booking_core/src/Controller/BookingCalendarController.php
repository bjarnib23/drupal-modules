<?php

namespace Drupal\booking_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for the booking calendar view and JSON feed.
 */
class BookingCalendarController extends ControllerBase {

  public function page(): array {
    return [
      '#markup' => '<div id="booking-calendar"></div>',
      '#attached' => [
        'library' => ['booking_core/booking_calendar'],
        'drupalSettings' => [
          'bookingCalendar' => [
            'feedUrl' => Url::fromRoute('booking_core.calendar_feed', [], ['absolute' => TRUE])->toString(),
          ],
        ],
      ],
    ];
  }

  public function feed(): JsonResponse {
    $ids = $this->entityTypeManager()->getStorage('booking')->getQuery()
      ->accessCheck(FALSE)
      ->execute();

    $events = [];
    foreach ($this->entityTypeManager()->getStorage('booking')->loadMultiple($ids) as $booking) {
      $date = $booking->getDate();
      if (!$date) {
        continue;
      }
      $title = $booking->getName();
      if ($booking->getService()) {
        $title .= ' — ' . $booking->getService();
      }
      $events[] = [
        'title' => $title,
        'start' => $date,
        'url'   => Url::fromRoute('entity.booking.canonical', ['booking' => $booking->id()], ['absolute' => TRUE])->toString(),
      ];
    }

    return new JsonResponse($events);
  }

}
