<?php

namespace Drupal\booking_core;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Generates and validates available booking time slots.
 */
class BookingSlotService {

  public function __construct(
    private ConfigFactoryInterface $configFactory,
    private EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns available time slots for a given date.
   *
   * @return array<string, string> Keyed by time string (H:i), e.g. ['09:00' => '09:00']
   */
  public function getAvailableSlots(?string $date): array {
    if (!$date) {
      return [];
    }

    $config        = $this->configFactory->get('booking_core.settings');
    $open_days     = $config->get('open_days') ?? [1, 2, 3, 4, 5];
    $open_time     = $config->get('open_time') ?? '09:00';
    $close_time    = $config->get('close_time') ?? '17:00';
    $slot_duration = (int) ($config->get('slot_duration') ?? 30);
    $weeks_ahead   = (int) ($config->get('weeks_ahead') ?? 4);

    $utc         = new \DateTimeZone('UTC');
    $dt          = new \DateTime($date . ' 00:00:00', $utc);
    $day_of_week = (int) $dt->format('w');
    if (!in_array($day_of_week, array_map('intval', $open_days))) {
      return [];
    }

    $today    = new \DateTime('now', $utc);
    $max_date = new \DateTime("+{$weeks_ahead} weeks", $utc);
    if ($dt <= $today || $dt > $max_date) {
      return [];
    }

    $blocked_periods = $config->get('blocked_periods') ?? [];
    $blocked_ranges  = [];
    foreach ($blocked_periods as $period) {
      if (($period['date'] ?? '') !== $date) {
        continue;
      }
      if (!empty($period['all_day'])) {
        return [];
      }
      if (!empty($period['start_time']) && !empty($period['end_time'])) {
        $blocked_ranges[] = ['start' => $period['start_time'], 'end' => $period['end_time']];
      }
    }

    $booked_times = $this->getBookedTimes($date);

    return $this->generateSlots($date, $open_time, $close_time, $slot_duration, $booked_times, $blocked_ranges);
  }

  /**
   * Returns booked time strings (H:i) for a given date.
   *
   * @return string[]
   */
  public function getBookedTimes(string $date): array {
    $ids = $this->entityTypeManager->getStorage('booking')->getQuery()
      ->condition('date', $date . 'T', 'STARTS_WITH')
      ->accessCheck(FALSE)
      ->execute();

    if (!$ids) {
      return [];
    }

    $times = [];
    foreach ($this->entityTypeManager->getStorage('booking')->loadMultiple($ids) as $b) {
      $times[] = substr($b->get('date')->value, 11, 5);
    }

    return $times;
  }

  /**
   * Generates all slots between open and close time, excluding booked/blocked ones.
   *
   * @param string[] $booked_times
   * @param array<array{start: string, end: string}> $blocked_ranges
   * @return array<string, string>
   */
  public function generateSlots(string $date, string $open_time, string $close_time, int $slot_duration, array $booked_times = [], array $blocked_ranges = []): array {
    $utc    = new \DateTimeZone('UTC');
    $slots  = [];
    $cursor = new \DateTime($date . ' ' . $open_time, $utc);
    $end    = new \DateTime($date . ' ' . $close_time, $utc);

    while ($cursor < $end) {
      $time = $cursor->format('H:i');
      if (!in_array($time, $booked_times) && !$this->isBlocked($time, $blocked_ranges)) {
        $slots[$time] = $time;
      }
      $cursor->modify("+{$slot_duration} minutes");
    }

    return $slots;
  }

  /**
   * Returns TRUE if the given time falls within any blocked range.
   *
   * @param array<array{start: string, end: string}> $blocked_ranges
   */
  private function isBlocked(string $time, array $blocked_ranges): bool {
    foreach ($blocked_ranges as $range) {
      if ($time >= $range['start'] && $time < $range['end']) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
