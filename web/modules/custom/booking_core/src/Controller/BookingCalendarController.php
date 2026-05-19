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

    $blocked_periods = $this->config('booking_core.settings')->get('blocked_periods') ?? [];
    foreach ($blocked_periods as $period) {
      if (empty($period['date'])) {
        continue;
      }
      $event = [
        'display' => 'background',
        'color'   => '#ef4444',
        'title'   => $period['reason'] ?? $this->t('Blocked')->render(),
      ];
      if (!empty($period['all_day'])) {
        // FullCalendar all-day end is exclusive, so add one day.
        $end_dt      = new \DateTime($period['date']);
        $end_dt->modify('+1 day');
        $event['start'] = $period['date'];
        $event['end']   = $end_dt->format('Y-m-d');
        $event['allDay'] = TRUE;
      }
      else {
        $event['start'] = $period['date'] . 'T' . ($period['start_time'] ?? '00:00');
        $event['end']   = $period['date'] . 'T' . ($period['end_time'] ?? '23:59');
      }
      $events[] = $event;
    }

    return new JsonResponse($events);
  }

}
