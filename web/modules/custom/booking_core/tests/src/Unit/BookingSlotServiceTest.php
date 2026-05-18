<?php

namespace Drupal\Tests\booking_core\Unit;

use Drupal\booking_core\BookingSlotService;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(BookingSlotService::class)]
#[Group('booking_core')]
class BookingSlotServiceTest extends UnitTestCase {

  private function makeService(): BookingSlotService {
    $config_factory   = $this->createMock(\Drupal\Core\Config\ConfigFactoryInterface::class);
    $entity_type_mgr  = $this->createMock(\Drupal\Core\Entity\EntityTypeManagerInterface::class);
    return new BookingSlotService($config_factory, $entity_type_mgr);
  }

  public function testGeneratesSlotsForFullDay(): void {
    $service = $this->makeService();
    $slots   = $service->generateSlots('2099-06-02', '09:00', '17:00', 60);

    $this->assertCount(8, $slots);
    $this->assertArrayHasKey('09:00', $slots);
    $this->assertArrayHasKey('16:00', $slots);
    $this->assertArrayNotHasKey('17:00', $slots);
  }

  public function testExcludesBookedTimes(): void {
    $service = $this->makeService();
    $slots   = $service->generateSlots('2099-06-02', '09:00', '11:00', 60, ['09:00']);

    $this->assertArrayNotHasKey('09:00', $slots);
    $this->assertArrayHasKey('10:00', $slots);
  }

  public function testReturnsEmptyWhenAllBooked(): void {
    $service = $this->makeService();
    $slots   = $service->generateSlots('2099-06-02', '09:00', '10:00', 60, ['09:00']);

    $this->assertEmpty($slots);
  }

  public function testThirtyMinuteSlots(): void {
    $service = $this->makeService();
    $slots   = $service->generateSlots('2099-06-02', '09:00', '10:00', 30);

    $this->assertCount(2, $slots);
    $this->assertArrayHasKey('09:00', $slots);
    $this->assertArrayHasKey('09:30', $slots);
  }

}
