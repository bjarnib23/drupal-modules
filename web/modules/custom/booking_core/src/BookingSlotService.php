<?php

namespace Drupal\booking_core;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

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

    $day_of_week = (int) date('w', strtotime($date));
    if (!in_array($day_of_week, array_map('intval', $open_days))) {
      return [];
    }

    $max_date = date('Y-m-d', strtotime("+{$weeks_ahead} weeks"));
    if ($date > $max_date || $date <= date('Y-m-d')) {
      return [];
    }

    $booked_times = $this->getBookedTimes($date);

    return $this->generateSlots($date, $open_time, $close_time, $slot_duration, $booked_times);
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
   * Generates all slots between open and close time, excluding booked ones.
   *
   * @param string[] $booked_times
   * @return array<string, string>
   */
  public function generateSlots(string $date, string $open_time, string $close_time, int $slot_duration, array $booked_times = []): array {
    $slots  = [];
    $cursor = strtotime($date . ' ' . $open_time);
    $end    = strtotime($date . ' ' . $close_time);

    while ($cursor < $end) {
      $time = date('H:i', $cursor);
      if (!in_array($time, $booked_times)) {
        $slots[$time] = $time;
      }
      $cursor += $slot_duration * 60;
    }

    return $slots;
  }

}
